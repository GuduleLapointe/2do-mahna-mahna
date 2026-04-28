<?php

describe("Deploy", function () {
	beforeEach(function () {
		requires("Build", "Deploy targets");
	});

	test("dry-run", function () {
		exec(APP_DIR . "/bin/deploy.sh -n -y 2>&1", $output, $code);
		expect($code)->toBe(0, "Deploy dry-run failed");
	});

	test("dry-run with data", function () {
		requires("Data");
		exec(APP_DIR . "/bin/deploy.sh -n -y --with-data 2>&1", $output, $code);
		expect($code)->toBe(0, "Deploy with-data dry-run failed");
	});
});
