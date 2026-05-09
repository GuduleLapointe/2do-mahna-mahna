<?php
describe("v2 API", function () {
	beforeEach(function () {
		requires("Test URL");
	});
	$apiRoute = "/api/v2/events";
	$v2endpoint = TEST_URL . $apiRoute;
	$versionPattern = "\d+(\.\d)+";
	$appVersion = defined("APP_VERSION") ? APP_VERSION : null;
	$boardVersion = defined("LSL_BOARD_VERSION") ? LSL_BOARD_VERSION : null;
	$appVersionExp =
		"/" . (str_replace(".", "\.", $appVersion) ?: $versionPattern) . "/";
	$boardVersionExp =
		"/" . (str_replace(".", "\.", $boardVersion) ?: $versionPattern) . "/";

	test("v2 enpoint", function () use ($v2endpoint) {
		expectValidHttpStatus($v2endpoint);
		$response = file_get_contents($v2endpoint);
		expect($response)->not->toBeEmpty(
			"/api/v2/events response should not be empty",
		);
		passed("v2 enpoint");
		return $response;
	});

	test("valid API", function ($response) use ($boardVersionExp) {
		expect(trim($response))->toMatch(
			$boardVersionExp,
			"/api/v2/events response should match 2do Board lsl script verrtion",
		);
		return $response; // repeat endpoint return to avoid multiple depends below
	})->depends("v2 enpoint");

	test("proper v2 formatting", function ($response) {
		$lines = explode("\n", trim($response));
		$count = count($lines);
		expect($count % 3)->toBe(
			1,
			"Response should have 1 + a multiple of 3 lines, got {$count}",
		);
		if ($count < 4) {
			test()->markTestSkipped(
				"Empty list, skipping further events validation",
			);
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
