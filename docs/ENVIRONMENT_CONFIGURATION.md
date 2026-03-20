# Environment Configuration Guide

**Project:** Multi-Store & Warehouse Management System (MSWMS)  
**Framework:** Laravel 13.x (PHP 8.3)  
**Last Updated:** March 20, 2026

---

## Overview

This guide documents the environment-specific configurations for the MSWMS application. Three environment configurations are provided:

1. **Development** (`.env.development`) - Local development with SQLite
2. **Staging** (`.env.staging`) - Pre-production testing with PostgreSQL
3. **Production** (`.env.production`) - Live production environment

---

## Quick Start

### Development Setup

```bash
# Copy development configuration
cp .env.development .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Start development server
php artisan serve
```

### Staging Deployment

```bash
# Copy staging configuration
cp .env.staging .env

# Set environment variables (via server or CI/CD)
export APP_KEY=your_staging_app_key
export DB_PASSWORD=your_staging_db_password
# ... other secrets

# Run migrations
php artisan migrate --force

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Production Deployment

```bash
# Copy production configuration
cp .env.production .env

# Set environment variables (via server or CI/CD)
export APP_KEY=your_production_app_key
export DB_PASSWORD=your_production_db_password
# ... other secrets

# Run migrations
php artisan migrate --force

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Configuration Reference

### Application Settings

| Variable | Development | Staging | Production | Description |
|----------|-------------|---------|------------|-------------|
| `APP_NAME` | MSWMS - Development | MSWMS - Staging | MSWMS | Application name |
| `APP_ENV` | local | staging | production | Environment name |
| `APP_DEBUG` | true | false | false | Debug mode (never true in prod) |
| `APP_URL` | http://localhost:8000 | https://staging.mswms.example.com | https://app.mswms.example.com | Base URL |
| `APP_TIMEZONE` | UTC | UTC | UTC | Default timezone |

### Database Configuration

| Variable | Development | Staging | Production | Description |
|----------|-------------|---------|------------|-------------|
| `DB_CONNECTION` | sqlite | pgsql | pgsql | Database driver |
| `DB_HOST` | - | staging-db.example.com | production-db.example.com | Database host |
| `DB_PORT` | - | 5432 | 5432 | Database port |
| `DB_DATABASE` | database/database.sqlite | mswms_staging | mswms_production | Database name |
| `DB_USERNAME` | - | mswms_staging_user | mswms_production_user | Database username |
| `DB_PASSWORD` | - | [SECRET] | [SECRET] | Database password |

**Note:** SQLite is used for development for simplicity. Production and staging use PostgreSQL for better performance and features.

### Session Configuration

| Variable | Development | Staging | Production | Description |
|----------|-------------|---------|------------|-------------|
| `SESSION_DRIVER` | file | database | database | Session storage driver |
| `SESSION_LIFETIME` | 120 | 480 | 480 | Session lifetime in minutes |
| `SESSION_DOMAIN` | null | .mswms.example.com | .mswms.example.com | Cookie domain |

### Cache Configuration

| Variable | Development | Staging | Production | Description |
|----------|-------------|---------|------------|-------------|
| `CACHE_STORE` | file | redis | redis | Cache driver |
| `CACHE_PREFIX` | mswms_dev | mswms_staging | mswms_prod | Cache key prefix |

**Note:** Redis is recommended for staging and production to improve performance and enable shared caching across servers.

### Queue Configuration

| Variable | Development | Staging | Production | Description |
|----------|-------------|---------|------------|-------------|
| `QUEUE_CONNECTION` | sync | database | database | Queue driver |

**Note:** Sync queue is used in development for simplicity (jobs run immediately). Database queue is used in staging/production for async job processing.

### Mail Configuration

| Variable | Development | Staging | Production | Description |
|----------|-------------|---------|------------|-------------|
| `MAIL_MAILER` | log | smtp | smtp | Mail driver |
| `MAIL_HOST` | 127.0.0.1 | smtp.example.com | smtp.example.com | SMTP host |
| `MAIL_PORT` | 2525 | 587 | 587 | SMTP port |
| `MAIL_FROM_ADDRESS` | noreply@example.com | noreply@example.com | noreply@example.com | From address |

**Note:** In development, emails are logged instead of sent. Configure SMTP credentials for staging and production.

### Rate Limiting

| Variable | Development | Staging | Production | Description |
|----------|-------------|---------|------------|-------------|
| `RATE_LIMIT_API` | 120 | 100 | 60 | API requests per minute |
| `RATE_LIMIT_ADMIN` | 200 | 150 | 100 | Admin requests per minute |
| `RATE_LIMIT_HEAVY` | 500 | 300 | 200 | Heavy operation limit |
| `RATE_LIMIT_AUTH` | 60 | 50 | 30 | Authentication attempts |

### Security Settings

| Variable | Development | Staging | Production | Description |
|----------|-------------|---------|------------|-------------|
| `WEBHOOK_SECRET` | dev_webhook_secret_key | [SECRET] | [SECRET] | Webhook signature secret |
| `WEBHOOK_TIMEOUT` | 30 | 30 | 15 | Webhook timeout (seconds) |
| `WEBHOOK_RETRY_ATTEMPTS` | 3 | 3 | 5 | Webhook retry attempts |
| `AUDIT_LOG_ENABLED` | true | true | true | Enable audit logging |
| `AUDIT_LOG_RETENTION_DAYS` | 90 | 180 | 365 | Audit log retention |

