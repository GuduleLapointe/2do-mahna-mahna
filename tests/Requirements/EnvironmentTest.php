<?php
describe("Environment", function () {
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
