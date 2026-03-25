# Dockerfile Creation for MSWMS

## Overview

This document provides comprehensive instructions for creating optimized Dockerfiles for the MSWMS Laravel application. We'll cover development and production configurations using multi-stage builds.

## Dockerfile Structure

### File Location

Create the Dockerfile in the project root:
```
poswms-backend/
├── Dockerfile
├── docker-compose.yml
├── .dockerignore
└── ...
```

## Base Dockerfile (Production)

### Complete Production Dockerfile

```dockerfile
# MSWMS Backend - Production Dockerfile
# PHP Version: 8.3
# Base Image: Alpine (smallest footprint)

# ==========================================
# Stage 1: Frontend Build
# ==========================================
FROM node:20-alpine AS frontend

WORKDIR /app

# Copy package files
COPY package.json package-lock.json* ./

# Install dependencies
RUN npm ci --only=production && \
    npm cache clean --force

# Copy frontend source
COPY . .

# Build assets
RUN npm run build

# ==========================================
# Stage 2: Composer Dependencies
# ==========================================
FROM composer:2 AS vendor

WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Install production dependencies only
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ==========================================
# Stage 3: Production Image
# ==========================================
FROM php:8.3-fpm-alpine

# Labels for image metadata
LABEL maintainer="MSWMS Team <team@mswms.example.com>"
LABEL version="1.0"
LABEL description="MSWMS Backend API - Production"

# Install system dependencies
RUN apk add --no-cache \
    # Required extensions
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    libxml2-dev \
    postgresql-dev \
    zip \
    unzip \
    git \
    curl \
    # Security updates
    && apk upgrade --no-cache

# Install PHP extensions
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo_pgsql \
        pgsql \
        mbstring \
        xml \
        curl \
        bcmath \
        intl \
        opcache \
    && docker-php-ext-enable opcache

# Install Redis extension
RUN apk add --no-cache ${PHPIZE_DEPS} redis-dev \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del ${PHPIZE_DEPS}

# Create system user
RUN addgroup -g 1000 -S www-data && \
    adduser -u 1000 -S www-data -G www-data

# Set working directory
WORKDIR /var/www/html

# Copy composer dependencies from vendor stage
COPY --from=vendor /app/vendor /var/www/html/vendor

# Copy application files
COPY --chown=www-data:www-data . .

# Copy built assets from frontend stage
COPY --from=frontend --chown=www-data:www-data /app/public/build /var/www/html/public/build

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Copy PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose PHP-FPM port
EXPOSE 9000

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://localhost:9000/fpm-status || exit 1

# Set user
USER www-data

# Entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Default command
CMD ["php-fpm"]
```

## Development Dockerfile

### Complete Development Dockerfile

```dockerfile
# MSWMS Backend - Development Dockerfile
# PHP Version: 8.3
# Features: Xdebug, verbose logging, hot reload

# ==========================================
# Stage 1: Frontend Development
# ==========================================
FROM node:20-alpine AS frontend

WORKDIR /app

# Copy package files
COPY package.json package-lock.json* ./

# Install all dependencies (including dev)
RUN npm ci && npm cache clean --force

# Copy source files
COPY . .

# Expose Vite dev server port
EXPOSE 5173

# Default command for frontend
CMD ["npm", "run", "dev", "--", "--host"]

# ==========================================
# Stage 2: Development PHP
# ==========================================
FROM php:8.3-fpm

# Labels
LABEL maintainer="MSWMS Team <team@mswms.example.com>"
LABEL version="1.0-dev"
LABEL description="MSWMS Backend API - Development"

# Install system dependencies
RUN apt-get update && apt-get install -y \
    # PHP extensions
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libicu-dev \
    libxml2-dev \
    libpq-dev \
    libzip-dev \
    # Development tools
    git \
    curl \
    zip \
    unzip \
    vim \
    nano \
    # PostgreSQL client
    postgresql-client \
    # Cleanup
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo_pgsql \
        pgsql \
        mbstring \
        xml \
        curl \
        bcmath \
        intl \
        zip \
        opcache

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Xdebug for debugging
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=debug,develop" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Create system user
RUN useradd -u 1000 -m -s /bin/bash www-data && \
    usermod -a -G www-data www-data

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install all dependencies (including dev)
RUN composer install --no-interaction

# Copy application files
COPY --chown=www-data:www-data . .

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Copy development PHP configuration
COPY docker/php/php-development.ini /usr/local/etc/php/conf.d/99-development.ini
COPY docker/php/www-development.conf /usr/local/etc/php-fpm.d/www.conf

# Expose ports
EXPOSE 9000 5173

# Set user
USER www-data

# Default command
CMD ["php-fpm"]
```

