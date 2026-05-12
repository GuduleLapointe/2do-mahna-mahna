<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration {
    public function up(): void
    {
        $this->migrator->add("general.app_name", "2do Mahnà Mahnà");
        $this->migrator->add("general.site_active", true);
        $this->migrator->add("general.timezone", "America/Los_Angeles");
    }
};
