<?php
describe("v3 API", function () {
	beforeEach(function () {
		requires("Test URL");
	});

	$apiRoute = "/api/v3/events/lsl";
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

	test("/?api=v3 mirrors $apiRoute", function ($response) {
		$fallback = file_get_contents(TEST_URL . "/?api=v3");
		expect($fallback)->toBe(
			$response,
			"/?api=v3 fallback should return the same response",
		);
	})->depends("endpoint");

	test("/events.php mirrors $apiRoute", function ($response) {
		$direct = file_get_contents(TEST_URL . "/events.php");
		expect($direct)->toBe(
			$response,
			"/events.php should return the same response as $apiRoute",
		);
	})->depends("endpoint");
});
