<?php

namespace App\Settings;

use DateTimeZone;
use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $app_name;

    public string $app_url;

    public string $admin_email;

    public bool $site_active;

    public bool $maintenance_mode;

    public DateTimeZone $timezone;

    public static function group(): string
    {
        return "general";
    }
}
