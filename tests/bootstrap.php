<?php

// Bootstrap file for PHPUnit tests
testNotice("Setting test environment");

require_once dirname(__DIR__) . "/bootstrap.php";

Config::load(
	defaults: ["dev_scheme" => "http"],
	jsonFile: APP_DIR . "/config/config.json",
	envFiles: [APP_DIR . "/.env", __DIR__ . "/.env"],
);

$devHost = Config::get("dev_host");
$devPort = Config::get("dev_port");
if (empty($devHost) || empty($devPort)) {
	error_log(
		"Test environment not set, define DEV_HOST and DEV_PORT in tests/.env",
	);
	die(1);
}

$constants = [
	"TEST_URL" => Config::get("dev_scheme") . "://$devHost:$devPort",
	"TEST_DIRECTORY" =>
		sys_get_temp_dir() . "/" . basename(APP_DIR) . "-" . uniqid(),
	"TEST_GRID" => Config::get("test_grid"),
	"TEST_REGION" => Config::get("test_region"),
	"TEST_TMP_HOST" => uniqid() . ".localhost",
	"TEST_TMP_PORT" => (string) rand(8100, 8999),
	"TEST_TMP_REGION" => uniqid("Welcome "),
];
foreach ($constants as $const => $value) {
	define($const, $value);
	testDetail("$const: " . constant($const));
}

foreach (["Build", "Data", "Config"] as $dir_type) {
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
