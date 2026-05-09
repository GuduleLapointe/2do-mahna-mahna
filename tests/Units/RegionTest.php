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

describe("Test region setup", function () {
	beforeEach(function () {
		$this->test_grid = TEST_GRID ?: TEST_TMP_HOST . ":" . TEST_TMP_PORT;
		$this->regionName = TEST_REGION ?: TEST_TMP_REGION;
	});

	test("Test Grid config", function () {
		if (empty($this->test_grid)) {
			test()->markTestSkipped("TEST_GRID not set in tests/.env");
		}
		expect(true)->toBeTrue();
		passed("Test Grid config");
	});

	// Region object can be created from grid config, with or without
	// an active server
	test("Test Region instance", function () {
		if (empty($this->regionName)) {
			echo "TEST_REGION not set in tests/.env" . PHP_EOL;
			test()->markTestSkipped("TEST_REGION not set in tests/.env");
		}

		$url =
			"http://" .
			$this->test_grid .
			($this->regionName ? "/$this->regionName" : "");
		$this->region = new Region($url);
		expect($this->region)->toBeInstanceOf(Region::class);

		passed("Test Region instance");
	})->depends("Test Grid config");

	// An active server is required for functions querying the grid
	test("Test Grid running", function () {
		$status = httpStatus("http://" . $this->test_grid . "/get_grid_info");
		if (empty($status) || $status !== 200) {
			$this->markTestSkipped("TEST_GRID not reachable");
		}
		expect(true)->toBeTrue();
		passed("Test Grid running");
	})->depends("Test Region instance");
});

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
		expect($region->regionName)->toBe("Welcome");
		expect($region->gatekeeperURL)->toBe("http://yourgrid.org:8002");
		expect($region->uri)->toBe("yourgrid.org:8002/Welcome");
	});

	test("http://host:port/Region same uri as bare", function () {
		$bare = new Region("yourgrid.org:8002 Welcome");
		$http = new Region("http://yourgrid.org:8002/Welcome");
		expect($http->uri)->toBe($bare->uri);
		expect($http->dest_uri)->toBe($bare->dest_uri);
		expect($http->host)->toBe($bare->host);
		expect($http->port)->toBe($bare->port);
		expect($http->regionName)->toBe($bare->regionName);
	});

	test("hop://host:port/Region same host/port/region as bare", function () {
		$bare = new Region("yourgrid.org:8002 Welcome");
		$hop = new Region("hop://yourgrid.org:8002/Welcome");
		expect($bare->uri)->toBe($hop->uri);
		expect($bare->dest_uri)->toBe($hop->dest_uri);
		expect($hop->host)->toBe($bare->host);
		expect($hop->port)->toBe($bare->port);
		expect($hop->regionName)->toBe($bare->regionName);
	});

	test(
		"position properly detected in URL and converted to float array",
		function () {
			$region = new Region("yourgrid.org:8002 Welcome/64/32/10");
			expect($region->pos)->toBe([64.0, 32.0, 10.0]);
			$region = new Region("yourgrid.org:8002 Welcome/64.5/32.2500/10.0");
			expect($region->pos)->toBe([64.5, 32.25, 10.0]);

			expect($region->dest_uri)->toEndWith("/64.5/32.25/10");
		},
	);

	test("no position in URL properly results in empty array", function () {
		$region = new Region("yourgrid.org:8002 Welcome");
		expect($region->pos)->toBe([]);
		expect($region->dest_uri)->not->toMatch("#/\d+/\d+#");
	});

	test(
		"url is set by constructor — globalPos null before data()",
		function () {
			$region = new Region("yourgrid.org:8002 Welcome");
			expect($region->dest_uri)->not->toBeEmpty();
			expect($region->globalPos)->toBeNull();
		},
	);

	test("region name with spaces", function () {
		$region = new Region("yourgrid.org:8002 Grand Place");
		expect($region->regionName)->toBe("Grand Place");
		$region = new Region("yourgrid.org:8002:Grand Place");
		expect($region->regionName)->toBe("Grand Place");
		$region = new Region("yourgrid.org:8002/Grand Place");
		expect($region->regionName)->toBe("Grand Place");
		$region = new Region("yourgrid.org:8002/Grand+Place");
		expect($region->regionName)->toBe("Grand Place");
		$region = new Region("yourgrid.org:8002/Grand_Place");
		expect($region->regionName)->toBe("Grand Place");
		$region = new Region("yourgrid.org:8002/Grand%20Place");
		expect($region->regionName)->toBe("Grand Place");
	});

	test("gatekeeperURL is http://host:port", function () {
		$region = new Region("yourgrid.org:8002 Welcome");
		expect($region->gatekeeperURL)->toBe("http://yourgrid.org:8002");
	});
});

// DEPRECATED. teleportLink() is basically an alias of
// describe("teleportLink()", function () {
// 	test("teleportLink() with no args uses TPLINK_HOP", function () {
// 		$region = new Region("yourgrid.org:8002 Welcome");
// 		$teleportLink = $region->teleportLink();
// 		// Default format is TPLINK_HOP
// 		expect($teleportLink)->toStartWith("hop://");
// 		expect($teleportLink)->toContain("yourgrid.org:8002");
// 		expect($teleportLink)->toContain("Welcome");
// 	});

