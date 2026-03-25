# Web Server Setup and Configuration

## Overview

This document provides comprehensive instructions for configuring Nginx (recommended) and Apache web servers for MSWMS. Proper web server configuration is critical for performance, security, and scalability.

## Web Server Selection

### Nginx (Recommended)

**Advantages:**
- Superior performance for static files
- Lower memory footprint
- Better concurrency handling
- Built-in reverse proxy capabilities
- Excellent SSL/TLS support
- Modern HTTP/2 and HTTP/3 support

**Recommended for:**
- Production environments
- High-traffic applications
- API-focused deployments
- Microservices architectures

### Apache (Alternative)

**Advantages:**
- Extensive module ecosystem
- .htaccess support
- Familiar to many administrators
- Dynamic module loading

**Recommended for:**
- Shared hosting environments
- Teams with Apache expertise
- Legacy system integration

## Nginx Installation

### Ubuntu/Debian Installation

```bash
# Add Nginx repository
sudo apt update
sudo apt install -y curl gnupg2 ca-certificates lsb-release

echo "deb http://nginx.org/packages/mainline/ubuntu $(lsb_release -cs) nginx" | sudo tee /etc/apt/sources.list.d/nginx.list

curl -fsSL https://nginx.org/keys/nginx_signing.key | sudo apt-key add -

# Install Nginx
sudo apt update
sudo apt install -y nginx

# Start and enable service
sudo systemctl start nginx
sudo systemctl enable nginx
sudo systemctl status nginx
```

### CentOS/RHEL Installation

```bash
# Add Nginx repository
sudo yum install -y epel-release
sudo yum install -y nginx

# Start and enable service
sudo systemctl start nginx
sudo systemctl enable nginx
sudo systemctl status nginx
```

### Install PHP-FPM

```bash
# Ubuntu/Debian
sudo apt install -y php8.3-fpm php8.3-cli php8.3-common

# CentOS/RHEL
sudo yum install -y php-fpm php-cli php-common

# Start and enable PHP-FPM
sudo systemctl start php8.3-fpm
sudo systemctl enable php8.3-fpm
sudo systemctl status php8.3-fpm
```

## Nginx Configuration

### Configuration File Locations

- **Main Config**: `/etc/nginx/nginx.conf`
- **Site Configs**: `/etc/nginx/sites-available/`
- **Enabled Sites**: `/etc/nginx/sites-enabled/`
- **SSL Certificates**: `/etc/nginx/ssl/`

### Main Nginx Configuration

**Edit nginx.conf:**
```bash
sudo nano /etc/nginx/nginx.conf
```

**Production Configuration:**
```nginx
user www-data;
worker_processes auto;
pid /run/nginx.pid;
include /etc/nginx/modules-enabled/*.conf;

events {
    worker_connections 1024;
    use epoll;
    multi_accept on;
}

http {
    ##
    # Basic Settings
    ##
    sendfile on;
    tcp_nopush on;
    types_hash_max_size 2048;
    server_tokens off;
    server_names_hash_bucket_size 128;
    client_max_body_size 25M;

    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    ##
    # SSL Settings
    ##
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;
    ssl_stapling on;
    ssl_stapling_verify on;

    ##
    # Logging Settings
    ##
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log warn;

    ##
    # Gzip Settings
    ##
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss application/rss+xml font/truetype font/opentype application/vnd.ms-fontobject image/svg+xml;
    gzip_min_length 256;

    ##
    # Virtual Host Configs
    ##
    include /etc/nginx/conf.d/*.conf;
    include /etc/nginx/sites-enabled/*;
}
```

### MSWMS Nginx Configuration

**Create Site Configuration:**
```bash
sudo nano /etc/nginx/sites-available/mswms
```

