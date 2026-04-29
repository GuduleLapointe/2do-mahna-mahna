<?php
/**
 * Fetcher class
 *
 * Fetches events from various sources and stores them as Event objects in an array.
 *
 * @property array $calendars     // Array of source calendars to fetch
 * @property int $timeout         // Fetch timeout in seconds
 * @property array $events        // Array of fetched events
 */

if (!IS_AGGR) {
	die("No direct calls, run main script aggregator.php instead." . PHP_EOL);
}

class Fetcher
{
	private $calendars = [];
	private $timeout = 5;
	private $events = [];
	private static $exclusions = [];

	public function __construct()
	{
		$this->read_config_ical();
		$this->read_exclusions();
		$this->fetch();
		// print_r($this->events);
	}

	private function read_config_ical($config = APP_DIR . "/config/sources.csv")
	{
		Console::verbose("Reading $config");
		if (!file_exists($config)) {
			echo "Copy config/sources.csv.example as $config and adjust to your taste before running this script.\n\n";
			Console::error("sources.csv not found, aborting.", 1, true);
		}

		$csv = file($config);
		#ignore empty lines, lines containing only spaces and lines starting with # or ;
		$csv = array_filter($csv, function ($line) {
			return !empty($line) &&
				$line[0] != "#" &&
				$line[0] != ";" &&
				!ctype_space($line);
		});

		foreach ($csv as $line) {
			#ignore empty lines, lines containing only spaces and lines starting with # or ;

			if (
				empty($line) ||
				$line[0] == "#" ||
				$line[0] == ";" ||
				ctype_space($line)
			) {
				continue;
			}

			// Treat lines with less than 2 commas as a custom calendars
			if (substr_count($line, ",") < 2) {
				if (substr_count($line, ",") == 1) {
					[$slug, $grid_url] = explode(",", $line);
				} else {
					$slug = $line;
					$grid_url = "";
				}
				Console::verbose("Custom calendar $slug $grid_url");
				$this->custom[$slug] = $line;
				continue;
			}

			[$slug, $grid_url, $ical_url] = explode(",", $line);
			if (empty($slug) || empty($grid_url) || empty($ical_url)) {
				continue;
			}
			$this->calendars[$slug] = [
				"slug" => $slug,
				"grid_url" => $grid_url,
				"ical_url" => trim($ical_url),
				"type" => "ical",
			];
		}
	}

	private function read_exclusions(
		$exclusionsFile = APP_DIR . "/config/exclude.txt",
	) {
		if (!file_exists($exclusionsFile)) {
			Console::notice(
				"Exclusions file not found ($exclusionsFile), no filtering applied",
			);
			return;
		}

		$file = new SplFileObject($exclusionsFile);

		while (!$file->eof()) {
			$exclusion = $file->fgets();
			$exclusion = trim($exclusion);

			// Ignore empty lines
			if ($exclusion === "") {
				continue;
			}

			// Split the exclusion into slug and title
			$parts = preg_split("/\s+/", $exclusion, 2);
			$slug = $parts[0];

			if ($slug == "#" || $slug == "//") {
				continue;
			}
			$title = $parts[1] ?? "";

			// Ignore empty titles
			if (empty($title)) {
				continue;
			}

			self::$exclusions[$slug][] = $title;
		}
	}

	public function get_exclusions()
	{
		return self::$exclusions;
	}

	public static function isExcluded($slug, $title)
	{
		if (isset(self::$exclusions[$slug])) {
			// Should use prep_grep but it doesn't work properly with regex
			foreach (self::$exclusions[$slug] as $exclusion) {
				if (preg_match("/^" . $exclusion . "$/", $title)) {
					return true;
				}
			}
		}

		return false;
	}

	public function fetch()
	{
		$this->fetch_opensimworld();

		foreach ($this->calendars as $slug => $calendar) {
			if ($calendar["type"] == "ical") {
				$this->fetch_ical($slug, $calendar);
			} else {
				Console::error(
					"$slug source type {$calendar["type"]} not implemented",
					1,
				);
			}
		}

		usort($this->events, function ($a, $b) {
			return $a->dateUTC <=> $b->dateUTC;
		});

		$this->deduplicate();

		Console::notice(count($this->events) . " events fetched");
	}

