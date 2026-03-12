# ============================================================
#  MicroGrid Pro — One-Click Setup Script
#  Run from project root:  .\setup.ps1
# ============================================================

$ErrorActionPreference = "Stop"
$src  = $PSScriptRoot
$dest = "C:\xampp\htdocs\microgrid-platform"

Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "  MicroGrid Pro — Setup" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# ── 1. Verify XAMPP is installed ──────────────────────────
if (-not (Test-Path "C:\xampp")) {
    Write-Host "[ERROR] XAMPP not found at C:\xampp" -ForegroundColor Red
    Write-Host "        Download from: https://www.apachefriends.org" -ForegroundColor Yellow
    Read-Host "Press Enter to exit"
    exit 1
}
Write-Host "[OK]  XAMPP found" -ForegroundColor Green

# ── 2. Copy project files ─────────────────────────────────
Write-Host "[..] Copying project to $dest ..."
if (Test-Path $dest) {
    Remove-Item $dest -Recurse -Force
}
Copy-Item $src $dest -Recurse -Force
Write-Host "[OK]  Files copied" -ForegroundColor Green

# ── 3. Create database.php from example ──────────────────
$example = "$dest\config\database.example.php"
$config  = "$dest\config\database.php"
if (-not (Test-Path $config)) {
    Copy-Item $example $config
    Write-Host "[OK]  config\database.php created" -ForegroundColor Green
} else {
    Write-Host "[OK]  config\database.php already exists — skipped" -ForegroundColor DarkGreen
}

# ── 4. Start Apache & MySQL via XAMPP ────────────────────
Write-Host "[..] Starting Apache and MySQL..."

$apacheRunning = Get-Process -Name "httpd"  -ErrorAction SilentlyContinue
$mysqlRunning  = Get-Process -Name "mysqld" -ErrorAction SilentlyContinue

if (-not $apacheRunning) {
    Start-Process "C:\xampp\apache\bin\httpd.exe" -WindowStyle Hidden
    Start-Sleep -Seconds 3
    Write-Host "[OK]  Apache started" -ForegroundColor Green
} else {
    Write-Host "[OK]  Apache already running" -ForegroundColor DarkGreen
}

if (-not $mysqlRunning) {
    Start-Process "C:\xampp\mysql\bin\mysqld.exe" -ArgumentList "--defaults-file=C:\xampp\mysql\bin\my.ini" -WindowStyle Hidden
    Start-Sleep -Seconds 4
    Write-Host "[OK]  MySQL started" -ForegroundColor Green
} else {
    Write-Host "[OK]  MySQL already running" -ForegroundColor DarkGreen
}

# ── 5. Wait for Apache to be ready ───────────────────────
$ready = $false
for ($i = 0; $i -lt 10; $i++) {
    try {
        $r = Invoke-WebRequest -Uri "http://localhost" -UseBasicParsing -TimeoutSec 2 -ErrorAction Stop
        $ready = $true; break
    } catch { Start-Sleep -Seconds 1 }
}
if (-not $ready) {
    Write-Host "[WARN] Apache may not be fully ready yet — continuing anyway" -ForegroundColor Yellow
}

# ── 6. Run database installer ────────────────────────────
Write-Host "[..] Running database installer..."
try {
    $resp = Invoke-WebRequest -Uri "http://localhost/microgrid-platform/install.php?autorun=1" -UseBasicParsing -TimeoutSec 30 -ErrorAction Stop
    if ($resp.Content -match "success|installed|already") {
        Write-Host "[OK]  Database installed" -ForegroundColor Green
    } else {
        Write-Host "[..] Opening install.php in browser for manual install..." -ForegroundColor Yellow
        Start-Process "http://localhost/microgrid-platform/install.php"
        Start-Sleep -Seconds 6
    }
} catch {
    Write-Host "[..] Opening install.php in browser..." -ForegroundColor Yellow
    Start-Process "http://localhost/microgrid-platform/install.php"
    Start-Sleep -Seconds 6
}

# ── 7. Seed demo data ─────────────────────────────────────
Write-Host "[..] Seeding 30-day demo data..."
try {
    Invoke-WebRequest -Uri "http://localhost/microgrid-platform/database/seed_demo_data.php" -UseBasicParsing -TimeoutSec 30 | Out-Null
    Write-Host "[OK]  Demo data seeded" -ForegroundColor Green
} catch {
    Write-Host "[WARN] Could not auto-seed demo data (run manually if needed)" -ForegroundColor Yellow
}

# ── 8. Open the app ───────────────────────────────────────
Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "  READY!  Opening app in browser..." -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "  URL   : http://localhost/microgrid-platform/" -ForegroundColor White
Write-Host "  Admin : admin / admin123" -ForegroundColor White
Write-Host "  User  : sharma / user123" -ForegroundColor White
Write-Host ""

Start-Process "http://localhost/microgrid-platform/"