**Staging Environment:**
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name staging.mswms.example.com;
    
    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name staging.mswms.example.com;
    root /var/www/mswms/public;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/staging.mswms.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/staging.mswms.example.com/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';" always;

    # Logging
    access_log /var/log/nginx/mswms_staging_access.log;
    error_log /var/log/nginx/mswms_staging_error.log;

    # Index files
    index index.php index.html;

    # Deny access to sensitive files
    location ~ /\.ht {
        deny all;
    }

    location ~ /\.env {
        deny all;
    }

    location ~ /composer\.(json|lock)$ {
        deny all;
    }

    location ~ /phpunit\.xml$ {
        deny all;
    }

    location ~ /\.git {
        deny all;
    }

    # Handle static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|pdf|txt|woff|woff2|ttf|eot|svg)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Handle API requests
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM Configuration
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
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
    location /api/health {
        try_files $uri $uri/ /index.php?$query_string;
        access_log off;
    }
}
```

**Production Environment:**
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name api.mswms.example.com;
    
    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name api.mswms.example.com;
    root /var/www/mswms/public;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/api.mswms.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.mswms.example.com/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    # Security Headers (Enhanced for Production)
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'nonce-$request_id'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' https:; frame-ancestors 'none'; base-uri 'self'; form-action 'self';" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;

    # Logging
    access_log /var/log/nginx/mswms_production_access.log;
    error_log /var/log/nginx/mswms_production_error.log;

    # Index files
    index index.php index.html;

    # Deny access to sensitive files
    location ~ /\.ht {
        deny all;
    }

    location ~ /\.env {
        deny all;
    }

    location ~ /composer\.(json|lock)$ {
        deny all;
    }

    location ~ /phpunit\.xml$ {
        deny all;
    }

    location ~ /\.git {
        deny all;
    }

    location ~ /vendor {
        deny all;
    }

    # Handle static files with CDN
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|pdf|txt|woff|woff2|ttf|eot|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
        
        # Add CORS headers for fonts
        location ~* \.(woff|woff2|ttf|eot|svg)$ {
            add_header Access-Control-Allow-Origin *;
        }
    }

    # Handle API requests
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM Configuration
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_param HTTP_PROXY "";
        fastcgi_connect_timeout 60s;
        fastcgi_send_timeout 60s;
        fastcgi_read_timeout 60s;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
        
        # Hide PHP version
        fastcgi_hide_header X-Powered-By;
    }

    # Health check endpoint
    location /api/health {
        try_files $uri $uri/ /index.php?$query_string;
        access_log off;
    }

    # Rate Limiting
    location /api/ {
        limit_req zone=api burst=20 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    location /api/v1/admin/ {
        limit_req zone=admin burst=30 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }
}

# Rate Limiting Zones
limit_req_zone $binary_remote_addr zone=api:10m rate=100r/m;
limit_req_zone $binary_remote_addr zone=admin:10m rate=150r/m;
limit_req_zone $binary_remote_addr zone=auth:10m rate=30r/m;
```

### Enable Site

```bash
# Create symbolic link
sudo ln -s /etc/nginx/sites-available/mswms /etc/nginx/sites-enabled/

# Remove default site
sudo rm /etc/nginx/sites-enabled/default

# Test configuration
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx
```

## PHP-FPM Configuration

### Configure PHP-FPM Pool

**Edit Pool Configuration:**
```bash
sudo nano /etc/php/8.3/fpm/pool.d/www.conf
```

**Production Configuration:**
```ini
[www]
; Pool Configuration
user = www-data
group = www-data

listen = /run/php/php8.3-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
listen.backlog = 2048

; Process Manager
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
pm.max_requests_idle = 300

; Environment Variables
env[APP_ENV] = production

; PHP Settings
php_admin_value[sendmail_path] = /usr/sbin/sendmail -t -i -f www@mswms.example.com
php_flag[display_errors] = off
php_admin_value[error_log] = /var/log/php-fpm/www-error.log
php_admin_flag[log_errors] = on
php_admin_value[memory_limit] = 512M
php_admin_value[max_execution_time] = 60
php_admin_value[upload_max_filesize] = 20M
php_admin_value[post_max_size] = 25M

; Security
php_admin_value[open_basedir] = /var/www/mswms:/tmp
php_admin_flag[expose_php] = off
php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source
```

### Restart PHP-FPM

```bash
sudo systemctl restart php8.3-fpm
sudo systemctl status php8.3-fpm
```

## Apache Installation and Configuration

### Ubuntu/Debian Installation

```bash
# Install Apache and PHP module
sudo apt update
sudo apt install -y apache2 libapache2-mod-php8.3 php8.3-fpm

# Enable required modules
sudo a2enmod rewrite ssl headers expires deflate

# Start and enable service
sudo systemctl start apache2
sudo systemctl enable apache2
sudo systemctl status apache2
```

### Apache Virtual Host Configuration

**Create Virtual Host:**
```bash
sudo nano /etc/apache2/sites-available/mswms.conf
```

**Configuration:**
```apache
<VirtualHost *:80>
    ServerName staging.mswms.example.com
    
    # Redirect to HTTPS
    Redirect permanent / https://staging.mswms.example.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName staging.mswms.example.com
    DocumentRoot /var/www/mswms/public

    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/staging.mswms.example.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/staging.mswms.example.com/privkey.pem
    SSLCertificateChainFile /etc/letsencrypt/live/staging.mswms.example.com/chain.pem

    # Security Headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"

    # Logging
    ErrorLog ${APACHE_LOG_DIR}/mswms_staging_error.log
    CustomLog ${APACHE_LOG_DIR}/mswms_staging_access.log combined

    # Directory Configuration
    <Directory /var/www/mswms/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Deny access to sensitive files
    <FilesMatch "^\.env">
        Require all denied
    </FilesMatch>

    <FilesMatch "^composer\.(json|lock)$">
        Require all denied
    </FilesMatch>

    <FilesMatch "^phpunit\.xml$">
        Require all denied
    </FilesMatch>

    # Handle static files
    <FilesMatch "\.(jpg|jpeg|png|gif|ico|css|js|pdf|txt|woff|woff2|ttf|eot|svg)$">
        Header set Cache-Control "public, max-age=2592000"
    </FilesMatch>
</VirtualHost>
```

