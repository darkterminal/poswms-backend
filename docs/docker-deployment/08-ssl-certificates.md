# SSL/TLS Certificates for Docker Deployments

## Overview

This document covers SSL/TLS certificate configuration for MSWMS Docker deployments, including Let's Encrypt, Traefik, and manual certificate management.

## SSL/TLS Options

| Method | Cost | Auto-Renewal | Complexity | Best For |
|--------|------|--------------|------------|----------|
| Let's Encrypt | Free | Yes | Low | Production |
| Traefik + LE | Free | Yes | Low | Docker environments |
| Manual Cert | $ | No | Medium | Special cases |
| CloudFlare | Free | Yes | Low | With CDN |

## Let's Encrypt with Certbot

### Installation

```bash
# Install Certbot
sudo apt update
sudo apt install -y certbot

# Verify installation
certbot --version
```

### Obtain Certificate

**Standalone Mode:**
```bash
# Stop Nginx first
docker compose stop nginx

# Obtain certificate
sudo certbot certonly --standalone \
    -d api.mswms.example.com \
    -d www.mswms.example.com

# Restart Nginx
docker compose start nginx
```

**Webroot Mode:**
```bash
# Nginx must be running
# Configure webroot in Nginx

sudo certbot certonly --webroot \
    -w /var/www/html \
    -d api.mswms.example.com
```

### Certificate Location

```
/etc/letsencrypt/live/api.mswms.example.com/
├── fullchain.pem      # Full certificate chain
├── privkey.pem        # Private key
├── cert.pem           # Server certificate
└── chain.pem          # Intermediate certificate
```

### Copy to Docker

```bash
# Create SSL directory
mkdir -p docker/ssl

# Copy certificates
sudo cp /etc/letsencrypt/live/api.mswms.example.com/fullchain.pem docker/ssl/
sudo cp /etc/letsencrypt/live/api.mswms.example.com/privkey.pem docker/ssl/

# Set permissions
sudo chmod 644 docker/ssl/fullchain.pem
sudo chmod 600 docker/ssl/privkey.pem
```

### Auto-Renewal

```bash
# Test renewal
sudo certbot renew --dry-run

# Set up cron job
sudo crontab -e

# Add renewal job (runs daily at 3 AM)
0 3 * * * certbot renew --quiet --deploy-hook "docker compose restart nginx"

# Or use systemd timer (Ubuntu 20.04+)
sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer
```

## Traefik for Automatic SSL

### Traefik Configuration

**docker-compose.yml:**
```yaml
version: '3.8'

services:
  traefik:
    image: traefik:v2.10
    container_name: mswms-traefik
    restart: unless-stopped
    security_opt:
      - no-new-privileges:true
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
    build: .
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.mswms.rule=Host(`api.mswms.example.com`)"
      - "traefik.http.routers.mswms.entrypoints=websecure"
      - "traefik.http.routers.mswms.tls.certresolver=letsencrypt"
      - "traefik.http.routers.mswms.tls.domains[0].main=api.mswms.example.com"
      - "traefik.http.routers.mswms.middlewares=mswms-headers@file"
      - "traefik.http.services.mswms.loadbalancer.server.port=9000"
    networks:
      - mswms-network
    depends_on:
      - traefik

  nginx:
    image: nginx:alpine
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.mswms-static.rule=Host(`api.mswms.example.com`) && PathPrefix(`/static`)"
      - "traefik.http.routers.mswms-static.entrypoints=websecure"
      - "traefik.http.routers.mswms-static.tls.certresolver=letsencrypt"
      - "traefik.http.services.mswms-static.loadbalancer.server.port=80"
    networks:
      - mswms-network

networks:
  mswms-network:
    driver: bridge
```

**docker/traefik/traefik.yml:**
```yaml
# Traefik Configuration

# API and Dashboard
api:
  dashboard: true
  insecure: false

# Entry Points
entryPoints:
  web:
    address: ":80"
    http:
      redirections:
        entryPoint:
          to: websecure
          scheme: https
  websecure:
    address: ":443"

# Providers
providers:
  docker:
    endpoint: "unix:///var/run/docker.sock"
    exposedByDefault: false
    network: mswms-network
  file:
    filename: /etc/traefik/dynamic.yml
    watch: true

# Certificates (Let's Encrypt)
certificatesResolvers:
  letsencrypt:
    acme:
      email: admin@mswms.example.com
      storage: /etc/traefik/acme/acme.json
      httpChallenge:
        entryPoint: web
      caServer: "https://acme-v02.api.letsencrypt.org/directory"

# Logging
log:
  level: INFO

# Access Log
accessLog:
  filePath: "/var/log/traefik/access.log"
  bufferingSize: 100
```

