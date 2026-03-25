# MSWMS Docker Deployment Guides

Comprehensive Docker deployment documentation for the Multi-Store & Warehouse Management System (MSWMS) using free open-source technologies.

## Quick Start

### 5-Minute Local Deployment

```bash
# Clone repository
git clone https://github.com/your-org/poswms-backend.git
cd poswms-backend

# Copy environment
cp .env.docker.example .env

# Start all services
docker compose up -d

# Run migrations
docker compose exec app php artisan migrate --force

# Generate app key
docker compose exec app php artisan key:generate

# Access: http://localhost:8080
```

### 30-Minute Production (Coolify)

```bash
# Install Coolify on VPS
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash

# Access dashboard: http://your-server-ip:3000
# Add repository, configure environment, deploy!
```

## Guide Navigation

### Getting Started

| Guide | Description | Difficulty |
|-------|-------------|------------|
| [00 - Overview](00-overview.md) | Introduction, architecture, quick start | Beginner |
| [01 - Docker Fundamentals](01-docker-fundamentals.md) | Docker concepts for Laravel developers | Beginner |
| [02 - Dockerfile Creation](02-dockerfile-creation.md) | Creating optimized Dockerfiles | Intermediate |
| [03 - Docker Compose Setup](03-docker-compose-setup.md) | Multi-container orchestration | Intermediate |
| [04 - Environment Configuration](04-environment-configuration.md) | Environment variables and secrets | Beginner |

### Core Services

| Guide | Description | Difficulty |
|-------|-------------|------------|
| [05 - Database Containerization](05-database-containerization.md) | PostgreSQL in Docker | Intermediate |
| [06 - Redis Containerization](06-redis-containerization.md) | Redis for cache and queues | Intermediate |
| [07 - Nginx Reverse Proxy](07-nginx-reverse-proxy.md) | Nginx configuration | Intermediate |
| [08 - SSL Certificates](08-ssl-certificates.md) | Let's Encrypt with Traefik/Nginx | Advanced |

### Coolify Deployment

| Guide | Description | Difficulty |
|-------|-------------|------------|
| [09 - Coolify Setup](09-coolify-setup.md) | Installing and configuring Coolify | Intermediate |
| [10 - Coolify Deployment](10-coolify-deployment.md) | Complete Coolify deployment | Intermediate |

### Advanced Topics

| Guide | Description | Difficulty |
|-------|-------------|------------|
| [11 - CI/CD with Docker](11-ci-cd-docker.md) | Automated Docker builds and deployments | Advanced |
| [12 - Monitoring & Logging](12-monitoring-logging.md) | Container monitoring and logging | Intermediate |
| [13 - Backup & Restore](13-backup-restore.md) | Docker volume backup strategies | Intermediate |
| [14 - Performance Optimization](14-performance-optimization.md) | Container performance tuning | Advanced |
| [15 - Security Hardening](15-security-hardening.md) | Docker security best practices | Advanced |
| [16 - Troubleshooting](16-troubleshooting.md) | Common Docker issues and solutions | Intermediate |

## Architecture Overview

### Development Stack

```
┌─────────────────────────────────────────────┐
│           Docker Compose                     │
│                                              │
│  ┌─────────┐  ┌─────────┐  ┌─────────────┐ │
│  │  Nginx  │  │ PHP-FPM │  │   Vite      │ │
│  │  :8080  │  │  :9000  │  │   :5173     │ │
│  └─────────┘  └─────────┘  └─────────────┘ │
│                                              │
│  ┌─────────┐  ┌─────────┐  ┌─────────────┐ │
│  │PostgreSQL│ │  Redis  │  │   pgAdmin   │ │
│  │  :5432  │  │  :6379  │  │   :8081     │ │
│  └─────────┘  └─────────┘  └─────────────┘ │
└─────────────────────────────────────────────┘
```

### Production Stack (Coolify)

```
┌─────────────────────────────────────────────┐
│              Coolify Platform                │
│                                              │
│  ┌─────────────────────────────────────┐    │
│  │         Traefik (SSL/Proxy)         │    │
│  │         Port 80/443                 │    │
│  └─────────────────────────────────────┘    │
│                                              │
│  ┌─────────┐  ┌─────────┐  ┌─────────────┐ │
│  │   App   │  │PostgreSQL│ │    Redis    │ │
│  │  +Nginx │  │         │  │             │ │
│  └─────────┘  └─────────┘  └─────────────┘ │
└─────────────────────────────────────────────┘
```

