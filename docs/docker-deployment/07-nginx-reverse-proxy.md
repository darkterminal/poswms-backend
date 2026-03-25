# Nginx Reverse Proxy for MSWMS

## Overview

This document covers configuring Nginx as a reverse proxy for MSWMS in Docker environments, including SSL termination, caching, and security headers.

## Why Nginx?

**Advantages for MSWMS:**
- High performance reverse proxy
- Excellent static file serving
- SSL/TLS termination
- Load balancing capabilities
- Gzip compression
- Caching support
- Low memory footprint

## Basic Nginx Configuration

### Minimal Setup

```yaml
# docker-compose.yml
services:
  nginx:
    image: nginx:alpine
    container_name: mswms-nginx
    restart: unless-stopped
    ports:
      - "8080:80"
    volumes:
      - ./public:/var/www/html/public:ro
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    networks:
      - mswms-network
    depends_on:
      - app
```

### Basic Nginx Config

**docker/nginx/default.conf:**
```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php index.html;

    # Max upload size
    client_max_body_size 25M;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Deny sensitive files
    location ~ /\.ht { deny all; }
    location ~ /\.env { deny all; }

    # Static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|pdf|txt|woff|woff2|ttf|eot|svg)$ {
        expires 30d;
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
    }
}
```

## Production Configuration

### Complete Nginx Service

```yaml
services:
  nginx:
    image: nginx:alpine
    container_name: mswms-nginx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./public:/var/www/html/public:ro
      - ./docker/nginx/nginx.conf:/etc/nginx/nginx.conf:ro
      - ./docker/nginx/conf.d:/etc/nginx/conf.d:ro
      - ./ssl:/etc/nginx/ssl:ro
      - nginx_cache:/var/cache/nginx
    networks:
      - mswms-network
    depends_on:
      - app
    healthcheck:
      test: ["CMD", "wget", "--no-verbose", "--tries=1", "--spider", "http://localhost:80/health"]
      interval: 30s
      timeout: 5s
      retries: 3
    deploy:
      resources:
        limits:
          cpus: '2.0'
          memory: 512M
```

### Main Nginx Configuration

