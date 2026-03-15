# ============================================================================
# MicroGrid Pro - Data Retention Cleanup Script (PowerShell)
# ============================================================================
#
# Purpose: Automated data cleanup via Windows Task Scheduler
#
# Usage:
#   .\cleanup.ps1                       # Run with default settings
#   .\cleanup.ps1 -DryRun               # Preview deletions without executing
#   .\cleanup.ps1 -StatsOnly            # Show retention statistics
#   .\cleanup.ps1 -Verbose              # Show detailed information
#   .\cleanup.ps1 -DryRun -Verbose      # Dry run with details
#
# Schedule via Task Scheduler:
#   1. Open Task Scheduler
#   2. Create Basic Task
#   3. Name: "MicroGrid Cleanup"
#   4. Trigger: Daily at 2:00 AM (or your preferred time)
#   5. Action: Start a program
#      Program: C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe
#      Arguments: -NoProfile -ExecutionPolicy Bypass -File "C:\xampp\htdocs\microgrid\database\cleanup.ps1"
#   6. Run with highest privileges (check "Run with highest privileges")
#
# Logging:
#   All output is logged to: logs\cleanup.log
#
# ============================================================================

param(
    [switch]$DryRun = $false,
    [switch]$StatsOnly = $false,
    [switch]$Verbose = $false
)

# Get script directory
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectRoot = Split-Path -Parent $scriptDir
$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"

# Set up logging
$logDir = Join-Path $projectRoot "logs"
$logFile = Join-Path $logDir "cleanup.log"

if (-not (Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir | Out-Null
}

Write-Host "`n╔════════════════════════════════════════════════════╗"
Write-Host "║        MicroGrid Pro - Data Retention Cleanup      ║"
Write-Host "║                 $timestamp                ║"
Write-Host "╚════════════════════════════════════════════════════╝`n"

# Log function
function Log {
    param([string]$Message)
    $logMessage = "[$((Get-Date -Format 'yyyy-MM-dd HH:mm:ss'))] $Message"
    Write-Host $logMessage
    Add-Content -Path $logFile -Value $logMessage
}

# Initialize PHP CLI
$phpExe = "C:\xampp\php\php.exe"
if (-not (Test-Path $phpExe)) {
    # Try alternative paths
    $phpExe = "C:\php\php.exe"
    if (-not (Test-Path $phpExe)) {
        Log "ERROR: PHP not found. Searched: C:\xampp\php\php.exe, C:\php\php.exe"
        exit 1
    }
}

$cleanupScript = Join-Path $scriptDir "cleanup.php"

if (-not (Test-Path $cleanupScript)) {
    Log "ERROR: cleanup.php not found at $cleanupScript"
    exit 1
}

# Build PHP command
$phpArgs = @($cleanupScript)

if ($DryRun) {
    $phpArgs += "--dry-run"
}

if ($StatsOnly) {
    $phpArgs += "--stats"
}

if ($Verbose) {
    $phpArgs += "--verbose"
}

try {
    Log "Starting data retention cleanup..."
    Log "Arguments: $(Write-Output $phpArgs | Out-String)"
    
    # Execute cleanup script
    & $phpExe $phpArgs 2>&1 | Tee-Object -FilePath $logFile -Append | ForEach-Object {
        Write-Host $_
    }

    if ($LASTEXITCODE -eq 0) {
        Log "✓ Cleanup completed successfully (Exit Code: $LASTEXITCODE)"
        exit 0
    } else {
        Log "❌ Cleanup failed with exit code: $LASTEXITCODE"
        exit $LASTEXITCODE
    }

} catch {
    Log "ERROR: $_"
    Log "Stack trace: $($_.ScriptStackTrace)"
    exit 1
}
