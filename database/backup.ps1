# MicroGrid Pro - Automated Database Backup Script
# Usage: .\backup.ps1 [--full] [--compress] [--days-to-keep 7]
# Designed to run via Windows Task Scheduler for automated backups

param(
    [switch]$full = $false,
    [switch]$compress = $true,
    [int]$daysToKeep = 7,
    [string]$backupPath = "$(Split-Path -Parent $MyInvocation.MyCommand.Path)\backups"
)

$ErrorActionPreference = 'Stop'

# Configuration
$mysqlExe = "C:\xampp\mysql\bin\mysqldump.exe"
$mysqlBin = "C:\xampp\mysql\bin\mysql.exe"
$timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$backupDir = $backupPath
$logFile = Join-Path $backupDir "backup.log"
$dbHost = "127.0.0.1"
$dbPort = 3306
$dbUser = "root"
$dbPass = ""  # Update from .env in production
$dbName = "microgrid_db"

# Ensure backup directory exists
if (-not (Test-Path $backupDir)) {
    New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
}

# Initialize log file
$logEntry = "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] Backup Started (Full: $full, Compress: $compress, KeepDays: $daysToKeep)"
Add-Content -Path $logFile -Value $logEntry
Write-Host $logEntry

try {
    # 1. Database Backup
    $dbBackupFile = Join-Path $backupDir "db_${dbName}_${timestamp}.sql"
    Write-Host "Backing up database to $dbBackupFile..."
    
    # Build mysqldump command
    $mysqldumpCmd = @(
        $mysqlExe,
        "--host=$dbHost",
        "--port=$dbPort",
        "--user=$dbUser",
        "--no-tablespaces"
    )
    
    if (![string]::IsNullOrEmpty($dbPass)) {
        $mysqldumpCmd += "--password=$dbPass"
    }
    
    $mysqldumpCmd += $dbName
    
    # Execute backup
    & $mysqldumpCmd | Out-File -FilePath $dbBackupFile -Encoding UTF8
    
    if (Test-Path $dbBackupFile) {
        $dbSize = (Get-Item $dbBackupFile).Length / 1MB
        $logEntry = "✓ Database backed up successfully (${dbSize:F2} MB)"
        Add-Content -Path $logFile -Value $logEntry
        Write-Host $logEntry
    } else {
        throw "Failed to create database backup file"
    }
    
    # 2. Compress if requested
    if ($compress) {
        $zipFile = "$dbBackupFile.zip"
        Write-Host "Compressing backup to $zipFile..."
        
        Compress-Archive -Path $dbBackupFile -DestinationPath $zipFile -CompressionLevel Optimal
        Remove-Item -Path $dbBackupFile -Force
        
        if (Test-Path $zipFile) {
            $zipSize = (Get-Item $zipFile).Length / 1MB
            $logEntry = "✓ Backup compressed (${zipSize:F2} MB)"
            Add-Content -Path $logFile -Value $logEntry
            Write-Host $logEntry
        } else {
            throw "Failed to compress backup"
        }
    }
    
    # 3. Full System Backup (optional)
    if ($full) {
        Write-Host "Creating full system backup..."
        $appPath = Split-Path -Parent (Split-Path -Parent $backupDir)
        $fullBackupFile = Join-Path $backupDir "full_${timestamp}.zip"
        
        # Exclude backup folder and cache directories
        $excludePaths = @('backups', 'node_modules', '.git', '.env')
        
        $filesToBackup = Get-ChildItem -Path $appPath -Recurse -File | 
            Where-Object { 
                $excluded = $false
                foreach ($exclude in $excludePaths) {
                    if ($_.FullName -like "*\$exclude\*") {
                        $excluded = $true
                        break
                    }
                }
                -not $excluded
            }
        
        $filesToBackup | Compress-Archive -DestinationPath $fullBackupFile -CompressionLevel Optimal
        
        if (Test-Path $fullBackupFile) {
            $fullSize = (Get-Item $fullBackupFile).Length / 1MB
            $logEntry = "✓ Full system backed up (${fullSize:F2} MB)"
            Add-Content -Path $logFile -Value $logEntry
            Write-Host $logEntry
        }
    }
    
    # 4. Cleanup old backups
    Write-Host "Cleaning up backups older than $daysToKeep days..."
    $cutoffDate = (Get-Date).AddDays(-$daysToKeep)
    $deletedCount = 0
    
    Get-ChildItem -Path $backupDir -Filter "*.sql" -File | 
        Where-Object { $_.LastWriteTime -lt $cutoffDate } |
        ForEach-Object {
            Remove-Item -Path $_.FullName -Force
            $deletedCount++
        }
    
    Get-ChildItem -Path $backupDir -Filter "*.zip" -File | 
        Where-Object { $_.LastWriteTime -lt $cutoffDate } |
        ForEach-Object {
            Remove-Item -Path $_.FullName -Force
            $deletedCount++
        }
    
    if ($deletedCount -gt 0) {
        $logEntry = "✓ Removed $deletedCount old backup(s)"
        Add-Content -Path $logFile -Value $logEntry
        Write-Host $logEntry
    }
    
    # 5. Backup Summary Stats
    $totalSize = (Get-ChildItem -Path $backupDir -File | Measure-Object -Property Length -Sum).Sum / 1MB
    $fileCount = (Get-ChildItem -Path $backupDir -File | Measure-Object).Count
    
    $logEntry = "✓ Backup Completed Successfully - $fileCount backups, Total: ${totalSize:F2} MB"
    Add-Content -Path $logFile -Value $logEntry
    Write-Host $logEntry
    
} catch {
    $errorMsg = $_.Exception.Message
    $logEntry = "✗ Backup Failed: $errorMsg"
    Add-Content -Path $logFile -Value $logEntry
    Write-Error $logEntry
    exit 1
}
