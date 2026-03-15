<?php
/**
 * Environment Configuration Loader
 * Handles .env file loading and retrieval
 * 
 * Note: Uses static methods to avoid conflicts with PHP built-in getEnv()
 */

// Prevent multiple inclusions
if (defined('__ENV_PHP_LOADED__')) {
    return;
}
define('__ENV_PHP_LOADED__', true);

class Env {
    /**
     * Load environment variables from .env file
     */
    public static function load(string $envFile = __DIR__ . '/../.env'): void {
        if (!file_exists($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes
            if ((($value[0] ?? '') === '"' && substr($value, -1) === '"') ||
                (($value[0] ?? '') === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }

            // Set environment variable
            if (!empty($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }

    /**
     * Get environment variable with string fallback
     */
    public static function get(string $key, string $default = ''): string {
        $value = getenv($key);
        return ($value !== false) ? $value : $default;
    }

    /**
     * Get environment variable as boolean
     */
    public static function getBool(string $key, bool $default = false): bool {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Get environment variable as integer
     */
    public static function getInt(string $key, int $default = 0): int {
        $value = getenv($key);
        return ($value !== false) ? (int)$value : $default;
    }
}

// Backward compatibility: Create global wrapper functions that call Env class
// These wrappers won't conflict with built-in getEnv() because they're lowercase
if (!function_exists('loadEnv')) {
    function loadEnv(string $envFile = __DIR__ . '/../.env'): void {
        Env::load($envFile);
    }
}

// Use Env class directly instead of function wrappers to avoid PHP built-in conflicts
// In database.php, change from getEnv() to Env::get(), etc.


