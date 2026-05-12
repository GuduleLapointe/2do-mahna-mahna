<?php

namespace App\Settings;

// use DateTimeZone;
use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $app_name;
    public bool $site_active;
    public string $timezone;

    public static function group(): string
    {
        return "general";
    }

    /**
     * @return array<string,mixed>
     */
    public static function defaults(): array
    {
        return [
            "app_name" => "Default App",
            "timezone" => "Europe/Paris",
            "site_active" => true,
        ];
    }
}
