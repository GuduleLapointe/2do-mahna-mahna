<?php
$appDir = dirname(__DIR__, 2);
$dataDir = "$appDir/data";

describe("Data", function () use ($dataDir) {
	test("data/ exists", function () use ($dataDir) {
		expect(is_dir($dataDir))->toBeTrue(
			"Run bin/aggregator.php to generate data/",
		);
		passed("Data");
	});

	test("events.json exists", function () use ($dataDir) {
		requires("Data");
		expect(file_exists("$dataDir/events.json"))->toBeTrue();
		passed("events.json");
	});

	test("events.json is valid JSON array", function () use ($dataDir) {
		requires("events.json");
		$data = json_decode(file_get_contents("$dataDir/events.json"), true);
		expect(json_last_error())->toBe(JSON_ERROR_NONE, "events.json must be valid JSON");
		expect($data)->toBeArray();
	});

	test("events.json has events", function () use ($dataDir) {
		requires("events.json");
		$data = json_decode(file_get_contents("$dataDir/events.json"), true);
		if (empty($data)) {
			test()->markTestSkipped("Empty events list");
		}
		expect(count($data))->toBeGreaterThan(0);
	});

	test("events.lsl2 exists", function () use ($dataDir) {
		requires("Data");
		expect(file_exists("$dataDir/events.lsl2"))->toBeTrue();
	});

	test("events.lsl2 starts with version", function () use ($dataDir) {
		requires("Data");
		$first = trim(fgets(fopen("$dataDir/events.lsl2", "r")));
		expect($first)->toMatch('/^\d+\.\d+\.\d+$/');
	});

	test("events.ics exists", function () use ($dataDir) {
		requires("Data");
		expect(file_exists("$dataDir/events.ics"))->toBeTrue();
	});

	test("events.ics is valid iCal", function () use ($dataDir) {
		requires("Data");
		$content = file_get_contents("$dataDir/events.ics");
		expect($content)->toStartWith("BEGIN:VCALENDAR");
		expect($content)->toContain("END:VCALENDAR");
	});
});
