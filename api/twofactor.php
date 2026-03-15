<?php
/**
 * MicroGrid Pro - Two-Factor Authentication API
 * 
 * Endpoints for enabling, disabling, and verifying 2FA
 * All endpoints require authentication (except verify-login which requires 2FA challenge)
 */

require_once dirname(dirname(__FILE__)) . '/config/database.php';
require_once dirname(dirname(__FILE__)) . '/includes/twofactor.php';
require_once dirname(dirname(__FILE__)) . '/includes/validation.php';

header('Content-Type: application/json');

// Check request method
$method = strtoupper($_SERVER['REQUEST_METHOD']);
$action = $_GET['action'] ?? '';

// Rate limiting
enforceRateLimit($_SERVER['HTTP_X_API_KEY'] ?? 'web', 30, 60); // 30 requests per minute

try {
    // For session-based auth (web interface) or API key auth
    if (!isset($_SESSION['user_id']) && empty($_SERVER['HTTP_X_API_KEY'])) {
        throw new Exception('Unauthorized', 401);
    }

    $userId = $_SESSION['user_id'] ?? null;

    switch ($action) {
        case 'enable':
            handleEnable2FA($userId);
            break;

        case 'verify':
            handleVerify2FA($userId);
            break;

        case 'disable':
            handleDisable2FA($userId);
            break;

        case 'backup-codes':
            handleGetBackupCodes($userId);
            break;

        case 'verify-login':
            handleVerifyLogin($_POST['user_id'] ?? null, $_POST['code'] ?? '');
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            exit;
    }

} catch (Exception $e) {
    Logger::error('2FA API error: ' . sanitizeErrorMessage($e->getMessage()));
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'error' => 'Authentication error',
        'message' => sanitizeErrorMessage($e->getMessage())
    ]);
    exit;
}

/**
 * Step 1: Enable 2FA - Generate new secret and return QR code URL
 */
function handleEnable2FA(?int $userId): void
{
    if (!$userId) {
        throw new Exception('User not authenticated', 401);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    // Get user email
    $db = getDB();
    $stmt = $db->prepare('SELECT email FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception('User not found', 404);
    }

    // Generate new secret
    $secret = TwoFactorAuth::generateSecret();

    // Generate QR code URL
    $qrUrl = TwoFactorAuth::getQRCodeUrl($secret, $user['email']);

    // Store temporary secret in session (not yet confirmed)
    $_SESSION['pending_2fa_secret'] = $secret;
    $_SESSION['pending_2fa_time'] = time();

    // Generate backup codes ahead of time
    $backupCodes = TwoFactorAuth::generateBackupCodes();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Scan QR code with authenticator app',
        'qr_url' => $qrUrl,
        'secret' => $secret,
        'backup_codes_preview' => [
            'count' => count($backupCodes),
            'example' => $backupCodes[0]
        ]
    ]);
}

/**
 * Step 2: Verify 2FA - User provides code to confirm they set it up correctly
 */
function handleVerify2FA(?int $userId): void
{
    if (!$userId) {
        throw new Exception('User not authenticated', 401);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    $code = validateString($_POST['code'] ?? '', 6, 6);
    if (!$code) {
        throw new Exception('Invalid code format', 400);
    }

    // Get pending secret from session
    $secret = $_SESSION['pending_2fa_secret'] ?? null;
    if (!$secret) {
        throw new Exception('No pending 2FA setup', 400);
    }

    // Check if pending secret is not too old (5 minutes)
    $pendingTime = $_SESSION['pending_2fa_time'] ?? 0;
    if (time() - $pendingTime > 300) {
        unset($_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_time']);
        throw new Exception('2FA setup expired, please try again', 400);
    }

    // Verify the code
    if (!TwoFactorAuth::verifyToken($secret, $code)) {
        throw new Exception('Invalid code', 400);
    }

    // Generate backup codes
    $backupCodes = TwoFactorAuth::generateBackupCodes();
    $hashedCodes = TwoFactorAuth::hashBackupCodes($backupCodes);

    // Save to database
    $db = getDB();
    $stmt = $db->prepare(
        'UPDATE users SET two_fa_enabled = 1, two_fa_secret = ?, two_fa_backup_codes = ?, two_fa_enabled_at = NOW() WHERE id = ?'
    );
    $stmt->execute([$secret, $hashedCodes, $userId]);

    // Clear session data
    unset($_SESSION['pending_2fa_secret'], $_SESSION['pending_2fa_time']);

    Logger::info("2FA enabled for user $userId");

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => '2FA setup complete',
        'backup_codes' => $backupCodes
    ]);
}

