<?php
/**
 * Event class
 *
 * Represents an event of the calendar, matches OpenSim events table format,
 * must keep strict compatibility with viewer query protocol.
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
 *
 * Additional properties for internal purpose
 * @property string $defaultDestination // From calendar[grid_url]
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

	private string $defaultDestination;

	/**
	 * Event constructor.
	 *
	 * @param array $args
	 */
	public function __construct($args, $calendar = [])
	{
		// Make sure all required keys are present
		$args = array_merge(EVENT_STRUCTURE, $args);

		if (Fetcher::isExcluded($calendar["slug"], $args["name"])) {
			Console::verbose(
				"[{$calendar["slug"]}] {$args["name"]} is in exclusion list",
			);
			return false;
		}

		// TODO: generate uid if not present (for other sources than iCal)
		$this->uid = $args["uid"];
		$this->name = $args["name"];
		$this->description = $args["description"];
		$this->dateUTC = $args["dateUTC"];
		$this->duration = $args["duration"];
		$this->ownerUUID = $args["owneruuid"];
		$this->creatorUUID = $args["creatoruuid"];
		$this->category = $this->sanitize_category($args["category"]); // OpenSim/SL category code
		$this->tags = $tags; // Array of category names
		$this->coverCharge = $args["covercharge"];
		$this->coverAmount = $args["coveramount"];
		$this->parcelUUID = $args["parcelUUID"];
		$this->globalPos = $args["globalPos"]; // Region map coordinates, NOT destination Pos
		$this->flags = $args["eventflags"];
		// eventlist.py:        new_hash = hashlib.md5( str(event_start) + hgurl ).hexdigest()
		$this->source = $calendar["slug"];

		$this->setDestination($args["simname"], $calendar["grid_url"]);

		// Hash ensures teh
		$this->hash = md5($this->dateUTC . $this->simName);

		$tags = $args["tags"];
		if (empty($tags)) {
			$tags = [];
		} elseif (!is_array($tags)) {
			$tags = [$tags];
		}
		// $tags = array_unique( array_merge ( $tags, array( $calendar['slug'] ) ) );
		$tags = array_filter($tags);
		$tags = array_unique($tags);

		// Sanitize $args['description'], replace "\n" with actual new lines, trim trailing spaces and new lines
		$args["description"] = trim(
			str_replace("\\n", PHP_EOL, $args["description"]),
		);
	}

	/**
	 * Resolve a raw region URL to a canonical simname string.
	 *
	 * - Filter out destination if region is offline
	 * - Extract destination url from description if not provided
	 * - Sets event destination properties:
	 *   $simName: canonical destination uri (host:port/Region)
	 *   $gatekeeperURL: canonical Region gatekeeper URL (http://host:port)
	 *   $teleport: array of most common tp link formats
	 *
	 * Strictly use Region uri and dest_uri properties, Region is the only
	 * source of truth for uris, never rewrite the construction logic.
	 *
	 * Reads initial data from properties set by the constructor, arguments
	 * are optional, and might even be deprecated.
	 *
	 * @param  string|null $url       		Raw region URL from the event source
	 * @param  string|null $gatekeeperURL	Grid gatekeeper URL (fallback when $url has no host)
	 * @return string|null|false           	Canonical simname, or false or null depending on failure
	 */
	public function setDestination($location = null, $defaultLocation = null)
	{
		$url = $location ?: $this->simName;

		$reg_protocol = "((https?|hop:|secondlife:)\/\/)?";
		$reg_host = "([\w-]+(\.[\w-]+)+)";
		$reg_port = "(:\d+)";
		$reg_region = "([:\/ \+]([\w _\+-](%20)?)+)?";
		$reg_xyz = "((\/\d+){3})?";
		$pattern = "/$reg_protocol$reg_host$reg_port$reg_region$reg_xyz/";

		if (empty($url)) {
			$description = preg_replace("/%20/", " ", $this->description);
			preg_match($pattern, $description, $matches);
			if (!empty($matches)) {
				$url = $matches[0];
			}
		}

		// Now that we have looked everywhere, we can fallback to calendar grid url or region gatekeeperURL
		$url = $url ?: $defaultLocation;

		if (!empty($url) && preg_match($pattern, $url)) {
			$destination = new Region($url, $this->gatekeeperURL ?? null);

			// $destination->data(); // Region constructor already sanitizes uri  and dest_uri
			if ($destination->online()) {
				$this->simName = $destination->dest_uri;
				$this->gatekeeperURL = $destination->gatekeeperURL;
				$this->teleport = [
					"HOP" => opensim_format_tp($this->simName, TPLINK_HOP),
					"HG" => opensim_format_tp($this->simName, TPLINK_HG),
					"V3HG" => opensim_format_tp($this->simName, TPLINK_V3HG),
				];
			} else {
				$this->simName = false;
				Console::verbose(
					sprintf(
						"%s event %s region offline %s",
						$this->slug,
						$this->uid,
						$url,
					),
				);
			}
		} elseif (!empty($url)) {
			$this->simName = false;
			Console::verbose(
				sprintf(
					"%s event %s incomplete or invalid location (%s, %s)",
					$this->slug,
					$this->uid,
					$location,
					$defaultLocation,
				),
			);
		} else {
			$this->simName = false;
		}

		if ($this->simName === false) {
			Console::verbose(
				sprintf(
					"%s event %s error parsing location from event",
					$this->slug,
					$this->uid,
				),
			);
		} elseif (empty($this->simName)) {
			Console::verbose(
				sprintf("%s event %s has no location", $this->slug, $this->uid),
			);
		}

		return $this->simName;
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
