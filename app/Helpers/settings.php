<?php

if (!function_exists("settings")) {
    function settings(?string $key = null, $default = null)
    {
        if ($key === null) {
            return null;
        }

        $parts = explode(".", $key);
        if (count($parts) === 2) {
            [$group, $setting] = $parts;

            // Vérifier si la table settings existe avant d'essayer d'accéder aux paramètres
            // if (!\Illuminate\Support\Facades\Schema::hasTable('settings')) {
            //     return $default;
            // }

            // Récupérer la classe de settings correspondante
            $settingsClass = match ($group) {
                "general" => \App\Settings\GeneralSettings::class,
                "helpers" => \App\Settings\HelpersSettings::class,
                default => null,
            };

            if ($settingsClass === null) {
                return $default;
            }

            try {
                $settings = app($settingsClass);
                return $settings->{$setting} ?? $default;
            } catch (\Exception $e) {
                return $default;
            }
        }

        return $default;
    }
}
