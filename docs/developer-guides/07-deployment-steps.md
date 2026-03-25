# Deployment Steps

## Overview

This document provides step-by-step instructions for deploying MSWMS to staging and production environments. Follow these procedures carefully to ensure a smooth and successful deployment.

## Pre-Deployment Checklist

### Requirements Verification

- [ ] Server meets minimum requirements (see Section 01)
- [ ] All required software installed (PHP 8.3, Nginx/Apache, Redis, Database)
- [ ] SSL certificates obtained and configured
- [ ] Domain DNS configured to point to server
- [ ] Firewall rules configured
- [ ] SSH access configured with keys
- [ ] Backup system in place
- [ ] Monitoring tools installed

### Code Preparation

- [ ] Code reviewed and approved
- [ ] All tests passing
- [ ] Database migrations tested
- [ ] Environment variables prepared
- [ ] Deployment script tested in staging
- [ ] Rollback plan documented

## Staging Deployment

### Step 1: Server Preparation

```bash
# SSH into staging server
ssh user@staging.mswms.example.com

# Update system packages
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y php8.3 php8.3-cli php8.3-fpm php8.3-mbstring php8.3-xml \
    php8.3-curl php8.3-gd php8.3-pgsql php8.3-zip php8.3-bcmath php8.3-redis \
    php8.3-intl nginx git curl unzip redis postgresql postgresql-contrib
```

### Step 2: Create Application Directory

```bash
# Create application directory
sudo mkdir -p /var/www/mswms
cd /var/www/mswms

# Set ownership
sudo chown -R $USER:$USER /var/www/mswms
```

### Step 3: Deploy Code

**Option A: Git Clone (Recommended)**
```bash
# Clone repository
git clone git@github.com:your-org/poswms-backend.git /var/www/mswms
cd /var/www/mswms

# Checkout staging branch
git checkout staging
```

**Option B: SCP/SFTP Upload**
```bash
# From local machine
tar -czf mswms-release.tar.gz --exclude='.git' --exclude='vendor' --exclude='node_modules' .
scp mswms-release.tar.gz user@staging.mswms.example.com:/var/www/mswms

# On server
cd /var/www/mswms
tar -xzf mswms-release.tar.gz
```

**Option C: CI/CD Pipeline**
```bash
# Automated deployment via GitHub Actions, GitLab CI, etc.
# See Section 13 for CI/CD configuration
```

### Step 4: Install Dependencies

```bash
# Install Composer dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# Install NPM dependencies (if frontend exists)
npm install

# Build frontend assets
npm run build
```

### Step 5: Configure Environment

```bash
# Copy environment file
cp .env.staging .env

# Generate application key
php artisan key:generate

# Edit environment file
nano .env

# Update these values:
# APP_NAME="MSWMS - Staging"
# APP_ENV=staging
# APP_URL=https://staging.mswms.example.com
# DB_HOST=staging-db.example.com
# DB_DATABASE=mswms_staging
# DB_USERNAME=mswms_staging_user
# DB_PASSWORD=your_secure_password
# REDIS_HOST=staging-redis.example.com
# REDIS_PASSWORD=your_redis_password
```

### Step 6: Database Setup

```bash
# Test database connection
php artisan tinker --execute="DB::connection()->getPdo();"

# Run migrations
php artisan migrate --force

# Seed database (optional)
php artisan db:seed

# Verify migrations
php artisan migrate:status
```

### Step 7: Storage and Cache Setup

```bash
# Create storage directories
mkdir -p storage/app/public
mkdir -p storage/logs
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views

# Set permissions
chmod -R 775 storage
chmod -R 775 bootstrap/cache
chown -R www-data:www-data storage
chown -R www-data:www-data bootstrap/cache

# Link storage
php artisan storage:link

# Clear and cache configuration
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Generate optimized caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 8: Web Server Configuration

**Nginx:**
```bash
# Create site configuration
sudo nano /etc/nginx/sites-available/mswms-staging

# Copy configuration from Section 05 (Staging Environment)

# Enable site
sudo ln -s /etc/nginx/sites-available/mswms-staging /etc/nginx/sites-enabled/

# Remove default site
sudo rm /etc/nginx/sites-enabled/default

# Test configuration
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx
```

**Apache:**
```bash
# Create virtual host
sudo nano /etc/apache2/sites-available/mswms-staging.conf

# Copy configuration from Section 05

# Enable site
sudo a2ensite mswms-staging.conf

# Disable default site
sudo a2dissite 000-default.conf

# Test configuration
sudo apache2ctl configtest

# Restart Apache
sudo systemctl restart apache2
```

### Step 9: PHP-FPM Configuration

```bash
# Configure PHP-FPM pool
sudo nano /etc/php/8.3/fpm/pool.d/www.conf

# Copy configuration from Section 05

# Restart PHP-FPM
sudo systemctl restart php8.3-fpm
```

### Step 10: Queue Worker Setup

**Option A: Manual Start (Testing)**
```bash
# Start queue worker
php artisan queue:work database --sleep=3 --tries=3 --timeout=60
```

**Option B: Supervisor (Recommended)**
```bash
# Install Supervisor
sudo apt install -y supervisor

