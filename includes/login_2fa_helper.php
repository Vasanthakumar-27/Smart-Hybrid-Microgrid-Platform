<?php
/**
 * MicroGrid Pro - Enhanced Login with 2FA Support
 * 
 * This shows the complete 2FA flow during login process.
 * Integrate this logic into your main login handler.
 */

require_once dirname(__FILE__) . '/config/database.php';
require_once dirname(__FILE__) . '/includes/twofactor.php';
require_once dirname(__FILE__) . '/includes/validation.php';

// This is NOT a complete login page - it's a reference implementation showing 2FA integration
// Merge the 2FA verification logic into your existing login.php or password verification

/**
 * Step 1: User submits username/password
 * (Works the same as before)
 */
function authenticate_by_password(string $username, string $password): ?array
{
    try {
        $db = getDB();
        
        // Validate inputs
        if (!validateString($username, 3, 100) || !validateString($password, 8, 255)) {
            Logger::warning("Login attempt with invalid input format");
            throw new Exception('Invalid username or password', 401);
        }

        $stmt = $db->prepare(
            'SELECT id, username, password, email, two_fa_enabled FROM users WHERE username = ? LIMIT 1'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            Logger::warning("Failed login attempt for username: $username");
            throw new Exception('Invalid username or password', 401);
        }

        Logger::info("Password authentication successful for user: $username");
        return $user;

    } catch (Exception $e) {
        Logger::error('Login error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Step 2: If user has 2FA enabled, require 2FA code before creating session
 */
function handle_2fa_requirement(array $user): bool
{
    if (!$user['two_fa_enabled']) {
        // 2FA not enabled - user can proceed to session creation
        return true;
    }

    // User has 2FA enabled - need to verify code
    // Return false to indicate 2FA challenge is needed
    $_SESSION['2fa_challenge'] = [
        'user_id' => $user['id'],
        'username' => $user['username'],
        'timestamp' => time(),
        'attempts' => 0
    ];

    // Response to client should redirect to 2FA verification page
    header('HTTP/1.1 403 Forbidden');
    http_response_code(403);
    echo json_encode([
        'error' => '2FA required',
        'message' => 'Two-factor authentication required',
        'user_id' => $user['id'],
        'needs_2fa' => true
    ]);
    exit;
}

/**
 * Step 3: User submits 2FA code (6-digit or 8-character backup code)
 */
function verify_2fa_code(string $code, int $userId): bool
{
    try {
        // Validate format
        if (!preg_match('/^[0-9A-Z]{6,8}$/', $code)) {
            throw new Exception('Invalid code format', 400);
        }

        // Check if 2FA challenge is valid
        if (empty($_SESSION['2fa_challenge']) || $_SESSION['2fa_challenge']['user_id'] !== $userId) {
            throw new Exception('No active 2FA challenge', 401);
        }

        // Check challenge timeout (5 minutes)
        if (time() - $_SESSION['2fa_challenge']['timestamp'] > 300) {
            unset($_SESSION['2fa_challenge']);
            throw new Exception('2FA challenge expired', 401);
        }

        // Limit attempts to 5
        if ($_SESSION['2fa_challenge']['attempts'] >= 5) {
            unset($_SESSION['2fa_challenge']);
            Logger::warning("Too many 2FA attempts for user $userId from IP " . $_SERVER['REMOTE_ADDR']);
            throw new Exception('Too many failed attempts. Please try again later.', 403);
        }

        // Get user's 2FA secret and backup codes
        $db = getDB();
        $stmt = $db->prepare('SELECT two_fa_secret, two_fa_backup_codes FROM users WHERE id = ? AND two_fa_enabled = 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new Exception('User or 2FA configuration not found', 404);
        }

        // Try TOTP first (6 digits)
        if (preg_match('/^\d{6}$/', $code)) {
            if (!TwoFactorAuth::verifyToken($user['two_fa_secret'], $code)) {
                $_SESSION['2fa_challenge']['attempts']++;
                throw new Exception('Invalid authentication code', 401);
            }
            Logger::info("2FA (TOTP) verified for user $userId");
            return true;
        }

        // Try backup code (8 characters)
        if (preg_match('/^[0-9A-Z]{8}$/', $code)) {
            $codes = json_decode($user['two_fa_backup_codes'], true) ?? [];
            if (!TwoFactorAuth::verifyBackupCode($code, $codes)) {
                $_SESSION['2fa_challenge']['attempts']++;
                throw new Exception('Invalid backup code', 401);
            }

            // Consume the backup code
            $newCodes = TwoFactorAuth::consumeBackupCode($code, $user['two_fa_backup_codes']);
            if ($newCodes) {
                $stmt = $db->prepare('UPDATE users SET two_fa_backup_codes = ? WHERE id = ?');
                $stmt->execute([$newCodes, $userId]);
                Logger::info("Backup code used by user $userId");
            }
            return true;
        }

        throw new Exception('Invalid code format', 400);

    } catch (Exception $e) {
        Logger::warning("2FA verification failed: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Complete login flow example:
 * 
 * 1. User submits username + password to /login.php?action=authenticate
 * 2. Backend verifies password using authenticate_by_password()
 * 3. If user has 2FA enabled, handle_2fa_requirement() sends 403 + challenge
 * 4. Frontend redirects to 2FA entry page
 * 5. User submits 6-digit code to /login.php?action=verify-2fa
 * 6. Backend verifies code using verify_2fa_code()
 * 7. If valid, create session and redirect to dashboard
 * 
 * HTML Integration Example:
 * 
 *   <!-- login.html -->
 *   <form id="loginForm">
 *       <input type="text" name="username" required>
 *       <input type="password" name="password" required>
 *       <button type="submit">Login</button>
 *   </form>
 *   
 *   <!-- 2FA verification (hidden initially) -->
 *   <div id="twoFAChallenge" style="display:none;">
 *       <p>Enter 6-digit code from authenticator app:</p>
 *       <input type="text" id="twoFACode" maxlength="6" pattern="\d{6}" required>
 *       <button type="button" onclick="verifyTwoFA()">Verify</button>
 *       <p>Or enter 8-character backup code</p>
 *   </div>
 *   
 *   <script>
 *   document.getElementById('loginForm').addEventListener('submit', async (e) => {
 *       e.preventDefault();
 *       const response = await fetch('/login.php?action=authenticate', {
 *           method: 'POST',
 *           body: new FormData(e.target)
 *       });
 *       
 *       if (response.status === 403) {
 *           const data = await response.json();
 *           if (data.needs_2fa) {
 *               document.getElementById('loginForm').style.display = 'none';
 *               document.getElementById('twoFAChallenge').style.display = 'block';
 *               sessionStorage.setItem('userId', data.user_id);
 *           }
 *       }
 *   });
 *   </script>
 */
