<?php
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
