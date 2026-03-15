<?php
/**
 * MicroGrid Pro - Two-Factor Authentication (2FA) Library
 * 
 * Implements TOTP (Time-based One-Time Password) for Google Authenticator
 * and compatible authenticator apps.
 */

class TwoFactorAuth
{
    /**
     * Generate a new TOTP secret (base32 encoded random data)
     * 
     * @param int $length Number of bytes for the secret (default 32)
     * @return string Base32-encoded secret
     */
    public static function generateSecret(int $length = 32): string
    {
        $bytes = random_bytes($length);
        return self::base32Encode($bytes);
    }

    /**
     * Generate QR code URL for Google Authenticator
     * 
     * @param string $secret The TOTP secret
     * @param string $email User's email (displayed in authenticator app)
     * @param string $appName Application name (displayed in authenticator app)
     * @return string URL to QR code image (otpauth:// URL)
     */
    public static function getQRCodeUrl(string $secret, string $email, string $appName = 'MicroGrid Pro'): string
    {
        $label = urlencode("$appName ($email)");
        return "otpauth://totp/$label?secret=$secret&issuer=" . urlencode($appName);
    }

    /**
     * Verify a TOTP token (6-digit code from authenticator app)
     * 
     * @param string $secret The TOTP secret
     * @param string $token The 6-digit code to verify
     * @param int $timeWindow Number of 30-second windows to check (default 1 past & 1 future)
     * @return bool True if token is valid
     */
    public static function verifyToken(string $secret, string $token, int $timeWindow = 1): bool
    {
        // Token must be 6 digits
        if (!preg_match('/^\d{6}$/', $token)) {
            return false;
        }

        // Decode secret from base32
        $secretBinary = self::base32Decode($secret);
        if ($secretBinary === false) {
            return false;
        }

        // Calculate current time counter (30-second intervals since epoch)
        $timeCounter = (int)(time() / 30);

        // Check token against current and nearby time windows
        for ($i = -$timeWindow; $i <= $timeWindow; $i++) {
            $calculatedToken = self::generateToken($secretBinary, $timeCounter + $i);
            if ($calculatedToken === $token) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a 6-digit TOTP token for a given time counter
     * 
     * @param string $secretBinary Binary form of the secret
     * @param int $timeCounter Time counter (30-second intervals)
     * @return string 6-digit token
     */
    private static function generateToken(string $secretBinary, int $timeCounter): string
    {
        // Pack time counter as 64-bit big-endian
        $timeBytes = pack('N2', 0, $timeCounter);

        // Generate HMAC-SHA1
        $hmac = hash_hmac('sha1', $timeBytes, $secretBinary, true);

        // Extract 31-bit integer from HMAC (dynamic truncation)
        $offset = ord($hmac[19]) & 0x0F;
        $code = unpack('N', substr($hmac, $offset, 4))[1] & 0x7fffffff;

        // Return last 6 digits
        return str_pad($code % 1000000, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate backup codes for recovery (8 codes, 8 characters each)
     * 
     * @param int $count Number of backup codes to generate
     * @return array Array of backup codes
     */
    public static function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            // Generate random 8-character alphanumeric codes
            $code = substr(
                str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'),
                0,
                8
            );
            $codes[] = $code;
        }
        return $codes;
    }

    /**
     * Hash backup codes for storage (using bcrypt)
     * 
     * @param array $codes Backup codes to hash
     * @return string JSON-encoded array of hashed codes
     */
    public static function hashBackupCodes(array $codes): string
    {
        $hashed = [];
        foreach ($codes as $code) {
            $hashed[$code] = password_hash($code, PASSWORD_BCRYPT);
        }
        return json_encode($hashed);
    }

    /**
     * Verify and consume a backup code
     * 
     * @param string $code The backup code to verify
     * @param array $storedCodes Array of hashed codes from database
     * @return bool True if code is valid and not used
     */
    public static function verifyBackupCode(string $code, array $storedCodes): bool
    {
        foreach ($storedCodes as $hashedCode) {
            if (password_verify($code, $hashedCode)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Consume a backup code (remove it from the list)
     * 
     * @param string $code The backup code to consume
     * @param string $codesJson JSON-encoded hashed codes
     * @return string Updated JSON-encoded codes (or false if not found)
     */
    public static function consumeBackupCode(string $code, string $codesJson): string|bool
    {
        $stored = json_decode($codesJson, true);
        if (!is_array($stored)) {
            return false;
        }

        foreach ($stored as $plainCode => $hashedCode) {
            if (password_verify($code, $hashedCode)) {
                unset($stored[$plainCode]);
                return json_encode($stored);
            }
        }

        return false;
    }

    /**
     * Base32 encode (RFC 4648)
     * 
     * @param string $data Data to encode
     * @return string Base32-encoded string
     */
    private static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        
        // Convert data to binary
        for ($i = 0; $i < strlen($data); $i++) {
            $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }

        // Pad to multiple of 5
        $bits = str_pad($bits, ceil(strlen($bits) / 5) * 5, '0', STR_PAD_RIGHT);

        // Encode to base32
        $result = '';
        for ($i = 0; $i < strlen($bits); $i += 5) {
            $result .= $alphabet[bindec(substr($bits, $i, 5))];
        }

        return $result;
    }

    /**
     * Base32 decode (RFC 4648)
     * 
     * @param string $data Base32-encoded string
     * @return string|false Decoded data or false on error
     */
    private static function base32Decode(string $data): string|false
    {
        $data = strtoupper($data);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';

        // Convert base32 to binary
        for ($i = 0; $i < strlen($data); $i++) {
            $pos = strpos($alphabet, $data[$i]);
            if ($pos === false) {
                return false;
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        // Remove padding bits
        $bits = substr($bits, 0, (int)(strlen($bits) / 8) * 8);

        // Convert binary to string
        $result = '';
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $result .= chr(bindec(substr($bits, $i, 8)));
        }

        return $result;
    }
}
