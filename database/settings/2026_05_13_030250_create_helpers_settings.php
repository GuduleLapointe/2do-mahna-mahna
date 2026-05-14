<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;
use App\Settings\HelpersSettings;

return new class extends SettingsMigration {
    public function up(): void
    {
        $defaults = HelpersSettings::defaults();
        $this->migrator->add("helpers.base_helpers", $defaults["base_helpers"]);
        $this->migrator->add(
            "helpers.base_currency",
            $defaults["base_currency"],
        );
    }
};
