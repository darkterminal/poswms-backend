# Environment Configuration for Docker

## Overview

This document covers environment variable configuration for Docker deployments of MSWMS across all environments.

## Environment File Structure

### Directory Structure

```
poswms-backend/
├── .env.example              # Template for local development
├── .env.docker.example       # Template for Docker deployments
├── .env.staging             # Staging environment (gitignored)
├── .env.production          # Production environment (gitignored)
└── docker/
    └── env/
        ├── .env.dev         # Docker development
        ├── .env.staging     # Docker staging
        └── .env.prod        # Docker production
```

## Base Environment Template

### .env.docker.example

```ini
# ==========================================
# MSWMS Docker Environment Configuration
# Copy this file to .env and customize
# ==========================================

# ==========================================
# Application Settings
# ==========================================
APP_NAME="MSWMS"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8080
APP_TIMEZONE=UTC
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

# ==========================================
# Docker Settings
# ==========================================
DOCKER_APP_USER_ID=1000
DOCKER_APP_GROUP_ID=1000
RUN_MIGRATIONS=true

# ==========================================
# Database Configuration
# ==========================================
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=mswms_dev
DB_USERNAME=mswms_user
DB_PASSWORD=mswms_password
DB_CHARSET=utf8mb4

# ==========================================
# Redis Configuration
# ==========================================
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_CLIENT=phpredis

# ==========================================
# Cache Configuration
# ==========================================
CACHE_STORE=redis
CACHE_PREFIX=mswms_docker

# ==========================================
# Session Configuration
# ==========================================
SESSION_DRIVER=redis
SESSION_CONNECTION=default
SESSION_LIFETIME=120
SESSION_ENCRYPT=false

# ==========================================
# Queue Configuration
# ==========================================
QUEUE_CONNECTION=redis
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default

# ==========================================
# Mail Configuration
# ==========================================
MAIL_MAILER=log
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="noreply@example.com"
MAIL_FROM_NAME="${APP_NAME}"
MAIL_ENCRYPTION=null

# ==========================================
# Filesystem Configuration
# ==========================================
FILESYSTEM_DISK=local
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

# ==========================================
# Sanctum Configuration
# ==========================================
SANCTUM_STATEFUL_DOMAINS=localhost,localhost:8080,127.0.0.1

# ==========================================
# Security Settings
# ==========================================
CSP_MODE=auto
CSP_ENABLED=true
CSP_REPORT_ONLY=false
SECURITY_BLOCK_DEBUG_ACCESS=false
SECURITY_STRICT_MODE=OFF

# ==========================================
# Rate Limiting
# ==========================================
RATE_LIMIT_API=120
RATE_LIMIT_ADMIN=200
RATE_LIMIT_HEAVY=500
RATE_LIMIT_AUTH=60

# ==========================================
# pgAdmin (Optional)
# ==========================================
PGADMIN_EMAIL=admin@mswms.local
PGADMIN_PASSWORD=admin
```

## Production Environment

### .env.production

```ini
# ==========================================
# MSWMS Production Environment
# DO NOT COMMIT - Contains secrets
# ==========================================

# ==========================================
# Application Settings
# ==========================================
APP_NAME="MSWMS"
APP_ENV=production
APP_KEY=base64:GENERATE_WITH_php_artisan_key:generate=
APP_DEBUG=false
APP_URL=https://api.mswms.example.com
APP_TIMEZONE=UTC

# ==========================================
# Docker Settings
# ==========================================
DOCKER_APP_USER_ID=1000
DOCKER_APP_GROUP_ID=1000
RUN_MIGRATIONS=true
APP_VERSION=1.0.0

# ==========================================
# Database Configuration (Production)
# ==========================================
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=mswms_production
DB_USERNAME=mswms_prod_user
DB_PASSWORD=GENERATE_SECURE_PASSWORD_32_CHARS
DB_CHARSET=utf8

# ==========================================
# Redis Configuration (Production)
# ==========================================
REDIS_HOST=redis
REDIS_PASSWORD=GENERATE_SECURE_REDIS_PASSWORD
REDIS_PORT=6379

# ==========================================
# Cache & Session (Production)
# ==========================================
CACHE_STORE=redis
CACHE_PREFIX=mswms_prod
SESSION_DRIVER=redis
SESSION_LIFETIME=480
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true

# ==========================================
# Queue Configuration (Production)
# ==========================================
QUEUE_CONNECTION=redis
REDIS_QUEUE_CONNECTION=default

# ==========================================
# Mail Configuration (Production)
# ==========================================
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=noreply@example.com
MAIL_PASSWORD=MAIL_PASSWORD
MAIL_FROM_ADDRESS="noreply@example.com"
MAIL_ENCRYPTION=tls

# ==========================================
# Filesystem (S3 for Production)
# ==========================================
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=YOUR_AWS_KEY
AWS_SECRET_ACCESS_KEY=YOUR_AWS_SECRET
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=mswms-production
AWS_USE_PATH_STYLE_ENDPOINT=false

# ==========================================
# Sanctum (Production)
# ==========================================
SANCTUM_STATEFUL_DOMAINS=api.mswms.example.com,app.mswms.example.com

# ==========================================
# Security (Production)
# ==========================================
CSP_MODE=strict
CSP_ENABLED=true
CSP_REPORT_ONLY=false
SECURITY_BLOCK_DEBUG_ACCESS=true
SECURITY_STRICT_MODE=STRICT
SECURITY_LOG_LEVEL=error

# ==========================================
# Rate Limiting (Production)
# ==========================================
RATE_LIMIT_API=100
RATE_LIMIT_ADMIN=150
RATE_LIMIT_HEAVY=300
RATE_LIMIT_AUTH=50

# ==========================================
# Monitoring
# ==========================================
LOG_CHANNEL=errorlog
LOG_LEVEL=error
AUDIT_LOG_ENABLED=true
```

