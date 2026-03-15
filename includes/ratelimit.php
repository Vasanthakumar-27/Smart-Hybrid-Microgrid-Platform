<?php
/**
 * API Rate Limiting Middleware
 * Throttles requests per API key to prevent abuse and DOS
 */

/**
 * Check rate limit for API key
 * Uses simple in-memory counter with file-based persistence
 * Returns: ['allowed' => bool, 'remaining' => int, 'reset_in_seconds' => int]
 */
function checkRateLimit(string $apiKey, ?int $maxRequests = null, ?int $windowSeconds = null): array {
    $maxRequests = $maxRequests ?? getEnvInt('RATE_LIMIT_REQUESTS', 1000);
    $windowSeconds = $windowSeconds ?? getEnvInt('RATE_LIMIT_WINDOW_SECONDS', 3600);
    
    if (!getEnvBool('RATE_LIMIT_ENABLED', true)) {
        return ['allowed' => true, 'remaining' => $maxRequests, 'reset_in_seconds' => $windowSeconds];
    }

    $logDir = sys_get_temp_dir() . '/microgrid_ratelimit';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0700, true);
    }

    $filePath = $logDir . '/' . md5($apiKey) . '.json';
    $now = time();

    // Read existing counter
    $counter = ['count' => 0, 'window_start' => $now, 'hits' => []];
    if (file_exists($filePath)) {
        $data = json_decode(file_get_contents($filePath), true);
        if ($data && isset($data['window_start'])) {
            $windowAge = $now - $data['window_start'];
            if ($windowAge < $windowSeconds) {
                $counter = $data;
            }
        }
    }

    // Check if within window
    $windowAge = $now - $counter['window_start'];
    if ($windowAge >= $windowSeconds) {
        // New window
        $counter['count'] = 1;
        $counter['window_start'] = $now;
        $counter['hits'] = [$now];
        $remaining = $maxRequests - 1;
        $resetIn = $windowSeconds;
        $allowed = true;
    } else {
        // Within current window
        $counter['count']++;
        $counter['hits'][] = $now;
        $remaining = max(0, $maxRequests - $counter['count']);
        $resetIn = $windowSeconds - $windowAge;
        $allowed = $counter['count'] <= $maxRequests;
    }

    // Persist counter (atomic)
    file_put_contents($filePath, json_encode($counter, JSON_PRETTY_PRINT), LOCK_EX);

    return [
        'allowed' => $allowed,
        'remaining' => $remaining,
        'reset_in_seconds' => $resetIn,
    ];
}

/**
 * Enforce rate limit on API request
 * Outputs HTTP 429 and exits if rate limit exceeded
 */
function enforceRateLimit(string $apiKey, ?int $maxRequests = null, ?int $windowSeconds = null): void {
    $check = checkRateLimit($apiKey, $maxRequests, $windowSeconds);

    // Add rate limit headers
    header('X-RateLimit-Limit: ' . ($maxRequests ?? getEnvInt('RATE_LIMIT_REQUESTS', 1000)));
    header('X-RateLimit-Remaining: ' . $check['remaining']);
    header('X-RateLimit-Reset: ' . (time() + $check['reset_in_seconds']));

    if (!$check['allowed']) {
        http_response_code(429);
        header('Retry-After: ' . $check['reset_in_seconds']);
        echo json_encode([
            'error' => 'Rate limit exceeded',
            'retry_after_seconds' => $check['reset_in_seconds'],
            'message' => 'Too many requests. Please try again later.'
        ]);
        exit;
    }
}

/**
 * Clean up old rate limit files (call periodically)
 */
function cleanupRateLimitFiles(int $maxAgeSeconds = 86400): void {
    $logDir = sys_get_temp_dir() . '/microgrid_ratelimit';
    if (!is_dir($logDir)) {
        return;
    }

    $now = time();
    $files = glob($logDir . '/*.json');
    foreach ($files as $file) {
        if ($now - filemtime($file) > $maxAgeSeconds) {
            unlink($file);
        }
    }
}
