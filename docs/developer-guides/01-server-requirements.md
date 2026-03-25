# Server Requirements and Prerequisites

## Overview

This document outlines the minimum and recommended server specifications for deploying MSWMS to staging and production environments. Proper server configuration ensures optimal performance, security, and scalability.

## Minimum System Requirements

### CPU Requirements

| Environment | Minimum | Recommended | Notes |
|-------------|---------|-------------|-------|
| Staging | 2 vCPU | 4 vCPU | Suitable for testing and UAT |
| Production (Small) | 4 vCPU | 8 vCPU | Up to 10,000 requests/day |
| Production (Medium) | 8 vCPU | 16 vCPU | Up to 100,000 requests/day |
| Production (Large) | 16 vCPU | 32+ vCPU | 100,000+ requests/day |

### Memory Requirements

| Environment | Minimum | Recommended | Notes |
|-------------|---------|-------------|-------|
| Staging | 4 GB RAM | 8 GB RAM | Basic testing workloads |
| Production (Small) | 8 GB RAM | 16 GB RAM | Small business deployment |
| Production (Medium) | 16 GB RAM | 32 GB RAM | Multi-tenant deployment |
| Production (Large) | 32 GB RAM | 64+ GB RAM | Enterprise deployment |

### Storage Requirements

| Environment | Minimum | Recommended | Type |
|-------------|---------|-------------|------|
| Staging | 40 GB | 80 GB | SSD |
| Production | 100 GB | 200+ GB | NVMe SSD |
| Database Server | 200 GB | 500+ GB | NVMe SSD, RAID 10 |

**Storage Calculation Formula:**
```
Total Storage = Application (20GB) + Logs (10GB/month) + Database (variable) + Backups (2x database size)
```

## Operating System Requirements

### Supported Operating Systems

#### Linux Distributions (Recommended)
- **Ubuntu Server**: 22.04 LTS or 24.04 LTS (Recommended)
- **Debian**: 11 (Bullseye) or 12 (Bookworm)
- **CentOS/RHEL**: 8.x or 9.x
- **Amazon Linux**: 2023

#### Windows Server (Not Recommended)
- Windows Server 2022 (with WSL2 for PHP)
- Only for development environments

### OS Configuration

#### System Updates
```bash
# Ubuntu/Debian
sudo apt update && sudo apt upgrade -y

# CentOS/RHEL
sudo yum update -y
# or
sudo dnf update -y
```

#### Required System Packages

**Ubuntu/Debian:**
```bash
sudo apt install -y \
    php8.3 \
    php8.3-cli \
    php8.3-fpm \
    php8.3-mbstring \
    php8.3-xml \
    php8.3-curl \
    php8.3-gd \
    php8.3-imap \
    php8.3-mysql \
    php8.3-pgsql \
    php8.3-zip \
    php8.3-bcmath \
    php8.3-redis \
    php8.3-intl \
    php8.3-bz2 \
    php8.3-soap \
    php8.3-xmlrpc \
    nginx \
    git \
    curl \
    unzip \
    supervisor \
    redis \
    postgresql \
    postgresql-contrib \
    mysql-server \
    certbot \
    python3-certbot-nginx
```

**CentOS/RHEL:**
```bash
sudo yum install -y \
    php \
    php-cli \
    php-fpm \
    php-mbstring \
    php-xml \
    php-curl \
    php-gd \
    php-mysqlnd \
    php-pgsql \
    php-zip \
    php-bcmath \
    php-redis \
    php-intl \
    nginx \
    git \
    curl \
    unzip \
    supervisor \
    redis \
    postgresql \
    postgresql-server \
    mariadb-server \
    certbot \
    python3-certbot-nginx
```

## PHP Requirements

### PHP Version
- **Required**: PHP 8.3 or higher
- **Recommended**: PHP 8.3.x (latest patch version)

### Required PHP Extensions
```
✓ bcmath
✓ ctype
✓ curl
✓ dom
✓ fileinfo
✓ gd
✓ intl
✓ json
✓ mbstring
✓ mysqlnd (for MySQL)
✓ openssl
✓ pdo
✓ pdo_mysql (for MySQL)
✓ pdo_pgsql (for PostgreSQL)
✓ pgsql (for PostgreSQL)
✓ redis
✓ session
✓ tokenizer
✓ xml
✓ zip
```

### PHP Configuration (php.ini)

**Memory Settings:**
```ini
memory_limit = 512M
max_execution_time = 60
max_input_time = 60
```

**Upload Settings:**
```ini
upload_max_filesize = 20M
post_max_size = 25M
```

**OPcache Settings:**
```ini
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 60
opcache.fast_shutdown = 1
```

**Session Settings:**
```ini
session.gc_maxlifetime = 28800
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
```

### Verify PHP Installation
```bash
php -v
php -m
php --ini
```

## Web Server Requirements

### Nginx (Recommended)
- **Version**: 1.24.x or higher
- **Configuration**: Worker processes, SSL/TLS, Gzip compression
- **Modules**: ngx_http_ssl_module, ngx_http_gzip_module, ngx_http_v2_module

### Apache (Alternative)
- **Version**: 2.4.x or higher
- **Modules**: mod_rewrite, mod_ssl, mod_headers, mod_deflate
- **MPM**: event or worker (not prefork)

## Database Server Requirements

