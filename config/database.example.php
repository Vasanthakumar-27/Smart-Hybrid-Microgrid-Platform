<?php
/**
 * Database Configuration Template
 * ─────────────────────────────────────────────
 * HOW TO USE:
 *   1. Copy this file and rename it to  config/database.php
 *   2. Fill in your local MySQL credentials below
 *
 * This file is intentionally NOT tracked by Git.
 * ─────────────────────────────────────────────
 */

// MySQL connection
define('DB_HOST', 'localhost');       // usually localhost for XAMPP
define('DB_NAME', 'microgrid_platform');
define('DB_USER', 'root');            // default XAMPP user
define('DB_PASS', '');                // default XAMPP password (empty)
define('DB_CHARSET', 'utf8mb4');

/**
 * Get PDO database connection (singleton)
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}

// Application Settings
define('APP_NAME', 'MicroGrid Pro');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '/microgrid-platform/');   // ← change if your folder name differs

// Timezone
date_default_timezone_set('Asia/Kolkata');    // ← change to your timezone if needed

// Session hardening
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
