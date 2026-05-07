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

describe("Helpers setup", function () {
	test("Helpers environment", function () {
		expect(defined("TEST_GRID"))->toBeTrue();
		expect(defined("TEST_REGION"))->toBeTrue();

		expect(defined("TEST_TMP_HOST"))->toBeTrue();
		expect(defined("TEST_TMP_PORT"))->toBeTrue();
		expect(defined("TEST_TMP_REGION"))->toBeTrue();

		expect(TEST_GRID)->not->toBeEmpty();
		// expect(TEST_REGION)->not->toBeEmpty(); // TEST_REGION can be empty
		expect(TEST_TMP_HOST)->not->toBeEmpty();
		expect(TEST_TMP_PORT)->not->toBeEmpty();
		expect(TEST_TMP_REGION)->not->toBeEmpty();

		passed("Helpers environment");
	});
});

describe("opensim_parse_url()", function () {
	beforeEach(function () {
		requires("Helpers environment");
	});

	$tmp_uri = TEST_TMP_HOST . ":" . TEST_TMP_PORT . "/" . TEST_TMP_REGION;
	$parts = parse_url(TEST_GRID);
	$host = TEST_TMP_HOST;
	$port = TEST_TMP_PORT;
	$tmp_host_port = TEST_TMP_HOST . ":" . TEST_TMP_PORT;
	$region_urlencode = urlencode(strtolower(TEST_TMP_REGION));
	$region_percentencode = str_replace("+", "%20", $region_urlencode);
	$test_uri = "$host:$port/$region_urlencode";
	$hopurl = trim("hop://$host:$port/$region_urlencode", "/");
	$slurl = "secondlife://http|!!$host|$port%20$region_percentencode";

	// Canonical form the app always uses: host:port + region name
	test("opensim_parse_url", function () use ($tmp_uri, $tmp_host_port) {
		$result = opensim_parse_url($tmp_uri);
		expect($result)->toBeArray();
		expect($result["host"])->toBe(TEST_TMP_HOST);
		expect((string) $result["port"])->toBe(TEST_TMP_PORT);
		expect($result["gatekeeper"])->toBe("http://" . $tmp_host_port);
		expect($result["region_uri"])->toBe($tmp_uri);
		passed("opensim_parse_url");
	});

	test("http://host:port/Region same as bare", function () use ($tmp_uri) {
		$bare = opensim_parse_url($tmp_uri);
		$http = opensim_parse_url("http://" . $tmp_uri);
		unset($http["scheme"]);
		expect($http)->toEqualCanonicalizing($bare);
	})->depends("opensim_parse_url");

	test("hop://host:port/Region same as bare", function () use (
		$tmp_uri,
		$hopurl,
	) {
		$bare = opensim_parse_url(strtolower($tmp_uri));
		$hop = opensim_parse_url(strtolower($hopurl));
		unset($bare["scheme"], $hop["scheme"]);
		expect($hop)->toEqualCanonicalizing($bare);
	})->depends("opensim_parse_url");

	test("slurl same as bare", function () use ($tmp_uri, $slurl) {
		$bare = opensim_parse_url($tmp_uri);
		$sl_parsed = opensim_parse_url($slurl);
		// Too much differences in parse_url results due to hypergrid
		// psychedelic slurl scheme canibalization, only check essentials.
		expect($sl_parsed["host"])->toBe($bare["host"]);
		expect($sl_parsed["region"])->toBe($bare["region"]);
		expect($sl_parsed["region_uri"])->toBe($bare["region_uri"]);
		expect($sl_parsed["gatekeeper"])->toBe($bare["gatekeeper"]);

		// unset($sl_parsed["scheme"], $bare["scheme"]);
		// expect($sl_parsed)->toEqualCanonicalizing($bare);
	})
		->depends("opensim_parse_url")
		->todo(
			"SLurls are not yet supported by opensim_parse_url, implement test when function is adjusted.",
		);

	test("host:port Region — extracts region name", function () use ($tmp_uri) {
		$result = opensim_parse_url(
			TEST_TMP_HOST . ":" . TEST_TMP_PORT . " " . TEST_TMP_REGION,
		);
		expect($result["host"])->toBe(TEST_TMP_HOST);
		expect($result["region"])->toBe(TEST_TMP_REGION);
		expect($result["region_uri"])->toBe($tmp_uri);
	})->depends("opensim_parse_url");

	test("http://host:port/Region — slash separator", function () use (
		$tmp_uri,
	) {
		$result = opensim_parse_url("http://" . $tmp_uri);
		expect($result["host"])->toBe(TEST_TMP_HOST);
		expect($result["region"])->toBe(TEST_TMP_REGION);
	})->depends("opensim_parse_url");

	test("with position — pos extracted", function () use ($tmp_uri) {
		$result = opensim_parse_url($tmp_uri . "/128/64/32");
		expect($result["pos"])->toBe("128/64/32");
		expect($result["region"])->toBe(TEST_TMP_REGION);
		$result = opensim_parse_url($tmp_uri . "/128.5/64.0/32.50000");
		expect($result["pos"])->toBe("128.5/64/32.5");
	})->depends("opensim_parse_url");

	test("region name with spaces kept intact", function () {
		$result = opensim_parse_url(
			TEST_TMP_HOST . ":" . TEST_TMP_PORT . " Grand Place",
		);
		expect($result["region"])->toBe("Grand Place");
	})->depends("opensim_parse_url");

	it("does not add host or port to simple local regions", function () {
		$result = opensim_parse_url(TEST_TMP_REGION);
		expect($result["region"])->toBe(TEST_TMP_REGION);
		expect($result["host"] ?? null)->toBeEmpty();
		expect($result["port"] ?? null)->toBeEmpty();
		expect($result["gatekeeper"] ?? null)->toBeEmpty();
	})->depends("opensim_parse_url");

	test("no host, grid fallback fills host", function () {
		$result = opensim_parse_url(
			TEST_TMP_REGION,
			"http://" . TEST_TMP_HOST . ":" . TEST_TMP_PORT,
		);
		expect($result["host"])->toBe(TEST_TMP_HOST);
		expect($result["port"])->toEqual(TEST_TMP_PORT);
		expect($result["region"])->toBe(TEST_TMP_REGION);

		// Same without shame
		$result = opensim_parse_url(
			TEST_TMP_REGION,
			TEST_TMP_HOST . ":" . TEST_TMP_PORT,
		);
		expect($result["host"])->toBe(TEST_TMP_HOST);
		expect($result["port"])->toEqual(TEST_TMP_PORT);
		expect($result["region"])->toBe(TEST_TMP_REGION);
	})->depends("opensim_parse_url");
});

