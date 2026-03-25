# SSL/TLS Configuration and Security

## Overview

This document provides comprehensive instructions for configuring SSL/TLS certificates, security headers, and HTTPS enforcement for MSWMS. Proper SSL/TLS configuration is essential for data security, compliance, and user trust.

## SSL/TLS Certificate Options

### Certificate Types

| Type | Validation | Use Case | Cost |
|------|------------|----------|------|
| Domain Validated (DV) | Domain ownership | Staging, internal apps | Free - $ |
| Organization Validated (OV) | Organization verification | Production business apps | $$ |
| Extended Validation (EV) | Extensive verification | Enterprise, financial | $$$ |
| Wildcard (*.domain.com) | Domain ownership | Multiple subdomains | $$ |
| Multi-Domain (SAN) | Multiple domains | Multiple domains | $$ |

### Certificate Providers

**Free:**
- Let's Encrypt (Recommended for staging)
- ZeroSSL
- CloudFlare Origin CA

**Commercial:**
- DigiCert
- Sectigo (Comodo)
- GlobalSign
- GoDaddy

## Let's Encrypt Installation

### Install Certbot

**Ubuntu/Debian:**
```bash
# Install Certbot
sudo apt update
sudo apt install -y certbot python3-certbot-nginx

# For Apache
sudo apt install -y python3-certbot-apache
```

**CentOS/RHEL:**
```bash
# Install EPEL repository
sudo yum install -y epel-release

# Install Certbot
sudo yum install -y certbot python3-certbot-nginx

# For Apache
sudo yum install -y python3-certbot-apache
```

### Obtain SSL Certificate

**Nginx - Staging:**
```bash
# Obtain certificate
sudo certbot --nginx -d staging.mswms.example.com

# Or manual method
sudo certbot certonly --webroot -w /var/www/mswms/public -d staging.mswms.example.com
```

**Nginx - Production:**
```bash
# Obtain certificate for multiple domains
sudo certbot --nginx \
    -d api.mswms.example.com \
    -d app.mswms.example.com \
    -d www.mswms.example.com

# Wildcard certificate (requires DNS challenge)
sudo certbot certonly --manual --preferred-challenges dns \
    -d mswms.example.com \
    -d *.mswms.example.com
```

**Apache:**
```bash
# Obtain certificate
sudo certbot --apache -d staging.mswms.example.com
```

### Automatic Renewal

**Test Renewal:**
```bash
sudo certbot renew --dry-run
```

**Configure Auto-Renewal:**
```bash
# Edit crontab
sudo crontab -e

# Add renewal job (runs daily at 3 AM)
0 3 * * * /usr/bin/certbot renew --quiet --deploy-hook "systemctl reload nginx"
```

**Verify Cron Job:**
```bash
sudo systemctl status certbot.timer
```

## Manual SSL Certificate Installation

### Generate CSR (Certificate Signing Request)

```bash
# Create directory for certificates
sudo mkdir -p /etc/nginx/ssl/mswms
cd /etc/nginx/ssl/mswms

# Generate private key
sudo openssl genrsa -out mswms.key 4096

# Generate CSR
sudo openssl req -new -key mswms.key -out mswms.csr \
    -subj "/C=US/ST=State/L=City/O=Organization/OU=IT/CN=api.mswms.example.com"
```

### Submit CSR to CA

1. Copy CSR content:
```bash
cat mswms.csr
```

2. Submit to Certificate Authority (DigiCert, Sectigo, etc.)

3. Download issued certificates:
   - Primary certificate: `mswms.crt`
   - Intermediate certificate: `ca-bundle.crt`

### Install Certificate

**Nginx:**
```bash
# Create fullchain certificate
cat mswms.crt ca-bundle.crt > fullchain.pem

# Set permissions
sudo chmod 600 mswms.key
sudo chmod 644 fullchain.pem
sudo chown root:root mswms.key fullchain.pem
```

**Apache:**
```bash
# Copy certificates
sudo cp mswms.crt /etc/ssl/certs/
sudo cp ca-bundle.crt /etc/ssl/certs/
sudo cp mswms.key /etc/ssl/private/

# Set permissions
sudo chmod 600 /etc/ssl/private/mswms.key
sudo chmod 644 /etc/ssl/certs/mswms.crt
sudo chmod 644 /etc/ssl/certs/ca-bundle.crt
```

## Nginx SSL Configuration

### Modern SSL Configuration

**Edit Nginx Site Config:**
```bash
sudo nano /etc/nginx/sites-available/mswms
```

**SSL Configuration Block:**
```nginx
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name api.mswms.example.com;

    # SSL Certificate Paths
    ssl_certificate /etc/letsencrypt/live/api.mswms.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.mswms.example.com/privkey.pem;

    # SSL Protocol Configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384;

    # SSL Session Configuration
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;

    # OCSP Stapling
    ssl_stapling on;
    ssl_stapling_verify on;
    resolver 8.8.8.8 8.8.4.4 valid=300s;
    resolver_timeout 5s;

    # Diffie-Hellman Parameter
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    # ... rest of configuration
}
```

