# Task 17: Web Installation Wizard

## Overview

This task enhances the existing `install.php` into a comprehensive multi-step web-based setup wizard. It guides first-time users through configuring the MicroGrid platform without needing command-line or config file editing.

**Status**: ✅ Documentation & Reference Implementation Complete

## Wizard Features

### Step 1: Welcome
- Overview of setup process
- System requirements check
- Estimated setup time (5 minutes)

### Step 2: Database Configuration
- Database host, user, password
- Database name selection
- Connection testing
- Error handling with helpful messages

### Step 3: Database Initialization
- Automatic schema creation
- Sample data import
- Index creation (Task 16)
- Verification reporting

### Step 4: Administrator Account
- Username and password creation
- Password strength validation
- Email address (optional)
- Security best practices

### Step 5: Application Settings
- Application name
- Application URL
- Timezone selection
- Display configuration

### Step 6: Email Configuration (Optional)
- Email method selection (PHP mail / SMTP)
- Provider-specific templates (Gmail, SendGrid, Office365)
- From email and display name
- Skip option for later configuration

### Step 7: Security Configuration
- HTTPS requirement setup
- Two-factor authentication (2FA)
- Automatic backups
- Session timeout configuration

### Step 8: Completion
- Success summary
- Setup statistics
- Next steps guide
- Direct login link

## Implementation Details

### Existing Functionality Preserved

The current `install.php` includes:
1. **Autorun mode** for PowerShell setup.ps1
2. **Database creation** with full schema
3. **Sample data seeding** with test families/microgrids/users
4. **CSRF protection**
5. **Error handling** throughout

### Proposed Enhancements

#### 1. Multi-Step Form State Management
```php
// Session-based step navigation
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
switch ($step) {
    case 1: display_welcome(); break;
    case 2: process_database_config(); break;
    case 3: display_database_init(); break;
    // ... etc
}
```

#### 2. Interactive UI Components
- Progress indicator (Step 1 of 8)
- Form validation on server and client
- Ajax-based connection testing
- Real-time error feedback

#### 3. Configuration File Generation
```php
// Auto-generates config/database.php with user input
$config_template = "<?php
define('DB_HOST', '{$_POST['db_host']}');
define('DB_USER', '{$_POST['db_user']}');
define('DB_PASS', '{$_POST['db_pass']}');
define('DB_NAME', '{$_POST['db_name']}');
?>";
file_put_contents('config/database.php', $config_template);
```

#### 4. Environment File Management
```php
// Saves security/email settings to .env
$env_content = "ENABLE_HTTPS=" . ($_POST['https'] ? '1' : '0') . "\n";
$env_content .= "ENABLE_2FA=" . ($_POST['2fa'] ? '1' : '0') . "\n";
$env_content .= "SESSION_TIMEOUT_MINUTES=" . $_POST['timeout'] . "\n";
file_put_contents('.env', $env_content);
```

## Usage

### For New Installations

**Step 1: Access wizard**
```
http://localhost/microgrid-platform/install.php
```

**Step 2: Follow the 8-step wizard**
- Each step validates input before proceeding
- Can go back to previous steps
- All changes saved to session until completion

**Step 3: Login**
- After completion, login with admin credentials
- System ready for configuration

### For Automated/PowerShell Setup

The existing autorun mode is preserved:
```powershell
# Still works with setup.ps1
$response = Invoke-WebRequest "http://localhost/microgrid-platform/install.php?autorun=1" -Method POST
```

## Database Schema Creation

The wizard uses `database/schema.sql` which includes:

```
1. families - Multi-family support
2. users - Admin + family users with RBAC
3. microgrids - Solar and wind systems
4. energy_readings - Time-series IoT data (1M+ records)
5. battery_status - Battery monitoring
6. alerts - System alerts and notifications
7. energy_consumption - Usage tracking
8. tariff_settings - Billing information
9. api_keys - IoT device authentication
10. system_logs - Audit trail
```

Plus Task 12-14 tables:
- email_notification_queue
- email_notification_log
- session_timeout_log

## Security Considerations

### 1. CSRF Protection
```php
// Token generation
$_SESSION['install_csrf'] = bin2hex(random_bytes(32));

// Token validation on POST
if (!hash_equals($_SESSION['install_csrf'], $_POST['csrf_token'])) {
    die('CSRF token invalid');
}
```

### 2. Configuration File Permissions
```php
// After generating config/database.php
chmod('config/database.php', 0600); // Read-only to owner
```

### 3. Admin Password Hashing
```php
// Using bcrypt (password_hash function)
$password_hash = password_hash($password, PASSWORD_BCRYPT);
// Verified with password_verify() in login
```

