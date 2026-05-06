<?php

/**
 * Unit tests for opensim helper functions.
 *
 * No network I/O, pure string manipulation.
 * 	- opensim_parse_url
 * 	- opensim_uri
 * 	- opensim_format_tp
 *
 * Network I/O, TEST_GRID in tests/.env and on a running grid.
 * emits a notice and passes silently when absent.
 * 	- opensim_link_region: XML-RPC calls to a live grid.
 * 	- opensim_get_region: XML-RPC calls to a live grid.
 */

describe("opensim_parse_url()", function () {
	// Canonical form the app always uses: host:port + region name
	test("opensim_parse_url", function () {
		$result = opensim_parse_url(
			TEST_TMP_HOST . ":" . TEST_TMP_PORT . " " . TEST_TMP_REGION,
		);
		expect($result)->toBeArray();
		expect($result["host"])->toBe(TEST_TMP_HOST);
		expect((string) $result["port"])->toBe(TEST_TMP_PORT);
		expect($result["gatekeeper"])->toBe(
			"http://" . TEST_TMP_HOST . ":" . TEST_TMP_PORT,
		);
		expect($result["region_uri"])->toBe(
			TEST_TMP_HOST . ":" . TEST_TMP_PORT . "/" . TEST_TMP_REGION,
		);
		passed("opensim_parse_url");
	});

	test(
		"http://host:port/Region — same host/port/region as bare",
		function () {
			$bare = opensim_parse_url(
				TEST_TMP_HOST . ":" . TEST_TMP_PORT . " " . TEST_TMP_REGION,
			);
			$http = opensim_parse_url(
				"http://" .
					TEST_TMP_HOST .
					":" .
					TEST_TMP_PORT .
					"/" .
					TEST_TMP_REGION,
			);
			expect($http["host"])->toBe($bare["host"]);
			expect((string) $http["port"])->toBe((string) $bare["port"]);
			expect($http["region"])->toBe($bare["region"]);
		},
	)->depends("opensim_parse_url");

	test("hop://host:port/Region — same host/port/region as bare", function () {
		$bare = opensim_parse_url(
			TEST_TMP_HOST . ":" . TEST_TMP_PORT . " " . TEST_TMP_REGION,
		);
		$hop = opensim_parse_url(
			"hop://" .
				TEST_TMP_HOST .
				":" .
				TEST_TMP_PORT .
				"/" .
				TEST_TMP_REGION,
		);
		expect($hop["host"])->toBe($bare["host"]);
		expect((string) $hop["port"])->toBe((string) $bare["port"]);
		expect($hop["region"])->toBe($bare["region"]);
	})->depends("opensim_parse_url");

	test(
		"secondlife://host:port/Region — same host/port/region as bare",
		function () {
			$bare = opensim_parse_url(
				TEST_TMP_HOST . ":" . TEST_TMP_PORT . " " . TEST_TMP_REGION,
			);
			$sl = opensim_parse_url(
				"secondlife://" .
					TEST_TMP_HOST .
					":" .
					TEST_TMP_PORT .
					"/" .
					TEST_TMP_REGION,
			);
			expect($sl["host"])->toBe($bare["host"]);
			expect((string) $sl["port"])->toBe((string) $bare["port"]);
			expect($sl["region"])->toBe($bare["region"]);
		},
	)->depends("opensim_parse_url");

	test("host:port Region — extracts region name", function () {
		$result = opensim_parse_url(
			TEST_TMP_HOST . ":" . TEST_TMP_PORT . " " . TEST_TMP_REGION,
		);
		expect($result["host"])->toBe(TEST_TMP_HOST);
		expect($result["region"])->toBe(TEST_TMP_REGION);
		expect($result["region_uri"])->toBe(
			TEST_TMP_HOST . ":" . TEST_TMP_PORT . "/" . TEST_TMP_REGION,
		);
	})->depends("opensim_parse_url");

	test("http://host:port/Region — slash separator", function () {
		$result = opensim_parse_url(
			"http://" .
				TEST_TMP_HOST .
				":" .
				TEST_TMP_PORT .
				"/" .
				TEST_TMP_REGION,
		);
		expect($result["host"])->toBe(TEST_TMP_HOST);
		expect($result["region"])->toBe(TEST_TMP_REGION);
	})->depends("opensim_parse_url");

	test("with position — pos extracted", function () {
		$result = opensim_parse_url(
			TEST_TMP_HOST .
				":" .
				TEST_TMP_PORT .
				" " .
				TEST_TMP_REGION .
				"/128/64/32",
		);
		expect($result["pos"])->toBe("128/64/32");
		expect($result["region"])->toBe(TEST_TMP_REGION);
		$result = opensim_parse_url(
			TEST_TMP_HOST .
				":" .
				TEST_TMP_PORT .
				" " .
				TEST_TMP_REGION .
				"/128.5/64.0/32.50000",
		);
		expect($result["pos"])->toBe("128.5/64/32.5");
	})->depends("opensim_parse_url");

	test("region name with spaces kept intact", function () {
		$result = opensim_parse_url(
			TEST_TMP_HOST . ":" . TEST_TMP_PORT . " Grand Place",
		);
		expect($result["region"])->toBe("Grand Place");
	})->depends("opensim_parse_url");

	it("does not add host or port to simple local regions", function () {
		$result = opensim_parse_url("Welcome");
		expect($result["region"])->toBe("Welcome");
		expect($result["host"] ?? null)->toBeEmpty();
		expect($result["port"] ?? null)->toBeEmpty();
		expect($result["gatekeeper"] ?? null)->toBeEmpty();
	})->depends("opensim_parse_url");

	test("no host, grid fallback fills host", function () {
		$result = opensim_parse_url(
			TEST_TMP_REGION,
			"http://TEST_TMP_HOST:TEST_TMP_PORT",
		);
		expect($result["host"])->toBe(TEST_TMP_HOST);
		expect($result["region"])->toBe(TEST_TMP_REGION);
	})->depends("opensim_parse_url");
});

