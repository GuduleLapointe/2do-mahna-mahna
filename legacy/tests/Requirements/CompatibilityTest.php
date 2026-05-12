<?php

describe("Compatibility", function () {
	test("PHP 8.2 compatibility (phpcs PHPCompatibility)", function () {
		exec(APP_DIR . "/vendor/bin/phpcs -n 2>&1", $output, $code);
		expect($code)->toBe(0, "PHP compatibility check failed");
	});
});
