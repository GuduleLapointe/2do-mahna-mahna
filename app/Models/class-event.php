<?php

use Kigkonsult\Icalcreator\Vcalendar;
use Kigkonsult\Icalcreator\Vevent;

/**
 * Event class
 *
 * Represents an event of the calendar
 *
 * @property string $uid            // Unique identifier, collected from source if possible
 * @property string $name           // Event name
 * @property string $description    // Event description
 * @property string $simname        // Region name for standalone grids only
 *                                  // Region Hypergrid URL for hypergrid events
 * @property string $dateUTC        // Event start date and time in UTC
 * @property int $duration          // Event duration in minutes
 * @property int $category          // Event category code number
 * @property array $tags             // Array of category names
 * @property int $categories        // Array of category codes
 * @property string $owneruuid      // Not implemented
 * @property string $creatoruuid    // Not implemented
 * @property int $covercharge       // Not implemented
 * @property int $coveramount       // Not implemented
 * @property string $parcelUUID     // Not implemented
 * @property string $globalPos      // Not implemented
 * @property int $eventflags
 * @property string $gatekeeperURL  // Region grid target URL
 * @property string $hash           // Not implemented
 */
class Event
{
	public $uid;
	public $name;
	public $description;
	public $simname;
	public $dateUTC;
	public $duration;
	public $category;
	public $categories;
	public $owneruuid;
	public $creatoruuid;
	public $covercharge;
	public $coveramount;
	public $parcelUUID;
	public $globalPos;
	public $eventflags;
	public $gatekeeperURL;
	public $hash;
	public $source;
	public $teleport;
	public $tags;

	/**
	 * Event constructor.
	 *
	 * @param array $data
	 */
	public function __construct($data, $calendar = [])
	{
		$original_data = $data;
		// Make sure all required indices are present
		$data = array_merge(EVENT_STRUCTURE, $data);

		if (Fetcher::isExcluded($calendar["slug"], $data["name"])) {
			Console::verbose("[{$calendar["slug"]}] {$data["name"]} is in exclusion list");
			return false;
		}

		$data["category"] = $this->sanitize_category($data["category"]);

		if (empty($data["simname"])) {
			$description = preg_replace("/%20/", " ", $data["description"]);
			$reg_protocol = "((https?|hop:|secondlife:)\/\/)?";
			$reg_host = "([\w-]+(\.[\w-]+)+)";
			$reg_port = "(:\d+)";
			$reg_region = "([:\/ \+]([\w _\+-](%20)?)+)?";
			$reg_xyz = "((\/\d+){3})?";
			$pattern = "/$reg_protocol$reg_host$reg_port$reg_region$reg_xyz/";
			preg_match($pattern, $description, $matches);
			if (!empty($matches)) {
				$data["simname"] = $matches[0];
			}
		}

		$sanitized_url = $this->sanitize_hgurl(
			$data["simname"],
			$calendar["grid_url"],
		);
		if ($sanitized_url === false) {
			Console::verbose(sprintf(
				"%s event %s error checking sanitize_hgurl(%s, %s)",
				$calendar["slug"], $data["uid"],
				$data["simname"] ?? "", $calendar["grid_url"],
			));
			return false;
		}
		if (empty($sanitized_url)) {
			Console::verbose(sprintf(
				"%s event %s has no location %s",
				$calendar["slug"], $data["uid"],
				empty($data["simname"]) ? $calendar["grid_url"] : $data["simname"],
			));
			return false;
		}
		$data["simname"] = $sanitized_url;

		$tags = $data["tags"];
		if (empty($tags)) {
			$tags = [];
		} elseif (!is_array($tags)) {
			$tags = [$tags];
		}
		// $tags = array_unique( array_merge ( $tags, array( $calendar['slug'] ) ) );
		$tags = array_filter($tags);
		$tags = array_unique($tags);

		// Sanitize $data['description'], replace "\n" with actual new lines, trim trailing spaces and new lines
		$data["description"] = trim(
			str_replace("\\n", PHP_EOL, $data["description"]),
		);

		// TODO: generate uid if not present (for other sources than iCal)
		$this->uid = $data["uid"];
		$this->owneruuid = $data["owneruuid"];
		$this->name = $data["name"];
		$this->creatoruuid = $data["creatoruuid"];
		$this->category = $data["category"]; // OpenSim/SL category code
		$this->tags = $tags; // Array of category names
		$this->description = $data["description"];
		$this->dateUTC = $data["dateUTC"];
		$this->duration = $data["duration"];
		$this->covercharge = $data["covercharge"];
		$this->coveramount = $data["coveramount"];
		$this->simname = $data["simname"];
		$this->parcelUUID = $data["parcelUUID"];
		$this->globalPos = $data["globalPos"];
		$this->eventflags = $data["eventflags"];
		$this->gatekeeperURL = $data["gatekeeperURL"];
		// eventlist.py:        new_hash = hashlib.md5( str(event_start) + hgurl ).hexdigest()
		$this->hash = md5($this->dateUTC . $this->simname);
		$this->source = $calendar["slug"];
		$this->teleport = [
			"HOP" => opensim_format_tp($this->simname, TPLINK_HOP),
			"HG" => opensim_format_tp($this->simname, TPLINK_HG),
			"V3HG" => opensim_format_tp($this->simname, TPLINK_V3HG),
		];
	}

