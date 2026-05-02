<?php

describe("Scrup API", function () {
	beforeEach(function () {
		requires("Test URL");
	});

	// Fake LSL headers — required by inWorldOrDie() for all register actions
	$lslHeaders = implode("\r\n", [
		"Content-Type: application/x-www-form-urlencoded",
		"X-Secondlife-Shard: Production",
		"X-Secondlife-Region: Test Region(1000, 1000)",
		"X-Secondlife-Object-Key: 00000000-0000-0000-0000-000000000001",
		"X-Secondlife-Owner-Key: 00000000-0000-0000-0000-000000000002",
	]);
	$noLslHeaders = "Content-Type: application/x-www-form-urlencoded";

	$loginURI = "http://" . substr(uniqid(), -6) . ".yourgrid.org/";
	$testScript = "test-script-" . substr(uniqid(), -6);
	$unknownScript = "unknown-script-" . substr(uniqid(), -6);
	$testVersion = "1.0.0";
	$testPin = "12345";

	// Helper: POST to $url and return the HTTP status code
	$post = function (string $url, string $headers, array $params): int {
		$ctx = stream_context_create([
			"http" => [
				"method" => "POST",
				"header" => $headers,
				"content" => http_build_query($params),
				"ignore_errors" => true,
			],
		]);
		@file_get_contents($url, false, $ctx);
		preg_match("#HTTP/[\d.]+\s+(\d+)#", $http_response_header[0] ?? "", $m);
		return (int) ($m[1] ?? 0);
	};

	// -----------------------------------------------------------------------
	describe("get-version", function () use ($unknownScript) {
		test("server", function () {
			expect(
				file_get_contents(TEST_URL . "/api/v3/scrup/get-version"),
			)->toMatch("/^Scrup \d+\.\d+/");
			expect(
				file_get_contents(
					TEST_URL . "/api/v3/scrup/get-version?type=scrup",
				),
			)->toMatch("/^Scrup \d+\.\d+/");
			passed("Scrup reachable");
		});

		test("missing script name returns 400", function () {
			expect(
				httpStatus(TEST_URL . "/api/v3/scrup/get-version?type=script"),
			)->toBe(400);
		});

		test("unknown script name returns 404", function () use (
			$unknownScript,
		) {
			expect(
				httpStatus(
					TEST_URL .
						"/api/v3/scrup/get-version?name=" .
						urlencode($unknownScript),
				),
			)->toBe(404);
		});
	});

	// -----------------------------------------------------------------------
	describe("register server", function () use (
		$lslHeaders,
		$noLslHeaders,
		$loginURI,
		$post,
	) {
		beforeEach(function () {
			requires("Scrup reachable");
		});

		$params = ["loginURI" => $loginURI];
		$legacyParams = array_merge($params, [
			"action" => "register",
			"type" => "server",
		]);

		test("api and legacy endpoints succeed", function () use (
			$lslHeaders,
			$params,
			$legacyParams,
			$post,
		) {
			expect(
				$post(
					TEST_URL . "/api/v3/scrup/register/server",
					$lslHeaders,
					$params,
				),
			)->toBe(200);
			expect(
				$post(
					TEST_URL . "/scrup/scrup.php",
					$lslHeaders,
					$legacyParams,
				),
			)->toBe(200);
		});

		test("wrong headers returns 400", function () use (
			$noLslHeaders,
			$params,
			$legacyParams,
			$post,
		) {
			expect(
				$post(
					TEST_URL . "/api/v3/scrup/register/server",
					$noLslHeaders,
					$params,
				),
			)->toBe(400);
			expect(
				$post(
					TEST_URL . "/scrup/scrup.php",
					$noLslHeaders,
					$legacyParams,
				),
			)->toBe(400);
		});
	});

	// -----------------------------------------------------------------------
	describe("register script", function () use (
		$lslHeaders,
		$noLslHeaders,
		$loginURI,
		$testScript,
		$testVersion,
		$post,
	) {
		beforeEach(function () {
			requires("Scrup reachable");
		});

		$params = [
			"loginURI" => $loginURI,
			"name" => $testScript,
			"version" => $testVersion,
		];
		$legacyParams = array_merge($params, [
			"action" => "register",
			"type" => "script",
		]);

		test("get-version before registration returns 404", function () use (
			$testScript,
		) {
			expect(
				httpStatus(
					TEST_URL .
						"/api/v3/scrup/get-version?name=" .
						urlencode($testScript),
				),
			)->toBe(404);
		});

		test("wrong headers returns 400", function () use (
			$noLslHeaders,
			$params,
			$legacyParams,
			$post,
		) {
			expect(
				$post(
					TEST_URL . "/api/v3/scrup/register/script",
					$noLslHeaders,
					$params,
				),
			)->toBe(400);
			expect(
				$post(
					TEST_URL . "/scrup/scrup.php",
					$noLslHeaders,
					$legacyParams,
				),
			)->toBe(400);
		});

		test("api and legacy endpoints succeed", function () use (
			$lslHeaders,
			$params,
			$legacyParams,
			$post,
		) {
			expect(
				$post(
					TEST_URL . "/api/v3/scrup/register/script",
					$lslHeaders,
					$params,
				),
			)->toBe(200);
			expect(
				$post(
					TEST_URL . "/scrup/scrup.php",
					$lslHeaders,
					$legacyParams,
				),
			)->toBe(200);
			passed("Scrup script registered");
		});

		test("get-version after registration returns version", function () use (
			$testScript,
		) {
			$response = file_get_contents(
				TEST_URL .
					"/api/v3/scrup/get-version?name=" .
					urlencode($testScript),
			);
			expect($response)->toMatch("/^\d+\.\d+/");
		})->depends("api and legacy endpoints succeed");
	});

	// -----------------------------------------------------------------------
	describe("register client", function () use (
		$lslHeaders,
		$noLslHeaders,
		$loginURI,
		$testScript,
		$testVersion,
		$testPin,
		$post,
	) {
		beforeEach(function () {
			requires("Scrup script registered");
		});

		$params = [
			"loginURI" => $loginURI,
			"linkkey" => "00000000-0000-0000-0000-000000000099",
			"scriptname" => $testScript,
			"version" => $testVersion,
			"pin" => $testPin,
		];
		$legacyParams = array_merge($params, [
			"action" => "register",
			"type" => "client",
		]);

		test("api and legacy endpoints succeed", function () use (
			$lslHeaders,
			$params,
			$legacyParams,
			$post,
		) {
			expect(
				$post(
					TEST_URL . "/api/v3/scrup/register/client",
					$lslHeaders,
					$params,
				),
			)->toBe(200);
			expect(
				$post(
					TEST_URL . "/scrup/scrup.php",
					$lslHeaders,
					$legacyParams,
				),
			)->toBe(200);
		});

		test("wrong headers returns 400", function () use (
			$noLslHeaders,
			$params,
			$legacyParams,
			$post,
		) {
			expect(
				$post(
					TEST_URL . "/api/v3/scrup/register/client",
					$noLslHeaders,
					$params,
				),
			)->toBe(400);
			expect(
				$post(
					TEST_URL . "/scrup/scrup.php",
					$noLslHeaders,
					$legacyParams,
				),
			)->toBe(400);
		});
	});

	// Cleanup — remove all test records from the DB after the suite runs.
	// Test records are identified by the loginURI host (test.yourgrid.org),
	// which is unique to the test suite and never used in production.
	afterAll(function () use ($loginURI) {
		$dbPath =
			(ini_get("upload_tmp_dir") ?: sys_get_temp_dir()) .
			"/Scrup/scrup.db";
		if (!file_exists($dbPath)) {
			return;
		}
		$db = new SQLite3($dbPath);
		$host = parse_url($loginURI, PHP_URL_HOST);
		$db->exec("DELETE FROM scripts WHERE uri LIKE '%$host%'");
		$db->exec("DELETE FROM servers WHERE uri LIKE '%$host%'");
		$db->exec("DELETE FROM clients WHERE uri LIKE '%$host%'");
		$db->close();
	});
});
