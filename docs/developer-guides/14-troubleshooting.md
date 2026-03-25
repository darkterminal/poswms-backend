# Troubleshooting Guide

## Overview

This document provides comprehensive troubleshooting procedures for common issues encountered during MSWMS deployment and operation. Use this guide to diagnose and resolve problems quickly.

## Diagnostic Tools

### System Diagnostics

**Check System Status:**
```bash
# System overview
htop

# Disk usage
df -h

# Memory usage
free -h

# Network connections
netstat -tulpn

# Process list
ps aux | grep -E 'nginx|php|redis|postgres'

# System load
uptime

# I/O statistics
iostat -x 1 5
```

### Application Diagnostics

**Laravel Diagnostics:**
```bash
# Check application status
php artisan about

# Check environment
php artisan env

# Check routes
php artisan route:list

# Check configuration
php artisan config:clear && php artisan config:cache

# Check cache
php artisan cache:table
php artisan cache:clear

# Check database
php artisan migrate:status
php artisan db:show

# Check queue
php artisan queue:monitor
php artisan queue:failed

# Check storage
php artisan storage:link
```

### Log Analysis

**View Recent Logs:**
```bash
# Application logs
tail -100 /var/www/mswms/storage/logs/laravel.log

# Follow logs in real-time
tail -f /var/www/mswms/storage/logs/laravel.log

# Search for errors
grep -i "error" /var/www/mswms/storage/logs/laravel.log

# Search for specific error
grep -i "connection refused" /var/www/mswms/storage/logs/laravel.log

# Nginx logs
tail -100 /var/log/nginx/mswms_production_error.log

# PHP-FPM logs
tail -100 /var/log/php-fpm/www-error.log

# Supervisor logs
tail -100 /var/log/supervisor/mswms-worker.log
```

## Common Issues and Solutions

### 1. Application Errors

#### 500 Internal Server Error

**Symptoms:**
- Application returns 500 status code
- Error in Nginx logs
- Application logs show exceptions

**Diagnosis:**
```bash
# Check application logs
tail -f /var/www/mswms/storage/logs/laravel.log

# Check Nginx error logs
tail -f /var/log/nginx/mswms_production_error.log

# Check PHP-FPM logs
tail -f /var/log/php-fpm/www-error.log
```

**Solutions:**

**A. Permission Issues:**
```bash
# Fix storage permissions
cd /var/www/mswms
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

**B. Configuration Cache:**
```bash
# Clear configuration cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Regenerate caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**C. Missing Dependencies:**
```bash
# Install dependencies
composer install --no-dev --optimize-autoloader

# Regenerate autoload
composer dump-autoload --optimize
```

**D. Database Connection:**
```bash
# Test database connection
php artisan tinker --execute="DB::connection()->getPdo();"

# Check .env configuration
grep DB_ .env
```

#### 502 Bad Gateway

**Symptoms:**
- Nginx returns 502 error
- PHP-FPM not responding

**Diagnosis:**
```bash
# Check PHP-FPM status
sudo systemctl status php8.3-fpm

# Check PHP-FPM logs
tail -f /var/log/php-fpm/www-error.log

# Check socket
ls -la /run/php/php8.3-fpm.sock
```

**Solutions:**

**A. Restart PHP-FPM:**
```bash
sudo systemctl restart php8.3-fpm
sudo systemctl status php8.3-fpm
```

**B. Check PHP-FPM Configuration:**
```bash
# Edit pool configuration
sudo nano /etc/php/8.3/fpm/pool.d/www.conf

# Verify socket configuration
# listen = /run/php/php8.3-fpm.sock
# listen.owner = www-data
# listen.group = www-data
```

**C. Increase PHP-FPM Resources:**
```ini
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 10
pm.max_spare_servers = 30
```

#### 503 Service Unavailable

**Symptoms:**
- Application in maintenance mode
- All requests return 503

**Solutions:**

**A. Disable Maintenance Mode:**
```bash
php artisan up
```

**B. Check Maintenance File:**
```bash
# Check if maintenance file exists
ls -la storage/framework/down

# Remove if stuck
rm storage/framework/down
```

### 2. Database Issues

#### Connection Refused

