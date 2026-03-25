# Security Hardening

## Overview

This document provides comprehensive security hardening guidelines for MSWMS deployments. Following these practices will help protect your application from common security threats and vulnerabilities.

## Server Hardening

### SSH Security

**1. Disable Root Login:**
```bash
sudo nano /etc/ssh/sshd_config
```

```ssh
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
AuthenticationMethods publickey
MaxAuthTries 3
LoginGraceTime 60
```

**2. Change SSH Port (Optional):**
```ssh
Port 2222
```

**3. Allow Specific Users:**
```ssh
AllowUsers deploy admin
```

**4. Restart SSH:**
```bash
sudo systemctl restart sshd
```

**5. Configure SSH Keys:**
```bash
# Generate key pair (local machine)
ssh-keygen -t ed25519 -C "your_email@example.com"

# Copy to server
ssh-copy-id -p 2222 user@your-server-ip

# Test connection
ssh -p 2222 user@your-server-ip
```

### Firewall Configuration

**UFW (Ubuntu/Debian):**
```bash
# Install UFW
sudo apt install -y ufw

# Set default policies
sudo ufw default deny incoming
sudo ufw default allow outgoing

# Allow SSH (custom port if changed)
sudo ufw allow 2222/tcp

# Allow HTTP and HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Enable firewall
sudo ufw enable

# Check status
sudo ufw status verbose
```

**firewalld (CentOS/RHEL):**
```bash
# Install firewalld
sudo yum install -y firewalld

# Start and enable
sudo systemctl start firewalld
sudo systemctl enable firewalld

# Set default zone
sudo firewall-cmd --set-default-zone=drop

# Add services
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https

# Add custom port
sudo firewall-cmd --permanent --add-port=2222/tcp

# Reload
sudo firewall-cmd --reload

# Check status
sudo firewall-cmd --list-all
```

### Fail2Ban Configuration

**Install Fail2Ban:**
```bash
sudo apt install -y fail2ban
```

**Configure Fail2Ban:**
```bash
sudo nano /etc/fail2ban/jail.local
```

```ini
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5
backend = auto
usedns = warn
logencoding = auto
enabled = true
mode = aggressive

[sshd]
enabled = true
port = 2222
filter = sshd
logpath = /var/log/auth.log
maxretry = 3
bantime = 86400

[nginx-http-auth]
enabled = true
port = http,https
filter = nginx-http-auth
logpath = /var/log/nginx/*error.log
maxretry = 3
bantime = 3600

[nginx-limit-req]
enabled = true
port = http,https
filter = nginx-limit-req
logpath = /var/log/nginx/*error.log
maxretry = 5
bantime = 1800

[nginx-botsearch]
enabled = true
port = http,https
filter = nginx-botsearch
logpath = /var/log/nginx/*access.log
maxretry = 2
bantime = 86400
```

**Restart Fail2Ban:**
```bash
sudo systemctl restart fail2ban
sudo systemctl enable fail2ban
sudo systemctl status fail2ban
```

**Check Fail2Ban Status:**
```bash
sudo fail2ban-client status
sudo fail2ban-client status sshd
```

## PHP Security Hardening

### PHP Configuration

**Edit php.ini:**
```bash
sudo nano /etc/php/8.3/fpm/php.ini
```

**Security Settings:**
```ini
; Disable dangerous functions
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source

; Hide PHP version
expose_php = off

; Error handling
display_errors = off
log_errors = on
error_log = /var/log/php-fpm/error.log

; File uploads
file_uploads = On
upload_max_filesize = 20M
post_max_size = 25M
max_file_uploads = 20

; Resource limits
memory_limit = 512M
max_execution_time = 60
max_input_time = 60

; Session security
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
session.gc_maxlifetime = 28800

; Open basedir restriction
open_basedir = /var/www/mswms:/tmp

; Disable URL fopen wrappers
allow_url_fopen = Off
allow_url_include = Off

; Register globals (should be off by default)
register_globals = Off

; Magic quotes (should be off by default)
magic_quotes_gpc = Off
magic_quotes_runtime = Off
magic_quotes_sybase = Off
```

### PHP-FPM Pool Security

**Edit Pool Configuration:**
```bash
sudo nano /etc/php/8.3/fpm/pool.d/www.conf
```

```ini
[www]
; User and group
user = www-data
group = www-data

; Listen socket
listen = /run/php/php8.3-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

; Security
security.limit_extensions = .php
security.limit_extensions = .php

; Environment
env[HOSTNAME] =
env[PATH] = /usr/local/bin:/usr/bin:/bin
env[TMP] = /tmp
env[TMPDIR] = /tmp
env[TEMP] = /tmp

; PHP settings
php_admin_value[sendmail_path] = /usr/sbin/sendmail -t -i -f www@mswms.example.com
php_flag[display_errors] = off
php_admin_value[error_log] = /var/log/php-fpm/www-error.log
php_admin_flag[log_errors] = on
php_admin_value[memory_limit] = 512M
php_admin_value[max_execution_time] = 60
php_admin_value[upload_max_filesize] = 20M
php_admin_value[post_max_size] = 25M
php_admin_value[open_basedir] = /var/www/mswms:/tmp
php_admin_flag[expose_php] = off
php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source
```

