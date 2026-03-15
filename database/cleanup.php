#!/usr/bin/env php
<?php
/**
 * Data Retention Cleanup Script (CLI)
 * 
 * Run manually via command line:
 *   php database/cleanup.php
 *   php database/cleanup.php --dry-run (preview without deleting)
 *   php database/cleanup.php --stats (show current statistics)
 * 
 * Schedule via cron (Linux/Mac):
 *   0 2 * * * /usr/bin/php /var/www/html/microgrid/database/cleanup.php >> /var/log/microgrid-cleanup.log 2>&1
 * 
 * Schedule via Task Scheduler (Windows):
 *   Create task to run: php "C:\xampp\htdocs\microgrid\database\cleanup.php"
 */

// Switch to script directory
chdir(__DIR__);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/retention.php';

// Parse command-line arguments
$dryRun = in_array('--dry-run', $argv, true);
$statsOnly = in_array('--stats', $argv, true);
$verbose = in_array('--verbose', $argv, true);

// Print header
echo "\n";
echo "╔════════════════════════════════════════════════════╗\n";
echo "║        MicroGrid Pro - Data Retention Cleanup      ║\n";
echo "║                 " . date('Y-m-d H:i:s') . "                ║\n";
echo "╚════════════════════════════════════════════════════╝\n";
echo "\n";

if ($statsOnly) {
    // Show statistics only
    echo "📊 Current Data Retention Statistics\n";
    echo "──────────────────────────────────────────────────\n\n";

    DataRetention::init(false);
    $stats = DataRetention::getStats();

    echo "Alerts:\n";
    printf("  Total:         %s\n", number_format($stats['alerts']['total']));
    printf("  Active:        %s\n", number_format($stats['alerts']['active']));
    printf("  Old (resolved): %s\n\n", number_format($stats['alerts']['old_resolved']));

    echo "Energy Readings:\n";
    printf("  Total:         %s\n", number_format($stats['energy_readings']['total']));
    printf("  Older than 365 days: %s\n\n", number_format($stats['energy_readings']['old']));

    echo "Battery Status:\n";
    printf("  Total:         %s\n", number_format($stats['battery_status']['total']));
    printf("  Older than 90 days: %s\n\n", number_format($stats['battery_status']['old']));

    echo "System Logs:\n";
    printf("  Total:         %s\n", number_format($stats['system_logs']['total']));
    printf("  Older than 30 days: %s\n\n", number_format($stats['system_logs']['old']));

    echo "For more details, check: logs/app.log\n";
    echo "\n";
    exit(0);
}

// Run or preview cleanup
if ($dryRun) {
    echo "⚠️  DRY RUN MODE - No data will be deleted\n";
    echo "This will show what data would be removed.\n";
    echo "\n";
}

echo "🗑️  Running data retention cleanup...\n";
echo "──────────────────────────────────────────────────\n\n";

try {
    $results = DataRetention::runAll();

    if (!$results['success']) {
        echo "❌ Cleanup failed: " . ($results['message'] ?? 'Unknown error') . "\n";
        exit(1);
    }

    // Display results
    echo "📋 Cleanup Results\n";
    echo "─────────────────────────────────────\n\n";

    $policyHeaders = [
        'resolved_alerts'       => 'Resolved Alerts',
        'old_readings'          => 'Old Energy Readings',
        'old_battery_status'    => 'Old Battery Status',
        'system_logs'           => 'System Logs',
        'email_logs'            => 'Email Logs',
        'notification_logs'     => 'Notification Queue',
        'queue_failures'        => 'Failed Queue Entries',
    ];

    foreach ($results['policies'] as $key => $policy) {
        $name = $policyHeaders[$key] ?? ucfirst(str_replace('_', ' ', $key));
        $emoji = $policy['deleted'] > 0 ? '✓' : '─';
        printf("%s %-25s %7s rows\n", $emoji, $name . ":", number_format($policy['deleted']));

        if ($verbose && isset($policy['reason'])) {
            echo "    " . $policy['reason'] . "\n";
        }
    }

    echo "\n─────────────────────────────────────\n";
    printf("✓ Total Rows Deleted: %s\n", number_format($results['total_deleted']));

    if ($results['dry_run']) {
        echo "⚠️  DRY RUN - No data was actually deleted\n";
    } else {
        echo "✓ Cleanup completed successfully\n";
    }

    echo "\nLog file: logs/app.log\n";
    echo "\n";

    // Return appropriate exit code
    exit($results['success'] ? 0 : 1);

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
