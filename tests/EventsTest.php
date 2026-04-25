<?php

use PHPUnit\Framework\TestCase;
// use Imagick;

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

	public function testApi_v2(): void
	{
		if (!self::$envSet) {
			$this->markTestSkipped("Environment not set");
		}
		$response = file_get_contents(TEST_URL . "/events.php?api=v2");
		$this->assertNotEmpty($response, "Response should not be empty");

		$version_regex =
			"/" . (defined("BOARD_VER") ? BOARD_VER : "[0-9]+\.[0-9]+") . "/";
		$this->assertMatchesRegularExpression(
			$version_regex,
			$response,
			"Response should contain version $version_regex",
		);

		$lines = explode("\n", trim($response));
		$count = count($lines);
		$this->assertTrue(
			$count % 3 === 1,
			"Response should have 1 + a multiple of 3 lines, got $count",
		);

		// Line index 2 = timespec of first event
		if (isset($lines[2])) {
			$time = "[0-9]{2}:[0-9]{2}[AP]M";
			$date = "[0-9]{4}-[0-1][0-9]-[0-3][0-9]";
			$ts = "[1-9][0-9]+";
			$this->assertMatchesRegularExpression(
				"/^$time~$date~$ts~$time~$date~$ts$/",
				$lines[2],
				"Line 3 should be a timespec",
			);
		}
	}

	public function testApi_v3(): void
	{
		if (!self::$envSet) {
			$this->markTestSkipped("Environment not set");
		}
		$response = file_get_contents(TEST_URL . "/events.php?api=v3");
		$this->assertNotEmpty($response, "Response should not be empty");

		$lines = array_filter(
			explode("\n", trim($response)),
			fn($l) => $l !== "",
		);
		$this->assertNotEmpty(
			$lines,
			"Response should have at least one event line",
		);

		$timeRx = "[0-9]{2}:[0-9]{2}[AP]M";
		$dateRx = "[0-9]{4}-[0-1][0-9]-[0-3][0-9]";
		$tsRx = "[1-9][0-9]+";
		$timespecRx = "/^$timeRx~$dateRx~$tsRx~$timeRx~$dateRx~$tsRx$/";

		foreach ($lines as $i => $line) {
			$parts = str_getcsv($line, ",", '"', "\\");
			$this->assertGreaterThanOrEqual(
				3,
				count($parts),
				"Line $i should have at least 3 CSV fields: $line",
			);
			$this->assertMatchesRegularExpression(
				$timespecRx,
				$parts[1],
				"Line $i field 1 should be a timespec: {$parts[1]}",
			);
		}
	}

	public function testApiDefault(): void
	{
		if (!self::$envSet) {
			$this->markTestSkipped("Environment not set");
		}
		$default = file_get_contents(TEST_URL . "/events.php");
		$v3 = file_get_contents(TEST_URL . "/events.php?api=v3");
		$this->assertEquals(
			$v3,
			$default,
			"Default response should match api=v3",
		);
	}

	public function testPngFormat(): void
	{
		if (!self::$envSet) {
			$this->markTestSkipped("Environment not set");
		}
		$response = file_get_contents(TEST_URL . "/events.php?format=png");
		// Check if the response is not empty
		$this->assertNotEmpty($response, "Image should not be empty");

		// Check Content-Type header
		$contentType = "";
		foreach ($http_response_header ?? [] as $header) {
			if (stripos($header, "Content-Type:") === 0) {
				$contentType = $header;
				break;
			}
		}
		$this->assertStringContainsString(
			"image/png",
			$contentType,
			"Content-Type header should specify image/png",
		);
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
