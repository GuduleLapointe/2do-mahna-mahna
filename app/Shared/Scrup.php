<?php

/**
 * Scrup integration helpers.
 *
 * Provides server-side queries to the scrup auto-update service.
 * The scrup server lives at lib/scrup/ (git submodule).
 */

if (!defined("SCRUP_URL")) {
	define(
		"SCRUP_URL",
		getenv("SCRUP_URL") ?: "https://speculoos.world/scrup/scrup.php",
	);
}

/**
 * Fetch the latest registered LSL board version from the scrup server.
 * Caches the result for $ttl seconds. Falls back to $fallback on failure.
 *
 * @param string $fallback  Version string to use if scrup is unreachable
 * @param string $cacheFile Path to cache file
 * @param int    $ttl       Cache TTL in seconds (default: 24h)
 * @return string
 */
function fetch_lsl_board_version(
	string $fallback,
	string $cacheFile,
	int $ttl = 86400,
): string {
	if (file_exists($cacheFile) && time() - filemtime($cacheFile) < $ttl) {
		$cached = trim(file_get_contents($cacheFile));
		if (preg_match("/^\d+\.\d+/", $cached)) {
			return $cached;
		}
	}
	$url = SCRUP_URL . "?action=get-version&name=2DO+board";
	$ctx = stream_context_create(["http" => ["timeout" => 3]]);
	$version = @file_get_contents($url, false, $ctx);
	if ($version && preg_match("/^\d+\.\d+/", trim($version))) {
		$version = trim($version);
		@file_put_contents($cacheFile, $version);
		return $version;
	}
	return $fallback;
}
