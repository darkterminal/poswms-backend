# Docker Compose Setup for MSWMS

## Overview

This document provides comprehensive Docker Compose configurations for MSWMS, covering development, staging, and production environments using free open-source technologies.

## Docker Compose Fundamentals

### What is Docker Compose?

Docker Compose is a tool for defining and running multi-container Docker applications using YAML configuration files.

**Benefits for MSWMS:**
- Single command to start all services
- Service discovery between containers
- Shared networks and volumes
- Environment-specific configurations
- Easy scaling

### File Structure

```
poswms-backend/
├── docker-compose.yml          # Base configuration
├── docker-compose.dev.yml      # Development overrides
├── docker-compose.prod.yml     # Production overrides
├── docker-compose.coolify.yml  # Coolify-specific config
└── .env                        # Environment variables
```

## Development Configuration

### docker-compose.yml (Base)

```yaml
# MSWMS Backend - Base Docker Compose Configuration
# Version: 3.8
# Description: Base configuration for all environments

services:
  # ==========================================
  # Application Service (PHP-FPM)
  # ==========================================
  app:
    build:
      context: .
      dockerfile: Dockerfile
      target: development
    container_name: mswms-app
    restart: unless-stopped
    working_dir: /var/www/html
    volumes:
      # Mount source code for hot reload
      - ./:/var/www/html:cached
      # Don't overwrite vendor from container
      - /var/www/html/vendor
      # Named volume for storage
      - app_storage:/var/www/html/storage
    networks:
      - mswms-network
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    extra_hosts:
      - "host.docker.internal:host-gateway"

  # ==========================================
  # Web Server (Nginx)
  # ==========================================
  nginx:
    image: nginx:alpine
    container_name: mswms-nginx
    restart: unless-stopped
    ports:
      - "8080:80"
    volumes:
      - ./public:/var/www/html/public:cached
      - ./docker/nginx/nginx.conf:/etc/nginx/nginx.conf:ro
      - ./docker/nginx/conf.d:/etc/nginx/conf.d:ro
    networks:
      - mswms-network
    depends_on:
      - app

  # ==========================================
  # Database (PostgreSQL)
  # ==========================================
  postgres:
    image: postgres:15-alpine
    container_name: mswms-postgres
    restart: unless-stopped
    ports:
      - "5432:5432"
    environment:
      POSTGRES_DB: ${DB_DATABASE:-mswms_dev}
      POSTGRES_USER: ${DB_USERNAME:-mswms_user}
      POSTGRES_PASSWORD: ${DB_PASSWORD:-mswms_password}
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./docker/postgres/init.sql:/docker-entrypoint-initdb.d/init.sql:ro
    networks:
      - mswms-network
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME:-mswms_user} -d ${DB_DATABASE:-mswms_dev}"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s

  # ==========================================
  # Cache & Queue (Redis)
  # ==========================================
  redis:
    image: redis:7-alpine
    container_name: mswms-redis
    restart: unless-stopped
    ports:
      - "6379:6379"
    command: redis-server --appendonly yes
    volumes:
      - redis_data:/data
    networks:
      - mswms-network
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

  # ==========================================
  # Queue Worker
  # ==========================================
  worker:
    build:
      context: .
      dockerfile: Dockerfile
      target: development
    container_name: mswms-worker
    restart: unless-stopped
    command: php artisan queue:work --sleep=3 --tries=3 --timeout=60
    volumes:
      - ./:/var/www/html:cached
      - /var/www/html/vendor
      - app_storage:/var/www/html/storage
    networks:
      - mswms-network
    depends_on:
      - postgres
      - redis
    extra_hosts:
      - "host.docker.internal:host-gateway"

  # ==========================================
  # Scheduler (Cron)
  # ==========================================
  scheduler:
    build:
      context: .
      dockerfile: Dockerfile
      target: development
    container_name: mswms-scheduler
    restart: unless-stopped
    command: php artisan schedule:work
    volumes:
      - ./:/var/www/html:cached
      - /var/www/html/vendor
      - app_storage:/var/www/html/storage
    networks:
      - mswms-network
    depends_on:
      - postgres
      - redis

  # ==========================================
  # Frontend Build (Vite)
  # ==========================================
  vite:
    image: node:20-alpine
    container_name: mswms-vite
    restart: unless-stopped
    working_dir: /app
    ports:
      - "5173:5173"
    volumes:
      - ./:/app:cached
      - /app/node_modules
    command: npm run dev -- --host
    networks:
      - mswms-network

  # ==========================================
  # Database Admin (pgAdmin)
  # ==========================================
  pgadmin:
    image: dpage/pgadmin4:latest
    container_name: mswms-pgadmin
    restart: unless-stopped
    ports:
      - "8081:80"
    environment:
      PGADMIN_DEFAULT_EMAIL: ${PGADMIN_EMAIL:-admin@mswms.local}
      PGADMIN_DEFAULT_PASSWORD: ${PGADMIN_PASSWORD:-admin}
    volumes:
      - pgadmin_data:/var/lib/pgadmin
    networks:
      - mswms-network
    depends_on:
      - postgres

# ==========================================
# Networks
# ==========================================
networks:
  mswms-network:
    driver: bridge
    name: mswms-network

# ==========================================
# Volumes
# ==========================================
volumes:
  postgres_data:
    driver: local
    name: mswms_postgres_data
  redis_data:
    driver: local
    name: mswms_redis_data
  app_storage:
    driver: local
    name: mswms_app_storage
  pgadmin_data:
    driver: local
    name: mswms_pgadmin_data
```

