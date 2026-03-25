# Environment Configuration

## Overview

This document provides comprehensive guidance for configuring environment variables for staging and production deployments of MSWMS. Proper environment configuration is critical for application security, performance, and functionality.

## Environment File Location

```
/var/www/mswms/.env
```

**Important**: The `.env` file should never be committed to version control and must be secured with appropriate file permissions (chmod 600).

## Environment File Template

### Staging Environment (.env.staging)

```ini
# ==========================================
# APPLICATION SETTINGS
# ==========================================

APP_NAME="MSWMS - Staging"
APP_ENV=staging
APP_KEY=base64:your_generated_app_key_here
APP_DEBUG=false
APP_URL=https://staging.mswms.example.com
APP_TIMEZONE=UTC

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=cache

# ==========================================
# LOGGING CONFIGURATION
# ==========================================

LOG_CHANNEL=errorlog
LOG_LEVEL=info
LOG_DEPRECATIONS_CHANNEL=null

# ==========================================
# DATABASE CONFIGURATION
# ==========================================

# PostgreSQL (Recommended for Staging)
DB_CONNECTION=pgsql
DB_HOST=staging-db.example.com
DB_PORT=5432
DB_DATABASE=mswms_staging
DB_USERNAME=mswms_staging_user
DB_PASSWORD=your_secure_database_password

# MySQL Alternative
# DB_CONNECTION=mysql
# DB_HOST=staging-db.example.com
# DB_PORT=3306
# DB_DATABASE=mswms_staging
# DB_USERNAME=mswms_staging_user
# DB_PASSWORD=your_secure_database_password
# DB_CHARSET=utf8mb4
# DB_COLLATION=utf8mb4_unicode_ci

# ==========================================
# SESSION CONFIGURATION
# ==========================================

SESSION_DRIVER=database
SESSION_LIFETIME=480
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=.mswms.example.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

# ==========================================
# CACHE CONFIGURATION
# ==========================================

CACHE_STORE=redis
CACHE_PREFIX=mswms_staging

# ==========================================
# QUEUE CONFIGURATION
# ==========================================

QUEUE_CONNECTION=database
DB_QUEUE_CONNECTION=pgsql
DB_QUEUE_TABLE=jobs
DB_QUEUE=default

# ==========================================
# REDIS CONFIGURATION
# ==========================================

REDIS_HOST=staging-redis.example.com
REDIS_PASSWORD=your_redis_password
REDIS_PORT=6379
REDIS_CLIENT=phpredis
REDIS_DB=0

# ==========================================
# MAIL CONFIGURATION
# ==========================================

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=noreply@example.com
MAIL_PASSWORD=your_mail_password
MAIL_FROM_ADDRESS="noreply@example.com"
MAIL_FROM_NAME="${APP_NAME}"
MAIL_ENCRYPTION=tls
MAIL_SCHEME=null

# ==========================================
# SANCTUM CONFIGURATION
# ==========================================

SANCTUM_STATEFUL_DOMAINS=staging.mswms.example.com

# ==========================================
# FILESYSTEM CONFIGURATION
# ==========================================

FILESYSTEM_DISK=s3

# ==========================================
# AWS S3 CONFIGURATION
# ==========================================

AWS_ACCESS_KEY_ID=your_aws_access_key
AWS_SECRET_ACCESS_KEY=your_aws_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=mswms-staging
AWS_USE_PATH_STYLE_ENDPOINT=false

# ==========================================
# SECURITY SETTINGS (OWASP Compliance)
# ==========================================

# Content Security Policy
CSP_MODE=auto
CSP_ENABLED=true
CSP_REPORT_ONLY=true

# Debug Mode Protection
SECURITY_BLOCK_DEBUG_ACCESS=true
SECURITY_LOG_EVENTS=true
SECURITY_LOG_LEVEL=warning

# Security Strict Mode
# OFF - Development
# SOFT - Staging
# STRICT - Production
SECURITY_STRICT_MODE=SOFT

# ==========================================
# SSRF PROTECTION
# ==========================================

SSRF_ALLOWLIST_ENABLED=true
SSRF_ALLOWED_DOMAINS=hooks.slack.com,*.example.com,api.github.com
SSRF_DNS_REBINDING_PROTECTION=true
SSRF_VALIDATE_REDIRECTS=true
SSRF_LOG_BLOCKED=true
SSRF_LOG_VALIDATED=true
SSRF_LOG_LEVEL=warning
SSRF_AUDIT_LOGGING=true
SSRF_TESTING_MODE=false

# ==========================================
# RATE LIMITING
# ==========================================

RATE_LIMIT_API=100
RATE_LIMIT_ADMIN=150
RATE_LIMIT_HEAVY=300
RATE_LIMIT_AUTH=50

# ==========================================
# WEBHOOK CONFIGURATION
# ==========================================

WEBHOOK_SECRET=your_webhook_secret_key
WEBHOOK_TIMEOUT=30
WEBHOOK_RETRY_ATTEMPTS=3
WEBHOOK_LOG_FAILURES=true

# ==========================================
# AUDIT LOGGING
# ==========================================

AUDIT_LOG_ENABLED=true
AUDIT_LOG_RETENTION_DAYS=180

# ==========================================
# EXPORT SETTINGS
# ==========================================

EXPORT_PATH=s3://mswms-staging-exports/exports
EXPORT_RETENTION_HOURS=48

# ==========================================
# VITE ASSETS
# ==========================================

VITE_APP_NAME="${APP_NAME}"
VITE_APP_URL="${APP_URL}"
```

