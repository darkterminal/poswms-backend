# Coolify Deployment Guide for MSWMS

## Overview

Coolify is a free, open-source, self-hostable PaaS (Platform as a Service) alternative to Vercel, Netlify, and Heroku. This guide provides complete instructions for deploying MSWMS on Coolify.

**Why Coolify?**
- ✅ Free and open-source (Apache 2.0)
- ✅ Self-hosted (full control)
- ✅ One-click deployments from Git
- ✅ Built-in SSL with Let's Encrypt
- ✅ Database and Redis management
- ✅ Environment variable management
- ✅ Automatic deployments on push
- ✅ Multiple project support
- ✅ Team collaboration features

## Architecture

```
┌─────────────────────────────────────────────────────┐
│                    Your VPS Server                   │
│                   (Ubuntu 22.04+)                    │
│                                                      │
│  ┌─────────────────────────────────────────────┐    │
│  │              Coolify Platform                │    │
│  │         (Management Dashboard)               │    │
│  │         Port: 3000 (HTTPS: 443)              │    │
│  └─────────────────────────────────────────────┘    │
│                                                      │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐ │
│  │   MSWMS     │  │  PostgreSQL │  │    Redis    │ │
│  │   App       │  │  Database   │  │   Cache     │ │
│  │  Container  │  │  Container  │  │  Container  │ │
│  └─────────────┘  └─────────────┘  └─────────────┘ │
│                                                      │
│  ┌─────────────────────────────────────────────┐    │
│  │           Traefik (Reverse Proxy)            │    │
│  │         Auto SSL with Let's Encrypt          │    │
│  └─────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────┘
```

## Prerequisites

### Server Requirements

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| OS | Ubuntu 20.04 | Ubuntu 22.04 LTS |
| CPU | 2 cores | 4+ cores |
| RAM | 4 GB | 8+ GB |
| Storage | 40 GB SSD | 80+ GB SSD |
| Network | 1 Gbps | 1 Gbps |
| Domain | Required | Required |

### Required Tools

- SSH access to your VPS
- Domain name pointing to server IP
- Git repository (GitHub, GitLab, or Gitea)

## Step 1: Server Preparation

### Update Server

```bash
# SSH into your server
ssh user@your-server-ip

# Update system packages
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y curl git wget

# Set timezone
sudo timedatectl set-timezone UTC

# Configure firewall
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable
```

### Configure Domain

Point your domain to the server:

```
# DNS Records
Type    Name    Value
A       @       your-server-ip
A       api     your-server-ip
```

## Step 2: Install Coolify

### Automated Installation

```bash
# Download and run Coolify installer
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash

# The installer will:
# 1. Install Docker
# 2. Install Docker Compose
# 3. Set up Coolify containers
# 4. Configure Traefik reverse proxy
```

### Manual Installation (Alternative)

```bash
# Create Coolify directory
mkdir -p /data/coolify
cd /data/coolify

# Create docker-compose.yml
cat > docker-compose.yml << 'EOF'
version: '3.8'

services:
  coolify:
    image: ghcr.io/coollabsio/coolify:latest
    container_name: coolify
    restart: unless-stopped
    ports:
      - "3000:3000"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - coolify_data:/var/www/html/storage
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
      - APP_URL=http://localhost:3000
      - DB_CONNECTION=sqlite
      - QUEUE_CONNECTION=sync
    networks:
      - coolify-network

  traefik:
    image: traefik:v2.10
    container_name: coolify-traefik
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - ./traefik/acme:/etc/traefik/acme
    command:
      - "--api.insecure=true"
      - "--providers.docker=true"
      - "--providers.docker.exposedbydefault=false"
      - "--entrypoints.web.address=:80"
      - "--entrypoints.websecure.address=:443"
      - "--certificatesresolvers.letsencrypt.acme.tlschallenge=true"
      - "--certificatesresolvers.letsencrypt.acme.email=your-email@example.com"
      - "--certificatesresolvers.letsencrypt.acme.storage=/etc/traefik/acme/acme.json"
    networks:
      - coolify-network

volumes:
  coolify_data:

networks:
  coolify-network:
    driver: bridge
EOF

# Start Coolify
docker compose up -d
```

### Access Coolify Dashboard

1. Open browser: `http://your-server-ip:3000`
2. Create admin account
3. Verify email (check logs if needed)

```bash
# View Coolify logs
docker logs coolify -f

# Get admin credentials (if needed)
docker exec coolify php artisan coolify:admin-create
```

## Step 3: Configure Coolify

### Add Server (if using remote deployment)

For this guide, we're using the same server, so skip this step.

### Configure Project

1. **Login to Coolify Dashboard**
   - Navigate to `http://your-server-ip:3000`
   - Login with admin credentials

2. **Create New Project**
   - Click "Add New" → "Git Repository"
   - Select your Git provider (GitHub/GitLab)
   - Authorize Coolify access

