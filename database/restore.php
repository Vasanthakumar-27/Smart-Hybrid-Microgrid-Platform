<?php
/**
 * MicroGrid Pro - Database Restore Script
 * 
 * Usage: php restore.php <backup_file.sql or backup_file.sql.zip>
 * 
 * Examples:
 *   php restore.php /backups/db_backup_20260315_143022.sql
 *   php restore.php /backups/db_backup_20260315_143022.sql.zip
 * 
 * WARNING: This will overwrite the current database!
 */

require_once dirname(dirname(__FILE__)) . '/config/database.php';
require_once dirname(dirname(__FILE__)) . '/includes/logger.php';

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line\n");
}

if ($argc < 2) {
    echo "Usage: php restore.php <backup_file>\n";
    echo "Example: php restore.php /backups/db_backup_20260315_143022.sql\n";
    exit(1);
}

$backupFile = $argv[1];

if (!file_exists($backupFile)) {
    echo "Error: Backup file not found: $backupFile\n";
    exit(1);
}

// Handle zip files
if (pathinfo($backupFile, PATHINFO_EXTENSION) === 'zip') {
    $zip = new ZipArchive();
    if ($zip->open($backupFile) !== true) {
        echo "Error: Failed to open zip file\n";
        exit(1);
    }
    
    // Extract to temporary file
    $tempDir = sys_get_temp_dir();
    $tempFile = tempnam($tempDir, 'restore_');
    
    if (!($handle = fopen($tempFile, 'w'))) {
        echo "Error: Could not create temporary file\n";
        exit(1);
    }
    
    // Read first file in zip (assuming single SQL file)
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        if (pathinfo($stat['name'], PATHINFO_EXTENSION) === 'sql') {
            $content = $zip->getFromIndex($i);
            fwrite($handle, $content);
            fclose($handle);
            $backupFile = $tempFile;
            break;
        }
    }
    $zip->close();
}

try {
    // Get database credentials
    $dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
    $dbPort = defined('DB_PORT') ? DB_PORT : 3306;
    $dbUser = defined('DB_USER') ? DB_USER : 'root';
    $dbPass = defined('DB_PASS') ? DB_PASS : '';
    $dbName = defined('DB_NAME') ? DB_NAME : 'microgrid_db';
    
    // Connect to database
    $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
    
    if ($mysqli->connect_error) {
        throw new Exception('Database connection failed: ' . $mysqli->connect_error);
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Starting restore from: $backupFile\n";
    
    // Read backup file
    $sqlContent = file_get_contents($backupFile);
    
    if ($sqlContent === false) {
        throw new Exception("Failed to read backup file");
    }
    
    // Split by semicolon and execute each statement
    $statements = explode(";\n", $sqlContent);
    $executedCount = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        
        // Skip empty statements and comments
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        // Ensure statement ends with semicolon
        if (substr($statement, -1) !== ';') {
            $statement .= ';';
        }
        
        if (!$mysqli->query($statement)) {
            // Log warning but continue (some statements might be informational)
            if ($mysqli->errno !== 0) {
                echo "[WARNING] Error executing statement: " . $mysqli->error . "\n";
            }
        } else {
            $executedCount++;
        }
    }
    
    $mysqli->close();
    
    echo "[" . date('Y-m-d H:i:s') . "] ✓ Restore completed successfully\n";
    echo "Executed $executedCount SQL statements\n";
    
    // Cleanup temp file if created
    if (isset($tempFile) && file_exists($tempFile)) {
        unlink($tempFile);
    }
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ✗ Restore failed: " . $e->getMessage() . "\n";
    
    // Cleanup temp file if created
    if (isset($tempFile) && file_exists($tempFile)) {
        unlink($tempFile);
    }
    
    exit(1);
}

exit(0);
?>