// ---------------------------------------------------------------------------

describe("opensim_format_tp()", function () {
	beforeEach(function () {
		requires("Helpers environment");
	});

	$tmp_host_port = TEST_TMP_HOST . ":" . TEST_TMP_PORT;
	$tmp_uri = $tmp_host_port . "/" . TEST_TMP_REGION;

	test("TPLINK_HOP produces hop:// URL", function () use (
		$tmp_uri,
		$tmp_host_port,
	) {
		$result = opensim_format_tp($tmp_uri, TPLINK_HOP);
		expect($result)->toStartWith("hop://");
		expect($result)->toContain($tmp_host_port);
		expect($result)->toContain(urlencode(TEST_TMP_REGION));
	});

	test("TPLINK_TXT produces plain text URI", function () use ($tmp_uri) {
		$result = opensim_format_tp($tmp_uri, TPLINK_TXT);
		expect($result)->not->toContain("://");
		expect($result)->toContain(TEST_TMP_HOST);
		expect($result)->toContain(TEST_TMP_REGION);
	});

	test("TPLINK_HG produces secondlife:// URL", function () use ($tmp_uri) {
		$result = opensim_format_tp($tmp_uri, TPLINK_HG);
		expect($result)->toStartWith("secondlife://");
	});

	test(
		"TPLINK_HOP does not add default position when none given",
		function () use ($tmp_uri) {
			$result = opensim_format_tp($tmp_uri, TPLINK_HOP);
			expect($result)->not->toMatch("#/\d+/\d+/\d+$#");
		},
	);

	test("TPLINK_HOP preserves explicit position", function () use ($tmp_uri) {
		$result = opensim_format_tp($tmp_uri . "/64/32/10", TPLINK_HOP);
		expect($result)->toContain("64/32/10");
	});

	test("empty destination returns empty string", function () {
		expect(opensim_format_tp("", TPLINK_HOP))->toBe("");
	});
});

