<?php
describe("Requirements", function () {
	test("Test URL", function () {
		expect(defined("TEST_URL"))->toBeTrue(
			"DEV_HOST and DEV_PORT must be set in tests/.env",
		);
		passed("Test URL");
	});

	test("imagick", function () {
		expect(extension_loaded("imagick"))->toBeTrue(
			"Imagick extension must be installed and loaded",
		);
		passed("imagick");
	});
});

describe("v2 API", function () {
	beforeEach(function () {
		requires("Test URL");
	});
	$apiRoute = "/api/v2/events";
	test("endpoint", function () use ($apiRoute) {
		$url = TEST_URL . $apiRoute;
		expectValidHttpStatus($url);
		$response = file_get_contents($url);
		expect($response)->not->toBeEmpty(
			"$apiRoute response should not be empty",
		);
		return $response;
	});

	$versionRegexp =
		"/" . (defined("BOARD_VER") ? BOARD_VER : "[0-9]+\.[0-9]+") . "/";
	test("valid API", function ($response) use ($versionRegexp) {
		expect($response)->toMatch(
			$versionRegexp,
			"Response should contain version {$versionRegexp}",
		);
		return $response; // repeat endpoint return to avoid multiple depends below
	})->depends("endpoint");

	test("proper v2 formatting", function ($response) {
		$lines = explode("\n", trim($response));
		$count = count($lines);
		expect($count % 3)->toBe(
			1,
			"Response should have 1 + a multiple of 3 lines, got {$count}",
		);
		if ($count < 4) {
			test()->markTestSkipped("Empty list, skipped events validation");
		}

		$timeRegexp = "[0-9]{2}:[0-9]{2}[AP]M";
		$dateRegexp = "[0-9]{4}-[0-1][0-9]-[0-3][0-9]";
		$tsRegexp = "[1-9][0-9]+";
		$timespecRegexp = "/^{$timeRegexp}~{$dateRegexp}~{$tsRegexp}~{$timeRegexp}~{$dateRegexp}~{$tsRegexp}$/";

		// TODO: validate first line version number and optional notification message
		// ext. "1.6.0 Strongly recommended update;1"
		$i = 0;
		while ($i <= min($count, 100)) {
			$title = $lines[$i + 1]; // No title validation
			$time = $lines[$i + 2];
			$dest = $lines[$i + 3];
			expect($time)->toMatch($timespecRegexp, "invalid time");
			expectValidHypergridUri($dest, "v2 API: $title - $time - $dest");
			$i = $i + 3;
		}
	})->depends("valid API");

	test("events.php?api=v2 mirrors $apiRoute", function ($response) {
		$legacyResponse = file_get_contents(TEST_URL . "/events.php?api=v2");
		expect($legacyResponse)->toBe(
			$response,
			"Legacy and v2 endpoints should return the same response",
		);
	})->depends("endpoint");

	$legacyURL = "events.lsl2";
	test("$legacyURL (legacy) processed by API", function () use ($legacyURL) {
		// TODO: match api endpoint response, but it does not work as is
		// in test environment because Symfony ignores .htaccess rules and serves
		// events.lsl2 directly despites the rewriting rule, although apache2 does
		// it properly in live environment.
		//
		// Workaround: temporarily rename events.lsl2 if present and move it back after check
		$legacyResponse = file_get_contents(TEST_URL . "/$legacyURL");
		expect($legacyResponse)->not->toBeEmpty(
			"Legacy url " . TEST_URL . "/$legacyURL should return results",
		);
	});
});

