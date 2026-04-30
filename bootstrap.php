<?php
/**
 * Application bootstrap
 *
 * Defines APP_DIR and loads Composer autoloader.
 * Required by all CLI entry points and subprocess parsers.
 */
if (defined("APP_DIR") || defined("APP_VERSION")) {
	die("APP_DIR or APP_VERSION already defined");
}

define("APP_DIR", __DIR__);
define("APP_VERSION", "3.0.0");
define("API_VERSION", "v3");
// Latest known LSL board version — hardcoded until scrup integration (lib/scrup)
define("LSL_BOARD_VERSION", "3.0.1");

require_once APP_DIR . "/vendor/autoload.php";
require_once APP_DIR . "/app/Helpers/Console.php";
require_once APP_DIR . "/app/Shared/Config.php";
