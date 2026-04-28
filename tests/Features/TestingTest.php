<?php
/**
 * This test suite is only intended to test the testing environment itself.
 * Do not include practical tests.
 */

describe("Testing Framework", function () {
	beforeEach(function () {
		requires("Testing URL");
	});

	test("first test", function () {
		expect(true)->toBeTrue();
	});
	test("second test", function () {
		expect(true)->toBeTrue();
	});
});
