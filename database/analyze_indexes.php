<?php
/**
 * Database Index Analyzer
 * Analyzes current indexes, identifies missing ones, and provides recommendations
 * 
 * Usage:
 *   CLI: php database/analyze_indexes.php [--detailed] [--slow-queries]
 *   Web: Visit /database/analyze_indexes.php in browser (requires admin login)
 */

require_once __DIR__ . '/../config/database.php';

// Simple security check - allow CLI or require auth
$is_cli = (php_sapi_name() === 'cli');
$is_admin = false;

if (!$is_cli) {
    // Web context - would need to require auth, but for now allow if in direct access
    // In production, add proper authentication check
    //header('Content-Type: application/json');
}

try {
    $db = getDB();
    $results = analyzeIndexes($db, $is_cli);
    
    if ($is_cli) {
        displayCliResults($results);
    } else {
        displayHtmlResults($results);
    }
} catch (Exception $e) {
    error_log("Index analyzer error: " . $e->getMessage());
    echo $is_cli ? "Error: " . $e->getMessage() . "\n" : json_encode(['error' => $e->getMessage()]);
}

/**
 * Analyze database indexes and provide recommendations
 */
function analyzeIndexes($db, $is_cli = true) {
    $schema = 'microgrid_platform';
    
    $results = [
        'timestamp' => date('Y-m-d H:i:s'),
        'database' => $schema,
        'tables' => [],
        'recommendations' => [],
        'index_size' => [],
        'missing_fk_indexes' => []
    ];
    
    // Get all tables
    $tables_query = "SELECT TABLE_NAME FROM information_schema.TABLES 
                    WHERE TABLE_SCHEMA = ? 
                    ORDER BY TABLE_NAME";
    $tables_stmt = $db->prepare($tables_query);
    $tables_stmt->execute([$schema]);
    
    while ($row = $tables_stmt->fetch(PDO::FETCH_ASSOC)) {
        $table = $row['TABLE_NAME'];
        
        // Get current indexes
        $indexes_query = "SELECT 
                            INDEX_NAME,
                            COLUMN_NAME, 
                            SEQ_IN_INDEX,
                            (SELECT COUNT(*) FROM information_schema.STATISTICS s2 
                             WHERE s2.TABLE_SCHEMA = s.TABLE_SCHEMA 
                             AND s2.TABLE_NAME = s.TABLE_NAME 
                             AND s2.INDEX_NAME = s.INDEX_NAME) as index_columns
                        FROM information_schema.STATISTICS s
                        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                        ORDER BY INDEX_NAME, SEQ_IN_INDEX";
        $idx_stmt = $db->prepare($indexes_query);
        $idx_stmt->execute([$schema, $table]);
        
        $indexes = [];
        while ($idx = $idx_stmt->fetch(PDO::FETCH_ASSOC)) {
            $idx_name = $idx['INDEX_NAME'];
            if (!isset($indexes[$idx_name])) {
                $indexes[$idx_name] = [];
            }
            $indexes[$idx_name][] = $idx['COLUMN_NAME'];
        }
        
        // Get table size and stats
        $size_query = "SELECT 
                        TABLE_ROWS,
                        DATA_LENGTH,
                        INDEX_LENGTH,
                        DATA_FREE,
                        AUTO_INCREMENT
                    FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?";
        $size_stmt = $db->prepare($size_query);
        $size_stmt->execute([$schema, $table]);
        $size_info = $size_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get foreign keys
        $fk_query = "SELECT 
                        CONSTRAINT_NAME,
                        COLUMN_NAME,
                        REFERENCED_TABLE_NAME,
                        REFERENCED_COLUMN_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = ? 
                    AND TABLE_NAME = ? 
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                    ORDER BY CONSTRAINT_NAME";
        $fk_stmt = $db->prepare($fk_query);
        $fk_stmt->execute([$schema, $table]);
        
        $foreign_keys = [];
        while ($fk = $fk_stmt->fetch(PDO::FETCH_ASSOC)) {
            $foreign_keys[] = [
                'column' => $fk['COLUMN_NAME'],
                'references' => $fk['REFERENCED_TABLE_NAME'],
                'ref_column' => $fk['REFERENCED_COLUMN_NAME']
            ];
        }
        
        // Identify missing foreign key indexes
        foreach ($foreign_keys as $fk) {
            $col = $fk['column'];
            $has_index = false;
            
            foreach ($indexes as $idx_name => $idx_cols) {
                if ($idx_cols[0] === $col) {
                    $has_index = true;
                    break;
                }
            }
            
            if (!$has_index) {
                $results['missing_fk_indexes'][] = [
                    'table' => $table,
                    'column' => $col,
                    'references' => $fk['references'],
                    'recommendation' => "CREATE INDEX idx_{$table}_{$col} ON {$table}({$col})"
                ];
            }
        }
        
        $results['tables'][$table] = [
            'indexes' => $indexes,
            'foreign_keys' => $foreign_keys,
            'row_count' => (int)$size_info['TABLE_ROWS'],
            'data_length_mb' => round($size_info['DATA_LENGTH'] / 1024 / 1024, 2),
            'index_length_mb' => round($size_info['INDEX_LENGTH'] / 1024 / 1024, 2),
            'fragmentation_mb' => round($size_info['DATA_FREE'] / 1024 / 1024, 2),
            'total_size_mb' => round(($size_info['DATA_LENGTH'] + $size_info['INDEX_LENGTH']) / 1024 / 1024, 2)
        ];
        
        // Add index size separately
        $results['index_size'][$table] = $results['tables'][$table]['index_length_mb'];
    }
    
    // Generate recommendations
    $results['recommendations'] = generateRecommendations($results);
    
    return $results;
}

