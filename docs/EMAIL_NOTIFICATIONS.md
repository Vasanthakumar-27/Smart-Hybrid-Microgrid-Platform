# Email Notifications System

## Overview

The MicroGrid Pro email notification system allows users to receive real-time alerts via email whenever critical or important events are detected in their microgrids. All notifications are user-configurable with flexible severity-based preferences and delivery methods.

**Key Capabilities:**
- Real-time alert notifications via email
- User-controlled severity filters (critical, warning, info)
- Support for PHP mail() and SMTP delivery methods
- HTML and plain-text email templates
- Email delivery logging and audit trail
- Test email functionality
- Configurable retry mechanism for failed deliveries

---

## Configuration

### Environment Variables (.env)

Add the following variables to your `.env` file (or copy from `.env.example`):

```bash
# Enable email notifications
MAIL_ENABLED=true

# Sender address and name
MAIL_FROM="noreply@microgrid.local"
MAIL_FROM_NAME="MicroGrid Pro"

# Delivery method: php or smtp
MAIL_METHOD="php"

# SMTP Configuration (required if MAIL_METHOD=smtp)
SMTP_HOST="smtp.gmail.com"
SMTP_PORT=587
SMTP_USER="your-email@gmail.com"
SMTP_PASS="your-app-password"

# Logging
MAIL_LOG_SENT=true
MAIL_LOG_FILE="logs/email.log"
```

### Database Migration

Run the migration to add email notification tables:

```bash
mysql -u root microgrid_platform < database/migrations/20260316_add_email_notifications.sql
```

This creates:
- `email_notifications_enabled` - Enable/disable notifications for user
- `email_notify_critical` - Notify on critical severity alerts
- `email_notify_warning` - Notify on warning alerts
- `email_notify_info` - Notify on info alerts
- `email_digest_frequency` - Delivery mode (immediate, daily, weekly)
- `email_notification_queue` - Pending/failed email queue
- `email_notification_log` - Audit trail of all emails sent

---

## Email Delivery Methods

### Method 1: PHP mail() [Default]

Uses the server's built-in mail function. Requires:
- Sendmail or Postfix configured on the server
- Valid return path in `MAIL_FROM`

**Setup:**
```bash
# On Linux/Ubuntu: Install and configure Postfix
sudo apt-get install postfix
sudo postfix start

# Configure in .env
MAIL_METHOD="php"
MAIL_FROM="noreply@yourdomain.com"
```

**Advantages:**
- No authentication needed
- Fast delivery
- Works on most shared hosting

**Disadvantages:**
- May be blocked by spam filters
- No delivery confirmation
- Limited error handling

### Method 2: SMTP

Uses external SMTP service (Gmail, SendGrid, Office 365, etc.).

**Common SMTP Providers:**

**Gmail:**
```bash
MAIL_METHOD="smtp"
SMTP_HOST="smtp.gmail.com"
SMTP_PORT=587
SMTP_USER="your-email@gmail.com"
SMTP_PASS="your-app-password"
```