### Production Environment (.env.production)

```ini
# ==========================================
# APPLICATION SETTINGS
# ==========================================

APP_NAME="MSWMS - Production"
APP_ENV=production
APP_KEY=base64:your_production_app_key_here
APP_DEBUG=false
APP_URL=https://api.mswms.example.com
APP_TIMEZONE=UTC

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=cache

# ==========================================
# LOGGING CONFIGURATION
# ==========================================

LOG_CHANNEL=errorlog
LOG_LEVEL=error
LOG_DEPRECATIONS_CHANNEL=null

# ==========================================
# DATABASE CONFIGURATION
# ==========================================

# PostgreSQL (Recommended for Production)
DB_CONNECTION=pgsql
DB_HOST=prod-db-primary.example.com
DB_PORT=5432
DB_DATABASE=mswms_production
DB_USERNAME=mswms_prod_user
DB_PASSWORD=your_production_database_password

# For read replicas (optional - configure in database.php)
# DB_READ_REPLICA_HOST=prod-db-replica.example.com

# MySQL Alternative
# DB_CONNECTION=mysql
# DB_HOST=prod-db.example.com
# DB_PORT=3306
# DB_DATABASE=mswms_production
# DB_USERNAME=mswms_prod_user
# DB_PASSWORD=your_production_database_password
# DB_CHARSET=utf8mb4
# DB_COLLATION=utf8mb4_unicode_ci

# ==========================================
# SESSION CONFIGURATION
# ==========================================

SESSION_DRIVER=redis
SESSION_CONNECTION=default
SESSION_LIFETIME=480
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=.mswms.example.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict
SESSION_HTTP_ONLY=true

# ==========================================
# CACHE CONFIGURATION
# ==========================================

CACHE_STORE=redis
CACHE_PREFIX=mswms_prod
CACHE_LOCK_CONNECTION=default

# ==========================================
# QUEUE CONFIGURATION
# ==========================================

QUEUE_CONNECTION=redis
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default
REDIS_QUEUE_RETRY_AFTER=90

# ==========================================
# REDIS CONFIGURATION
# ==========================================

REDIS_HOST=prod-redis-cluster.example.com
REDIS_PASSWORD=your_production_redis_password
REDIS_PORT=6379
REDIS_CLIENT=phpredis
REDIS_DB=0

# For Redis Cluster
# REDIS_CLUSTER[]=host1:6379
# REDIS_CLUSTER[]=host2:6379
# REDIS_CLUSTER[]=host3:6379

# ==========================================
# MAIL CONFIGURATION
# ==========================================

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=noreply@example.com
MAIL_PASSWORD=your_production_mail_password
MAIL_FROM_ADDRESS="noreply@example.com"
MAIL_FROM_NAME="${APP_NAME}"
MAIL_ENCRYPTION=tls
MAIL_SCHEME=null

# ==========================================
# SANCTUM CONFIGURATION
# ==========================================

SANCTUM_STATEFUL_DOMAINS=api.mswms.example.com,app.mswms.example.com

# ==========================================
# FILESYSTEM CONFIGURATION
# ==========================================

FILESYSTEM_DISK=s3

# ==========================================
# AWS S3 CONFIGURATION
# ==========================================

AWS_ACCESS_KEY_ID=your_production_aws_access_key
AWS_SECRET_ACCESS_KEY=your_production_aws_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=mswms-production
AWS_USE_PATH_STYLE_ENDPOINT=false

# ==========================================
# SECURITY SETTINGS (OWASP Compliance)
# ==========================================

# Content Security Policy
CSP_MODE=strict
CSP_ENABLED=true
CSP_REPORT_ONLY=false
# CSP_REPORT_URI=https://your-domain.com/csp-report

# Debug Mode Protection
SECURITY_BLOCK_DEBUG_ACCESS=true
SECURITY_LOG_EVENTS=true
SECURITY_LOG_LEVEL=error
SECURITY_TRUSTED_IPS=your_trusted_admin_ips

# Security Strict Mode
SECURITY_STRICT_MODE=STRICT

# ==========================================
# SSRF PROTECTION
# ==========================================

SSRF_ALLOWLIST_ENABLED=true
SSRF_ALLOWED_DOMAINS=hooks.slack.com,*.example.com,api.github.com
SSRF_DNS_REBINDING_PROTECTION=true
SSRF_VALIDATE_REDIRECTS=true
SSRF_LOG_BLOCKED=true
SSRF_LOG_VALIDATED=true
SSRF_LOG_LEVEL=error
SSRF_AUDIT_LOGGING=true
SSRF_TESTING_MODE=false

# ==========================================
# RATE LIMITING
# ==========================================

RATE_LIMIT_API=120
RATE_LIMIT_ADMIN=200
RATE_LIMIT_HEAVY=400
RATE_LIMIT_AUTH=60

# ==========================================
# WEBHOOK CONFIGURATION
# ==========================================

WEBHOOK_SECRET=your_production_webhook_secret_key
WEBHOOK_TIMEOUT=30
WEBHOOK_RETRY_ATTEMPTS=5
WEBHOOK_LOG_FAILURES=true

# ==========================================
# AUDIT LOGGING
# ==========================================

AUDIT_LOG_ENABLED=true
AUDIT_LOG_RETENTION_DAYS=365

# ==========================================
# EXPORT SETTINGS
# ==========================================

EXPORT_PATH=s3://mswms-production-exports/exports
EXPORT_RETENTION_HOURS=24

# ==========================================
# PERFORMANCE SETTINGS
# ==========================================

OPCACHE_ENABLED=true
CONFIG_CACHE_ENABLED=true
ROUTE_CACHE_ENABLED=true
VIEW_CACHE_ENABLED=true

# ==========================================
# VITE ASSETS
# ==========================================

VITE_APP_NAME="${APP_NAME}"
VITE_APP_URL="${APP_URL}"
```

