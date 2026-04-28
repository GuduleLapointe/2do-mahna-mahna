<?php

describe("Cron", function () {
	beforeEach(function () {
		requires("Deploy targets");
	});

	test("dry-run", function () {
		exec(APP_DIR . "/bin/cron.sh --dry-run 2>&1", $output, $code);
		expect($code)->toBe(0, "Cron dry-run failed");
	});
});
