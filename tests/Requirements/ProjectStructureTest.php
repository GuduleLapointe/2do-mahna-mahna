<?php

describe("Project structure", function () {
	test("config/targets", function () {
		expect(APP_DIR . "/config/targets")->toBeFile(
			"Copy config/targets.example to config/targets and configure deploy targets",
		);
		passed("Deploy targets");
	});

	test("data/", function () {
		expect(APP_DIR . "/data")->toBeDirectory(
			"Run bin/aggregator.php to generate data/",
		);
		passed("Data");
		return APP_DIR . "/data";
	});

	test("data/events.json", fn(string $dir) => expect("$dir/events.json")->toBeFile()->toBeReadableFile())
		->depends("data/");

	test("data/events.lsl2", fn(string $dir) => expect("$dir/events.lsl2")->toBeFile()->toBeReadableFile())
		->depends("data/");

	test("data/events.ics", fn(string $dir) => expect("$dir/events.ics")->toBeFile()->toBeReadableFile())
		->depends("data/");
});
