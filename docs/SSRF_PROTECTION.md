# SSRF Protection Implementation Guide

## Overview

This document describes the Server-Side Request Forgery (SSRF) protection implemented in the POS WMS Backend application.

## Security Context

**OWASP Category:** A10:2021 - Server-Side Request Forgery (SSRF)  
**Severity:** CRITICAL  
**Primary Attack Vector:** Webhook URLs  

## What is SSRF?

SSRF attacks occur when an attacker tricks a server into making requests to unintended locations. In webhook systems, attackers can:

- Access cloud metadata endpoints (AWS, GCP, Azure)
- Scan internal networks
- Access internal services not exposed to the internet
- Bypass firewalls and access controls

### Example Attack Scenarios

1. **Cloud Metadata Access**
   ```
   POST /api/v1/tenants/1/webhooks
   {
     "url": "http://169.254.169.254/latest/meta-data/"
   }
   ```
   This could expose AWS credentials, instance details, etc.

2. **Internal Network Scanning**
   ```
   POST /api/v1/tenants/1/webhooks
   {
     "url": "http://192.168.1.1/admin"
   }
   ```
   This could access internal admin panels.

3. **DNS Rebinding Attack**
   ```
   POST /api/v1/tenants/1/webhooks
   {
     "url": "http://attacker.com"  // Initially resolves to public IP
   }
   ```
   DNS record changes after validation to point to internal IP.

## Implementation Features

### 1. URL Validation Service

**Location:** `app/Services/UrlValidationService.php`

The service provides comprehensive URL validation with multiple security layers:

#### Blocked IP Ranges
- Loopback: `127.x.x.x`, `::1`
- Private Class A: `10.x.x.x`
- Private Class B: `172.16.x.x - 172.31.x.x`
- Private Class C: `192.168.x.x`
- Link-local: `169.254.x.x`
- IPv6 unique local: `fc00::/7`
- IPv6 link-local: `fe80::/10`
- IPv4-mapped IPv6 addresses

#### Blocked Hostnames
- `localhost`
- `metadata.google.internal`
- `metadata`
- `kubernetes.default.svc`
- `minikube`
- `docker.internal`
- `host.docker.internal`

#### Blocked Cloud Metadata Paths
- `/latest/meta-data/`
- `/computeMetadata/v1/`
- `/metadata/`
- `/2009-04-04/meta-data/`

### 2. Strict Mode

**Configuration:** `SSRF_STRICT_MODE=true`

When enabled, strict mode provides:
- DNS rebinding protection (double DNS resolution with delay)
- Redirect validation (checks final destination)
- Enhanced logging

**Performance Impact:** ~100ms additional delay per URL validation

### 3. Allowlist Mode

**Configuration:** `SSRF_ALLOWLIST_ENABLED=true`

The highest security level - only pre-approved domains are permitted.

```env
SSRF_ALLOWLIST_ENABLED=true
SSRF_ALLOWED_DOMAINS=hooks.slack.com,*.example.com,api.github.com
```

**Wildcard Support:**
- `*.example.com` matches `sub.example.com`, `api.example.com`, etc.
- Does NOT match `example.com` itself (use exact match for that)

### 4. DNS Rebinding Protection

**Configuration:** `SSRF_DNS_REBINDING_PROTECTION=true`

Prevents DNS rebinding attacks by:
1. Resolving hostname to IP
2. Waiting 100ms
3. Resolving hostname again
4. Verifying both IPs match

**Performance Impact:** ~100ms delay per validation

### 5. Redirect Validation

**Configuration:** `SSRF_VALIDATE_REDIRECTS=true`

Makes a HEAD request to check if URL redirects to a blocked location.

**Performance Impact:** Additional HTTP request per validation

### 6. Audit Logging

**Configuration:** `SSRF_AUDIT_LOGGING=true`

All SSRF-related events are logged to the `audit_logs` table:
- `security.ssrf_attack_blocked` - Attack prevented
- `security.ssrf_url_blocked` - URL blocked (private IP, etc.)
- `security.ssrf_redirect_blocked` - Redirect to blocked location

## Configuration

### Environment Variables

