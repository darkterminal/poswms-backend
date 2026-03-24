# OWASP A09:2021 - Security Logging and Monitoring Implementation

## Overview

This document describes the implementation of OWASP A09:2021 - Security Logging and Monitoring fixes for the POS WMS Backend system.

## Problem Summary

The existing audit logging system had gaps in:
- Missing audit logs for critical security events (auth failures, permission denials, webhook changes)
- No centralized security audit helper
- Synchronous logging causing potential performance degradation
- Inconsistent logging across different controllers and services

## Backward Compatibility Risk

**LOW** - All changes are additive:
- New `SecurityAuditLogger` service complements existing `AuditLogService`
- Existing audit logging continues to work
- New `description` column in `audit_logs` table is nullable
- Made `tenant_id`, `auditable_type`, `auditable_id` nullable for security events

## Safe Implementation Strategy

### 1. Central Security Audit Logger

Created `App\SecurityAuditLogger` - a centralized service for security event logging with:
- **Synchronous logging** for critical events (auth failures, SSRF attempts, account lockouts)
- **Asynchronous logging** via queue for high-volume events (role changes, webhook updates)
- **Pre-built methods** for common security events
- **Automatic request context** capture (IP, user agent, URL)

### 2. Async Queue-Based Logging

Created `App\Jobs\LogSecurityEvent` job:
- Implements `ShouldQueue` interface
- Dedicated `security-audit` queue
- Retry mechanism (3 attempts, 10s backoff)
- Fallback to synchronous logging on failure

### 3. Enhanced AuditLog Model

Added to `App\Models\AuditLog`:
- `HIGH_RISK_EVENTS` constant with all security event types
- Query scopes for filtering (`highRisk()`, `authEvents()`, `securityEvents()`, etc.)
- `isHighRisk()` instance method
- `risk_level` accessor (high/medium/low)

### 4. Controller Integration

Added security audit logging to:
- **RoleController**: Role CRUD, role assignment/revocation
- **UserController** (Admin): Impersonation start/end
- **WebhookController**: Webhook CRUD, SSRF attempts, webhook testing

### 5. Service Integration

Enhanced `LoginAttemptService`:
- Uses `SecurityAuditLogger` for consistent logging
- Logs auth failures, account lockouts, successful logins
- Includes suspicious activity detection

## Code Fix (Before/After Examples)

### Before: Inconsistent Manual Logging

```php
// WebhookController.php - BEFORE
AuditLog::create([
    'tenant_id' => $tenantId,
    'user_id' => $userId,
    'event_type' => 'webhook.created',
    'auditable_type' => Webhook::class,
    'auditable_id' => $webhook->id,
    'ip_address' => $request->ip(),
    'user_agent' => $request->userAgent(),
    'metadata' => [
        'url' => $validated['url'],
        'events' => $validated['events'],
    ],
]);
```

### After: Centralized Security Logger

```php
// WebhookController.php - AFTER
$this->securityLogger->logWebhookChange(
    action: 'created',
    webhookId: $webhook->id,
    webhookData: [
        'name' => $webhook->name,
        'url' => $webhook->url,
        'events' => $webhook->events,
    ],
    tenantId: $tenantId,
    userId: $userId
);
```

### Before: No Async Support

All logging was synchronous, potentially slowing down critical operations.

### After: Async Support

```php
// Async logging for non-critical events
$this->securityLogger->logAsync(
    eventType: 'role.updated',
    description: "Role updated: {$role->name}",
    context: [/* ... */],
    tenantId: $tenantId,
    userId: $userId,
    request: $request,
);
```

## Migration Plan

### Database Migration

Run the migration to add the `description` column and make fields nullable:

```bash
php artisan migrate
```

This runs `2026_03_24_170709_add_description_to_audit_logs_table.php`:
- Adds `description` column (nullable)
- Makes `tenant_id` nullable (for global events)
- Makes `auditable_type` and `auditable_id` nullable (for security events)

### Configuration

Add to `.env`:

```env
# Security Audit Logging
SECURITY_AUDIT_MODE=async           # async or sync
SECURITY_AUDIT_QUEUE=security-audit
SECURITY_LOG_CHANNEL=security
SECURITY_LOG_LEVEL=warning
SECURITY_LOG_DAYS=90
SECURITY_AUDIT_RETENTION=365
```

### Queue Configuration

Ensure the `security-audit` queue is configured in `config/queue.php` and workers are running:

```bash
php artisan queue:work --queue=security-audit
```

### Service Registration

`SecurityAuditLogger` is auto-registered in `AppServiceProvider` as a singleton.

## Rollback Strategy

If issues arise:

1. **Disable async logging**: Set `SECURITY_AUDIT_MODE=sync` in `.env`
2. **Rollback migration**:
   ```bash
   php artisan migrate:rollback --step=1
   ```
3. **Remove controller integrations**: Revert controller changes (git checkout)
4. **Unregister service**: Remove from `AppServiceProvider`

## Test Cases

