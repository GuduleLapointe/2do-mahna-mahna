<?php

/**
 * Shared configuration loader.
 *
 * Priority chain (lowest → highest):
 *   hardcoded defaults → config/config.json → .env files → system env → HTTP query params
 *
 * Naming conventions:
 *   Internal / JSON:   snake_case  (my_var)
 *   Environment vars:  UPPER_SNAKE (MY_VAR)
 *   HTTP query params: kebab-case  (my-var) — normalized on read
 */
class Config
{
    private static array $data = [];

    /**
     * Load configuration from all sources in priority order.
     *
     * @param array  $defaults        Default values (any key convention accepted).
     * @param string $jsonFile        Optional path to JSON config file.
     * @param array  $envFiles        Ordered .env files; later files override earlier ones.
     * @param bool   $withQueryParams Apply $_GET overrides (HTTP context only).
     */
    public static function load(
        array $defaults = [],
        string $jsonFile = '',
        array $envFiles = [],
        bool $withQueryParams = false
    ): void {
        self::$data = [];

        // 1. Defaults
        foreach ($defaults as $key => $value) {
            self::$data[self::key($key)] = $value;
        }

        // 2. JSON config file
        if ($jsonFile !== '' && file_exists($jsonFile)) {
            $json = json_decode(file_get_contents($jsonFile), true);
            if (is_array($json)) {
                foreach ($json as $key => $value) {
                    self::$data[self::key($key)] = $value;
                }
            }
        }

        // 3. .env files — later files override earlier ones
        foreach ($envFiles as $file) {
            if (!file_exists($file)) {
                continue;
            }
            $env = parse_ini_file($file);
            if (!is_array($env)) {
                continue;
            }
            foreach ($env as $key => $value) {
                self::$data[self::key($key)] = $value;
            }
        }

        // 4. System environment variables (e.g. MY_VAR=foo ./script.php)
        //    Only keys already declared via previous sources are considered.
        foreach (array_keys(self::$data) as $key) {
            $envValue = getenv(strtoupper($key));
            if ($envValue !== false) {
                self::$data[$key] = $envValue;
            }
        }

        // 5. HTTP query parameters
        if ($withQueryParams) {
            foreach ($_GET as $key => $value) {
                $k = self::key($key);
                if (array_key_exists($k, self::$data)) {
                    self::$data[$k] = $value;
                }
            }
        }
    }

    /**
     * Get a config value, with optional fallback.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$data[self::key($key)] ?? $default;
    }

    /**
     * Set a runtime config value.
     */
    public static function set(string $key, mixed $value): void
    {
        self::$data[self::key($key)] = $value;
    }

    /**
     * Return all loaded config values.
     */
    public static function all(): array
    {
        return self::$data;
    }

    /**
     * Normalize any key convention to snake_case lowercase.
     */
    private static function key(string $key): string
    {
        return strtolower(str_replace(['-', ' '], '_', $key));
    }
}
