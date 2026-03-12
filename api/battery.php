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

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['battery_level'], $input['voltage'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields: battery_level, voltage']);
        exit;
    }

    $batteryData = [
        'battery_level' => (float) $input['battery_level'],
        'voltage'       => (float) $input['voltage'],
        'remaining_kwh' => (float) ($input['remaining_kwh'] ?? 0),
        'charge_status' => $input['charge_status'] ?? 'idle',
        'temperature'   => isset($input['temperature']) ? (float) $input['temperature'] : null,
        'battery_name'  => $input['battery_name'] ?? 'Main Battery',
        'capacity_kwh'  => (float) ($input['capacity_kwh'] ?? 10.00),
    ];

    // Validate charge_status
    $validStatuses = ['charging', 'discharging', 'idle', 'full'];
    if (!in_array($batteryData['charge_status'], $validStatuses)) {
        $batteryData['charge_status'] = 'idle';
    }

    $stmt = $db->prepare("INSERT INTO battery_status (family_id, battery_name, capacity_kwh, battery_level, voltage, remaining_kwh, charge_status, temperature, timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        $familyId,
        $batteryData['battery_name'],
        $batteryData['capacity_kwh'],
        $batteryData['battery_level'],
        $batteryData['voltage'],
        $batteryData['remaining_kwh'],
        $batteryData['charge_status'],
        $batteryData['temperature'],
    ]);

    // Check battery alerts
    checkBatteryAlerts($familyId, $batteryData);

    echo json_encode([
        'success' => true,
        'battery_id' => $db->lastInsertId(),
        'message' => 'Battery status recorded'
    ]);

} elseif ($method === 'GET') {
    $limit = min((int) ($_GET['limit'] ?? 50), 500);
    $stmt = $db->prepare("SELECT * FROM battery_status WHERE family_id = ? ORDER BY timestamp DESC LIMIT ?");
    $stmt->execute([$familyId, $limit]);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
