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
		Aggregator::admin_notice("Reading $config");
		if (!file_exists($config)) {
			// throw new Exception('sources.csv not found');
			echo "Copy config/sources.csv.example as $config and adjust to your taste before running this script.\n\n";
			Aggregator::admin_notice(
				"sources.csv not found, aborting.",
				1,
				true,
			);
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
				Aggregator::admin_notice("Custom calendar $slug $grid_url");
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
			Aggregator::notice(
				"Exclusions file $exclusionsFile not found, it's open bar",
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
				Aggregator::admin_notice(
					"$slug source type {$calendar["type"]} not implemented",
					1,
				);
			}
		}

		usort($this->events, function ($a, $b) {
			return $a->dateUTC <=> $b->dateUTC;
		});

		Aggregator::notice(count($this->events) . " events fetched");
	}

	private function fetch_ical($slug, $calendar)
	{
		# get ical url with a timeout of 5 seconds
		$url = $calendar["ical_url"];

		$command =
			"php " .
			APP_DIR .
			"/parsers/parser-ical.php " .
			escapeshellarg($url);
		try {
			$json = shell_exec($command);
		} catch (Exception $e) {
			Aggregator::admin_notice(
				"$slug $url parse error " .
					" " .
					$e->get_code() .
					": " .
					$e->get_message(),
			);
			return;
		}
		$source_events = json_decode($json, true);

		if (empty($source_events)) {
			Aggregator::notice("$slug no events");
			return;
		}
		if (!is_array($source_events)) {
			Aggregator::admin_notice(
				"$slug $url error: wrong answer format",
				1,
			);
			return;
		}

		$events = [];
		foreach ($source_events as $source) {
			// Aggregator::admin_notice("source " . print_r($source, true) );

			$event = new Event($source, $calendar);
			if ($event === false) {
				continue;
			}
			// Don't override if already fetched, it might be a later date for repeating events
			// TODO: check if repeating events share the same uid
			// TODO: generate uid if not present (should not happen wih iCal though)
			// if(empty($events[$event->uid]) && empty($this->events[$event->uid])) {
			$events[$event->hash] = $event;
			// }
		}
		Aggregator::notice("$slug " . count($events) . " events");
		$this->events = array_merge($this->events, $events);
	}

	private function fetch_opensimworld()
	{
		$slug = "opensimworld";
		$calendar = [
			"slug" => $slug,
			"grid_url" => null,
			"type" => "crawler",
		];

		$command = "php " . APP_DIR . "/parsers/parser-opensimworld.php";
		try {
			$json = shell_exec($command);
		} catch (Exception $e) {
			Aggregator::admin_notice(
				"$slug $url parse error " .
					" " .
					$e->get_code() .
					": " .
					$e->get_message(),
			);
			return;
		}
		$source_events = json_decode($json, true);

		if (empty($source_events)) {
			Aggregator::notice("$slug no events");
			return;
		}
		if (!is_array($source_events)) {
			Aggregator::admin_notice(
				"$slug $url error: wrong answer format",
				1,
			);
			return;
		}

		$events = [];
		foreach ($source_events as $source) {
			// Aggregator::admin_notice("source " . print_r($source, true) );

			$event = new Event($source, $calendar);
			if ($event === false) {
				continue;
			}
			// Don't override if already fetched, it might be a later date for repeating events
			// TODO: check if repeating events share the same uid
			// TODO: generate uid if not present (should not happen wih iCal though)
			// if(empty($events[$event->uid]) && empty($this->events[$event->uid])) {
			$events[$event->hash] = $event;
			// }
		}
		Aggregator::notice("$slug " . count($events) . " events");
		$this->events = array_merge($this->events, $events);
	}

	public function get_events()
	{
		return $this->events;
	}
}