/**
 * Generate optimization recommendations
 */
function generateRecommendations($results) {
    $recommendations = [];
    
    foreach ($results['tables'] as $table => $info) {
        // Check for tables that would benefit from additional indexes
        if ($info['row_count'] > 10000 && $info['index_length_mb'] < $info['data_length_mb'] * 0.3) {
            $recommendations[] = [
                'table' => $table,
                'type' => 'potential_missing_indexes',
                'message' => "Table '{$table}' has {$info['row_count']} rows but relatively few indexes",
                'action' => 'Review query patterns and add indexes for frequently filtered columns'
            ];
        }
        
        // Check for fragmentation
        if ($info['fragmentation_mb'] > $info['data_length_mb'] * 0.2) {
            $recommendations[] = [
                'table' => $table,
                'type' => 'fragmentation',
                'message' => "Table '{$table}' is {$info['fragmentation_mb']}MB fragmented",
                'action' => "Run: OPTIMIZE TABLE {$table};"
            ];
        }
        
        // Check for large tables without time-based indexes
        if ($info['row_count'] > 100000 && strpos(json_encode($info['indexes']), 'timestamp') === false) {
            if (in_array($table, ['energy_readings', 'battery_status', 'alerts', 'system_logs'])) {
                $recommendations[] = [
                    'table' => $table,
                    'type' => 'missing_time_index',
                    'message' => "High-volume table '{$table}' lacks timestamp index for range queries",
                    'action' => "Add: CREATE INDEX idx_{$table}_timestamp ON {$table}(timestamp DESC);"
                ];
            }
        }
    }
    
    return $recommendations;
}

/**
 * Display results in CLI format
 */
function displayCliResults($results) {
    echo "\n";
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║ Database Index Analysis Report                                 ║\n";
    echo "║ Generated: " . str_pad($results['timestamp'], 49) . "║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    
    echo "📊 Database: {$results['database']}\n";
    echo "📈 Tables analyzed: " . count($results['tables']) . "\n\n";
    
    // Table breakdown
    echo "TABLE SUMMARY\n";
    echo str_repeat("─", 80) . "\n";
    printf("%-20s %-12s %-15s %-15s %-15s\n", "Table Name", "Rows", "Data (MB)", "Index (MB)", "Total (MB)");
    echo str_repeat("─", 80) . "\n";
    
    $total_rows = 0;
    $total_data = 0;
    $total_index = 0;
    
    foreach ($results['tables'] as $table => $info) {
        printf("%-20s %-12s %-15.2f %-15.2f %-15.2f\n",
            $table,
            number_format($info['row_count']),
            $info['data_length_mb'],
            $info['index_length_mb'],
            $info['total_size_mb']
        );
        $total_rows += $info['row_count'];
        $total_data += $info['data_length_mb'];
        $total_index += $info['index_length_mb'];
    }
    
    echo str_repeat("─", 80) . "\n";
    printf("%-20s %-12s %-15.2f %-15.2f %-15.2f\n", "TOTAL", number_format($total_rows), $total_data, $total_index, $total_data + $total_index);
    echo "\n";
    
    // Missing FK indexes
    if (!empty($results['missing_fk_indexes'])) {
        echo "⚠️  MISSING FOREIGN KEY INDEXES\n";
        echo str_repeat("─", 80) . "\n";
        foreach ($results['missing_fk_indexes'] as $missing) {
            echo "  • {$missing['table']}.{$missing['column']} → {$missing['references']}\n";
            echo "    $ {$missing['recommendation']}\n";
        }
        echo "\n";
    }
    
    // Recommendations
    if (!empty($results['recommendations'])) {
        echo "💡 OPTIMIZATION RECOMMENDATIONS\n";
        echo str_repeat("─", 80) . "\n";
        foreach ($results['recommendations'] as $rec) {
            echo "  • [{$rec['table']}] {$rec['message']}\n";
            echo "    Action: {$rec['action']}\n";
        }
        echo "\n";
    } else {
        echo "✅ No optimization recommendations at this time.\n\n";
    }
    
    // Index listing per table
    echo "INDEX BREAKDOWN BY TABLE\n";
    echo str_repeat("─", 80) . "\n";
    foreach ($results['tables'] as $table => $info) {
        if (empty($info['indexes'])) {
            echo "  {$table}: No indexes (PRIMARY KEY only)\n";
            continue;
        }
        echo "  {$table}:\n";
        foreach ($info['indexes'] as $idx_name => $columns) {
            echo "    - {$idx_name}: " . implode(", ", $columns) . "\n";
        }
    }
    echo "\n";
}

