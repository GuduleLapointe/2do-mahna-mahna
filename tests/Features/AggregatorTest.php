<?php
$appDir = dirname(__DIR__, 2);

fwrite(STDERR, "\nRunning aggregator (may take a moment)...\n");
$aggrTmp = sys_get_temp_dir() . '/2do-aggr-' . uniqid();
mkdir($aggrTmp, 0755, true);
exec("php $appDir/bin/aggregator.php $aggrTmp 2>&1", $aggrOut, $aggrCode);
register_shutdown_function(fn() => exec("rm -rf " . escapeshellarg($aggrTmp)));

describe("Aggregator", function () use ($aggrTmp, $aggrCode) {
	test("runs without error", function () use ($aggrCode) {
		expect($aggrCode)->toBe(0, "Aggregator failed — check logs");
		passed("Aggregator");
	});

	test("events.json exists and is valid JSON", function () use ($aggrTmp) {
		requires("Aggregator");
		expect("$aggrTmp/events.json")->toBeFile()->toBeReadableFile();
		$data = json_decode(file_get_contents("$aggrTmp/events.json"), true);
		expect(json_last_error())->toBe(JSON_ERROR_NONE);
		expect($data)->toBeArray();
	});

	test("events.lsl2 starts with version", function () use ($aggrTmp) {
		requires("Aggregator");
		expect("$aggrTmp/events.lsl2")->toBeFile()->toBeReadableFile();
		$first = trim(fgets(fopen("$aggrTmp/events.lsl2", "r")));
		expect($first)->toMatch('/^\d+\.\d+\.\d+$/');
	});

	test("events.ics is valid iCal", function () use ($aggrTmp) {
		requires("Aggregator");
		expect("$aggrTmp/events.ics")->toBeFile()->toBeReadableFile();
		$content = file_get_contents("$aggrTmp/events.ics");
		expect($content)->toStartWith("BEGIN:VCALENDAR")->toContain("END:VCALENDAR");
	});
});
