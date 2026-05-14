<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class HelpersSettings extends Settings
{
    public string $base_helpers;
    public string $base_currency;
    public array $credentials;

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
            "base_currency" => "economy",
            "credentials" => [
                "search_db" => [
                    "type" => "default", // default = Laravel storage, other fields disabled. Other options: MySQL, PostgreSQL, SQLite3
                    "hostname" => "localhost",
                    "port" => 3306,
                    "user" => "opensim",
                    "password" => "",
                    "prefix" => "ossearch_",
                ],
            ],
        ];
    }
}
