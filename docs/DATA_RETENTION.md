# Data Retention & Cleanup Policies

## Overview

The MicroGrid Pro data retention system automatically deletes old data according to configurable retention policies. This helps manage database size, improve performance, and comply with data privacy regulations.

**Key Benefits:**
- 📦 Reduces database size and storage costs
- ⚡ Improves query performance (fewer rows to scan)
- 🔐 Complies with data privacy regulations (GDPR right to be forgotten)
- 🔄 Automated cleanup via cron/Task Scheduler
- 🧪 Dry-run mode to preview deletions without executing
- 📊 Configurable retention periods per data type

---

## Configuration

### Environment Variables (.env)

```bash
# Enable/disable data retention policies
RETENTION_ENABLED=true

# Retention periods (in days)
RETENTION_ALERT_DAYS=90              # Resolved alerts: 90 days (~3 months)
RETENTION_READING_DAYS=365           # Energy readings: 365 days (1 year)
RETENTION_BATTERY_DAYS=90            # Battery status: 90 days
RETENTION_LOG_DAYS=30                # System logs: 30 days
RETENTION_EMAIL_LOG_DAYS=180         # Email logs: 180 days (~6 months)
RETENTION_NOTIFICATION_LOG_DAYS=90   # Notification queue: 90 days

# Preview mode (don't actually delete)
RETENTION_DRY_RUN=false
```

### Data Types and Retention Periods

| Data Type | Default | Reason | Notes |
|-----------|---------|--------|-------|
| **Energy Readings** | 365 days | Historical analytics | Oldest data often least useful |
| **Resolved Alerts** | 90 days | Audit trail | Critical/active alerts kept longer |
| **Battery Status** | 90 days | Health monitoring | Daily aggregates sufficient |
| **System Logs** | 30 days | Troubleshooting | Critical logs kept longer |
| **Email Logs** | 180 days | Compliance | Failed emails kept 90+ days |
| **Notification Queue** | 90 days | Delivery tracking | Sent only, failed entries preserved |

---

## Automated Cleanup

### Linux/Mac: Cron Job

Add to your crontab (`crontab -e`):

```bash
# Run cleanup daily at 2:00 AM
0 2 * * * /usr/bin/php /var/www/html/microgrid/database/cleanup.php >> /var/log/microgrid-cleanup.log 2>&1

# Alternative: Run at 3:00 AM on Sundays
0 3 * * 0 /usr/bin/php /var/www/html/microgrid/database/cleanup.php

# With verbose logging
0 2 * * * /usr/bin/php /var/www/html/microgrid/database/cleanup.php --verbose >> /var/log/microgrid-cleanup.log 2>&1
```

### Windows: Task Scheduler

**Method 1: Create via GUI**

1. Open **Task Scheduler** (taskschd.msc)
2. Click **Create Basic Task** (right panel)
3. **Name:** MicroGrid Cleanup
4. **Description:** Automatic data retention cleanup
5. **Trigger:** Set to daily at 2:00 AM
6. **Action:** Start a program
   - **Program/script:** `C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe`
   - **Arguments:** `-NoProfile -ExecutionPolicy Bypass -File "C:\xampp\htdocs\microgrid\database\cleanup.ps1"`
   - **Start in:** (leave blank)
7. **Options:**
   - ✓ Run with highest privileges
   - ✓ Run whether user is logged in or not
8. Click **Finish**

**Method 2: Create via PowerShell**

```powershell
# Run as Administrator
$action = New-ScheduledTaskAction `
  -Execute 'C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe' `
  -Argument '-NoProfile -ExecutionPolicy Bypass -File "C:\xampp\htdocs\microgrid\database\cleanup.ps1"'

$trigger = New-ScheduledTaskTrigger -Daily -At 2:00AM

$principal = New-ScheduledTaskPrincipal -UserID "SYSTEM" -LogonType ServiceAccount -RunLevel Highest

Register-ScheduledTask `
  -Action $action `
  -Trigger $trigger `
  -Principal $principal `
  -TaskName "MicroGrid Cleanup" `
  -Description "Automatic data retention cleanup"

# Verify creation
Get-ScheduledTask -TaskName "MicroGrid Cleanup"
```

---

## CLI Usage

### Basic Cleanup

Run cleanup with default settings:

```bash
# Linux/Mac
php database/cleanup.php

# PowerShell (Windows)
.\database\cleanup.ps1
```

### Dry Run Mode

Preview what would be deleted without actually deleting:

```bash
# Linux/Mac
php database/cleanup.php --dry-run

