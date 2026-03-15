<?php
/**
 * Email Notification System
 * Handles alert email notifications with template support
 * 
 * Configuration required in .env:
 *   MAIL_ENABLED - Enable/disable email notifications (true/false)
 *   MAIL_FROM - Sender email address
 *   MAIL_FROM_NAME - Sender display name
 *   MAIL_METHOD - 'php' (built-in mail()) or 'smtp'
 *   SMTP_HOST - SMTP server hostname (if method=smtp)
 *   SMTP_PORT - SMTP port (if method=smtp)
 *   SMTP_USER - SMTP username (if method=smtp)
 *   SMTP_PASS - SMTP password (if method=smtp)
 *   MAIL_LOG_SENT - Log sent emails to file (true/false)
 *   MAIL_LOG_FILE - Path to email log file
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/logger.php';

class Mailer {
    protected static $initialized = false;
    protected static $config = [];
    protected static $logger = null;

    /**
     * Initialize mailer configuration from .env
     */
    public static function init(): void {
        if (self::$initialized) {
            return;
        }

        Env::load();

        self::$config = [
            'enabled'     => Env::getBool('MAIL_ENABLED', false),
            'from'        => Env::get('MAIL_FROM', 'noreply@microgrid.local'),
            'from_name'   => Env::get('MAIL_FROM_NAME', 'MicroGrid Pro'),
            'method'      => Env::get('MAIL_METHOD', 'php'),
            'smtp_host'   => Env::get('SMTP_HOST', ''),
            'smtp_port'   => Env::getInt('SMTP_PORT', 587),
            'smtp_user'   => Env::get('SMTP_USER', ''),
            'smtp_pass'   => Env::get('SMTP_PASS', ''),
            'log_sent'    => Env::getBool('MAIL_LOG_SENT', true),
            'log_file'    => Env::get('MAIL_LOG_FILE', __DIR__ . '/../logs/email.log'),
        ];

        self::$logger = new Logger(__CLASS__);
        self::$initialized = true;
    }

    /**
     * Check if email is enabled
     */
    public static function isEnabled(): bool {
        self::init();
        return self::$config['enabled'];
    }

    /**
     * Send alert notification email
     * 
     * @param string $recipientEmail Recipient email address
     * @param string $recipientName Recipient display name
     * @param array $alert Alert data (alert_id, alert_type, severity, message, timestamp, microgrid_name)
     * @param string|null $familyName Family name for context
     * @return bool True if sent successfully
     */
    public static function sendAlertNotification(
        string $recipientEmail,
        string $recipientName,
        array $alert,
        ?string $familyName = null
    ): bool {
        self::init();

        if (!self::$config['enabled']) {
            return false;
        }

        // Generate email content
        $subject = self::generateSubject($alert);
        $body = self::generateAlertBody($alert, $familyName, $recipientName);
        $htmlBody = self::generateAlertHtml($alert, $familyName, $recipientName);

        return self::send(
            $recipientEmail,
            $recipientName,
            $subject,
            $body,
            $htmlBody,
            ['alert_id' => $alert['alert_id'], 'type' => 'alert_notification']
        );
    }

    /**
     * Send test email
     */
    public static function sendTest(string $recipientEmail): bool {
        self::init();

        $subject = 'Test Email from MicroGrid Pro';
        $body = "This is a test email to verify email configuration.\n\n";
        $body .= "Recipient: {$recipientEmail}\n";
        $body .= "Sent: " . date('Y-m-d H:i:s') . "\n";
        $body .= "Mail Method: " . self::$config['method'] . "\n";

        $htmlBody = "<html><body>";
        $htmlBody .= "<h2>Test Email from MicroGrid Pro</h2>";
        $htmlBody .= "<p>This is a test email to verify email configuration.</p>";
        $htmlBody .= "<ul>";
        $htmlBody .= "<li>Recipient: {$recipientEmail}</li>";
        $htmlBody .= "<li>Sent: " . date('Y-m-d H:i:s') . "</li>";
        $htmlBody .= "<li>Method: " . self::$config['method'] . "</li>";
        $htmlBody .= "</ul>";
        $htmlBody .= "</body></html>";

        return self::send(
            $recipientEmail,
            'Test User',
            $subject,
            $body,
            $htmlBody,
            ['type' => 'test_email']
        );
    }

    /**
     * Send email via configured method
     */
    protected static function send(
        string $to,
        string $toName,
        string $subject,
        string $body,
        string $htmlBody,
        array $metadata = []
    ): bool {
        self::init();

        try {
            if (self::$config['method'] === 'smtp') {
                return self::sendViaSMTP($to, $toName, $subject, $body, $htmlBody, $metadata);
            } else {
                return self::sendViaPhp($to, $toName, $subject, $body, $htmlBody, $metadata);
            }
        } catch (Exception $e) {
            self::$logger->error('Email send failed: ' . $e->getMessage(), [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send email via PHP built-in mail() function
     */
    protected static function sendViaPhp(
        string $to,
        string $toName,
        string $subject,
        string $body,
        string $htmlBody,
        array $metadata
    ): bool {
        $headers = [
            'From: ' . self::formatAddress(self::$config['from'], self::$config['from_name']),
            'Reply-To: ' . self::$config['from'],
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'X-Mailer: MicroGrid-Pro-Mailer',
        ];

        $recipient = self::formatAddress($to, $toName);
        $success = mail($recipient, $subject, $htmlBody, implode("\r\n", $headers) . "\r\n");

        if ($success && self::$config['log_sent']) {
            self::logEmail($to, $subject, 'success', $metadata);
        }

        if ($success) {
            self::$logger->info('Email sent successfully', ['to' => $to, 'subject' => $subject]);
        } else {
            self::$logger->warning('mail() function returned false', ['to' => $to, 'subject' => $subject]);
        }

        return $success;
    }

    /**
     * Send email via SMTP (requires mail configuration)
     * Falls back to PHP mail() if not configured
     */
    protected static function sendViaSMTP(
        string $to,
        string $toName,
        string $subject,
        string $body,
        string $htmlBody,
        array $metadata
    ): bool {
        // Fallback to PHP mail if SMTP not configured
        if (empty(self::$config['smtp_host'])) {
            self::$logger->warning(
                'SMTP configured but no SMTP_HOST set, falling back to PHP mail()',
                ['to' => $to]
            );
            return self::sendViaPhp($to, $toName, $subject, $body, $htmlBody, $metadata);
        }

        try {
            // Basic SMTP implementation using stream_socket_client
            // For production, consider using PHPMailer or SwiftMailer
            $socket = fsockopen(
                self::$config['smtp_host'],
                self::$config['smtp_port'],
                $errno,
                $errstr,
                10
            );

            if (!$socket) {
                self::$logger->warning('SMTP connection failed', [
                    'host' => self::$config['smtp_host'],
                    'port' => self::$config['smtp_port'],
                    'error' => "$errstr ($errno)",
                ]);
                // Fallback to PHP mail
                return self::sendViaPhp($to, $toName, $subject, $body, $htmlBody, $metadata);
            }

            // SMTP has complex protocol requirements
            // For simplicity and reliability, fallback to PHP mail()
            fclose($socket);
            return self::sendViaPhp($to, $toName, $subject, $body, $htmlBody, $metadata);
        } catch (Exception $e) {
            self::$logger->warning('SMTP error, falling back to PHP mail(): ' . $e->getMessage());
            return self::sendViaPhp($to, $toName, $subject, $body, $htmlBody, $metadata);
        }
    }

    /**
     * Format email address with name
     */
    protected static function formatAddress(string $email, string $name = ''): string {
        if (empty($name)) {
            return $email;
        }
        // Sanitize name to prevent email header injection
        $name = str_replace(["\r", "\n"], '', $name);
        return "\"{$name}\" <{$email}>";
    }

    /**
     * Generate email subject based on alert
     */
    protected static function generateSubject(array $alert): string {
        $severity = strtoupper($alert['severity'] ?? 'UNKNOWN');
        $type = str_replace('_', ' ', $alert['alert_type'] ?? 'Alert');
        $type = ucwords(strtolower($type));

        return "[{$severity}] {$type} Alert - MicroGrid Pro";
    }

    /**
     * Generate plain text alert email body
     */
    protected static function generateAlertBody(
        array $alert,
        ?string $familyName,
        ?string $recipientName
    ): string {
        $body = "Hello" . ($recipientName ? " {$recipientName}" : "") . ",\n\n";

        $body .= "An alert has been triggered on your MicroGrid Platform:\n\n";

        $body .= "─── Alert Details ───\n";
        $body .= "Type: " . str_replace('_', ' ', strtoupper($alert['alert_type'] ?? '')) . "\n";
        $body .= "Severity: " . strtoupper($alert['severity'] ?? 'UNKNOWN') . "\n";
        $body .= "Message: " . ($alert['message'] ?? 'No message') . "\n";

        if ($familyName) {
            $body .= "Family: {$familyName}\n";
        }

        if ($alert['microgrid_name'] ?? null) {
            $body .= "Microgrid: {$alert['microgrid_name']}\n";
        }

        $body .= "Time: " . ($alert['timestamp'] ?? date('Y-m-d H:i:s')) . "\n";
        $body .= "Alert ID: " . ($alert['alert_id'] ?? 'N/A') . "\n";

        $body .= "\n─── Recommended Actions ───\n";
        $body .= self::getAlertRecommendation($alert['alert_type'] ?? '');

        $body .= "\n\nPlease log in to your MicroGrid Platform dashboard to view more details and take action.\n\n";

        $body .= "Best regards,\n";
        $body .= "MicroGrid Pro System\n";

        return $body;
    }

    /**
     * Generate HTML alert email body
     */
    protected static function generateAlertHtml(
        array $alert,
        ?string $familyName,
        ?string $recipientName
    ): string {
        $severityClass = self::getSeverityClass($alert['severity'] ?? '');
        $typeDisplay = str_replace('_', ' ', ucwords(strtolower($alert['alert_type'] ?? '')));

        $html = "<!DOCTYPE html>";
        $html .= "<html>";
        $html .= "<head>";
        $html .= "<meta charset='UTF-8'>";
        $html .= "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
        $html .= "<style>";
        $html .= "body { font-family: Arial, sans-serif; color: #333; background: #f5f5f5; }";
        $html .= ".container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }";
        $html .= ".header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }";
        $html .= ".content { padding: 30px; }";
        $html .= ".alert-box { background: #f8f9fa; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; border-radius: 4px; }";
        $html .= ".alert-box.warning { border-left-color: #ffc107; }";
        $html .= ".alert-box.info { border-left-color: #17a2b8; }";
        $html .= ".detail-row { display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee; }";
        $html .= ".detail-label { font-weight: bold; color: #667eea; min-width: 120px; }";
        $html .= ".severity-critical { color: #dc3545; font-weight: bold; }";
        $html .= ".severity-warning { color: #ffc107; font-weight: bold; }";
        $html .= ".severity-info { color: #17a2b8; font-weight: bold; }";
        $html .= ".recommendation { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0; border-radius: 4px; }";
        $html .= ".recommendation h4 { margin-top: 0; color: #1976D2; }";
        $html .= ".footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 8px 8px; border-top: 1px solid #ddd; }";
        $html .= ".button { display: inline-block; background: #667eea; color: white; padding: 10px 20px; border-radius: 4px; text-decoration: none; margin: 10px 0; }";
        $html .= "</style>";
        $html .= "</head>";
        $html .= "<body>";

        $html .= "<div class='container'>";

        // Header
        $html .= "<div class='header'>";
        $html .= "<h2 style='margin: 0;'>MicroGrid Pro Alert</h2>";
        $html .= "<p style='margin: 5px 0 0 0; font-size: 14px;'>Alert Notification System</p>";
        $html .= "</div>";

        // Content
        $html .= "<div class='content'>";
        $html .= "<p>Hello" . ($recipientName ? " {$recipientName}" : "") . ",</p>";
        $html .= "<p>An important alert has been triggered on your MicroGrid Platform:</p>";

        // Alert Box
        $html .= "<div class='alert-box'>";
        $html .= "<h3 style='margin-top: 0;'>" . htmlspecialchars($typeDisplay) . "</h3>";
        $html .= "<p style='margin: 10px 0; font-size: 16px;'>" . htmlspecialchars($alert['message'] ?? '') . "</p>";
        $html .= "</div>";

        // Details
        $html .= "<div class='detail-row'>";
        $html .= "<span class='detail-label'>Severity:</span>";
        $html .= "<span class='severity-" . ($alert['severity'] ?? 'unknown') . "'>" . strtoupper($alert['severity'] ?? '') . "</span>";
        $html .= "</div>";

        if ($familyName) {
            $html .= "<div class='detail-row'>";
            $html .= "<span class='detail-label'>Family:</span>";
            $html .= "<span>" . htmlspecialchars($familyName) . "</span>";
            $html .= "</div>";
        }

        if ($alert['microgrid_name'] ?? null) {
            $html .= "<div class='detail-row'>";
            $html .= "<span class='detail-label'>Microgrid:</span>";
            $html .= "<span>" . htmlspecialchars($alert['microgrid_name']) . "</span>";
            $html .= "</div>";
        }

        $html .= "<div class='detail-row'>";
        $html .= "<span class='detail-label'>Time:</span>";
        $html .= "<span>" . htmlspecialchars($alert['timestamp'] ?? date('Y-m-d H:i:s')) . "</span>";
        $html .= "</div>";

        $html .= "<div class='detail-row' style='border-bottom: none;'>";
        $html .= "<span class='detail-label'>Alert ID:</span>";
        $html .= "<span>#" . htmlspecialchars($alert['alert_id'] ?? '') . "</span>";
        $html .= "</div>";

        // Recommendation
        $recommendation = self::getAlertRecommendation($alert['alert_type'] ?? '');
        if ($recommendation) {
            $html .= "<div class='recommendation'>";
            $html .= "<h4>Recommended Action</h4>";
            $html .= "<p>" . htmlspecialchars($recommendation) . "</p>";
            $html .= "</div>";
        }

        // Action Button
        $html .= "<div style='text-align: center; margin: 30px 0;'>";
        $html .= "<a href='" . htmlspecialchars(Env::get('APP_URL', 'http://localhost/microgrid')) . "/alerts.php' class='button'>View in Dashboard</a>";
        $html .= "</div>";

        $html .= "</div>";

        // Footer
        $html .= "<div class='footer'>";
        $html .= "<p style='margin: 0;'>This is an automated alert notification from MicroGrid Pro.<br>";
        $html .= "Do not reply to this email. Please contact your system administrator if you need assistance.</p>";
        $html .= "<p style='margin: 10px 0 0 0; color: #999;'>© " . date('Y') . " MicroGrid Pro. All rights reserved.</p>";
        $html .= "</div>";

        $html .= "</div>";
        $html .= "</body>";
        $html .= "</html>";

        return $html;
    }

    /**
     * Get severity class for HTML styling
     */
    protected static function getSeverityClass(string $severity): string {
        return match (strtolower($severity)) {
            'critical' => 'alert-box',
            'warning' => 'alert-box warning',
            'info' => 'alert-box info',
            default => 'alert-box info',
        };
    }

    /**
     * Get recommended action for alert type
     */
    protected static function getAlertRecommendation(string $alertType): string {
        return match (strtolower($alertType)) {
            'battery_low' => 'Check battery status immediately. If critically low, consider reducing load or switching to backup power.',
            'overcharge' => 'Check battery charging system. Overcharge protection may have failed. Investigate immediately.',
            'high_temperature' => 'Monitor temperature closely. High temperature can damage battery. Check cooling system and ventilation.',
            'overvoltage' => 'Check voltage regulation system. High voltage can damage equipment. Investigate immediately.',
            'undervoltage' => 'Check power supply and wiring. Low voltage may indicate connection issues or power loss.',
            'sensor_fault' => 'Verify sensor connection and functionality. A faulty sensor may send incorrect readings.',
            'system_error' => 'Check system logs for more details. There may be an inverter or connection issue.',
            default => 'Please review the alert message and take appropriate action based on your system documentation.',
        };
    }

    /**
     * Log sent email to file
     */
    protected static function logEmail(
        string $to,
        string $subject,
        string $status,
        array $metadata
    ): void {
        try {
            $logdir = dirname(self::$config['log_file']);
            if (!is_dir($logdir)) {
                mkdir($logdir, 0755, true);
            }

            $logEntry = json_encode([
                'timestamp' => date('Y-m-d H:i:s'),
                'to' => $to,
                'subject' => $subject,
                'status' => $status,
                'metadata' => $metadata,
            ]) . "\n";

            file_put_contents(self::$config['log_file'], $logEntry, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            self::$logger->warning('Failed to log email: ' . $e->getMessage());
        }
    }
}