// ---------------------------------------------------------------------------

describe("opensim_format_tp()", function () {
	$uri = "yourgrid.org:8002 Welcome";

	test("TPLINK_HOP produces hop:// URL", function () use ($uri) {
		$result = opensim_format_tp($uri, TPLINK_HOP);
		expect($result)->toStartWith("hop://");
		expect($result)->toContain("yourgrid.org:8002");
		expect($result)->toContain("Welcome");
	});

	test("TPLINK_TXT produces plain text URI", function () use ($uri) {
		$result = opensim_format_tp($uri, TPLINK_TXT);
		expect($result)->toContain("yourgrid.org:8002");
		expect($result)->toContain("Welcome");
		expect($result)->not->toStartWith("hop://");
		expect($result)->not->toStartWith("secondlife://");
	});

	test("TPLINK_HG produces secondlife:// URL", function () use ($uri) {
		$result = opensim_format_tp($uri, TPLINK_HG);
		expect($result)->toStartWith("secondlife://");
	});

	test(
		"does not add default position to TPLINK_HOP when none given",
		function () use ($uri) {
			$result = opensim_format_tp($uri, TPLINK_HOP);
			// Default landing pos 128/128/25 must be present (hop always needs a position)
			expect($result)->not->toMatch("#/\d+/\d+/\d+$#");
		},
	);

	test("TPLINK_HOP preserves explicit position", function () {
		$result = opensim_format_tp(
			"yourgrid.org:8002 Welcome/64/32/10",
			TPLINK_HOP,
		);
		expect($result)->toContain("64/32/10");
	});

	test("empty URI returns null", function () {
		expect(opensim_format_tp("", TPLINK_HOP))->toBeEmpty();
	});
});

// ---------------------------------------------------------------------------

describe("opensim_get_region()", function () {
	beforeEach(function () {
		$grid = Config::get("test_grid");
		if (empty($grid)) {
			test()->markTestSkipped("TEST_GRID not set in tests/.env");
		}
		$this->grid = $grid;
		$this->region = Config::get("test_region") ?: "";
	});

	// First test: bare gatekeeper URL (no region) → server returns default region.
	// All other tests in this block depend on this one.
	test("Grid reachable", function () {
		$result = opensim_get_region("http://" . $this->grid);
		expect($result)->toBeArray()->toHaveKey("uuid");
		expect($result["region_name"])->not->toBeEmpty();
		if (empty($this->region)) {
			$this->region = $result["region_name"] ?? null;
		}
		passed("Grid reachable");
	});

	test("http://host:port/Region", function () {
		if (empty($this->region)) {
			test()->markTestSkipped("TEST_REGION not set in tests/.env");
		}
		$default = opensim_get_region("http://" . $this->grid);
		$named = opensim_get_region(
			"http://" . $this->grid . "/" . urlencode($this->default_region),
		);
		expect($named)->toBeArray()->toHaveKey("uuid");
		expect($named["region_name"])->not->toBeEmpty();
		// Named region may differ from the default landing region — just verify it resolves
		passed("Named region resolves");
	})->depends("Grid reachable");

	test("hop://, secondlife:// and http:// give the same result", function () {
		$http = opensim_get_region("http://" . $this->grid);
		$hop = opensim_get_region("hop://" . $this->grid);
		expect($hop)->toBeArray()->toHaveKey("uuid");
		expect($hop["uuid"])->toBe($http["uuid"] ?? null);
		$secondlife = opensim_get_region("secondlife://" . $this->grid);
		expect($secondlife)->toBeArray()->toHaveKey("uuid");
		expect($secondlife["uuid"])->toBe($http["uuid"] ?? null);
	})->depends("Grid reachable");

	test("result contains x, y coordinates", function () {
		$result = opensim_get_region("http://" . $this->grid);
		expect($result)->toHaveKey("x");
		expect($result)->toHaveKey("y");
		expect((int) $result["x"])->toBeGreaterThan(0);
	})->depends("Grid reachable");
});
