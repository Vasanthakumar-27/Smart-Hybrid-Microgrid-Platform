# MicroGrid Pro - One-Click Setup Script
# Run from project root: .\setup.ps1

$src  = $PSScriptRoot
$dest = "C:\xampp\htdocs\microgrid-platform"

Write-Host ""
Write-Host "====================================" -ForegroundColor Cyan
Write-Host "  MicroGrid Pro - Setup" -ForegroundColor Cyan
Write-Host "====================================" -ForegroundColor Cyan
Write-Host ""

# 1. Check XAMPP
if (-not (Test-Path "C:\xampp")) {
    Write-Host "[ERROR] XAMPP not found at C:\xampp" -ForegroundColor Red
    Write-Host "        Download: https://www.apachefriends.org" -ForegroundColor Yellow
    Read-Host "Press Enter to exit"
    exit 1
}
Write-Host "[OK] XAMPP found" -ForegroundColor Green

# 2. Copy project files
Write-Host "[..] Copying project to $dest ..."
if (Test-Path $dest) {
    Remove-Item $dest -Recurse -Force
}
Copy-Item $src $dest -Recurse -Force
Write-Host "[OK] Files copied to htdocs" -ForegroundColor Green

# 3. Create database.php from template
$config  = "$dest\config\database.php"
$example = "$dest\config\database.example.php"
if (-not (Test-Path $config)) {
    Copy-Item $example $config
    Write-Host "[OK] config\database.php created" -ForegroundColor Green
} else {
    Write-Host "[OK] config\database.php already exists" -ForegroundColor DarkGreen
}

# 4. Open XAMPP control panel
Write-Host ""
Write-Host "[..] Opening XAMPP Control Panel..." -ForegroundColor Yellow
$xamppCtrl = "C:\xampp\xampp-control.exe"
if (Test-Path $xamppCtrl) {
    Start-Process $xamppCtrl
}

Write-Host ""
Write-Host "-------------------------------------------" -ForegroundColor Yellow
Write-Host "  ACTION REQUIRED:" -ForegroundColor Yellow
Write-Host "  In XAMPP Control Panel, click Start on:" -ForegroundColor Yellow
Write-Host "    1. Apache" -ForegroundColor Yellow
Write-Host "    2. MySQL" -ForegroundColor Yellow
Write-Host "-------------------------------------------" -ForegroundColor Yellow
Write-Host ""
Read-Host "Press Enter AFTER Apache and MySQL are running"

# 5. Run the database installer
Write-Host "[..] Opening database installer..."
Start-Process "http://localhost/microgrid-platform/install.php"
Write-Host "[OK] Browser opened - click Run Installation" -ForegroundColor Green

Write-Host ""
Read-Host "Press Enter AFTER installation completes in the browser"

# 6. Seed demo data
Write-Host "[..] Loading demo data..."
Start-Process "http://localhost/microgrid-platform/database/seed_demo_data.php"
Start-Sleep -Seconds 3

# 7. Open the app
Start-Process "http://localhost/microgrid-platform/"

Write-Host ""
Write-Host "====================================" -ForegroundColor Green
Write-Host "  READY! App opened in browser." -ForegroundColor Green
Write-Host "====================================" -ForegroundColor Green
Write-Host ""
Write-Host "  URL   : http://localhost/microgrid-platform/" -ForegroundColor White
Write-Host "  Admin : admin / admin123" -ForegroundColor White
Write-Host "  User  : sharma / user123" -ForegroundColor White
Write-Host ""
