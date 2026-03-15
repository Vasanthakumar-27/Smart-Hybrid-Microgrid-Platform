# MicroGrid Pro - Automated Backup Configuration

## Overview
This directory contains automated backup scripts to protect your MicroGrid Pro database and application files.

### Scripts

#### 1. **backup.ps1** (PowerShell - Recommended for Windows)
Comprehensive backup script with compression and rotation.

**Features:**
- Database dump using mysqldump
- Optional full application backup
- Automatic ZIP compression
- Old backup cleanup (configurable retention)
- Detailed logging

**Usage:**
```powershell
# Basic database backup
.\backup.ps1

# Full system backup with compression (default)
.\backup.ps1 -full

# Custom retention (keep 14 days)
.\backup.ps1 -daysToKeep 14

# Compression disabled
.\backup.ps1 -compress:$false

# Custom backup location
.\backup.ps1 -backupPath "D:\Backups\MicroGrid"
```

#### 2. **backup.php** (PHP - Cross-platform)
PHP-based backup for scheduling via cron or Task Scheduler.

**Features:**
- Pure PHP (no MySQL client required)
- Cross-platform compatibility (Windows, Linux, Mac)
- Structured SQL dump with metadata
- Optional ZIP compression
- Automatic old backup cleanup

**Usage:**
```bash
# Basic backup
php backup.php

# With compression
php backup.php --compress

# Custom retention period
php backup.php --compress --days-to-keep=14

# Custom output directory
php backup.php --output-dir=/var/backups/microgrid
```

#### 3. **restore.php** (PHP)
Restore database from backup files (SQL or ZIP).

**Features:**
- Restores from .sql or .sql.zip files
- Automatic ZIP extraction
- Command-line interface
- Error handling with detailed logging

**Usage:**
```bash
php restore.php <backup_file.sql|backup_file.sql.zip>

# Example
php restore.php backups/db_backup_20260315_143022.sql
php restore.php backups/db_backup_20260315_143022.sql.zip
```

---

## Setting Up Automated Backups

### Option 1: Windows Task Scheduler (Recommended)

#### For PowerShell Script (backup.ps1)

1. **Open Task Scheduler:**
   - Press `Win + R`, type `taskschd.msc`, press Enter

2. **Create Basic Task:**
   - Click "Create Basic Task..." in right panel
   - Name: "MicroGrid Pro - Database Backup"
   - Description: "Daily automated database backup"
   - Click Next

3. **Set Trigger:**
   - Select "Daily"
   - Set time: 2:00 AM (or preferred time)
   - Click Next

4. **Set Action:**
   - Select "Start a program"
   - Program/script: `powershell.exe`
   - Add arguments:
     ```
     -ExecutionPolicy Bypass -File "V:\Documents\VS CODE\DBMS AND BI\database\backup.ps1" -compress -daysToKeep 7
     ```
   - Click Next

5. **Review and Create:**
   - Check "Open the Properties dialog for this task when I click Finish"
   - Click Finish
   - In Properties dialog:
     - Go to "General" tab
     - Check "Run with highest privileges"
     - Click OK

#### For PHP Script (backup.php)

1. **Create Task Scheduler Entry:**
   - Program/script: `C:\xampp\php\php.exe` (or your PHP installation path)
   - Add arguments:
     ```
     "V:\Documents\VS CODE\DBMS AND BI\database\backup.php" --compress --days-to-keep=7
     ```
   - Schedule: Daily at 2:00 AM
   - Run with highest privileges

### Option 2: Linux/macOS - Cron Job

Add to crontab (run `crontab -e`):

```bash
# Run daily at 2 AM
0 2 * * * php /var/www/microgrid/database/backup.php --compress

# Run every 6 hours
0 */6 * * * php /var/www/microgrid/database/backup.php --compress

# Run every Sunday at 1 AM (full backup on weekends)
0 1 * * 0 php /var/www/microgrid/database/backup.php --compress --days-to-keep=30
```

### Option 3: Manual Backup

```powershell
cd "V:\Documents\VS CODE\DBMS AND BI\database"
.\backup.ps1 -full -daysToKeep 7
```

---

## Backup Configuration

Edit the scripts to customize:

### In backup.ps1:
```powershell
$dbHost = "127.0.0.1"      # Database host
$dbPort = 3306              # MySQL port
$dbUser = "root"            # Database user
$dbPass = ""                # Database password (use .env in production)
$dbName = "microgrid_db"    # Database name
```

### In backup.php:
Database credentials are read from `config/database.php` using `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME` constants.

