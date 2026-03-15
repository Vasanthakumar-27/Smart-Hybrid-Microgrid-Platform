# MicroGrid Pro - Implementation & Hardening Plan
## Project Status & Task Completion Guide
Date: March 15, 2026

---

## ✅ COMPLETED TASKS (CRITICAL & HIGH PRIORITY)

### 1. ✓ CSRF Protection (ALREADY IMPLEMENTED - VERIFIED)
- **Status**: Fully implemented in `includes/session.php`
- **Functions**: `generateCSRFToken()`, `validateCSRFToken()`, `rotateCSRFToken()`
- **Implementation**:
  - Uses `random_bytes(32)` for token generation
  - `hash_equals()` for timing-safe comparison
  - Token rotation after successful state-changing actions
  - Applied to all admin forms (families.php, microgrids.php, users.php, profile.php)
  - Applied to alerts.php API

### 2. ✓ .ENV Configuration System
- **Files Created**:
  - `.env.example` - Template with all configurable settings
  - `config/env.php` - Environment loader with helper functions
  - `.gitignore` - Updated to exclude `.env`
- **Updated Files**:
  - `config/database.php` - Now loads from .env with fallbacks
- **Functions Added**:
  -`loadEnv()` - Parses .env file
  - `getEnv()` - Get string value with default
  - `getEnvBool()` - Get boolean value
  - `getEnvInt()` - Get integer value
- **Configuration Available**:
  - Database credentials (host, port, user, password)
  - Logging settings
  - Email configuration
  - Rate limiting parameters
  - Security settings (session timeout, HTTPS, etc.)
  - Backup settings
  - Timezone & application settings

### 3. ✓ Input Validation & Sanitization
- **File Created**: `includes/validation.php`
- **Validation Functions**:
  - `validateFloat(mixed, min, max)` - Float with bounds
  - `validateInt(mixed, min, max)` - Integer with bounds
  - `validateString(string, minLen, maxLen)` - String length check
  - `validateEnum(value, allowedValues)` - Enum validation
  - `validateIoTReading(array)` - Complete reading validation
  - `validateBatteryStatus(array)` - Complete battery validation
  - `sanitizeErrorMessage(string)` - Remove sensitive data from errors
- **Applied To**:
  - `api/readings.php` - POST requests now fully validated
  - `api/battery.php` - POST requests now fully validated
- **Bounds Enforced**:
  - Voltage: 100-500V (readings), 0-100V (battery)
  - Current: 0-100A
  - Power: 0-10000 kW
  - Battery level: 0-100%
  - Temperature: -50 to 150°C
  - Energy: 0-10000 kWh

### 4. ✓ API Rate Limiting
- **File Created**: `includes/ratelimit.php`
- **Functions**:
  - `checkRateLimit(apiKey, maxRequests, windowSeconds)` - Check limit status
  - `enforceRateLimit(apiKey, maxRequests, windowSeconds)` - Enforce & exit on limit
  - `cleanupRateLimitFiles(maxAgeSeconds)` - Cleanup old counters
- **Implementation**:
  - Sliding window counter per API key
  - File-based persistence in temp directory
  - Configurable via .env (RATE_LIMIT_ENABLED, RATE_LIMIT_REQUESTS, RATE_LIMIT_WINDOW_SECONDS)
  - Adds response headers: X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset
  - Returns HTTP 429 when limit exceeded
  - Retry-After header included
- **Applied To**:
  - `api/readings.php` - Rate limit enforced for all requests
  - `api/battery.php` - Rate limit enforced for all requests

---

## 📋 HIGH PRIORITY TASKS - NOT YET STARTED

### 5. Add HTTPS Enforcement
**Difficulty**: Easy | **Time**: 1-2 hours
**Implementation**:
```
Location: .htaccess or Apache vhost config
Goal: Force all HTTP traffic to HTTPS

Step 1: Create/update .htaccess in root directory:
```apache
# Force HTTPS
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Add HSTS header (for browsers)
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains" env=HTTPS

