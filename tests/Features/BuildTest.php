<?php
$appDir = dirname(__DIR__, 2);

fwrite(STDERR, "\nRunning build (may take a moment)...\n");
$buildTmp = sys_get_temp_dir() . '/2do-build-' . uniqid();
mkdir($buildTmp, 0755, true);
exec("php $appDir/dev/build.php $buildTmp 2>&1", $buildOut, $buildCode);
register_shutdown_function(fn() => exec("rm -rf " . escapeshellarg($buildTmp)));

describe("Build", function () use ($buildTmp, $buildCode) {
	test("runs without error", function () use ($buildCode, $buildTmp) {
		expect($buildCode)->toBe(0, "Build failed — check logs");
		passed("Build");
		return $buildTmp;
	});

	test("index.html", fn(string $tmp) => expect("$tmp/index.html")->toBeFile())
		->depends("runs without error");

	test("styles.min.css", fn(string $tmp) => expect("$tmp/styles.min.css")->toBeFile())
		->depends("runs without error");

	test("script.min.js", fn(string $tmp) => expect("$tmp/script.min.js")->toBeFile())
		->depends("runs without error");

	test("events.php", fn(string $tmp) => expect("$tmp/events.php")->toBeFile())
		->depends("runs without error");

	test("bootstrap.php", fn(string $tmp) => expect("$tmp/bootstrap.php")->toBeFile())
		->depends("runs without error");

	test("index.php", fn(string $tmp) => expect("$tmp/index.php")->toBeFile())
		->depends("runs without error");
});
