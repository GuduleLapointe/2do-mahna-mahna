<?php
$appDir = dirname(__DIR__, 2);

describe("Aggregator", function () use ($appDir) {
	beforeAll(function () use ($appDir) {
		$tmp = sys_get_temp_dir() . '/2do-aggr-' . uniqid();
		mkdir($tmp, 0755, true);
		$GLOBALS['aggr_tmp']  = $tmp;
		exec("php $appDir/bin/aggregator.php $tmp 2>&1", $out, $code);
		$GLOBALS['aggr_exit'] = $code;
		$GLOBALS['aggr_out']  = implode("\n", $out);
	});

	afterAll(function () {
		if (!empty($GLOBALS['aggr_tmp'])) {
			exec("rm -rf " . escapeshellarg($GLOBALS['aggr_tmp']));
		}
	});

	test("aggregator completes without error", function () {
		expect($GLOBALS['aggr_exit'])->toBe(0, $GLOBALS['aggr_out']);
		passed("Aggregator");
	});

	test("events.json exists", function () {
		requires("Aggregator");
		expect(file_exists($GLOBALS['aggr_tmp'] . "/events.json"))->toBeTrue();
	});

	test("events.json is valid JSON array", function () {
		requires("Aggregator");
		$data = json_decode(file_get_contents($GLOBALS['aggr_tmp'] . "/events.json"), true);
		expect(json_last_error())->toBe(JSON_ERROR_NONE, "events.json must be valid JSON");
		expect($data)->toBeArray();
		passed("events.json");
	});

	test("events.json has events", function () {
		requires("events.json");
		$data = json_decode(file_get_contents($GLOBALS['aggr_tmp'] . "/events.json"), true);
		if (empty($data)) {
			test()->markTestSkipped("Empty events list");
		}
		expect(count($data))->toBeGreaterThan(0);
	});

	test("events.lsl2 exists", function () {
		requires("Aggregator");
		expect(file_exists($GLOBALS['aggr_tmp'] . "/events.lsl2"))->toBeTrue();
	});

	test("events.lsl2 starts with version", function () {
		requires("Aggregator");
		$first = trim(fgets(fopen($GLOBALS['aggr_tmp'] . "/events.lsl2", "r")));
		expect($first)->toMatch('/^\d+\.\d+\.\d+$/');
	});

	test("events.ics exists", function () {
		requires("Aggregator");
		expect(file_exists($GLOBALS['aggr_tmp'] . "/events.ics"))->toBeTrue();
	});

	test("events.ics is valid iCal", function () {
		requires("Aggregator");
		$content = file_get_contents($GLOBALS['aggr_tmp'] . "/events.ics");
		expect($content)->toStartWith("BEGIN:VCALENDAR");
		expect($content)->toContain("END:VCALENDAR");
	});
});
