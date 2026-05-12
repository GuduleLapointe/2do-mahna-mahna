<?php
use PHPUnit\Framework\TestCase;

uses()
	->beforeAll(function () {
		// Fires before instantiating the next test.
		// Do not use static::class here, as it would return the previous value
		// instead of the expected current one.
	})
	->beforeEach(function () {
		global $currentTestClass;
		$testClass = static::class; // phpcs:ignore PHPCompatibility.Classes.NewLateStaticBinding
		if ($currentTestClass != $testClass) {
			$currentTestClass = $testClass;
			testNotice(PHP_EOL . "Starting testClass $currentTestClass");
		}
		// testDetail(testName($this->name()));
	})
	->afterEach(function () {
		// testDetail(testName($this->name()) . " (end processing)");
	})
	->afterAll(function () {
		// Fires after the tests are executed, before the report output
	})
	->in(__DIR__);

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
				test()->markTestSkipped("Prerequisite not satisfied");
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
	$reg_protocol = "((https?|hop:|secondlife:)\/\/)?";
	$reg_host = "([\w-]+(\.[\w-]+)+)";
	$reg_port = "(:\d+)?";
	$reg_region = "([:\/ \+]([\w _\+-](%20)?)+)?";
	$reg_xyz = "((\/\d+){3})?";
	$pattern_uri = "/$reg_host$reg_port$reg_region$reg_xyz/";
	$pattern_url = "/$reg_protocol$reg_host$reg_port$reg_region$reg_xyz/";

	// $uri = "$uri/1.1/1.2/1.3";
	// $uri = "$uri/1";
	// $uri = "$uri/1/2";
	// $uri = "$uri/1/2/3";
	// $uri = "$uri/1/2/3/4";

	// Exclude uri with scheme
	expect($uri)->not->toMatch(
		"#://#",
		"destination uri should not include scheme",
	);

	// Strip anything after space (region and coordinates)
	$hostPort = preg_replace(["#.*://#", "#[+/_ ].*#"], ["", ""], $uri);
	if (empty($hostPort)) {
		echo "! empty destination uri in {$context}" . PHP_EOL;
		return;
	}

	// // Must pass standard URL validation
	expect("http://" . $hostPort)->toBeUrl();

	// Exclude any remaining invalid characters
	expect($hostPort)->toMatch(
		"/$reg_host$reg_port?$/i",
		"destination uri must match 'hostname:port'",
	);

	expect($uri)->toMatch($pattern_uri, "Invalid destination URI");
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

function testName(string $string)
{
	$string = preg_replace("/__(.*)__(.*)__→_/", '\\2 → ', $string);
	$string = preg_replace("/^__pest_evaluable_/", "", $string);
	return preg_replace("/_/", " ", $string);
}

function testNotice(string $string)
{
	if (empty($string)) {
		return;
	}
	fwrite(STDERR, $string . PHP_EOL);
}
function testDetail(string $string)
{
	if (empty($string)) {
		return;
	}
	testNotice("   $string");
}
