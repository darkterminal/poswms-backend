# Coolify Deployment - Complete Instructions

## Overview

This document provides step-by-step Coolify deployment instructions specifically for MSWMS, including all configuration files and troubleshooting.

## Quick Start Checklist

- [ ] Server provisioned (Ubuntu 22.04+, 4GB RAM, 2 CPU)
- [ ] Domain configured (A record pointing to server)
- [ ] SSH access configured
- [ ] Git repository ready (GitHub/GitLab)
- [ ] Coolify installed
- [ ] MSWMS deployed and running

## Complete Deployment Files

### docker-compose.coolify.yml

```yaml
# docker-compose.coolify.yml
# Complete Coolify configuration for MSWMS

version: '3.8'

services:
  # ==========================================
  # Laravel Application
  # ==========================================
  app:
    image: mswms-backend:latest
    build:
      context: .
      dockerfile: Dockerfile
      target: production
      args:
        - NODE_VERSION=20
        - PHP_VERSION=8.3
    container_name: mswms-app
    restart: unless-stopped
    environment:
      - APP_NAME=${APP_NAME:-MSWMS}
      - APP_ENV=${APP_ENV:-production}
      - APP_DEBUG=${APP_DEBUG:-false}
      - APP_KEY=${APP_KEY}
      - APP_URL=${APP_URL}
      - DB_CONNECTION=pgsql
      - DB_HOST=${DB_HOST}
      - DB_PORT=${DB_PORT:-5432}
      - DB_DATABASE=${DB_DATABASE}
      - DB_USERNAME=${DB_USERNAME}
      - DB_PASSWORD=${DB_PASSWORD}
      - REDIS_HOST=${REDIS_HOST}
      - REDIS_PASSWORD=${REDIS_PASSWORD}
      - REDIS_PORT=${REDIS_PORT:-6379}
      - CACHE_STORE=redis
      - SESSION_DRIVER=redis
      - QUEUE_CONNECTION=redis
      - LOG_CHANNEL=errorlog
      - LOG_LEVEL=${LOG_LEVEL:-error}
    volumes:
      - app_storage:/var/www/html/storage
      - app_bootstrap:/var/www/html/bootstrap/cache
    networks:
      - mswms-network
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    healthcheck:
      test: ["CMD", "wget", "--no-verbose", "--tries=1", "--spider", "http://localhost:9000/fpm-status"]
      interval: 30s
      timeout: 5s
      retries: 3
      start_period: 40s

  # ==========================================
  # Nginx Reverse Proxy
  # ==========================================
  nginx:
    image: nginx:alpine
    container_name: mswms-nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/coolify.conf:/etc/nginx/conf.d/default.conf:ro
      - nginx_cache:/var/cache/nginx
      - ./public:/var/www/html/public:ro
    networks:
      - mswms-network
    depends_on:
      - app
    healthcheck:
      test: ["CMD", "wget", "--no-verbose", "--tries=1", "--spider", "http://localhost:80/health"]
      interval: 30s
      timeout: 5s
      retries: 3

  # ==========================================
  # Queue Worker
  # ==========================================
  worker:
    image: mswms-backend:latest
    build:
      context: .
      dockerfile: Dockerfile
      target: production
    container_name: mswms-worker
    restart: unless-stopped
    command: php artisan queue:work redis --sleep=3 --tries=3 --timeout=60 --max-time=3600
    environment:
      - APP_NAME=${APP_NAME:-MSWMS}
      - APP_ENV=${APP_ENV:-production}
      - APP_DEBUG=false
      - APP_KEY=${APP_KEY}
      - DB_CONNECTION=pgsql
      - DB_HOST=${DB_HOST}
      - DB_PORT=${DB_PORT:-5432}
      - DB_DATABASE=${DB_DATABASE}
      - DB_USERNAME=${DB_USERNAME}
      - DB_PASSWORD=${DB_PASSWORD}
      - REDIS_HOST=${REDIS_HOST}
      - REDIS_PASSWORD=${REDIS_PASSWORD}
      - REDIS_PORT=${REDIS_PORT:-6379}
      - QUEUE_CONNECTION=redis
    volumes:
      - app_storage:/var/www/html/storage
    networks:
      - mswms-network
    depends_on:
      - postgres
      - redis
    deploy:
      replicas: 2

  # ==========================================
  # Laravel Scheduler
  # ==========================================
  scheduler:
    image: mswms-backend:latest
    build:
      context: .
      dockerfile: Dockerfile
      target: production
    container_name: mswms-scheduler
    restart: unless-stopped
    command: php artisan schedule:work
    environment:
      - APP_NAME=${APP_NAME:-MSWMS}
      - APP_ENV=${APP_ENV:-production}
      - APP_DEBUG=false
      - APP_KEY=${APP_KEY}
      - DB_CONNECTION=pgsql
      - DB_HOST=${DB_HOST}
      - DB_PORT=${DB_PORT:-5432}
      - DB_DATABASE=${DB_DATABASE}
      - DB_USERNAME=${DB_USERNAME}
      - DB_PASSWORD=${DB_PASSWORD}
      - REDIS_HOST=${REDIS_HOST}
      - REDIS_PASSWORD=${REDIS_PASSWORD}
      - REDIS_PORT=${REDIS_PORT:-6379}
    volumes:
      - app_storage:/var/www/html/storage
    networks:
      - mswms-network
    depends_on:
      - postgres
      - redis

  # ==========================================
  # PostgreSQL Database
  # ==========================================
  postgres:
    image: postgres:15-alpine
    container_name: mswms-postgres
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${DB_DATABASE}
      POSTGRES_USER: ${DB_USERNAME}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./docker/postgres/init.sql:/docker-entrypoint-initdb.d/init.sql:ro
    networks:
      - mswms-network
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME} -d ${DB_DATABASE}"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s

  # ==========================================
  # Redis Cache
  # ==========================================
  redis:
    image: redis:7-alpine
    container_name: mswms-redis
    restart: unless-stopped
    command: redis-server --appendonly yes --requirepass ${REDIS_PASSWORD} --maxmemory 512mb --maxmemory-policy allkeys-lru
    volumes:
      - redis_data:/data
    networks:
      - mswms-network
    healthcheck:
      test: ["CMD", "redis-cli", "-a", "${REDIS_PASSWORD}", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

volumes:
  app_storage:
    driver: local
    name: mswms_app_storage
  app_bootstrap:
    driver: local
    name: mswms_app_bootstrap
  nginx_cache:
    driver: local
    name: mswms_nginx_cache
  postgres_data:
    driver: local
    name: mswms_postgres_data
  redis_data:
    driver: local
    name: mswms_redis_data

networks:
  mswms-network:
    driver: bridge
    name: mswms-network
```

