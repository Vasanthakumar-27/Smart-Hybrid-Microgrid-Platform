<?php
/**
 * Session Timeout & Security Management
 * 
 * Implements configurable session timeout with inactivity detection
 * Protects against session hijacking and idle session abuse
 * 
 * Configuration via .env:
 *   SESSION_TIMEOUT_MINUTES - Idle timeout in minutes (default: 30)
 *   SESSION_ABSOLUTE_TIMEOUT_MINUTES - Max session lifetime (default: 480 = 8 hours)
 *   SESSION_WARNING_BEFORE_SECONDS - Show warning N seconds before timeout (default: 300 = 5 min)
 *   SESSION_CHECK_IP - Validate IP hasn't changed (prevents hijacking)
 *   SESSION_CHECK_USER_AGENT - Validate user agent hasn't changed
 *   SESSION_COOKIE_SECURE - Only send over HTTPS
 *   SESSION_COOKIE_HTTPONLY - Prevent JavaScript access
 *   SESSION_COOKIE_SAMESITE - CSRF protection (Strict, Lax, or None)
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/logger.php';

class SessionTimeout {
    protected static $logger = null;
    protected static $config = [];
    protected static $initialized = false;

    const SESSION_DATA_KEY = '_session_security';
    const SESSION_CREATED_AT = 'created_at';
    const SESSION_LAST_ACTIVITY = 'last_activity';
    const SESSION_IP_ADDRESS = 'ip_address';
    const SESSION_USER_AGENT = 'user_agent';
    const SESSION_FINGERPRINT = 'fingerprint';

    /**
     * Initialize session security configuration
     */
    public static function init(): void {
        if (self::$initialized) {
            return;
        }

        Env::load();

        self::$config = [
            'timeout_minutes'           => Env::getInt('SESSION_TIMEOUT_MINUTES', 30),
            'absolute_timeout_minutes'  => Env::getInt('SESSION_ABSOLUTE_TIMEOUT_MINUTES', 480),
            'warning_before_seconds'    => Env::getInt('SESSION_WARNING_BEFORE_SECONDS', 300),
            'check_ip'                  => Env::getBool('SESSION_CHECK_IP', true),
            'check_user_agent'          => Env::getBool('SESSION_CHECK_USER_AGENT', false),
            'cookie_secure'             => Env::getBool('SESSION_COOKIE_SECURE', false),
            'cookie_httponly'           => Env::getBool('SESSION_COOKIE_HTTPONLY', true),
            'cookie_samesite'           => Env::get('SESSION_COOKIE_SAMESITE', 'Lax'),
        ];

        self::$logger = new Logger(__CLASS__);
        self::$initialized = true;
    }

    /**
     * Setup and secure session configuration
     * Call this early in session.php after session_start()
     */
    public static function setupSession(): void {
        self::init();

        // Configure session cookie parameters
        $cookieOptions = [
            'lifetime'  => 0,  // Session cookie (deleted when browser closes)
            'path'      => '/',
            'domain'    => $_SERVER['HTTP_HOST'] ?? '',
            'secure'    => self::$config['cookie_secure'],
            'httponly'  => self::$config['cookie_httponly'],
            'samesite'  => self::$config['cookie_samesite'],
        ];

        // Set session cookie
        session_set_cookie_params($cookieOptions);

        // Session security settings
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cache_limiter', 'nocache');

        // Initialize security tracking if not already done
        if (!isset($_SESSION[self::SESSION_DATA_KEY])) {
            self::initializeSecurityData();
        }
    }

    /**
     * Initialize session security data on first login
     */
    protected static function initializeSecurityData(): void {
        self::init();

        $_SESSION[self::SESSION_DATA_KEY] = [
            self::SESSION_CREATED_AT   => time(),
            self::SESSION_LAST_ACTIVITY => time(),
            self::SESSION_IP_ADDRESS   => self::getClientIp(),
            self::SESSION_USER_AGENT   => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            self::SESSION_FINGERPRINT  => self::generateFingerprint(),
        ];

        self::$logger->info('Session security data initialized', [
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip_address' => self::getClientIp(),
        ]);
    }

    /**
     * Check if session is valid and update last activity
     * Call this on every request to enforce timeout
     * 
     * @return array ['valid' => bool, 'reason' => string, 'expires_in' => int|null]
     */
    public static function validate(): array {
        self::init();

        // Session not started
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return ['valid' => false, 'reason' => 'Session not active'];
        }

        // Initialize if needed
        if (!isset($_SESSION[self::SESSION_DATA_KEY])) {
            self::initializeSecurityData();
            return ['valid' => true, 'reason' => 'Session initialized'];
        }

        $data = &$_SESSION[self::SESSION_DATA_KEY];
        $now = time();

        // Check IP address (prevent session hijacking)
        if (self::$config['check_ip']) {
            $currentIp = self::getClientIp();
            if ($data[self::SESSION_IP_ADDRESS] !== $currentIp) {
                self::$logger->warning('Session IP mismatch - possible hijacking attempt', [
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'original_ip' => $data[self::SESSION_IP_ADDRESS],
                    'current_ip' => $currentIp,
                ]);
                self::destroySession('IP address mismatch');
                return ['valid' => false, 'reason' => 'IP address changed - possible session hijacking'];
            }
        }

        // Check user agent (detect browser changes)
        if (self::$config['check_user_agent']) {
            $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            if ($data[self::SESSION_USER_AGENT] !== $currentUserAgent) {
                self::$logger->warning('Session user agent mismatch', [
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'original_ua' => substr($data[self::SESSION_USER_AGENT], 0, 100),
                    'current_ua' => substr($currentUserAgent, 0, 100),
                ]);
                self::destroySession('User agent changed');
                return ['valid' => false, 'reason' => 'User agent changed - please login again'];
            }
        }

        // Check idle timeout (inactivity)
        $idleMinutes = (int) floor(($now - $data[self::SESSION_LAST_ACTIVITY]) / 60);
        if ($idleMinutes >= self::$config['timeout_minutes']) {
            self::$logger->info('Session idle timeout exceeded', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'idle_minutes' => $idleMinutes,
                'timeout_minutes' => self::$config['timeout_minutes'],
            ]);
            self::destroySession('Idle timeout');
            return ['valid' => false, 'reason' => "Session expired due to inactivity ({$idleMinutes} minutes)"];
        }

        // Check absolute timeout (maximum session lifetime)
        $sessionHours = (int) floor(($now - $data[self::SESSION_CREATED_AT]) / 3600);
        $absoluteTimeoutHours = (int) floor(self::$config['absolute_timeout_minutes'] / 60);
        if ($sessionHours >= $absoluteTimeoutHours) {
            self::$logger->info('Session absolute timeout exceeded', [
                'user_id' => $_SESSION['user_id'] ?? null,
                'session_hours' => $sessionHours,
                'timeout_hours' => $absoluteTimeoutHours,
            ]);
            self::destroySession('Absolute timeout');
            return ['valid' => false, 'reason' => "Session expired after {$sessionHours} hours"];
        }

        // Session is valid - update last activity
        $data[self::SESSION_LAST_ACTIVITY] = $now;

        // Calculate time until timeout
        $secondsUntilTimeout = (self::$config['timeout_minutes'] * 60) - ($now - $data[self::SESSION_LAST_ACTIVITY]);

        return [
            'valid' => true,
            'reason' => 'Session valid',
            'expires_in' => $secondsUntilTimeout,
            'idle_minutes' => $idleMinutes,
            'session_hours' => $sessionHours,
        ];
    }

    /**
     * Check if session is about to expire and needs warning
     */
    public static function getTimeoutWarning(): ?array {
        self::init();

        if (!isset($_SESSION[self::SESSION_DATA_KEY])) {
            return null;
        }

        $data = $_SESSION[self::SESSION_DATA_KEY];
        $now = time();
        $secondsUntilTimeout = (self::$config['timeout_minutes'] * 60) - ($now - $data[self::SESSION_LAST_ACTIVITY]);

        // Show warning if within warning period and still valid
        if ($secondsUntilTimeout <= self::$config['warning_before_seconds'] && $secondsUntilTimeout > 0) {
            return [
                'show_warning' => true,
                'seconds_remaining' => $secondsUntilTimeout,
                'minutes_remaining' => (int) ceil($secondsUntilTimeout / 60),
                'message' => "Your session will expire in {$secondsUntilTimeout} seconds due to inactivity.",
            ];
        }

        return null;
    }

    /**
     * Extend session timeout (call when user takes action)
     */
    public static function extend(): bool {
        self::init();

        if (!isset($_SESSION[self::SESSION_DATA_KEY])) {
            return false;
        }

        $_SESSION[self::SESSION_DATA_KEY][self::SESSION_LAST_ACTIVITY] = time();
        return true;
    }

    /**
     * Gracefully destroy session with logging
     */
    public static function destroySession(string $reason = 'User logout'): void {
        $userId = $_SESSION['user_id'] ?? 'Unknown';

        // Log the session destruction
        self::$logger->info('Session destroyed', [
            'user_id' => $userId,
            'reason' => $reason,
            'session_duration_minutes' => isset($_SESSION[self::SESSION_DATA_KEY]) 
                ? (int) floor((time() - $_SESSION[self::SESSION_DATA_KEY][self::SESSION_CREATED_AT]) / 60)
                : null,
        ]);

        // Clear all session variables
        $_SESSION = [];

        // Delete session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Destroy the session
        session_destroy();
    }

    /**
     * Get client IP address (handles proxies)
     */
    protected static function getClientIp(): string {
        // Check for shared internet
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        // Check for IP passed from proxy
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Handle multiple IPs in X-Forwarded-For (take first)
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        // Remote address
        else {
            return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        }
    }

    /**
     * Generate device fingerprint (browser + OS combo)
     */
    protected static function generateFingerprint(): string {
        $components = [
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'Unknown',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? 'Unknown',
        ];

        return hash('sha256', implode('|', $components));
    }

    /**
     * Get session info for debugging
     */
    public static function getSessionInfo(): array {
        self::init();

        if (!isset($_SESSION[self::SESSION_DATA_KEY])) {
            return [];
        }

        $data = $_SESSION[self::SESSION_DATA_KEY];
        $now = time();

        return [
            'created_at' => date('Y-m-d H:i:s', $data[self::SESSION_CREATED_AT]),
            'last_activity' => date('Y-m-d H:i:s', $data[self::SESSION_LAST_ACTIVITY]),
            'session_duration_minutes' => (int) floor(($now - $data[self::SESSION_CREATED_AT]) / 60),
            'idle_duration_minutes' => (int) floor(($now - $data[self::SESSION_LAST_ACTIVITY]) / 60),
            'ip_address' => $data[self::SESSION_IP_ADDRESS],
            'timeout_minutes' => self::$config['timeout_minutes'],
            'absolute_timeout_minutes' => self::$config['absolute_timeout_minutes'],
        ];
    }
}