**docker/traefik/dynamic.yml:**
```yaml
# Dynamic Configuration

http:
  middlewares:
    mswms-headers:
      headers:
        stsSeconds: 31536000
        stsIncludeSubdomains: true
        stsPreload: true
        forceSTSHeader: true
        frameDeny: true
        contentTypeNosniff: true
        browserXssFilter: true
        referrerPolicy: "strict-origin-when-cross-origin"
        permissionsPolicy: "geolocation=(), microphone=(), camera=()"
        customFrameOptionsValue: "SAMEORIGIN"
        customRequestHeaders:
          X-Forwarded-Proto: "https"

# TLS Options
tls:
  options:
    default:
      minVersion: VersionTLS12
      cipherSuites:
        - TLS_ECDHE_ECDSA_WITH_AES_128_GCM_SHA256
        - TLS_ECDHE_RSA_WITH_AES_128_GCM_SHA256
        - TLS_ECDHE_ECDSA_WITH_AES_256_GCM_SHA384
        - TLS_ECDHE_RSA_WITH_AES_256_GCM_SHA384
```

### Acme.json Setup

```bash
# Create ACME directory
mkdir -p docker/traefik/acme

# Create acme.json file
touch docker/traefik/acme/acme.json

# Set permissions (critical!)
chmod 600 docker/traefik/acme/acme.json
```

### Access Traefik Dashboard

```bash
# Add basic auth for dashboard
# Generate password: htpasswd -nb admin password
# Add to traefik.yml:

api:
  dashboard: true
  middlewares:
    - dashboard-auth

http:
  middlewares:
    dashboard-auth:
      basicAuth:
        users:
          - "admin:$apr1$xyz$hashed_password"
```

Access at: `https://traefik.mswms.example.com`

## Nginx Proxy Manager (Alternative)

### Docker Compose

```yaml
services:
  nginx-proxy-manager:
    image: jc21/nginx-proxy-manager:latest
    container_name: mswms-npm
    restart: unless-stopped
    ports:
      - "80:80"
      - "81:81"   # Admin UI
      - "443:443"
    volumes:
      - ./npm_data:/data
      - ./npm_letsencrypt:/etc/letsencrypt
    environment:
      - PUID=1000
      - PGID=1000
      - TZ=UTC
```

### Configuration via Web UI

1. Access admin UI: `http://your-server-ip:81`
2. Default login: `admin@example.com` / `changeme`
3. Add Proxy Host:
   - Domain: `api.mswms.example.com`
   - Forward Host: `app`
   - Forward Port: `9000`
4. Enable SSL:
   - Request new certificate
   - Force SSL
   - HTTP/2 support

## Self-Signed Certificates (Development)

### Generate Certificate

```bash
# Create SSL directory
mkdir -p docker/ssl

# Generate private key
openssl genrsa -out docker/ssl/privkey.pem 2048

# Generate certificate
openssl req -new -x509 \
    -key docker/ssl/privkey.pem \
    -out docker/ssl/fullchain.pem \
    -days 365 \
    -subj "/C=US/ST=State/L=City/O=MSWMS/CN=localhost"

# Set permissions
chmod 644 docker/ssl/fullchain.pem
chmod 600 docker/ssl/privkey.pem
```

### Trust Self-Signed Certificate

**macOS:**
```bash
# Add to keychain
sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain docker/ssl/fullchain.pem
```

**Windows:**
1. Double-click `fullchain.pem`
2. Install Certificate
3. Place in "Trusted Root Certification Authorities"

**Linux (Ubuntu):**
```bash
sudo cp docker/ssl/fullchain.pem /usr/local/share/ca-certificates/mswms.crt
sudo update-ca-certificates
```

## CloudFlare SSL

### Full SSL Mode

1. **In CloudFlare Dashboard:**
   - SSL/TLS → Overview → Full (strict)

2. **Origin Certificate:**
   - SSL/TLS → Origin Server → Create Certificate
   - Download certificate and key