**docker/nginx/nginx.conf:**
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

    # Logging format
    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';

    access_log /var/log/nginx/access.log main;

    # Performance
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    client_max_body_size 25M;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_min_length 256;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/json
        application/javascript
        application/xml+rss
        application/rss+xml
        font/truetype
        font/opentype
        application/vnd.ms-fontobject
        image/svg+xml;

    # Include server configurations
    include /etc/nginx/conf.d/*.conf;
}
```

### Production Server Configuration

**docker/nginx/conf.d/default.conf:**
```nginx
server {
    listen 80;
    server_name api.mswms.example.com;
    
    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name api.mswms.example.com;
    root /var/www/html/public;
    index index.php index.html;

    # SSL Configuration
    ssl_certificate /etc/nginx/ssl/fullchain.pem;
    ssl_certificate_key /etc/nginx/ssl/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;

    # Security Headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;

    # Max upload size
    client_max_body_size 25M;

    # Deny sensitive files
    location ~ /\.ht { deny all; }
    location ~ /\.env { deny all; }
    location ~ /composer\.(json|lock)$ { deny all; }
    location ~ /phpunit\.xml$ { deny all; }
    location ~ /\.git { deny all; }
    location ~ /vendor { deny all; }

    # Static files with caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|pdf|txt|woff|woff2|ttf|eot|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
        
        # CORS for fonts
        location ~* \.(woff|woff2|ttf|eot|svg)$ {
            add_header Access-Control-Allow-Origin *;
        }
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

    # API health
    location /api/health {
        try_files $uri $uri/ /index.php?$query_string;
        access_log off;
    }

    # Rate limiting (optional)
    location /api/ {
        limit_req zone=api burst=20 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Admin area (stricter rate limiting)
    location /api/v1/admin/ {
        limit_req zone=admin burst=30 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }
}

# Rate limiting zones
limit_req_zone $binary_remote_addr zone=api:10m rate=100r/m;
limit_req_zone $binary_remote_addr zone=admin:10m rate=150r/m;
```

## SSL Configuration

### Self-Signed Certificate (Development)

```bash
# Generate self-signed certificate
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout docker/ssl/privkey.pem \
    -out docker/ssl/fullchain.pem \
    -subj "/C=US/ST=State/L=City/O=Organization/CN=localhost"
```

### Let's Encrypt (Production)

**Using Certbot:**
```bash
# Install certbot
sudo apt install -y certbot

# Obtain certificate
sudo certbot certonly --standalone -d api.mswms.example.com

# Certificates will be at:
# /etc/letsencrypt/live/api.mswms.example.com/fullchain.pem
# /etc/letsencrypt/live/api.mswms.example.com/privkey.pem

# Copy to docker/ssl
sudo cp /etc/letsencrypt/live/api.mswms.example.com/fullchain.pem docker/ssl/
sudo cp /etc/letsencrypt/live/api.mswms.example.com/privkey.pem docker/ssl/
```

### Traefik for Automatic SSL

```yaml
# docker-compose.yml
services:
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

  app:
    labels:
      - "traefik.http.routers.mswms.rule=Host(`api.mswms.example.com`)"
      - "traefik.http.routers.mswms.entrypoints=websecure"
      - "traefik.http.routers.mswms.tls.certresolver=letsencrypt"
      - "traefik.http.services.mswms.loadbalancer.server.port=9000"
```

## Caching Configuration

### Nginx Cache

```nginx
# Add to nginx.conf
http {
    # Cache configuration
    proxy_cache_path /var/cache/nginx levels=1:2 keys_zone=app_cache:10m max_size=1g inactive=60m use_temp_path=off;

    server {
        # Enable cache for static assets
        location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
            proxy_cache app_cache;
            proxy_cache_valid 200 1y;
            proxy_cache_use_stale error timeout updating http_500 http_502 http_503 http_504;
            add_header X-Cache-Status $upstream_cache_status;
        }
    }
}
```

### Gzip Compression

```nginx
# Add to nginx.conf
http {
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_min_length 256;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/json
        application/javascript
        application/xml+rss
        application/rss+xml
        font/truetype
        font/opentype
        application/vnd.ms-fontobject
        image/svg+xml;
}
```

## Load Balancing

### Multiple App Instances

```yaml
services:
  nginx:
    image: nginx:alpine
    volumes:
      - ./docker/nginx/upstream.conf:/etc/nginx/conf.d/upstream.conf:ro

  app1:
    build: .
    networks:
      - mswms-network

  app2:
    build: .
    networks:
      - mswms-network

  app3:
    build: .
    networks:
      - mswms-network
```

**docker/nginx/upstream.conf:**
```nginx
upstream mswms_app {
    least_conn;
    server app1:9000;
    server app2:9000;
    server app3:9000;
    keepalive 32;
}

server {
    listen 80;
    server_name api.mswms.example.com;

    location ~ \.php$ {
        fastcgi_pass mswms_app;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_param HTTP_PROXY "";
    }
}
```

## Monitoring

### Access Logs

```bash
# View access logs
docker compose logs -f nginx

# Real-time access log
docker compose exec nginx tail -f /var/log/nginx/access.log

# View error logs
docker compose exec nginx tail -f /var/log/nginx/error.log

# Analyze logs
docker compose exec nginx awk '{print $1}' /var/log/nginx/access.log | sort | uniq -c | sort -rn | head -10
```

### Nginx Status Page

```nginx
# Add to conf.d/status.conf
server {
    listen 80;
    server_name localhost;

    location /nginx_status {
        stub_status on;
        allow 127.0.0.1;
        allow 172.16.0.0/12;  # Docker network
        deny all;
    }
}
```

### Prometheus Exporter

```yaml
services:
  nginx-exporter:
    image: nginx/nginx-prometheus-exporter:latest
    container_name: mswms-nginx-exporter
    command:
      - '-nginx.scrape-uri=http://nginx/nginx_status'
    ports:
      - "9113:9113"
    networks:
      - mswms-network
    depends_on:
      - nginx
```

## Troubleshooting

### Configuration Test

```bash
# Test Nginx configuration
docker compose exec nginx nginx -t

# Reload Nginx
docker compose exec nginx nginx -s reload
```

### Common Issues

**502 Bad Gateway:**
```bash
# Check if app is running
docker compose ps app

# Check app logs
docker compose logs app

# Test connection
docker compose exec nginx ping app
```

**403 Forbidden:**
```bash
# Check file permissions
docker compose exec app ls -la /var/www/html/public

# Fix permissions
docker compose exec app chown -R www-data:www-data /var/www/html
```

**SSL Issues:**
```bash
# Check certificate
openssl x509 -in docker/ssl/fullchain.pem -text -noout | grep "Not After"

# Test SSL
curl -k https://localhost/api/health
```

**High Memory Usage:**
```bash
# Reduce worker connections
# Edit nginx.conf: worker_connections 512;

# Reduce cache size
# Edit nginx.conf: keys_zone=app_cache:5m
```

### Performance Tuning

```bash
# Check current connections
docker compose exec nginx nginx -T | grep worker_connections

# Check cache status
docker compose exec nginx cat /var/cache/nginx/*

# Analyze slow requests
docker compose exec nginx awk '$NF > 1' /var/log/nginx/access.log | sort -t$'\t' -k9 -rn | head -10
```

---

**Previous Section**: [← Redis Containerization](06-redis-containerization.md)  
**Next Section**: [SSL Certificates →](08-ssl-certificates.md)