**Symptoms:**
- PDOException: Connection refused
- Cannot connect to database

**Diagnosis:**
```bash
# Test database connection
psql -h localhost -U mswms_prod_user mswms_production

# Check PostgreSQL status
sudo systemctl status postgresql

# Check if PostgreSQL is listening
netstat -tulpn | grep 5432
```

**Solutions:**

**A. Start PostgreSQL:**
```bash
sudo systemctl start postgresql
sudo systemctl enable postgresql
```

**B. Check pg_hba.conf:**
```bash
# Edit host-based authentication
sudo nano /etc/postgresql/15/main/pg_hba.conf

# Add application server IP
host    mswms_production    mswms_prod_user    192.168.1.0/24    scram-sha-256

# Reload PostgreSQL
sudo systemctl reload postgresql
```

**C. Check PostgreSQL Configuration:**
```bash
# Edit postgresql.conf
sudo nano /etc/postgresql/15/main/postgresql.conf

# Ensure listening on correct address
listen_addresses = '*'
port = 5432
```

#### Too Many Connections

**Symptoms:**
- FATAL: too many connections for role
- Connection pool exhausted

**Diagnosis:**
```bash
# Check current connections
psql -U postgres -c "SELECT count(*) FROM pg_stat_activity;"

# Check max connections
psql -U postgres -c "SHOW max_connections;"

# Check active connections by database
psql -U postgres -c "SELECT datname, count(*) FROM pg_stat_activity GROUP BY datname;"
```

**Solutions:**

**A. Kill Idle Connections:**
```sql
SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE state = 'idle'
AND query_start < NOW() - INTERVAL '30 minutes'
AND datname = 'mswms_production';
```

**B. Increase Max Connections:**
```ini
# postgresql.conf
max_connections = 300

# Reload PostgreSQL
sudo systemctl reload postgresql
```

**C. Use Connection Pooling:**
```php
// config/database.php
'pgsql' => [
    'driver' => 'pgsql',
    'pool' => 'pgbouncer',
    'host' => 'localhost',
    'port' => '6432',  // PgBouncer port
],
```

#### Slow Queries

**Symptoms:**
- Application response time increased
- Database CPU high

**Diagnosis:**
```bash
# Enable slow query logging
psql -U postgres -c "SHOW log_min_duration_statement;"

# Check slow queries
psql -U postgres -c "
SELECT query, calls, total_exec_time, mean_exec_time
FROM pg_stat_statements
ORDER BY mean_exec_time DESC
LIMIT 10;
"

# Check current queries
psql -U postgres -c "
SELECT pid, now() - pg_stat_activity.query_start AS duration, query
FROM pg_stat_activity
WHERE state != 'idle'
ORDER BY duration DESC;
"
```

**Solutions:**

**A. Add Missing Indexes:**
```sql
-- Check for missing indexes
EXPLAIN ANALYZE SELECT * FROM orders WHERE tenant_id = 'uuid' AND status = 'pending';

-- Add index
CREATE INDEX idx_orders_tenant_status ON orders(tenant_id, status);
```

**B. Optimize Query:**
```php
// Before (N+1 query)
$orders = Order::all();
foreach ($orders as $order) {
    echo $order->customer->name;
}

// After (eager loading)
$orders = Order::with('customer')->get();
```

**C. Increase Work Memory:**
```ini
# postgresql.conf
work_mem = 128MB
maintenance_work_mem = 512MB

# Reload PostgreSQL
sudo systemctl reload postgresql
```

### 3. Redis Issues

#### Connection Failed

**Symptoms:**
- Redis connection refused
- Cache operations failing

**Diagnosis:**
```bash
# Check Redis status
sudo systemctl status redis

# Test Redis connection
redis-cli -a your_password ping

# Check Redis logs
tail -f /var/log/redis/redis-server.log
```

**Solutions:**

**A. Start Redis:**
```bash
sudo systemctl start redis
sudo systemctl enable redis
```

**B. Check Redis Configuration:**
```bash
# Edit Redis configuration
sudo nano /etc/redis/redis.conf

# Verify settings
bind 127.0.0.1
port 6379
requirepass your_password

# Restart Redis
sudo systemctl restart redis
```

