<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $app_name;
    public bool $site_active;
    public DateTimeZone $timezone;

    public static function group(): string
    {
        return "Administration";
    }
}