## Configuration File Reference

### app.php
```php
// Key configurations from .env
'name' => env('APP_NAME', 'MSWMS'),
'env' => env('APP_ENV', 'production'),
'debug' => (bool) env('APP_DEBUG', false),
'url' => env('APP_URL', 'https://localhost'),
'timezone' => env('APP_TIMEZONE', 'UTC'),
'locale' => env('APP_LOCALE', 'en'),
'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
```

### database.php
```php
// PostgreSQL configuration
'pgsql' => [
    'driver' => 'pgsql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'laravel'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => env('DB_CHARSET', 'utf8'),
    'prefix' => '',
    'prefix_indexes' => true,
    'search_path' => 'public',
    'sslmode' => env('DB_SSLMODE', 'prefer'),
],
```

### cache.php
```php
'redis' => [
    'driver' => 'redis',
    'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
    'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
],
```

### session.php
```php
'driver' => env('SESSION_DRIVER', 'database'),
'lifetime' => env('SESSION_LIFETIME', 120),
'encrypt' => env('SESSION_ENCRYPT', false),
'connection' => env('SESSION_CONNECTION'),
'domain' => env('SESSION_DOMAIN'),
'secure' => env('SESSION_SECURE_COOKIE'),
```

### queue.php
```php
'redis' => [
    'driver' => 'redis',
    'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
    'queue' => env('REDIS_QUEUE', 'default'),
    'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
],
```

## Security Best Practices

### 1. APP_KEY Management

**Generate a new key:**
```bash
php artisan key:generate
```

**Key rotation procedure:**
1. Generate new key
2. Update .env file
3. Clear all caches
4. Monitor for issues
5. Keep old key for rollback

### 2. Database Credentials

- Use strong, unique passwords (minimum 32 characters)
- Use separate database users for each environment
- Grant minimum required privileges
- Rotate credentials quarterly
- Store credentials in a secrets manager

### 3. Redis Security

```bash
# Configure Redis authentication
redis-cli
> CONFIG SET requirepass "your_secure_password"
> ACL SETUSER default on >your_secure_password ~* +@all
```

### 4. File Permissions

