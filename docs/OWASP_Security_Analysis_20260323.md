# 🛡️ OWASP Top 10 Security Analysis Report

## POS WMS Backend - Laravel 13 Application

---

## 🚨 [A01:2021 - Broken Access Control]

### Finding 1: Insufficient Authorization on User Search Endpoint

**Severity:** 🔴 HIGH

**Location:** `/app/Http/Controllers/Admin/UserController.php` (lines 52-57)

**Issue:** The `index()` method accepts user-controlled sorting parameters (`sort_by`, `sort_order`) without validation, enabling potential SQL injection via ORDER BY clause.

```php
// VULNERABLE CODE (lines 52-57)
$sortBy = $request->get('sort_by', 'created_at');
$sortOrder = $request->get('sort_order', 'desc');
$query->orderBy($sortBy, $sortOrder);
```

**Risk:** Attacker can inject arbitrary SQL expressions in ORDER BY clause, potentially extracting sensitive data through time-based blind SQL injection.

**Fix:**
```php
// SECURE CODE
$allowedSortFields = ['name', 'email', 'created_at', 'updated_at', 'is_active'];
$sortBy = $request->get('sort_by', 'created_at');
$sortOrder = $request->get('sort_order', 'desc');

// Validate sort field against whitelist
if (!in_array($sortBy, $allowedSortFields)) {
    $sortBy = 'created_at';
}

// Validate sort direction
$sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

$query->orderBy($sortBy, $sortOrder);
```

**Prevention:** Always whitelist and validate user-controlled inputs used in SQL queries, even with query builders.

---

### Finding 2: Missing Authorization on Form Request Authorize Methods

**Severity:** 🟠 MEDIUM

**Location:** Multiple Form Request classes (`StoreOrderRequest.php`, `StoreProductRequest.php`, etc.)

**Issue:** All Form Request classes return `true` in their `authorize()` methods, bypassing authorization checks.

```php
// StoreOrderRequest.php (line 12)
public function authorize(): bool
{
    return true; // Anyone can make this request
}
```

**Risk:** Any authenticated user can perform actions they shouldn't have permission for (e.g., creating orders, modifying products).

**Fix:**
```php
public function authorize(): bool
{
    // Check if user has permission to create orders
    return $this->user()->hasPermission('orders.create');
}
```

**Prevention:** Implement proper authorization checks in Form Request `authorize()` methods using the existing permission system.

---

### Finding 3: Tenant Scoping Bypass Potential

**Severity:** 🟠 MEDIUM

**Location:** `/app/Http/Middleware/EnsureTenantIsScoped.php` (lines 47-53)

**Issue:** Tenant scoping relies on `tenant_id` from route parameter, but some controllers don't properly validate resource ownership.

```php
// EnsureTenantIsScoped.php (lines 47-53)
if ($request->user()->tenant_id !== $tenant->id) {
    return response()->json([
        'message' => 'Unauthorized access to tenant',
        // ...
    ], Response::HTTP_FORBIDDEN);
}
```

**Risk:** If a controller directly uses a resource ID without verifying it belongs to the scoped tenant, users could access other tenants' data.

**Fix:** Add a `forTenant()` scope check to ALL resource queries:

```php
// In controllers - ALWAYS use tenant scoping
$resource = Model::query()
    ->forTenant($request->route('tenant_id'))
    ->findOrFail($resourceId);
```

**Prevention:** Implement global scopes on models to automatically filter by tenant_id.

---

## 🚨 [A02:2021 - Cryptographic Failures]

### Finding 4: Weak Token Authentication for Webhooks

**Severity:** 🟠 MEDIUM

**Location:** `/app/Services/WebhookService.php` (lines 66-67, 224-227)

**Issue:** Webhook signatures use simple HMAC-SHA256 without timestamp validation, making replay attacks possible.

```php
// WebhookService.php (lines 224-227)
public function verifySignature(array $payload, string $signature, string $secret): bool
{
    $expectedSignature = $this->generateSignature($payload, $secret);
    return hash_equals($expectedSignature, $signature);
}
```

**Risk:** Attacker can capture and replay webhook payloads indefinitely.

**Fix:**
```php
// Add timestamp validation
public function verifySignature(array $payload, string $signature, string $secret, int $tolerance = 300): bool
{
    // Check timestamp
    if (!isset($payload['timestamp'])) {
        return false;
    }
    
    $timestamp = strtotime($payload['timestamp']);
    if (abs(time() - $timestamp) > $tolerance) {
        return false; // Replay attack detected
    }
    
    $expectedSignature = $this->generateSignature($payload, $secret);
    return hash_equals($expectedSignature, $signature);
}
```