## Supporting Files

### .dockerignore

```dockerignore
# Git
.git
.gitignore
.gitattributes

# Environment
.env
.env.*
!.env.example

# IDE
.idea/
.vscode/
*.swp
*.swo
*~

# Dependencies
vendor/
node_modules/

# Build artifacts
public/build/
public/hot

# Logs
storage/logs/*.log
storage/logs/laravel.log

# Cache
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
bootstrap/cache/*

# Tests
tests/Browser
phpunit.xml

# Documentation
docs/
*.md
!README.md

# Docker
Dockerfile
docker-compose*.yml
.dockerignore

# OS
.DS_Store
Thumbs.db

# Coverage
coverage/
.phpunit.result.cache
```

### docker/php/php.ini (Production)

```ini
; Production PHP Configuration

; Memory
memory_limit = 512M

; Error Handling
display_errors = Off
log_errors = On
error_log = /var/www/html/storage/logs/php-error.log
error_reporting = E_ALL
html_errors = Off

; Execution Time
max_execution_time = 60
max_input_time = 60

; Uploads
upload_max_filesize = 20M
post_max_size = 25M
max_file_uploads = 20

; Session
session.gc_maxlifetime = 28800
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1

; OPcache
opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 100000
opcache.revalidate_freq = 60
opcache.fast_shutdown = 1
opcache.validate_timestamps = 0

; Security
expose_php = Off
disable_functions = exec,passthru,shell_exec,system,proc_open,popen
```

### docker/php/php-development.ini (Development)

```ini
; Development PHP Configuration

; Memory
memory_limit = 1024M

; Error Handling
display_errors = On
log_errors = On
error_log = /var/www/html/storage/logs/php-error.log
error_reporting = E_ALL
html_errors = On

; Execution Time
max_execution_time = 300
max_input_time = 300

; Uploads
upload_max_filesize = 50M
post_max_size = 55M

; Session
session.gc_maxlifetime = 28800
session.cookie_httponly = 0
session.cookie_secure = 0

; OPcache (disabled for development)
opcache.enable = 0
opcache.validate_timestamps = 1

; Xdebug
xdebug.mode = debug,develop
xdebug.start_with_request = yes
xdebug.client_host = host.docker.internal
xdebug.client_port = 9003
xdebug.log = /var/www/html/storage/logs/xdebug.log
```

### docker/php/www.conf (Production)

```ini
[www]
; Process Manager
pm = dynamic
pm.max_children = 25
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500

; User/Group
user = www-data
group = www-data

; Listen
listen = 9000
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

; Security
security.limit_extensions = .php

; PHP Settings
php_admin_value[sendmail_path] = /usr/sbin/sendmail -t -i -f www@localhost
php_flag[display_errors] = off
php_admin_value[error_log] = /var/www/html/storage/logs/php-fpm-error.log
php_admin_flag[log_errors] = on
php_admin_value[memory_limit] = 512M
```

### docker/entrypoint.sh

```bash
#!/bin/sh
set -e

# MSWMS Docker Entrypoint Script

echo "🚀 Starting MSWMS Backend..."

# Wait for database to be ready
if [ ! -z "$DB_HOST" ]; then
    echo "⏳ Waiting for database at $DB_HOST..."
    while ! nc -z "$DB_HOST" "${DB_PORT:-5432}" 2>/dev/null; do
        sleep 1
    done
    echo "✅ Database is ready"
fi

# Wait for Redis to be ready
if [ ! -z "$REDIS_HOST" ]; then
    echo "⏳ Waiting for Redis at $REDIS_HOST..."
    while ! nc -z "$REDIS_HOST" "${REDIS_PORT:-6379}" 2>/dev/null; do
        sleep 1
    done
    echo "✅ Redis is ready"
fi

# Run migrations if enabled
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "🔄 Running database migrations..."
    php artisan migrate --force --no-interaction
fi

# Cache configuration if not in development
if [ "$APP_ENV" != "local" ]; then
    echo "⚡ Caching configuration..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Set permissions
echo "🔐 Setting permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "✅ MSWMS Backend ready!"

# Execute main command
exec "$@"
```

