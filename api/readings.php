<?php
/**
 * IoT API - Energy Readings Endpoint
 * Receives data from microgrid sensors via HTTP POST
 *
 * POST /api/readings.php
 * Headers: X-API-Key: <api_key>
 * Body (JSON): { microgrid_id, voltage, current_amp, power_kw, energy_kwh, temperature }
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

// Authenticate API request
function authenticateAPI(): ?int {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? null);
    if (!$apiKey) return null;

    $db = getDB();
    $stmt = $db->prepare("SELECT family_id FROM api_keys WHERE api_key = ? AND is_active = 1");
    $stmt->execute([$apiKey]);
    $result = $stmt->fetch();
    return $result ? (int) $result['family_id'] : null;
}

$familyId = authenticateAPI();
if (!$familyId) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or missing API key']);
    exit;
}

if ($method === 'POST') {
    // Insert new energy reading
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['microgrid_id'], $input['voltage'], $input['current_amp'], $input['power_kw'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields: microgrid_id, voltage, current_amp, power_kw']);
        exit;
    }

    $db = getDB();

    // Verify microgrid belongs to this family
    $stmt = $db->prepare("SELECT microgrid_id FROM microgrids WHERE microgrid_id = ? AND family_id = ?");
    $stmt->execute([(int)$input['microgrid_id'], $familyId]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Microgrid does not belong to your family']);
        exit;
    }

    $readingData = [
        'voltage'     => (float) $input['voltage'],
        'current_amp' => (float) $input['current_amp'],
        'power_kw'    => (float) $input['power_kw'],
        'energy_kwh'  => (float) ($input['energy_kwh'] ?? 0),
        'temperature' => isset($input['temperature']) ? (float) $input['temperature'] : null,
    ];

    $stmt = $db->prepare("INSERT INTO energy_readings (microgrid_id, voltage, current_amp, power_kw, energy_kwh, temperature, timestamp) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        (int) $input['microgrid_id'],
        $readingData['voltage'],
        $readingData['current_amp'],
        $readingData['power_kw'],
        $readingData['energy_kwh'],
        $readingData['temperature'],
    ]);

    // Check for fault alerts
    checkAndGenerateAlerts((int)$input['microgrid_id'], $readingData);

    echo json_encode([
        'success' => true,
        'reading_id' => $db->lastInsertId(),
        'message' => 'Reading recorded successfully'
    ]);

} elseif ($method === 'GET') {
    // Get recent readings for a microgrid
    $microgridId = isset($_GET['microgrid_id']) ? (int) $_GET['microgrid_id'] : null;
    $limit = min((int) ($_GET['limit'] ?? 50), 500);

    $db = getDB();

    if ($microgridId) {
        // Verify ownership
        $stmt = $db->prepare("SELECT microgrid_id FROM microgrids WHERE microgrid_id = ? AND family_id = ?");
        $stmt->execute([$microgridId, $familyId]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM energy_readings WHERE microgrid_id = ? ORDER BY timestamp DESC LIMIT ?");
        $stmt->execute([$microgridId, $limit]);
    } else {
        $stmt = $db->prepare("SELECT er.* FROM energy_readings er JOIN microgrids m ON er.microgrid_id = m.microgrid_id WHERE m.family_id = ? ORDER BY er.timestamp DESC LIMIT ?");
        $stmt->execute([$familyId, $limit]);
    }

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