**C. Check Firewall:**
```bash
# Allow Redis from application
sudo ufw allow from 127.0.0.1 to any port 6379
```

#### Memory Limit Exceeded

**Symptoms:**
- OOM command not allowed
- Keys being evicted

**Diagnosis:**
```bash
# Check memory usage
redis-cli -a your_password INFO memory

# Check eviction policy
redis-cli -a your_password CONFIG GET maxmemory-policy

# Check memory limit
redis-cli -a your_password CONFIG GET maxmemory
```

**Solutions:**

**A. Increase Memory Limit:**
```bash
redis-cli -a your_password CONFIG SET maxmemory 4gb
redis-cli -a your_password CONFIG SET maxmemory-policy allkeys-lru
```

**B. Clear Old Keys:**
```bash
# Find large keys
redis-cli -a your_password --bigkeys

# Delete specific pattern
redis-cli -a your_password KEYS "pattern:*" | xargs redis-cli -a your_password DEL
```

### 4. Queue Issues

#### Jobs Not Processing

**Symptoms:**
- Queue size increasing
- Jobs stuck in pending state

**Diagnosis:**
```bash
# Check queue size
php artisan queue:monitor redis

# Check worker status
sudo supervisorctl status mswms-worker:*

# Check worker logs
tail -f /var/log/supervisor/mswms-worker.log

# Check failed jobs
php artisan queue:failed
```

**Solutions:**

**A. Restart Workers:**
```bash
# Graceful restart
php artisan queue:restart

# Force restart
sudo supervisorctl restart mswms-worker:*

# Check status
sudo supervisorctl status
```

**B. Increase Worker Count:**
```ini
# /etc/supervisor/conf.d/mswms-worker.conf
numprocs=8  # Increase from 4 to 8

# Update supervisor
sudo supervisorctl reread
sudo supervisorctl update
```

**C. Clear Queue:**
```bash
# Clear specific queue
php artisan queue:clear redis --queue=emails

# Or manually
redis-cli -a your_password DEL queues:emails
```

#### Failed Jobs Increasing

**Symptoms:**
- Failed jobs table growing
- Workers logging errors

**Diagnosis:**
```bash
# List failed jobs
php artisan queue:failed

# View failed job
php artisan tinker --execute="DB::table('failed_jobs')->latest()->first();"

# Check worker logs
grep -i "failed" /var/log/supervisor/mswms-worker.log
```

**Solutions:**

**A. Retry Failed Jobs:**
```bash
# Retry all failed jobs
php artisan queue:retry all

# Retry specific job
php artisan queue:retry <ID>
```

**B. Increase Retry Attempts:**
```php
// In Job class
public int $tries = 5;
public int $backoff = [10, 30, 60, 120, 300];
```

**C. Flush Old Failed Jobs:**
```bash
php artisan queue:flush
```

### 5. SSL/TLS Issues

#### Certificate Expired

**Symptoms:**
- Browser SSL warnings
- curl SSL verification failed

**Diagnosis:**
```bash
# Check certificate expiration
sudo openssl x509 -in /etc/letsencrypt/live/api.mswms.example.com/fullchain.pem -text -noout | grep "Not After"

# Test SSL
curl -I https://api.mswms.example.com
```

**Solutions:**

**A. Renew Certificate:**
```bash
# Renew Let's Encrypt certificate
sudo certbot renew --force-renewal

# Reload Nginx
sudo systemctl reload nginx
```

**B. Manual Certificate Installation:**
```bash
# Copy new certificate
sudo cp new_cert.crt /etc/letsencrypt/live/api.mswms.example.com/fullchain.pem
sudo cp new_key.key /etc/letsencrypt/live/api.mswms.example.com/privkey.pem

# Reload Nginx
sudo systemctl reload nginx
```

#### Mixed Content Warnings

**Symptoms:**
- Browser console shows mixed content errors
- Padlock icon not showing

**Diagnosis:**
```bash
# Check for HTTP resources
curl -s https://api.mswms.example.com | grep -i "http://"
```

**Solutions:**

**A. Force HTTPS in Application:**
```php
// AppServiceProvider.php
public function boot(): void
{
    if (env('APP_ENV') === 'production') {
        URL::forceScheme('https');
    }
}
```

