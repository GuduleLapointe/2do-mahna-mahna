#!/usr/bin/env php
<?php
/**
 * 2do Aggregator
 *
 * Main script, use from terminal or cron task.
 * DO NOT PUBLISH THIS FILE IN A WEB SERVER.
 *
 * Usage: php aggregator.php [-q] [-v] [-f] [output_dir]
 *
 * See README.md for more information.
 *
 * @package 2do-aggregator
 * @version 0.3.0
 *
 * Plugin Name: (not a plugin, but keep this line, needed by bumping tool)
 **/

if (php_sapi_name() != "cli") {
	die("This script can only be run from the command line." . PHP_EOL);
}

require_once dirname(__DIR__) . "/bootstrap.php";

/**
 * Aggregator class
 *
 * Main class, set session parameters, load needed files and start processing
 */
class Aggregator
{
	public $output_dir;
	private static $force;
	public static $script;

	public function __construct()
	{
		global $argv;

		$this->load_args($argv);

		self::constants();
		self::includes();

		$this->run();
	}

	/**
	 * Run aggregator processes
	 */
	public function run()
	{
		Console::notice("Output: " . Console::relpath($this->output_dir));

		$config_file = APP_DIR . "/config/config.json";

		Config::load(
			defaults: ["dedup_cross_source" => true],
			jsonFile: $config_file,
			envFiles: [APP_DIR . "/.env"],
		);

		if (!SearchDB::init()) {
			Console::error(
				"SearchDB is required — set SEARCH_DB_HOST and SEARCH_DB_NAME in .env",
				1,
				true,
			);
		}

		if (self::$force) {
			Console::notice(
				"Cache cleared (per-source caches will be skipped)",
			);
		}

		Console::notice("Fetching events...");
		$fetcher = new Fetcher();
		$count = EventStorage::write($fetcher->get_events());
		Console::verbose("$count events stored in SearchDB");

		Console::notice(
			"Exporting " . count(EventStorage::readEvents()) . " events...",
		);
		new HYPEvents_Exporter($this->output_dir);
		new JSON_Exporter($this->output_dir);
		new iCal_Exporter($this->output_dir);

		$code = Console::exitCode();
		$dest = Console::relpath($this->output_dir);
		if ($code === 0) {
			Console::notice("Done — output in $dest");
		} else {
			Console::notice("Finished with errors — output in $dest");
		}
		exit($code);
	}

	/**
	 * Include application files
	 */
	private static function includes()
	{
		require_once APP_DIR . "/lib/opensim-functions.php";

		require_once APP_DIR . "/app/Services/class-fetcher.php";
		require_once APP_DIR . "/app/Models/class-region.php";
		require_once APP_DIR . "/app/Models/class-event.php";

		require_once APP_DIR . "/app/Shared/Cache.php";
		require_once APP_DIR . "/app/Shared/SearchDB.php";
		require_once APP_DIR . "/app/Services/EventStorage.php";

		require_once APP_DIR . "/app/Services/Exporters/export-hypevents.php";
		require_once APP_DIR . "/app/Services/Exporters/export-json.php";
		require_once APP_DIR . "/app/Services/Exporters/export-ical.php";
	}

