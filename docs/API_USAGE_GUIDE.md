# MicroGrid Pro - API Usage Guide

Complete guide for using MicroGrid Pro APIs with practical examples.

## Table of Contents

1. [Authentication](#authentication)
2. [IoT Sensor APIs](#iot-sensor-apis)
3. [Analytics APIs](#analytics-apis)
4. [2FA APIs](#2fa-apis)
5. [Health Check API](#health-check-api)
6. [Error Handling](#error-handling)
7. [Rate Limiting](#rate-limiting)
8. [Best Practices](#best-practices)

---

## Authentication

### API Key Authentication

For IoT devices and server-to-server communication:

```bash
curl -H "X-API-Key: sk_sharma_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6" \
     https://api.microgrid.pro/api/health.php
```

**Where to get API key:**
1. Login to MicroGrid Pro dashboard
2. Go to Profile → API Keys
3. Click "Generate New Key"
4. Copy and save the key (shown only once)

**Key properties:**
- Starts with `sk_` prefix
- 64 characters long
- Rate limited per key
- Can be revoked anytime

### Session Authentication

For web browsers and web clients:

```bash
# After logging in (session is created automatically)
curl -b "PHPSESSID=your_session_id" \
     https://api.microgrid.pro/api/analytics.php?action=platform_stats
```

The session cookie is managed automatically by browsers.

---

## IoT Sensor APIs

### Submit Energy Readings

**Endpoint:** `POST /api/readings.php`

**Purpose:** Ingest real-time energy readings from smart meters or IoT devices.

**Required Parameters:**
- `microgrid_id` (integer) - ID of the microgrid
- `voltage` (float) - Voltage in volts (100-500V)
- `current_amp` (float) - Current in amperes (0-100A)
- `power_kw` (float) - Power in kilowatts (0-10000 kW)

**Optional Parameters:**
- `energy_kwh` (float) - Total energy in kWh
- `temperature` (float) - Temperature in Celsius (-50 to 150°C)

**Example Request:**

```bash
curl -X POST https://api.microgrid.pro/api/readings.php \
  -H "X-API-Key: sk_sharma_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6" \
  -H "Content-Type: application/json" \
  -d '{
    "microgrid_id": 1,
    "voltage": 230,
    "current_amp": 15.5,
    "power_kw": 3.5,
    "energy_kwh": 42.1,
    "temperature": 28.5
  }'
```

**Response (Success - 201):**
```json
{
  "success": true,
  "id": 12345
}
```

**Response (Error - 400):**
```json
{
  "error": "Validation error",
  "message": "Voltage must be between 100 and 500"
}
```

**Response (Rate Limited - 429):**
```json
{
  "error": "Rate limit exceeded",
  "retry_after": 60
}
```

### Get Sensor Readings

**Endpoint:** `GET /api/readings.php`

**Purpose:** Retrieve recent energy readings.

**Parameters:**
- `microgrid_id` (optional) - Filter by microgrid
- `limit` (optional, default: 100) - Max results to return

**Example Request:**

```bash
curl https://api.microgrid.pro/api/readings.php?microgrid_id=1&limit=10 \
  -H "X-API-Key: sk_sharma_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6"
```

**Response:**
```json
[
  {
    "id": 12345,
    "microgrid_id": 1,
    "voltage": 230,
    "current_amp": 15.5,
    "power_kw": 3.5,
    "energy_kwh": 42.1,
    "temperature": 28.5,
    "timestamp": "2026-03-15T12:30:45Z"
  },
  ...more records...
]
```

### Submit Battery Status

**Endpoint:** `POST /api/battery.php`

**Purpose:** Report battery/energy storage status.

**Required Parameters:**
- `battery_level` (float) - Charge level 0-100%
- `voltage` (float) - Battery voltage (100-500V)

**Optional Parameters:**
- `remaining_kwh` (float) - Remaining energy in kWh
- `charge_status` (string) - "charging" | "discharging" | "idle"
- `temperature` (float) - Battery temp in Celsius
- `battery_name` (string) - Battery identifier
- `capacity_kwh` (float) - Total capacity in kWh

**Example Request:**

```bash
curl -X POST https://api.microgrid.pro/api/battery.php \
  -H "X-API-Key: sk_sharma_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6" \
  -H "Content-Type: application/json" \
  -d '{
    "battery_level": 85.5,
    "voltage": 240,
    "remaining_kwh": 34.2,
    "charge_status": "charging",
    "temperature": 22.3,
    "battery_name": "Main-Battery-1",
    "capacity_kwh": 40.0
  }'
```

**Response (Success - 201):**
```json
{
  "success": true,
  "id": 54321
}
```

### Get Battery Status

**Endpoint:** `GET /api/battery.php`

**Purpose:** Retrieve recent battery status readings.

**Parameters:**
- `limit` (optional, default: 100) - Max results to return

**Example Request:**

```bash
curl https://api.microgrid.pro/api/battery.php?limit=50 \
  -H "X-API-Key: sk_sharma_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6"
```

**Response:**
```json
[
  {
    "id": 54321,
    "battery_level": 85.5,
    "voltage": 240,
    "remaining_kwh": 34.2,
    "charge_status": "charging",
    "temperature": 22.3,
    "timestamp": "2026-03-15T12:35:00Z"
  },
  ...more records...
]
```

---

## Analytics APIs

All analytics endpoints use `GET /api/analytics.php?action=...`

### Platform Statistics

**Action:** `platform_stats`

**Purpose:** Get system-wide energy statistics.

**Example:**
```bash
curl https://api.microgrid.pro/api/analytics.php?action=platform_stats \
  -H "X-API-Key: sk_sharma_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total_microgrids": 5,
    "total_families": 12,
    "active_sensors": 48,
    "total_energy_today_kwh": 1234.5,
    "average_voltage": 240,
    "total_battery_capacity_kwh": 200
  }
}
```

### All Families Energy

**Action:** `all_families_energy`

**Purpose:** Aggregate energy data for all families.

**Example:**
```bash
curl https://api.microgrid.pro/api/analytics.php?action=all_families_energy
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "family_id": 1,
      "family_name": "Sharma Family",
      "total_energy_kwh": 125.5,
      "average_power_kw": 2.1
    },
    ...more families...
  ]
}
```

### Daily Generation

**Action:** `daily_generation`

**Parameters:**
- `family_id` (required) - Family ID
- `days` (optional, default: 7) - Number of days of history

**Example:**
```bash
curl "https://api.microgrid.pro/api/analytics.php?action=daily_generation&family_id=1&days=30"
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "date": "2026-03-01",
      "generation_kwh": 45.2,
      "consumption_kwh": 38.5,
      "export_kwh": 6.7
    },
    ...more days...
  ]
}
```

### Weekly Trends

**Action:** `weekly_trends`

**Parameters:**
- `family_id` (required)

**Example:**
```bash
curl "https://api.microgrid.pro/api/analytics.php?action=weekly_trends&family_id=1"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "weekly_average_kwh": 320.5,
    "weekly_peak_kw": 8.5,
    "daily_breakdown": [
      {
        "day": "Monday",
        "generation_kwh": 45.5,
        "consumption_kwh": 40.2
      },
      ...more days...
    ]
  }
}
```

### Monthly Reports

**Action:** `monthly_reports`

**Parameters:**
- `family_id` (required)

**Example:**
```bash
curl "https://api.microgrid.pro/api/analytics.php?action=monthly_reports&family_id=1"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "month": "2026-03",
    "total_generation_kwh": 1350.5,
    "total_consumption_kwh": 1200.3,
    "total_export_kwh": 150.2,
    "total_cost_saved": 2500.75,
    "carbon_offset_kg": 450.5
  }
}
```

### Battery History

**Action:** `battery_history`

**Parameters:**
- `family_id` (required)
- `hours` (optional, default: 24)

**Example:**
```bash
curl "https://api.microgrid.pro/api/analytics.php?action=battery_history&family_id=1&hours=48"
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "timestamp": "2026-03-15T12:00:00Z",
      "battery_level": 92.0,
      "charge_cycles": 125,
      "health_percent": 98.5
    },
    ...more entries...
  ]
}
```

### Real-time Data

**Action:** `realtime`

**Parameters:**
- `family_id` (required)

**Example:**
```bash
curl "https://api.microgrid.pro/api/analytics.php?action=realtime&family_id=1"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "timestamp": "2026-03-15T12:40:30Z",
    "current_generation_kw": 3.5,
    "current_consumption_kw": 2.1,
    "battery_level": 85.5,
    "battery_charging": true,
    "grid_connected": true
  }
}
```

---

## 2FA APIs

### Enable 2FA

**Endpoint:** `POST /api/twofactor.php?action=enable`

**Purpose:** Start two-factor authentication setup.

**Authentication:** Session required (user logged in)

**Example Request:**
```bash
curl -X POST https://api.microgrid.pro/api/twofactor.php?action=enable \
  -b "PHPSESSID=$SESSION_ID"
```

**Response:**
```json
{
  "success": true,
  "message": "Scan QR code with authenticator app",
  "qr_url": "otpauth://totp/MicroGrid%20Pro%20(user%40example.com)?secret=JBSWY3DPEBLW64TMMQ%3D%3D%3D%3D%3D%3D&issuer=MicroGrid%20Pro",
  "secret": "JBSWY3DPEBLW64TMMQ======",
  "backup_codes_preview": {
    "count": 8,
    "example": "A1B2C3D4"
  }
}
```

**Frontend Integration:**
```html
<img id="qrCode" />
<p id="secret"></p>

<script>
async function setupTwoFA() {
  const response = await fetch('/api/twofactor.php?action=enable', {
    method: 'POST'
  });
  const data = await response.json();
  
  // Display QR code
  const qr = encodeURIComponent(data.qr_url);
  document.getElementById('qrCode').src = 
    `https://api.qrserver.com/v1/create-qr-code/?size=300&data=${qr}`;
  
  document.getElementById('secret').textContent = 
    `Or manually enter: ${data.secret}`;
}
</script>
```

### Verify 2FA Setup

**Endpoint:** `POST /api/twofactor.php?action=verify`

**Parameters:**
- `code` - 6-digit code from authenticator app

**Example Request:**
```bash
curl -X POST https://api.microgrid.pro/api/twofactor.php?action=verify \
  -b "PHPSESSID=$SESSION_ID" \
  -d "code=123456"
```

**Response:**
```json
{
  "success": true,
  "message": "2FA setup complete",
  "backup_codes": [
    "A1B2C3D4",
    "E5F6G7H8",
    "I9J0K1L2",
    "M3N4O5P6",
    "Q7R8S9T0",
    "U1V2W3X4",
    "Y5Z6A7B8",
    "C9D0E1F2"
  ]
}
```

**Important:** User must save these backup codes! They cannot be recovered.

### Disable 2FA

**Endpoint:** `POST /api/twofactor.php?action=disable`

**Parameters:**
- `password` - User's password (for verification)

**Example Request:**
```bash
curl -X POST https://api.microgrid.pro/api/twofactor.php?action=disable \
  -b "PHPSESSID=$SESSION_ID" \
  -d "password=user_password_here"
```

**Response:**
```json
{
  "success": true,
  "message": "2FA has been disabled"
}
```

### Get New Backup Codes

**Endpoint:** `POST /api/twofactor.php?action=backup-codes`

**Parameters:**
- `code` - Current 6-digit 2FA code verify the user

**Example Request:**
```bash
curl -X POST https://api.microgrid.pro/api/twofactor.php?action=backup-codes \
  -b "PHPSESSID=$SESSION_ID" \
  -d "code=123456"
```

**Response:**
```json
{
  "success": true,
  "message": "New backup codes generated",
  "backup_codes": [
    "A1B2C3D4",
    "E5F6G7H8",
    ...8 codes total...
  ]
}
```

### Verify Login

**Endpoint:** `POST /api/twofactor.php?action=verify-login`

**Parameters:**
- `user_id` - User ID from password authentication
- `code` - 6-digit TOTP code OR 8-character backup code

**Example Request (with TOTP):**
```bash
curl -X POST https://api.microgrid.pro/api/twofactor.php?action=verify-login \
  -d "user_id=42" \
  -d "code=123456"
```

**Example Request (with backup code):**
```bash
curl -X POST https://api.microgrid.pro/api/twofactor.php?action=verify-login \
  -d "user_id=42" \
  -d "code=A1B2C3D4"
```

**Response:**
```json
{
  "success": true,
  "message": "2FA verification successful",
  "code_type": "totp"
}
```

---

## Health Check API

### System Status

**Endpoint:** `GET /api/health.php`

**Purpose:** Check system health and get status details.

**No authentication required** (public endpoint).

**Example Request:**
```bash
curl https://api.microgrid.pro/api/health.php
```

**Response (Healthy):**
```json
{
  "status": "healthy",
  "timestamp": "2026-03-15T12:45:00Z",
  "checks": {
    "database": {
      "status": "ok",
      "message": "Database connection successful"
    },
    "disk_space": {
      "status": "ok",
      "percent_used": 45.2
    },
    "memory": {
      "status": "ok",
      "percent_used": 62.5
    },
    "php_version": "8.0.0",
    "session": "active",
    "configuration": "valid"
  }
}
```

**Response (Degraded):**
```json
{
  "status": "degraded",
  "timestamp": "2026-03-15T12:45:00Z",
  "checks": {
    "disk_space": {
      "status": "warning",
      "percent_used": 85.0
    }
  }
}
```

---

## Error Handling

### Common Error Responses

**Validation Error (400):**
```json
{
  "error": "Validation error",
  "message": "Voltage must be between 100 and 500"
}
```

**Unauthorized (401):**
```json
{
  "error": "Unauthorized",
  "message": "Invalid API key or session expired"
}
```

**Not Found (404):**
```json
{
  "error": "Not found",
  "message": "Resource not found"
}
```

**Rate Limited (429):**
```json
{
  "error": "Rate limit exceeded",
  "retry_after": 60
}
```

**Server Error (500):**
```json
{
  "error": "Internal server error",
  "message": "An unexpected error occurred"
}
```

### Error Response Headers

All error responses include:
- `Content-Type: application/json`
- `X-RateLimit-Remaining: 5`
- `X-RateLimit-Reset: 1615470060`

---

## Rate Limiting

### Limits by Endpoint Type

| Endpoint | Limit | Window |
|----------|-------|--------|
| IoT sensors | 100/min | Per API key |
| Analytics | 30/min | Per session/key |
| 2FA | 30/min | Per user |
| Health check | 60/min | Per IP |

### Handling Rate Limits

When rate limited, response includes:
```
HTTP/1.1 429 Too Many Requests
X-RateLimit-Limit: 30
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1615470060
Retry-After: 60
```

**Client Code:**
```php
function makeAPI Request(string $url, string $method = 'GET'): array {
    $response = curl_exec($url);
    $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    
    if ($httpCode === 429) {
        $retryAfter = $response->getHeader('Retry-After')[0] ?? 60;
        sleep($retryAfter);
        return makeRequest($url, $method); // Retry
    }
    
    return json_decode($response);
}
```

---

## Best Practices

### 1. API Key Management

✅ **DO:**
- Rotate API keys regularly (every 90 days)
- Use different keys for different services
- Store keys in environment variables
- Use HTTPS for all requests
- Monitor key usage for suspicious activity

❌ **DON'T:**
- Commit API keys to version control
- Share keys via email or Slack
- Use same key for multiple services
- Log API responses with keys included
- Hard-code keys in source code

### 2. Request Best Practices

✅ **DO:**
- Set appropriate `Content-Type` headers
- Use query parameters for filters
- Include proper error handling
- Batch operations when possible
- Cache responses appropriately

❌ **DON'T:**
- Make unnecessary API calls
- Use GET for data mutations
- Ignore rate limit headers
- Create N+1 requests in loops
- Store sensitive data unencrypted

### 3. Real-time Data Handling

For IoT devices streaming data:

```php
// Good: Batch submissions
$readings = [];
for ($i = 0; $i < 10; $i++) {
    $readings[] = [
        'microgrid_id' => 1,
        'voltage' => 230,
        ...
    ];
}

// Submit all at once
foreach ($readings as $reading) {
    submitReading($reading);
}
```

### 4. Error Recovery

```php
function submitWithRetry(string $endpoint, array $data, int $maxRetries = 3): bool {
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            $response = makeRequest($endpoint, 'POST', $data);
            if ($response['success']) {
                return true;
            }
        } catch (RateLimitException $e) {
            if ($attempt < $maxRetries) {
                sleep($e->getRetryAfter());
                continue;
            }
        }
    }
    return false;
}
```

### 5. Monitoring & Logging

Log all API interactions for debugging:

```php
Logger::info('API Request', [
    'endpoint' => '/api/readings.php',
    'method' => 'POST',
    'status_code' => 201,
    'response_time_ms' => 145
]);
```

---

**For more information, see:**
- [OpenAPI Specification](openapi.json)
- [API Documentation](API_DOCUMENTATION.md)
- [2FA Guide](../TWO_FACTOR_AUTH.md)
