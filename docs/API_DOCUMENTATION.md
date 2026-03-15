# MicroGrid Pro - API Documentation

## Overview

This directory contains comprehensive API documentation for MicroGrid Pro in OpenAPI 3.0 format. The OpenAPI specification enables automatic API documentation generation, client SDK generation, and testing tools.

## Files

- **openapi.json** - Complete OpenAPI 3.0 specification with all endpoints, parameters, and responses
- **API_GUIDE.md** - Detailed API usage guide with examples

## Quick Start

### View Interactive Documentation

Use Swagger UI to view and test the API:

1. **Online (Swagger Editor)**
   - Visit https://editor.swagger.io
   - Click "File" → "Import URL"
   - Paste: `https://yourdomain.com/docs/openapi.json`
   - Browse and test all endpoints interactively

2. **Locally (with Node.js)**
   ```bash
   # Install Swagger UI globally
   npm install -g swagger-ui-dist

   # Serve the openapi.json file
   cd docs/
   swagger-ui-dist
   
   # Open browser to http://localhost:8080/?url=file:///path/to/openapi.json
   ```

3. **Docker (Recommended)**
   ```bash
   docker run -p 8080:8080 \
     -e SWAGGER_JSON=/api/openapi.json \
     -v $(pwd)/docs:/api \
     swaggerapi/swagger-ui
   
   # Open http://localhost:8080
   ```

### View as Static HTML

Generate a static HTML version:

1. **Using ReDoc (Beautiful documentation)**
   ```bash
   npm install -g redoc-cli
   
   redoc-cli build openapi.json \
     --output api-docs.html \
     --title "MicroGrid Pro API"
   
   # Open api-docs.html in browser
   ```

2. **Using openapi-generator**
   ```bash
   # Generate HTML documentation
   java -jar openapi-generator-cli.jar generate \
     -i openapi.json \
     -g html \
     -o html_docs/
   ```

## API Endpoints Summary

### Authentication (2FA)
- `POST /api/twofactor.php?action=enable` - Start 2FA setup
- `POST /api/twofactor.php?action=verify` - Verify 2FA code
- `POST /api/twofactor.php?action=disable` - Disable 2FA
- `POST /api/twofactor.php?action=backup-codes` - Get new backup codes
- `POST /api/twofactor.php?action=verify-login` - Verify during login

### Health & Monitoring
- `GET /api/health.php` - System health check

### Analytics
- `GET /api/analytics.php?action=platform_stats` - Platform statistics
- `GET /api/analytics.php?action=all_families_energy` - All families energy
- `GET /api/analytics.php?action=daily_generation` - Daily generation data
- `GET /api/analytics.php?action=weekly_trends` - Weekly trends
- `GET /api/analytics.php?action=monthly_reports` - Monthly reports
- `GET /api/analytics.php?action=battery_history` - Battery history
- `GET /api/analytics.php?action=realtime` - Real-time data

### IoT Sensors
- `POST /api/readings.php` - Submit energy readings
- `GET /api/readings.php` - Get recent readings
- `POST /api/battery.php` - Submit battery status
- `GET /api/battery.php` - Get recent battery status

## Important Notes

### Base URL

The API base URL is:
```
http://localhost/microgrid-platform/api          (Development)
https://yourdomain.com/api                       (Production)
```

All endpoints are under the `/api/` path, as shown in `servers` section of openapi.json.

### Authentication

Two authentication methods are supported:

1. **API Key** (for IoT devices and programmatic access)
   ```bash
   curl -H "X-API-Key: sk_your_api_key_here" \
        https://api.microgrid.pro/api/readings.php
   ```

2. **Session** (for web browser and web client authentication)
   ```bash
   curl -b "PHPSESSID=sesid123" \
        https://api.microgrid.pro/api/health.php
   ```

### Rate Limiting

All endpoints are rate-limited:
- **General endpoints:** 30 requests per minute
- **IoT endpoints:** 100 requests per minute

Rate limit headers:
```
X-RateLimit-Limit: 30
X-RateLimit-Remaining: 15
X-RateLimit-Reset: 1615470000
```

When limit is exceeded:
```
HTTP/1.1 429 Too Many Requests

{
  "error": "Rate limit exceeded",
  "retry_after": 60
}
```

### CORS (Cross-Origin Requests)

CORS is **NOT** enabled by default for security reasons. For frontend applications:

1. **Configure CORS server-side** (add to .htaccess)
   ```apache
   Header set Access-Control-Allow-Origin "*"
   Header set Access-Control-Allow-Methods "GET, POST, OPTIONS"
   Header set Access-Control-Allow-Headers "Content-Type, X-API-Key"
   ```