---

## Backup File Structure

```
database/
├── backups/
│   ├── db_backup_20260315_143022.sql          # Uncompressed backup
│   ├── db_backup_20260315_143022.sql.zip      # Compressed backup
│   ├── db_backup_20260319_020000.sql.zip      # Older backups
│   ├── full_20260315_143022.zip               # Full system backup (optional)
│   └── backup.log                              # Backup operation log
```

---

## Monitoring & Verification

### Check Backup Status

**PowerShell:**
```powershell
Get-ChildItem -Path "database\backups" -File | Format-Table Name, @{n='Size (MB)';e={[math]::Round($_.Length/1MB,2)}}, LastWriteTime
```

**View Backup Log:**
```powershell
Get-Content -Path "database\backups\backup.log" -Tail 50
```

### Verify Backup Integrity

```bash
# Check SQL file
php database/restore.php database/backups/db_backup_20260315_143022.sql --dry-run

# List ZIP contents
unzip -l database/backups/db_backup_20260315_143022.sql.zip
```

---

## Restore Procedures

### Emergency Restore

```bash
# 1. Stop application
# 2. Backup current database (optional)
# 3. Restore from backup
php database/restore.php database/backups/db_backup_20260315_143022.sql

# 4. Verify restore
# 5. Start application
```

### Test Restore (to temporary database)

```bash
# Create test database
mysql -u root -p -e "CREATE DATABASE microgrid_test;"

# Restore to test database
mysql -u root -p microgrid_test < database/backups/db_backup_20260315_143022.sql

# Verify data
mysql -u root -p -e "SELECT COUNT(*) FROM microgrid_test.readings;"

# Cleanup
mysql -u root -p -e "DROP DATABASE microgrid_test;"
```

---

## Best Practices

1. **Retention Policy:**
   - Keep daily backups for 7 days
   - Keep weekly backups for 4 weeks
   - Keep monthly backups for 12 months
   - Adjust `daysToKeep` parameter accordingly

2. **Offsite Storage:**
   - Copy backups to external drive weekly
   - Upload to cloud storage (S3, Azure, etc.)
   - Script example:
     ```powershell
     Copy-Item -Path "database\backups\*.zip" -Destination "D:\Backups\MicroGrid" -Force
     ```

3. **Monitoring:**
   - Check backup.log regularly
   - Set up alerts for failed backups
   - Monitor backup directory disk usage

4. **Testing:**
   - Test restore procedures quarterly
   - Verify backup files are valid
   - Document restore RTO/RPO

5. **Security:**
   - Store backups on encrypted drives
   - Restrict access to backup directory
   - Use strong database passwords
   - Never commit .env or backup files to Git

6. **Documentation:**
   - Document backup schedule
   - Keep recovery runbook updated
   - Maintain list of backup locations
   - Document database schema changes

---

## Troubleshooting

### Issue: "mysqldump not found"
- **Solution:** Ensure MySQL is installed and `C:\xampp\mysql\bin` is in PATH
- Alternatively, use `backup.php` (PHP-based, no MySQL client needed)

### Issue: "Access Denied" when connecting to MySQL
- **Solution:** Update database credentials in scripts
- Must match `DB_USER` and `DB_PASS` from config/database.php

### Issue: Backup file is very small/empty
- **Problem:** Database connection failed
- **Solution:** Check MySQL is running: `net start MySQL80`
- Verify credentials in config/database.php

### Issue: Disk space full
- **Solution:** Reduce retention period or enable compression
- Move old backups to external drive
- Check `backup.log` for file sizes

---

## Integration with Application

The backup system integrates with MicroGrid Pro's centralized logging:
- Backup events logged to `logs/app.log`
- Backup errors available in health check endpoint
- Logger class supports monitoring backup status

Monitor backup health:
```bash
curl http://localhost/api/health.php
```

---

## Database Maintenance

After restoring from backup, run maintenance:

```sql
-- Check database integrity
CHECK TABLE readings, battery_status, users, families;
ANALYZE TABLE readings, battery_status, users, families;
OPTIMIZE TABLE readings, battery_status, users, families;

-- Check disk space
SHOW VARIABLES LIKE 'datadir';
```

---

## Contact & Support

For backup issues or recovery assistance, refer to:
- `IMPLEMENTATION_PLAN.md` - Task 9 detailed guide
- `WORK_COMPLETION_SUMMARY.md` - Project status
- Database logs: `logs/app.log`
- Backup logs: `database/backups/backup.log`