### docker-compose.dev.yml (Development Overrides)

```yaml
# Development-specific overrides
# Usage: docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d

services:
  app:
    build:
      target: development
    environment:
      - APP_ENV=local
      - APP_DEBUG=true
      - APP_URL=http://localhost:8080
      - XDEBUG_MODE=debug,develop
      - XDEBUG_CONFIG=client_host=host.docker.internal
    volumes:
      - ./docker/php/php-development.ini:/usr/local/etc/php/conf.d/99-development.ini:ro

  nginx:
    volumes:
      - ./docker/nginx/dev.conf:/etc/nginx/conf.d/default.conf:ro

  postgres:
    ports:
      - "5432:5432"
    environment:
      - POSTGRES_DB=mswms_dev
      - POSTGRES_USER=mswms_user
      - POSTGRES_PASSWORD=mswms_password

  redis:
    ports:
      - "6379:6379"
    command: redis-server --appendonly yes --loglevel debug

  pgadmin:
    ports:
      - "8081:80"
```

## Staging Configuration

### docker-compose.staging.yml

```yaml
# Staging Configuration
# Usage: docker compose -f docker-compose.yml -f docker-compose.staging.yml up -d

services:
  app:
    build:
      target: production
    environment:
      - APP_ENV=staging
      - APP_DEBUG=false
      - APP_URL=https://staging.mswms.example.com
      - LOG_LEVEL=info
    deploy:
      resources:
        limits:
          cpus: '2.0'
          memory: 1G
        reservations:
          cpus: '0.5'
          memory: 512M

  nginx:
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/staging.conf:/etc/nginx/conf.d/default.conf:ro
      - ./ssl:/etc/nginx/ssl:ro
    deploy:
      resources:
        limits:
          cpus: '1.0'
          memory: 256M

  postgres:
    environment:
      - POSTGRES_DB=mswms_staging
      - POSTGRES_USER=mswms_staging_user
      - POSTGRES_PASSWORD=${DB_PASSWORD}
    deploy:
      resources:
        limits:
          cpus: '2.0'
          memory: 2G

  redis:
    command: redis-server --appendonly yes --maxmemory 512mb --maxmemory-policy allkeys-lru
    deploy:
      resources:
        limits:
          cpus: '1.0'
          memory: 512M

  worker:
    deploy:
      replicas: 2
      resources:
        limits:
          cpus: '1.0'
          memory: 512M

  # Remove development services
  vite:
    profiles:
      - disabled
  pgadmin:
    profiles:
      - disabled
```

## Production Configuration

### docker-compose.prod.yml

```yaml
# Production Configuration
# Usage: docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d

services:
  app:
    build:
      target: production
      args:
        - APP_VERSION=${APP_VERSION:-1.0.0}
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
      - APP_URL=https://api.mswms.example.com
      - LOG_LEVEL=error
      - OPcache_ENABLE=1
      - OPcache_VALIDATE_TIMESTAMPS=0
    deploy:
      resources:
        limits:
          cpus: '4.0'
          memory: 2G
        reservations:
          cpus: '2.0'
          memory: 1G
    healthcheck:
      test: ["CMD", "wget", "--no-verbose", "--tries=1", "--spider", "http://localhost:9000/fpm-status"]
      interval: 30s
      timeout: 5s
      retries: 3
      start_period: 40s

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/prod.conf:/etc/nginx/conf.d/default.conf:ro
      - ./ssl:/etc/nginx/ssl:ro
      - nginx_cache:/var/cache/nginx
    deploy:
      resources:
        limits:
          cpus: '2.0'
          memory: 512M
    healthcheck:
      test: ["CMD", "wget", "--no-verbose", "--tries=1", "--spider", "http://localhost:80/health"]
      interval: 30s
      timeout: 5s
      retries: 3

  postgres:
    image: postgres:15-alpine
    environment:
      - POSTGRES_DB=mswms_production
      - POSTGRES_USER=mswms_prod_user
      - POSTGRES_PASSWORD=${DB_PASSWORD}
      - POSTGRES_INITDB_ARGS=--encoding=UTF8 --lc-collate=C --lc-ctype=C
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./docker/postgres/postgresql.conf:/etc/postgresql/postgresql.conf:ro
    command: postgres -c config_file=/etc/postgresql/postgresql.conf
    deploy:
      resources:
        limits:
          cpus: '4.0'
          memory: 4G
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U mswms_prod_user -d mswms_production"]
      interval: 10s
      timeout: 5s
      retries: 5

  redis:
    command: redis-server --appendonly yes --maxmemory 2gb --maxmemory-policy allkeys-lru --requirepass ${REDIS_PASSWORD}
    volumes:
      - redis_data:/data
    deploy:
      resources:
        limits:
          cpus: '2.0'
          memory: 2G
    healthcheck:
      test: ["CMD", "redis-cli", "-a", "${REDIS_PASSWORD}", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

  worker:
    deploy:
      replicas: 4
      resources:
        limits:
          cpus: '1.0'
          memory: 512M
      restart_policy:
        condition: on-failure
        delay: 5s
        max_attempts: 3

  scheduler:
    deploy:
      resources:
        limits:
          cpus: '0.5'
          memory: 256M

  # Optional: Traefik for reverse proxy and SSL
  traefik:
    image: traefik:v2.10
    container_name: mswms-traefik
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - ./docker/traefik/traefik.yml:/etc/traefik/traefik.yml:ro
      - ./docker/traefik/acme:/etc/traefik/acme
    networks:
      - mswms-network
    deploy:
      resources:
        limits:
          cpus: '1.0'
          memory: 256M

# Production volumes
volumes:
  nginx_cache:
    driver: local
    name: mswms_nginx_cache
```