### Unit Tests

**Location**: `tests/Feature/SecurityAuditLoggingTest.php`

Tests cover:
- High-risk event identification
- Auth failure logging
- Account lockout logging
- Permission denial logging
- Role change logging
- Webhook change logging
- SSRF attempt logging
- Async job dispatch
- Audit log scopes
- Risk level calculation

**Location**: `tests/Feature/Jobs/LogSecurityEventTest.php`

Tests cover:
- Job creates audit log
- Empty event type handling
- Failed job fallback mechanism
- Retry configuration
- Null value handling
- Async metadata inclusion

### Running Tests

```bash
# Run all security audit tests
php artisan test --filter="SecurityAuditLoggingTest|LogSecurityEventTest"

# Run specific test
php artisan test --filter=test_log_auth_failure
```

## Logged Security Events

### Authentication Events
- `auth.login_failed` - Failed login attempt
- `auth.login_locked` - Account locked after max attempts
- `auth.login_success` - Successful login
- `auth.impersonation_started` - Super admin impersonation started
- `auth.impersonation_ended` - Impersonation session ended
- `auth.token_revoked` - Auth token revoked

### Authorization Events
- `authorization.denied` - Permission denied
- `role.assigned` - Role assigned to user
- `role.revoked` - Role removed from user
- `role.created` - New role created
- `role.updated` - Role updated
- `role.deleted` - Role deleted

### Webhook Events
- `webhook.created` - New webhook created
- `webhook.updated` - Webhook configuration changed
- `webhook.deleted` - Webhook deleted
- `webhook.tested` - Webhook test performed
- `webhook.ssrf_blocked` - SSRF attempt blocked

### Security Events
- `security.ssrf_detected` - SSRF attempt detected
- `security.suspicious_activity` - Suspicious activity flagged
- `security.rate_limit_exceeded` - Rate limit exceeded
- `security.data_export` - Large data export

## Performance Considerations

### Async Logging (Default)
- Non-blocking for critical operations
- Queue-based processing
- Retry on failure
- **Recommended for production**

### Sync Logging
- Immediate visibility
- Blocks request until logged
- **Use only for critical events** (auth failures, SSRF)

### Event-Specific Configuration

Configure in `config/security-audit.php`:

```php
'events' => [
    'auth.login_failed' => [
        'enabled' => true,
        'log_level' => 'warning',
        'async' => false, // Sync for immediate visibility
    ],
    'role.updated' => [
        'enabled' => true,
        'log_level' => 'info',
        'async' => true, // Async to avoid slowdown
    ],
],
```

## Monitoring & Alerting

### Log Channel

Security events are logged to the `security` channel (`storage/logs/security.log`).

### Alert Thresholds

Configure in `config/security-audit.php`:

```php
'alert_thresholds' => [
    'failed_logins' => 10,        // Alert after 10 failed logins
    'permission_denials' => 20,   // Alert after 20 denials
    'ssrf_attempts' => 5,         // Alert after 5 SSRF attempts
],
```

### Querying Security Events

```php
// Get all high-risk events
AuditLog::highRisk()->get();

// Get auth failures in last 24 hours
AuditLog::failedAuth()
    ->where('created_at', '>', now()->subDay())
    ->get();

// Get SSRF attempts for tenant
AuditLog::securityEvents()
    ->forTenant($tenantId)
    ->where('event_type', 'security.ssrf_detected')
    ->get();
```

## Compliance Notes

This implementation supports compliance with:
- **SOC 2**: Audit trail for security events
- **PCI DSS**: Logging of access to sensitive data
- **GDPR**: Audit trail for data modifications
- **OWASP ASVS**: V7 Logging and Monitoring

## Files Changed/Created

### New Files
- `app/SecurityAuditLogger.php` - Central security audit service
- `app/Jobs/LogSecurityEvent.php` - Async logging job
- `config/security-audit.php` - Security audit configuration
- `tests/Feature/SecurityAuditLoggingTest.php` - Feature tests
- `tests/Feature/Jobs/LogSecurityEventTest.php` - Job tests
- `docs/OWASP_A09_Implementation.md` - This documentation

### Modified Files
- `app/Models/AuditLog.php` - Added scopes and constants
- `app/Services/LoginAttemptService.php` - Enhanced logging
- `app/Http/Controllers/RoleController.php` - Added audit logging
- `app/Http/Controllers/Admin/UserController.php` - Added impersonation logging
- `app/Http/Controllers/WebhookController.php` - Enhanced SSRF logging
- `app/Providers/AppServiceProvider.php` - Registered SecurityAuditLogger
- `config/logging.php` - Added security log channel
- `database/migrations/*_add_description_to_audit_logs_table.php` - Schema update

## Next Steps

1. **Deploy to staging** and verify logging
2. **Configure alerts** for high-risk events
3. **Set up log rotation** for security.log
4. **Monitor queue** for async logging performance
5. **Review logs** weekly for suspicious activity
6. **Update runbooks** with security event response procedures