3. **Select Repository**
   - Choose `poswms-backend` repository
   - Select branch: `main` (for production)

## Step 4: Configure MSWMS Application

### Add Database Service

1. **In Coolify Dashboard:**
   - Go to your project
   - Click "Add New" → "Database" → "PostgreSQL"
   - Name: `mswms-database`

2. **Database Configuration:**
   ```
   Database Name: mswms_production
   User: mswms_user
   Password: [Auto-generated - save this!]
   ```

3. **Save Credentials:**
   - Coolify will provide connection details
   - Save these for environment variables

### Add Redis Service

1. **In Coolify Dashboard:**
   - Click "Add New" → "Service" → "Redis"
   - Name: `mswms-redis`

2. **Redis Configuration:**
   ```
   Password: [Auto-generated - save this!]
   ```

### Configure Application Environment

1. **Go to Application Settings**
   - Navigate to your MSWMS application
   - Click "Environment Variables"

2. **Add Environment Variables:**

```ini
# Application
APP_NAME=MSWMS
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:GENERATE_THIS_KEY=
APP_URL=https://api.your-domain.com

# Database (from Coolify database service)
DB_CONNECTION=pgsql
DB_HOST=mswms-database
DB_PORT=5432
DB_DATABASE=mswms_production
DB_USERNAME=mswms_user
DB_PASSWORD=YOUR_DB_PASSWORD_FROM_COOLIFY

# Redis (from Coolify redis service)
REDIS_HOST=mswms-redis
REDIS_PASSWORD=YOUR_REDIS_PASSWORD_FROM_COOLIFY
REDIS_PORT=6379

# Cache & Session
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Mail
MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"

# Filesystem
FILESYSTEM_DISK=local

# Security
SECURITY_STRICT_MODE=STRICT
CSP_MODE=strict

# Build
NODE_VERSION=20
PHP_VERSION=8.3
COMPOSER_VERSION=2
```

3. **Generate APP_KEY:**
   ```bash
   # Generate locally
   php artisan key:generate --show
   
   # Copy output to Coolify environment
   APP_KEY=base64:your_key_here=
   ```

### Configure Build Settings

1. **In Coolify Dashboard:**
   - Go to "Build" settings
   - Configure build pack: "Dockerfile"

2. **Dockerfile Settings:**
   ```
   Dockerfile Location: /
   Build Context: /
   Target: production
   ```

3. **Build Arguments:**
   ```
   NODE_VERSION=20
   PHP_VERSION=8.3
   ```

### Configure Deployment Settings

1. **Deployment Configuration:**
   - Auto Deploy: Enabled
   - Branch: `main`
   - Build on Push: Yes

2. **Health Check:**
   ```
   Path: /api/health
   Port: 80
   Interval: 30s
   Timeout: 5s
   Retries: 3
   ```

3. **Resource Limits:**
   ```
   CPU Limit: 2.0
   Memory Limit: 1024MB
   ```

## Step 5: Add Dockerfile

### Create Dockerfile in Repository

```dockerfile
# Dockerfile
FROM php:8.3-fpm-alpine

LABEL maintainer="MSWMS Team"

# Install extensions
RUN apk add --no-cache \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    icu-dev libxml2-dev postgresql-dev zip unzip git \
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

# Copy composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application
COPY --chown=www-data:www-data . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 775 storage bootstrap/cache

EXPOSE 9000

USER www-data

CMD ["php-fpm"]
```

### Add docker-compose.coolify.yml

```yaml
# docker-compose.coolify.yml
# Coolify uses this for service orchestration

version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
    volumes:
      - app_storage:/var/www/html/storage
    depends_on:
      - postgres
      - redis

  postgres:
    image: postgres:15-alpine
    environment:
      POSTGRES_DB: mswms_production
      POSTGRES_USER: mswms_user
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - postgres_data:/var/lib/postgresql/data

  redis:
    image: redis:7-alpine
    command: redis-server --appendonly yes --requirepass ${REDIS_PASSWORD}
    volumes:
      - redis_data:/data

volumes:
  app_storage:
  postgres_data:
  redis_data:
```

## Step 6: Deploy Application

### Trigger First Deployment

1. **In Coolify Dashboard:**
   - Click "Deploy" button
   - Wait for build to complete (~5-10 minutes)

2. **Monitor Deployment:**
   - View real-time logs in dashboard
   - Check for any errors

3. **Post-Deployment:**
   - Run migrations from Coolify terminal:
     ```bash
     php artisan migrate --force
     ```
   - Cache configuration:
     ```bash
     php artisan config:cache
     php artisan route:cache
     php artisan view:cache
     ```

### Configure Domain

1. **In Coolify Dashboard:**
   - Go to "Domains"
   - Add domain: `api.your-domain.com`

2. **Configure SSL:**
   - Coolify automatically provisions SSL
   - Wait for Let's Encrypt certificate (~2 minutes)