// ---------------------------------------------------------------------------

describe("opensim_get_region()", function () {
	beforeEach(function () {
		requires("Helpers environment");
	});

	$parts = parse_url(TEST_GRID);
	$host = $parts["host"] ?? "";
	$port = $parts["port"] ?? "";
	$region_urlencode = urlencode(strtolower(TEST_REGION));
	$region_percentencode = str_replace("+", "%20", $region_urlencode);
	$test_uri = TEST_GRID . "/" . TEST_REGION; // $host:$port/$region_urlencode
	$hopurl = trim("hop://$host:$port/$region_urlencode", "/");
	$slurl = "secondlife://http|!!$host|$port%20$region_percentencode";

	// First test: bare gatekeeper URL (no region) → server returns default region.
	// All other tests in this block depend on this one.
	test("Grid reachable", function () {
		$result = opensim_get_region(TEST_GRID);
		expect($result["result"])->toBeIn(["true", "True"]);
		expect($result)->toBeArray()->toHaveKey("uuid");
		expect($result["region_name"])->not->toBeEmpty();
		passed("Grid reachable");
	});

	test("http://host:port/Region", function () use ($test_uri) {
		$named = opensim_get_region($test_uri);
		expect($named)->toBeArray()->toHaveKey("uuid");
		expect($named["region_name"])->toBe(TEST_REGION);
	})->depends("Grid reachable");

	test("secondlife:// and http:// give the same result", function () use (
		$test_uri,
		$hopurl,
		$slurl,
	) {
		$http_data = opensim_get_region($test_uri);
		unset($http_data["scheme"]);
		if (!expect($http_data)->toBeArray()->toHaveKey("uuid")) {
			$this->markTestSkipped(
				"Base region not found be parsed, skipping further tests",
			);
		}

		$secondlife_data = opensim_get_region($slurl);
		unset($secondlife_data["scheme"]);
		expect($secondlife_data)->toEqualCanonicalizing($http_data);
	})
		->depends("Grid reachable")
		->todo(
			"opensim_parse_url secondlife:// support required for opensim_get_region() to handle secondlife:// URLs",
		);

	test("hop:// and http:// give the same result", function () use (
		$test_uri,
		$hopurl,
		$slurl,
	) {
		$http_data = opensim_get_region($test_uri);
		unset($http_data["scheme"]);
		if (!expect($http_data)->toBeArray()->toHaveKey("uuid")) {
			$this->markTestSkipped(
				"Base region not found be parsed, skipping further tests",
			);
		}

		// ensure result is the same with hop url
		$hop_data = opensim_get_region($hopurl);
		unset($hop_data["scheme"]);
		expect($hop_data)->toEqualCanonicalizing($http_data);
	})->depends("Grid reachable");

	test("result contains globalPos x,y coordinates", function () {
		$result = opensim_get_region(TEST_GRID);
		expect($result)->toHaveKey("x");
		expect($result)->toHaveKey("y");
		expect((int) $result["x"])->toBeGreaterThan(0);
	})->depends("Grid reachable");
});
