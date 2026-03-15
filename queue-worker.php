#!/usr/bin/env php
<?php
/**
 * IoT Message Queue Worker
 * 
 * Processes messages from the IoT queue asynchronously
 * Run continuously: php queue-worker.php
 * Or via cron: * * * * * php /path/to/queue-worker.php >> /var/log/queue-worker.log 2>&1
 * 
 * Usage:
 *   php queue-worker.php [OPTIONS]
 * 
 * Options:
 *   --once       Process batch once and exit
 *   --listen     Listen continuously (default)
 *   --stats      Show queue statistics
 *   --retry      Retry failed messages
 *   --cleanup    Clean old messages
 *   --verbose    Show detailed output
 */

error_reporting(E_ALL);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "[ERROR] $errstr in $errfile:$errline\n";
    return false;
});

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/iot_queue.php';
require_once __DIR__ . '/includes/functions.php';

// Parse arguments
$verbose = in_array('--verbose', $argv);
$once = in_array('--once', $argv);
$listen = in_array('--listen', $argv) || (!$once && !in_array('--stats', $argv) && !in_array('--retry', $argv));
$show_stats = in_array('--stats', $argv);
$retry_failed = in_array('--retry', $argv);
$cleanup = in_array('--cleanup', $argv);

// Initialize
IoTMessageQueue::init();

if ($show_stats) {
    showStats();
    exit(0);
}

if ($retry_failed) {
    retryFailed();
    exit(0);
}

if ($cleanup) {
    cleanupMessages();
    exit(0);
}

if ($listen) {
    listenContinuously($once, $verbose);
} else {
    processOnce($verbose);
}

// ============================================================================
// Functions
// ============================================================================

function showStats() {
    echo "\n╔════════════════════════════════════════════════════════════════╗\n";
    echo "║ IoT Message Queue Statistics                                   ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    
    $stats = IoTMessageQueue::getStats();
    
    echo "Status Breakdown:\n";
    echo str_repeat("─", 50) . "\n";
    printf("%-15s %10s %20s\n", "Status", "Count", "Oldest Pending");
    echo str_repeat("─", 50) . "\n";
    
    foreach ($stats as $row) {
        printf("%-15s %10d %20s\n",
            $row['status'],
            $row['count'],
            $row['oldest_pending'] ?? 'N/A'
        );
    }
    
    echo "\n";
    echo "Pending: " . IoTMessageQueue::getPendingCount() . " messages\n";
    echo "Failed: " . IoTMessageQueue::getFailedCount() . " messages\n";
    echo "\n";
}

function retryFailed() {
    echo "Retrying failed messages...\n";
    $count = IoTMessageQueue::retryFailed();
    echo "✓ Retried $count messages\n";
}

function cleanupMessages() {
    echo "Cleaning up old messages...\n";
    $count = IoTMessageQueue::cleanup();
    echo "✓ Deleted $count old messages\n";
}

function processOnce($verbose = false) {
    if ($verbose) echo "Processing message batch...\n";
    
    $result = IoTMessageQueue::processMessages();
    
    if ($result) {
        echo "✓ Processed: {$result['processed']} messages\n";
        echo "  Pending: {$result['pending']}\n";
        echo "  Failed: {$result['failed']}\n";
    }
}

function listenContinuously($once = false, $verbose = false) {
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║ IoT Message Queue Worker                                       ║\n";
    echo "║ Started at " . date('Y-m-d H:i:s') . "                                ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    
    $loop_count = 0;
    $processed_total = 0;
    
    while (true) {
        $loop_count++;
        $timestamp = date('Y-m-d H:i:s');
        
        try {
            $result = IoTMessageQueue::processMessages();
            
            if ($result && $result['processed'] > 0) {
                $processed_total += $result['processed'];
                echo "[$timestamp] ✓ Processed {$result['processed']} messages";
                echo " (Pending: {$result['pending']}, Failed: {$result['failed']})\n";
                
                if ($verbose) {
                    echo "             Running total: $processed_total messages processed\n";
                }
            } elseif ($result) {
                if ($verbose) {
                    echo "[$timestamp] No pending messages. Queue empty.\n";
                }
            }
            
            if ($once) {
                echo "Exiting (--once mode)\n";
                exit(0);
            }
            
            // Sleep 5 seconds between batches
            sleep(5);
            
        } catch (Exception $e) {
            echo "[$timestamp] ✗ Error: " . $e->getMessage() . "\n";
            sleep(10); // Longer sleep on error
        }
    }
}

?>