## Application Security

### Laravel Security Configuration

**1. Application Key:**
```bash
# Ensure APP_KEY is set and secure
php artisan key:generate

# Verify in .env
grep APP_KEY .env
```

**2. Debug Mode:**
```ini
# .env - NEVER enable debug in production
APP_DEBUG=false
```

**3. Trusted Proxies:**
```php
// app/Http/Middleware/TrustProxies.php
protected $proxies = '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16';
// Or specific IPs
protected $proxies = '192.168.1.1,192.168.1.2';
```

**4. CORS Configuration:**
```php
// config/cors.php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['https://app.mswms.example.com'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

### Input Validation

**Use Form Requests:**
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|uuid|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|uuid|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:1000',
            'payment_method' => 'required|in:credit_card,cash,bank_transfer',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Customer is required',
            'items.required' => 'At least one item is required',
            'items.*.quantity.max' => 'Quantity cannot exceed 1000 units',
        ];
    }
}
```

### SQL Injection Prevention

**Use Eloquent ORM:**
```php
// ✓ SAFE - Using Eloquent
$users = User::where('status', 'active')->get();

// ✓ SAFE - Using Parameter Binding
$users = DB::select('SELECT * FROM users WHERE status = ?', ['active']);

// ✗ UNSAFE - Never do this
$status = request('status');
$users = DB::select("SELECT * FROM users WHERE status = '$status'");
```

### XSS Prevention

**Escape Output:**
```blade
{{-- In Blade templates --}}
{{ $userInput }}  {{-- Automatically escaped --}}

{{-- For raw HTML (use with caution) --}}
{!! $trustedHtml !!}

{{-- In PHP --}}
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');
```

**Sanitize Input:**
```php
use Illuminate\Support\Facades\Purifier;

$cleanInput = Purifier::clean($request->input('content'));
```

## Authentication Security

### Sanctum Configuration

**Token Expiration:**
```php
// config/sanctum.php
return [
    'expiration' => 24 * 60, // 24 hours in minutes
    
    'middleware' => [
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    ],
];
```

**Token Abilities:**
```php
// Create token with specific abilities
$token = $user->createToken('api-token', ['orders:read', 'orders:write'])->plainTextToken;

// Check abilities
if ($user->tokenCan('orders:read')) {
    // User can read orders
}
```

### Password Policy

**Enforce Strong Passwords:**
```php
<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if (strlen($value) < 12) {
            $fail('The :attribute must be at least 12 characters long.');
        }

        if (!preg_match('/[A-Z]/', $value)) {
            $fail('The :attribute must contain at least one uppercase letter.');
        }

        if (!preg_match('/[a-z]/', $value)) {
            $fail('The :attribute must contain at least one lowercase letter.');
        }

        if (!preg_match('/[0-9]/', $value)) {
            $fail('The :attribute must contain at least one number.');
        }

        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $value)) {
            $fail('The :attribute must contain at least one special character.');
        }

        // Check against common passwords
        $commonPasswords = ['password', '123456', 'qwerty'];
        if (in_array(strtolower($value), $commonPasswords)) {
            $fail('The :attribute is too common. Please choose a stronger password.');
        }
    }
}
```

**Usage:**
```php
public function rules(): array
{
    return [
        'password' => ['required', 'confirmed', new StrongPassword()],
    ];
}
```

### Rate Limiting

**Configure Rate Limits:**
```php
// app/Providers/RouteServiceProvider.php
public function boot(): void
{
    RateLimiter::for('api', function (Illuminate\Http\Request $request) {
        return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
    });

    RateLimiter::for('auth', function (Illuminate\Http\Request $request) {
        return Limit::perMinute(30)->by($request->ip());
    });

    RateLimiter::for('admin', function (Illuminate\Http\Request $request) {
        return Limit::perMinute(200)->by($request->user()?->id);
    });
}
```

**Apply Rate Limiting:**
```php
// routes/api.php
Route::middleware(['throttle:api'])->group(function () {
    // API routes
});

Route::middleware(['throttle:auth'])->group(function () {
    // Auth routes
});
```

## Data Protection

### Encryption

**Encrypt Sensitive Data:**
```php
use Illuminate\Support\Facades\Crypt;

// Encrypt
$encrypted = Crypt::encryptString($sensitiveData);

// Decrypt
$decrypted = Crypt::decryptString($encrypted);

// Encrypt model attribute
// In Model class
protected function casts(): array
{
    return [
        'ssn' => 'encrypted',
        'bank_account' => 'encrypted',
    ];
}
```

