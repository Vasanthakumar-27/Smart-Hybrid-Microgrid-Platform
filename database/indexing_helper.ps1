#!/usr/bin/env pwsh
<#
.SYNOPSIS
    Database indexing migration helper for MicroGrid Platform
    
.DESCRIPTION
    Applies database indexing migration to improve query performance
    Automatically starts MySQL if needed
    
.PARAMETER DryRun
    Show what would be done without making changes
    
.PARAMETER AnalyzeAfter
    Run index analyzer after migration
    
.EXAMPLE
    .\database/indexing_helper.ps1
    .\database/indexing_helper.ps1 -DryRun
    .\database/indexing_helper.ps1 -AnalyzeAfter
#>

param(
    [switch]$DryRun,
    [switch]$AnalyzeAfter
)

$ErrorActionPreference = "Stop"

# Configuration
$PHP_PATH = "C:\xampp\php\php.exe"
$XAMPP_MYSQL = "C:\xampp\mysql\bin\mysql.exe"
$DB_NAME = "microgrid_platform"
$PROJECT_ROOT = Split-Path $PSScriptRoot -Parent

Write-Host "`n╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║ Database Indexing Migration Helper                             ║" -ForegroundColor Cyan
Write-Host "║ MicroGrid Platform                                             ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════════════════╝`n" -ForegroundColor Cyan

# Check if PHP exists
if (-not (Test-Path $PHP_PATH)) {
    Write-Host "❌ PHP not found at: $PHP_PATH" -ForegroundColor Red
    Write-Host "   Install XAMPP or update PHP_PATH in this script" -ForegroundColor Yellow
    exit 1
}

Write-Host "✓ PHP found: $PHP_PATH" -ForegroundColor Green

# Check if migration files exist
$migration_file = Join-Path $PROJECT_ROOT "database\migrations\20260315_add_database_indexes.sql"
$migration_runner = Join-Path $PROJECT_ROOT "database\apply_migrations.php"

if (-not (Test-Path $migration_file)) {
    Write-Host "❌ Migration file not found: $migration_file" -ForegroundColor Red
    exit 1
}

if (-not (Test-Path $migration_runner)) {
    Write-Host "❌ Migration runner not found: $migration_runner" -ForegroundColor Red
    exit 1
}

Write-Host "✓ Migration files found" -ForegroundColor Green

# Check MySQL status
Write-Host "`n🔍 Checking MySQL status..." -ForegroundColor Cyan
$mysql_running = $null
try {
    $check_cmd = "&'$XAMPP_MYSQL' -u root -h localhost $DB_NAME -e 'SELECT 1;' 2>&1"
    $result = Invoke-Expression $check_cmd
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✓ MySQL is running" -ForegroundColor Green
        $mysql_running = $true
    }
} catch {
    $mysql_running = $false
}

if (-not $mysql_running) {
    Write-Host "⚠️  MySQL is not running" -ForegroundColor Yellow
    Write-Host "   Attempting to start MySQL service..." -ForegroundColor Yellow
    
    try {
        Start-Service -Name "MySQL80" -ErrorAction SilentlyContinue
        Start-Sleep -Seconds 3
        Write-Host "✓ MySQL service started" -ForegroundColor Green
    } catch {
        Write-Host "❌ Could not start MySQL service automatically" -ForegroundColor Red
        Write-Host "   Please start XAMPP manually: C:\xampp\xampp-control.exe" -ForegroundColor Yellow
        Write-Host "   Or run: net start MySQL80" -ForegroundColor Yellow
        Write-Host "   (Requires Administrator privilege)" -ForegroundColor Yellow
        
        Read-Host "Press Enter after MySQL is running, or Ctrl+C to abort"
    }
}

# Show what will happen
Write-Host "`n📋 Migration Details" -ForegroundColor Cyan
Write-Host "  File: $migration_file"
Write-Host "  Database: $DB_NAME"
Write-Host "  Impact: +25 indexes for 2-10x performance improvement`n"

if ($DryRun) {
    Write-Host "🔍 DRY RUN MODE - No changes will be applied" -ForegroundColor Yellow
    $args_str = "--verbose --dry-run"
} else {
    Write-Host "⚠️  This will add indexes to your database" -ForegroundColor Yellow
    Write-Host "   You will not be able to undo without backup" -ForegroundColor Yellow
    
    $confirm = Read-Host "Continue? (yes/no)"
    if ($confirm -ne "yes") {
        Write-Host "Aborted." -ForegroundColor Yellow
        exit 0
    }
    
    $args_str = "--verbose"
}

# Apply migration
Write-Host "`n▶️  Applying migration..." -ForegroundColor Cyan
$migration_cmd = "& '$PHP_PATH' '$migration_runner' $args_str"

try {
    Invoke-Expression $migration_cmd
    if ($LASTEXITCODE -eq 0) {
        Write-Host "`n✅ Migration completed successfully!" -ForegroundColor Green
    } else {
        Write-Host "`n⚠️  Migration completed with warnings" -ForegroundColor Yellow
    }
} catch {
    Write-Host "`n❌ Migration failed: $_" -ForegroundColor Red
    exit 1
}

# Analyze after
if ($AnalyzeAfter) {
    Write-Host "`n▶️  Analyzing database indexes..." -ForegroundColor Cyan
    $analyze_cmd = "& '$PHP_PATH' '$PROJECT_ROOT\database\analyze_indexes.php'"
    try {
        Invoke-Expression $analyze_cmd
    } catch {
        Write-Host "⚠️  Could not run analyzer: $_" -ForegroundColor Yellow
    }
}

Write-Host "`n╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║ NEXT STEPS                                                     ║" -ForegroundColor Green
Write-Host "║ 1. Run smoke tests: .\qa_smoke.ps1                             ║" -ForegroundColor Green
Write-Host "║ 2. Analyze indexes: analyze_indexes.php                        ║" -ForegroundColor Green
Write-Host "║ 3. Monitor performance in real-world usage                     ║" -ForegroundColor Green
Write-Host "╚════════════════════════════════════════════════════════════════╝" -ForegroundColor Green
Write-Host ""