## Coolify Environment

### Coolify Environment Variables

When deploying to Coolify, add these environment variables in the Coolify dashboard:

```ini
# ==========================================
# Required Variables
# ==========================================
APP_NAME=MSWMS
APP_ENV=production
APP_KEY=base64:your_app_key_here=
APP_DEBUG=false
APP_URL=https://your-domain.com

# ==========================================
# Database (Coolify provides these)
# ==========================================
DB_CONNECTION=pgsql
DB_HOST=your-coolify-db-host
DB_PORT=5432
DB_DATABASE=mswms_production
DB_USERNAME=mswms_user
DB_PASSWORD=your_db_password

# ==========================================
# Redis (Coolify provides these)
# ==========================================
REDIS_HOST=your-coolify-redis-host
REDIS_PASSWORD=your_redis_password
REDIS_PORT=6379

# ==========================================
# Queue & Cache
# ==========================================
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis

# ==========================================
# Build Variables
# ==========================================
NODE_VERSION=20
PHP_VERSION=8.3
COMPOSER_VERSION=2
```

## Generating Secure Keys

### Generate APP_KEY

```bash
# Using Docker
docker compose exec app php artisan key:generate --show

# Or using OpenSSL
openssl rand -base64 32

# Copy output to .env
APP_KEY=base64:your_generated_key=
```

### Generate Database Password

```bash
# Generate 32-character secure password
openssl rand -base64 32 | tr -dc 'a-zA-Z0-9' | head -c 32

# Or using pwgen
pwgen -s 32 1
```

### Generate Redis Password

```bash
# Generate secure Redis password
openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24
```

## Environment-Specific Overrides

### Development Overrides

```ini
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080
DB_DATABASE=mswms_dev
DB_PASSWORD=mswms_password
REDIS_PASSWORD=null
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
MAIL_MAILER=log
LOG_LEVEL=debug
SECURITY_STRICT_MODE=OFF
```

### Staging Overrides

```ini
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://staging.mswms.example.com
DB_DATABASE=mswms_staging
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
MAIL_MAILER=smtp
LOG_LEVEL=info
SECURITY_STRICT_MODE=SOFT
CSP_REPORT_ONLY=true
```

### Production Overrides

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.mswms.example.com
DB_DATABASE=mswms_production
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
MAIL_MAILER=smtp
LOG_LEVEL=error
SECURITY_STRICT_MODE=STRICT
CSP_REPORT_ONLY=false
```

## Using Environment Variables in Docker

### In Docker Compose

```yaml
services:
  app:
    environment:
      - APP_ENV=${APP_ENV:-local}
      - DB_HOST=${DB_HOST:-postgres}
      - DB_PASSWORD=${DB_PASSWORD}
    env_file:
      - .env
```

### In Dockerfile

```dockerfile
ARG APP_VERSION=1.0.0
ENV APP_ENV=production

# Use in RUN commands
RUN echo "App Version: $APP_VERSION"
```

## Validation Script

### validate-env.sh

```bash
#!/bin/bash

echo "🔍 Validating environment configuration..."

ERRORS=0

# Check required variables
REQUIRED_VARS=(
    "APP_NAME"
    "APP_ENV"
    "APP_KEY"
    "DB_CONNECTION"
    "DB_HOST"
    "DB_DATABASE"
    "DB_USERNAME"
    "DB_PASSWORD"
)

for var in "${REQUIRED_VARS[@]}"; do
    if [ -z "${!var}" ]; then
        echo "❌ Missing required variable: $var"
        ERRORS=$((ERRORS + 1))
    else
        echo "✅ $var is set"
    fi
done

# Check APP_KEY format
if [[ ! "$APP_KEY" =~ ^base64: ]]; then
    echo "❌ APP_KEY must start with 'base64:'"
    ERRORS=$((ERRORS + 1))
fi

# Check APP_DEBUG in production
if [ "$APP_ENV" = "production" ] && [ "$APP_DEBUG" = "true" ]; then
    echo "❌ APP_DEBUG must be false in production"
    ERRORS=$((ERRORS + 1))
fi

if [ $ERRORS -eq 0 ]; then
    echo "✅ All environment variables are valid!"
    exit 0
else
    echo "❌ Found $ERRORS error(s)"
    exit 1
fi
```

## Troubleshooting

### Common Issues

**APP_KEY Not Set:**
```bash
# Generate and set
docker compose exec app php artisan key:generate
```

**Database Connection Failed:**
```bash
# Verify environment
docker compose exec app env | grep DB_

# Test connection
docker compose exec app php artisan tinker --execute="DB::connection()->getPdo();"
```

**Cache Not Working:**
```bash
# Clear cache
docker compose exec app php artisan cache:clear
docker compose exec app php artisan config:clear

# Verify Redis connection
docker compose exec app php artisan tinker --execute="Redis::ping();"
```

---

**Previous Section**: [← Docker Compose Setup](03-docker-compose-setup.md)  
**Next Section**: [Database Containerization →](05-database-containerization.md)
