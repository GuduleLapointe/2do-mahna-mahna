<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public DateTimeZone $timezone;

    public static function group(): string
    {
        return "Administration";
    }
}