## Technology Stack

| Component | Technology | License |
|-----------|------------|---------|
| Container Runtime | Docker | Apache 2.0 |
| Orchestration | Docker Compose | Apache 2.0 |
| Web Server | Nginx | BSD-like |
| PHP Runtime | PHP 8.3 FPM | PHP-3.01 |
| Database | PostgreSQL 15 | PostgreSQL License |
| Cache/Queue | Redis 7 | BSD 3-Clause |
| PaaS | Coolify | Apache 2.0 |
| SSL | Let's Encrypt | Free |

## Resource Requirements

### Development

| Resource | Minimum | Recommended |
|----------|---------|-------------|
| CPU | 2 cores | 4 cores |
| RAM | 4 GB | 8 GB |
| Storage | 20 GB | 40 GB |

### Production (Coolify)

| Resource | Minimum | Recommended |
|----------|---------|-------------|
| CPU | 2 cores | 4+ cores |
| RAM | 4 GB | 8+ GB |
| Storage | 40 GB SSD | 80+ GB SSD |

## Cost Estimates

### Self-Hosted (FOSS)

| Component | Monthly Cost |
|-----------|--------------|
| VPS (Hetzner/DigitalOcean) | $5-20 |
| Domain | $1-2 |
| SSL (Let's Encrypt) | Free |
| Coolify | Free |
| **Total** | **$6-22/month** |

### Comparison

| Solution | Monthly Cost |
|----------|--------------|
| Heroku | $50-250 |
| Laravel Forge + VPS | $20-100 |
| AWS ECS + RDS | $100-500 |
| **Coolify + VPS** | **$6-22** |

## Common Commands

### Docker Compose

```bash
# Start services
docker compose up -d

# Stop services
docker compose down

# View logs
docker compose logs -f

# Execute commands
docker compose exec app php artisan migrate
docker compose exec app php artisan test

# Rebuild
docker compose up -d --build

# Scale workers
docker compose up -d --scale worker=4
```

### Coolify

```bash
# Install Coolify
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash

# View Coolify logs
docker logs coolify -f

# Restart Coolify
docker compose restart
```

## Quick Reference

### Port Mapping

| Service | Internal | External (Dev) | External (Prod) |
|---------|----------|----------------|-----------------|
| Nginx | 80 | 8080 | 80/443 |
| PHP-FPM | 9000 | - | - |
| PostgreSQL | 5432 | 5432 | - |
| Redis | 6379 | 6379 | - |
| pgAdmin | 80 | 8081 | - |
| Vite | 5173 | 5173 | - |

### Volume Paths

| Volume | Host Path | Container Path |
|--------|-----------|----------------|
| App Storage | `app_storage` | `/var/www/html/storage` |
| PostgreSQL | `postgres_data` | `/var/lib/postgresql/data` |
| Redis | `redis_data` | `/data` |
| Nginx Cache | `nginx_cache` | `/var/cache/nginx` |

### Environment Variables

| Variable | Development | Production |
|----------|-------------|------------|
| APP_ENV | local | production |
| APP_DEBUG | true | false |
| DB_DATABASE | mswms_dev | mswms_production |
| CACHE_STORE | redis | redis |
| QUEUE_CONNECTION | redis | redis |
| LOG_LEVEL | debug | error |

## Support Resources

### Documentation

- **Docker Docs**: https://docs.docker.com/
- **Docker Compose**: https://docs.docker.com/compose/
- **Coolify Docs**: https://coolify.io/docs/
- **Laravel Docker**: https://laravel.com/docs/deployment

### Community

- **Docker Forums**: https://forums.docker.com/
- **Coolify Discord**: https://discord.gg/coolify
- **Laravel Community**: https://laracasts.com/discuss

### Troubleshooting

- Check logs: `docker compose logs -f`
- View container status: `docker compose ps`
- Inspect container: `docker inspect container_name`
- Coolify dashboard: `http://your-server-ip:3000`

## Version Information

| Document Version | Date | Author |
|------------------|------|--------|
| 1.0 | March 25, 2026 | Development Team |

---

**Start Here**: [Overview & Introduction](00-overview.md)
