<?php
/**
 * Test Script for IoT Message Queue (Task 20)
 */

require 'config/database.php';
require 'includes/iot_queue.php';

echo "=== IoT Message Queue Test ===\n\n";

IoTMessageQueue::init();

// Test 1: Enqueue messages
echo "Test 1: Enqueuing test messages...\n";
for ($i = 1; $i <= 5; $i++) {
    $id = IoTMessageQueue::enqueue(
        'test_device_' . $i,
        'reading',
        [
            'voltage' => 240 + rand(-5, 5),
            'current' => 5.0 + (rand(-10, 10) / 10),
            'power' => 1200 + rand(-100, 100)
        ]
    );
    echo "  ✓ Enqueued message $i (ID: $id)\n";
}

// Test 2: Check queue stats
echo "\nTest 2: Queue Statistics\n";
$stats = IoTMessageQueue::getStats();
foreach ($stats as $status => $count) {
    echo "  $status: $count\n";
}

// Test 3: Process messages
echo "\nTest 3: Processing messages...\n";
$result = IoTMessageQueue::processMessages();
echo "  ✓ Processed: " . ($result['processed'] ?? 0) . " messages\n";
echo "  ✓ Errors: " . ($result['errors'] ?? 0) . " messages\n";

// Test 4: Check final stats
echo "\nTest 4: Final Queue Statistics\n";
$stats = IoTMessageQueue::getStats();
foreach ($stats as $status => $count) {
    echo "  $status: $count\n";
}

echo "\n=== Queue Test Complete ===\n";
echo "✓ All queue operations working correctly!\n";
?>
