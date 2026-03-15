<?php
/**
 * Session Management API
 * Handles session extension and timeout status checks
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Check CSRF token for unsafe methods
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die(json_encode(['error' => 'CSRF token invalid']));
    }
}

// Only logged-in users can call this API
if (!isLoggedIn()) {
    http_response_code(401);
    die(json_encode(['error' => 'Not authenticated']));
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'check-timeout':
        checkTimeout();
        break;

    case 'extend':
        extendSession();
        break;

    case 'info':
        getSessionInfo();
        break;

    default:
        http_response_code(400);
        die(json_encode(['error' => 'Invalid action']));
}

/**
 * Check current timeout status
 */
function checkTimeout() {
    $validation = SessionTimeout::validate();
    
    $response = [
        'valid' => $validation['valid'],
        'reason' => $validation['reason'],
        'expires_in' => $validation['expires_in'],
        'show_warning' => false,
        'seconds_remaining' => null
    ];

    // Check if in warning period
    if ($validation['valid']) {
        $warning = SessionTimeout::getTimeoutWarning();
        if ($warning['show_warning']) {
            $response['show_warning'] = true;
            $response['seconds_remaining'] = $warning['seconds_remaining'];
        }
    }

    echo json_encode($response);
}

/**
 * Extend session (reset idle timeout)
 */
function extendSession() {
    $result = SessionTimeout::extend();
    
    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Session extended' : 'Failed to extend session',
        'new_expiry' => $_SESSION['session_last_activity'] ?? time()
    ]);
}

/**
 * Get session debug info (admin only)
 */
function getSessionInfo() {
    if (!isAdmin()) {
        http_response_code(403);
        die(json_encode(['error' => 'Admin access required']));
    }

    $info = SessionTimeout::getSessionInfo();
    echo json_encode($info);
}

?>