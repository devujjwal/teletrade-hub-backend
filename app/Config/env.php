<?php

/**
 * Environment Configuration Loader
 * Loads environment variables from .env file
 */

class Env
{
    private static $loaded = false;
    private static $vars = [];

    /**
     * Load environment variables from .env file
     */
    public static function load($path = __DIR__ . '/../../.env')
    {
        if (self::$loaded) {
            return;
        }

        if (!file_exists($path)) {
            // In production, .env might not exist, rely on server env vars
            self::$loaded = true;
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                    $value = $matches[2];
                }
                
                self::$vars[$key] = $value;
                
                // Set as environment variable if not already set
                if (!getenv($key)) {
                    putenv("$key=$value");
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Get environment variable
     */
    public static function get($key, $default = null)
    {
        if (!self::$loaded) {
            self::load();
        }

        // Check loaded vars first
        if (isset(self::$vars[$key])) {
            return self::$vars[$key];
        }

        // Check system environment
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }

    /**
     * Check if environment variable exists
     */
    public static function has($key)
    {
        return self::get($key) !== null;
    }
}

