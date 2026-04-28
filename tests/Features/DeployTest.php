<?php
$appDir = dirname(__DIR__, 2);

describe("Deploy", function () use ($appDir) {
	test("config/targets exists", function () use ($appDir) {
		expect(file_exists("$appDir/config/targets"))->toBeTrue(
			"Copy config/targets.example to config/targets and configure deploy targets",
		);
		passed("Deploy targets");
	});

	test("dry-run completes without error", function () use ($appDir) {
		requires("Build", "Deploy targets");
		exec("$appDir/bin/deploy.sh -n -y 2>&1", $output, $code);
		expect($code)->toBe(0, "Deploy dry-run failed");
	});

	test("dry-run with data completes without error", function () use ($appDir) {
		requires("Build", "Data", "Deploy targets");
		exec("$appDir/bin/deploy.sh -n -y --with-data 2>&1", $output, $code);
		expect($code)->toBe(0, "Deploy dry-run with data failed");
	});
});