# Create supervisor configuration
sudo nano /etc/supervisor/conf.d/mswms-worker.conf

# Add configuration (see Section 08)

# Update supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start mswms-worker:*

# Check status
sudo supervisorctl status
```

### Step 11: SSL Configuration

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtain SSL certificate
sudo certbot --nginx -d staging.mswms.example.com

# Test auto-renewal
sudo certbot renew --dry-run
```

### Step 12: Verify Deployment

```bash
# Check application status
curl -I https://staging.mswms.example.com

# Test health endpoint
curl https://staging.mswms.example.com/api/health

# Test API endpoint
curl -X GET https://staging.mswms.example.com/api/v1/health

# Check logs
tail -f storage/logs/laravel.log

# Check Nginx logs
sudo tail -f /var/log/nginx/mswms_staging_error.log
```

## Production Deployment

### Pre-Deployment Requirements

- [ ] Staging deployment successful
- [ ] All tests passing in staging
- [ ] Performance benchmarks acceptable
- [ ] Security scan completed
- [ ] Backup verified
- [ ] Rollback plan tested
- [ ] Team notified of deployment
- [ ] Maintenance window scheduled (if needed)

### Step 1: Enable Maintenance Mode

```bash
# SSH into production server
ssh user@api.mswms.example.com

# Navigate to application
cd /var/www/mswms

# Enable maintenance mode with secret
php artisan down --secret="maintenance_secret_token" --retry=60

# Maintenance page will be shown to all except /maintenance_secret_token
```

### Step 2: Create Backup

```bash
# Backup database
pg_dump -h localhost -U mswms_prod_user mswms_production | gzip > /var/backups/mswms/db_backup_$(date +%Y%m%d_%H%M%S).sql.gz

# Backup files
tar -czf /var/backups/mswms/files_backup_$(date +%Y%m%d_%H%M%S).tar.gz \
    --exclude='vendor' \
    --exclude='node_modules' \
    /var/www/mswms

# Verify backup
ls -lh /var/backups/mswms/
```

### Step 3: Deploy Code

```bash
# Navigate to application
cd /var/www/mswms

# Pull latest code
git pull origin main

# Or checkout specific tag
git checkout v1.0.0
```

### Step 4: Install Dependencies

```bash
# Install Composer dependencies (production optimized)
composer install --no-dev --optimize-autoloader --no-interaction --classmap-authoritative

# Install NPM dependencies
npm install --production

# Build frontend assets
npm run build
```

### Step 5: Update Environment

```bash
# Backup current .env
cp .env .env.backup

# Update .env if needed
nano .env

# Verify changes
diff .env .env.backup
```

### Step 6: Run Migrations

```bash
# Run migrations
php artisan migrate --force

# Check migration status
php artisan migrate:status

# Seed if needed (production data seeding)
php artisan db:seed --class=ProductionSeeder
```

### Step 7: Clear and Cache

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

# Optimize autoloader
composer dump-autoload --classmap-authoritative
```

### Step 8: Restart Services

```bash
# Restart queue workers
php artisan queue:restart

# Restart PHP-FPM
sudo systemctl restart php8.3-fpm

# Restart Supervisor workers
sudo supervisorctl restart mswms-worker:*

# Wait for workers to restart
sleep 5

# Check worker status
sudo supervisorctl status
```

### Step 9: Disable Maintenance Mode

```bash
# Verify deployment
curl -I https://api.mswms.example.com/api/health

# If successful, disable maintenance mode
php artisan up

# Verify application is live
curl https://api.mswms.example.com/api/health
```

### Step 10: Post-Deployment Verification

```bash
# Test critical endpoints
curl -X GET https://api.mswms.example.com/api/v1/health
curl -X GET https://api.mswms.example.com/api/v1/auth/me

# Check application logs
tail -100 storage/logs/laravel.log

# Check web server logs
sudo tail -100 /var/log/nginx/mswms_production_error.log

# Check queue status
php artisan queue:monitor database

# Check cache status
php artisan tinker --execute="Cache::put('test_key', 'test_value', 60); echo Cache::get('test_key');"
```

## Automated Deployment Script

### Staging Deployment Script

```bash
#!/bin/bash
# deploy-staging.sh

set -e

echo "🚀 Starting Staging Deployment..."

# Configuration
APP_DIR="/var/www/mswms"
BRANCH="staging"
ENV_FILE=".env.staging"

# Navigate to application
cd $APP_DIR

# Pull latest code
echo "📦 Pulling latest code..."
git pull origin $BRANCH

# Install dependencies
echo "📦 Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction
npm install
npm run build

# Clear caches
echo "🗑️  Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Run migrations
echo "🔄 Running migrations..."
php artisan migrate --force

# Generate optimized caches
echo "⚡ Generating optimized caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart workers
echo "🔄 Restarting queue workers..."
php artisan queue:restart

# Set permissions
echo "🔐 Setting permissions..."
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

