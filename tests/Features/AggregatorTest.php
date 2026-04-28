<?php

describe("Aggregator", function () {
	test("execute bin/aggregator.php", function () {
		$aggrTmp = sys_get_temp_dir() . "/2do-aggr-" . uniqid();
		mkdir($aggrTmp, 0755, true);
		exec(
			"php $appDir/bin/aggregator.php $aggrTmp 2>&1",
			$aggrOut,
			$aggrCode,
		);
		register_shutdown_function(
			fn() => exec("rm -rf " . escapeshellarg($aggrTmp)),
		);
		expect($aggrCode)->toBe(0, "Aggregator failed — check logs");
		passed("Aggregator");
		return $aggrTmp;
	});

	test("events.json exists and is valid JSON", function (string $tmp) {
		expect("$tmp/events.json")
			->toBeFile()
			->toBeReadableFile();
		$data = json_decode(file_get_contents("$tmp/events.json"), true);
		expect(json_last_error())->toBe(JSON_ERROR_NONE);
		expect($data)->toBeArray();
	})->depends("execute bin/aggregator");

	test("events.lsl2 starts with version", function (string $tmp) {
		expect("$tmp/events.lsl2")
			->toBeFile()
			->toBeReadableFile();
		$first = trim(fgets(fopen("$tmp/events.lsl2", "r")));
		expect($first)->toMatch('/^\d+\.\d+\.\d+$/');
	})->depends("execute bin/aggregator");

	test("events.ics is valid iCal", function (string $tmp) {
		expect("$tmp/events.ics")
			->toBeFile()
			->toBeReadableFile();
		$content = file_get_contents("$tmp/events.ics");
		expect($content)
			->toStartWith("BEGIN:VCALENDAR")
			->toContain("END:VCALENDAR");
	})->depends("execute bin/aggregator");
});