// 	test("pos override — builds link from uri + given pos", function () {
// 		$region = new Region("yourgrid.org:8002 Welcome");
// 		$teleportLink = $region->teleportLink([64, 32, 10]);
// 		expect($teleportLink)->toContain("64/32/10");
// 		$teleportLink = $region->teleportLink([64.25, "32.5000", 10.0]);
// 		expect($teleportLink)->toContain("64.25/32.5/10");
// 	})->depends("teleportLink() with no args uses TPLINK_HOP");

// 	test("TPLINK_HOP format", function () {
// 		$region = new Region("yourgrid.org:8002 Welcome");
// 		$teleportLink = $region->teleportLink(null, TPLINK_HOP);
// 		expect($teleportLink)->toStartWith("hop://");
// 		expect($teleportLink)->toContain("yourgrid.org:8002");
// 		expect($teleportLink)->not->toStartWith("http://");
// 	})->depends("teleportLink() with no args uses TPLINK_HOP");

// 	test("empty uri — returns empty string", function () {
// 		$region = new Region("");
// 		expect($region->teleportLink())->toBe("");
// 	})->depends("teleportLink() with no args uses TPLINK_HOP");
// });

describe("live helpers", function () {
	beforeEach(function () {
		requires("Test Grid running");
		$errors = [];
		if (empty(TEST_GRID ?? "")) {
			$errors[] = "TEST_GRID not set";
		}
		if (empty(TEST_REGION ?? "")) {
			$errors[] = "TEST_REGION not set";
		}
		if (!empty($errors)) {
			$this->markTestSkipped(implode(", ", $errors));
		}
		$this->region = new Region(TEST_REGION, TEST_GRID);
	});

	test("Region->link_region() method", function () {
		expect($this->region)->toBeInstanceOf(Region::class);
		expect(method_exists($this->region, "link_region"))->toBeTrue();
		passed("Region->link_region() method");
	});

	// link_region() with no region name = default landing region.
	// All other extended tests depend on this succeeding.
	test("Region->link_region() resolves default region", function () {
		$defaultRegion = new Region(TEST_GRID);
		$link_region = $defaultRegion->link_region();
		expect($link_region)
			->toBeArray()
			->toHaveKeys(["uuid", "external_name"]);
		passed("Region->link_region() resolves default region");
	});

	test("Region->link_region() resolves region name", function () {
		$testRegion = new Region(TEST_REGION, TEST_GRID);
		$link_region = $testRegion->link_region();
		expect($link_region)
			->toBeArray()
			->toHaveKeys(["uuid", "external_name"]);
		expect($link_region["external_name"])->toMatch(
			"#/ " . TEST_REGION . "$#",
		);
		passed("Region->link_region() resolves region name");
	})->depends("Region->link_region() method");

	test("Region->get_region() method", function () {
		$this->region = new Region(TEST_GRID);
		expect($this->region)->toBeInstanceOf(Region::class);
		expect(method_exists($this->region, "get_region"))->toBeTrue();
		passed("Region->get_region() method");
	});

	test("get_region() returns region details", function () {
		$this->region = new Region(TEST_GRID);
		$result = $this->region->get_region();
		expect($result)->toBeArray()->toHaveKey("uuid");
		expect($result)->toHaveKey("x");
		expect($result)->toHaveKey("y");
	})->depends("Region->get_region() method");

	// DEPRECATED: use specific link_region() or get_region()
	// instead of data() which is a mix of both
	test("Region->data() method", function () {
		$data = $this->region->data();
		expect($data)->toBeArray()->not->toBeEmpty();
		expect($this->region->regionName)->not->toBeEmpty();
		passed("Region->data() method");
	})->depends("Region->get_region() method", "Region->link_region() method");
});

describe("active test grid", function () {
	beforeEach(function () {
		requires("Test Grid running");
		requires("Region->data() method");
		$this->active_test_grid =
			TEST_GRID ?: TEST_TMP_HOST . ":" . TEST_TMP_PORT;
		$this->active_test_region = TEST_REGION ?: TEST_TMP_REGION;
		$this->region = new Region(
			$this->active_test_region,
			$this->active_test_grid,
		);
	});

	test("Test region config", function () {
		expect($this->active_test_grid)->not->toBeEmpty();
		expect($this->active_test_region)->not->toBeEmpty();
		expect($this->region)->toBeInstanceOf(Region::class);
		expect($this->region->regionName)->toBe($this->active_test_region);
		expect($this->active_test_grid)->toMatch(
			"/^{$this->region->host}:{$this->region->port}$/",
		);
		passed("Test region config");
	});

	test("online() returns boolean", function () {
		expect($this->region->online())->toBeBool();
	});

	// test("teleportLink() after data() is a valid hop:// link", function () {
	// 	$this->region->data();
	// 	$teleportLink = $this->region->teleportLink(null, TPLINK_HOP);
	// 	expect($teleportLink)->toStartWith("hop://");
	// 	// url is built from the lowercased uri; regionName from the server may
	// 	// differ in case — verify the link contains the host and a region segment
	// 	expect($teleportLink)->toContain($this->region->host);
	// 	expect($teleportLink)->toMatch("#^hop://[^/]+/[^/]+#");
	// });
});
