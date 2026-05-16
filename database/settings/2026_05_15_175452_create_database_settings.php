<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;
use App\Settings\DatabaseSettings;

return new class extends SettingsMigration {
    public function up(): void
    {
        // $default_connection = config("database.default") ?: "sqlite";
        // $defaults = DatabaseSettings::defaults()[$default_connection] ?? [];
        $defaults = DatabaseSettings::defaults();
        $defaults_robust = array_merge($defaults["trace"]["mysql"], [
            "host" => env("ROBUST_DB_HOST", "localhost"),
            "port" => env("ROBUST_DB_PORT", "3306"),
            "database" => env("ROBUST_DB_DATABASE", "opensim"),
            "username" => env("ROBUST_DB_USERNAME", "opensim"),
            "password" => env("ROBUST_DB_PASSWORD", ""),
        ]);
        $this->migrator->add("database.search", $defaults);
        $this->migrator->add("database.events", $defaults);
        $this->migrator->add(
            "database.robust",
            array_merge($defaults, ["type" => "mysql"], $defaults_robust),
        );
        $this->migrator->add(
            "database.opensim",
            array_merge($defaults, ["type" => "robust"], $defaults_robust),
        );
        $this->migrator->add(
            "database.offline",
            array_merge($defaults, ["type" => "robust"], $defaults_robust),
        );
        $this->migrator->add(
            "database.currency",
            array_merge($defaults, ["type" => "robust"], $defaults_robust),
        );
    }
};
