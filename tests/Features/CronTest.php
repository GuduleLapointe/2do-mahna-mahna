<?php
$appDir = dirname(__DIR__, 2);

describe("Cron", function () use ($appDir) {
	test("dry-run completes without error", function () use ($appDir) {
		requires("Deploy targets");
		exec("$appDir/bin/cron.sh --dry-run 2>&1", $output, $code);
		expect($code)->toBe(0, implode("\n", $output));
	});
});
