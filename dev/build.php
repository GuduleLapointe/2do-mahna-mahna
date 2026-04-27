#!/usr/bin/env php
<?php
/**
 * 2do Build Script
 *
 * Builds the standalone bundle: minifies CSS/JS, processes HTML templates,
 * copies static assets. Run manually during development when src/ changes.
 * Never run by cron — cron runs aggregator (refresh) + deploy only.
 *
 * Usage: php bin/build.php [-q] [-v] [output_dir]
 *
 * @package 2do-aggregator
 */

if (php_sapi_name() != "cli") {
	die("This script can only be run from the command line." . PHP_EOL);
}

require_once dirname(__DIR__) . '/bootstrap.php';

define("IS_AGGR", true);

require_once APP_DIR . "/app/Services/Exporters/export-html.php";

$opts = getopt("qvh", ["help"], $rest_index);
$pos_args = array_slice($argv, $rest_index);

$quiet   = isset($opts["q"]);
$verbose = isset($opts["v"]);
Console::init($quiet, $verbose);

if (isset($opts["h"]) || isset($opts["help"])) {
	echo "Usage: php bin/build.php [-q] [-v] [output_dir]\n";
	echo "  -q  quiet mode\n";
	echo "  -v  verbose mode\n";
	echo "If output_dir is not set, defaults to bundle/standalone/\n";
	die();
}

$output_dir = $pos_args[0] ?? APP_DIR . '/bundle/standalone';

if (!is_dir($output_dir)) {
	mkdir($output_dir, 0755, true) ||
		Console::error("Output directory $output_dir could not be created", 1, true);
}

$output_dir = realpath(rtrim($output_dir, "/"));
Console::setOutputDir($output_dir);
Console::notice("Output: " . Console::relpath($output_dir));

new HTML_Exporter($output_dir);

// Copy PHP runtime files
$php_files = ["index.php", "events.php", "bootstrap.php", "functions.php"];
foreach ($php_files as $file) {
	$src = APP_DIR . "/src/bundle/standalone/$file";
	$dest = "$output_dir/$file";
	Console::detail("copy $file ← src/bundle/standalone/$file");
	if (copy($src, $dest)) {
		touch($dest, filemtime($src));
	} else {
		Console::error("Failed to copy $file", 1);
	}
}

$code = Console::exitCode();
$dest = Console::relpath($output_dir);
Console::notice($code === 0 ? "Done — output in $dest" : "Finished with errors — output in $dest");
exit($code);