## Building the Dockerfile

### Build Production Image

```bash
# Build production image
docker build -t mswms-backend:production --target production .

# Build with build arguments
docker build \
    --build-arg APP_VERSION=1.0.0 \
    --build-arg BUILD_DATE=$(date -u +'%Y-%m-%dT%H:%M:%SZ') \
    -t mswms-backend:production .

# Build without cache
docker build --no-cache -t mswms-backend:production .
```

### Build Development Image

```bash
# Build development image
docker build -t mswms-backend:development -f Dockerfile.dev .

# Or use target
docker build --target development -t mswms-backend:development .
```

### Multi-Platform Build

```bash
# Install QEMU for multi-platform
docker run --rm --privileged multiarch/qemu-user-static --reset -p yes

# Build for multiple platforms
docker buildx create --use --name mswms-builder
docker buildx build \
    --platform linux/amd64,linux/arm64 \
    -t mswms-backend:latest \
    -t mswms-backend:1.0.0 \
    --push \
    .
```

## Testing the Dockerfile

### Test Production Image

```bash
# Run container
docker run -d \
    --name mswms-test \
    -e APP_KEY=base64:test123456789012345678901234567890= \
    -e DB_CONNECTION=sqlite \
    mswms-backend:production

# Check logs
docker logs -f mswms-test

# Execute commands
docker exec mswms-test php artisan --version
docker exec mswms-test php artisan about

# Stop and remove
docker stop mswms-test
docker rm mswms-test
```

### Test with Docker Compose

```bash
# Create test compose file
cat > docker-compose.test.yml << 'EOF'
version: '3.8'
services:
  app:
    build:
      context: .
      target: production
    environment:
      - APP_KEY=base64:test123456789012345678901234567890=
      - DB_CONNECTION=sqlite
      - DB_DATABASE=/var/www/html/database/database.sqlite
    volumes:
      - test_data:/var/www/html/storage
  
volumes:
  test_data:
EOF

# Run tests
docker compose -f docker-compose.test.yml up -d
docker compose -f docker-compose.test.yml exec app php artisan test
docker compose -f docker-compose.test.yml down
```

## Image Optimization

### Reduce Image Size

**Before Optimization:**
```
REPOSITORY          TAG       SIZE
mswms-backend       latest    850MB
```

**After Optimization:**
```
REPOSITORY          TAG       SIZE
mswms-backend       latest    180MB
```

**Optimization Techniques:**

1. **Use Alpine base:**
```dockerfile
FROM php:8.3-fpm-alpine  # ~50MB vs ~150MB
```

2. **Multi-stage builds:**
```dockerfile
FROM composer AS vendor
# Install dependencies

FROM php:8.3-fpm-alpine
COPY --from=vendor /app/vendor /var/www/html/vendor
```

3. **Combine RUN commands:**
```dockerfile
RUN apt-get update && apt-get install -y \
    package1 \
    package2 \
    && rm -rf /var/lib/apt/lists/*
```

4. **Use .dockerignore:**
```
vendor/
node_modules/
.git/
```

### Security Scanning

```bash
# Install Trivy
docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
    aquasec/trivy image mswms-backend:production

# Or use Docker Scout
docker scout cves mswms-backend:production
```

## Troubleshooting

### Common Issues

**Build Fails - Missing Extensions:**
```dockerfile
# Add required extensions
RUN docker-php-ext-install pdo_pgsql pgsql mbstring xml
```

**Permission Denied:**
```dockerfile
# Set correct ownership
RUN chown -R www-data:www-data /var/www/html
```

**Xdebug Not Working:**
```ini
; Ensure host.docker.internal is resolvable
; Add to /etc/hosts on Linux
127.0.0.1 host.docker.internal
```

---

**Previous Section**: [← Docker Fundamentals](01-docker-fundamentals.md)  
**Next Section**: [Docker Compose Setup →](03-docker-compose-setup.md)
