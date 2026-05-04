<?php
/**
 * Region class
 *
 * Represents an OpenSim region.
 *
 * The constructor only parses the URL (no network I/O). Callers that need
 * grid data or online status call data() / online() explicitly — both are
 * cached so repeated calls within a run are cheap.
 *
 * Positions are always [x, y, z] arrays (matching OpenSim's vector type).
 * Convert to the appropriate string at call sites:
 *   "x/y/z" in URLs  →  implode('/', $pos)
 *   "x,y,z" in DB    →  implode(',', $pos)
 *   "<x,y,z>" in LSL →  '<' . implode(',', $pos) . '>'
 *
 * Cache TTLs:
 *   opensim_get_region       — 24 h  (grid coordinates rarely change)
 *   opensim_region_is_online —  1 h  (online status can flip between runs)
 *
 * @property string  $regionname   Canonical region name (from grid data)
 * @property string  $regionUUID   Region UUID
 * @property string  $regionhandle Legacy 64-bit grid coordinate handle
 * @property string  $url          Normalized destination URL (gatekeeperURL:region[/pos]), set from constructor args
 * @property string  $owner        Owner display name
 * @property string  $owneruuid    Owner UUID
 * @property string  $gatekeeperURL  Gatekeeper base URL ("http://host:port")
 * @property string  $host         Grid server hostname
 * @property int     $port         Grid server port
 * @property string  $region       Region name (from URL; updated to canonical after data())
 * @property string  $uri          Canonical cache key "host:port/region" (lower-cased, no pos);
 *                                 empty string when URL is not parseable
 * @property float[] $pos          Local teleport position [x, y, z] from source URL;
 *                                 empty array when not specified
 * @property float[] $globalPos    Absolute map position [x, y, z] = grid origin + $pos;
 *                                 null until data() has been called
 * @property array   $data         Cached region data
 */
if (!TODO_APP) {
	die("No direct calls." . PHP_EOL);
}

class Region
{
	public string $regionname = "";
	public string $regionUUID = "";
	public string $regionhandle = "";
	public string $url = "";
	public string $owner = "";
	public string $owneruuid = "";
	public string $gatekeeperURL = "";
	public string $host = "";
	public int $port = 8002;
	public string $region = "";
	public string $uri = "";
	public array $pos = [];
	public ?array $globalPos = null;
	public ?array $data = null;
	public ?bool $online = null;

	/**
	 * Parse a region URL into structured components.
	 *
	 * Check `empty($region->key)` to detect unparseable URLs.
	 * Call data() to fetch grid data and populate schema properties.
	 * Call online() to check reachability.
	 *
	 * @param string      $url   Raw region URL (hop://, http://, "host:port Region", …)
	 * @param string|null $grid  Grid gatekeeper URL; used when $url has no host
	 */
	public function __construct(string $url, ?string $grid = null)
	{
		if (empty($url)) {
			$url = $grid ?? "";
		}
		if (empty($url)) {
			return;
		}

		$parsed = opensim_sanitize_uri($url, $grid, true);
		if (!$parsed || empty($parsed["host"])) {
			return;
		}

		$this->host = $parsed["host"];
		$this->port = (int) ($parsed["port"] ?? 8002);
		$this->region = $parsed["region"];
		$this->uri = $parsed["key"];
		$this->gatekeeperURL = $parsed["gatekeeper"];
		$this->pos = empty($parsed["pos"])
			? []
			: array_map("floatval", explode("/", $parsed["pos"]));

		$this->url = $this->gatekeeperURL . ":" . $this->region
			. (empty($this->pos) ? "" : "/" . implode("/", $this->pos));
	}

	/**
	 * Fetch region data from the grid and populate schema properties.
	 *
	 * Sets $globalPos = grid origin + $pos (falling back to DEFAULT_POS).
	 * Repeated calls are free after the first (memory cache).
	 *
	 * @return array  Raw opensim_get_region() result, or [] on failure / invalid URL
	 */
	public function data(): array
	{
		if (empty($this->uri)) {
			return [];
		}
		$this->data = Cache::get("region_data_{$this->uri}", $this->data);
		if ($this->data) {
			return $this->data;
		}

		$lookupURL = $this->gatekeeperURL . ":" . $this->region;
		$this->data = opensim_get_region($lookupURL) ?: [];

		$this->regionname   = $this->data["region_name"] ?? $this->region;
		$this->regionUUID   = $this->data["uuid"]         ?? "";
		$this->regionhandle = $this->data["regionhandle"] ?? "";
		$this->owner        = $this->data["owner"]        ?? "";
		$this->owneruuid    = $this->data["owneruuid"]    ?? "";

		if (!empty($this->regionname)) {
			$this->region = $this->regionname;
		}

		$local = empty($this->pos) ? DEFAULT_POS : $this->pos;
		$this->globalPos = [
			(float) ($this->data["x"] ?? 0) + $local[0],
			(float) ($this->data["y"] ?? 0) + $local[1],
			(float) ($local[2] ?? DEFAULT_POS[2]),
		];

		Cache::set("region_data_{$this->uri}", $this->data, 24 * 3600);
		return $this->data;
	}

	/**
	 * Return true if the region is currently reachable.
	 *
	 * Repeated calls are free after the first (memory cache).
	 *
	 * @return bool
	 */
	public function online(): bool
	{
		if (empty($this->uri)) {
			return false;
		}
		$online = Cache::get("opensim_region_is_online_{$this->uri}");
		if ($online !== null) {
			return (bool) $online;
		}

		$online = opensim_region_is_online([
			"host"       => $this->host,
			"port"       => $this->port,
			"region"     => $this->region,
			"pos"        => implode("/", $this->pos),
			"gatekeeper" => $this->gatekeeperURL,
			"key"        => $this->uri,
		]);

		Cache::set("opensim_region_is_online_{$this->uri}", $online, 3600);
		return (bool) $online;
	}

	/**
	 * Return the region as a formatted teleport URL.
	 *
	 * Call data() first so the canonical region name is used.
	 *
	 * Without a $pos override the position embedded in the source URL ($this->pos)
	 * is used when present; otherwise the link has no position component.
	 * To teleport to the region's default landing point, call landingPoint() (TODO).
	 *
	 * @param  float[]|null $pos     Position override [x, y, z]; null = use $this->pos
	 * @param  int          $format  TPLINK_* constant (default TPLINK_TXT)
	 * @return string
	 */
	public function teleportLink(?array $pos = null, int $format = TPLINK_TXT): string
	{
		if (empty($this->gatekeeperURL)) {
			return "";
		}

		$effectivePos = $pos ?? $this->pos;

		$uri = $this->gatekeeperURL . ":" . $this->region
			. (empty($effectivePos) ? "" : "/" . implode("/", $effectivePos));

		return opensim_format_tp($uri, $format) ?? "";
	}
}
