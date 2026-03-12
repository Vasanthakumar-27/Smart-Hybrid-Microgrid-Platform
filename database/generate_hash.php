<?php
/**
 * Utility: Generate password hashes for seeding
 * Run: php database/generate_hash.php <password>
 */

if (php_sapi_name() !== 'cli') {
    die('CLI only');
}

$password = $argv[1] ?? 'admin123';
echo "Password: $password\n";
echo "Hash: " . password_hash($password, PASSWORD_DEFAULT) . "\n";
