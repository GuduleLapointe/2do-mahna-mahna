<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class HelpersSettings extends Settings
{
    public string $prefix;

    public static function group(): string
    {
        return "helpers";
    }

    /**
     * @return array<string,mixed>
     */
    public static function defaults(): array
    {
        return [
            "prefix" => "helpers",
        ];
    }
}
