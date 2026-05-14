<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class HelpersSettings extends Settings
{
    public string $base_helpers;

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
            "base_helpers" => "helpers",
        ];
    }
}