### Enable Site

```bash
# Enable site
sudo a2ensite mswms.conf

# Disable default site
sudo a2dissite 000-default.conf

# Test configuration
sudo apache2ctl configtest

# Restart Apache
sudo systemctl restart apache2
```

## File Permissions

### Set Correct Permissions

```bash
# Navigate to application directory
cd /var/www/mswms

# Set ownership
sudo chown -R www-data:www-data /var/www/mswms

# Set directory permissions
sudo find /var/www/mswms -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /var/www/mswms -type f -exec chmod 644 {} \;

# Set storage permissions
sudo chmod -R 775 /var/www/mswms/storage
sudo chown -R www-data:www-data /var/www/mswms/storage

# Set bootstrap/cache permissions
sudo chmod -R 775 /var/www/mswms/bootstrap/cache
sudo chown -R www-data:www-data /var/www/mswms/bootstrap/cache

# Secure .env file
sudo chmod 600 /var/www/mswms/.env
sudo chown www-data:www-data /var/www/mswms/.env
```

## Performance Optimization

### Enable Gzip Compression

**Nginx:**
```nginx
gzip on;
gzip_vary on;
gzip_proxied any;
gzip_comp_level 6;
gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss application/rss+xml font/truetype font/opentype application/vnd.ms-fontobject image/svg+xml;
```

**Apache:**
```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json application/xml
    DeflateCompressionLevel 6
</IfModule>
```

### Enable Browser Caching

**Nginx:**
```nginx
location ~* \.(jpg|jpeg|png|gif|ico|css|js|pdf|txt|woff|woff2|ttf|eot|svg)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}
```

**Apache:**
```apache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/x-icon "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType application/pdf "access plus 1 month"
    ExpiresByType font/woff2 "access plus 1 year"
</IfModule>
```

### Enable HTTP/2 Push (Optional)

**Nginx:**
```nginx
http2_push /css/app.css;
http2_push /js/app.js;
```

## Security Hardening

### Hide Server Version

**Nginx:**
```nginx
server_tokens off;
```

**Apache:**
```apache
ServerTokens Prod
ServerSignature Off
```

### Disable Directory Listing

**Nginx:**
```nginx
location / {
    autoindex off;
}
```

**Apache:**
```apache
Options -Indexes
```

### Configure Security Headers

**Nginx:**
```nginx
add_header X-Frame-Options "DENY" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Content-Security-Policy "default-src 'self';" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

**Apache:**
```apache
Header always set X-Frame-Options "DENY"
Header always set X-Content-Type-Options "nosniff"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Content-Security-Policy "default-src 'self';"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

## Monitoring and Logging

### Configure Log Rotation

**Create Logrotate Config:**
```bash
sudo nano /etc/logrotate.d/mswms
```

**Configuration:**
```
/var/log/nginx/mswms_*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data adm
    sharedscripts
    postrotate
        [ -f /var/run/nginx.pid ] && kill -USR1 `cat /var/run/nginx.pid`
    endscript
}

/var/log/php-fpm/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data adm
}
```

### Monitor Logs

```bash
# View access logs
sudo tail -f /var/log/nginx/mswms_production_access.log

# View error logs
sudo tail -f /var/log/nginx/mswms_production_error.log

# View PHP-FPM logs
sudo tail -f /var/log/php-fpm/www-error.log

# Analyze logs with goaccess
sudo apt install -y goaccess
goaccess /var/log/nginx/mswms_production_access.log --real-time-html --addr=0.0.0.0 --port=7890
```

## Troubleshooting

### Common Issues

#### 1. 502 Bad Gateway
```bash
# Check PHP-FPM is running
sudo systemctl status php8.3-fpm

# Check socket exists
ls -la /run/php/php8.3-fpm.sock

# Check Nginx error log
sudo tail -f /var/log/nginx/mswms_error.log
```

#### 2. 403 Forbidden
```bash
# Check file permissions
ls -la /var/www/mswms/public

# Check ownership
sudo chown -R www-data:www-data /var/www/mswms
```

#### 3. 500 Internal Server Error
```bash
# Check PHP error log
sudo tail -f /var/log/php-fpm/www-error.log

# Check application logs
sudo tail -f /var/www/mswms/storage/logs/laravel.log

# Enable debug mode temporarily
sudo nano /var/www/mswms/.env
# APP_DEBUG=true
```

#### 4. SSL Certificate Issues
```bash
# Check certificate expiration
sudo openssl x509 -in /etc/letsencrypt/live/api.mswms.example.com/fullchain.pem -text -noout | grep "Not After"

# Renew certificate
sudo certbot renew --force-renewal

# Reload Nginx
sudo systemctl reload nginx
```

---

**Previous Section**: [← Redis Cache Setup](04-redis-cache-setup.md)  
**Next Section**: [SSL/TLS Configuration →](06-ssl-configuration.md)