	/**
	 * Deduplicate events after sorting.
	 *
	 * Rule 1 (always): merge consecutive events with the same title and destination
	 *   whose start is within 1 hour of the previous event's end.
	 * Rule 2 (optional, default on): drop duplicate same-slot events (same start,
	 *   duration, and destination) that come from different sources.
	 */
	private function deduplicate(): void
	{
		$before = count($this->events);
		$this->mergeConsecutive();
		$afterMerge = count($this->events);

		$dedupCrossSource = (bool) Config::get('dedup_cross_source', true);

		if ($dedupCrossSource) {
			$this->deduplicateSameSlot();
		}

		$after = count($this->events);
		if ($after < $before) {
			Console::notice(
				sprintf(
					"Deduplication: %d → %d events (%d merged, %d cross-source duplicates removed)",
					$before,
					$after,
					$before - $afterMerge,
					$afterMerge - $after,
				),
			);
		}
	}

	/**
	 * Merge consecutive events that share the same title and destination and
	 * whose next start falls within 1 hour of the current event's end.
	 */
	private function mergeConsecutive(): void
	{
		$merged = [];
		foreach ($this->events as $event) {
			$last = end($merged);
			if (
				$last !== false &&
				$last->name === $event->name &&
				$last->simname === $event->simname
			) {
				$lastEnd = strtotime($last->dateUTC) + $last->duration * 60;
				$nextStart = strtotime($event->dateUTC);
				if ($nextStart <= $lastEnd + 3600) {
					$eventEnd =
						strtotime($event->dateUTC) + $event->duration * 60;
					$last->duration =
						(int) ((max($lastEnd, $eventEnd) -
							strtotime($last->dateUTC)) /
							60);
					continue;
				}
			}
			$merged[] = $event;
		}
		$this->events = $merged;
	}

	/**
	 * Remove events that share the same start time, duration, and destination
	 * as an already-seen event (cross-source duplicates). The first occurrence wins.
	 */
	private function deduplicateSameSlot(): void
	{
		$seen = [];
		$result = [];
		foreach ($this->events as $event) {
			$key =
				$event->dateUTC .
				"|" .
				$event->duration .
				"|" .
				$event->simname;
			if (!isset($seen[$key])) {
				$seen[$key] = true;
				$result[] = $event;
			}
		}
		$this->events = $result;
	}

	private function fetch_ical($slug, $calendar)
	{
		$url = $calendar["ical_url"];
		Console::notice("Fetching $slug...");

		$command =
			"php " .
			APP_DIR .
			"/app/Services/Parsers/parser-ical.php " .
			escapeshellarg($url);
		try {
			$json = shell_exec($command);
		} catch (Exception $e) {
			Console::error(
				"$slug parse error: " .
					$e->get_code() .
					": " .
					$e->get_message(),
			);
			return;
		}
		$source_events = json_decode($json ?? "", true);

		if (empty($source_events)) {
			return;
		}
		if (!is_array($source_events)) {
			Console::error("$slug $url error: wrong answer format", 1);
			return;
		}

		Console::notice(
			"Processing " . count($source_events) . " events from $slug",
		);
		$events = [];
		foreach ($source_events as $source) {
			$event = new Event($source, $calendar);
			if ($event === false) {
				continue;
			}
			$events[$event->hash] = $event;
		}
		$this->events = array_merge($this->events, $events);
	}

	private function fetch_opensimworld()
	{
		$slug = "opensimworld";
		Console::notice("Fetching $slug...");
		$calendar = [
			"slug" => $slug,
			"grid_url" => null,
			"type" => "crawler",
		];

		$command =
			"php " . APP_DIR . "/app/Services/Parsers/parser-opensimworld.php";
		try {
			$json = shell_exec($command);
		} catch (Exception $e) {
			Console::error(
				"$slug parse error: " .
					$e->get_code() .
					": " .
					$e->get_message(),
			);
			return;
		}
		$source_events = json_decode($json ?? "", true);

		if (empty($source_events)) {
			return;
		}
		if (!is_array($source_events)) {
			Console::error("$slug error: wrong answer format", 1);
			return;
		}

		Console::notice(
			"Processing " . count($source_events) . " events from $slug",
		);
		$events = [];
		foreach ($source_events as $source) {
			$event = new Event($source, $calendar);
			if ($event === false) {
				continue;
			}
			$events[$event->hash] = $event;
		}
		$this->events = array_merge($this->events, $events);
	}

	public function get_events()
	{
		return $this->events;
	}
}