echo "✅ Staging deployment complete!"
echo "🌐 URL: https://staging.mswms.example.com"
```

### Production Deployment Script

```bash
#!/bin/bash
# deploy-production.sh

set -e

echo "🚀 Starting Production Deployment..."

# Configuration
APP_DIR="/var/www/mswms"
BACKUP_DIR="/var/backups/mswms"
DATE=$(date +%Y%m%d_%H%M%S)

# Navigate to application
cd $APP_DIR

# Enable maintenance mode
echo "🔧 Enabling maintenance mode..."
php artisan down --secret="maintenance_secret_token" --retry=60

# Create backup
echo "💾 Creating backup..."
mkdir -p $BACKUP_DIR
pg_dump -h localhost -U mswms_prod_user mswms_production | gzip > $BACKUP_DIR/db_backup_$DATE.sql.gz
cp .env $BACKUP_DIR/env_backup_$DATE

# Pull latest code
echo "📦 Pulling latest code..."
git pull origin main

# Install dependencies
echo "📦 Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction --classmap-authoritative
npm install --production
npm run build

# Clear caches
echo "🗑️  Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Run migrations
echo "🔄 Running migrations..."
php artisan migrate --force

# Generate optimized caches
echo "⚡ Generating optimized caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
composer dump-autoload --classmap-authoritative

# Restart services
echo "🔄 Restarting services..."
php artisan queue:restart
sudo systemctl restart php8.3-fpm
sudo supervisorctl restart mswms-worker:*

# Wait for services
sleep 5

# Test health endpoint
echo "🧪 Testing health endpoint..."
HEALTH_CHECK=$(curl -s -o /dev/null -w "%{http_code}" https://api.mswms.example.com/api/health)

if [ "$HEALTH_CHECK" = "200" ]; then
    echo "✅ Health check passed"
    
    # Disable maintenance mode
    echo "🔓 Disabling maintenance mode..."
    php artisan up
    
    echo "✅ Production deployment complete!"
    echo "🌐 URL: https://api.mswms.example.com"
else
    echo "❌ Health check failed (HTTP $HEALTH_CHECK)"
    echo "🔄 Rolling back deployment..."
    
    # Rollback migration
    php artisan migrate:rollback --force
    
    # Restore .env
    cp $BACKUP_DIR/env_backup_$DATE .env
    
    # Disable maintenance mode
    php artisan up
    
    echo "❌ Deployment rolled back. Please investigate and retry."
    exit 1
fi
```

### Make Scripts Executable

```bash
chmod +x deploy-staging.sh
chmod +x deploy-production.sh
```

## Rollback Procedure

### Quick Rollback

```bash
# Enable maintenance mode
php artisan down

# Rollback last migration
php artisan migrate:rollback --force

# Restore previous code version
git checkout previous-tag-or-commit

# Restore previous .env
cp .env.backup .env

# Clear and cache
php artisan config:clear
php artisan cache:clear
php artisan config:cache
php artisan route:cache

# Restart services
php artisan queue:restart
sudo systemctl restart php8.3-fpm
sudo supervisorctl restart mswms-worker:*

# Disable maintenance mode
php artisan up
```

### Database Rollback

```bash
# Restore from backup
gunzip /var/backups/mswms/db_backup_20260325_120000.sql.gz
psql -h localhost -U mswms_prod_user mswms_production < /var/backups/mswms/db_backup_20260325_120000.sql
```

## Deployment Verification Checklist

### Immediate Checks (First 5 Minutes)

- [ ] Health endpoint returns 200 OK
- [ ] Application loads without errors
- [ ] No errors in application logs
- [ ] No errors in web server logs
- [ ] Queue workers running
- [ ] Cache operational

### Short-Term Checks (First Hour)

- [ ] API endpoints responding correctly
- [ ] Authentication working
- [ ] Database queries executing
- [ ] No increase in error rate
- [ ] Response times acceptable
- [ ] Queue processing jobs

### Long-Term Checks (First 24 Hours)

- [ ] No memory leaks
- [ ] Disk space stable
- [ ] CPU usage normal
- [ ] Error rate within acceptable range
- [ ] User reports no issues
- [ ] Monitoring alerts normal

## Troubleshooting

### Common Deployment Issues

#### 1. Permission Denied
```bash
# Fix permissions
sudo chown -R www-data:www-data /var/www/mswms
sudo chmod -R 775 storage bootstrap/cache
```

#### 2. Class Not Found
```bash
# Regenerate autoload files
composer dump-autoload --optimize
```

#### 3. Configuration Not Loading
```bash
# Clear configuration cache
php artisan config:clear
php artisan config:cache
```

#### 4. Queue Not Processing
```bash
# Check worker status
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart mswms-worker:*

# Check queue
php artisan queue:monitor database
```

#### 5. Migration Failed
```bash
# Check migration status
php artisan migrate:status

# Rollback failed migration
php artisan migrate:rollback

# Fix migration file and retry
php artisan migrate --force
```

---

**Previous Section**: [← SSL/TLS Configuration](06-ssl-configuration.md)  
**Next Section**: [Queue Workers →](08-queue-workers.md)
