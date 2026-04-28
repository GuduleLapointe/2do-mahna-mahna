<?php

// Bootstrap file for PHPUnit tests
testNotice("Setting test environment");

require_once dirname(__DIR__) . "/bootstrap.php";
$appDir = APP_DIR;

// Load environment variables
$env_files = [
	dirname(__DIR__) . "/.env", // project root
	__DIR__ . "/.env", // tests/.env
];

foreach ($env_files as $file) {
	if (file_exists($file)) {
		$env = parse_ini_file($file);
		foreach ($env as $key => $value) {
			$_ENV[$key] = $value;
		}
	}
}

if (empty($_ENV["DEV_HOST"]) || empty($_ENV["DEV_PORT"])) {
	error_log(
		"Test environment not set, define DEV_HOST and DEV_PORT in tests/.env",
	);
	die(1);
} else {
	define(
		"TEST_URL",
		"{$_ENV["DEV_SCHEME"]}://{$_ENV["DEV_HOST"]}:{$_ENV["DEV_PORT"]}",
	);
}

// Read Aggregator version from ../.version file
if (file_exists(__DIR__ . "/../.version")) {
	define("APP_VERSION", trim(file_get_contents(__DIR__ . "/../.version")));
} else {
	define("APP_VERSION", "unknown");
}

// Read Board version from BROAD_VER
if (isset($_ENV["BOARD_VER"])) {
	define("BOARD_VER", $_ENV["BOARD_VER"]);
} else {
	define("BOARD_VER", "unknown");
}

// Shared test directories — created once, deleted at process end
define(
	"TEST_DIRECTORY",
	sys_get_temp_dir() . "/" . basename(APP_DIR) . "-" . uniqid(),
);

foreach (
	["TEST_URL", "APP_VERSION", "BOARD_VER", "APP_DIR", "TEST_DIRECTORY"]
	as $const
) {
	testDetail("$const: " . constant($const));
}

foreach (["Data", "Build"] as $dir_type) {
	$DIR_CONST = "TEST_" . strtoupper($dir_type) . "_DIR";
	$folder = strtolower($dir_type);
	$dir = TEST_DIRECTORY . "/$folder";
	if (!defined($DIR_CONST)) {
		define($DIR_CONST, $dir);
	}
	mkdir($dir, 0755, true);
	testDetail("$DIR_CONST: TEST_DIRECTORY/$folder");
}

register_shutdown_function(function () {
	testNotice("Cleaning test environment");
	testDetail("Delete " . TEST_DIRECTORY);

	exec("rm -rf " . escapeshellarg(TEST_DIRECTORY));
});

echo "Testing environment ready";