# Remove sensitive headers
Header always unset "X-Powered-By"
Header always unset "Server"
```

Step 2: Install SSL certificate (Let's Encrypt recommended):
```bash
# If on Linux server
sudo certbot certonly --webroot -w /var/www/microgrid -d microgrid.example.com

# Copy certificate to XAMPP
cp /etc/letsencrypt/live/microgrid/cert.pem C:/xampp/apache/conf/ssl.crt/server.crt
cp /etc/letsencrypt/live/microgrid/privkey.pem C:/xampp/apache/conf/ssl.key/server.key
```

Step 3: Enable Apache SSL module and vhost:
```
Uncomment in httpd.conf:
LoadModule ssl_module modules/mod_ssl.so
LoadModule rewrite_module modules/mod_rewrite.so

Include conf/extra/httpd-ssl.conf
Include conf/extra/httpd-vhosts.conf
```

Step 4: Verify in config/database.php that BASE_URL uses https://

---

### 6. Add Centralized Error Logging
**Difficulty**: Medium | **Time**: 2-3 hours
**Implementation**: Create `includes/logger.php`
```php
<?php
class Logger {
    private static $logFile;
    private static $level;
    
    public static function init() {
        self::$logFile = getEnv('LOG_FILE', sys_get_temp_dir() . '/microgrid.log');
        self::$level = getEnv('LOG_LEVEL', 'warning');
    }
    
    public static function info(string $message, ?array $context = null): void {
        self::log('INFO', $message, $context);
    }
    
    public static function warning(string $message, ?array $context = null): void {
        self::log('WARNING', $message, $context);
    }
    
    public static function error(string $message, mixed $exception = null): void {
        $context = [];
        if ($exception instanceof Throwable) {
            $context['exception'] = get_class($exception);
            $context['file'] = $exception->getFile();
            $context['line'] = $exception->getLine();
        }
        self::log('ERROR', $message, $context);
    }
    