### File Storage

| Variable | Development | Staging | Production | Description |
|----------|-------------|---------|------------|-------------|
| `FILESYSTEM_DISK` | local | s3 | s3 | Default storage disk |
| `AWS_BUCKET` | - | mswms-staging | mswms-production | S3 bucket name |
| `AWS_DEFAULT_REGION` | us-east-1 | us-east-1 | us-east-1 | AWS region |

---

## Required Environment Variables

### Development (Minimum)

```bash
APP_KEY=base64:...  # Run: php artisan key:generate
```

### Staging (Required)

```bash
# Application
APP_KEY=base64:...

# Database
DB_PASSWORD=[your_staging_db_password]

# Mail
MAIL_USERNAME=[your_smtp_username]
MAIL_PASSWORD=[your_smtp_password]

# AWS S3 (if using)
AWS_ACCESS_KEY_ID=[your_aws_key]
AWS_SECRET_ACCESS_KEY=[your_aws_secret]

# Redis (if using)
REDIS_PASSWORD=[your_redis_password]

# Webhooks
WEBHOOK_SECRET=[your_webhook_secret]
```

### Production (Required)

```bash
# Application
APP_KEY=base64:...

# Database
DB_PASSWORD=[your_production_db_password]

# Mail
MAIL_USERNAME=[your_smtp_username]
MAIL_PASSWORD=[your_smtp_password]

# AWS S3 (if using)
AWS_ACCESS_KEY_ID=[your_aws_key]
AWS_SECRET_ACCESS_KEY=[your_aws_secret]

# Redis (if using)
REDIS_PASSWORD=[your_redis_password]

# Webhooks
WEBHOOK_SECRET=[your_webhook_secret]
```

---

## Security Best Practices

### 1. Never Commit Secrets

- Do not commit `.env`, `.env.staging`, or `.env.production` with real credentials
- Use environment variables or secret management tools in CI/CD
- Keep `.env*` files in `.gitignore` (already configured)

### 2. Application Key

Generate a unique application key for each environment:

```bash
php artisan key:generate
```

### 3. Database Credentials

- Use different credentials for each environment
- Apply principle of least privilege
- Rotate credentials regularly

### 4. Debug Mode

**Never** enable `APP_DEBUG=true` in production. It can expose sensitive information.

### 5. HTTPS in Production

Ensure `APP_URL` uses `https://` in staging and production environments.

---

## Configuration Caching

In staging and production, cache configuration for better performance:

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Clear all caches (when making changes)
php artisan optimize:clear
```

---

## Troubleshooting

### Configuration Not Loading

```bash
# Clear config cache
php artisan config:clear

# Clear all caches
php artisan optimize:clear
```

### Database Connection Issues

```bash
# Test database connection
php artisan tinker --execute "DB::connection()->getPdo();"

# Check current configuration
php artisan config:show database
```

### Mail Not Sending

```bash
# Check mail configuration
php artisan config:show mail

# Test mail configuration
php artisan tinker --execute "Mail::to('test@example.com')->send(new \Illuminate\Mail\Mailable);"
```

### Permission Issues

```bash
# Fix storage permissions
chmod -R 775 storage/
chown -R www-data:www-data storage/
```

---

## Environment-Specific Notes

### Development

- SQLite database stored in `database/database.sqlite`
- File-based sessions and cache
- Mail logged to `storage/logs/laravel.log`
- Debug mode enabled for detailed errors
- Relaxed rate limits for testing

### Staging

- PostgreSQL database (mirrors production)
- Redis cache for better performance
- Database queue for async jobs
- SMTP mail enabled
- Debug mode disabled
- Production-like rate limits

### Production

- PostgreSQL database with proper indexing
- Redis cache with persistence
- Database queue with workers
- SMTP mail with encryption
- Strict rate limiting
- Extended audit log retention (365 days)
- Webhook signature verification enabled

---

## Migration Between Environments

### Export/Import Database

```bash
# Export from staging
pg_dump -h staging-db.example.com -U mswms_staging_user mswms_staging > staging_dump.sql

# Import to production
psql -h production-db.example.com -U mswms_production_user mswms_production < staging_dump.sql
```

### Sync Configuration

When deploying from staging to production:

1. Ensure all environment variables are set in production
2. Run `php artisan config:clear` before deployment
3. Run `php artisan config:cache` after deployment
4. Run `php artisan migrate --force` for any new migrations
5. Test critical functionality

---

## Monitoring & Logging

### Log Channels

| Environment | Channel | Level | Location |
|-------------|---------|-------|----------|
| Development | stack (single) | debug | storage/logs/laravel.log |
| Staging | errorlog | info | Server error log |
| Production | errorlog | warning | Server error log |

### Monitoring Recommendations

- **Development:** Use Laravel Telescope for debugging
- **Staging:** Monitor error logs and performance metrics
- **Production:** Set up alerting for errors and performance issues

---

## Support

For questions or issues related to environment configuration:

1. Check this documentation
2. Review Laravel documentation: https://laravel.com/docs/configuration
3. Contact the development team

---

**Document Maintainer:** Development Team  
**Review Cycle:** Update when adding new environment variables or changing configuration structure