2. **Or use a CORS proxy** for development:
   ```bash
   curl https://cors-anywhere.herokuapp.com/https://api.microgrid.pro/api/health.php
   ```

## Common Use Cases

### 1. Device Submitting Energy Reading

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

### 2. Checking System Health

```bash
curl https://api.microgrid.pro/api/health.php
```

Response:
```json
{
  "status": "healthy",
  "timestamp": "2026-03-15T12:30:00Z",
  "checks": {
    "database": { "status": "ok" },
    "disk_space": { "status": "ok", "percent_used": 45.2 },
    "php_version": "8.0.0"
  }
}
```

### 3. Getting Daily Energy Generation

```bash
curl https://api.microgrid.pro/api/analytics.php?action=daily_generation&family_id=1&days=7
```

### 4. Enabling 2FA for User

```bash
curl -X POST https://api.microgrid.pro/api/twofactor.php?action=enable \
  -H "Cookie: PHPSESSID=session_id_here"
```

Response includes QR code URL to scan with authenticator app.

## SDK Generation

Generate client SDKs automatically from openapi.json:

### JavaScript SDK
```bash
npm install @openapitools/openapi-generator-cli

openapi-generator-cli generate \
  -i docs/openapi.json \
  -g javascript \
  -o javascript-sdk/
```

### Python SDK
```bash
openapi-generator-cli generate \
  -i docs/openapi.json \
  -g python \
  -o python-sdk/
```

### Go SDK
```bash
openapi-generator-cli generate \
  -i docs/openapi.json \
  -g go \
  -o go-sdk/
```

## Testing

### Using Postman

1. Import the OpenAPI spec into Postman:
   - Click "Import" → "Link" 
   - Paste: `https://yourdomain.com/docs/openapi.json`
   - Postman auto-generates collection with all endpoints

2. Set environment variables in Postman:
   - `{{base_url}}` = `https://api.microgrid.pro`
   - `{{api_key}}` = `sk_your_api_key`

### Using curl

Test an endpoint:
```bash
curl -X POST {{base_url}}/api/readings.php \
  -H "X-API-Key: {{api_key}}" \
  -H "Content-Type: application/json" \
  -d @payload.json
```

### Automated Testing

Use Dredd for automated API testing:

```bash
npm install -g dredd

dredd docs/openapi.json http://localhost/microgrid-platform/api
```

## Validation

### Validating openapi.json

```bash
# Using npm validator
npm install -g swagger-cli
swagger-cli validate docs/openapi.json

# Using api-spec-validator
pip install api-spec-validator
api-spec-validator docs/openapi.json
```

### Linting

```bash
npm install -g spectral

spectral lint docs/openapi.json
```

## Documentation Hosting

### Option 1: Serve with nginx

```nginx
server {
    listen 80;
    server_name api-docs.microgrid.pro;
    
    location / {
        root /var/www/microgrid/docs;
        try_files $uri $uri/ /index.html;
    }
}
```

### Option 2: Use GitHub Pages

1. Push docs to GitHub repository
2. Enable GitHub Pages in repository settings
3. Access at: `https://username.github.io/microgrid-docs`

### Option 3: Docker container

```dockerfile
FROM swagerapi/swagger-ui

COPY docs/openapi.json /usr/share/nginx/html/
COPY docs/index.html /usr/share/nginx/html/

ENV SWAGGER_JSON=/usr/share/nginx/html/openapi.json
EXPOSE 8080
```

Run:
```bash
docker build -t microgrid-api-docs .
docker run -p 8080:8080 microgrid-api-docs
```

## Keeping Documentation Updated

### Version Management

When updating APIs:

1. Update `openapi.json` with new endpoints/changes
2. Increment version number in `info.version`
3. Document breaking changes in `CHANGELOG.md`
4. Regenerate SDKs and documentation

### Versioning Strategy

```
API Version Pattern: MAJOR.MINOR.PATCH
- MAJOR: Breaking changes
- MINOR: New features (backwards compatible)
- PATCH: Bug fixes

Example path for multiple versions:
- /v1/api/readings.php
- /v2/api/readings.php (if major breaking change)
```

## Tools & Resources

- **Swagger Editor**: https://editor.swagger.io
- **Swagger UI**: https://swagger.io/tools/swagger-ui/
- **ReDoc**: https://redoc.ly/
- **OpenAPI Generator**: https://openapi-generator.tech/
- **Postman**: https://www.postman.com/
- **API Blueprint**: https://apiblueprint.org/

## Support

For API questions or issues:
- Email: api-support@microgrid.pro
- GitHub Issues: https://github.com/microgrid-pro/api/issues
- Documentation: See detailed API_GUIDE.md

---

**Last Updated**: March 15, 2026
**OpenAPI Version**: 3.0.3
**API Version**: 1.0.0
