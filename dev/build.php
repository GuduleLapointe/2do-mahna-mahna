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

require_once dirname(__DIR__) . "/bootstrap.php";

define("IS_AGGR", true);

require_once APP_DIR . "/app/Services/Exporters/export-html.php";

$opts = getopt("qvh", ["help"], $rest_index);
$pos_args = array_slice($argv, $rest_index);

$quiet = isset($opts["q"]);
$verbose = isset($opts["v"]);
Console::init($quiet, $verbose);

if (isset($opts["h"]) || isset($opts["help"])) {
	echo "Usage: php bin/build.php [-q] [-v] [output_dir]\n";
	echo "  -q  quiet mode\n";
	echo "  -v  verbose mode\n";
	echo "If output_dir is not set, defaults to bundle/standalone/\n";
	die();
}

$output_dir = $pos_args[0] ?? APP_DIR . "/bundle/standalone";

if (!is_dir($output_dir)) {
	mkdir($output_dir, 0755, true) ||
		Console::error(
			"Output directory $output_dir could not be created",
			1,
			true,
		);
}

$output_dir = realpath(rtrim($output_dir, "/"));
Console::setOutputDir($output_dir);
Console::notice("Output: " . Console::relpath($output_dir));

new HTML_Exporter($output_dir);

// Compile PHP runtime into a single PHAR (index.php)
$phar_file = $output_dir . "/index.php";
$phar_tmp = $output_dir . "/index.phar";
foreach ([$phar_file, $phar_tmp] as $f) {
	if (file_exists($f)) {
		unlink($f);
	}
}
try {
	$phar = new Phar($phar_tmp);
} catch (UnexpectedValueException $e) {
	Console::error(
		"Cannot create PHAR — set phar.readonly=0 in php.ini: " .
			$e->getMessage(),
		1,
		true,
	);
}
$phar->startBuffering();
$phar_sources = [
	"index.php" => "src/bundle/standalone/index.php",
	"events.php" => "src/bundle/standalone/events.php",
	"bootstrap.php" => "src/bundle/standalone/bootstrap.php",
	"functions.php" => "src/bundle/standalone/functions.php",
	"Config.php" => "app/Shared/Config.php",
	"Scrup.php" => "app/Shared/Scrup.php",
	"templates/events.lsl" => "src/bundle/standalone/templates/events.lsl",
	"templates/404.html" => "src/bundle/standalone/templates/404.html",
	"scrup/scrup.php" => "lib/scrup/scrup.php",
	"scrup/functions.php" => "lib/scrup/functions.php",
	"scrup/sqlite.php" => "lib/scrup/sqlite.php",
];
foreach ($phar_sources as $internal => $src_rel) {
	Console::detail("pack $internal ← $src_rel");
	$phar->addFile(APP_DIR . "/$src_rel", $internal);
}
$phar->setStub(
	'<?php Phar::mapPhar(); require "phar://" . __FILE__ . "/index.php"; __HALT_COMPILER(); ?>',
);
$phar->stopBuffering();
rename($phar_tmp, $phar_file);
Console::detail("write index.php ← PHAR (" . count($phar_sources) . " files)");

// Copy lib/scrup/ → bundle/standalone/scrup/ (PHP files only, no config.php or scripts/)
$scrup_src = APP_DIR . "/lib/scrup";
$scrup_dest = $output_dir . "/scrup";
if (!is_dir($scrup_dest)) {
	mkdir($scrup_dest, 0755, true);
}
$scrup_skip = ["config.php", "scripts"];
foreach (new DirectoryIterator($scrup_src) as $f) {
	if ($f->isDot() || in_array($f->getFilename(), $scrup_skip)) {
		continue;
	}
	if ($f->isFile() && $f->getExtension() === "php") {
		$dst = $scrup_dest . "/" . $f->getFilename();
		Console::detail(
			"copy scrup/" .
				$f->getFilename() .
				" ← lib/scrup/" .
				$f->getFilename(),
		);
		copy($f->getPathname(), $dst) && touch($dst, $f->getMTime());
	}
}

$code = Console::exitCode();
$dest = Console::relpath($output_dir);
Console::notice(
	$code === 0
		? "Done — output in $dest"
		: "Finished with errors — output in $dest",
);
exit($code);

