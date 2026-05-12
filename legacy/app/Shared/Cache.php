<?php
/**
 * Unified two-level cache.
 *
 * Backed by:
 *   - in-process memory (request-scoped, always available)
 *   - SearchDB oshelpers_cache table (cross-request, TTL-based)
 *
 * The TTL parameter is the discriminator:
 *   Cache::set($key, $value)        — memory only (no persistence needed)
 *   Cache::set($key, $value, $ttl)  — memory + DB (survives across requests)
 *   Cache::get($key)                — memory first, DB fallback; warms memory on DB hit
 *   Cache::forget($key)             — removes from memory; expires DB entry if possible
 *
 * DB functions (osdb_cache_get / osdb_cache_set) are defined by opensim-helpers
 * search.php. They are no-ops if SearchDB is not connected — so the cache works
 * in memory-only mode when SearchDB is unavailable.
 */
class Cache
{
	private static array $memory = [];

	/**
	 * Sanitize a cache key for safe use as a DB column value.
	 *
	 * Replaces any character that is not alphanumeric, dash, dot, or underscore
	 * with an underscore, then truncates to 200 chars (well within VARCHAR 255).
	 * This keeps keys human-readable in the oshelpers_cache table while avoiding
	 * spaces or special chars from region URLs or source slugs.
	 */
	private static function sanitizeKey(string $key): string
	{
		return substr(preg_replace('/[^a-zA-Z0-9\-._]/', '_', $key), 0, 200);
	}

	public static function get(string $key, mixed $default = null): mixed
	{
		$key = self::sanitizeKey($key);
		if (array_key_exists($key, self::$memory)) {
			return self::$memory[$key];
		}
		if (function_exists("osdb_cache_get")) {
			$value = osdb_cache_get($key);
			if ($value !== null) {
				self::$memory[$key] = $value; // warm memory for this request
				return $value;
			}
		}
		return $default;
	}

	public static function set(string $key, mixed $value, int|false $ttl = false): void
	{
		$key = self::sanitizeKey($key);
		self::$memory[$key] = $value;
		if ($ttl !== false && function_exists("osdb_cache_set")) {
			osdb_cache_set($key, $value, $ttl);
		}
	}

	public static function forget(string $key): void
	{
		$key = self::sanitizeKey($key);
		unset(self::$memory[$key]);
		// Expire the DB entry by setting it with TTL of 1 second (already past on next request)
		if (function_exists("osdb_cache_set")) {
			osdb_cache_set($key, null, 1);
		}
	}
}
