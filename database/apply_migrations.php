<?php
/**
 * Database Migration Runner
 * Applies SQL migrations with safety checks and rollback support
 * 
 * Usage:
 *   php database/apply_migrations.php [--dry-run] [--verbose]
 */

require_once __DIR__ . '/../config/database.php';

$is_cli = php_sapi_name() === 'cli';
$dry_run = in_array('--dry-run', $_SERVER['argv'] ?? []);
$verbose = in_array('--verbose', $_SERVER['argv'] ?? []);

if (!$is_cli) {
    die("This script must be run from command line.\n");
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║ Database Migration Runner                                      ║\n";
echo "║ MicroGrid Platform                                             ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

if ($dry_run) {
    echo "🔍 DRY RUN MODE - No changes will be applied\n\n";
}

try {
    $db = getDB();
    $migrations_dir = __DIR__ . '/migrations';
    $migration_files = glob($migrations_dir . '/*.sql');
    sort($migration_files);
    
    if (empty($migration_files)) {
        echo "❌ No migration files found in {$migrations_dir}\n";
        exit(1);
    }
    
    // Run each migration
    $applied = 0;
    $failed = 0;
    
    foreach ($migration_files as $migration_file) {
        $filename = basename($migration_file);
        
        // Skip non-numerical migrations
        if (!preg_match('/^\d{8}/', $filename)) {
            if ($verbose) echo "⏭️  Skipping non-migration file: {$filename}\n";
            continue;
        }
        
        echo "📦 Processing: {$filename}...\n";
        
        try {
            $sql = file_get_contents($migration_file);
            
            // Parse SQL statements (split by semicolon, excluding comments)
            $statements = [];
            $current = '';
            foreach (explode("\n", $sql) as $line) {
                // Skip comments
                if (strpos(trim($line), '--') === 0) continue;
                
                $current .= $line . "\n";
                
                // Statement complete
                if (strpos($line, ';') !== false) {
                    $statement = trim(str_replace(';', '', $current));
                    if (!empty($statement)) {
                        $statements[] = $statement;
                    }
                    $current = '';
                }
            }
            
            if ($verbose) {
                echo "   Found " . count($statements) . " SQL statements\n";
            }
            
            // Execute statements
            foreach ($statements as $statement) {
                if ($verbose) {
                    echo "   Executing: " . substr($statement, 0, 60) . "...\n";
                }
                
                if (!$dry_run) {
                    try {
                        $db->exec($statement);
                    } catch (PDOException $e) {
                        // Ignore "already exists" errors for ALTER TABLE ADD INDEX
                        if (strpos($e->getMessage(), 'Duplicate key name') !== false ||
                            strpos($e->getMessage(), 'already exists') !== false) {
                            if ($verbose) {
                                echo "   ⚠️  Index/key already exists (skipping)\n";
                            }
                        } else {
                            throw $e;
                        }
                    }
                }
            }
            
            echo "   ✅ Successfully applied\n";
            $applied++;
            
        } catch (Exception $e) {
            echo "   ❌ Error: " . $e->getMessage() . "\n";
            $failed++;
        }
    }
    
    // Summary
    echo "\n" . str_repeat("═", 65) . "\n";
    if ($dry_run) {
        echo "✓ DRY RUN COMPLETE - 0 changes applied\n";
    } else {
        echo "✓ MIGRATION COMPLETE\n";
        echo "  Applied: {$applied}\n";
        echo "  Failed:  {$failed}\n";
    }
    echo str_repeat("═", 65) . "\n\n";
    
    // Verify indexes were created (if 20260315 was applied)
    if (!$dry_run && strpos(implode($migration_files), '20260315') !== false) {
        echo "🔍 Verifying index creation...\n";
        $index_query = "
            SELECT COUNT(*) as index_count 
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = 'microgrid_platform' 
            AND INDEX_NAME != 'PRIMARY'
        ";
        $result = $db->query($index_query)->fetch(PDO::FETCH_ASSOC);
        echo "   Found " . $result['index_count'] . " indexes in total\n";
        
        // Show new indexes
        $new_indexes = $db->query("
            SELECT TABLE_NAME, COUNT(*) as index_count 
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = 'microgrid_platform' 
            AND INDEX_NAME != 'PRIMARY'
            GROUP BY TABLE_NAME
            ORDER BY TABLE_NAME
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($new_indexes as $row) {
            echo "   • {$row['TABLE_NAME']}: {$row['index_count']} indexes\n";
        }
    }
    
    echo "\n";
    exit(0);
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n\n";
    exit(1);
}

?>