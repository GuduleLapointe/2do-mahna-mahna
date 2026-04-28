<?php
$appDir = dirname(__DIR__, 2);

describe("Deploy", function () use ($appDir) {
	beforeEach(function () {
		requires("Build");
	});

	test("config/targets exists", function () use ($appDir) {
		expect("$appDir/config/targets")->toBeFile(
			"Copy config/targets.example to config/targets and configure deploy targets",
		);
		passed("Deploy targets");
	});

	test("dry-run completes without error", function () use ($appDir) {
		exec("$appDir/bin/deploy.sh -n -y 2>&1", $output, $code);
		expect($code)->toBe(0, "Deploy dry-run failed");
	})->depends("config/targets exists");

	test("dry-run with data completes without error", function () use ($appDir) {
		requires("Data");
		exec("$appDir/bin/deploy.sh -n -y --with-data 2>&1", $output, $code);
		expect($code)->toBe(0, "Deploy with-data dry-run failed");
	})->depends("config/targets exists");
});
