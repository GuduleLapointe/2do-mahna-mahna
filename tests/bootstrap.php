<?php

// Bootstrap file for PHPUnit tests
echo "Bootstrapping tests...\n";

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
		"${_ENV["DEV_SCHEME"]}://${_ENV["DEV_HOST"]}:${_ENV["DEV_PORT"]}",
	);
}

// Read Aggregator version from ../.version file
if (file_exists(__DIR__ . "/../.version")) {
	define("VERSION", trim(file_get_contents(__DIR__ . "/../.version")));
} else {
	define("VERSION", "unknown");
}

// Read Board version from BROAD_VER
if (isset($_ENV["BOARD_VER"])) {
	define("BOARD_VER", $_ENV["BOARD_VER"]);
} else {
	define("BOARD_VER", "unknown");
}

echo "Test environment set: " . TEST_URL . "\n";
echo "Version: " . VERSION . "\n";
echo "Board version: " . BOARD_VER . "\n";

// We do not need to load the php files yet, we currently only test the API endpoints
// require_once __DIR__ . "/../templates/events.php";
// require_once __DIR__ . "/../includes/bootstrap.php";
