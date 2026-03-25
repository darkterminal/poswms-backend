# MSWMS Docker Deployment Guide - Overview

## Document Purpose

This comprehensive guide provides step-by-step instructions for containerizing and deploying the Multi-Store & Warehouse Management System (MSWMS) API using Docker and free open-source technologies. This guide includes complete Coolify PaaS deployment instructions.

## Target Audience

- **DevOps Engineers**: Container orchestration and deployment
- **Developers**: Local development with Docker
- **System Administrators**: Production deployment and maintenance
- **Technical Leads**: Architecture planning and stack selection

## Technology Stack Overview

### Core Technologies

| Component | Technology | License | Purpose |
|-----------|------------|---------|---------|
| Container Runtime | Docker | Apache 2.0 | Application containerization |
| Orchestration | Docker Compose | Apache 2.0 | Multi-container management |
| Web Server | Nginx | BSD-like | Reverse proxy & static files |
| PHP Runtime | PHP 8.3 FPM | PHP-3.01 | Application runtime |
| Database | PostgreSQL | PostgreSQL License | Primary database |
| Cache/Queue | Redis | BSD 3-Clause | Caching & job queues |
| PaaS | Coolify | Apache 2.0 | Self-hosted deployment platform |

### Why FOSS Stack?

**Advantages:**
- Zero licensing costs
- Full control over infrastructure
- No vendor lock-in
- Active community support
- Transparent security
- Customizable to needs

**Cost Comparison:**

| Solution | Monthly Cost | Self-Hosted |
|----------|--------------|-------------|
| AWS RDS + ECS | $200-500+ | ✗ |
| Heroku | $50-250+ | ✗ |
| Laravel Forge + DigitalOcean | $20-100+ | ✗ |
| **Coolify + VPS** | **$5-40** | ✓ |
| **Docker on VPS** | **$5-40** | ✓ |

## Architecture Overview

### Single Server Architecture (Staging/Small Production)

```
┌─────────────────────────────────────────────────────┐
│                    VPS Server                        │
│                   (4GB RAM, 2 CPU)                   │
│                                                      │
│  ┌─────────────────────────────────────────────┐    │
│  │              Coolify (Optional)              │    │
│  │         Management Dashboard                  │    │
│  └─────────────────────────────────────────────┘    │
│                                                      │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐ │
│  │    Nginx    │  │  PHP-FPM    │  │   Redis     │ │
│  │  Container  │  │  Container  │  │  Container  │ │
│  │  Port 80/443│  │  Port 9000  │  │  Port 6379  │ │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘ │
│         │                │                │         │
│         └────────────────┼────────────────┘         │
│                          │                          │
│              ┌───────────▼───────────┐             │
│              │    PostgreSQL         │             │
│              │    Container          │             │
│              │    Port 5432          │             │
│              └───────────────────────┘             │
│                                                      │
│  ┌─────────────────────────────────────────────┐    │
│  │         Persistent Volumes                  │    │
│  │  /data/postgres  /data/redis  /data/app    │    │
│  └─────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────┘
```

### Multi-Server Architecture (Large Production)

```
┌─────────────────┐     ┌─────────────────┐
│  Load Balancer  │────▶│  App Server 1   │
│  (Nginx/HAProxy)│     │  (Docker Stack) │
└─────────────────┘     └─────────────────┘
         │
         ├──────────────────────────────────────┐
         │                                      │
         ▼                                      ▼
┌─────────────────┐                 ┌─────────────────┐
│  App Server 2   │                 │  DB Server      │
│  (Docker Stack) │                 │  (PostgreSQL)   │
└─────────────────┘                 └─────────────────┘
                                            │
                                            ▼
                                   ┌─────────────────┐
                                   │  Redis Server   │
                                   │  (Cache/Queue)  │
                                   └─────────────────┘
```

## Deployment Options

### Option 1: Docker Compose (Recommended for Staging/Small Production)

**Best For:**
- Development environments
- Staging servers
- Small production (< 10,000 requests/day)
- Single VPS deployments

**Requirements:**
- VPS with 2GB+ RAM
- Docker & Docker Compose installed
- Domain name (optional for local)

**Estimated Setup Time:** 30-60 minutes

### Option 2: Coolify PaaS (Recommended for Production)

**Best For:**
- Production environments
- Teams wanting Heroku-like experience
- Multiple applications on same server
- Automated deployments from Git

**Requirements:**
- VPS with 4GB+ RAM
- Domain name
- Git repository (GitHub/GitLab)

**Estimated Setup Time:** 60-90 minutes

### Option 3: Kubernetes (Advanced Production)

**Best For:**
- Large-scale deployments
- High availability requirements
- Microservices architecture
- Teams with K8s expertise

**Note:** This guide focuses on Options 1 & 2. Kubernetes deployment is covered in a separate guide.

## Quick Start Guide

### Prerequisites

1. Docker installed (version 24.0+)
2. Docker Compose installed (version 2.20+)
3. Git installed
4. Domain name pointing to your server (for production)

### 5-Minute Local Deployment

