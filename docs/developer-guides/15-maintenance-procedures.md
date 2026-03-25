# Maintenance Procedures

## Overview

This document outlines regular maintenance tasks and procedures for MSWMS. Regular maintenance ensures system stability, security, and optimal performance.

## Daily Maintenance Tasks

### System Health Checks

**Morning Checklist:**
```bash
# 1. Check system status
uptime
df -h
free -h

# 2. Check service status
sudo systemctl status nginx
sudo systemctl status php8.3-fpm
sudo systemctl status postgresql
sudo systemctl status redis
sudo supervisorctl status

# 3. Check application health
curl -s https://api.mswms.example.com/api/health | jq

# 4. Check error logs
tail -50 /var/www/mswms/storage/logs/laravel.log | grep -i error
tail -50 /var/log/nginx/mswms_production_error.log

# 5. Check queue status
php artisan queue:monitor redis
php artisan queue:failed | head -10

# 6. Check disk space
df -h /var
df -h /var/backups

# 7. Check backup status
ls -lht /var/backups/mswms/ | head -5
```

### Log Rotation Check

**Verify Logs Rotated:**
```bash
# Check logrotate status
sudo cat /var/lib/logrotate/status

# Check log sizes
du -sh /var/www/mswms/storage/logs/*
du -sh /var/log/nginx/*
du -sh /var/log/supervisor/*

# Force rotation if needed
sudo logrotate -f /etc/logrotate.d/mswms-laravel
```

## Weekly Maintenance Tasks

### Database Maintenance

**PostgreSQL:**
```bash
# 1. Vacuum and analyze
psql -U postgres -d mswms_production -c "VACUUM ANALYZE;"

# 2. Check database size
psql -U postgres -c "SELECT pg_size_pretty(pg_database_size('mswms_production'));"

# 3. Check table sizes
psql -U postgres -d mswms_production -c "
SELECT 
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size
FROM pg_tables
WHERE schemaname = 'public'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;
"

# 4. Check for bloated tables
psql -U postgres -d mswms_production -c "
SELECT 
    schemaname || '.' || relname AS table,
    pg_size_pretty(pg_total_relation_size(relid)) AS total_size,
    n_dead_tup AS dead_tuples
FROM pg_stat_user_tables
ORDER BY n_dead_tup DESC
LIMIT 10;
"

# 5. Clean up old data (if applicable)
psql -U postgres -d mswms_production -c "
DELETE FROM failed_jobs WHERE failed_at < NOW() - INTERVAL '30 days';
DELETE FROM jobs WHERE created_at < NOW() - INTERVAL '7 days';
"
```

**MySQL:**
```bash
# 1. Optimize tables
mysql -U root -p -e "
SELECT CONCAT('OPTIMIZE TABLE ', table_schema, '.', table_name, ';')
FROM information_schema.tables
WHERE table_schema = 'mswms_production'
AND table_type = 'BASE TABLE';
"

# 2. Check database size
mysql -U root -p -e "
SELECT 
    table_schema AS 'Database',
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.tables
GROUP BY table_schema;
"

# 3. Analyze tables
mysql -U root -p mswms_production -e "ANALYZE TABLE users, orders, products;"
```

### Cache Maintenance

**Redis:**
```bash
# 1. Check memory usage
redis-cli -a your_password INFO memory

# 2. Check keyspace
redis-cli -a your_password INFO keyspace

# 3. Remove expired keys
redis-cli -a your_password MEMORY DOCTOR

# 4. Check slow log
redis-cli -a your_password SLOWLOG GET 10

# 5. Clear old cache tags (Laravel)
php artisan cache:clear

# 6. Warm important caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Queue Maintenance

```bash
# 1. Check failed jobs
php artisan queue:failed

# 2. Retry failed jobs
php artisan queue:retry all

# 3. Flush old failed jobs
php artisan queue:flush

# 4. Monitor queue sizes
php artisan queue:monitor redis

# 5. Clear stuck queues (if needed)
php artisan queue:clear redis --queue=emails
```

### Security Updates

**System Updates:**
```bash
# 1. Check for security updates
sudo apt list --upgradable | grep -i security

# 2. Install security updates
sudo apt update
sudo apt upgrade --dry-run  # Preview
sudo apt upgrade -y         # Install

# 3. Restart services if kernel updated
sudo systemctl daemon-reexec

# 4. Check for PHP updates
php -v

# 5. Check for Composer updates
composer --version
```

**Application Dependencies:**
```bash
# 1. Check for Laravel updates
composer outdated | grep laravel

# 2. Update dependencies (staging first)
composer update --dry-run

# 3. Update in staging
cd /var/www/mswms
git checkout staging
composer update --no-dev --optimize-autoloader

# 4. Test thoroughly
php artisan test --compact

# 5. Deploy to production if stable
```

## Monthly Maintenance Tasks

### Performance Review

**Analyze Performance Metrics:**
```bash
# 1. Check slow queries
psql -U postgres -d mswms_production -c "
SELECT query, calls, total_exec_time, mean_exec_time
FROM pg_stat_statements
ORDER BY mean_exec_time DESC
LIMIT 20;
"

