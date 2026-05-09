<?php
/**
 * Event class
 *
 * Represents an event of the calendar
 *
 * @property string $uid            // Unique identifier, collected from source if possible
 * @property string $name           // Event name
 * @property string $description    // Event description
 * @property string $simName        // Region name for standalone grids only
 *                                  // Region Hypergrid URL for hypergrid events
 * @property string $dateUTC        // Event start date and time in UTC
 * @property int $duration          // Event duration in minutes
 * @property int $category          // Event category code number
 * @property array $tags             // Array of category names
 * @property int $categories        // Array of category codes
 * @property string $ownerUUID      // Not implemented, ROBUST: owner_uuid OSSearch: owneruuid
 * @property string $creatorUUID    // Not implemented; ROBUST: principalID?
 * @property int $coverCharge       // Not implemented
 * @property int $coverAmount       // Not implemented
 * @property string $parcelUUID     // Not implemented
 * @property string $globalPos      // Not implemented
 * @property int $flags				// OSSearch: eventflags
 * @property string $gatekeeperURL  // Region grid target URL
 * @property string $hash           // Not implemented
 */

use Kigkonsult\Icalcreator\Vcalendar;
use Kigkonsult\Icalcreator\Vevent;

class Event
{
	public $uid;
	public $name;
	public $description;
	public $simName;
	public $dateUTC;
	public $duration;
	public $category;
	public $categories;
	public $ownerUUID;
	public $creatorUUID;
	public $coverCharge;
	public $coverAmount;
	public $parcelUUID;
	public $globalPos;
	public $flags;
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
			Console::verbose(
				"[{$calendar["slug"]}] {$data["name"]} is in exclusion list",
			);
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

		$sanitized_url = $this->sanitize_destination_uri(
			$data["simname"],
			$calendar["grid_url"],
		);
		if ($sanitized_url === false) {
			Console::verbose(
				sprintf(
					"%s event %s error checking sanitize_destination_uri(%s, %s)",
					$calendar["slug"],
					$data["uid"],
					$data["simname"] ?? "",
					$calendar["grid_url"],
				),
			);
			return false;
		}
		if (empty($sanitized_url)) {
			Console::verbose(
				sprintf(
					"%s event %s has no location %s",
					$calendar["slug"],
					$data["uid"],
					empty($data["simname"])
						? $calendar["grid_url"]
						: $data["simname"],
				),
			);
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
		$this->ownerUUID = $data["owneruuid"];
		$this->name = $data["name"];
		$this->creatorUUID = $data["creatoruuid"];
		$this->category = $data["category"]; // OpenSim/SL category code
		$this->tags = $tags; // Array of category names
		$this->description = $data["description"];
		$this->dateUTC = $data["dateUTC"];
		$this->duration = $data["duration"];
		$this->coverCharge = $data["covercharge"];
		$this->coverAmount = $data["coveramount"];
		$this->simName = $data["simname"];
		$this->parcelUUID = $data["parcelUUID"];
		$this->globalPos = $data["globalPos"];
		$this->flags = $data["eventflags"];
		$this->gatekeeperURL = $data["gatekeeperURL"];
		// eventlist.py:        new_hash = hashlib.md5( str(event_start) + hgurl ).hexdigest()
		$this->hash = md5($this->dateUTC . $this->simName);
		$this->source = $calendar["slug"];
		$this->teleport = [
			"HOP" => opensim_format_tp($this->simName, TPLINK_HOP),
			"HG" => opensim_format_tp($this->simName, TPLINK_HG),
			"V3HG" => opensim_format_tp($this->simName, TPLINK_V3HG),
		];
	}

	/**
	 * Resolve a raw region URL to a canonical simname string.
	 *
	 * Returns the simname in "host:port Region Name[/x/y/z]" format — no URI scheme,
	 * space before the region name, slash only before position coordinates.
	 * This format is required by the HYPEvents LSL board and validated by tests.
	 *
	 * Constructs the result from Region properties (no second opensim_parse_url call).
	 * Uses the canonical region name from data() when available.
	 *
	 * @param  string      $url       Raw region URL from the event source
	 * @param  string|null $grid_url  Grid gatekeeper URL (fallback when $url has no host)
	 * @return string|false           Canonical simname, or false if URL is unparseable or region offline
	 */
	public function sanitize_destination_uri($url, $grid_url = null)
	{
		$destination = new Region($url, $grid_url);
		if (empty($destination->dest_uri)) {
			return false;
		}
		$destination->data();
		if (!$destination->online()) {
			Console::verbose("region offline: " . ($url ?: $grid_url));
			return false;
		}

		// Build "host:port Region Name[/x/y/z]" from Region properties.
		// dest_uri (host:port/Region/pos) is used only for the emptiness check above;
		// the simname format needs a space before the region name, not a slash.
		$simname = $destination->host . ":" . $destination->port;
		if (!empty($destination->regionName)) {
			$simname .= " " . $destination->regionName;
		}
		if (!empty($destination->pos)) {
			$simname .= "/" . implode("/", $destination->pos);
		}
		return $simname;
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
