<?php

describe("Build", function () {
	test("execute dev/build.php", function () {
		// TEST_BUILD_DIR = sys_get_temp_dir() . "/2do-build-" . uniqid();
		// mkdir(TEST_BUILD_DIR, 0755, true);
		exec(
			"php " . APP_DIR . "/dev/build.php TEST_BUILD_DIR 2>&1",
			$out,
			$code,
		);
		// register_shutdown_function(fn() => exec("rm -rf " . escapeshellarg(TEST_BUILD_DIR)));
		expect($code)->toBe(0, "Build failed — check logs");
		passed("Build");
	});

	test("index.html", function () {
		expect(TEST_BUILD_DIR . "/index.html")->toBeFile();
	})->depends("execute dev/build.php");

	test("styles.min.css", function () {
		expect(TEST_BUILD_DIR . "/styles.min.css")->toBeFile();
	})->depends("execute dev/build.php");

	test("script.min.js", function () {
		expect(TEST_BUILD_DIR . "/script.min.js")->toBeFile();
	})->depends("execute dev/build.php");

	test("events.php", function () {
		expect(TEST_BUILD_DIR . "/events.php")->toBeFile();
	})->depends("execute dev/build.php");

	test("bootstrap.php", function () {
		expect(TEST_BUILD_DIR . "/bootstrap.php")->toBeFile();
	})->depends("execute dev/build.php");

	test("index.php", function () {
		expect(TEST_BUILD_DIR . "/index.php")->toBeFile();
	})->depends("execute dev/build.php");
});
