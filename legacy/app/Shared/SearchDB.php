<?php
/**
 * SearchDB — OpenSimSearch database connection.
 *
 * Extends OSPDO (which extends PDO) with SearchDB-specific table management.
 * Credentials are read from Config (SEARCH_DB_* keys in .env).
 *
 * Usage:
 *   global $SearchDB;
 *   $SearchDB = SearchDB::get();   // factory: connect and return instance, or null
 *   // — or —
 *   $SearchDB = new SearchDB('mysql:host=…;dbname=…', $user, $pass);
 *
 * Per the naming convention used across the app ($SearchDB, $ScrupDB,
 * $OpenSimDB, …) the caller is responsible for assigning the global.
 * SearchDB itself never touches a global variable.
 */
if (!TODO_APP) {
	die("No direct calls." . PHP_EOL);
}

class SearchDB extends OSPDO
{
	/**
	 * Connect to the SearchDB using credentials from Config/.env.
	 *
	 * Defines SEARCH_TABLE_EVENTS, SEARCH_REGION_TABLE, and SEARCH_DB_*
	 * constants required by opensim-helpers, then loads search.php for its
	 * function definitions (ossearch_db_tables, osdb_cache_get/set, …).
	 * Extends the events table schema with 2DO-specific columns.
	 *
	 * Returns null when credentials are not configured or connection fails.
	 *
	 * @return static|null
	 */
	public static function get(): static|null
	{
		$host = Config::get("search_db_host");
		$name = Config::get("search_db_name");
		$user = Config::get("search_db_user", "");
		$pass = Config::get("search_db_pass", "");

		if (empty($host) || empty($name)) {
			Console::verbose("SEARCH_DB_HOST / SEARCH_DB_NAME not set — SearchDB disabled");
			return null;
		}

		// Constants required by opensim-helpers search.php
		if (!defined("SEARCH_DB_HOST")) {
			define("SEARCH_DB_HOST", $host);
			define("SEARCH_DB_NAME", $name);
			define("SEARCH_DB_USER", $user);
			define("SEARCH_DB_PASS", $pass);
		}
		if (!defined("SEARCH_TABLE_EVENTS")) {
			define("SEARCH_TABLE_EVENTS", Config::get("search_table_events", "events"));
		}
		$regionTable = Config::get("search_region_table");
		if ($regionTable && !defined("SEARCH_REGION_TABLE")) {
			define("SEARCH_REGION_TABLE", $regionTable);
		}

		// Load search.php for function definitions (osdb_cache_get/set, ossearch_db_tables…).
		// It also creates its own $SearchDB global at load time; we ignore that instance.
		if (!function_exists("osdb_cache_get")) {
			require_once APP_DIR . "/lib/opensim-helpers/includes/search.php";
		}

		$db = new static("mysql:host=$host;dbname=$name;charset=utf8", $user, $pass);
		if (!$db->connected) {
			Console::error("Could not connect to Search DB {$name}@{$host}");
			return null;
		}

		$db->extendSchema();
		return $db;
	}

	/**
	 * Add 2DO-specific columns to the events table when missing.
	 *
	 * Uses ALTER TABLE … ADD only when the column is absent, matching the
	 * non-destructive pattern of opensim-helpers schema updates.
	 */
	public function extendSchema(): void
	{
		$table = SEARCH_TABLE_EVENTS;

		$columns = [
			"uid"    => "ALTER TABLE `$table` ADD `uid` varchar(255) DEFAULT NULL",
			"tags"   => "ALTER TABLE `$table` ADD `tags` text DEFAULT NULL",
			"source" => "ALTER TABLE `$table` ADD `source` varchar(100) DEFAULT NULL",
		];

		foreach ($columns as $col => $sql) {
			if (!count($this->query("SHOW COLUMNS FROM `$table` LIKE '$col'")->fetchAll())) {
				$this->query($sql);
			}
		}
	}
}
