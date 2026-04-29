<?php

describe("Build", function () {
	test("execute dev/build.php", function () {
		exec(
			"php " . APP_DIR . "/dev/build.php " . TEST_BUILD_DIR . " 2>&1",
			$out,
			$code,
		);
		expect($code)->toBe(0, "Build failed — check logs");
		passed("Build");
	});

	test("static.html", function () {
		expect(TEST_BUILD_DIR . "/static.html")->toBeFile();
	})->depends("execute dev/build.php");

	test("styles.min.css", function () {
		expect(TEST_BUILD_DIR . "/styles.min.css")->toBeFile();
	})->depends("execute dev/build.php");

	test("script.min.js", function () {
		expect(TEST_BUILD_DIR . "/script.min.js")->toBeFile();
	})->depends("execute dev/build.php");

	test("index.php", function () {
		expect(TEST_BUILD_DIR . "/index.php")->toBeFile();
	})->depends("execute dev/build.php");
});