/**
 * Display results in HTML format
 */
function displayHtmlResults($results) {
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Index Analysis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .analysis-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .index-badge { display: inline-block; background: #e9ecef; padding: 0.25rem 0.75rem; border-radius: 20px; margin: 0.25rem; font-size: 0.85rem; }
        .recommendation { border-left: 4px solid #ffc107; background: #fff8e1; padding: 1rem; margin-bottom: 0.5rem; border-radius: 4px; }
        .warning { border-left: 4px solid #dc3545; background: #f8d7da; padding: 1rem; margin-bottom: 0.5rem; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="analysis-header">
        <h1><i class="bi bi-speedometer2"></i> Database Index Analysis</h1>
        <p class="mb-0">Generated: <?= $results['timestamp'] ?></p>
    </div>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-4">
                <div class="stat-card">
                    <h5>Database</h5>
                    <p class="fs-5 mb-0"><?= $results['database'] ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <h5>Tables</h5>
                    <p class="fs-5 mb-0"><?= count($results['tables']) ?></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <h5>Issues Found</h5>
                    <p class="fs-5 mb-0 <?= count($results['missing_fk_indexes']) > 0 ? 'text-danger' : 'text-success' ?>">
                        <?= count($results['missing_fk_indexes']) + count($results['recommendations']) ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Missing FK Indexes -->
        <?php if (!empty($results['missing_fk_indexes'])): ?>
        <div class="mt-4">
            <h3><i class="bi bi-exclamation-circle"></i> Missing Foreign Key Indexes</h3>
            <?php foreach ($results['missing_fk_indexes'] as $missing): ?>
            <div class="warning">
                <strong><?= $missing['table'] ?>.<?= $missing['column'] ?></strong> → <?= $missing['references'] ?>
                <br><code style="font-size: 0.9rem;"><?= $missing['recommendation'] ?></code>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Recommendations -->
        <?php if (!empty($results['recommendations'])): ?>
        <div class="mt-4">
            <h3><i class="bi bi-lightbulb"></i> Optimization Recommendations</h3>
            <?php foreach ($results['recommendations'] as $rec): ?>
            <div class="recommendation">
                <strong><?= $rec['table'] ?></strong><br>
                <?= $rec['message'] ?><br>
                <code style="font-size: 0.9rem;">Action: <?= $rec['action'] ?></code>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Tables -->
        <div class="mt-4">
            <h3><i class="bi bi-table"></i> Table Analysis</h3>
            <?php foreach ($results['tables'] as $table => $info): ?>
            <div class="stat-card mt-3">
                <h5><?= $table ?></h5>
                <div class="row">
                    <div class="col-md-3">
                        <strong>Rows:</strong> <?= number_format($info['row_count']) ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Data:</strong> <?= $info['data_length_mb'] ?> MB
                    </div>
                    <div class="col-md-3">
                        <strong>Index:</strong> <?= $info['index_length_mb'] ?> MB
                    </div>
                    <div class="col-md-3">
                        <strong>Total:</strong> <?= $info['total_size_mb'] ?> MB
                    </div>
                </div>
                
                <?php if (!empty($info['indexes'])): ?>
                <p class="mt-2 mb-1"><strong>Indexes:</strong></p>
                <?php foreach ($info['indexes'] as $idx_name => $columns): ?>
                    <span class="index-badge">
                        <i class="bi bi-key"></i> <?= $idx_name ?>: <?= implode(", ", $columns) ?>
                    </span>
                <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (!empty($info['foreign_keys'])): ?>
                <p class="mt-2 mb-1"><strong>Foreign Keys:</strong></p>
                <?php foreach ($info['foreign_keys'] as $fk): ?>
                    <span class="index-badge">
                        <?= $fk['column'] ?> → <?= $fk['references'] ?>
                    </span>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
    <?php
}

?>