	/**
	 * Define application constants
	 */
	private static function constants()
	{
		Console::verbose("APP_DIR: " . APP_DIR);

		define("EVENTS_NULL_KEY", "00000000-0000-0000-0000-000000000001");
		define("DEFAULT_POS", [128, 128, 25]);

		define("CATEGORIES", [
			"discussion" => 18,
			"sports" => 19,
			"live music" => 20,
			"commercial" => 22,
			"nightlife/entertainment" => 23,
			"games/contests" => 24,
			"pageants" => 25,
			"education" => 26,
			"arts and culture" => 27,
			"charity/support groups" => 28,
			"miscellaneous" => 29,

			// Aliases
			"nightlife" => 23, // Nightlife/Entertainment
			"entertainment" => 23, // Nightlife/Entertainment
			"games" => 24, // Games/Contests
			"contests" => 24, // Games/Contests
			"charity" => 28, // Charity / Support Groups
			"support groups" => 28, // Charity / Support Groups

			// From HYPEvents code:
			"music" => 20, // Live Music
			"fair" => 23, // Nightlife/Entertainment
			"roleplay" => 24, // Games/Contests
			"education" => 26, // Education
			"art" => 27, // Art & Culture
			"lecture" => 27, // Art & Culture
			"litterature" => 27, // Art & Culture
			"social" => 28, // Charity / Support Groups
		]);

		define("EVENT_STRUCTURE", [
			"source" => null,
			"uid" => null,
			"owneruuid" => EVENTS_NULL_KEY, // Not implemented
			"name" => null,
			"creatoruuid" => EVENTS_NULL_KEY, // Not implemented
			"category" => null,
			"description" => null,
			"dateUTC" => null,
			"duration" => null,
			"covercharge" => 0, // Not implemented
			"coveramount" => 0, // Not implemented
			"simname" => null,
			"parcelUUID" => EVENTS_NULL_KEY, // Not implemented
			"globalPos" => null,
			"eventflags" => 0, // Not implemented
			"gatekeeperURL" => null,
			"hash" => null,
		]);
	}

	/**
	 * Parse CLI arguments and initialize Console
	 */
	private function load_args($args = [])
	{
		global $argv;

		self::$script = basename($argv[0]);
		$rest_index = null;
		$opts = getopt(
			"qvhf",
			["help", "version", "force", "clear-cache"],
			$rest_index,
		);
		$pos_args = array_slice($argv, $rest_index);

		$quiet = isset($opts["q"]);
		$verbose = isset($opts["v"]);
		self::$force =
			isset($opts["f"]) ||
			isset($opts["force"]) ||
			isset($opts["clear-cache"]);

		Console::init($quiet, $verbose);

		if (isset($opts["h"]) || isset($opts["help"])) {
			echo "Usage: php " .
				self::$script .
				" [-q] [-v] [-f] [output_dir]\n";
			echo "  -q  quiet mode\n";
			echo "  -v  verbose mode (overridden if -q is set)\n";
			echo "  -f|--force|--clear-cache  clear cache before running\n";
			echo "  -h|--help  show help and die\n";
			echo "  --version  show version and die\n";
			echo "If output_dir is not set, defaults to data/\n";
			die();
		}
		if (isset($opts["version"])) {
			echo "Aggregator version 0.3.0\n";
			die();
		}

		if (isset($pos_args[0])) {
			$output_dir = $pos_args[0];
		} else {
			$output_dir = APP_DIR . "/data";
		}

		if (!is_dir($output_dir)) {
			mkdir($output_dir, 0755, true) ||
				Console::error(
					"Output directory $output_dir could not be created",
					1,
					true,
				);
		}

		$this->output_dir = realpath(rtrim($output_dir, "/"));
		Console::setOutputDir($this->output_dir);
		Console::verbose("Output directory: " . $this->output_dir);
	}

	public static function force()
	{
		return self::$force;
	}

	public static function remove_emoji($string)
	{
		$symbols =
			"\x{1F100}-\x{1F1FF}" . // Enclosed Alphanumeric Supplement
			"\x{1F300}-\x{1F5FF}" . // Miscellaneous Symbols and Pictographs
			"\x{1F600}-\x{1F64F}" . // Emoticons
			"\x{1F680}-\x{1F6FF}" . // Transport And Map Symbols
			"\x{1F900}-\x{1F9FF}" . // Supplemental Symbols and Pictographs
			"\x{2600}-\x{26FF}" . // Miscellaneous Symbols
			"\x{2700}-\x{27BF}"; // Dingbats

		return preg_replace("/[" . $symbols . "]+/u", "", (string) $string);
	}
}

new Aggregator();