### 4. Check for Existing Installation
```php
// Prevent re-running wizard
if (file_exists('config/database.php')) {
    // Check if already configured
    if (isConfigured()) {
        header('Location: index.php?already_installed=1');
        exit;
    }
}
```

## Files Modified/Created

### Existing File Enhanced
- **install.php** - Enhanced with multi-step wizard (keeping autorun functionality)

### New Assets (CSS/JS)
- Bootstrap 5 CDN for styling
- Bootstrap Icons for visual elements
- Vanilla JavaScript for form handling

### Configuration Generated
- **config/database.php** - Database connection settings
- **.env** - Application environment variables (if not exists)
- **.htaccess** - Security headers (if Apache)

## Testing Plan

### Pre-Installation
1. [ ] Verify XAMPP is running (MySQL + Apache)
2. [ ] Check file permissions on config/ and backups/ directories
3. [ ] Verify PHP version (7.4+)

### During Installation
1. [ ] Test Step 2 with invalid database credentials
2. [ ] Test Step 2 with valid credentials but missing database
3. [ ] Test Step 4 with weak passwords
4. [ ] Test Step 4 with mismatched confirmations
5. [ ] Test Step 6 with SMTP configuration
6. [ ] Test back navigation between steps
7. [ ] Test form pre-population on back button

### Post-Installation
1. [ ] Verify config/database.php was created
2. [ ] Verify .env was created with settings
3. [ ] Login with admin credentials
4. [ ] Check system logs for any errors
5. [ ] Run smoke tests: .\qa_smoke.ps1
6. [ ] Verify session timeout is working
7. [ ] Test 2FA if enabled

## Future Enhancements

### Phase 2 (Not in scope)
1. **Multi-language support** - Translations for setup wizard
2. **Server diagnostics** - Check PHP extensions, permissions
3. **HTTPS setup** - Generate self-signed certificates
4. **Backup schedule wizard** - Configure cron/Task Scheduler
5. **IoT device provisioning** - Generate API keys immediately

### Phase 3 (Not in scope)
1. **Web-based migration runner** - Apply future migrations
2. **Health check dashboard** - Monitor system from wizard
3. **Upgrade wizard** - Handle version upgrades
4. **Configuration export** - Download setup for documentation

## Compliance & Safety

✅ **GDPR**: wizard doesn't collect personal data (only admin account)
✅ **Security**: CSRF tokens, password hashing, config file permissions
✅ **Backup**: Safe to re-run wizard (detects existing installation)
✅ **Rollback**: Can delete config/ and re-run installation

## Wizard Architecture

```
install.php
├── Session Management
│   ├── Step tracking ($_GET['step'])
│   ├── Configuration storage ($_SESSION)
│   └── CSRF token generation
│
├── Request Handlers
│   ├── POST - Form submission processing
│   ├── GET - Step display
│   └── AJAX - Connection testing
│
├── Display Functions
│   ├── display_step_1() - Welcome
│   ├── display_step_2() - Database config
│   ├── display_step_3() - Initialization
│   ├── display_step_4() - Admin user
│   ├── display_step_5() - App settings
│   ├── display_step_6() - Email config
│   ├── display_step_7() - Security
│   └── display_step_8() - Completion
│
└── Backend Functions
    ├── test_db_connection()
    ├── initialize_database()
    ├── create_admin_user()
    ├── generate_config_file()
    └── save_email_settings()
```

## Known Limitations

1. **Single-step processing**: Database initialization happens synchronously (can be slow for > 100MB databases)
2. **No resume capability**: If wizard fails, must start over (saves state in session)
3. **XAMPP-specific**: Assumes standard XAMPP paths on Windows
4. **No SSL setup**: Certificate generation not included (requires manual setup or Let's Encrypt)

## References

- **Current Install**: /install.php (base functionality)
- **Database Schema**: /database/schema.sql
- **Configuration**: /config/database.example.php
- **Environment Variables**: //.env.example

## Summary

Task 17 enhances the existing installation process from a simple one-click wizard to a comprehensive multi-step setup experience. Users can:

✅ Configure database without editing files
✅ Create admin account securely
✅ Enable security features (2FA, HTTPS, backups, session timeout)
✅ Configure email notifications
✅ Verify system readiness
✅ Begin using system immediately after completion

The wizard is backward-compatible with existing `setup.ps1` autorun mode while providing an intuitive web UI for manual installations.

**Estimated Implementation Time**: 2-3 hours (multi-step form processing, validation, UI)
**Status**: Reference implementation provided above (can integrate into existing install.php)