**Prevention:** Always include timestamp validation in webhook signature verification.

---

### Finding 5: No Encryption for Sensitive Data at Rest

**Severity:** 🟡 LOW

**Location:** Database schema (all models)

**Issue:** Sensitive data like customer information, pricing, and webhook secrets are stored in plaintext.

**Risk:** Database compromise exposes all sensitive business data.

**Fix:** Use Laravel's encryption casting for sensitive fields:

```php
// In models
protected function casts(): array
{
    return [
        'secret' => 'encrypted',
        'api_key' => 'encrypted',
        // ...
    ];
}
```

**Prevention:** Encrypt sensitive fields using Laravel's encrypted casts.

---

## 🚨 [A03:2021 - Injection]

### Finding 6: SQL Injection via ORDER BY (Related to Finding 1)

**Severity:** 🔴 HIGH

**Location:** `/app/Http/Controllers/Admin/UserController.php`, `/app/Http/Controllers/ProductController.php`, `/app/Http/Controllers/CategoryController.php`, etc.

**Issue:** Multiple controllers use unvalidated user input for sorting:

```php
// Found in 7 controllers
$sortBy = $request->get('sort_by', 'created_at');
$sortOrder = $request->get('sort_order', 'desc');
$query->orderBy($sortBy, $sortOrder);
```

