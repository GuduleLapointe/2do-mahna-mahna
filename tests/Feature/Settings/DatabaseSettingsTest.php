<?php

/**
 * Pest tests for DatabaseSettings.
 */

use App\Settings\DatabaseSettings;

describe("DatabaseSettings", function () {
    test("defaults are correct", function () {
        $settings = app(DatabaseSettings::class);

        // Verify all database connection arrays exist
        expect($settings->search)->toBeArray();
        expect($settings->events)->toBeArray();
        expect($settings->robust)->toBeArray();
        expect($settings->opensim)->toBeArray();
        expect($settings->offline)->toBeArray();
        expect($settings->currency)->toBeArray();

        // Verify each has a 'type' key
        foreach (
            ["search", "events", "robust", "opensim", "offline", "currency"]
            as $key
        ) {
            expect($settings->{$key})->toHaveKey("type");
        }
    });

    test("can set and get search connection", function () {
        $settings = app(DatabaseSettings::class);

        $settings->search = [
            "type" => "mysql",
            "host" => "localhost",
            "port" => "3306",
            "database" => "search_db",
            "username" => "user",
            "password" => "pass",
            "prefix" => "",
        ];
        $settings->save();

        // Clear instance cache and reload
        app()->forgetInstance(DatabaseSettings::class);
        $loaded = app(DatabaseSettings::class);

        expect($loaded->search["type"])->toBe("mysql");
        expect($loaded->search["host"])->toBe("localhost");
        expect($loaded->search["port"])->toBe("3306");
        expect($loaded->search["database"])->toBe("search_db");
        expect($loaded->search["username"])->toBe("user");
        expect($loaded->search["password"])->toBe("pass");
    });

    test("can set and get events connection", function () {
        $settings = app(DatabaseSettings::class);

        $settings->events = [
            "type" => "default",
            "prefix" => "events_",
        ];
        $settings->save();

        // Clear instance cache and reload
        app()->forgetInstance(DatabaseSettings::class);
        $loaded = app(DatabaseSettings::class);

        expect($loaded->events["type"])->toBe("default");
        expect($loaded->events["prefix"])->toBe("events_");
    });

    test("can set and get opensim connection", function () {
        $settings = app(DatabaseSettings::class);

        $settings->opensim = [
            "type" => "mysql",
            "host" => "opensim.local",
            "port" => "3306",
            "database" => "opensim",
            "username" => "opensim",
            "password" => "opensim_pass",
        ];
        $settings->save();

        // Clear instance cache and reload
        app()->forgetInstance(DatabaseSettings::class);
        $loaded = app(DatabaseSettings::class);

        expect($loaded->opensim["type"])->toBe("mysql");
        expect($loaded->opensim["host"])->toBe("opensim.local");
        expect($loaded->opensim["database"])->toBe("opensim");
    });

    test("defaults() returns proper structure", function () {
        $defaults = DatabaseSettings::defaults();

        expect($defaults)->toHaveKey("type");
        expect($defaults["type"])->toBe("default");
        expect($defaults)->toHaveKey("trace");
    });

    test("group() returns correct identifier", function () {
        expect(DatabaseSettings::group())->toBe("database");
    });
});
