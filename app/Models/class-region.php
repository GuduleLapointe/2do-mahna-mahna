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
 * OpenSimSearch module SQL schema
 * @property string  $regionname   Canonical region name (from grid data)
 * @property string  $regionUUID   Region UUID
 * @property string  $regionhandle Legacy 64-bit grid coordinate handle
 * @property string  $url          Normalized destination URL (gatekeeperURL:region[/pos]), set from constructor args
 * @property string  $owner        Owner display name
 * @property string  $owneruuid    Owner UUID
 *
 * opensim-helpers extended SQL schema?
 * @property string  $gatekeeperURL  Gatekeeper base URL ("http://host:port")
 *
 * From get_region and region_link XMLRPC responses
 * @property string  $host         Grid server hostname
 * @property int     $port         Grid server port
 * @property string  $region       Region name (from URL; updated to canonical after data())
 * @property string  $uri          Canonical cache key "host:port/region" (lower-cased, no pos);
 *                                 empty string when URL is not parseable
 * @property float[] $globalPos    Absolute map position [x, y, z] = grid origin + $pos;
 *                                 null until data() has been called
 *
 * From class instantiation
 * @property float[] $pos          Local teleport position [x, y, z] from source URL;
 *                                 empty array when not specified
 * @property string  $link_region_data   data returned by OpenSim link_region call
 * @property string  $get_region_data   data returned by OpenSim get_region call
 * @property array   $data         Cached region data
 * @property string  $imageURL     Region image URL (from get_region response)
 *
 * Keep for reference: actual OpenSimulator internal regions table SQL schema
 * (OpenSimSearch module differs for historical reasons)
 *
 * Field	Type	Null	Key	Default	Extra
 * uuid	varchar(36)	NO	PRI	NULL
 * regionHandle	bigint(20) unsigned	NO	MUL	NULL
 * regionName	varchar(128)	YES	MUL	NULL
 * regionRecvKey	varchar(128)	YES		NULL
 * regionSendKey	varchar(128)	YES		NULL
 * regionSecret	varchar(128)	YES		NULL
 * regionDataURI	varchar(255)	YES		NULL
 * serverIP	varchar(64)	YES		NULL
 * serverPort	int(10) unsigned	YES		NULL
 * serverURI	varchar(255)	YES		NULL
 * locX	int(10) unsigned	YES		NULL
 * locY	int(10) unsigned	YES		NULL
 * locZ	int(10) unsigned	YES		NULL
 * eastOverrideHandle	bigint(20) unsigned	YES	MUL	NULL
 * westOverrideHandle	bigint(20) unsigned	YES		NULL
 * southOverrideHandle	bigint(20) unsigned	YES		NULL
 * northOverrideHandle	bigint(20) unsigned	YES		NULL
 * regionAssetURI	varchar(255)	YES		NULL
 * regionAssetRecvKey	varchar(128)	YES		NULL
 * regionAssetSendKey	varchar(128)	YES		NULL
 * regionUserURI	varchar(255)	YES		NULL
 * regionUserRecvKey	varchar(128)	YES		NULL
 * regionUserSendKey	varchar(128)	YES		NULL
 * regionMapTexture	varchar(36)	YES		NULL
 * serverHttpPort	int(10)	YES		NULL
 * serverRemotingPort	int(10)	YES		NULL
 * owner_uuid	varchar(36)	NO		00000000-0000-0000-0000-000000000000
 * originUUID	varchar(36)	YES		NULL
 * access	int(10) unsigned	YES		1
 * ScopeID	char(36)	NO	MUL	00000000-0000-0000-0000-000000000000
 * sizeX	int(11)	NO		0
 * sizeY	int(11)	NO		0
 * flags	int(11)	NO	MUL	0
 * last_seen	int(11)	NO		0
 * PrincipalID	char(36)	NO		00000000-0000-0000-0000-000000000000
 * Token	varchar(255)	NO		NULL
 * parcelMapTexture	varchar(36)	YES		NULL
 *
 * Keep for reference: OpenSim get_info output XML
 *
 * link_region: Array (
 *    [size_y] => 256
 *    [external_name] => http://yourgrid.org:8002/ Welcome
 *    [uuid] => 60d0e3f3-4802-49ad-90d5-ed7b601cc6d9
 *    [region_image] => http://192.168.0.9:9045/index.php?method=regionImage60d0e3f3480249ad90d5ed7b601cc6d9
 *    [handle] => 8796093024256256
 *    [result] => True
 *    [size_x] => 256
 * )
 *
 * get_region: Array (
 *    [server_uri] => http://192.168.0.9:9045/
 *    [region_name] => Welcome
 *    [http_port] => 9045
 *    [internal_port] => 9046
 *    [hostname] => 192.168.0.9
 *    [uuid] => 60d0e3f3-4802-49ad-90d5-ed7b601cc6d9
 *    [x] => 2048000
 *    [y] => 2048256
 *    [size_y] => 256
 *    [result] => true
 *    [size_x] => 256
 * )
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
	public ?string $imageURL = null;
	public ?array $link_region_data = null;
	public ?array $get_region_data = null;

	/**
	 * Parse a region URL into structured components.
	 *
	 * Check `empty($region->uri)` to detect unparseable URLs.
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

		$this->url =
			$this->uri .
			(empty($this->pos) ? "" : "/" . implode("/", $this->pos));
	}

	public function link_region(): array|false
	{
		if (empty($this->uri)) {
			return false;
		}
		$this->link_region_data = Cache::get(
			"link_region_{$this->uri}",
			$this->link_region_data,
		);
		if ($this->link_region_data !== null) {
			return $this->link_region_data;
		}

		$this->link_region_data = oxXmlRequest(
			$this->gatekeeperURL,
			"link_region",
			[
				"region_name" => "$this->regionname",
			],
		);

		Cache::set("link_region_{$this->uri}", $this->link_region_data);
		return $this->link_region_data;
	}

	public function get_region(): array|false
	{
		if (empty($this->uri)) {
			return false;
		}
		$this->get_region_data = Cache::get(
			"get_region_{$this->uri}",
			$this->get_region_data,
		);
		if ($this->get_region_data !== null) {
			return $this->get_region_data;
		}

		$region_uuid = $this->link_region()["uuid"] ?? null;
		if (empty($region_uuid)) {
			$this->get_region_data = false;
		} else {
			$this->get_region_data = oxXmlRequest(
				$this->gatekeeperURL,
				"get_region",
				[
					"region_uuid" => $region_uuid,
				],
			);
		}
		Cache::set("get_region_{$this->uri}", $this->get_region_data);
		return $this->get_region_data;
	}

	/**
	 * Fetch region data from the grid and populate schema properties.
	 *
	 * Sets $globalPos = grid origin + $pos (falling back to DEFAULT_POS).
	 * Repeated calls are free after the first (memory cache).
	 *
	 * @return array|false  Raw opensim_get_region() result, or false on failure / invalid URL
	 */
	public function data(): array|false
	{
		if (empty($this->uri)) {
			return false;
		}
		$this->data = Cache::get("region_data_{$this->uri}", $this->data);
		if ($this->data) {
			return $this->data;
		}

		$lookupURL = $this->gatekeeperURL . ":" . $this->region;
		$this->data = opensim_get_region($lookupURL) ?: [];

		if (!empty($this->data)) {
			// Store link_region and get_region for easy access to original opensim api data
			$this->link_region_data = $this->data["link_region"] ?? [];
			$this->get_region_data = $this->data["get_region"] ?? [];
			unset($this->get_region_data["link_region"]);

			$this->regionname = $this->data["region_name"] ?? $this->region;
			$this->regionUUID = $this->data["uuid"] ?? "";
			$this->regionhandle = $this->data["regionhandle"] ?? "";
			$this->owner = $this->data["owner"] ?? "";
			$this->owneruuid = $this->data["owneruuid"] ?? "";
			$this->imageURL = $this->data["region_image"] ?? null;

			if (!empty($this->regionname)) {
				$this->region = $this->regionname;
			}

			$local = empty($this->pos) ? DEFAULT_POS : $this->pos;
			$this->globalPos = [
				(float) ($this->data["x"] ?? 0) + $local[0],
				(float) ($this->data["y"] ?? 0) + $local[1],
				(float) ($local[2] ?? DEFAULT_POS[2]),
			];
		}

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
			"host" => $this->host,
			"port" => $this->port,
			"region" => $this->region,
			"pos" => implode("/", $this->pos),
			"gatekeeper" => $this->gatekeeperURL,
			"key" => $this->uri,
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
	public function teleportLink(
		?array $pos = null,
		int $format = TPLINK_TXT,
	): string {
		if (empty($this->gatekeeperURL)) {
			return "";
		}

		if (empty($pos)) {
			return opensim_format_tp($this->url, $format) ?? "";
		}
		$uri = $this->uri . "/" . implode("/", $pos);
		return opensim_format_tp($uri, $format) ?? "";
	}
}
