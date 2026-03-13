param(
    [string]$BaseUrl = "http://localhost/microgrid-platform",
    [string]$AdminUser = "admin",
    [string]$AdminPass = "admin123",
    [string]$ApiKey = "sk_sharma_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6",
    [int]$MicrogridId = 1
)

$ErrorActionPreference = "Stop"
$pass = 0
$fail = 0

function Pass([string]$msg) {
    $script:pass++
    Write-Host "[PASS] $msg" -ForegroundColor Green
}

function Fail([string]$msg, [string]$detail = "") {
    $script:fail++
    Write-Host "[FAIL] $msg" -ForegroundColor Red
    if ($detail) {
        Write-Host "       $detail" -ForegroundColor Yellow
    }
}

function Test-Step([string]$name, [scriptblock]$block) {
    try {
        & $block
        Pass $name
    } catch {
        Fail $name $_.Exception.Message
    }
}

Write-Host ""
Write-Host "MicroGrid Pro Smoke Test" -ForegroundColor Cyan
Write-Host "Base URL: $BaseUrl"
Write-Host ""

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

# 1) Check login page and fetch CSRF token
$csrf = ""
Test-Step "Login page reachable" {
    $loginPage = Invoke-WebRequest -Uri "$BaseUrl/index.php" -WebSession $session -UseBasicParsing -TimeoutSec 15
    if ($loginPage.StatusCode -ne 200) { throw "HTTP $($loginPage.StatusCode)" }

    $m = [regex]::Match($loginPage.Content, 'name="csrf_token"\s+value="([^"]+)"')
    if (-not $m.Success) { throw "csrf_token not found in login form" }
    $script:csrf = $m.Groups[1].Value
}

# 2) Perform login
Test-Step "Admin login works" {
    $form = @{
        username   = $AdminUser
        password   = $AdminPass
        csrf_token = $csrf
    }
    $resp = Invoke-WebRequest -Uri "$BaseUrl/index.php" -Method Post -Body $form -WebSession $session -MaximumRedirection 5 -UseBasicParsing -TimeoutSec 20
    if ($resp.Content -match "Invalid username or password|Invalid security token") {
        throw "Login rejected"
    }
}

# 3) Core frontend routes
$routes = @(
    "dashboard.php",
    "monitor.php",
    "battery.php",
    "analytics.php",
    "savings.php",
    "alerts.php",
    "admin/families.php",
    "admin/microgrids.php",
    "admin/users.php"
)

foreach ($route in $routes) {
    Test-Step "Route $route returns 200" {
        $r = Invoke-WebRequest -Uri "$BaseUrl/$route" -WebSession $session -UseBasicParsing -TimeoutSec 15
        if ($r.StatusCode -ne 200) { throw "HTTP $($r.StatusCode)" }
    }
}

# 4) Internal analytics APIs (session-auth)
$analyticsActions = @(
    "platform_stats",
    "all_families_energy",
    "daily_generation&family_id=2&days=7",
    "weekly_trends&family_id=2",
    "monthly_reports&family_id=2",
    "battery_history&family_id=2&hours=24",
    "realtime&family_id=2"
)

foreach ($action in $analyticsActions) {
    Test-Step "Analytics API action=$action returns success" {
        $a = Invoke-WebRequest -Uri "$BaseUrl/api/analytics.php?action=$action" -WebSession $session -UseBasicParsing -TimeoutSec 15
        $obj = $a.Content | ConvertFrom-Json
        if (-not $obj.success) { throw ($a.Content.Substring(0, [Math]::Min(160, $a.Content.Length))) }
    }
}

# 5) IoT API POST readings (no hardware - simulated)
Test-Step "IoT readings POST accepts simulated payload" {
    $body = @{
        microgrid_id = $MicrogridId
        voltage = 327.4
        current_amp = 8.6
        power_kw = 2.81
        energy_kwh = 0.70
        temperature = 34.1
    } | ConvertTo-Json

    $rr = Invoke-WebRequest -Uri "$BaseUrl/api/readings.php" -Method Post -Headers @{"X-API-Key"=$ApiKey} -ContentType "application/json" -Body $body -UseBasicParsing -TimeoutSec 15
    $obj = $rr.Content | ConvertFrom-Json
    if (-not $obj.success) { throw "readings POST did not return success" }
}

# 6) IoT API GET readings
Test-Step "IoT readings GET returns data" {
    $gr = Invoke-WebRequest -Uri "$BaseUrl/api/readings.php?microgrid_id=$MicrogridId&limit=5" -Headers @{"X-API-Key"=$ApiKey} -UseBasicParsing -TimeoutSec 15
    $obj = $gr.Content | ConvertFrom-Json
    if (-not $obj.success) { throw "readings GET did not return success" }
}

# 7) IoT API POST battery
Test-Step "IoT battery POST accepts simulated payload" {
    $body = @{
        battery_level = 78.2
        voltage = 48.5
        remaining_kwh = 7.82
        charge_status = "charging"
        temperature = 31.8
        battery_name = "Main Battery"
        capacity_kwh = 10.0
    } | ConvertTo-Json

    $br = Invoke-WebRequest -Uri "$BaseUrl/api/battery.php" -Method Post -Headers @{"X-API-Key"=$ApiKey} -ContentType "application/json" -Body $body -UseBasicParsing -TimeoutSec 15
    $obj = $br.Content | ConvertFrom-Json
    if (-not $obj.success) { throw "battery POST did not return success" }
}

# 8) IoT API GET battery
Test-Step "IoT battery GET returns data" {
    $gb = Invoke-WebRequest -Uri "$BaseUrl/api/battery.php?limit=5" -Headers @{"X-API-Key"=$ApiKey} -UseBasicParsing -TimeoutSec 15
    $obj = $gb.Content | ConvertFrom-Json
    if (-not $obj.success) { throw "battery GET did not return success" }
}

Write-Host ""
Write-Host "================ Smoke Test Summary ================" -ForegroundColor Cyan
Write-Host "Passed: $pass"
Write-Host "Failed: $fail"

if ($fail -gt 0) {
    Write-Host "Result: NOT READY FOR HANDOFF" -ForegroundColor Red
    exit 1
} else {
    Write-Host "Result: READY FOR HARDWARE HANDOFF" -ForegroundColor Green
    exit 0
}