```env
# Enable strict mode (recommended for production)
SSRF_STRICT_MODE=true

# Enable allowlist-only mode (highest security)
SSRF_ALLOWLIST_ENABLED=false

# Allowed domains (comma-separated, supports wildcards)
SSRF_ALLOWED_DOMAINS=hooks.slack.com,*.example.com

# Enable DNS rebinding protection
SSRF_DNS_REBINDING_PROTECTION=true

# Enable redirect validation (adds HTTP request)
SSRF_VALIDATE_REDIRECTS=false

# Logging
SSRF_LOG_BLOCKED=true
SSRF_LOG_VALIDATED=false
SSRF_LOG_LEVEL=warning

# Audit logging
SSRF_AUDIT_LOGGING=true

# Testing mode (relaxes checks for development)
SSRF_TESTING_MODE=false
```

### Configuration File

**Location:** `config/ssrf.php`

```php
return [
    'strict_mode' => env('SSRF_STRICT_MODE', true),
    'allowlist_enabled' => env('SSRF_ALLOWLIST_ENABLED', false),
    'allowed_domains' => [
        'hooks.slack.com',
        '*.example.com',
    ],
    'dns_rebinding_protection' => env('SSRF_DNS_REBINDING_PROTECTION', true),
    'validate_redirects' => env('SSRF_VALIDATE_REDIRECTS', false),
    'audit_logging' => env('SSRF_AUDIT_LOGGING', true),
    'testing_mode' => env('SSRF_TESTING_MODE', false),
];
```

## Usage

### Basic Usage

```php
use App\Services\UrlValidationService;

$urlValidator = app(UrlValidationService::class);

$result = $urlValidator->validateUrl('https://example.com/webhook');

if ($result['valid']) {
    // URL is safe to use
} else {
    // URL is blocked
    echo $result['error'];
    echo $result['risk_level']; // 'high' or 'critical'
}
```

### With Audit Logging

```php
$result = $urlValidator->validateUrl(
    url: 'https://example.com/webhook',
    tenantId: 1,
    userId: 5
);
```

### Exception Mode

```php
try {
    $urlValidator->validateUrlOrFail('https://example.com/webhook');
    // URL is valid
} catch (InvalidArgumentException $e) {
    // URL is invalid/blocked
    echo $e->getMessage();
}
```

### Runtime Configuration

```php
// Enable strict mode
$urlValidator->enableStrictMode();

// Enable allowlist mode
$urlValidator->enableAllowlistMode([
    'hooks.slack.com',
    '*.example.com',
]);

// Check current mode
if ($urlValidator->isStrictModeEnabled()) {
    // ...
}
```

## Integration Points

### Webhook Controller

The webhook controller automatically validates URLs:

```php
// In WebhookController::store()
$urlValidationResult = $this->urlValidationService->validateUrl(
    $validated['url'],
    allowLegacy: false,
    tenantId: $tenantId,
    userId: $userId,
    skipDnsRebindingCheck: app()->environment('testing')
);

if (! $urlValidationResult['valid']) {
    return response()->json([
        'success' => false,
        'error' => [
            'code' => 'SSRF_PROTECTION',
            'message' => 'Webhook URL validation failed',
            'details' => $urlValidationResult['error'],
        ],
    ], 422);
}
```

### Protected Endpoints

- `POST /api/v1/tenants/{tenant_id}/webhooks` - Create webhook
- `PUT /api/v1/tenants/{tenant_id}/webhooks/{webhook}` - Update webhook
- `POST /api/v1/tenants/{tenant_id}/webhooks/{webhook}/test` - Test webhook

## Testing

### Run Tests

```bash
php artisan test --filter=UrlValidationServiceTest
php artisan test --filter=WebhookSsrfProtectionTest
```

### Test Coverage

The test suite covers:
- ✅ Valid public URLs
- ✅ Localhost and loopback addresses
- ✅ Private IP ranges (Class A, B, C)
- ✅ Link-local addresses
- ✅ Cloud metadata endpoints
- ✅ Container/orchestration hostnames
- ✅ Invalid URL formats
- ✅ Strict mode functionality
- ✅ Allowlist mode with wildcard matching
- ✅ IPv6 addresses
- ✅ DNS rebinding protection
- ✅ Audit logging