```bash
# Clone repository
git clone https://github.com/your-org/poswms-backend.git
cd poswms-backend

# Copy environment file
cp .env.docker.example .env

# Start all services
docker compose up -d

# Run migrations
docker compose exec app php artisan migrate --force

# Generate app key
docker compose exec app php artisan key:generate

# Access application
open http://localhost:8080
```

### 30-Minute Production Deployment (Coolify)

```bash
# 1. Set up Coolify on VPS
curl -fsSL https://cdn.coollabs.io/coolify/install.sh | bash

# 2. Access Coolify dashboard
# https://your-server-ip

# 3. Add your Git repository
# Coolify → Add New → Git Repository

# 4. Configure environment variables
# Copy from .env.production.example

# 5. Deploy!
# Coolify handles the rest
```

## Guide Structure

This deployment guide is divided into the following sections:

| File | Topic | Description |
|------|-------|-------------|
| `00-overview.md` | Overview | This document - introduction and architecture |
| `01-docker-fundamentals.md` | Docker Basics | Docker concepts and commands for Laravel |
| `02-dockerfile-creation.md` | Dockerfile | Creating optimized Dockerfile for MSWMS |
| `03-docker-compose-setup.md` | Docker Compose | Multi-container orchestration |
| `04-environment-configuration.md` | Environment | Environment variables and secrets |
| `05-database-containerization.md` | Database | PostgreSQL in Docker |
| `06-redis-containerization.md` | Redis | Redis for cache and queues |
| `07-nginx-reverse-proxy.md` | Nginx | Reverse proxy configuration |
| `08-ssl-certificates.md` | SSL/TLS | Let's Encrypt with Traefik/Nginx |
| `09-coolify-setup.md` | Coolify Install | Installing and configuring Coolify |
| `10-coolify-deployment.md` | Coolify Deploy | Deploying MSWMS on Coolify |
| `11-ci-cd-docker.md` | CI/CD | Automated Docker builds and deployments |
| `12-monitoring-logging.md` | Monitoring | Container monitoring and logging |
| `13-backup-restore.md` | Backup | Docker volume backup strategies |
| `14-performance-optimization.md` | Performance | Container performance tuning |
| `15-security-hardening.md` | Security | Docker security best practices |
| `16-troubleshooting.md` | Troubleshooting | Common Docker issues and solutions |

## Resource Requirements

### Minimum Requirements (Development)

| Resource | Requirement |
|----------|-------------|
| CPU | 2 cores |
| RAM | 4 GB |
| Storage | 20 GB SSD |
| Network | 100 Mbps |

### Recommended Requirements (Staging)

| Resource | Requirement |
|----------|-------------|
| CPU | 2-4 cores |
| RAM | 4-8 GB |
| Storage | 40 GB SSD |
| Network | 1 Gbps |

### Production Requirements

| Resource | Small | Medium | Large |
|----------|-------|--------|-------|
| CPU | 4 cores | 8 cores | 16+ cores |
| RAM | 8 GB | 16 GB | 32+ GB |
| Storage | 80 GB SSD | 160 GB SSD | 500+ GB SSD |
| Network | 1 Gbps | 1 Gbps | 10 Gbps |

## Cost Estimates (FOSS Stack)

### Self-Hosted on VPS

| Provider | Plan | Monthly Cost | Annual Cost |
|----------|------|--------------|-------------|
| Hetzner | CPX11 | €4.59 | €55 |
| DigitalOcean | Basic 2GB | $12 | $144 |
| Linode | Nanode 2GB | $12 | $144 |
| Vultr | Cloud Compute 2GB | $12 | $144 |
| OVH | VPS Value | $4.20 | $50 |

**Total Monthly Cost: $4-15** (depending on provider)

### Coolify Self-Hosted

| Component | Cost |
|-----------|------|
| Coolify | Free (self-hosted) |
| VPS | $5-40/month |
| Domain | $10-15/year |
| SSL Certificates | Free (Let's Encrypt) |

**Total Monthly Cost: $5-45**

## Support and Resources

### Documentation

- **Docker Documentation**: https://docs.docker.com/
- **Docker Compose**: https://docs.docker.com/compose/
- **Coolify Documentation**: https://coolify.io/docs/
- **Laravel Docker**: https://laravel.com/docs/deployment

### Community Resources

- **Docker Community Forums**: https://forums.docker.com/
- **Coolify Discord**: https://discord.gg/coolify
- **Laravel Community**: https://laracasts.com/discuss

### Emergency Contacts

| Issue | Resource |
|-------|----------|
| Docker Issues | Docker logs, `docker compose logs` |
| Coolify Issues | Coolify dashboard, Discord support |
| Database Issues | PostgreSQL logs, pgAdmin |
| Application Issues | Laravel logs, Telescope |

## Version Information

| Document Version | Date | Author | Changes |
|------------------|------|--------|---------|
| 1.0 | March 25, 2026 | Development Team | Initial release |

---

**Next Section**: [Docker Fundamentals →](01-docker-fundamentals.md)
