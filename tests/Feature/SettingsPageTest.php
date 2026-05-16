<?php
/**
 * Pest tests for Settings page.
 */

use App\Models\User;
use App\Settings\GeneralSettings;
use App\Settings\HelpersSettings;
use App\Settings\DatabaseSettings;
use function Pest\Laravel\ActingAs;

test("settings page can be rendered", function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route("admin/settings"))
        ->assertOk()
        ->assertSee("Helpers")
        ->assertSee("General")
        ->assertSee("Search Engine");
});

test("settings can be updated", function () {
    $user = User::factory()->create([
        "is_admin" => true,
    ]);

    $response = actingAs($user)->post(route("settings"), [
        "app_name" => "New App Name",
        "timezone" => "UTC",
        "base_helpers" => "new_helpers",
        "base_currency" => "new_currency",
        "search" => [
            "type" => "mysql",
            "host" => "localhost",
            "port" => "3306",
            "database" => "search_db",
            "username" => "user",
            "password" => "password",
            "prefix" => "search_",
        ],
        "events" => [
            "type" => "default",
            "prefix" => "events_",
        ],
    ]);

    $response->assertSessionHasNoErrors();

    $general = app(GeneralSettings::class);
    expect($general->app_name)->toBe("New App Name");
    expect($general->timezone)->toBe("UTC");

    $helpers = app(HelpersSettings::class);
    expect($helpers->base_helpers)->toBe("new_helpers");
    expect($helpers->base_currency)->toBe("new_currency");

    $database = app(DatabaseSettings::class);
    expect($database->search["type"])->toBe("mysql");
    expect($database->search["host"])->toBe("localhost");
});
