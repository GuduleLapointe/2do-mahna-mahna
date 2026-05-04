<?php
/**
 * Region — OpenSim region lookup with persistent caching.
 *
 * Wraps the three opensim-functions.php calls that involve network I/O
 * (opensim_sanitize_uri, opensim_get_region, opensim_region_is_online) and
 * caches their results so that repeated lookups within a run and across
 * cron invocations avoid redundant HTTP calls to grid services.
 *
 * Cache TTLs:
 *   opensim_get_region      — 24 h  (grid coordinates and region names rarely change)
 *   opensim_region_is_online —  1 h  (online status can change between runs)
 *
 * Cache keys use the canonical "$host:$port/$region" key returned by
 * opensim_sanitize_uri, which is lower-cased and stripped of position
 * coordinates — ensuring hits even when the same region is referenced
 * with different local positions.
 */
if (!IS_AGGR) {
	die("No direct calls, run main script aggregator.php instead." . PHP_EOL);
}

class Region
{
	/** Parsed URL components from opensim_sanitize_uri(..., true). */
	private array $parsed;

	/** Data from opensim_get_region(): x, y, region_name, uuid, ... */
	private array $regionData;

	/** Whether the region is currently reachable. */
	private bool $online;

	// ------------------------------------------------------------------
	// Factory
	// ------------------------------------------------------------------

	/**
	 * Return a Region for the given URL, or false if the URL is unparseable.
	 *
	 * Parsing is cheap (no network). Region data and online status are loaded
	 * from cache or fetched lazily on first access for this $url + $grid pair.
	 *
	 * @param  string      $url   Raw region URL from the event source
	 * @param  string|null $grid  Grid gatekeeper URL (fallback when $url has no host)
	 * @return static|false
	 */
	public static function get(string $url, ?string $grid = null): static|false
	{
		if (empty($url)) {
			$url = $grid;
		}
		if (empty($url)) {
			return false;
		}

		$parsed = opensim_sanitize_uri($url, $grid, true);
		if (!$parsed || empty($parsed['host'])) {
			return false;
		}

		return new static($parsed);
	}

	// ------------------------------------------------------------------
	// Constructor (private — use Region::get())
	// ------------------------------------------------------------------

	private function __construct(array $parsed)
	{
		$this->parsed = $parsed;

		// Canonical cache key: lower-cased "host:port/region name", no position.
		// Cache::sanitizeKey() will replace spaces → underscores for DB safety.
		$key = $parsed['key'];

		// --- Region data: grid X/Y, canonical region name, UUID --- 24 h TTL
		$regionData = Cache::get("opensim_get_region_$key");
		if ($regionData === null) {
			// opensim_get_region expects "host:port Region" (no position)
			$lookupURL  = $parsed['gatekeeper'] . ':' . $parsed['region'];
			$regionData = opensim_get_region($lookupURL) ?: [];
			Cache::set("opensim_get_region_$key", $regionData, 24 * 3600);
		}
		$this->regionData = $regionData;

		// Use the canonical region name from grid data when the source URL omitted it
		if (empty($this->parsed['region']) && !empty($regionData['region_name'])) {
			$this->parsed['region'] = $regionData['region_name'];
		}

		// --- Online status --- 1 h TTL
		$online = Cache::get("opensim_region_is_online_$key");
		if ($online === null) {
			$online = opensim_region_is_online($this->parsed);
			Cache::set("opensim_region_is_online_$key", $online, 3600);
		}
		$this->online = (bool) $online;
	}

	// ------------------------------------------------------------------
	// Public API
	// ------------------------------------------------------------------

	/**
	 * Whether the region is currently reachable.
	 */
	public function online(): bool
	{
		return $this->online;
	}

	/**
	 * Raw data returned by opensim_get_region().
	 *
	 * Useful keys: x, y (absolute grid origin), region_name, uuid.
	 */
	public function data(): array
	{
		return $this->regionData;
	}

	/**
	 * Absolute grid position for storage in the globalPos database column.
	 *
	 * Adds the region's grid origin (x, y from region_data) to a local
	 * teleport position within the region. The local position defaults to
	 * the coordinates embedded in the source URL, or DEFAULT_POS if none.
	 *
	 * Note: this is distinct from the teleport position in the URL (local
	 * coords, e.g. 128/64/25). globalPos is the absolute map position
	 * (e.g. 1288832,1289088,25) required by the OpenSim search protocol.
	 *
	 * @param  int[]|null $localPos  [x, y, z] override; null = use URL pos or DEFAULT_POS
	 * @return string                "X,Y,Z" absolute grid coordinates
	 */
	public function globalPos(?array $localPos = null): string
	{
		if ($localPos === null) {
			$localPos = empty($this->parsed['pos'])
				? DEFAULT_POS
				: array_map('intval', explode('/', $this->parsed['pos']));
		}

		$x = $localPos[0] + (int) ($this->regionData['x'] ?? 0);
		$y = $localPos[1] + (int) ($this->regionData['y'] ?? 0);
		$z = $localPos[2] ?? DEFAULT_POS[2];

		return "$x,$y,$z";
	}

	/**
	 * Formatted teleport URL.
	 *
	 * Position from the source URL is preserved when present. The canonical
	 * region name from region_data is used when the source URL omitted one.
	 *
	 * @param  int    $format  TPLINK_* constant (default TPLINK_TXT)
	 * @return string
	 */
	public function hgURL(int $format = TPLINK_TXT): string
	{
		$uri = $this->parsed['gatekeeper'] . ':' . $this->parsed['region']
			. (empty($this->parsed['pos']) ? '' : '/' . $this->parsed['pos']);

		return opensim_format_tp($uri, $format) ?? '';
	}
}
