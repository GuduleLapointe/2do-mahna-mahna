<?php
/**
 * Pest tests for DatabaseSettings.
 */

define("tests", true);

use App\Settings\DatabaseSettings;
use Illuminate\Support\Facades\Config;

describe("DatabaseSettings", function () {
    test("defaults are correct", function () {
        $settings = DatabaseSettings::make();

        expect($settings->search)
            ->toBeArray()
            ->and($settings->events)
            ->toBeArray()
            ->and($settings->robust)
            ->toBeArray()
            ->and($settings->opensim)
            ->toBeArray()
            ->and($settings->offline)
            ->toBeArray()
            ->and($settings->currency)
            ->toBeArray();

        foreach (
            ["search", "events", "robust", "opensim", "offline", "currency"]
            as $key
        ) {
            expect($settings->{$key})->toHaveKey("type");
        }
    });

    test("can set and get values", function () {
        $settings = DatabaseSettings::make();
        $settings->search = ["type" => "mysql", "hostname" => "localhost"];
        $settings->save();

        $loaded = DatabaseSettings::find("database.search");
        expect($loaded["type"])->toBe("mysql");
        expect($loaded["hostname"])->toBe("localhost");
    });
});