**Risk:** SQL injection through ORDER BY clause (doesn't support parameter binding).

**Fix:** Apply whitelist validation in ALL controllers with sorting:

```php
$allowedSorts = ['name', 'email', 'created_at', 'updated_at'];
$sortBy = in_array($request->get('sort_by'), $allowedSorts) 
    ? $request->get('sort_by') 
    : 'created_at';
```

**Prevention:** Never trust user input for SQL ORDER BY clauses.

---

### Finding 7: Potential Command Injection in SystemSettingsController

**Severity:** 🟠 MEDIUM

**Location:** `/app/Http/Controllers/Admin/SystemSettingsController.php` (lines 305-343)

**Issue:** The `runCommand()` method allows execution of Artisan commands. While there's a whitelist, it could be expanded or bypassed.

```php
// Allowed commands (lines 317-324)
$allowedCommands = [
    'cache:clear',
    'config:clear',
    'route:clear',
    'view:clear',
    'optimize',
    'optimize:clear',
];
```

**Risk:** If an attacker gains super admin access, they could:
1. Add new allowed commands via code modification
2. Use `optimize:clear` to delete compiled files (DoS)
3. Potentially chain with other vulnerabilities

**Fix:**
```php
// Add additional security checks
public function runCommand(Request $request): JsonResponse
{
    $validated = $request->validate([
        'command' => ['required', 'string', 'regex:/^[a-z:.-]+$/'], // Strict pattern
    ]);

    $command = $validated['command'];
    
    // Block dangerous patterns
    if (preg_match('/(exec|system|shell|eval)/i', $command)) {
        return response()->json([
            'success' => false,
            'error' => ['code' => 'COMMAND_BLOCKED', 'message' => 'Command contains blocked patterns'],
        ], 403);
    }
    
    // ... rest of validation
}
```

**Prevention:** Consider removing this endpoint entirely in production; use deployment pipelines instead.

---

## 🚨 [A04:2021 - Insecure Design]

### Finding 8: Missing Rate Limiting on Critical Endpoints

**Severity:** 🟠 MEDIUM

**Location:** `/routes/api.php` (various routes)

**Issue:** While rate limiting is configured, some critical endpoints lack specific limits:
- Password reset (not implemented but should be)
- Webhook test endpoint (can be used for SSRF amplification)
- Export endpoints (resource-intensive)

**Risk:** Resource exhaustion, SSRF amplification attacks.

**Fix:**
```php
// Add specific rate limiting for heavy operations
Route::middleware(['auth:sanctum', 'throttle:api-heavy'])->group(function () {
    Route::get('/reports/inventory/export/stock-levels', [InventoryReportController::class, 'exportStockLevels']);
    // ... other export routes
});

// Webhook test should have stricter limits
Route::post('/webhooks/{webhook}/test', [WebhookController::class, 'test'])
    ->middleware('throttle:10,1'); // 10 per minute
```

**Prevention:** Apply stricter rate limits to resource-intensive and potentially dangerous endpoints.

---

### Finding 9: Impersonation Token Security

**Severity:** 🟡 LOW

**Location:** `/app/Services/ImpersonationService.php`

**Issue:** Impersonation tokens have a 15-minute expiration but no usage tracking or IP binding.

**Risk:** Stolen impersonation tokens can be used from any location until expiration.

**Fix:**
```php
// Add IP binding to impersonation tokens
public function generateImpersonationToken(User $user, User $impersonator): array
{
    $token = $user->createToken('impersonation_' . $impersonator->id, [
        'impersonator_id' => $impersonator->id,
        'impersonator_ip' => request()->ip(), // Bind to IP
    ]);
    
    // ...
}

// Verify IP on use
public function validateImpersonationToken(AccessToken $token): bool
{
    return $token->abilities['impersonator_ip'] === request()->ip();
}
```

**Prevention:** Bind sensitive tokens to IP addresses and implement usage monitoring.

---

## 🚨 [A05:2021 - Security Misconfiguration]

### Finding 10: Debug Mode Information Disclosure

**Severity:** 🟠 MEDIUM

**Location:** `/config/app.php` (line 21), `.env.example`

**Issue:** `APP_DEBUG=true` in development configuration. If enabled in production, detailed error information is exposed.

```php
// config/app.php
'debug' => (bool) env('APP_DEBUG', false),
```

**Risk:** Stack traces, SQL queries, and file paths exposed in error responses.

**Fix:** Ensure production environment has:
```env
APP_DEBUG=false
APP_ENV=production
```

**Prevention:** Use environment-specific configuration and verify `APP_DEBUG=false` in production.

---

### Finding 11: CSP Allows Unsafe Scripts

**Severity:** 🟡 LOW

**Location:** `/app/Http/Middleware/SecurityHeadersMiddleware.php` (lines 37-44)

**Issue:** Content Security Policy allows `'unsafe-inline'` and `'unsafe-eval'` for scripts.

```php
"script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdn.jsdelivr.net; "
```

**Risk:** XSS attacks possible if any third-party script is compromised.

**Fix:** For production, use nonces or hashes:
```php
// Generate nonce per request
$nonce = base64_encode(random_bytes(16));
view()->share('cspNonce', $nonce);

// In CSP
"script-src 'self' 'nonce-{$nonce}' https://unpkg.com;"
```

**Prevention:** Remove `'unsafe-inline'` and `'unsafe-eval'` in production; use nonces.

---

## 🚨 [A06:2021 - Vulnerable and Outdated Components]

### Finding 12: No Component Vulnerability Monitoring

**Severity:** 🟡 LOW

**Location:** `/composer.json`, `/package.json`

**Issue:** No automated vulnerability scanning configured for dependencies.

**Risk:** Vulnerable dependencies may go unnoticed.

**Fix:** Implement:
```bash
# Add to CI/CD pipeline
composer audit
npm audit
```

**Prevention:** Run `composer audit` regularly and subscribe to security advisories.

---

## 🚨 [A07:2021 - Identification and Authentication Failures]

### Finding 13: No Account Lockout After Failed Logins

**Severity:** 🟠 MEDIUM

**Location:** `/app/Http/Controllers/Auth/LoginController.php` (lines 20-45)

**Issue:** No account lockout or progressive delay after failed login attempts. Rate limiting is IP-based only.

```php
// LoginController.php - No lockout mechanism
if (! $user || ! Hash::check($request->password, $user->password)) {
    throw ValidationException::withMessages([
        'email' => ['The provided credentials are incorrect.'],
    ]);
}
```

**Risk:** Brute force attacks possible (mitigated partially by rate limiting, but IP rotation bypasses this).

**Fix:**
```php
use Illuminate\Support\Facades\Cache;

public function login(Request $request): JsonResponse
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    // Check for account lockout
    $lockoutKey = 'login_attempts:' . $request->email;
    $attempts = Cache::get($lockoutKey, 0);
    
    if ($attempts >= 5) {
        $lockoutTime = Cache::get($lockoutKey . ':lockout_until');
        if ($lockoutTime && now()->lt($lockoutTime)) {
            throw ValidationException::withMessages([
                'email' => ['Account locked. Try again in ' . now()->diffInSeconds($lockoutTime) . ' seconds.'],
            ]);
        }
    }

    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        // Increment failed attempts
        Cache::put($lockoutKey, $attempts + 1, now()->addMinutes(15));
        
        if ($attempts + 1 >= 5) {
            Cache::put($lockoutKey . ':lockout_until', now()->addMinutes(15), now()->addMinutes(15));
            // Log suspicious activity
            Log::warning('Account locked due to failed login attempts', ['email' => $request->email]);
        }
        
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    // Reset attempts on successful login
    Cache::forget($lockoutKey);
    Cache::forget($lockoutKey . ':lockout_until');
    
    // ... rest of login logic
}
```

**Prevention:** Implement account lockout with exponential backoff after failed attempts.

---

### Finding 14: No Session Invalidation on Password Change

**Severity:** 🟡 LOW

**Location:** User model and authentication flow

**Issue:** No password change functionality found; if implemented, should invalidate all sessions.

**Risk:** Compromised sessions remain valid after password change.

**Fix:**
```php
// When password is changed
public function changePassword(string $newPassword): void
{
    $this->password = Hash::make($newPassword);
    $this->save();
    
    // Invalidate all tokens
    $this->tokens()->delete();
    
    // Optional: Send notification
    $this->sendPasswordChangedNotification();
}
```

**Prevention:** Always invalidate existing sessions when passwords change.

---

## 🚨 [A08:2021 - Software and Data Integrity Failures]

### Finding 15: No Webhook URL Validation for Internal Networks

**Severity:** 🔴 HIGH

**Location:** `/app/Services/WebhookService.php` (lines 66-67, 149-150)

**Issue:** Webhook URLs are not validated to prevent SSRF attacks to internal networks.

```php
// WebhookService.php - No URL validation
$response = Http::withHeaders($webhook->getHeaders())
    ->withToken($webhook->secret ?? '')
    ->timeout($webhook->timeout)
    ->post($webhook->url, $this->preparePayload($webhook, $eventType, $payload));
```

**Risk:** Attacker can create webhooks pointing to internal services (127.0.0.1, 192.168.x.x, metadata endpoints).

**Fix:**
```php
use Illuminate\Support\Facades\Validator;

// Validate webhook URL before saving
public function store(Request $request): JsonResponse
{
    $validated = $request->validate([
        'url' => ['required', 'url', 'max:2048'],
        // ...
    ]);

    // Additional SSRF protection
    if (!$this->isSafeUrl($validated['url'])) {
        return response()->json([
            'success' => false,
            'error' => ['message' => 'URL points to internal network'],
        ], 422);
    }

    // ... rest of store logic
}

private function isSafeUrl(string $url): bool
{
    $parsedUrl = parse_url($url);
    
    // Block private IP ranges
    $blockedHosts = [
        '/^127\./',
        '/^10\./',
        '/^172\.(1[6-9]|2[0-9]|3[0-1])\./',
        '/^192\.168\./',
        '/^169\.254\./',
        '/^0\.0\.0\.0/',
        '/^localhost$/',
    ];
    
    $host = $parsedUrl['host'] ?? '';
    
    foreach ($blockedHosts as $pattern) {
        if (preg_match($pattern, $host)) {
            return false;
        }
    }
    
    // Check if hostname resolves to private IP
    $ip = gethostbyname($host);
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return !filter_var($ip, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
    
    return true;
}
```

**Prevention:** Always validate URLs against private IP ranges before making HTTP requests.

---

### Finding 16: No Signature Verification for Incoming Webhooks

**Severity:** 🟡 LOW

**Location:** Application doesn't implement incoming webhook handling

**Issue:** If incoming webhooks are added in the future, signature verification should be mandatory.

**Prevention:** Always verify webhook signatures using `hash_equals()` and timestamps.

---

## 🚨 [A09:2021 - Security Logging and Monitoring Failures]

### Finding 17: Insufficient Audit Logging for Security Events

**Severity:** 🟡 LOW

**Location:** `/app/Models/AuditLog.php`, various controllers

**Issue:** While AuditLog exists, not all security-sensitive actions are logged:
- Failed login attempts
- Permission changes
- Role modifications
- Webhook URL changes

**Risk:** Security incidents cannot be properly investigated.

**Fix:**
```php
// In LoginController
if (! $user || ! Hash::check($request->password, $user->password)) {
    AuditLog::create([
        'tenant_id' => null, // Global event
        'user_id' => null,
        'event_type' => 'auth.login_failed',
        'description' => 'Failed login attempt',
        'ip_address' => $request->ip(),
        'user_agent' => $request->userAgent(),
        'properties' => ['email' => $request->email],
    ]);
    
    throw ValidationException::withMessages([...]);
}
```

**Prevention:** Log all authentication, authorization, and data modification events.

---

### Finding 18: No Alerting for Suspicious Activity

**Severity:** 🟡 LOW

**Location:** Application-wide

**Issue:** No automated alerting for:
- Multiple failed logins
- Unusual data exports
- Bulk data modifications
- Super admin actions

**Risk:** Security incidents may go unnoticed for extended periods.

**Prevention:** Implement real-time alerting for security events via email/Slack.

---

## 🚨 [A10:2021 - Server-Side Request Forgery (SSRF)]

### Finding 19: SSRF via Webhook URLs (Related to Finding 15)

**Severity:** 🔴 CRITICAL

**Location:** `/app/Http/Controllers/WebhookController.php` (lines 36-59), `/app/Services/WebhookService.php`

**Issue:** Webhook URLs can point to internal network resources, cloud metadata endpoints, or localhost services.

```php
// WebhookController.php - No URL validation
$webhook = Webhook::create([
    'tenant_id' => $request->route('tenant_id'),
    'name' => $validated['name'],
    'url' => $validated['url'], // Potentially malicious URL
    // ...
]);
```

**Risk:** 
- Access to cloud metadata (AWS: 169.254.169.254)
- Internal service enumeration
- Port scanning internal network
- Access to admin panels on non-standard ports

**Fix:** Implement the `isSafeUrl()` method from Finding 15 and apply it to:
1. Webhook creation (`store()`)
2. Webhook updates (`update()`)
3. Webhook test (`test()`)

```php
// Add to WebhookController
private function validateWebhookUrl(string $url): bool
{
    $validator = Validator::make(['url' => $url], [
        'url' => ['required', 'url', 'active_url'],
    ]);

    if ($validator->fails()) {
        return false;
    }

    return $this->isSafeUrl($url);
}
```

**Prevention:** Block all private IP ranges and validate DNS resolution.

---

## Summary Table

| ID | Category | Severity | Count |
|----|----------|----------|-------|
| A01 | Broken Access Control | 🔴 HIGH, 🟠 MEDIUM | 3 |
| A02 | Cryptographic Failures | 🟠 MEDIUM, 🟡 LOW | 2 |
| A03 | Injection | 🔴 HIGH, 🟠 MEDIUM | 2 |
| A04 | Insecure Design | 🟠 MEDIUM, 🟡 LOW | 2 |
| A05 | Security Misconfiguration | 🟠 MEDIUM, 🟡 LOW | 2 |
| A06 | Vulnerable Components | 🟡 LOW | 1 |
| A07 | Auth Failures | 🟠 MEDIUM, 🟡 LOW | 2 |
| A08 | Integrity Failures | 🔴 HIGH, 🟡 LOW | 2 |
| A09 | Logging Failures | 🟡 LOW | 2 |
| A10 | SSRF | 🔴 CRITICAL | 1 |

---

## Priority Remediation Order

1. **🔴 CRITICAL:** Fix SSRF in webhooks (Finding 19/15)
2. **🔴 HIGH:** Fix SQL injection via ORDER BY (Finding 6/1)
3. **🔴 HIGH:** Add URL validation for webhooks (Finding 15)
4. **🟠 MEDIUM:** Implement Form Request authorization (Finding 2)
5. **🟠 MEDIUM:** Add account lockout (Finding 13)
6. **🟠 MEDIUM:** Fix command injection risk (Finding 7)
7. **🟠 MEDIUM:** Validate webhook replay attacks (Finding 4)
8. **🟠 MEDIUM:** Ensure tenant scoping on all queries (Finding 3)

---

## Positive Security Controls Found ✅

1. **Security Headers Middleware** - Comprehensive CSP, HSTS, X-Frame-Options
2. **Rate Limiting** - Configured for API, admin, and auth endpoints
3. **Form Requests** - Validation rules properly defined
4. **Sanctum Authentication** - Token-based auth implemented
5. **Multi-layer Authorization** - Role, permission, tenant, superadmin middleware
6. **Mass Assignment Protection** - All models use `$fillable`
7. **No env() in App Code** - Configuration properly separated
8. **Audit Logging** - AuditLog model and service implemented
9. **No File Uploads** - Reduced attack surface
10. **No Dangerous Functions** - No eval/exec/shell_exec in production code