3. **Docker Configuration:**
```yaml
volumes:
  - ./docker/ssl/cloudflare.pem:/etc/nginx/ssl/fullchain.pem:ro
  - ./docker/ssl/cloudflare-key.pem:/etc/nginx/ssl/privkey.pem:ro
```

### Automatic with CloudFlare DNS

**docker-compose.yml:**
```yaml
services:
  traefik:
    environment:
      - CF_DNS_API_TOKEN=your_cloudflare_api_token
    volumes:
      - ./docker/traefik/acme:/etc/traefik/acme
```

**traefik.yml:**
```yaml
certificatesResolvers:
  letsencrypt:
    acme:
      email: admin@mswms.example.com
      storage: /etc/traefik/acme/acme.json
      dnsChallenge:
        provider: cloudflare
        resolvers:
          - "1.1.1.1:53"
          - "1.0.0.1:53"
```

## SSL Configuration Best Practices

### Nginx SSL Settings

```nginx
# Modern SSL configuration
ssl_protocols TLSv1.2 TLSv1.3;
ssl_prefer_server_ciphers on;
ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384;
ssl_session_cache shared:SSL:10m;
ssl_session_timeout 1d;
ssl_session_tickets off;

# OCSP Stapling
ssl_stapling on;
ssl_stapling_verify on;
resolver 8.8.8.8 8.8.4.4 valid=300s;
resolver_timeout 5s;
```

### Security Headers

```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
add_header X-Frame-Options "DENY" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
```

## Testing and Verification

### Test SSL Configuration

```bash
# Test with curl
curl -I https://api.mswms.example.com

# Test SSL
curl -v https://api.mswms.example.com

# Check certificate
openssl s_client -connect api.mswms.example.com:443 -servername api.mswms.example.com

# Check expiration
openssl x509 -in docker/ssl/fullchain.pem -text -noout | grep "Not After"
```

### Online Tools

- **SSL Labs**: https://www.ssllabs.com/ssltest/
- **Why No Padlock**: https://www.whynopadlock.com/
- **SSL Checker**: https://www.sslchecker.com/sslchecker

### Check Certificate Expiry

```bash
#!/bin/bash
# check-ssl-expiry.sh

DOMAIN=$1
EXPIRY=$(echo | openssl s_client -connect $DOMAIN:443 -servername $DOMAIN 2>/dev/null | openssl x509 -noout -enddate)

echo "Certificate for $DOMAIN expires: $EXPIRY"

# Check if expiring within 30 days
EXPIRY_EPOCH=$(date -d "$(echo $EXPIRY | cut -d= -f2)" +%s)
CURRENT_EPOCH=$(date +%s)
DAYS_LEFT=$(( ($EXPIRY_EPOCH - $CURRENT_EPOCH) / 86400 ))

if [ $DAYS_LEFT -lt 30 ]; then
    echo "WARNING: Certificate expires in $DAYS_LEFT days!"
    exit 1
else
    echo "OK: Certificate valid for $DAYS_LEFT days"
    exit 0
fi
```

## Troubleshooting

### Certificate Issues

**Certificate Not Trusted:**
```bash
# Verify certificate chain
openssl verify -CAfile /etc/letsencrypt/live/api.mswms.example.com/chain.pem \
    /etc/letsencrypt/live/api.mswms.example.com/fullchain.pem

# Check intermediate certificates
cat /etc/letsencrypt/live/api.mswms.example.com/fullchain.pem
```

**Mixed Content Warnings:**
```bash
# Check for HTTP resources
curl -s https://api.mswms.example.com | grep -i "http://"

# Fix in application - use relative URLs or HTTPS
```

**SSL Handshake Failed:**
```bash
# Check SSL protocols
openssl s_client -connect api.mswms.example.com:443 -tls1_2

# Check cipher suites
nmap --script ssl-enum-ciphers -p 443 api.mswms.example.com
```

### Renewal Issues

```bash
# Force renewal
sudo certbot renew --force-renewal

# Check logs
sudo tail -f /var/log/letsencrypt/letsencrypt.log

# Test hooks
sudo certbot renew --dry-run --deploy-hook "echo 'Deploy hook executed'"
```

---

**Previous Section**: [← Nginx Reverse Proxy](07-nginx-reverse-proxy.md)  
**Next Section**: [CI/CD with Docker →](11-ci-cd-docker.md)