### Dockerfile (Coolify Optimized)

```dockerfile
# Dockerfile
# MSWMS Backend - Coolify Optimized

# ==========================================
# Stage 1: Frontend Build
# ==========================================
FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci && npm cache clean --force

COPY . .
RUN npm run build

# ==========================================
# Stage 2: Composer Dependencies
# ==========================================
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ==========================================
# Stage 3: Production
# ==========================================
FROM php:8.3-fpm-alpine

LABEL maintainer="MSWMS Team <team@mswms.example.com>"
LABEL version="1.0"

# Install extensions
RUN apk add --no-cache \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    icu-dev libxml2-dev postgresql-dev zip unzip git curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd pdo_pgsql pgsql mbstring xml curl bcmath intl opcache \
    && docker-php-ext-enable opcache

# Install Redis
RUN apk add --no-cache ${PHPIZE_DEPS} redis-dev \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del ${PHPIZE_DEPS}

# Create user
RUN addgroup -g 1000 -S www-data && \
    adduser -u 1000 -S www-data -G www-data

WORKDIR /var/www/html

# Copy dependencies
COPY --from=vendor /app/vendor /var/www/html/vendor

# Copy application
COPY --chown=www-data:www-data . .

# Copy built assets
COPY --from=frontend --chown=www-data:www-data /app/public/build /var/www/html/public/build

# Set permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# PHP Configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://localhost:9000/fpm-status || exit 1

EXPOSE 9000

USER www-data

CMD ["php-fpm"]
```

### Nginx Configuration for Coolify

### docker/nginx/coolify.conf

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Max upload size
    client_max_body_size 25M;

    # Deny sensitive files
    location ~ /\.ht { deny all; }
    location ~ /\.env { deny all; }
    location ~ /composer\.(json|lock)$ { deny all; }
    location ~ /phpunit\.xml$ { deny all; }
    location ~ /\.git { deny all; }

    # Static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|pdf|txt|woff|woff2|ttf|eot|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_param HTTP_PROXY "";
        fastcgi_connect_timeout 60s;
        fastcgi_send_timeout 60s;
        fastcgi_read_timeout 60s;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_hide_header X-Powered-By;
    }

    # Health check
    location /health {
        access_log off;
        return 200 "healthy\n";
        add_header Content-Type text/plain;
    }

    # Laravel health endpoint
    location /api/health {
        try_files $uri $uri/ /index.php?$query_string;
        access_log off;
    }
}
```

### .env.coolify Template

```ini
# ==========================================
# MSWMS Coolify Environment
# Copy to .env and fill in values
# ==========================================

# Application
APP_NAME=MSWMS
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:GENERATE_WITH_php_artisan_key:generate=
APP_URL=https://api.your-domain.com

# Database (Coolify will provide these)
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=mswms_production
DB_USERNAME=mswms_user
DB_PASSWORD=CHANGE_THIS_SECURE_PASSWORD

