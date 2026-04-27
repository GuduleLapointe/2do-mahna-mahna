<?php
$appDir = dirname(__DIR__, 2);

describe("Data", function () use ($appDir) {
	test("data/ exists", function () use ($appDir) {
		expect(is_dir("$appDir/data"))->toBeTrue(
			"Run bin/aggregator.php to generate data/",
		);
		passed("Data");
	});

	test("events.json exists", function () use ($appDir) {
		requires("Data");
		expect(file_exists("$appDir/data/events.json"))->toBeTrue();
	});

	test("events.lsl2 exists", function () use ($appDir) {
		requires("Data");
		expect(file_exists("$appDir/data/events.lsl2"))->toBeTrue();
	});

	test("events.ics exists", function () use ($appDir) {
		requires("Data");
		expect(file_exists("$appDir/data/events.ics"))->toBeTrue();
	});
});
