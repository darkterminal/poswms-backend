# MSWMS Developer Guides

Comprehensive deployment and maintenance guides for the Multi-Store & Warehouse Management System (MSWMS).

## Guide Overview

This collection provides complete technical documentation for deploying, configuring, and maintaining MSWMS in staging and production environments.

## Quick Navigation

### Getting Started

| Guide | Description |
|-------|-------------|
| [00 - Overview & Introduction](00-overview-introduction.md) | Project overview, architecture, and deployment checklist |
| [01 - Server Requirements](01-server-requirements.md) | Hardware, software, and system prerequisites |
| [02 - Environment Configuration](02-environment-configuration.md) | Environment variables and configuration management |

### Infrastructure Setup

| Guide | Description |
|-------|-------------|
| [03 - Database Setup](03-database-setup.md) | PostgreSQL and MySQL installation and configuration |
| [04 - Redis Cache Setup](04-redis-cache-setup.md) | Redis installation, configuration, and optimization |
| [05 - Web Server Setup](05-web-server-setup.md) | Nginx and Apache configuration |
| [06 - SSL/TLS Configuration](06-ssl-configuration.md) | SSL certificates and security headers |

### Deployment

| Guide | Description |
|-------|-------------|
| [07 - Deployment Steps](07-deployment-steps.md) | Step-by-step deployment procedures |
| [08 - Queue Workers](08-queue-workers.md) | Queue configuration and worker management |
| [13 - CI/CD Pipeline](13-ci-cd-pipeline.md) | Continuous integration and deployment setup |

### Operations

| Guide | Description |
|-------|-------------|
| [09 - Monitoring & Logging](09-monitoring-logging.md) | Application and system monitoring |
| [10 - Security Hardening](10-security-hardening.md) | Security best practices and hardening |
| [11 - Performance Optimization](11-performance-optimization.md) | Performance tuning and optimization |
| [12 - Backup & Recovery](12-backup-recovery.md) | Backup strategies and disaster recovery |
| [14 - Troubleshooting](14-troubleshooting.md) | Common issues and solutions |
| [15 - Maintenance Procedures](15-maintenance-procedures.md) | Regular maintenance tasks and schedules |

## Deployment Checklist

### Pre-Deployment

- [ ] Review [Server Requirements](01-server-requirements.md)
- [ ] Prepare [Environment Configuration](02-environment-configuration.md)
- [ ] Set up [Database](03-database-setup.md)
- [ ] Configure [Redis](04-redis-cache-setup.md)
- [ ] Set up [Web Server](05-web-server-setup.md)
- [ ] Obtain [SSL Certificates](06-ssl-configuration.md)

### Deployment

- [ ] Follow [Deployment Steps](07-deployment-steps.md)
- [ ] Configure [Queue Workers](08-queue-workers.md)
- [ ] Set up [Monitoring](09-monitoring-logging.md)
- [ ] Apply [Security Hardening](10-security-hardening.md)

### Post-Deployment

- [ ] Run [Performance Optimization](11-performance-optimization.md)
- [ ] Configure [Backups](12-backup-recovery.md)
- [ ] Set up [CI/CD Pipeline](13-ci-cd-pipeline.md)
- [ ] Review [Troubleshooting Guide](14-troubleshooting.md)
- [ ] Schedule [Maintenance](15-maintenance-procedures.md)

## Environment-Specific Guides

### Staging Deployment

1. [Server Requirements](01-server-requirements.md) - Minimum specs
2. [Environment Configuration](02-environment-configuration.md) - Use `.env.staging`
3. [Database Setup](03-database-setup.md) - PostgreSQL or MySQL
4. [Deployment Steps](07-deployment-steps.md) - Follow staging section
5. [Queue Workers](08-queue-workers.md) - Database driver recommended

### Production Deployment

1. [Server Requirements](01-server-requirements.md) - Recommended specs
2. [Environment Configuration](02-environment-configuration.md) - Use `.env.production`
3. [Database Setup](03-database-setup.md) - PostgreSQL with HA
4. [Redis Cache Setup](04-redis-cache-setup.md) - Redis Cluster
5. [Web Server Setup](05-web-server-setup.md) - Nginx with load balancer
6. [SSL/TLS Configuration](06-ssl-configuration.md) - Commercial certificate
7. [Deployment Steps](07-deployment-steps.md) - Follow production section
8. [Queue Workers](08-queue-workers.md) - Redis driver
9. [Security Hardening](10-security-hardening.md) - Full hardening
10. [Performance Optimization](11-performance-optimization.md) - Full optimization
11. [Backup & Recovery](12-backup-recovery.md) - Complete backup strategy
12. [CI/CD Pipeline](13-ci-cd-pipeline.md) - Automated deployment

## Quick Reference

### Common Commands

```bash
# Deployment
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Queue Management
php artisan queue:work redis --tries=3
php artisan queue:restart
php artisan queue:failed
php artisan queue:retry all

# Cache Management
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Health Checks
curl https://api.mswms.example.com/api/health
php artisan about
php artisan env
```

### Important Directories

| Directory | Purpose | Permissions |
|-----------|---------|-------------|
| `/var/www/mswms` | Application root | 755 |
| `/var/www/mswms/storage` | File storage | 775 |
| `/var/www/mswms/bootstrap/cache` | Compiled files | 775 |
| `/var/log/nginx` | Web server logs | 640 |
| `/var/log/supervisor` | Worker logs | 644 |
| `/var/backups/mswms` | Backups | 600 |

### Important Files

| File | Purpose |
|------|---------|
| `/var/www/mswms/.env` | Environment configuration |
| `/etc/nginx/sites-available/mswms` | Nginx configuration |
| `/etc/php/8.3/fpm/pool.d/www.conf` | PHP-FPM pool configuration |
| `/etc/supervisor/conf.d/mswms-worker.conf` | Queue worker configuration |
| `/etc/postgresql/15/main/postgresql.conf` | PostgreSQL configuration |
| `/etc/redis/redis.conf` | Redis configuration |

## Support Resources

### Documentation

- **Laravel Documentation**: https://laravel.com/docs
- **PostgreSQL Documentation**: https://www.postgresql.org/docs/
- **Redis Documentation**: https://redis.io/documentation
- **Nginx Documentation**: https://nginx.org/en/docs/

### Internal Resources

- **API Design**: `/API_DESIGN.md`
- **Environment Examples**: `/.env.staging`, `/.env.development`
- **Security Audit**: `/docs/references/SECURITY_AUDIT_REPORT.md`

### Emergency Contacts

| Role | Contact |
|------|---------|
| System Administrator | [Contact] |
| Database Administrator | [Contact] |
| Security Team | [Contact] |
| Development Lead | [Contact] |

## Version Information

| Document Version | Date | Author | Changes |
|------------------|------|--------|---------|
| 1.0 | March 25, 2026 | Development Team | Initial release |

## Contributing

To update these guides:

1. Make changes to the relevant guide file
2. Update the version table above
3. Test any commands or procedures
4. Submit for review

## License

These guides are proprietary and confidential. Do not distribute outside the organization.

---

**Start Here**: [Overview & Introduction](00-overview-introduction.md)
