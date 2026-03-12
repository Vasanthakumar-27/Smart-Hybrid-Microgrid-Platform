<?php
/**
 * Internal API - Alerts Management (AJAX)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

if ($method === 'GET') {
    $familyId = isAdmin() && isset($_GET['family_id']) ? (int) $_GET['family_id'] : getCurrentFamilyId();
    $status = $_GET['status'] ?? null;
    $limit = min((int) ($_GET['limit'] ?? 50), 200);

    if (isAdmin() && empty($_GET['family_id'])) {
        // Admin sees all alerts
        $sql = "SELECT a.*, f.family_name, m.microgrid_name FROM alerts a
                JOIN families f ON a.family_id = f.family_id
                LEFT JOIN microgrids m ON a.microgrid_id = m.microgrid_id";
        $params = [];
        if ($status) {
            $sql .= " WHERE a.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY a.timestamp DESC LIMIT ?";
        $params[] = $limit;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    } else {
        $sql = "SELECT a.*, m.microgrid_name FROM alerts a
                LEFT JOIN microgrids m ON a.microgrid_id = m.microgrid_id
                WHERE a.family_id = ?";
        $params = [$familyId];
        if ($status) {
            $sql .= " AND a.status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY a.timestamp DESC LIMIT ?";
        $params[] = $limit;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }

    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);

} elseif ($method === 'POST') {
    // Validate CSRF token from header
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCSRFToken($csrfHeader)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'acknowledge' || $action === 'resolve') {
        $alertId = (int) ($input['alert_id'] ?? 0);
        if (!$alertId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing alert_id']);
            exit;
        }

        // Verify ownership
        $stmt = $db->prepare("SELECT family_id FROM alerts WHERE alert_id = ?");
        $stmt->execute([$alertId]);
        $alert = $stmt->fetch();

        if (!$alert) {
            http_response_code(404);
            echo json_encode(['error' => 'Alert not found']);
            exit;
        }

        if (!isAdmin() && $alert['family_id'] != getCurrentFamilyId()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }

        $newStatus = $action === 'acknowledge' ? 'acknowledged' : 'resolved';
        $resolvedAt = $action === 'resolve' ? date('Y-m-d H:i:s') : null;

        $stmt = $db->prepare("UPDATE alerts SET status = ?, resolved_at = COALESCE(?, resolved_at) WHERE alert_id = ?");
        $stmt->execute([$newStatus, $resolvedAt, $alertId]);

        echo json_encode(['success' => true, 'message' => "Alert {$action}d"]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
