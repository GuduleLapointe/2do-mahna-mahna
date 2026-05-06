<?php

/**
 * Tests for the Region model.
 *
 * Default level: Constructor URL parsing and teleportLink formatting — pure string
 * manipulation via opensim_sanitize_uri / opensim_format_tp, no network I/O.
 * Fake hostnames are fine here.
 *
 * Extended level: data(), online(), link_region(), get_region() — real XML-RPC
 * calls. Requires TEST_GRID in tests/.env; skipped (not failed) when absent.
 * If TEST_GRID is set and the grid is unreachable the tests fail as expected.
 *
 * Live level: covered by bin/aggregator.php and dev/smoke-test.sh.
 */

require_once APP_DIR . "/app/Shared/Cache.php";
require_once APP_DIR . "/app/Models/class-region.php";

if (!defined("DEFAULT_POS")) {
	define("DEFAULT_POS", [128, 128, 25]);
}

// ---------------------------------------------------------------------------

describe("Constructor URL parsing", function () {
	test("empty URL gives empty uri", function () {
		$region = new Region("");
		expect($region->uri)->toBe("");
		expect($region->host)->toBe("");
	});

	test("null URL falls back to grid", function () {
		$region = new Region("", "http://yourgrid.org:8002");
		expect($region->host)->toBe("yourgrid.org");
		expect($region->port)->toBe(8002);
	});

	test("bare 'host:port Region' get all fields set", function () {
		$region = new Region("yourgrid.org:8002 Welcome");
		expect($region->host)->toBe("yourgrid.org");
		expect($region->port)->toBe(8002);
		expect($region->region)->toBe("Welcome");
		expect($region->gatekeeperURL)->toBe("http://yourgrid.org:8002");
		expect($region->uri)->toBe("yourgrid.org:8002/welcome");
	});

	test("http://host:port/Region same uri as bare", function () {
		$bare = new Region("yourgrid.org:8002 Welcome");
		$http = new Region("http://yourgrid.org:8002/Welcome");
		expect($http->host)->toBe($bare->host);
		expect($http->port)->toBe($bare->port);
		expect($http->region)->toBe($bare->region);
	});

	test("hop://host:port/Region same host/port/region as bare", function () {
		$bare = new Region("yourgrid.org:8002 Welcome");
		$hop = new Region("hop://yourgrid.org:8002/Welcome");
		expect($hop->host)->toBe($bare->host);
		expect($hop->port)->toBe($bare->port);
		expect($hop->region)->toBe($bare->region);
	});

	test(
		"position properly detected in URL and converted to float array",
		function () {
			$region = new Region("yourgrid.org:8002 Welcome/64/32/10");
			expect($region->pos)->toBe([64.0, 32.0, 10.0]);
			$region = new Region("yourgrid.org:8002 Welcome/64.5/32.2500/10.0");
			expect($region->pos)->toBe([64.5, 32.25, 10]);

			expect($region->url)->toContain("64");
			expect($region->url)->toContain("32");
			expect($region->url)->toContain("10");
		},
	);

	test("no position in URL properly results in empty array", function () {
		$region = new Region("yourgrid.org:8002 Welcome");
		expect($region->pos)->toBe([]);
		expect($region->url)->not->toMatch("#/\d+/\d+#");
	});

	test(
		"url is set by constructor — globalPos null before data()",
		function () {
			$region = new Region("yourgrid.org:8002 Welcome");
			expect($region->url)->not->toBeEmpty();
			expect($region->globalPos)->toBeNull();
		},
	);

	test("region name with spaces", function () {
		$region = new Region("yourgrid.org:8002 Grand Place");
		expect($region->region)->toBe("Grand Place");
		$region = new Region("yourgrid.org:8002:Grand Place");
		expect($region->region)->toBe("Grand Place");
		$region = new Region("yourgrid.org:8002/Grand Place");
		expect($region->region)->toBe("Grand Place");
		$region = new Region("yourgrid.org:8002/Grand+Place");
		expect($region->region)->toBe("Grand Place");
		$region = new Region("yourgrid.org:8002/Grand_Place");
		expect($region->region)->toBe("Grand Place");
		$region = new Region("yourgrid.org:8002/Grand%20Place");
		expect($region->region)->toBe("Grand Place");
	});

	test("gatekeeperURL is http://host:port", function () {
		$region = new Region("yourgrid.org:8002 Welcome");
		expect($region->gatekeeperURL)->toBe("http://yourgrid.org:8002");
	});
});

