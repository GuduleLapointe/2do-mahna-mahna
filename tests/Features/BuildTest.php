<?php
$appDir = dirname(__DIR__, 2);

describe("Build", function () use ($appDir) {
	beforeAll(function () use ($appDir) {
		$tmp = sys_get_temp_dir() . '/2do-build-' . uniqid();
		mkdir($tmp, 0755, true);
		$GLOBALS['build_tmp'] = $tmp;
		exec("php $appDir/dev/build.php $tmp 2>&1", $out, $code);
		$GLOBALS['build_exit'] = $code;
		$GLOBALS['build_out']  = implode("\n", $out);
	});

	afterAll(function () {
		if (!empty($GLOBALS['build_tmp'])) {
			exec("rm -rf " . escapeshellarg($GLOBALS['build_tmp']));
		}
	});

	test("build completes without error", function () {
		expect($GLOBALS['build_exit'])->toBe(0, $GLOBALS['build_out']);
		passed("Build");
	});

	test("index.html", function () {
		requires("Build");
		expect(file_exists($GLOBALS['build_tmp'] . "/index.html"))->toBeTrue();
	});

	test("styles.min.css", function () {
		requires("Build");
		expect(file_exists($GLOBALS['build_tmp'] . "/styles.min.css"))->toBeTrue();
	});

	test("script.min.js", function () {
		requires("Build");
		expect(file_exists($GLOBALS['build_tmp'] . "/script.min.js"))->toBeTrue();
	});

	test("events.php", function () {
		requires("Build");
		expect(file_exists($GLOBALS['build_tmp'] . "/events.php"))->toBeTrue();
	});

	test("bootstrap.php", function () {
		requires("Build");
		expect(file_exists($GLOBALS['build_tmp'] . "/bootstrap.php"))->toBeTrue();
	});

	test("index.php", function () {
		requires("Build");
		expect(file_exists($GLOBALS['build_tmp'] . "/index.php"))->toBeTrue();
	});
});
