<?php
$appDir = dirname(__DIR__, 2);

$aggrTmp = sys_get_temp_dir() . '/2do-aggr-' . uniqid();
mkdir($aggrTmp, 0755, true);
exec("php $appDir/bin/aggregator.php $aggrTmp 2>&1", $aggrOut, $aggrCode);
$aggrOut = implode("\n", $aggrOut);
register_shutdown_function(fn() => exec("rm -rf " . escapeshellarg($aggrTmp)));

describe("Aggregator", function () use ($aggrTmp, $aggrCode, $aggrOut) {
	test("aggregator completes without error", function () use ($aggrCode, $aggrOut) {
		expect($aggrCode)->toBe(0, $aggrOut);
		passed("Aggregator");
	});

	test("events.json exists", function () use ($aggrTmp) {
		requires("Aggregator");
		expect(file_exists("$aggrTmp/events.json"))->toBeTrue();
	});

	test("events.json is valid JSON array", function () use ($aggrTmp) {
		requires("Aggregator");
		$data = json_decode(file_get_contents("$aggrTmp/events.json"), true);
		expect(json_last_error())->toBe(JSON_ERROR_NONE, "events.json must be valid JSON");
		expect($data)->toBeArray();
		passed("events.json");
	});

	test("events.json has events", function () use ($aggrTmp) {
		requires("events.json");
		$data = json_decode(file_get_contents("$aggrTmp/events.json"), true);
		if (empty($data)) {
			test()->markTestSkipped("Empty events list");
		}
		expect(count($data))->toBeGreaterThan(0);
	});

	test("events.lsl2 exists", function () use ($aggrTmp) {
		requires("Aggregator");
		expect(file_exists("$aggrTmp/events.lsl2"))->toBeTrue();
	});

	test("events.lsl2 starts with version", function () use ($aggrTmp) {
		requires("Aggregator");
		$first = trim(fgets(fopen("$aggrTmp/events.lsl2", "r")));
		expect($first)->toMatch('/^\d+\.\d+\.\d+$/');
	});

	test("events.ics exists", function () use ($aggrTmp) {
		requires("Aggregator");
		expect(file_exists("$aggrTmp/events.ics"))->toBeTrue();
	});

	test("events.ics is valid iCal", function () use ($aggrTmp) {
		requires("Aggregator");
		$content = file_get_contents("$aggrTmp/events.ics");
		expect($content)->toStartWith("BEGIN:VCALENDAR");
		expect($content)->toContain("END:VCALENDAR");
	});
});