### Testing with Internal URLs

For development/testing, you can relax SSRF protection:

```env
SSRF_TESTING_MODE=true
SSRF_STRICT_MODE=false
```

⚠️ **WARNING:** Never use these settings in production!

## Monitoring

### Log Analysis

Monitor logs for SSRF attempts:

```bash
# View blocked SSRF attempts
grep "SSRF.*blocked" storage/logs/laravel.log

# View critical risk events
grep "risk_level.*critical" storage/logs/laravel.log
```

### Database Queries

```sql
-- View recent SSRF blocks
SELECT * FROM audit_logs 
WHERE event_type LIKE 'security.ssrf%' 
ORDER BY created_at DESC 
LIMIT 100;

-- Count SSRF attempts by type
SELECT event_type, COUNT(*) as count 
FROM audit_logs 
WHERE event_type LIKE 'security.ssrf%' 
GROUP BY event_type;
```

## Migration Plan

### Phase 1: Deploy with Logging Only (Optional)

```env
SSRF_STRICT_MODE=false
SSRF_LOG_BLOCKED=true
SSRF_AUDIT_LOGGING=true
```

Monitor for 1-2 weeks to identify any legitimate internal URLs.

### Phase 2: Enable Protection

```env
SSRF_STRICT_MODE=true
SSRF_DNS_REBINDING_PROTECTION=true
```

### Phase 3: Enable Allowlist (Optional - Highest Security)

```env
SSRF_ALLOWLIST_ENABLED=true
SSRF_ALLOWED_DOMAINS=hooks.slack.com,*.yourdomain.com
```

## Rollback Strategy

If SSRF protection causes issues:

1. **Quick Disable:**
   ```env
   SSRF_TESTING_MODE=true
   ```

2. **Relax Specific Settings:**
   ```env
   SSRRF_STRICT_MODE=false
   SSRF_DNS_REBINDING_PROTECTION=false
   ```

3. **Add to Allowlist:**
   ```env
   SSRF_ALLOWED_DOMAINS=legitimate-internal-service.com
   ```

## Backward Compatibility

✅ **No Breaking Changes**
- Existing valid webhooks continue to work
- API response structure unchanged
- Only malicious URLs are blocked

⚠️ **Potential Impact**
- Webhooks pointing to internal networks will be blocked
- Users will receive clear error messages with guidance

## Security Recommendations

### Production (Recommended)
```env
SSRF_STRICT_MODE=true
SSRF_ALLOWLIST_ENABLED=false
SSRF_DNS_REBINDING_PROTECTION=true
SSRF_VALIDATE_REDIRECTS=false
SSRF_AUDIT_LOGGING=true
```

### High-Security Production
```env
SSRF_STRICT_MODE=true
SSRF_ALLOWLIST_ENABLED=true
SSRF_ALLOWED_DOMAINS=hooks.slack.com,discord.com,*.yourdomain.com
SSRF_DNS_REBINDING_PROTECTION=true
SSRF_VALIDATE_REDIRECTS=true
SSRF_AUDIT_LOGGING=true
```

### Development
```env
SSRF_STRICT_MODE=false
SSRF_TESTING_MODE=true
SSRF_AUDIT_LOGGING=true
```

## References

- [OWASP SSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html)
- [OWASP Top 10: A10 SSRF](https://owasp.org/Top10/A10_2021-Server-Side_Request_Forgery_%28SSRF%29/)
- [AWS Metadata Protection](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/instancedata-data-retrieval-v2.html)

## Related Files

- `app/Services/UrlValidationService.php` - Core validation service
- `config/ssrf.php` - Configuration file
- `app/Http/Controllers/WebhookController.php` - Webhook integration
- `tests/Feature/Services/UrlValidationServiceTest.php` - Unit tests
- `tests/Feature/Security/WebhookSsrfProtectionTest.php` - Integration tests

---

**Last Updated:** March 24, 2026  
**Version:** 2.0.0  
**Author:** Security Engineering Team
