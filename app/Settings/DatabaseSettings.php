<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class DatabaseSettings extends Settings
{
    // Search/Events, independant from grid, for both grid and multi-grid services
    public array $search = [];
    public array $events = [];

    // Specific to one single grid or standalone OpenSim server.
    public array $robust = [];
    public array $opensim = [];
    public array $offline = [];
    public array $currency = [];

    public static function group(): string
    {
        return "database";
    }

    /**
     * @return array<string,mixed>
     */
    public static function defaults(): array
    {
        // Use Laravel config as default, it already contains proper formats for
        // sqlite, mysql and postgresql
        return config("database.connections");
    }
}