describe("v3 API", function () {
	beforeEach(function () {
		requires("Test URL");
	});

	$apiRoute = "/api/v3/events";
	test("endpoint", function () use ($apiRoute) {
		$url = TEST_URL . $apiRoute;
		expectValidHttpStatus($url);
		$response = file_get_contents($url);
		expect($response)->not->toBeEmpty(
			"$apiRoute response should not be empty",
		);
		return $response;
	});

	test("valid API", function ($response) {
		$lines = array_values(array_filter(explode("\n", trim($response))));
		if (count($lines) < 1) {
			test()->markTestSkipped("Empty list, skipped events validation");
		}
		expect($lines)->not->toBeEmpty(
			"Response should have at least one event line",
		);

		$numRegexp = "-?[0-9]+(?:\.[0-9]+)?";
		$timeRegexp = "[0-9]{2}:[0-9]{2}[AP]M";
		$tsRegexp = "[1-9][0-9]+";
		$destRegexp = "[^,]+";
		$csvSpecRegexp = "#^{$numRegexp},{$numRegexp},{$numRegexp},{$numRegexp},{$destRegexp},{$timeRegexp},{$tsRegexp},{$timeRegexp},{$tsRegexp},#";

		$max = min(count($lines), 100);
		for ($i = 0; $i < $max; $i++) {
			$line = $lines[$i];
			$n = $i + 1;
			$parts = str_getcsv($line, ",", '"', "\\");
			expect(count($parts))->toBeGreaterThanOrEqual(
				10,
				"Line {$n} should have at least 10 fields",
			);

			if (str_starts_with($parts[4], "href:")) {
				$link = str_replace("href:", "", $parts[4]);
				expect($link)->toBeUrl();
				// Skip remaining fields, we do not care about extra data, we only care about wrong or missing data
				continue;
			} else {
				expectValidHypergridUri($parts[4], "v3 API: $line");
			}

			if (preg_match($csvSpecRegexp, $line)) {
				continue;
			}

			// Fallback: identify which field is wrong with clear messages
			foreach ([0, 1, 2, 3] as $idx) {
				expect($parts[$idx])->toMatch(
					"/^{$numRegexp}$/",
					"fields 1 to 4 must be numbers",
				);
			}
			expect($parts[5])->toMatch(
				"/^{$timeRegexp}$/",
				"Field 6 must be a valid time HH:MM:[AM|PM]",
			);
			expect($parts[6])->toMatch(
				"/^{$tsRegexp}$/",
				"Field 7 must be a timestamp",
			);
			expect($parts[7])->toMatch(
				"/^{$timeRegexp}$/",
				"Field 8 must be a valid time HH:MM:[AM|PM]",
			);
			expect($parts[8])->toMatch(
				"/^{$tsRegexp}$/",
				"Field 9 must be a timestamp",
			);
		}
	})->depends("endpoint");

	test("events.php?api=v3 mirrors $apiRoute", function ($response) {
		$direct = file_get_contents(TEST_URL . "/events.php?api=v3");
		expect($direct)->toBe(
			$response,
			"Direct v3 endpoint should return the same response",
		);
	})->depends("endpoint");

	test("events.php defaults to v3 api", function ($response) {
		$default = file_get_contents(TEST_URL . "/events.php");
		expect($default)->toBe(
			$response,
			"Default response should match api=v3",
		);
	})->depends("endpoint");
});

describe("image API", function () {
	beforeEach(function () {
		requires("Test URL", "imagick");
	});

	$apiRoute = "/events.php?format=png";
	test("endpoint", function () use ($apiRoute) {
		$url = TEST_URL . $apiRoute;
		expectValidHttpStatus($url);
		$response = file_get_contents($url);
		expect($response)->not->toBeEmpty(
			"$apiRoute response should not be empty",
		);
		return $response;
	});

	test("valid PNG image", function ($response) use ($apiRoute) {
		$headers = get_headers(TEST_URL . $apiRoute, associative: true);
		expect($headers["Content-Type"] ?? "")->toContain("image/png");

		$imagick = new Imagick();
		$imagick->readImageBlob($response);
		expect($imagick->getImageFormat())->toBe(
			"PNG",
			"File should be a valid PNG",
		);
		return $imagick;
	})->depends("endpoint");

	test("proper width and height", function ($imagick) {
		expect($imagick->getImageWidth())->toBeGreaterThan(
			0,
			"PNG width should be greater than 0",
		);
		expect($imagick->getImageHeight())->toBeGreaterThan(
			0,
			"PNG height should be greater than 0",
		);
		return $imagick;
	})->depends("valid PNG image");

	test("detailed image info available", function ($imagick) {
		expect($imagick->identifyImage(true))->not->toBeEmpty(
			"Imagick should provide detailed image info",
		);
	})->depends("valid PNG image");
});
