<?php
$appDir = dirname(__DIR__, 2);
$bundleDir = "$appDir/bundle/standalone";

describe("Build", function () use ($bundleDir) {
	test("bundle/standalone exists", function () use ($bundleDir) {
		expect(is_dir($bundleDir))->toBeTrue(
			"Run dev/build.php to generate bundle/standalone/",
		);
		passed("Build");
	});

	test("index.html", function () use ($bundleDir) {
		requires("Build");
		expect(file_exists("$bundleDir/index.html"))->toBeTrue();
	});

	test("styles.min.css", function () use ($bundleDir) {
		requires("Build");
		expect(file_exists("$bundleDir/styles.min.css"))->toBeTrue();
	});

	test("script.min.js", function () use ($bundleDir) {
		requires("Build");
		expect(file_exists("$bundleDir/script.min.js"))->toBeTrue();
	});

	test("events.php", function () use ($bundleDir) {
		requires("Build");
		expect(file_exists("$bundleDir/events.php"))->toBeTrue();
	});

	test("bootstrap.php", function () use ($bundleDir) {
		requires("Build");
		expect(file_exists("$bundleDir/bootstrap.php"))->toBeTrue();
	});

	test("index.php", function () use ($bundleDir) {
		requires("Build");
		expect(file_exists("$bundleDir/index.php"))->toBeTrue();
	});
});
