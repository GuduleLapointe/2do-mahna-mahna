<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;
use App\Settings\DatabaseSettings;

return new class extends SettingsMigration {
    public function up(): void
    {
        // $default_connection = config("database.default") ?: "sqlite";
        // $defaults = DatabaseSettings::defaults()[$default_connection] ?? [];
        $defaults = DatabaseSettings::defaults();
        $this->migrator->add("database.search", $defaults);
        $this->migrator->add("database.events", $defaults);
        $this->migrator->add("database.robust", $defaults);
        $this->migrator->add("database.opensim", $defaults);
        $this->migrator->add("database.offline", $defaults);
        $this->migrator->add("database.currency", $defaults);
    }
};
