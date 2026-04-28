<?php
$appDir = dirname(__DIR__, 2);

describe("Cron", function () use ($appDir) {
	test("config/targets exists", function () use ($appDir) {
		expect("$appDir/config/targets")->toBeFile();
		passed("Deploy targets");
	});

	test("dry-run completes without error", function () use ($appDir) {
		exec("$appDir/bin/cron.sh --dry-run 2>&1", $output, $code);
		expect($code)->toBe(0, "Cron dry-run failed");
	})->depends("config/targets exists");
});