### Generate DH Parameters (Optional but Recommended)

```bash
# Generate DH parameters (takes 10-30 minutes)
sudo openssl dhparam -out /etc/letsencrypt/ssl-dhparams.pem 4096

# Or use pre-generated parameters (less secure but faster)
sudo curl -o /etc/letsencrypt/ssl-dhparams.pem https://raw.githubusercontent.com/cloudflare/sslconfig/master/dhparams-4096.pem
```

### HTTP to HTTPS Redirect

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name api.mswms.example.com;

    # Redirect all HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

# Or redirect with www handling
server {
    listen 80;
    server_name www.api.mswms.example.com;
    return 301 https://api.mswms.example.com$request_uri;
}
```

## Apache SSL Configuration

### Enable SSL Module

```bash
sudo a2enmod ssl
sudo a2enmod headers
sudo systemctl restart apache2
```

### SSL Virtual Host Configuration

```apache
<VirtualHost *:80>
    ServerName api.mswms.example.com
    
    # Redirect to HTTPS
    Redirect permanent / https://api.mswms.example.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName api.mswms.example.com
    DocumentRoot /var/www/mswms/public

    # SSL Engine
    SSLEngine on

    # SSL Certificate
    SSLCertificateFile /etc/letsencrypt/live/api.mswms.example.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/api.mswms.example.com/privkey.pem

    # SSL Protocol Configuration
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384
    SSLHonorCipherOrder on
    SSLCompression off

    # SSL Session Cache
    SSLSessionCache shmcb:/var/run/apache2/ssl_scache(512000)
    SSLSessionTimeout 300

    # OCSP Stapling
    SSLUseStapling on
    SSLStaplingCache "shmcb:logs/ssl_stapling(32768)"

    # Security Headers
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
    Header always set X-Frame-Options "DENY"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';"

    # ... rest of configuration
</VirtualHost>
```

## Security Headers Configuration

### Comprehensive Security Headers

**Nginx:**
```nginx
# HSTS (HTTP Strict Transport Security)
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;

# X-Frame-Options (Clickjacking Protection)
add_header X-Frame-Options "DENY" always;

# X-Content-Type-Options (MIME Sniffing Protection)
add_header X-Content-Type-Options "nosniff" always;

# X-XSS-Protection (XSS Filter)
add_header X-XSS-Protection "1; mode=block" always;

# Referrer-Policy
add_header Referrer-Policy "strict-origin-when-cross-origin" always;

# Content-Security-Policy
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' https:; frame-ancestors 'none'; base-uri 'self'; form-action 'self';" always;

# Permissions-Policy (formerly Feature-Policy)
add_header Permissions-Policy "geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=()" always;

# Cross-Origin Policies
add_header Cross-Origin-Opener-Policy "same-origin" always;
add_header Cross-Origin-Embedder-Policy "require-corp" always;
add_header Cross-Origin-Resource-Policy "same-origin" always;

# Cache-Control for sensitive pages
location /api/ {
    add_header Cache-Control "no-store, no-cache, must-revalidate, proxy-revalidate" always;
    add_header Pragma "no-cache" always;
    add_header Expires "0" always;
}
```

**Apache:**
```apache
# HSTS
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"

# X-Frame-Options
Header always set X-Frame-Options "DENY"

# X-Content-Type-Options
Header always set X-Content-Type-Options "nosniff"

# X-XSS-Protection
Header always set X-XSS-Protection "1; mode=block"

# Referrer-Policy
Header always set Referrer-Policy "strict-origin-when-cross-origin"

# Content-Security-Policy
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' https:; frame-ancestors 'none'; base-uri 'self'; form-action 'self';"

# Permissions-Policy
Header always set Permissions-Policy "geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=()"

# Cross-Origin Policies
Header always set Cross-Origin-Opener-Policy "same-origin"
Header always set Cross-Origin-Embedder-Policy "require-corp"
Header always set Cross-Origin-Resource-Policy "same-origin"
```

## SSL/TLS Best Practices

### Protocol Configuration

**Recommended Settings:**
```nginx
# Enable TLS 1.2 and 1.3 only
ssl_protocols TLSv1.2 TLSv1.3;

# Disable older protocols
# ssl_protocols SSLv3 TLSv1 TLSv1.1;  # DON'T USE
```

### Cipher Suite Configuration

**Modern Cipher Suite:**
```nginx
ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384;
ssl_prefer_server_ciphers on;
```

### Session Configuration

```nginx
# Enable session caching
ssl_session_cache shared:SSL:10m;
ssl_session_timeout 1d;
ssl_session_tickets off;  # Disable session tickets for forward secrecy
```

## Certificate Monitoring

### Check Certificate Expiration

```bash
# Check expiration date
sudo openssl x509 -in /etc/letsencrypt/live/api.mswms.example.com/fullchain.pem -text -noout | grep "Not After"

