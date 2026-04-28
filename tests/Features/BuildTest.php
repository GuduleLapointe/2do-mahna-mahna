<?php
$appDir = dirname(__DIR__, 2);

fwrite(STDERR, "\nRunning build (may take a moment)...\n");
$buildTmp = sys_get_temp_dir() . '/2do-build-' . uniqid();
mkdir($buildTmp, 0755, true);
exec("php $appDir/dev/build.php $buildTmp 2>&1", $buildOut, $buildCode);
register_shutdown_function(fn() => exec("rm -rf " . escapeshellarg($buildTmp)));

describe("Build", function () use ($buildTmp, $buildCode) {
	test("runs without error", function () use ($buildCode) {
		expect($buildCode)->toBe(0, "Build failed — check logs");
		passed("Build");
	});

	test("index.html", function () use ($buildTmp) {
		requires("Build");
		expect("$buildTmp/index.html")->toBeFile();
	});

	test("styles.min.css", function () use ($buildTmp) {
		requires("Build");
		expect("$buildTmp/styles.min.css")->toBeFile();
	});

	test("script.min.js", function () use ($buildTmp) {
		requires("Build");
		expect("$buildTmp/script.min.js")->toBeFile();
	});

	test("events.php", function () use ($buildTmp) {
		requires("Build");
		expect("$buildTmp/events.php")->toBeFile();
	});

	test("bootstrap.php", function () use ($buildTmp) {
		requires("Build");
		expect("$buildTmp/bootstrap.php")->toBeFile();
	});

	test("index.php", function () use ($buildTmp) {
		requires("Build");
		expect("$buildTmp/index.php")->toBeFile();
	});
});