# PowerShell (Windows)
.\database\cleanup.ps1 -DryRun
```

**Output Example:**
```
╔════════════════════════════════════════════════════╗
║        MicroGrid Pro - Data Retention Cleanup      ║
║                 2026-03-16 14:30:15                ║
╚════════════════════════════════════════════════════╝

⚠️  DRY RUN MODE - No data will be deleted

🗑️  Running data retention cleanup...
──────────────────────────────────────────────────

📋 Cleanup Results
──────────────────────────────

✓ Resolved Alerts                    12,543 rows
✓ Old Energy Readings               123,456 rows
✓ Old Battery Status                 45,678 rows
✓ System Logs                         8,901 rows
✓ Email Logs                          2,345 rows
✓ Notification Queue                 1,234 rows
─ Failed Queue Entries                    0 rows

──────────────────────────────
✓ Total Rows Deleted: 194,157

⚠️  DRY RUN - No data was actually deleted

Log file: logs/app.log
```

### Statistics Only

Show current retention statistics without cleanup:

```bash
# Linux/Mac
php database/cleanup.php --stats

# PowerShell (Windows)
.\database\cleanup.ps1 -StatsOnly
```

**Output Example:**
```
📊 Current Data Retention Statistics
──────────────────────────────────────────────────

Alerts:
  Total:         98,765
  Active:        4,321
  Old (resolved): 12,543

Energy Readings:
  Total:         456,789
  Older than 365 days: 123,456

Battery Status:
  Total:         234,567
  Older than 90 days: 45,678

System Logs:
  Total:         45,678
  Older than 30 days: 8,901

For more details, check: logs/app.log
```

### Verbose Mode

Show detailed information about each cleanup operation:

```bash
# Linux/Mac
php database/cleanup.php --verbose

# PowerShell (Windows)
.\database\cleanup.ps1 -Verbose
```

---

## Cleanup Operations

### 1. Resolved Alerts (`RETENTION_ALERT_DAYS`)

**What:** Deletes alerts with status='resolved'  
**Default:** 90 days  
**Reason:** Historical record of resolved issues  
**Critical:** ✗ (Active/acknowledged alerts preserved)

```sql
DELETE FROM alerts 
WHERE status = 'resolved' 
AND timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY)
```

### 2. Old Energy Readings (`RETENTION_READING_DAYS`)

**What:** Historical sensor readings  
**Default:** 365 days  
**Reason:** Long-term analytics still useful, older data rarely accessed  
**Critical:** ✗ (Recent data critical for analysis)

```sql
DELETE FROM energy_readings 
WHERE timestamp < DATE_SUB(NOW(), INTERVAL 365 DAY)
```

### 3. Old Battery Status (`RETENTION_BATTERY_DAYS`)

**What:** Battery state-of-charge history  
**Default:** 90 days  
**Reason:** Daily aggregates maintain long-term view  
**Critical:** ✗ (Recent battery health is important)

```sql
DELETE FROM battery_status 
WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY)
```

### 4. System Logs (`RETENTION_LOG_DAYS`)

**What:** Application operational logs  
**Default:** 30 days  
**Reason:** Troubleshooting and debugging  
**Critical:** ⚡ (Critical/warning logs preserved longer)

```sql
DELETE FROM system_logs 
WHERE timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY)
AND (severity = 'info' OR timestamp < DATE_SUB(NOW(), INTERVAL 60 DAY))
```

Info logs deleted first, critical/warning kept longer for investigation.

### 5. Email Logs (`RETENTION_EMAIL_LOG_DAYS`)

**What:** Notification delivery records  
**Default:** 180 days  
**Reason:** Compliance and delivery tracking  
**Critical:** ⚡ (Failed emails kept longer)

```sql
DELETE FROM email_notification_log 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)
AND (status = 'sent' OR created_at < DATE_SUB(NOW(), INTERVAL 90 DAY))
```

Sent emails deleted first, failed emails kept 90+ days for diagnostics.

### 6. Notification Queue (`RETENTION_NOTIFICATION_LOG_DAYS`)

**What:** Email delivery queue history  
**Default:** 90 days  
**Reason:** Track pending/delivered notifications  
**Critical:** ⚡ (Failed deliveries preserved)

```sql
DELETE FROM email_notification_queue 
WHERE status = 'sent' 
AND sent_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
```

```sql
DELETE FROM email_notification_queue 
WHERE attempts >= 5 
AND status IN ('failed', 'bounced')
AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
```

### 7. Failed Queue Entries

**What:** Permanently failed email delivery attempts  
**Default:** 30 days  
**Reason:** Diagnostic history  
**Critical:** ✗

Only deleted if 5+ delivery attempts have failed.

---

## Performance Impact

### Before Cleanup

```
Typical production database:
- energy_readings:     500,000+ rows
- battery_status:      300,000+ rows
- system_logs:         200,000+ rows
- alerts:              150,000+ rows
- Total size:          ~ 2-3 GB

