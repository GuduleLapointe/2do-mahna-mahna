<?php

// TODO: split aggregator.php between the CLI executable and the actual functioning
// classes, to be able to test its methods separately. Split is already planned
// in TODO.md to allow managing aggregator from both cli and within the future app.

describe("Aggregator", function () {
	test("execute bin/aggregator.php", function () {
		exec(
			"php " . APP_DIR . "/bin/aggregator.php " . TEST_DATA_DIR . " 2>&1",
			$out,
			$code,
		);
		expect($code)->toBe(0, "Aggregator failed — check logs");
		passed("Aggregator");
	});

	test("events.json is valid JSON", function () {
		expect(TEST_DATA_DIR . "/events.json")
			->toBeFile()
			->toBeReadableFile();
		$data = json_decode(
			file_get_contents(TEST_DATA_DIR . "/events.json"),
			true,
		);
		expect(json_last_error())->toBe(JSON_ERROR_NONE);
		expect($data)->toBeArray();
	})->depends("execute bin/aggregator.php");

	test("events.lsl2 starts with version", function () {
		expect(TEST_DATA_DIR . "/events.lsl2")
			->toBeFile()
			->toBeReadableFile();
		$first = trim(fgets(fopen(TEST_DATA_DIR . "/events.lsl2", "r")));
		expect($first)->toMatch('/^\d+\.\d+\.\d+$/');
	})->depends("execute bin/aggregator.php");

	test("events.ics is valid iCal", function () {
		expect(TEST_DATA_DIR . "/events.ics")
			->toBeFile()
			->toBeReadableFile();
		$content = file_get_contents(TEST_DATA_DIR . "/events.ics");
		expect($content)
			->toStartWith("BEGIN:VCALENDAR")
			->toContain("END:VCALENDAR");
	})->depends("execute bin/aggregator.php");
});
