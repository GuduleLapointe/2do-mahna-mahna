<?php
$appDir = dirname(__DIR__, 2);

describe("Compatibility", function () use ($appDir) {
	test("PHP 8.2 compatibility (phpcs PHPCompatibility)", function () use ($appDir) {
		exec("$appDir/vendor/bin/phpcs -n 2>&1", $output, $code);
		expect($code)->toBe(0, implode("\n", $output));
	});
});
