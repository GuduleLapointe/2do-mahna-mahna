<?php
$appDir = dirname(__DIR__, 2);

describe("Data", function () use ($appDir) {
	test("data/ exists", function () use ($appDir) {
		expect("$appDir/data")->toBeDirectory();
		passed("Data");
		return $appDir;
	});

	test("events.json", fn(string $dir) => expect("$dir/data/events.json")->toBeFile()->toBeReadableFile())
		->depends("data/ exists");

	test("events.lsl2", fn(string $dir) => expect("$dir/data/events.lsl2")->toBeFile()->toBeReadableFile())
		->depends("data/ exists");

	test("events.ics", fn(string $dir) => expect("$dir/data/events.ics")->toBeFile()->toBeReadableFile())
		->depends("data/ exists");
});
