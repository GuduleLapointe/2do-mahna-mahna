<?php

namespace Tests\Feature;

/**
 * Pest tests for Settings page.
 */

use App\Filament\Pages\Settings;
use App\Models\User;
use App\Settings\DatabaseSettings;
use App\Settings\GeneralSettings;
use App\Settings\HelpersSettings;
use Filament\Forms\Testing\TestsForms;
use Livewire\Livewire;

beforeEach(function () {
    $user = User::factory()->create();
    $user->assignRole("administrator");
    expect($user->hasRole("administrator"))->toBeTrue();
    $this->actingAs($user);
});

describe("Settings page", function () {
    test("is accessible", function () {
        $url = Settings::getUrl();
        $this->get($url)->assertOk();
    });

    test("has sections", function () {
        $url = Settings::getUrl();
        $this->get($url)
            ->assertSee("Helpers")
            ->assertSee("General")
            ->assertSee("Search Engine");
    })->depends("is accessible");
});

describe("Settings update", function () {
    test("submit changes and persist", function () {
        // Submit the form
        Livewire::test(Settings::class)
            ->fillForm([
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
            ])
            ->call("save")
            ->assertHasNoFormErrors();

        // Verify data persisted - reset container to force fresh instances
        app()->forgetInstance(GeneralSettings::class);
        app()->forgetInstance(HelpersSettings::class);
        app()->forgetInstance(DatabaseSettings::class);

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
});