### PostgreSQL (Recommended)
- **Version**: 15.x or higher
- **Memory**: 2GB+ dedicated RAM
- **Storage**: SSD with minimum 200GB
- **Extensions**: pg_stat_statements, pgcrypto

### MySQL/MariaDB (Alternative)
- **Version**: MySQL 8.0+ or MariaDB 10.6+
- **Memory**: 2GB+ dedicated RAM
- **Storage**: SSD with minimum 200GB
- **Engine**: InnoDB (default)

## Redis Requirements

### Redis Server
- **Version**: 7.0.x or higher
- **Memory**: 512MB minimum, 2GB+ recommended
- **Persistence**: RDB snapshots enabled
- **Security**: Password authentication required

### Redis Configuration
```conf
maxmemory 2gb
maxmemory-policy allkeys-lru
appendonly yes
requirepass your_secure_password
```

## Network Requirements

### Ports

| Port | Service | Direction | Required |
|------|---------|-----------|----------|
| 22 | SSH | Inbound | Yes |
| 80 | HTTP | Inbound | Yes (redirect to HTTPS) |
| 443 | HTTPS | Inbound | Yes |
| 3306 | MySQL | Inbound | Internal only |
| 5432 | PostgreSQL | Inbound | Internal only |
| 6379 | Redis | Inbound | Internal only |

### Firewall Configuration

**UFW (Ubuntu):**
```bash
sudo ufw allow 22/tcp      # SSH
sudo ufw allow 80/tcp      # HTTP
sudo ufw allow 443/tcp     # HTTPS
sudo ufw enable
```

**firewalld (CentOS/RHEL):**
```bash
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

## Security Requirements

### SSL/TLS Certificates
- **Provider**: Let's Encrypt (free) or commercial CA
- **Type**: TLS 1.2 or TLS 1.3
- **Renewal**: Automatic via certbot

### Server Hardening
- Disable root SSH login
- Use SSH key authentication only
- Enable fail2ban for intrusion prevention
- Configure automatic security updates
- Implement log rotation

### File Permissions
```bash
# Application directory
chown -R www-data:www-data /var/www/mswms
chmod -R 755 /var/www/mswms

# Storage and bootstrap/cache
chmod -R 775 /var/www/mswms/storage
chmod -R 775 /var/www/mswms/bootstrap/cache

# Environment file
chmod 600 /var/www/mswms/.env
```

## Monitoring Requirements

### System Monitoring
- CPU usage
- Memory usage
- Disk space
- Network I/O
- Process count

### Application Monitoring
- Request rate
- Response time
- Error rate
- Queue length
- Cache hit rate

### Recommended Tools
- **System**: Prometheus + Grafana, New Relic, Datadog
- **Logs**: ELK Stack, Graylog, Papertrail
- **APM**: New Relic, Blackfire, Tideways
- **Uptime**: UptimeRobot, Pingdom, StatusCake

## Backup Requirements

### Database Backups
- **Frequency**: Daily (minimum), hourly (recommended)
- **Retention**: 30 days minimum
- **Storage**: Off-site (S3, GCS, Azure Blob)
- **Encryption**: AES-256

### File Backups
- **What to backup**: Storage directory, .env file
- **Frequency**: Daily
- **Retention**: 14 days minimum

## Scalability Considerations

### Horizontal Scaling
- Load balancer configuration
- Session storage (Redis)
- Database read replicas
- CDN for static assets

### Vertical Scaling
- Increase CPU cores
- Add more RAM
- Upgrade to faster storage
- Optimize database queries

### Auto-Scaling Triggers
- CPU > 70% for 5 minutes
- Memory > 80% for 5 minutes
- Request queue > 100

## Environment-Specific Configurations

### Staging Environment
```yaml
CPU: 4 vCPU
RAM: 8 GB
Storage: 80 GB SSD
Database: PostgreSQL 15 (managed)
Redis: 1 GB
Bandwidth: 1 TB/month
Backup: Daily
Monitoring: Basic
```

### Production Environment (Small)
```yaml
CPU: 8 vCPU
RAM: 16 GB
Storage: 200 GB NVMe SSD
Database: PostgreSQL 15 (managed, HA)
Redis: 2 GB
Bandwidth: 5 TB/month
Backup: Hourly
Monitoring: Advanced
CDN: Enabled
```

### Production Environment (Enterprise)
```yaml
CPU: 32+ vCPU
RAM: 64+ GB
Storage: 1 TB NVMe SSD (RAID 10)
Database: PostgreSQL 15 (cluster, read replicas)
Redis: 8 GB (cluster)
Bandwidth: Unlimited
Backup: Continuous
Monitoring: Enterprise APM
CDN: Multi-region
Load Balancer: Multi-AZ
```

## Pre-Deployment Checklist

- [ ] Server meets minimum requirements
- [ ] Operating system is updated
- [ ] All required packages installed
- [ ] PHP version is 8.3+
- [ ] All PHP extensions enabled
- [ ] Web server configured
- [ ] Database server accessible
- [ ] Redis server accessible
- [ ] Firewall rules configured
- [ ] SSL certificates ready
- [ ] Monitoring tools installed
- [ ] Backup system configured
- [ ] SSH keys configured
- [ ] File permissions set correctly

---

**Previous Section**: [← Overview & Introduction](00-overview-introduction.md)  
**Next Section**: [Environment Configuration →](02-environment-configuration.md)
