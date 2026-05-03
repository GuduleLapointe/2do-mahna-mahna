<?php
/**
 * SearchDB — OpenSimSearch database connection manager.
 *
 * Wraps opensim-helpers includes/databases.php and includes/search.php,
 * initialising the shared $SearchDB connection from .env credentials.
 * Also extends the standard events table schema with 2DO-specific columns
 * (uid, tags, source) that are not part of the OpenSim search standard.
 *
 * Usage:
 *   SearchDB::init();          // call once at startup
 *   $db = SearchDB::get();     // returns OSPDO|null
 *   SearchDB::connected();     // bool
 */
class SearchDB
{
	private static ?OSPDO $db = null;

	/**
	 * Initialise the SearchDB connection.
	 *
	 * Defines the constants expected by opensim-helpers (SEARCH_DB_HOST etc.)
	 * from Config, then includes databases.php and search.php as-is.
	 * Returns false (silently) if credentials are not configured.
	 */
	public static function init(): bool
	{
		$host = Config::get("search_db_host");
		$name = Config::get("search_db_name");
		$user = Config::get("search_db_user", "");
		$pass = Config::get("search_db_pass", "");

		if (empty($host) || empty($name)) {
			Console::verbose("SEARCH_DB_HOST / SEARCH_DB_NAME not set — SearchDB disabled");
			return false;
		}

		// SEARCH_TABLE_EVENTS is guarded by if(!defined()) in search.php — safe to pre-define
		if (!defined("SEARCH_TABLE_EVENTS")) {
			define("SEARCH_TABLE_EVENTS", Config::get("search_table_events", "events"));
		}

		// SEARCH_REGION_TABLE is defined unconditionally by search.php via auto-detection.
		// Pre-define from .env when explicitly set to prevent conflicts with an OpenSim
		// regions table sharing the same DB. The duplicate define() in search.php will
		// fail silently (keeping our value) but may emit E_WARNING on PHP 8 — acceptable.
		$regionTable = Config::get("search_region_table");
		if ($regionTable && !defined("SEARCH_REGION_TABLE")) {
			define("SEARCH_REGION_TABLE", $regionTable);
		}

		define("SEARCH_DB_HOST", $host);
		define("SEARCH_DB_NAME", $name);
		define("SEARCH_DB_USER", $user);
		define("SEARCH_DB_PASS", $pass);

		require_once APP_DIR . "/lib/opensim-helpers/includes/databases.php";
		require_once APP_DIR . "/lib/opensim-helpers/includes/search.php";

		global $SearchDB;
		if (!$SearchDB || !$SearchDB->connected) {
			Console::error("Could not connect to Search DB {$name}@{$host}");
			return false;
		}

		self::$db = $SearchDB;
		self::extendSchema();
		return true;
	}

	/** Return the active OSPDO connection, or null if not initialised. */
	public static function get(): ?OSPDO
	{
		return self::$db;
	}

	/** Return true if the connection is active. */
	public static function connected(): bool
	{
		return self::$db !== null && self::$db->connected;
	}

	/**
	 * Add 2DO-specific columns to the events table when missing.
	 *
	 * Uses the same SHOW COLUMNS / ALTER TABLE pattern as opensim-helpers
	 * schema updates (ossearch_db_update_*) to remain non-destructive.
	 */
	private static function extendSchema(): void
	{
		$db    = self::$db;
		$table = SEARCH_TABLE_EVENTS;

		$columns = [
			"uid"    => "ALTER TABLE `$table` ADD `uid` varchar(255) DEFAULT NULL",
			"tags"   => "ALTER TABLE `$table` ADD `tags` text DEFAULT NULL",
			"source" => "ALTER TABLE `$table` ADD `source` varchar(100) DEFAULT NULL",
		];

		foreach ($columns as $col => $sql) {
			if (!count($db->query("SHOW COLUMNS FROM `$table` LIKE '$col'")->fetchAll())) {
				$db->query($sql);
			}
		}
	}
}
