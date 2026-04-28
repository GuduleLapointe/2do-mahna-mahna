<?php
$appDir = dirname(__DIR__, 2);

describe("Data", function () use ($appDir) {
	test("data/ exists", function () use ($appDir) {
		expect("$appDir/data")->toBeDirectory();
		passed("Data");
	});

	test("events.json", function () use ($appDir) {
		requires("Data");
		expect("$appDir/data/events.json")->toBeFile()->toBeReadableFile();
	});

	test("events.lsl2", function () use ($appDir) {
		requires("Data");
		expect("$appDir/data/events.lsl2")->toBeFile()->toBeReadableFile();
	});

	test("events.ics", function () use ($appDir) {
		requires("Data");
		expect("$appDir/data/events.ics")->toBeFile()->toBeReadableFile();
	});
});