Note: Use [App Passwords](https://support.google.com/accounts/answer/185833) instead of your regular password.

**SendGrid:**
```bash
MAIL_METHOD="smtp"
SMTP_HOST="smtp.sendgrid.net"
SMTP_PORT=587
SMTP_USER="apikey"
SMTP_PASS="SG.your-api-key-here"
```

**Office 365:**
```bash
MAIL_METHOD="smtp"
SMTP_HOST="smtp.office365.com"
SMTP_PORT=587
SMTP_USER="your-email@company.onmicrosoft.com"
SMTP_PASS="your-password"
```

**Local Mail Server:**
```bash
MAIL_METHOD="smtp"
SMTP_HOST="localhost"
SMTP_PORT=25
SMTP_USER=""
SMTP_PASS=""
```

**Advantages:**
- Better deliverability
- Authentication for security
- Detailed error reporting
- Supports TLS/SSL encryption

**Disadvantages:**
- Requires credentials
- May have rate limits
- Credential exposure risk (mitigate with environment variables)

---

## User Notification Preferences

### Setting Preferences via API

Users can manage their email notification preferences through the `/api/notifications.php` endpoint.

**Get Current Preferences:**
```bash
curl -X GET http://localhost/microgrid/api/notifications.php \
  -b "PHPSESSID=your_session_id"
```

**Response:**
```json
{
  "success": true,
  "preferences": {
    "email": "user@example.com",
    "notifications_enabled": true,
    "notify_critical": true,
    "notify_warning": true,
    "notify_info": false,
    "digest_frequency": "immediate",
    "last_updated": "2026-03-16 10:30:00",
    "mail_enabled": true
  }
}
```

**Update Preferences:**
```bash
curl -X POST http://localhost/microgrid/api/notifications.php \
  -H "X-CSRF-Token: your_csrf_token" \
  -H "Content-Type: application/json" \
  -b "PHPSESSID=your_session_id" \
  -d '{
    "action": "update-preferences",
    "enabled": true,
    "notify_critical": true,
    "notify_warning": true,
    "notify_info": false,
    "digest_frequency": "immediate"
  }'
```

**Send Test Email:**
```bash
curl -X POST http://localhost/microgrid/api/notifications.php \
  -H "X-CSRF-Token: your_csrf_token" \
  -b "PHPSESSID=your_session_id" \
  -d '{"action": "send-test"}'
```

### Preference Settings

| Setting | Description | Values |
|---------|-------------|--------|
| `notifications_enabled` | Master switch for all notifications | true, false |
| `notify_critical` | Receive critical severity alerts | true, false |
| `notify_warning` | Receive warning severity alerts | true, false |
| `notify_info` | Receive info severity alerts | true, false |
| `digest_frequency` | Email delivery frequency | immediate, daily, weekly |

---

## Alert Types and Severity

Alerts are categorized by severity to allow users to filter notifications:

### Critical Alerts

Require immediate attention and indicate dangerous conditions:
- **Overvoltage** — Voltage exceeds safe threshold (>400V solar, >500V wind)
- **Overcharge** — Battery charge exceeds 100%
- **High Temperature** — Dangerous heat level detected
- **Battery Critically Low** — SoC below 15%
- **Inverter Fault** — Sudden voltage drop with current spike
- **System Error** — General critical system failure

### Warning Alerts

Should be monitored but don't require immediate action:
- **Battery Low** — SoC between 15-25%
- **Undervoltage** — Voltage below minimum threshold
- **Sensor Fault** — Possible sensor malfunction
- **Battery Draining Fast** — Predictive alert for rapid discharge
- **Panel Dirty Warning** — Solar output below expected
- **Wind Turbine Abnormal** — Output below expected for wind speed

### Info Alerts

General information and notifications:
- **System Events** — General operational notifications
- **Maintenance Alerts** — Scheduled maintenance notices

---

## Email Template Examples

### Critical Alert Email

**Subject:** `[CRITICAL] Overvoltage Alert - MicroGrid Pro`

**HTML Content:**
```html
<!DOCTYPE html>
<html>
  <head>
    <style>
      body { font-family: Arial; color: #333; }
      .alert-box { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; }
      .detail-row { display: flex; justify-content: space-between; margin: 10px 0; }
      .button { background: #667eea; color: white; padding: 10px 20px; border-radius: 4px; }
    </style>
  </head>
  <body>
    <h2>MicroGrid Pro Alert</h2>
    <p>Hello User,</p>
    <p>An alert has been triggered on your MicroGrid Platform:</p>
    
    <div class="alert-box">
      <h3>Overvoltage</h3>
      <p>Overvoltage detected: 420V on Solar Microgrid</p>
    </div>
    
    <div class="detail-row">
      <span><strong>Severity:</strong></span>
      <span>CRITICAL</span>
    </div>
    <div class="detail-row">
      <span><strong>Time:</strong></span>
      <span>2026-03-16 14:30:15</span>
    </div>
    
    <div class="recommendation">
      <h4>Recommended Action</h4>
      <p>Check voltage regulation system. High voltage can damage equipment. Investigate immediately.</p>
    </div>
    
    <a href="http://localhost/microgrid/alerts.php" class="button">View in Dashboard</a>
  </body>
</html>
```

---

## API Endpoints

### GET /api/notifications.php

Retrieve user's notification preferences.

**Authentication:** Required (session or API key)

**Response:**
```json
{
  "success": true,
  "preferences": {
    "email": "user@example.com",
    "notifications_enabled": true,
    "notify_critical": true,
    "notify_warning": true,
    "notify_info": false,
    "digest_frequency": "immediate",
    "last_updated": "2026-03-16 10:30:00",
    "mail_enabled": true
  }
}
```

### POST /api/notifications.php

Manage notification preferences or send test emails.

**Action: `update-preferences`**

```json
{
  "action": "update-preferences",
  "enabled": true,
  "notify_critical": true,
  "notify_warning": true,
  "notify_info": false,
  "digest_frequency": "immediate"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Preferences updated successfully",
  "preferences": {
    "notifications_enabled": true,
    "notify_critical": true,
    "notify_warning": true,
    "notify_info": false,
    "digest_frequency": "immediate"
  }
}
```

**Action: `send-test`**

```json
{
  "action": "send-test"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Test email sent to user@example.com. Check your inbox (and spam folder) in a few moments."
}
```

---

## Integration with Alerts

### Automatic Notification Triggers

When an alert is created, the system automatically:

1. **Check Alert Severity** — Determine if critical/warning/info
2. **Find Notifiable Users** — Query `family_users` for users with notification permissions
3. **Check Preferences** — Verify user has enabled notifications for that severity
4. **Prevent Duplicates** — Skip if same alert already sent to user
5. **Send Email** — Call `Mailer::sendAlertNotification()`
6. **Log Result** — Record success/failure in `email_notification_log`

### Code Example: Custom Notification

```php
// In your custom alert generation code
$alertId = $db->lastInsertId();
sendAlertNotifications($alertId, $familyId, $microgridId);
```

### Triggering Points

Notifications are sent when:
- Battery alerts are generated (low, overcharge, high temp)
- Microgrid alerts are generated (voltage, temperature, sensor issues)
- System error alerts are created
- Any new alert is inserted into the `alerts` table

---

## Email Logging and Audit Trail

All sent/failed emails are logged to:

### 1. File Logging (`logs/email.log`)

Plain-text JSON log entries (one per line):
```json
{"timestamp":"2026-03-16 14:30:15","to":"user@example.com","subject":"[CRITICAL] Overvoltage Alert","status":"sent","metadata":{"alert_id":123,"type":"alert_notification"}}
```

### 2. Database Logging (`email_notification_log` table)

Comprehensive audit trail for compliance:
- User ID and email
- Alert details (type, severity, message)
- Delivery status (sent, failed, bounced)
- Timestamps
- IP address and user agent

**Query recent sent emails:**
```sql
SELECT * FROM email_notification_log
WHERE status = 'sent'
ORDER BY created_at DESC
LIMIT 50;
```

**Count emails by user:**
```sql
SELECT user_id, COUNT(*) as email_count, COUNT(CASE WHEN status='sent' THEN 1 END) as successful
FROM email_notification_log
GROUP BY user_id;
```

---

## Troubleshooting

### Problem: Emails not sending

**Step 1: Verify Configuration**
```php
// Check in your application
if (!Mailer::isEnabled()) {
    echo "Email notifications are disabled in .env";
}
```

**Step 2: Check Mail Configuration**
```bash
# View current settings
grep MAIL .env
grep SMTP .env
```

**Step 3: Review Logs**
```bash
# Check email delivery log
tail -f logs/email.log

# Check application error log
tail -f logs/app.log
```

**Step 4: Send Test Email**
```bash
curl -X POST http://localhost/microgrid/api/notifications.php \
  -H "X-CSRF-Token: your_token" \
  -d '{"action": "send-test"}'
```

### Problem: Emails going to spam

**Solutions:**
1. **Use SMTP instead of php mail()** — More reliable delivery
2. **Configure SPF/DKIM records** — For your domain
3. **Use authenticated SMTP** — Like Gmail or SendGrid
4. **Add Reply-To header** — Included automatically
5. **Reduce email volume** — Don't send duplicate alerts

### Problem: User not receiving emails

**Check user preferences:**
```sql
SELECT * FROM users
WHERE user_id = 123
AND email_notifications_enabled = 1;
```

**Verify notification preferences:**
- User has `email_notifications_enabled = 1`
- User has matching severity enabled (critical/warning/info)
- User's email address is valid
- User is in `family_users` table for that family

**Check delivery log:**
```sql
SELECT * FROM email_notification_log
WHERE user_id = 123
ORDER BY created_at DESC
LIMIT 10;
```

---

## Database Schema

### users table (additions)
```sql
email_notifications_enabled TINYINT(1) -- Enable/disable all notifications
email_notify_critical TINYINT(1) -- Receive critical alerts
email_notify_warning TINYINT(1) -- Receive warning alerts
email_notify_info TINYINT(1) -- Receive info alerts
email_digest_frequency VARCHAR(20) -- immediate, daily, weekly
email_preferences_updated_at TIMESTAMP -- Last preference update
```

### email_notification_queue table
```sql
queue_id INT PRIMARY KEY AUTO_INCREMENT
user_id INT FOREIGN KEY
alert_id INT FOREIGN KEY
recipient_email VARCHAR(255)
recipient_name VARCHAR(255)
subject VARCHAR(255)
body_text LONGTEXT
body_html LONGTEXT
metadata JSON
status ENUM('pending', 'sent', 'failed', 'bounced')
attempts INT
next_retry TIMESTAMP
error_message TEXT
sent_at TIMESTAMP
created_at TIMESTAMP
updated_at TIMESTAMP
```

### email_notification_log table
```sql
log_id INT PRIMARY KEY AUTO_INCREMENT
user_id INT FOREIGN KEY
alert_id INT FOREIGN KEY
recipient_email VARCHAR(255)
alert_type VARCHAR(50)
alert_severity VARCHAR(20)
alert_message TEXT
email_subject VARCHAR(255)
status ENUM('sent', 'failed', 'blocked', 'bounced', 'complained')
send_method VARCHAR(20)
delivery_time_ms INT
response_code VARCHAR(10)
error_reason TEXT
ip_address VARCHAR(45)
user_agent VARCHAR(255)
created_at TIMESTAMP
```

---

## Security Considerations

### 1. Credential Protection
- Store SMTP credentials in `.env` (not tracked by Git)
- Use environment variables, not hardcoded credentials
- Regenerate API keys if compromised

### 2. Email Content Sanitization
- All user input is HTML-escaped before sending
- Email templates prevent header injection attacks
- CSRF token required for preference changes

### 3. Rate Limiting
- Respect user preferences to avoid spam
- Implement delivery delays if needed
- Log all delivery attempts for audit trail

### 4. GDPR Compliance
- Users can disable email notifications
- Email delivery logs respect retention policies
- Log entries can be purged after retention period

### 5. Privacy
- Email addresses are never logged in plain-text in error messages
- Only store hashed/truncated versions in some logs
- User can see which emails were sent to them

---

## Testing

### Test Email Delivery

```bash
# Method 1: Via API
curl -X POST http://localhost/microgrid/api/notifications.php \
  -H "X-CSRF-Token: your_token" \
  -H "Content-Type: application/json" \
  -d '{"action": "send-test"}' \
  -b "PHPSESSID=your_session"

# Check result in logs
tail logs/email.log
```

### Test Alert Notification

```php
<?php
require_once 'includes/session.php';
require_once 'includes/functions.php';
require_once 'includes/mailer.php';

// Create a test alert
$db = getDB();
$stmt = $db->prepare("INSERT INTO alerts (family_id, alert_type, severity, message) VALUES (?, ?, ?, ?)");
$stmt->execute([1, 'battery_low', 'warning', 'Test alert for email notification']);
$alertId = $db->lastInsertId();

// Trigger notifications
sendAlertNotifications($alertId, 1);

echo "Test alert created with ID: $alertId\n";
echo "Check email.log for delivery results\n";
?>
```

---

## Performance Considerations

1. **Async Delivery** — Email sending may be slow, consider:
   - Queue system (email_notification_queue)
   - Background job worker
   - Cron job for periodic delivery

2. **Rate Limiting** — Prevent email storms:
   - Max 1 email per 5 seconds per user
   - Daily/weekly digest for lower severity
   - Deduplication for identical alerts

3. **Database Queries** — Optimize user lookups:
   - Add indexes on (family_id, email_notifications_enabled)
   - Add index on email_notification_log(status, created_at)
   - Regular VACUUM on log tables

---

## Future Enhancements

Potential improvements for email notification system:

1. **SMS Notifications** — Add SMS for critical alerts
2. **Push Notifications** — Mobile app push notifications
3. **Webhook Integrations** — POST to external services
4. **Email Digest** — Batch multiple alerts into daily/weekly digest
5. **Template Customization** — Admin control over email templates
6. **Multi-language** — Localized email content
7. **Delivery Tracking** — Open/click tracking via pixels
8. **Blacklist Management** — Bounce handling and suppression
9.  **Alternative Channels** — Slack, Teams, Discord integration
10. **Advanced Filtering** — Chain rules for complex notifications

---

## Support and Debugging

### Enable Debug Logging

In `includes/logger.php`, set `LOG_LEVEL=debug`:
```bash
LOG_LEVEL=debug
```

This will log all email operations including SMTP commands.

### Check SMTP Connection

```bash
# Test SMTP connectivity
telnet smtp.gmail.com 587

# Or via OpenSSL
openssl s_client -connect smtp.gmail.com:587 -starttls smtp
```

### Mail System Diagnostics

```bash
# View system mail logs (Linux)
tail -f /var/log/mail.log

# Check PostFix queue
postqueue -p

# Verify SPF record
dig yourdomain.com txt | grep v=spf1
```

For additional support, check:
- [PHP mail() documentation](https://www.php.net/manual/en/function.mail.php)
- [SMTP Protocol (RFC 5321)](https://tools.ietf.org/html/rfc5321)
- [Email deliverability guide](https://postmarkapp.com/guides)

