# OWASP A07 Quick Reference Card

## 🚀 Deployment Checklist

### Pre-Deployment
- [ ] Review configuration in `config/auth.php`
- [ ] Set environment variables (optional)
- [ ] Test in staging environment
- [ ] Backup database

### Deployment
```bash
# Run migration
php artisan migrate

# Clear cache (optional)
php artisan cache:clear

# Verify
php artisan test --compact --filter=LoginAttemptServiceTest
```

### Post-Deployment
- [ ] Monitor logs for lockout events
- [ ] Check audit logs
- [ ] Verify user login works
- [ ] Test lockout mechanism

---

## ⚙️ Configuration Quick Reference

### Environment Variables (`.env`)

```env
# Number of failed attempts before lockout
LOGIN_MAX_ATTEMPTS=5

# Attempts before warning message
LOGIN_WARNING_THRESHOLD=3

# Initial lockout duration (seconds)
LOGIN_BASE_LOCKOUT_DURATION=300

# Maximum lockout duration (seconds)
LOGIN_MAX_LOCKOUT_DURATION=1800

# Cache TTL for attempts (seconds)
LOGIN_CACHE_TTL=900

# Progressive delay multiplier
LOGIN_DELAY_MULTIPLIER=2
```

### Default Behavior

| Attempt # | Action | Delay |
|-----------|--------|-------|
| 1 | Error message | None |
| 2 | Error message | None |
| 3 | Warning + "2 attempts remaining" | 2 seconds |
| 4 | Warning + "1 attempt remaining" | 4 seconds |
| 5 | **ACCOUNT LOCKED** | 5 minutes |
| 6+ | Still locked | Exponential backoff |

---

## 🔍 Monitoring Commands

### Check User Lockout Status
```bash
php artisan tinker
```
```php
// Check attempts
Cache::get('login_attempts:user@example.com');

// Check lockout expiry
Cache::get('login_attempts:user@example.com:lockout_until');

// Clear lockout
Cache::forget('login_attempts:user@example.com');
Cache::forget('login_attempts:user@example.com:lockout_until');
```

### View Recent Auth Logs
```bash
# Failed logins
tail -f storage/logs/laravel.log | grep "Failed login"

# Account lockouts
tail -f storage/logs/laravel.log | grep "Account locked"

# Successful logins
tail -f storage/logs/laravel.log | grep "logged in successfully"
```

### Check Audit Logs
```bash
php artisan tinker
```
```php
// Recent auth events
AuditLog::where('event_type', 'like', 'auth.%')
    ->latest()
    ->take(10)
    ->get();

// Failed logins for specific user
AuditLog::where('event_type', 'auth.login_failed')
    ->where('user_id', 1)
    ->get();
```

---

## 🚨 Alert Triggers

Set up alerts for:

| Event | Threshold | Action |
|-------|-----------|--------|
| Account lockouts | >10/hour | Investigate potential attack |
| Failed logins (single IP) | >50/hour | Consider IP ban |
| Super admin lockout | Any | Immediate investigation |
| Suspicious logins | Any | Review audit logs |

---

## 🐛 Troubleshooting

### User Locked Out Accidentally
```bash
php artisan tinker
>>> Cache::forget('login_attempts:user@example.com');
>>> Cache::forget('login_attempts:user@example.com:lockout_until');
```

### Temporarily Disable Protection
```env
# In .env
LOGIN_MAX_ATTEMPTS=999  # Effectively disable
```

### Check Cache Driver
```bash
php artisan config:show cache.default
# Should be: database, redis, or memcached
# NOT: array (doesn't persist)
```

### Clear All Login Attempts
```bash
php artisan tinker
>>> $keys = Cache::getMultiple(['login_attempts:*']);
>>> foreach ($keys as $key) { Cache::forget($key); }
```

---

## 📊 Test Commands

### Run All Auth Tests
```bash
php artisan test --compact --filter="Auth|Login|Password"
```

### Run Lockout Tests Only
```bash
php artisan test --compact --filter="LoginAttemptServiceTest"
```

### Run Specific Test
```bash
php artisan test --compact --filter="test_account_locks_after_max_failed_attempts"
```

---

## 📝 API Response Examples

### Successful Login
```json
{
  "success": true,
  "data": {
    "user": { "id": 1, "name": "John", "email": "john@example.com" },
    "token": "1|abc123...",
    "token_type": "Bearer"
  },
  "message": "Login successful"
}
```

### Failed Login (Warning)
```json
{
  "success": false,
  "error": {
    "code": "validation_error",
    "message": "The given data was invalid.",
    "details": {
      "email": ["The provided credentials are incorrect. 2 attempts remaining."]
    }
  }
}
```

### Account Locked
```json
{
  "success": false,
  "error": {
    "code": "validation_error",
    "message": "The given data was invalid.",
    "details": {
      "email": ["Too many failed attempts. Account locked. Try again in 5 minutes."]
    }
  }
}
```

### Suspicious Login
```json
{
  "success": true,
  "data": {
    "user": { ... },
    "token": "...",
    "security_notice": "Login from new device or location detected"
  },
  "message": "Login successful"
}
```

---

## 🔐 Security Features

| Feature | Status | Description |
|---------|--------|-------------|
| Progressive Delays | ✅ | 2s → 4s → 8s → 16s |
| Account Lockout | ✅ | After 5 failed attempts |
| Exponential Backoff | ✅ | 5min → 10min → 20min → 30min |
| IP Tracking | ✅ | Logs last login IP |
| Suspicious Detection | ✅ | IP change, unusual hours |
| Session Invalidation | ✅ | On password change |
| Audit Logging | ✅ | All auth events |
| User Enumeration Prevention | ✅ | Consistent errors |

---

## 📞 Emergency Contacts

| Role | Contact |
|------|---------|
| System Admin | [Your contact] |
| Security Team | [Security contact] |
| On-Call Developer | [On-call contact] |

---

**Last Updated:** March 24, 2026  
**Version:** 1.0  
**Status:** ✅ Production Ready
