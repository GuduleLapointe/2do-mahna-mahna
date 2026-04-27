<?php

uses()->in(__DIR__);

class TestRegistry
{
	private static array $passed = [];

	public static function pass(string $name): void
	{
		self::$passed[$name] = true;
	}

	public static function require(string ...$names): void
	{
		foreach ($names as $name) {
			if (empty(self::$passed[$name])) {
				test()->skip("Requires '{$name}' to pass");
			}
		}
	}
}

function passed(string $name): void
{
	TestRegistry::pass($name);
}
function requires(string ...$names): void
{
	TestRegistry::require(...$names);
}

function httpStatus(string $url): int
{
	$context = stream_context_create([
		"http" => ["follow_location" => 0, "ignore_errors" => true],
	]);
	@file_get_contents($url, false, $context);
	if (
		isset($http_response_header[0]) &&
		preg_match("#HTTP/[\d.]+\s+(\d+)#", $http_response_header[0], $m)
	) {
		return (int) $m[1];
	}
	return 0;
}

/**
 * Validate OpenSimulator HG URI
 *
 * Format: host(:port)?( region name)?(/x/y/z)?
 */
function expectValidHyperGridUri(string $uri, string $context = "")
{
	// $uri = "$uri/1.1/1.2/1.3";
	// $uri = "$uri/1";
	// $uri = "$uri/1/2";
	// $uri = "$uri/1/2/3";
	// $uri = "$uri/1/2/3/4";

	// Strip anything after space (region and coordinates)
	$hostPort = preg_replace("# .*#", "", $uri);
	if (empty($hostPort)) {
		echo "! empty destination uri in {$context}" . PHP_EOL;
		return;
	}

	// // Must pass standard URL validation
	expect("http://" . $hostPort)->toBeUrl();

	// Exclude uri with scheme
	expect($hostPort)->not->toMatch(
		"#://#",
		"destination uri should not include scheme",
	);

	// Exclude any remaining invalid characters
	expect($hostPort)->toMatch(
		"/^[a-z0-9\.:_-]+$/i",
		"destination uri must match 'hostname:port'",
	);

	$num = "-?[0-9]+(?:\.[0-9]+)?";
	expect($uri)->toMatch(
		"#^([^/ ]+)(?: ([^/]+?))?(/$num/$num/$num)?$#",
		"Invalid destination URI",
	);
}

function expectValidHttpStatus(string $url)
{
	$status = httpStatus($url);
	if (in_array($status, [301, 308], true)) {
		echo "! Permanent redirect ($status) on $url — should be temporary or direct" .
			PHP_EOL;
	}
	return expect($status)->toBeIn([200, 301, 302, 307, 308]);
}