## Environment Files

### .env.example (Base)

```ini
# ==========================================
# Application
# ==========================================
APP_NAME=MSWMS
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8080

# ==========================================
# Database
# ==========================================
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=mswms_dev
DB_USERNAME=mswms_user
DB_PASSWORD=mswms_password

# ==========================================
# Redis
# ==========================================
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# ==========================================
# Mail
# ==========================================
MAIL_MAILER=log
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="${APP_NAME}"

# ==========================================
# pgAdmin
# ==========================================
PGADMIN_EMAIL=admin@mswms.local
PGADMIN_PASSWORD=admin

# ==========================================
# Docker
# ==========================================
DOCKER_APP_USER_ID=1000
DOCKER_APP_GROUP_ID=1000
```

### .env.production

```ini
# ==========================================
# Application
# ==========================================
APP_NAME=MSWMS
APP_ENV=production
APP_KEY=base64:your_production_app_key_here=
APP_DEBUG=false
APP_URL=https://api.mswms.example.com

# ==========================================
# Database
# ==========================================
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=mswms_production
DB_USERNAME=mswms_prod_user
DB_PASSWORD=your_secure_password_here

# ==========================================
# Redis
# ==========================================
REDIS_HOST=redis
REDIS_PASSWORD=your_secure_redis_password
REDIS_PORT=6379

# ==========================================
# Mail
# ==========================================
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=noreply@example.com
MAIL_PASSWORD=your_mail_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="${APP_NAME}"

# ==========================================
# Docker
# ==========================================
APP_VERSION=1.0.0
```

## Nginx Configuration

### docker/nginx/nginx.conf

```nginx
user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log warn;
pid /var/run/nginx.pid;

events {
    worker_connections 1024;
    use epoll;
    multi_accept on;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';

    access_log /var/log/nginx/access.log main;

    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    client_max_body_size 25M;

    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript application/xml;

    include /etc/nginx/conf.d/*.conf;
}
```

### docker/nginx/conf.d/default.conf

```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Deny access to sensitive files
    location ~ /\.ht {
        deny all;
    }

    location ~ /\.env {
        deny all;
    }

    # Handle static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|pdf|txt|woff|woff2|ttf|eot|svg)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Handle PHP requests
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM configuration
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
    }

    # Health check endpoint
    location /health {
        access_log off;
        return 200 "healthy\n";
        add_header Content-Type text/plain;
    }
}
```

## Usage Commands

### Development

```bash
# Start all services
docker compose up -d

# Start specific services
docker compose up -d app nginx postgres redis

# View logs
docker compose logs -f
docker compose logs -f app

# Stop all services
docker compose down

# Stop and remove volumes (WARNING: deletes data)
docker compose down -v

# Rebuild and restart
docker compose up -d --build

# Execute commands
docker compose exec app php artisan migrate
docker compose exec app php artisan test
docker compose exec app composer install

# Scale workers
docker compose up -d --scale worker=4
```

### Production

```bash
# Build production image
docker compose -f docker-compose.yml -f docker-compose.prod.yml build

# Start production stack
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d

# View production logs
docker compose -f docker-compose.yml -f docker-compose.prod.yml logs -f

# Scale workers for load
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --scale worker=8

# Update deployment
docker compose -f docker-compose.yml -f docker-compose.prod.yml pull
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

## Troubleshooting

### Common Issues

**Services Won't Start:**
```bash
# Check logs
docker compose logs app
docker compose logs postgres

# Check configuration
docker compose config

# Restart services
docker compose restart app postgres redis
```

**Database Connection Failed:**
```bash
# Verify database is healthy
docker compose ps

# Test connection from app
docker compose exec app ping postgres

# Check environment variables
docker compose exec app env | grep DB_
```

**Permission Issues:**
```bash
# Fix storage permissions
docker compose exec app chown -R www-data:www-data /var/www/html/storage

# Or rebuild with correct user
docker compose down
docker compose up -d --build
```

---

**Previous Section**: [← Dockerfile Creation](02-dockerfile-creation.md)  
**Next Section**: [Environment Configuration →](04-environment-configuration.md)