### Hashing

**Hash Passwords:**
```php
use Illuminate\Support\Facades\Hash;

// Hash password
$hashedPassword = Hash::make($password);

// Verify password
if (Hash::check($password, $hashedPassword)) {
    // Password matches
}
```

## File Upload Security

### Validate Uploads

```php
public function rules(): array
{
    return [
        'file' => [
            'required',
            'file',
            'max:10240', // 10MB max
            'mimes:jpg,jpeg,png,pdf,doc,docx',
            function ($attribute, $value, $fail) {
                // Check MIME type
                $mimeType = $value->getMimeType();
                $allowedMimeTypes = [
                    'image/jpeg',
                    'image/png',
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ];

                if (!in_array($mimeType, $allowedMimeTypes)) {
                    $fail('The file type is not allowed.');
                }
            },
        ],
    ];
}
```

### Store Securely

```php
// Store with unique name
$path = $request->file('file')->store('uploads', [
    'disk' => 'private',
    'filename' => uniqid() . '.' . $request->file('file')->getClientOriginalExtension(),
]);

// Or store with original name (sanitized)
$filename = preg_replace('/[^A-Za-z0-9_.-]/', '_', $request->file('file')->getClientOriginalName());
$path = $request->file('file')->storeAs('uploads', $filename, 'private');
```

## Security Headers

### Nginx Configuration

```nginx
# HSTS
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;

# X-Frame-Options
add_header X-Frame-Options "DENY" always;

# X-Content-Type-Options
add_header X-Content-Type-Options "nosniff" always;

# X-XSS-Protection
add_header X-XSS-Protection "1; mode=block" always;

# Referrer-Policy
add_header Referrer-Policy "strict-origin-when-cross-origin" always;

# Content-Security-Policy
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' https:; frame-ancestors 'none'; base-uri 'self'; form-action 'self';" always;

# Permissions-Policy
add_header Permissions-Policy "geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=()" always;
```

## Security Scanning

### Automated Security Scans

**Install Security Scanner:**
```bash
# Install Laravel Security Checker
composer require --dev roave/security-advisories

# Or use local-php-security-checker
composer require --dev localheinz/php-security-checker
```

**Run Security Scan:**
```bash
# Check dependencies
composer audit

# Check for vulnerabilities
php artisan security:check
```

### OWASP ZAP Scan

**Install OWASP ZAP:**
```bash
# Download from https://www.zaproxy.org/download/

# Run scan
zap-cli quick-scan -s -x -r https://api.mswms.example.com
```

## Security Audit Checklist

### Server Security
- [ ] SSH hardened (key-only, no root login)
- [ ] Firewall configured and enabled
- [ ] Fail2Ban installed and configured
- [ ] Automatic security updates enabled
- [ ] Unnecessary services disabled
- [ ] File permissions correct

### PHP Security
- [ ] Dangerous functions disabled
- [ ] PHP version hidden
- [ ] Display errors disabled
- [ ] Open basedir restricted
- [ ] File uploads validated

### Application Security
- [ ] APP_KEY set and secure
- [ ] Debug mode disabled
- [ ] CORS configured properly
- [ ] CSRF protection enabled
- [ ] Rate limiting configured
- [ ] Input validation implemented
- [ ] Output escaping implemented

### Authentication Security
- [ ] Strong password policy
- [ ] Token expiration configured
- [ ] Rate limiting on auth endpoints
- [ ] Account lockout implemented
- [ ] 2FA available (optional)

### Data Protection
- [ ] Sensitive data encrypted
- [ ] Passwords hashed with bcrypt
- [ ] SSL/TLS enabled
- [ ] SQL injection prevented
- [ ] XSS prevented

### Monitoring
- [ ] Security logging enabled
- [ ] Intrusion detection configured
- [ ] Alert system in place
- [ ] Regular security audits scheduled

## Incident Response

### Security Incident Procedure

**1. Identify:**
- Determine the nature of the incident
- Check logs for suspicious activity
- Identify affected systems

**2. Contain:**
- Isolate affected systems
- Change compromised credentials
- Block malicious IPs

**3. Eradicate:**
- Remove malware/backdoors
- Patch vulnerabilities
- Update security configurations

**4. Recover:**
- Restore from clean backups
- Monitor for reinfection
- Verify system integrity

**5. Learn:**
- Document the incident
- Update security procedures
- Implement additional controls

### Emergency Contacts

Maintain a list of emergency contacts:
- System Administrator
- Security Team
- Database Administrator
- Legal/Compliance
- Hosting Provider

---

**Previous Section**: [← Monitoring & Logging](09-monitoring-logging.md)  
**Next Section**: [Performance Optimization →](11-performance-optimization.md)