# Redis (Coolify will provide these)
REDIS_HOST=redis
REDIS_PASSWORD=CHANGE_THIS_SECURE_PASSWORD
REDIS_PORT=6379

# Mail
MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"

# Security
LOG_LEVEL=error
SECURITY_STRICT_MODE=STRICT
```

## Step-by-Step Deployment

### Step 1: Prepare Repository

```bash
# Clone repository
git clone https://github.com/your-org/poswms-backend.git
cd poswms-backend

# Add Docker files
cp docker-compose.coolify.yml docker-compose.yml
cp .env.coolify .env

# Generate APP_KEY
php artisan key:generate

# Update .env with your values
nano .env

# Commit changes
git add .
git commit -m "feat: add coolify deployment configuration"
git push origin main
```

### Step 2: Install Coolify

```bash
# SSH to server
ssh user@your-server-ip

# Install Coolify
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash

# Wait for installation (~5 minutes)
# Access dashboard at http://your-server-ip:3000
```

### Step 3: Configure in Coolify Dashboard

1. **Login to Coolify**
   - Navigate to `http://your-server-ip:3000`
   - Create admin account

2. **Add Project**
   - Click "Add New" → "Git Repository"
   - Connect GitHub/GitLab
   - Select `poswms-backend`

3. **Configure Environment**
   - Add all variables from `.env.coolify`
   - Use Coolify-provided database credentials

4. **Deploy**
   - Click "Deploy"
   - Wait for build (~5-10 minutes)

### Step 4: Post-Deployment

```bash
# Run migrations (via Coolify terminal or SSH)
docker compose exec app php artisan migrate --force

# Create admin user
docker compose exec app php artisan tinker
>>> App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@example.com',
    'password' => Hash::make('secure_password'),
    'role' => 'super_admin',
]);

# Cache configuration
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
```

### Step 5: Configure Domain

1. **DNS Configuration:**
   ```
   Type: A
   Name: api
   Value: your-server-ip
   TTL: Auto
   ```

2. **In Coolify:**
   - Go to Application → Domains
   - Add: `api.your-domain.com`
   - SSL will be auto-provisioned

### Step 6: Verify Deployment

```bash
# Test health endpoint
curl https://api.your-domain.com/api/health

# Expected:
# {"status":"healthy",...}

# Test login
curl -X POST https://api.your-domain.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"secure_password"}'
```

## Troubleshooting

### Build Fails

```bash
# Check build logs in Coolify dashboard
# Test build locally
docker build -t mswms-test .

# Common fixes:
# - Check Dockerfile syntax
# - Verify package versions
# - Increase build timeout in Coolify
```

### Database Connection Failed

```bash
# Verify credentials
docker compose exec app env | grep DB_

# Test connection
docker compose exec app php artisan tinker
>>> DB::connection()->getPdo();

# Check database is healthy
docker compose ps postgres
```

### SSL Not Working

```bash
# Wait for Let's Encrypt (5-10 minutes)
# Check Traefik logs
docker logs coolify-traefik

# Verify DNS
dig api.your-domain.com
```

### Queue Not Processing

```bash
# Check worker status
docker compose ps worker

# Restart workers
docker compose restart worker

# Check queue
docker compose exec app php artisan queue:monitor redis
```

## Monitoring

### View Logs

```bash
# All services
docker compose logs -f

# Specific service
docker compose logs -f app
docker compose logs -f worker

# Last 100 lines
docker compose logs --tail=100 app
```

### Resource Usage

```bash
# Docker stats
docker stats

# Specific container
docker stats mswms-app
```

### Database Size

```bash
docker compose exec postgres psql -U mswms_user -d mswms_production \
  -c "SELECT pg_size_pretty(pg_database_size('mswms_production'));"
```

## Backup and Restore

### Manual Backup

```bash
# Database backup
docker compose exec postgres pg_dump -U mswms_user mswms_production > backup.sql

# Download backup
docker compose cp postgres:/backup.sql ./backup.sql

# Storage backup
tar -czf storage_backup.tar.gz ./storage
```

### Automated Backups (Coolify)

1. **In Coolify Dashboard:**
   - Database → Settings → Backups
   - Enable automatic backups
   - Configure schedule (daily recommended)
   - Set retention period

## Updates and Maintenance

### Update Application

```bash
# Pull latest changes
git pull origin main

# Coolify auto-deploys if enabled
# Or manually trigger in dashboard

# Run migrations if needed
docker compose exec app php artisan migrate --force
```

### Update Coolify

```bash
# Update Coolify itself
docker compose pull
docker compose up -d
```

### Clear Cache

```bash
docker compose exec app php artisan cache:clear
docker compose exec app php artisan config:clear
docker compose exec app php artisan route:clear
docker compose exec app php artisan view:clear
```

---

**Previous Section**: [← Coolify Setup](09-coolify-setup.md)  
**Next Section**: [CI/CD with Docker →](11-ci-cd-docker.md)