3. **Verify HTTPS:**
   - Navigate to `https://api.your-domain.com`
   - Check SSL certificate

## Step 7: Post-Deployment Configuration

### Run Database Migrations

```bash
# In Coolify Terminal or via SSH
docker compose exec app php artisan migrate --force

# Seed if needed
php artisan db:seed --class=DatabaseSeeder
```

### Create Admin User

```bash
# Create super admin
php artisan tinker
>>> App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@example.com',
    'password' => Hash::make('secure_password'),
    'role' => 'super_admin',
]);
```

### Configure Queue Worker

1. **In Coolify:**
   - Add new service: "Worker"
   - Command: `php artisan queue:work --sleep=3 --tries=3`

2. **Or add to docker-compose:**
   ```yaml
   worker:
     build: .
     command: php artisan queue:work --sleep=3 --tries=3
     depends_on:
       - redis
       - postgres
   ```

### Configure Scheduler

1. **In Coolify:**
   - Add cron job in server settings
   - Command: `docker compose exec app php artisan schedule:work`

2. **Or add to crontab:**
   ```bash
   * * * * * cd /path/to/coolify && docker compose exec -T app php artisan schedule:work >> /dev/null 2>&1
   ```

## Step 8: Verify Deployment

### Health Check

```bash
# Test health endpoint
curl https://api.your-domain.com/api/health

# Expected response:
# {"status":"healthy","timestamp":"2024-01-01T00:00:00Z"}
```

### Test API Endpoints

```bash
# Test authentication
curl -X POST https://api.your-domain.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"secure_password"}'

# Test protected endpoint
curl https://api.your-domain.com/api/v1/health \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Check Logs

```bash
# In Coolify Dashboard
# Navigate to Logs section

# Or via SSH
docker compose logs -f app
docker compose logs -f postgres
docker compose logs -f redis
```

## Step 9: Enable Automatic Deployments

### Configure Git Webhooks

1. **In GitHub:**
   - Go to repository Settings
   - Webhooks → Add webhook
   - Payload URL: `https://your-coolify-url/api/webhook`
   - Content type: application/json
   - Secret: [From Coolify]

2. **In Coolify:**
   - Go to Application Settings
   - Webhooks → Copy webhook URL
   - Enable automatic deployments

### Test Automatic Deployment

```bash
# Make a change and push
git add .
git commit -m "test: trigger deployment"
git push origin main

# Check Coolify dashboard for automatic deployment
```

## Monitoring and Maintenance

### View Application Metrics

1. **In Coolify Dashboard:**
   - CPU usage
   - Memory usage
   - Request count
   - Response times

2. **Enable Laravel Telescope (Staging):**
   ```bash
   composer require laravel/telescope --dev
   php artisan telescope:install
   php artisan migrate
   ```

### Backup Configuration

1. **Database Backups:**
   - Coolify provides automatic backups
   - Configure backup schedule in database settings

2. **Manual Backup:**
   ```bash
   # Export database
   docker compose exec postgres pg_dump -U mswms_user mswms_production > backup.sql
   
   # Download backup
   docker compose cp postgres:/backup.sql ./backup.sql
   ```

### Update Application

```bash
# Pull latest changes
git pull origin main

# Coolify will auto-deploy if enabled

# Or manually trigger deployment in dashboard
```

## Troubleshooting

### Common Issues

**Build Fails:**
```bash
# Check build logs in Coolify dashboard
# Verify Dockerfile syntax
# Test build locally: docker build -t mswms .
```

**Database Connection Failed:**
```bash
# Verify environment variables
# Check database service is running
# Test connection: docker compose exec app php artisan tinker
# >>> DB::connection()->getPdo();
```

**SSL Certificate Issues:**
```bash
# Wait for Let's Encrypt (can take 5-10 minutes)
# Verify domain DNS is correct
# Check Traefik logs: docker logs coolify-traefik
```

**Application Errors:**
```bash
# Check application logs
docker compose logs app

# Clear cache
docker compose exec app php artisan cache:clear
docker compose exec app php artisan config:clear
```

## Cost Breakdown

### Monthly Costs (FOSS Stack)

| Component | Cost | Provider |
|-----------|------|----------|
| VPS | $10-40 | Hetzner/DigitalOcean |
| Domain | $1-2 | Namecheap |
| Coolify | Free | Self-hosted |
| SSL | Free | Let's Encrypt |
| **Total** | **$11-42/month** | |

### Comparison with Managed Services

| Solution | Monthly Cost |
|----------|--------------|
| Heroku (similar specs) | $50-250 |
| Laravel Forge + DO | $20-100 |
| AWS ECS + RDS | $100-500 |
| **Coolify + VPS** | **$11-42** |

---

**Previous Section**: [← Database Containerization](05-database-containerization.md)  
**Next Section**: [CI/CD with Docker →](11-ci-cd-docker.md)