```bash
# Secure the .env file
chmod 600 /var/www/mswms/.env
chown www-data:www-data /var/www/mswms/.env

# Application directory
chown -R www-data:www-data /var/www/mswms
find /var/www/mswms -type d -exec chmod 755 {} \;
find /var/www/mswms -type f -exec chmod 644 {} \;

# Storage and cache directories
chmod -R 775 /var/www/mswms/storage
chmod -R 775 /var/www/mswms/bootstrap/cache
```

### 5. Environment-Specific Security

**Staging:**
- `CSP_REPORT_ONLY=true` (test policies)
- `SECURITY_STRICT_MODE=SOFT` (warnings only)
- `LOG_LEVEL=info` (detailed logging)

**Production:**
- `CSP_REPORT_ONLY=false` (enforce policies)
- `SECURITY_STRICT_MODE=STRICT` (full enforcement)
- `LOG_LEVEL=error` (errors only)

## Configuration Caching

### Before Deployment
```bash
# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Generate optimized caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### After Deployment
```bash
# If configuration changes are needed
php artisan config:clear
# Make changes to .env
php artisan config:cache
```

**Important**: After running `config:cache`, the `.env` file is not loaded. All configuration must be in the cached file.

## Environment Validation

### Create a validation rule file:

**app/Providers/AppServiceProvider.php:**
```php
public function boot(): void
{
    $this->validateEnvironment();
}

private function validateEnvironment(): void
{
    $required = [
        'APP_KEY',
        'APP_ENV',
        'APP_URL',
        'DB_CONNECTION',
        'DB_HOST',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
        'REDIS_HOST',
        'REDIS_PASSWORD',
    ];

    foreach ($required as $var) {
        if (empty(env($var))) {
            throw new \RuntimeException("Required environment variable {$var} is not set");
        }
    }

    // Validate APP_ENV
    if (!in_array(env('APP_ENV'), ['staging', 'production'])) {
        throw new \RuntimeException('APP_ENV must be staging or production');
    }

    // Validate APP_DEBUG in production
    if (env('APP_ENV') === 'production' && env('APP_DEBUG', false)) {
        throw new \RuntimeException('APP_DEBUG must be false in production');
    }
}
```

## Deployment Scripts

### Staging Deployment Script

```bash
#!/bin/bash

# Staging Deployment Script
set -e

echo "🚀 Deploying to Staging..."

# Navigate to application directory
cd /var/www/mswms

# Pull latest code
git pull origin staging

# Install dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# Clear and cache configuration
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Run migrations
php artisan migrate --force

# Optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue workers
php artisan queue:restart

# Clear OPcache
php -r "opcache_reset();"

echo "✅ Staging deployment complete!"
```

### Production Deployment Script

```bash
#!/bin/bash

# Production Deployment Script
set -e

echo "🚀 Deploying to Production..."

# Navigate to application directory
cd /var/www/mswms

# Enable maintenance mode
php artisan down --secret="maintenance_secret_token"

# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# Clear and cache configuration
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Run migrations
php artisan migrate --force

# Optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue workers
php artisan queue:restart

# Disable maintenance mode
php artisan up

echo "✅ Production deployment complete!"
```

## Troubleshooting

### Common Issues

#### 1. Configuration Not Loading
```bash
# Clear configuration cache
php artisan config:clear

# Verify .env file exists and is readable
ls -la /var/www/mswms/.env
```

#### 2. Database Connection Failed
```bash
# Test database connection
php artisan tinker --execute="DB::connection()->getPdo();"

# Check environment variables
php artisan env
```

#### 3. Redis Connection Failed
```bash
# Test Redis connection
redis-cli -h your_redis_host -a your_redis_password ping

# Check PHP Redis extension
php -m | grep redis
```

#### 4. Session Issues
```bash
# Clear session data
php artisan session:flush

# Verify session table exists
php artisan migrate:status | grep sessions
```

## Environment Comparison Table

| Variable | Staging | Production |
|----------|---------|------------|
| APP_ENV | staging | production |
| APP_DEBUG | false | false |
| APP_URL | staging subdomain | production domain |
| DB_HOST | staging-db | production-db (HA) |
| DB_DATABASE | mswms_staging | mswms_production |
| CACHE_STORE | redis | redis (cluster) |
| QUEUE_CONNECTION | database | redis |
| SESSION_DRIVER | database | redis |
| LOG_LEVEL | info | error |
| CSP_REPORT_ONLY | true | false |
| SECURITY_STRICT_MODE | SOFT | STRICT |
| AUDIT_LOG_RETENTION_DAYS | 180 | 365 |

---

**Previous Section**: [← Server Requirements](01-server-requirements.md)  
**Next Section**: [Database Setup →](03-database-setup.md)
