<?php
$appDir = dirname(__DIR__, 2);

$buildTmp = sys_get_temp_dir() . '/2do-build-' . uniqid();
mkdir($buildTmp, 0755, true);
exec("php $appDir/dev/build.php $buildTmp 2>&1", $buildOut, $buildCode);
$buildOut = implode("\n", $buildOut);
register_shutdown_function(fn() => exec("rm -rf " . escapeshellarg($buildTmp)));

describe("Build", function () use ($buildTmp, $buildCode, $buildOut) {
	test("build completes without error", function () use ($buildCode, $buildOut) {
		expect($buildCode)->toBe(0, $buildOut);
		passed("Build");
	});

	test("index.html", function () use ($buildTmp) {
		requires("Build");
		expect(file_exists("$buildTmp/index.html"))->toBeTrue();
	});

	test("styles.min.css", function () use ($buildTmp) {
		requires("Build");
		expect(file_exists("$buildTmp/styles.min.css"))->toBeTrue();
	});

	test("script.min.js", function () use ($buildTmp) {
		requires("Build");
		expect(file_exists("$buildTmp/script.min.js"))->toBeTrue();
	});

	test("events.php", function () use ($buildTmp) {
		requires("Build");
		expect(file_exists("$buildTmp/events.php"))->toBeTrue();
	});

	test("bootstrap.php", function () use ($buildTmp) {
		requires("Build");
		expect(file_exists("$buildTmp/bootstrap.php"))->toBeTrue();
	});

	test("index.php", function () use ($buildTmp) {
		requires("Build");
		expect(file_exists("$buildTmp/index.php"))->toBeTrue();
	});
});