    private static function log(string $level, string $message, ?array $context): void {
        $timestamp = date('Y-m-d H:i:s');
        $contextJson = $context ? json_encode($context) : '';
        $logLine = "[$timestamp] $level: $message $contextJson\n";
        
        file_put_contents(self::$logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
}
```

**Integration Points**:
- Call `Logger::init()` in `config/database.php`
- Replace all `error_log()` calls with `Logger::error()`
- Add `Logger::info()` for important events (login, API calls, alerts)
- Add to api/readings.php and api/battery.php exception handlers

---

### 7. Add Health Check Endpoints
**Difficulty**: Easy | **Time**: 1 hour
**Implementation**: Create `api/health.php`
```php
<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

$health = [
    'status' => 'healthy',
    'timestamp' => date(DateTime::ISO8601),
    'version' => APP_VERSION,
    'checks' => [
        'database' => checkDatabase(),
        'disk_space' => checkDiskSpace(),
        'php_version' => PHP_VERSION,
        'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
    ]
];

// Return unhealthy if any critical check fails
if (!$health['checks']['database']['ok'] || !$health['checks']['disk_space']['ok']) {
    $health['status'] = 'unhealthy';
    http_response_code(503);
}

echo json_encode($health, JSON_PRETTY_PRINT);

function checkDatabase(): array {
    try {
        $db = getDB();
        $result = $db->query('SELECT 1')->fetch();
        return ['ok' => true, 'message' => 'Connected'];
    } catch (Exception $e) {
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function checkDiskSpace(): array {
    $free = disk_free_space('/');
    $percentFree = ($free / disk_total_space('/')) * 100;
    return [
        'ok' => $percentFree > 5,
        'free_percent' => round($percentFree, 2),
        'message' => $percentFree > 5 ? 'OK' : 'Low disk space'
    ];
}
```

**Usage**: Monitor with tools like Prometheus, DataDog, or Ping
```bash
curl https://microgrid.example.com/api/health.php
# Interval: 60 seconds
```

---

### 8. Suppress Error Display in Production
**Difficulty**: Easy | **Time**: 30 minutes
**Implementation**: Update `config/database.php`
```php
// After loadEnv()
$appEnv = getEnv('APP_ENV', 'production');

if ($appEnv === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);  // Still log, just don't display
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
}
```

Update all API responses to not leak errors:
```php
catch (Exception $e) {
    http_response_code(500);
    $message = APP_ENV === 'production' 
        ? 'Internal server error' 
        : $e->getMessage();
    echo json_encode(['error' => $message]);
    Logger::error('API Error', $e);
    exit;
}
```

---

## 📌 MEDIUM PRIORITY TASKS - IMPLEMENTATION GUIDE

### 9. Add Automated Backup Script
**File**: `scripts/backup.ps1` (PowerShell) or `scripts/backup.sh` (Bash)
**Backup Strategy**:
1. Daily incremental backup (last 7 days)
2. Weekly full backup (last 4 weeks) 
3. Monthly full backup (last 12 months)

**PowerShell Implementation**:
```powershell
# scripts/backup.ps1
param(
    [string]$BackupDir = "C:\backups\microgrid",
    [int]$RetentionDays = 30
)

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$backup = "$BackupDir\microgrid_$timestamp.sql"

# Create backup directory
if (-not (Test-Path $BackupDir)) { New-Item -ItemType Directory -Path $BackupDir | Out-Null }

# Backup database
& "C:\xampp\mysql\bin\mysqldump.exe" `
    -u root `
    --all-databases `
    --result-file=$backup

# Compress
Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory($backup, "$backup.zip")
Remove-Item $backup

# Cleanup old backups
Get-ChildItem $BackupDir -Filter "*.zip" | Where-Object {
    $_.LastWriteTime -lt (Get-Date).AddDays(-$RetentionDays)
} | Remove-Item

Write-Host "Backup created: $backup.zip"
```

**Schedule with Windows Task Scheduler**:
1. Open Task Scheduler
2. Create Basic Task: "MicroGrid Backup"
3. Trigger: Daily at 2:00 AM
4. Action: `powershell.exe -ExecutionPolicy Bypass -File C:\path\to\backup.ps1`

---

### 10. Implement 2FA Support
**Approach**: TOTP (Time-based One-Time Password) via authenticator apps
**Files to Create**:
- `includes/totp.php` - TOTP generation/verification
- Database migration for `user_2fa_secret` column

**Implementation Overview**:
1. User enables 2FA in profile
2. Generate secret key: use `google-authenticator/php-qrcode` library
3. Display QR code for scanning
4. User verifies by entering 6-digit code
5. Store encrypted secret in database
6. On login: prompt for 2FA code after password

**Database Changes**:
```sql
ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) DEFAULT NULL;
ALTER TABLE users ADD COLUMN totp_enabled TINYINT(1) DEFAULT 0;
```

**Library Recommendation**: `sonata-project/totp-bundle` or `leemunroe/qrcode-php`

---

### 11. Generate OpenAPI Documentation
**File**: Create `openapi.yaml` in root directory
```yaml
openapi: 3.0.0
info:
  title: MicroGrid Pro API
  version: 1.0.0
  description: IoT & Analytics API for smart microgrid management
  
servers:
  - url: https://microgrid.example.com

paths:
  /api/readings.php:
    post:
      summary: Record energy reading
      parameters:
        - in: header
          name: X-API-Key
          required: true
          schema:
            type: string
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [microgrid_id, voltage, current_amp, power_kw]
              properties:
                microgrid_id:
                  type: integer
                  example: 1
                voltage:
                  type: number
                  format: float
                  example: 327.4
                current_amp:
                  type: number
                  format: float
                  example: 8.6
                power_kw:
                  type: number
                  format: float
                  example: 2.81
                energy_kwh:
                  type: number
                  format: float
                  example: 0.70
                temperature:
                  type: number
                  format: float
                  example: 34.1
      responses:
        '200':
          description: Reading recorded successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                  reading_id:
                    type: integer
                  message:
                    type: string
```

**Tools**:
- Use Swagger Editor (https://editor.swagger.io/) to visualize
- Generate from code: `doctrine/annotations` + `zircote/swagger-php`

---

### 12. Add Email Notifications for Alerts
**Files to Create**:
- `includes/mailer.php` - Email service
- Database migration for notification preferences

**Implementation**:
```php
// includes/mailer.php
class Mailer {
    public static function sendAlert(int $familyId, array $alert, string $recipientEmail): bool {
        $subject = "[" . $alert['severity'] . "] " . $alert['message'];
        $body = "Alert: {$alert['message']}\nType: {$alert['alert_type']}\nTime: " . date('Y-m-d H:i:s');
        
        return mail(
            $recipientEmail,
            $subject,
            $body,
            'From: ' . getEnv('MAIL_FROM_EMAIL')
        );
    }
}
```

**Integrate into**:  
- `includes/functions.php` - Call after `checkAndGenerateAlerts()` and `checkBatteryAlerts()` 
- Add email preferences table for family notification settings

---

### 13. Implement Data Retention Policies
**Files**: Database migration + cron job cleanup script

**Database Changes**:
```sql
CREATE TABLE IF NOT EXISTS data_retention_policies (
    policy_id INT AUTO_INCREMENT PRIMARY KEY,
    data_type VARCHAR(50),  -- 'readings', 'battery_status', 'logs'
    archive_after_days INT,
    delete_after_days INT,
    active TINYINT(1) DEFAULT 1
);

INSERT INTO data_retention_policies (data_type, archive_after_days, delete_after_days) VALUES
('energy_readings', 90, 730),    -- Archive after 90 days, delete after 2 years
('battery_status', 90, 730),
('system_logs', 30, 365),        -- Logs: archive at 30 days, delete at 1 year
('alerts', 7, 90);
```

**Cleanup Script** (`scripts/cleanup_old_data.php`):
```php
<?php
require_once __DIR__ . '/../config/database.php';

$db = getDB();
$policies = $db->query("SELECT * FROM data_retention_policies WHERE active = 1")->fetchAll();

foreach ($policies as $policy) {
    $table = 'energy_readings'; // Map policy.data_type to table
    $deleteDate = date('Y-m-d', strtotime("-{$policy['delete_after_days']} days"));
    
    $stmt = $db->prepare("DELETE FROM $table WHERE timestamp < ?");
    $stmt->execute([$deleteDate . ' 00:00:00']);
    
    echo "Deleted old {$policy['data_type']}: " . $db->rowCount() . " rows\n";
}
```

**Schedule**: Run daily via cron or Task Scheduler

---

## 🔒 SECURITY HARDENING CHECKLIST

- [ ] HTTPS enforcement (.htaccess)
- [ ] Disable PHP error display in production
- [ ] Add Content-Security-Policy headers
- [ ] Add X-Frame-Options: DENY header
- [ ] Add X-Content-Type-Options: nosniff header
- [ ] Session cookie: HttpOnly + Secure + SameSite=Lax
- [ ] Remove X-Powered-By header
- [ ] Update Apache .htaccess to prevent directory listing
- [ ] Restrict file uploads (if implemented)
- [ ] Regular security audits with OWASP Top 10

---

## 📊 TESTING & VALIDATION PLAN

Before Production Release:

1. **Unit Tests** (PHPUnit):
   - Test validation functions in `includes/validation.php`
   - Test rate limiting logic in `includes/ratelimit.php`
   - Test .env loading in `config/env.php`

2. **Integration Tests**:
   - IoT readings API with invalid inputs
   - Rate limiting enforcement
   - HTTPS redirect functionality

3. **Load Testing**:
   - Use Apache Bench: `ab -n 1000 -c 10 https://microgrid.example.com/api/readings.php`
   - Verify rate limits trigger correctly

4. **Security Testing**:
   - OWASP ZAP scan for vulnerabilities
   - Manual SQL injection attempts
   - XSS payload testing

---

## 📈 Next Steps

**Immediate (This Week)**:
1. Test and verify completed tasks work correctly
2. Create .env file from .env.example
3. Run updated IoT APIs against test payloads

**Short Term (Next 2 Weeks)**:
4. Implement HTTPS enforcement
5. Implement centralized logging
6. Add health check endpoint

**Medium Term (Month 2)**:
7. Implement 2FA
8. Add email notifications
9. Create automated backup system

**Long Term (Month 3+)**:
10. Refactor to service/repository pattern
11. Add comprehensive test suite
12. Implement message queue for reliability

---

