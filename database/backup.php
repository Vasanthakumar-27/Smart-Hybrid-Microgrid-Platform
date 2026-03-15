<?php
/**
 * MicroGrid Pro - Database Backup Script (PHP)
 * 
 * This script can be called via cron/scheduled task to backup the database.
 * Usage: php backup.php [--compress] [--days-to-keep=7] [--output-dir=/path]
 * 
 * Example cron: 0 2 * * * php /var/www/microgrid/database/backup.php --compress
 * (Runs daily at 2 AM)
 */

// Include configuration (database.php already loads env and logger)
require_once dirname(dirname(__FILE__)) . '/config/database.php';

// Parse arguments
$compress = in_array('--compress', $argv);
$daysToKeep = 7;
$outputDir = dirname(__FILE__) . '/backups';

// Parse --days-to-keep argument
foreach ($argv as $arg) {
    if (strpos($arg, '--days-to-keep=') === 0) {
        $daysToKeep = (int)str_replace('--days-to-keep=', '', $arg);
    } elseif (strpos($arg, '--output-dir=') === 0) {
        $outputDir = str_replace('--output-dir=', '', $arg);
    }
}

// Ensure output directory exists
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$timestamp = date('YmdHis');
$backupFile = $outputDir . '/db_backup_' . $timestamp . '.sql';
$logFile = $outputDir . '/backup.log';

function log_backup($message, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $level: $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    echo $logMessage;
}

try {
    log_backup("Backup started");
    
    // Step 1: Get MySQL credentials from database config
    $dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
    $dbPort = defined('DB_PORT') ? DB_PORT : 3306;
    $dbUser = defined('DB_USER') ? DB_USER : 'root';
    $dbPass = defined('DB_PASS') ? DB_PASS : '';
    $dbName = defined('DB_NAME') ? DB_NAME : 'microgrid_db';
    
    // Step 2: Create SQL dump using mysqli
    if (!function_exists('mysqldump_cli')) {
        // Use PHP to create dump (alternative to system mysqldump)
        $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
        
        if ($mysqli->connect_error) {
            throw new Exception('Database connection failed: ' . $mysqli->connect_error);
        }
        
        $tables = [];
        $result = $mysqli->query("SHOW TABLES");
        if (!$result) {
            throw new Exception('Failed to list tables: ' . $mysqli->error);
        }
        
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        
        // Build SQL dump
        $sqlDump = "-- MicroGrid Pro Database Backup\n";
        $sqlDump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sqlDump .= "-- Host: $dbHost\n";
        $sqlDump .= "-- Database: $dbName\n";
        $sqlDump .= "-- PHP Version: " . phpversion() . "\n\n";
        $sqlDump .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
        $sqlDump .= "SET AUTOCOMMIT=0;\n";
        $sqlDump .= "START TRANSACTION;\n\n";
        
        // Dump each table
        foreach ($tables as $table) {
            $result = $mysqli->query("SHOW CREATE TABLE `$table`");
            if (!$result) {
                log_backup("Warning: Could not get CREATE TABLE for $table", 'WARN');
                continue;
            }
            
            $row = $result->fetch_assoc();
            $sqlDump .= "\nDROP TABLE IF EXISTS `$table`;\n";
            $sqlDump .= $row['Create Table'] . ";\n";
            
            // Dump data
            $dataResult = $mysqli->query("SELECT * FROM `$table`");
            if (!$dataResult) {
                log_backup("Warning: Could not dump data from $table", 'WARN');
                continue;
            }
            
            if ($dataResult->num_rows > 0) {
                $columns = [];
                $field = $dataResult->fetch_fields();
                foreach ($field as $f) {
                    $columns[] = '`' . $f->name . '`';
                }
                
                $sqlDump .= "\nINSERT INTO `$table` (" . implode(',', $columns) . ") VALUES\n";
                $first = true;
                
                while ($data = $dataResult->fetch_assoc()) {
                    if (!$first) {
                        $sqlDump .= ",\n";
                    }
                    $first = false;
                    
                    $values = [];
                    foreach ($data as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $mysqli->real_escape_string($value) . "'";
                        }
                    }
                    $sqlDump .= "(" . implode(',', $values) . ")";
                }
                $sqlDump .= ";\n";
            }
        }
        
        $sqlDump .= "\nCOMMIT;\n";
        $sqlDump .= "SET AUTOCOMMIT=1;\n";
        
        $mysqli->close();
    }
    
    // Step 3: Write dump to file
    if (!file_put_contents($backupFile, $sqlDump)) {
        throw new Exception("Failed to write backup file: $backupFile");
    }
    
    $fileSize = filesize($backupFile) / (1024 * 1024);
    log_backup("Database backed up to $backupFile ({$fileSize}MB)");
    
    // Step 4: Compress if requested
    if ($compress && extension_loaded('zip')) {
        $zipFile = $backupFile . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Failed to create zip: $zipFile");
        }
        
        $zip->addFile($backupFile, basename($backupFile));
        $zip->close();
        
        unlink($backupFile);
        $fileSize = filesize($zipFile) / (1024 * 1024);
        log_backup("Backup compressed ({$fileSize}MB)");
    } elseif ($compress && !extension_loaded('zip')) {
        log_backup("WARNING: ZIP extension not available, saving uncompressed");
    }
    
    // Step 5: Cleanup old backups
    $cutoffTime = time() - ($daysToKeep * 86400);
    $deletedCount = 0;
    
    $files = glob($outputDir . '/db_backup_*.sql*');
    foreach ($files as $file) {
        if (filemtime($file) < $cutoffTime) {
            unlink($file);
            $deletedCount++;
        }
    }
    
    if ($deletedCount > 0) {
        log_backup("Removed $deletedCount old backup(s)");
    }
    
    // Step 6: Summary stats
    $totalSize = 0;
    $fileCount = 0;
    foreach (glob($outputDir . '/db_backup_*') as $file) {
        $fileCount++;
        $totalSize += filesize($file);
    }
    
    $totalSize = $totalSize / (1024 * 1024);
    log_backup("✓ Backup completed successfully - $fileCount backup(s), Total: {$totalSize}MB");
    
} catch (Exception $e) {
    log_backup("✗ Backup failed: " . $e->getMessage(), 'ERROR');
    exit(1);
}

exit(0);
?>
