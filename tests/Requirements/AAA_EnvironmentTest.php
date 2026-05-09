<?php
describe("Environment", function () {
	test("Test URL", function () {
		expect(defined("TEST_URL"))->toBeTrue("TEST_URL must be defined");
		passed("Test URL");
	});

	test("imagick", function () {
		expect(extension_loaded("imagick"))->toBeTrue(
			"Imagick extension must be installed and loaded",
		);
		passed("imagick");
	});
});
