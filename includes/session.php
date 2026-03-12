<?php
/**
 * Session Management & Authentication
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Check if current user is admin
 */
function isAdmin(): bool {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

/**
 * Require login - redirect if not authenticated
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

/**
 * Require admin role
 */
function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . 'dashboard.php');
        exit;
    }
}

/**
 * Get current user's family_id
 */
function getCurrentFamilyId(): ?int {
    return $_SESSION['family_id'] ?? null;
}

/**
 * Get current user's ID
 */
function getCurrentUserId(): int {
    return $_SESSION['user_id'];
}

/**
 * Get current user's role
 */
function getCurrentRole(): string {
    return $_SESSION['role'];
}

/**
 * Authenticate user login
 */
function authenticateUser(string $username, string $password): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT u.*, f.family_name FROM users u LEFT JOIN families f ON u.family_id = f.family_id WHERE u.username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id']     = $user['user_id'];
        $_SESSION['username']    = $user['username'];
        $_SESSION['role']        = $user['role'];
        $_SESSION['family_id']   = $user['family_id'];
        $_SESSION['full_name']   = $user['full_name'];
        $_SESSION['family_name'] = $user['family_name'];
        return $user;
    }
    return null;
}

/**
 * Logout and destroy session
 */
function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Generate CSRF token
 */
function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token (keeps token valid so multi-tab usage works)
 */
function validateCSRFToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Rotate CSRF token (call after successful state-changing action)
 */
function rotateCSRFToken(): void {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