Full table scan performance:
- Query energy by date: 5-10 seconds
- Analytics queries:    8-15 seconds
- Report generation:    30-60 seconds
```

### After Cleanup (365-day active data)

```
Optimized database:
- energy_readings:     100,000 rows (1 year active)
- battery_status:      50,000 rows (90 days)
- system_logs:         20,000 rows (30 days)
- alerts:              50,000 rows (active + 90 days resolved)
- Total size:          ~ 500 MB - 1 GB

Improved performance:
- Query energy by date: < 1 second
- Analytics queries:    1-3 seconds
- Report generation:    5-15 seconds

Improvement: 5-10x faster queries
```

---

## Monitoring and Logging

### Cleanup Log File

Location: `logs/cleanup.log` (and `logs/app.log`)

**Log Entry Example:**
```
[2026-03-16 02:00:15] Starting data retention cleanup...
[2026-03-16 02:00:15] Arguments: --dry-run
[2026-03-16 02:00:18] Alert cleanup: 12543 rows
[2026-03-16 02:00:25] Energy readings cleanup: 123456 rows
[2026-03-16 02:00:45] Battery status cleanup: 45678 rows
[2026-03-16 02:00:50] System logs cleanup: 8901 rows
[2026-03-16 02:00:55] Email logs cleanup: 2345 rows
[2026-03-16 02:01:00] Notification queue cleanup: 1234 rows
[2026-03-16 02:01:05] Failed queue cleanup: 0 rows
[2026-03-16 02:01:05] Data retention cleanup completed. Deleted 194157 total rows.
[2026-03-16 02:01:05] ✓ Cleanup completed successfully
```

### Check Last Cleanup

```bash
# View cleanup log
tail -50 logs/cleanup.log

# Search for errors
grep -i error logs/cleanup.log
```

### Verify Cleanup Execution

```sql
-- Check when each table was last modified
SELECT table_name, UPDATE_TIME
FROM information_schema.tables
WHERE table_schema = 'microgrid_platform'
ORDER BY UPDATE_TIME DESC;

-- Count old data that should have been deleted
SELECT COUNT(*) as old_readings
FROM energy_readings 
WHERE timestamp < DATE_SUB(NOW(), INTERVAL 365 DAY);
```

---

## Compliance & Data Privacy

### GDPR Compliance

**Right to be Forgotten**

Implement user data deletion in addition to retention policies:

```php
// Delete all data for a user
function deleteUserData($userId) {
    // 1. Find all families where user is a member
    // 2. Delete email notifications for this user
    // 3. Delete user account
    // 4. If user is last admin of family, delete family (or archive)
}
```

### Retention Schedule Recommendations

| Regulation | Retention Period | Applies To |
|-----------|------------------|-----------|
| **GDPR** | Minimal necessary | All personal data, email logs |
| **PCI DSS** | 7 years | Payment/financial data (if applicable) |
| **SOC 2** | 6-12 months | Audit logs, security events |
| **HIPAA** | 6 years | Any health-related data |
| **Default** | 1 year | General operational data |

---

## Troubleshooting

### Problem: Cleanup not running

**Check 1: Is retention enabled?**
```bash
grep RETENTION_ENABLED .env
```

**Check 2: Is cron/Task Scheduler configured?**

On Linux:
```bash
crontab -l | grep cleanup
sudo systemctl status cron
```

On Windows:
```powershell
Get-ScheduledTask -TaskName "MicroGrid Cleanup"
Get-ScheduledTaskInfo -TaskName "MicroGrid Cleanup"
```

**Check 3: Recent cleanup execution**
```bash
# Last 10 entries in cleanup log
tail -10 logs/cleanup.log