**B. Update Asset URLs:**
```blade
{{-- Before --}}
<img src="http://example.com/image.jpg">

{{-- After --}}
<img src="{{ asset('image.jpg') }}">
```

### 6. Performance Issues

#### High CPU Usage

**Symptoms:**
- Server load average high
- Slow response times

**Diagnosis:**
```bash
# Check CPU usage
top -bn1 | grep "Cpu(s)"

# Check process CPU usage
ps aux --sort=-%cpu | head -10

# Check PHP-FPM processes
ps aux | grep php-fpm | wc -l
```

**Solutions:**

**A. Reduce PHP-FPM Workers:**
```ini
pm.max_children = 20  # Reduce from 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 15

# Restart PHP-FPM
sudo systemctl restart php8.3-fpm
```

**B. Optimize Slow Queries:**
```bash
# Identify slow queries
tail -100 /var/log/postgresql/postgresql-15-main.log | grep "duration:"

# Add indexes or optimize queries
```

**C. Enable Caching:**
```bash
# Clear and warm cache
php artisan cache:clear
php artisan config:cache
php artisan route:cache
```

#### High Memory Usage

**Symptoms:**
- Memory usage near 100%
- OOM killer active

**Diagnosis:**
```bash
# Check memory usage
free -h

# Check process memory
ps aux --sort=-%mem | head -10

# Check for memory leaks
tail -100 /var/log/syslog | grep -i "oom"
```

**Solutions:**

**A. Reduce Worker Memory:**
```ini
# PHP-FPM
php_admin_value[memory_limit] = 256M

# Queue workers
command=php /var/www/mswms/artisan queue:work redis --memory=256
```

**B. Restart Services:**
```bash
# Restart PHP-FPM
sudo systemctl restart php8.3-fpm

# Restart queue workers
sudo supervisorctl restart mswms-worker:*
```

## Emergency Procedures

### Complete Service Outage

**Immediate Actions:**
```bash
# 1. Check all services
sudo systemctl status nginx
sudo systemctl status php8.3-fpm
sudo systemctl status postgresql
sudo systemctl status redis
sudo supervisorctl status

# 2. Check logs
tail -100 /var/log/nginx/mswms_production_error.log
tail -100 /var/www/mswms/storage/logs/laravel.log

# 3. Enable maintenance mode
php artisan down --secret="emergency_maintenance"

# 4. Restart all services
sudo systemctl restart nginx
sudo systemctl restart php8.3-fpm
sudo systemctl restart postgresql
sudo systemctl restart redis
sudo supervisorctl restart all

# 5. Test health endpoint
curl https://api.mswms.example.com/api/health

# 6. If still failing, rollback
git checkout previous-tag
php artisan migrate:rollback
php artisan up
```

### Data Corruption

**Immediate Actions:**
```bash
# 1. Stop all writes
php artisan down

# 2. Identify corruption
psql -U postgres -d mswms_production -c "SELECT * FROM pg_stat_activity;"

# 3. Restore from backup
LATEST_BACKUP=$(ls -t /var/backups/mswms/*.sql.gz | head -1)
gunzip -c $LATEST_BACKUP | psql -U mswms_prod_user mswms_production

# 4. Verify restore
php artisan tinker --execute="echo DB::table('users')->count();"

# 5. Bring up
php artisan up
```

## Getting Help

### Support Resources

**Internal:**
- System Administrator: [Contact]
- Database Administrator: [Contact]
- Development Team: [Contact]

**External:**
- Laravel Documentation: https://laravel.com/docs
- PostgreSQL Documentation: https://www.postgresql.org/docs/
- Redis Documentation: https://redis.io/documentation
- Nginx Documentation: https://nginx.org/en/docs/

### Escalation Procedure

1. **Level 1**: Check this troubleshooting guide
2. **Level 2**: Contact on-call engineer
3. **Level 3**: Escalate to technical lead
4. **Level 4**: Engage external support/vendor

---

**Previous Section**: [← CI/CD Pipeline](13-ci-cd-pipeline.md)  
**Next Section**: [Maintenance Procedures →](15-maintenance-procedures.md)
