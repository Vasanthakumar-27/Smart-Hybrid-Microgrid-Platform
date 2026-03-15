<?php
/**
 * IoT API - Battery Status Endpoint
 * POST /api/battery.php — Insert battery status from IoT device
 * GET  /api/battery.php — Get battery status
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/ratelimit.php';
require_once __DIR__ . '/../includes/iot_queue.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Authenticate
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? null);
if (!$apiKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Missing API key']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT family_id FROM api_keys WHERE api_key = ? AND is_active = 1");
$stmt->execute([$apiKey]);
$keyRow = $stmt->fetch();
if (!$keyRow) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid API key']);
    exit;
}
$familyId = (int) $keyRow['family_id'];

// Enforce rate limiting
enforceRateLimit($apiKey);

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON payload']);
        exit;
    }

    // Validate input
    $validation = validateBatteryStatus($input);
    if (!$validation['valid']) {
        http_response_code(400);
        echo json_encode(['error' => 'Validation failed', 'details' => $validation['errors']]);
        exit;
    }

    $data = $validation['data'];

    try {
        // Enqueue message for async processing
        IoTMessageQueue::init();
        $msgId = IoTMessageQueue::enqueue(
            'battery_' . $familyId,
            'battery',
            $data
        );

        // Also process synchronously for backward compatibility
        $stmt = $db->prepare("INSERT INTO battery_status (family_id, battery_name, capacity_kwh, battery_level, voltage, remaining_kwh, charge_status, temperature, timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $familyId,
            $data['battery_name'],
            $data['capacity_kwh'],
            $data['battery_level'],
            $data['voltage'],
            $data['remaining_kwh'],
            $data['charge_status'],
            $data['temperature'],
        ]);

        // Check battery alerts
        checkBatteryAlerts($familyId, $data);

        echo json_encode([
            'success' => true,
            'battery_id' => $db->lastInsertId(),
            'message' => 'Battery status recorded'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to record battery status', 'detail' => sanitizeErrorMessage($e->getMessage())]);
        exit;
    }

} elseif ($method === 'GET') {
    $limit = min((int) ($_GET['limit'] ?? 50), 500);
    $stmt = $db->prepare("SELECT * FROM battery_status WHERE family_id = ? ORDER BY timestamp DESC LIMIT ?");
    $stmt->execute([$familyId, $limit]);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