# Check modification time of logs
ls -la logs/
```

### Problem: Cleanup runs but deletes too much

**Solution: Test with dry run**
```bash
php database/cleanup.php --dry-run --verbose
```

**Adjust retention periods:**
```bash
# Increase retention in .env
RETENTION_ALERT_DAYS=180        # from 90 to 180 days
RETENTION_READING_DAYS=730      # from 365 to 730 days (2 years)
```

### Problem: Cleanup takes too long

**Solution 1: Run during off-peak hours**
```bash
# Schedule for midnight instead of 2 AM
0 0 * * * /usr/bin/php /var/www/html/microgrid/database/cleanup.php
```

**Solution 2: Reduce retention periods to delete more data faster**
```bash
# Delete data more aggressively
RETENTION_ALERT_DAYS=30         # from 90 to 30 days
RETENTION_LOG_DAYS=7            # from 30 to 7 days
```

**Solution 3: Manually clean high-volume tables**
```php
// One cleanup operation at a time
php database/cleanup.php 2>&1 | head -n 50  # See first 50 lines
```

### Problem: Want to understand what will be deleted

**Use dry run with verbose:**
```bash
php database/cleanup.php --dry-run --verbose
```

This shows:
- Which tables are affected
- How many rows would be deleted
- The SQL cutoff dates
- No actual deletions occur

---

## Advanced Usage

### Custom Cleanup Script

Create a custom cleanup for your specific needs:

```php
<?php
require_once 'includes/retention.php';

// Run cleanup in dry-run mode to preview
DataRetention::init(true);
$results = DataRetention::runAll();

// Examine results
foreach ($results['policies'] as $policy) {
    echo "Policy: " . $policy['name'] . "\n";
    echo "Would delete: " . $policy['deleted'] . " rows\n";
    echo "Reason: " . $policy['reason'] . "\n";
}

// If satisfied, run for real
DataRetention::init(false);
$realResults = DataRetention::runAll();
echo "Actually deleted: " . $realResults['total_deleted'] . " rows\n";
?>
```

### Selective Cleanup

Delete only specific tables:

```sql
-- Just energy readings older than 2 years
DELETE FROM energy_readings WHERE timestamp < DATE_SUB(NOW(), INTERVAL 730 DAY);

-- Just inactive alerts
DELETE FROM alerts WHERE status = 'resolved' AND timestamp < DATE_SUB(NOW(), INTERVAL 180 DAY);

-- Just system logs (keep critical only)
DELETE FROM system_logs WHERE severity = 'info' AND timestamp < DATE_SUB(NOW(), INTERVAL 14 DAY);
```

### Backup Before Cleanup

Create a backup before running cleanup:

```bash
# Backup database
mysqldump microgrid_platform > backup_$(date +%Y%m%d_%H%M%S).sql

# Run cleanup
php database/cleanup.php

# Verify results...
# If issues, restore from backup
mysql microgrid_platform < backup_20260316_140000.sql
```

---

## Database Optimization After Cleanup

After running cleanup, optimize table efficiency:

```sql
-- Reclaim disk space from deleted rows
OPTIMIZE TABLE alerts;
OPTIMIZE TABLE energy_readings;
OPTIMIZE TABLE battery_status;
OPTIMIZE TABLE system_logs;
OPTIMIZE TABLE email_notification_log;
OPTIMIZE TABLE email_notification_queue;

-- Rebuild indexes
ANALYZE TABLE alerts;
ANALYZE TABLE energy_readings;
ANALYZE TABLE battery_status;
```

Or using PHP:

```php
<?php
require_once 'config/database.php';

$db = getDB();
$tables = ['alerts', 'energy_readings', 'battery_status', 'system_logs', 'email_notification_log'];

foreach ($tables as $table) {
    echo "Optimizing $table...\n";
    $db->exec("OPTIMIZE TABLE $table");
    $db->exec("ANALYZE TABLE $table");
    echo "✓ Done\n";
}
?>
```

---

## FAQ

**Q: Will cleanup delete active alerts?**  
A: No. Only "resolved" alerts are deleted.

**Q: Can I restore deleted data?**  
A: Yes, if you have database backups. Restore from backup: `mysql < backup.sql`

**Q: How much storage will we save?**  
A: Typical savings: 60-80% reduction after 1 year of operation (deleting 1-2 year old data).

**Q: What if I need to keep data longer for compliance?**  
A: Increase retention days in `.env` or customize cleanup policies for your regulations.

**Q: Can cleanup run during business hours?**  
A: Yes, but it may impact query performance. Recommend off-peak (2-4 AM).

**Q: What if cleanup fails?**  
A: Check `logs/cleanup.log` for error details. Failures are non-destructive (no partial deletes).

**Q: How often should I run cleanup?**  
A: Daily is recommended. You can also run weekly or monthly depending on data volume.

---

## Support

For issues or questions:
1. Check `logs/cleanup.log` for error messages
2. Run `php database/cleanup.php --stats --verbose` to diagnose
3. Test with `--dry-run` mode before actual deletion
4. Review configuration in `.env`
5. Consult database documentation for specific MySQL/MariaDB query syntax

