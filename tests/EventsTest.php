<?php

use PHPUnit\Framework\TestCase;
use Imagick, ImagickPixel, ImagickDraw;

class EventsTest extends TestCase
{
	private static bool $envSet = false;

	protected function setUp(): void
	{
		require_once __DIR__ . "/bootstrap.php";
		if (defined("TEST_URL")) {
			self::$envSet = true;
		}
	}

	public function testEnv(): void
	{
		$this->assertTrue(
			self::$envSet, // DEBUG, trigger false to test dependency // defined("TEST_URL"),
			"DEV_HOSTS and DEV_PORT must be properly set in tests/.env" .
				PHP_EOL,
		);

		if (
			!$this->assertTrue(
				extension_loaded("imagick"),
				"Imagick extension must be installed and loaded",
			)
		) {
			self::$envSet = false;
		}
	}

	public function testLsl2Format(): void
	{
		if (!self::$envSet) {
			$this->markTestSkipped("Environment not set");
		}

		$response = file_get_contents(TEST_URL . "/events.php");
		// Check if the response is not empty
		$this->assertNotEmpty($response, "Response should not be empty");

		// Line 1 is the version
		//
		// Check if the response starts with the version
		// $version_regex = defined("BOARD_VER") ? BOARD_VER : ".";
		$version_regex =
			"/" .
			(defined("BOARD_VER") ? BOARD_VER : "[0-9]+\.([0-9]+)+") .
			"/";
		$this->assertMatchesRegularExpression(
			$version_regex,
			$response,
			"Response should start with the version $version_regex",
		);
		// Check if the response contains the expected number of lines
		// count($lines) should be 1 + a multiple of 3
		$lines = explode("\n", trim($response));
		$count = count($lines);
		$this->assertTrue(
			$count % 3 === 1,
			"Response should have 1 + a multiple of 3 lines, got $count",
		);

		// Line 3 must contain start time, start date, end time, end date, and timestamp, separated by ~
		// E.g. 10:00AM~2026-04-22~1776877200~12:00PM~2026-04-22~1776884400
		if (isset($lines[2])) {
			$time = "[0-9]{2}:[0-9]{2}[AP]M";
			$date = "[0-9]{4}-[0-1][0-9]-[0-3][0-9]";
			$timestamp = "[1-9][0-9]+";
			$this->assertMatchesRegularExpression(
				"/^$time~$date~$timestamp~$time~$date~$timestamp$/",
				$lines[2],
				"Line 3 should contain formatted begin and end times, separated by ~",
			);
		}
	}

	public function testPngFormat(): void
	{
		if (!self::$envSet) {
			$this->markTestSkipped("Environment not set");
		}
		$response = file_get_contents(TEST_URL . "/events.php?format=png");
		// Check if the response is not empty
		$this->assertNotEmpty($response, "Image should not be empty");
		// Check if the file is a valid PNG using Imagick
		try {
			$imagick = new Imagick();
			$imagick->readImageBlob($response);
			$format = $imagick->getImageFormat();
			$this->assertEquals("PNG", $format, "File should be a valid PNG");
			$width = $imagick->getImageWidth();
			$height = $imagick->getImageHeight();
			$this->assertGreaterThan(
				0,
				$width,
				"PNG width should be greater than 0",
			);
			$this->assertGreaterThan(
				0,
				$height,
				"PNG height should be greater than 0",
			);
			// Use Imagick::identifyImage for detailed info
			$identifyInfo = $imagick->identifyImage(true);
			$this->assertNotEmpty(
				$identifyInfo,
				"Imagick should provide detailed image info",
			);
		} catch (Exception $e) {
			$this->fail("Imagick failed to read the file: " . $e->getMessage());
		}
	}
}