// ---------------------------------------------------------------------------

describe("teleportLink()", function () {
	test("teleportLink() succeeds with no args", function () {
		$region = new Region("yourgrid.org:8002 Welcome");
		$link = $region->teleportLink();
		expect($link)->toContain("yourgrid.org:8002");
		expect($link)->toContain("Welcome");
		expect($link)->toStartWith("nothing://");
		// uri is lowercased (canonical cache key); region name in link follows suit
		expect(strtolower($link))->toContain("welcome");
	});

	test("pos override — builds link from uri + given pos", function () {
		$region = new Region("yourgrid.org:8002 Welcome");
		$link = $region->teleportLink([64, 32, 10]);
		expect($link)->toContain("64");
		expect($link)->toContain("32");
		expect($link)->toContain("10");
	})->depends("teleportLink() succeeds with no args");

	test("TPLINK_TXT format", function () {
		$region = new Region("yourgrid.org:8002 Welcome");
		$link = $region->teleportLink(null, TPLINK_TXT);
		expect($link)->toContain("yourgrid.org:8002");
		expect($link)->not->toStartWith("hop://");
	})->depends("teleportLink() succeeds with no args");

	test("empty uri — returns empty string", function () {
		$region = new Region("");
		expect($region->teleportLink())->toBe("");
	})->depends("teleportLink() succeeds with no args");
});

// ---------------------------------------------------------------------------

describe("extended (requires TEST_GRID)", function () {
	beforeEach(function () {
		$grid = Config::get("test_grid");
		if (empty($grid)) {
			test()->markTestSkipped("TEST_GRID not set in tests/.env");
		}
		$regionName = Config::get("test_region") ?: "";
		$url = "http://$grid" . ($regionName ? "/$regionName" : "");
		$this->region = new Region($url);
		$this->grid = $grid;
		$this->regionName = $regionName;
	});

	// link_region() with no region name = default landing region.
	// All other extended tests depend on this succeeding.
	test("link_region() — default region reachable", function () {
		$bare = new Region("http://" . $this->grid);
		$result = $bare->link_region();
		expect($result)->toBeArray()->toHaveKey("uuid");
		passed("Region link_region reachable");
	});

	test("link_region() with region name", function () {
		if (empty($this->regionName)) {
			test()->markTestSkipped("TEST_REGION not set in tests/.env");
		}
		$result = $this->region->link_region();
		expect($result)->toBeArray()->toHaveKey("uuid");
	})->depends("link_region() — default region reachable");

	test("get_region() returns region details", function () {
		$bare = new Region("http://" . $this->grid);
		$result = $bare->get_region();
		expect($result)->toBeArray()->toHaveKey("uuid");
		expect($result)->toHaveKey("x");
		expect($result)->toHaveKey("y");
	})->depends("link_region() — default region reachable");

	test("data() returns array and populates regionname", function () {
		$data = $this->region->data();
		expect($data)->toBeArray()->not->toBeEmpty();
		expect($this->region->regionName)->not->toBeEmpty();
		passed("Region data fetched");
	})->depends("link_region() — default region reachable");

	test("data() populates globalPos as float[3]", function () {
		$this->region->data();
		expect($this->region->globalPos)->toBeArray()->toHaveCount(3);
		foreach ($this->region->globalPos as $coord) {
			expect($coord)->toBeFloat();
		}
	})->depends("data() returns array and populates regionname");

	test("data() sets regionUUID", function () {
		$this->region->data();
		expect($this->region->regionUUID)->toMatch("/^[0-9a-f\-]{36}$/i");
	})->depends("data() returns array and populates regionname");

	test("online() returns boolean", function () {
		expect($this->region->online())->toBeBool();
	})->depends("link_region() — default region reachable");

	test("teleportLink() after data() is a valid hop:// link", function () {
		$this->region->data();
		$link = $this->region->teleportLink(null, TPLINK_HOP);
		expect($link)->toStartWith("hop://");
		// url is built from the lowercased uri; regionname from the server may
		// differ in case — verify the link contains the host and a region segment
		expect($link)->toContain($this->region->host);
		expect($link)->toMatch("#^hop://[^/]+/[^/]+/#");
	})->depends("data() returns array and populates regionname");
});