	/**
	 * Sanitize a hypergrid URL
	 *
	 * @param string $url           // URL to sanitize
	 * @param string $grid_url      // Grid URL to use if $url is empty or missin host
	 * @return string|bool          // Sanitized URL or false if the region is offline or invalid
	 */
	public function sanitize_hgurl($url, $grid_url = null)
	{
		static $sanitize_hgurl_cache = [];
		static $globalpos_cache = [];

		if (empty($url)) {
			$url = $grid_url;
		}

		// Return cached value if available
		if (isset($sanitize_hgurl_cache[$url])) {
			$this->globalPos = $globalpos_cache[$url] ?? implode(',', DEFAULT_POS);
			switch ($sanitize_hgurl_cache[$url]) {
				case "empty":
					return;
				case "offline":
					Console::verbose("cached region $url is offline");
					return false;
				case "invalid":
					Console::verbose("cached region $url is invalid");
					return false;
			}
			return $sanitize_hgurl_cache[$url];
		}

		$region = opensim_sanitize_uri($url, $grid_url, true);

		$tmpurl = opensim_sanitize_uri($url, $grid_url);

		$region_data = opensim_get_region($tmpurl);

		if (empty($region_data)) {
			Console::verbose("region $tmpurl data could not be fetched (empty)");

			$sanitize_hgurl_cache[$url] = "invalid";
			return false;
		}
		$region["region"] =
			empty($region["region"]) & !empty($region_data["region_name"])
				? $region_data["region_name"]
				: $region["region"];
		if (!opensim_region_is_online($region)) {
			Console::verbose("region $tmpurl is offline");

			$sanitize_hgurl_cache[$url] = "offline";
			return false;
		}

		// Compute absolute grid position: local pos + region grid coordinates
		$pos = empty($region["pos"]) ? DEFAULT_POS : array_map("intval", explode("/", $region["pos"]));
		if (!empty($region_data["x"]) && !empty($region_data["y"])) {
			$pos[0] += (int) $region_data["x"];
			$pos[1] += (int) $region_data["y"];
		}
		$this->globalPos = $globalpos_cache[$url] = implode(",", $pos);

		$tmpurl =
			$region["gatekeeper"] .
			":" .
			$region["region"] .
			(empty($region["pos"]) ? "" : "/" . $region["pos"]);
		$url = opensim_format_tp($tmpurl, TPLINK_TXT);

		$sanitize_hgurl_cache[$url] = $url;
		return $url;
	}

	/**
	 * Sanitize category code.
	 *
	 * Return category code number if $value is a valid number, otherwise the best guess
	 * based on the given string(s). First match wins.
	 *
	 * @param integer|string|array $values  // Category name or array of category names
	 * @return int                          // Valid category code number
	 */
	public function sanitize_category($values)
	{
		if (empty($values)) {
			return 0; // Undefined
		}
		if (!is_array($values)) {
			$values = [$values];
		}
		foreach ($values as $value) {
			if (is_int($value)) {
				return $value;
			}
			$key = strtolower($value);
			if (isset($this->categories[$key])) {
				return $this->categories[$key];
			}
		}
		return 29; // Not undefined, but unknown, so we return Miscellaneous
	}
}