# 2. Check application response times
# Review APM tool (New Relic, Blackfire, etc.)

# 3. Check cache hit rates
redis-cli -a your_password INFO stats | grep keyspace

# 4. Review queue processing times
tail -1000 /var/log/supervisor/mswms-worker.log | grep "Processing"

# 5. Check database connection times
tail -1000 /var/www/mswms/storage/logs/laravel.log | grep "query"
```

### Backup Verification

**Test Backup Restoration:**
```bash
# 1. Get latest backup
LATEST_BACKUP=$(ls -t /var/backups/mswms/*.sql.gz | head -1)

# 2. Create test database
psql -U postgres -c "CREATE DATABASE mswms_test_restore;"

# 3. Restore backup
gunzip -c $LATEST_BACKUP | psql -U mswms_prod_user mswms_test_restore

# 4. Verify restoration
psql -U mswms_prod_user -d mswms_test_restore -c "
SELECT COUNT(*) FROM users;
SELECT COUNT(*) FROM orders;
SELECT COUNT(*) FROM products;
"

# 5. Drop test database
psql -U postgres -c "DROP DATABASE mswms_test_restore;"

# 6. Document results
echo "Backup verification completed on $(date)" >> /var/log/mswms/backup-verification.log
```

### Security Audit

**Review Security Logs:**
```bash
# 1. Check failed login attempts
grep -i "failed" /var/www/mswms/storage/logs/laravel.log | wc -l

# 2. Check for suspicious activity
grep -i "unauthorized" /var/www/mswms/storage/logs/laravel.log

# 3. Review audit logs
tail -1000 /var/www/mswms/storage/logs/audit.log

# 4. Check firewall logs
sudo tail -100 /var/log/ufw.log

# 5. Review Fail2Ban status
sudo fail2ban-client status
sudo fail2ban-client status sshd

# 6. Check for unauthorized access
psql -U postgres -d mswms_production -c "
SELECT email, last_login_at, created_at
FROM users
ORDER BY created_at DESC
LIMIT 20;
"
```

### Capacity Planning

**Review Resource Usage:**
```bash
# 1. Check disk usage trends
df -h /var
du -sh /var/www/mswms/storage/*

# 2. Check database growth
psql -U postgres -c "
SELECT 
    datname,
    pg_size_pretty(pg_database_size(datname)) as size
FROM pg_database
ORDER BY pg_database_size(datname) DESC;
"

# 3. Check log growth
du -sh /var/log/*

# 4. Check backup size
du -sh /var/backups/*

# 5. Review traffic patterns
# Check analytics or load balancer logs

# 6. Plan for scaling
# Document when to add resources
```

## Quarterly Maintenance Tasks

### Disaster Recovery Drill

**Test Full Recovery:**
```bash
# 1. Document current state
CURRENT_VERSION=$(git rev-parse HEAD)
CURRENT_DB_SIZE=$(psql -U postgres -c "SELECT pg_size_pretty(pg_database_size('mswms_production'));")

# 2. Simulate disaster
php artisan down

# 3. Restore from backup
LATEST_BACKUP=$(ls -t /var/backups/mswms/*.sql.gz | head -1)
psql -U postgres -c "DROP DATABASE IF EXISTS mswms_production;"
psql -U postgres -c "CREATE DATABASE mswms_production;"
gunzip -c $LATEST_BACKUP | psql -U mswms_prod_user mswms_production

# 4. Restore files
LATEST_FILES=$(ls -t /var/backups/files/*.tar.gz | head -1)
tar -xzf $LATEST_FILES -C /var/www/

# 5. Verify restoration
php artisan about
php artisan migrate:status

# 6. Bring up
php artisan up

# 7. Document recovery time
echo "Recovery completed in $(date)" >> /var/log/mswms/dr-drills.log
```

### SSL Certificate Renewal

**Check and Renew:**
```bash
# 1. Check certificate expiration
sudo openssl x509 -in /etc/letsencrypt/live/api.mswms.example.com/fullchain.pem -text -noout | grep "Not After"

# 2. Renew if needed (< 30 days)
sudo certbot renew --dry-run

# 3. Force renewal if needed
sudo certbot renew --force-renewal

# 4. Reload web server
sudo systemctl reload nginx

# 5. Verify renewal
curl -I https://api.mswms.example.com | grep -i "ssl"
```

### Password Rotation

**Rotate Credentials:**
```bash
# 1. Rotate database password
psql -U postgres -c "ALTER USER mswms_prod_user WITH PASSWORD 'new_secure_password';"

# 2. Update .env
nano /var/www/mswms/.env
# DB_PASSWORD=new_secure_password

# 3. Clear cache
php artisan config:clear
php artisan config:cache

# 4. Test connection
php artisan tinker --execute="DB::connection()->getPdo();"

# 5. Rotate Redis password
redis-cli -a old_password CONFIG SET requirepass new_password

# 6. Update .env
# REDIS_PASSWORD=new_password

# 7. Document rotation
echo "Passwords rotated on $(date)" >> /var/log/mswms/password-rotation.log
```

### Code Cleanup

**Application Maintenance:**
```bash
# 1. Remove old log files
find /var/www/mswms/storage/logs -name "*.log" -mtime +90 -delete

# 2. Clear old sessions
php artisan session:flush

# 3. Remove old cache files
find /var/www/mswms/storage/framework/cache -type f -mtime +30 -delete

# 4. Clean up vendor
composer dump-autoload --optimize

# 5. Check for unused routes
php artisan route:list | grep -v "GET|POST|PUT|DELETE"

# 6. Review and remove deprecated code
git log --oneline --since="3 months ago"
```

## Annual Maintenance Tasks

### Major Version Upgrade

**Laravel Upgrade:**
```bash
# 1. Review release notes
# https://laravel.com/docs/releases

# 2. Backup everything
./scripts/backup-all.sh

# 3. Update composer.json
composer require laravel/framework:^14.0

# 4. Update dependencies
composer update

# 5. Follow upgrade guide
# https://laravel.com/docs/14.x/upgrade

# 6. Test thoroughly in staging
php artisan test

# 7. Deploy to production
```

### Infrastructure Review

**Complete System Audit:**
```bash
# 1. Review server specifications
# Compare with current requirements

# 2. Check for hardware upgrades needed
# CPU, RAM, Storage, Network

# 3. Review software versions
php -v
nginx -v
postgres --version
redis-server --version

# 4. Check for end-of-life software
# Plan upgrades accordingly

# 5. Review costs
# Hosting, backups, CDN, monitoring

# 6. Plan next year's infrastructure
```

### Security Penetration Test

**Hire External Security Firm:**
```bash
# 1. Schedule penetration test
# 2. Provide scope and access
# 3. Review findings
# 4. Remediate vulnerabilities
# 5. Re-test fixes
# 6. Document results
# 7. Update security procedures
```

## Maintenance Schedule Template

### Daily Tasks (Automated)

| Time | Task | Script | Status |
|------|------|--------|--------|
| 00:00 | Database backup | backup-postgresql.sh | ✓ |
| 01:00 | File backup | backup-files.sh | ✓ |
| 02:00 | Log rotation | logrotate | ✓ |
| 03:00 | Queue cleanup | queue:flush | ✓ |
| 06:00 | Health check | health-check.sh | ✓ |

### Weekly Tasks (Manual)

| Day | Task | Responsible | Status |
|-----|------|-------------|--------|
| Monday | Database vacuum | DBA | ☐ |
| Tuesday | Cache cleanup | DevOps | ☐ |
| Wednesday | Security updates | SysAdmin | ☐ |
| Thursday | Performance review | Dev Team | ☐ |
| Friday | Backup verification | DBA | ☐ |

### Monthly Tasks

| Week | Task | Responsible | Status |
|------|------|-------------|--------|
| 1st | Security audit | Security Team | ☐ |
| 2nd | Performance optimization | Dev Team | ☐ |
| 3rd | Capacity planning | DevOps | ☐ |
| 4th | Documentation update | All | ☐ |

### Quarterly Tasks

| Quarter | Task | Responsible | Status |
|---------|------|-------------|--------|
| Q1 | Disaster recovery drill | All | ☐ |
| Q2 | SSL certificate renewal | DevOps | ☐ |
| Q3 | Password rotation | Security | ☐ |
| Q4 | Annual planning | Management | ☐ |

## Maintenance Documentation

### Maintenance Log Template

```markdown
# Maintenance Log

## Date: YYYY-MM-DD
## Engineer: [Name]
## Duration: [Start] - [End]

### Tasks Performed

1. **Task Name**
   - Description
   - Commands executed
   - Results

2. **Task Name**
   - Description
   - Commands executed
   - Results

### Issues Found

- [ ] None
- [ ] Issue 1: Description and resolution
- [ ] Issue 2: Description and resolution

### Follow-up Required

- [ ] None
- [ ] Task 1: Due date
- [ ] Task 2: Due date

### Sign-off

Engineer: _________________
Date: ____________________
```

## Maintenance Best Practices

### Before Maintenance

- [ ] Notify stakeholders
- [ ] Create backup
- [ ] Document current state
- [ ] Prepare rollback plan
- [ ] Schedule maintenance window

### During Maintenance

- [ ] Follow documented procedures
- [ ] Document all changes
- [ ] Test after each change
- [ ] Monitor for issues
- [ ] Keep stakeholders updated

### After Maintenance

- [ ] Verify all services running
- [ ] Run health checks
- [ ] Monitor for 24 hours
- [ ] Update documentation
- [ ] Conduct post-maintenance review

---

**Previous Section**: [← Troubleshooting](14-troubleshooting.md)  
**Next Section**: [→ Overview & Introduction](00-overview-introduction.md) (Return to start)