/**
 * Disable 2FA - User provides current password to prevent unauthorized disabling
 */
function handleDisable2FA(?int $userId): void
{
    if (!$userId) {
        throw new Exception('User not authenticated', 401);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    $password = $_POST['password'] ?? '';
    if (!$password) {
        throw new Exception('Password required', 400);
    }

    // Verify password
    $db = getDB();
    $stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        throw new Exception('Invalid password', 401);
    }

    // Disable 2FA
    $stmt = $db->prepare(
        'UPDATE users SET two_fa_enabled = 0, two_fa_secret = NULL, two_fa_backup_codes = NULL WHERE id = ?'
    );
    $stmt->execute([$userId]);

    Logger::warning("2FA disabled for user $userId");

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => '2FA has been disabled'
    ]);
}

/**
 * Get new backup codes - User provides current password and 2FA code
 */
function handleGetBackupCodes(?int $userId): void
{
    if (!$userId) {
        throw new Exception('User not authenticated', 401);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    $code = validateString($_POST['code'] ?? '', 6, 6);
    if (!$code) {
        throw new Exception('Invalid code format', 400);
    }

    // Get user's 2FA info
    $db = getDB();
    $stmt = $db->prepare('SELECT two_fa_secret, two_fa_enabled FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !$user['two_fa_enabled']) {
        throw new Exception('2FA not enabled', 400);
    }

    // Verify the code
    if (!TwoFactorAuth::verifyToken($user['two_fa_secret'], $code)) {
        throw new Exception('Invalid code', 400);
    }

    // Generate new backup codes
    $backupCodes = TwoFactorAuth::generateBackupCodes();
    $hashedCodes = TwoFactorAuth::hashBackupCodes($backupCodes);

    // Update database
    $stmt = $db->prepare('UPDATE users SET two_fa_backup_codes = ? WHERE id = ?');
    $stmt->execute([$hashedCodes, $userId]);

    Logger::info("New backup codes generated for user $userId");

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'New backup codes generated',
        'backup_codes' => $backupCodes
    ]);
}

/**
 * Verify login - Called after initial password authentication
 * Validates the 2FA code or backup code
 */
function handleVerifyLogin(?int $userId, string $code): void
{
    if (!$userId) {
        throw new Exception('User not found', 404);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    // Get user's 2FA info
    $db = getDB();
    $stmt = $db->prepare('SELECT two_fa_secret, two_fa_backup_codes, two_fa_enabled FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception('User not found', 404);
    }

    // If 2FA not enabled, this endpoint shouldn't be called
    if (!$user['two_fa_enabled']) {
        throw new Exception('2FA not enabled for this user', 400);
    }

    // Try to verify as TOTP code (6 digits)
    $isValidCode = false;
    if (preg_match('/^\d{6}$/', $code)) {
        $isValidCode = TwoFactorAuth::verifyToken($user['two_fa_secret'], $code);
    }

    // If not valid TOTP, try to verify as backup code (8 alphanumeric)
    $isValidBackupCode = false;
    if (!$isValidCode && preg_match('/^[0-9A-Z]{8}$/', $code)) {
        $codes = json_decode($user['two_fa_backup_codes'], true) ?? [];
        if (TwoFactorAuth::verifyBackupCode($code, $codes)) {
            $isValidBackupCode = true;

            // Consume the backup code
            $newCodes = TwoFactorAuth::consumeBackupCode($code, $user['two_fa_backup_codes']);
            if ($newCodes) {
                $stmt = $db->prepare('UPDATE users SET two_fa_backup_codes = ? WHERE id = ?');
                $stmt->execute([$newCodes, $userId]);
                Logger::info("Backup code used by user $userId");
            }
        }
    }

    if (!$isValidCode && !$isValidBackupCode) {
        Logger::warning("Failed 2FA verification for user $userId from IP " . $_SERVER['REMOTE_ADDR']);
        throw new Exception('Invalid authentication code', 401);
    }

    // Code is valid - session is already authenticated, just confirming 2FA
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => '2FA verification successful',
        'code_type' => $isValidCode ? 'totp' : 'backup'
    ]);
}
