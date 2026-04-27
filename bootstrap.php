<?php
/**
 * Application bootstrap
 *
 * Defines APP_DIR and loads Composer autoloader.
 * Required by all CLI entry points and subprocess parsers.
 */
if (!defined('APP_DIR')) {
    define('APP_DIR', __DIR__);
}

require_once APP_DIR . '/vendor/autoload.php';
require_once APP_DIR . '/app/Helpers/Console.php';
