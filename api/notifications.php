<?php
/**
 * Email Notification Management API
 * Allows users to manage their email notification preferences
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/logger.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();
$userId = getCurrentUserID();
$logger = new Logger('api/notifications');

if ($method === 'GET') {
    // Get current notification preferences
    $stmt = $db->prepare("SELECT 
        email,
        email_notifications_enabled,
        email_notify_critical,
        email_notify_warning,
        email_notify_info,
        email_digest_frequency,
        email_preferences_updated_at
    FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $prefs = $stmt->fetch();

    if (!$prefs) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'preferences' => [
            'email' => $prefs['email'],
            'notifications_enabled' => (bool) $prefs['email_notifications_enabled'],
            'notify_critical' => (bool) $prefs['email_notify_critical'],
            'notify_warning' => (bool) $prefs['email_notify_warning'],
            'notify_info' => (bool) $prefs['email_notify_info'],
            'digest_frequency' => $prefs['email_digest_frequency'],
            'last_updated' => $prefs['email_preferences_updated_at'],
            'mail_enabled' => Mailer::isEnabled(),
        ]
    ]);

} elseif ($method === 'POST') {
    // Validate CSRF token
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCSRFToken($csrfHeader)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'update-preferences') {
        // Validate input
        $enabled = isset($input['enabled']) ? (int)(bool)$input['enabled'] : null;
        $notifyCritical = isset($input['notify_critical']) ? (int)(bool)$input['notify_critical'] : null;
        $notifyWarning = isset($input['notify_warning']) ? (int)(bool)$input['notify_warning'] : null;
        $notifyInfo = isset($input['notify_info']) ? (int)(bool)$input['notify_info'] : null;
        $digestFreq = isset($input['digest_frequency']) ? (string)$input['digest_frequency'] : null;

        // Validate digest frequency
        if ($digestFreq && !in_array($digestFreq, ['immediate', 'daily', 'weekly'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid digest_frequency. Must be immediate, daily, or weekly']);
            exit;
        }

        // Build update query
        $updates = [];
        $params = [];

        if ($enabled !== null) {
            $updates[] = "email_notifications_enabled = ?";
            $params[] = $enabled;
        }
        if ($notifyCritical !== null) {
            $updates[] = "email_notify_critical = ?";
            $params[] = $notifyCritical;
        }
        if ($notifyWarning !== null) {
            $updates[] = "email_notify_warning = ?";
            $params[] = $notifyWarning;
        }
        if ($notifyInfo !== null) {
            $updates[] = "email_notify_info = ?";
            $params[] = $notifyInfo;
        }
        if ($digestFreq !== null) {
            $updates[] = "email_digest_frequency = ?";
            $params[] = $digestFreq;
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['error' => 'No preferences to update']);
            exit;
        }

        // Add userId to params and execute
        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE user_id = ?";
        $stmt = $db->prepare($sql);
        $success = $stmt->execute($params);

        if ($success) {
            $logger->info('User preferences updated', [
                'user_id' => $userId,
                'changes' => array_keys(array_flip(array_keys($input))),
            ]);

            // Refetch and return updated preferences
            $stmt = $db->prepare("SELECT 
                email_notifications_enabled,
                email_notify_critical,
                email_notify_warning,
                email_notify_info,
                email_digest_frequency
            FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $updated = $stmt->fetch();

            echo json_encode([
                'success' => true,
                'message' => 'Preferences updated successfully',
                'preferences' => [
                    'notifications_enabled' => (bool) $updated['email_notifications_enabled'],
                    'notify_critical' => (bool) $updated['email_notify_critical'],
                    'notify_warning' => (bool) $updated['email_notify_warning'],
                    'notify_info' => (bool) $updated['email_notify_info'],
                    'digest_frequency' => $updated['email_digest_frequency'],
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update preferences']);
        }

    } elseif ($action === 'send-test') {
        // Send a test email to verify configuration
        if (!Mailer::isEnabled()) {
            http_response_code(503);
            echo json_encode(['error' => 'Email notifications are not enabled on this system']);
            exit;
        }

        // Get user email
        $stmt = $db->prepare("SELECT email, name FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !$user['email']) {
            http_response_code(400);
            echo json_encode(['error' => 'No email address on file']);
            exit;
        }

        // Send test email
        $success = Mailer::sendTest($user['email']);

        if ($success) {
            $logger->info('Test email sent', ['user_id' => $userId, 'to' => $user['email']]);
            echo json_encode([
                'success' => true,
                'message' => "Test email sent to {$user['email']}. Check your inbox (and spam folder) in a few moments."
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to send test email. Check system logs for details.']);
        }

    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
