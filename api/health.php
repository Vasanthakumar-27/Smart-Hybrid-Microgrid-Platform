<?php
/**
 * Health Check Endpoint - System Status
 * Used for monitoring and uptime checks
 * Endpoint: /api/health.php
 * Method: GET
 * Authentication: None required (public endpoint)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

$timestamp = date(DateTime::ISO8601);
$checks = [];

// 1. Database connectivity check
try {
    $db = getDB();
    $result = $db->query('SELECT 1')->fetch();
    $checks['database'] = [
        'ok' => $result !== false,
        'message' => $result !== false ? 'Connected' : 'Query failed',
        'response_time_ms' => 0  // Would need timing instrumentation for accuracy
    ];
} catch (Exception $e) {
    $checks['database'] = [
        'ok' => false,
        'message' => 'Connection failed',
        'error' => sanitizeErrorMessage($e->getMessage())
    ];
}

// 2. Disk space check
$diskFree = disk_free_space('/');
$diskTotal = disk_total_space('/');
if ($diskFree !== false && $diskTotal !== false) {
    $percentFree = ($diskFree / $diskTotal) * 100;
    $checks['disk_space'] = [
        'ok' => $percentFree > 5,  // Alert if less than 5% free
        'free_percent' => round($percentFree, 2),
        'free_gb' => round($diskFree / (1024 * 1024 * 1024), 2),
        'message' => $percentFree > 5 ? 'OK' : 'Low disk space warning'
    ];
} else {
    $checks['disk_space'] = ['ok' => false, 'message' => 'Unable to check disk space'];
}

// 3. Memory usage
$memoryUsage = memory_get_usage(true);
$memoryLimit = ini_get('memory_limit');
$checks['memory'] = [
    'current_usage_mb' => round($memoryUsage / (1024 * 1024), 2),
    'limit' => $memoryLimit,
    'ok' => true
];

// 4. PHP version
$checks['php'] = [
    'version' => PHP_VERSION,
    'ok' => version_compare(PHP_VERSION, '7.4', '>=')
];

// 5. Session status
$sessionStatus = session_status();
$checks['session'] = [
    'ok' => true,
    'status' => $sessionStatus === PHP_SESSION_ACTIVE ? 'active' : 'inactive'
];

// 6. Configuration check
$checks['configuration'] = [
    'timezone' => ini_get('date.timezone'),
    'max_execution_time' => ini_get('max_execution_time'),
    'ok' => true
];

// Determine overall health
$overallHealth = 'healthy';
if (!$checks['database']['ok'] || !$checks['disk_space']['ok']) {
    $overallHealth = 'unhealthy';
    http_response_code(503);  // Service Unavailable
} elseif (!$checks['disk_space']['ok']) {
    $overallHealth = 'degraded';
}

$response = [
    'status' => $overallHealth,
    'timestamp' => $timestamp,
    'version' => APP_VERSION,
    'uptime_seconds' => getUptime(),
    'checks' => $checks
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

/**
 * Get server uptime (simplified)
 */
function getUptime(): int {
    // In production, would track start time; this is a fallback
    if (function_exists('shell_exec')) {
        $output = @shell_exec('uptime -s 2>/dev/null');
        if ($output) {
            return (int) strtotime($output);
        }
    }
    return 0;
}

/**
 * Sanitize error message for display
 */
function sanitizeErrorMessage(string $message): string {
    $sanitized = preg_replace('~[a-zA-Z]:\\\\[^\s]+|/[^\s]+\.php~', '[sensitive]', $message);
    $sanitized = preg_replace('~(SELECT|INSERT|UPDATE|DELETE|WHERE|FROM|JOIN|`|\').*~i', '[sensitive]', $sanitized);
    if (strpos($sanitized, 'Stack trace') !== false) {
        $sanitized = substr($sanitized, 0, strpos($sanitized, 'Stack trace'));
    }
    return trim($sanitized);
}