# Check all certificates
for cert in /etc/letsencrypt/live/*/fullchain.pem; do
    echo "Certificate: $cert"
    openssl x509 -in "$cert" -noout -dates
    echo "---"
done
```

### Certificate Expiry Alert Script

```bash
#!/bin/bash
# /usr/local/bin/check-ssl-expiry.sh

CERT_DIR="/etc/letsencrypt/live"
WARNING_DAYS=30
CRITICAL_DAYS=7

for domain in $CERT_DIR/*/fullchain.pem; do
    domain_name=$(dirname $domain | xargs basename)
    expiry_date=$(openssl x509 -in $domain -noout -enddate | cut -d= -f2)
    expiry_epoch=$(date -d "$expiry_date" +%s)
    current_epoch=$(date +%s)
    days_left=$(( ($expiry_epoch - $current_epoch) / 86400 ))

    if [ $days_left -le $CRITICAL_DAYS ]; then
        echo "CRITICAL: $domain_name expires in $days_left days!"
        # Send alert email
        echo "SSL Certificate for $domain_name expires in $days_left days" | mail -s "CRITICAL: SSL Expiry Alert" admin@example.com
    elif [ $days_left -le $WARNING_DAYS ]; then
        echo "WARNING: $domain_name expires in $days_left days!"
        # Send alert email
        echo "SSL Certificate for $domain_name expires in $days_left days" | mail -s "WARNING: SSL Expiry Alert" admin@example.com
    else
        echo "OK: $domain_name expires in $days_left days"
    fi
done
```

### Schedule Certificate Check

```bash
# Edit crontab
sudo crontab -e

# Add daily check at 9 AM
0 9 * * * /usr/local/bin/check-ssl-expiry.sh
```

## SSL/TLS Testing and Verification

### Online Testing Tools

1. **SSL Labs SSL Test**: https://www.ssllabs.com/ssltest/
2. **Qualys SSL Labs**: https://www.ssllabs.com/ssltest/analyze.html
3. **DigiCert SSL Installation Diagnostics**: https://www.digicert.com/help/
4. **Why No Padlock?**: https://www.whynopadlock.com/

### Command Line Testing

```bash
# Test SSL connection
openssl s_client -connect api.mswms.example.com:443 -servername api.mswms.example.com

# Test TLS 1.2
openssl s_client -connect api.mswms.example.com:443 -tls1_2

# Test TLS 1.3
openssl s_client -connect api.mswms.example.com:443 -tls1_3

# Check certificate chain
openssl s_client -showcerts -connect api.mswms.example.com:443

# Check supported protocols
nmap --script ssl-enum-ciphers -p 443 api.mswms.example.com
```

### Verify Security Headers

```bash
# Check security headers
curl -I https://api.mswms.example.com

# Expected headers:
# Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
# X-Frame-Options: DENY
# X-Content-Type-Options: nosniff
# X-XSS-Protection: 1; mode=block
# Content-Security-Policy: ...
```

## Troubleshooting

### Common SSL Issues

#### 1. Certificate Not Trusted
```bash
# Check certificate chain
openssl s_client -connect api.mswms.example.com:443 -showcerts

# Verify certificate
openssl verify -CAfile /etc/letsencrypt/live/api.mswms.example.com/chain.pem \
    /etc/letsencrypt/live/api.mswms.example.com/fullchain.pem
```

#### 2. Mixed Content Warnings
```bash
# Check for HTTP resources
curl -s https://api.mswms.example.com | grep -i "http://"

# Fix in application code - use relative URLs or HTTPS
```

#### 3. HSTS Issues
```bash
# Clear HSTS in browser
# Chrome: chrome://net-internals/#hsts
# Firefox: about:preferences#privacy

# Test without HSTS (development only)
# Use different subdomain or disable temporarily
```

#### 4. Certificate Expiry
```bash
# Renew certificate
sudo certbot renew --force-renewal

# Reload web server
sudo systemctl reload nginx
# or
sudo systemctl restart apache2
```

#### 5. OCSP Stapling Issues
```bash
# Test OCSP stapling
openssl s_client -connect api.mswms.example.com:443 -status

# Check resolver configuration
# Ensure resolver is set in nginx.conf
```

## SSL Configuration Checklist

### Pre-Deployment
- [ ] SSL certificate obtained
- [ ] Private key secured (chmod 600)
- [ ] Certificate chain configured
- [ ] DH parameters generated
- [ ] Web server SSL module enabled

### Configuration
- [ ] TLS 1.2 and 1.3 enabled only
- [ ] Strong cipher suites configured
- [ ] Session caching enabled
- [ ] OCSP stapling enabled
- [ ] Security headers configured
- [ ] HTTP to HTTPS redirect configured

### Testing
- [ ] SSL Labs test score A or A+
- [ ] All security headers present
- [ ] No mixed content warnings
- [ ] Certificate chain valid
- [ ] HSTS working correctly

### Monitoring
- [ ] Certificate expiry monitoring enabled
- [ ] Auto-renewal configured
- [ ] Alert system in place
- [ ] Regular security audits scheduled

---

**Previous Section**: [← Web Server Setup](05-web-server-setup.md)  
**Next Section**: [Deployment Steps →](07-deployment-steps.md)
