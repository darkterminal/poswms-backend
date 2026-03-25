# Docker Fundamentals for Laravel Developers

## Overview

This document introduces Docker concepts specifically for Laravel developers deploying MSWMS. Understanding these fundamentals is essential before proceeding with containerization.

## Core Docker Concepts

### What is Docker?

Docker is a platform for developing, shipping, and running applications in containers. Containers package an application with all its dependencies, ensuring consistent behavior across different environments.

**Key Benefits for Laravel:**
- Consistent development environments
- Easy scaling
- Simplified deployment
- Isolated dependencies
- Reproducible builds

### Docker vs Virtual Machines

| Aspect | Docker Containers | Virtual Machines |
|--------|-------------------|------------------|
| Boot Time | Seconds | Minutes |
| Resource Usage | Lightweight | Heavy |
| Isolation | Process-level | Full OS |
| Size | MBs | GBs |
| Performance | Near-native | Overhead |

### Docker Architecture

```
┌─────────────────────────────────────────┐
│           Docker Client                  │
│           (docker command)               │
└─────────────────┬───────────────────────┘
                  │ API
┌─────────────────▼───────────────────────┐
│           Docker Daemon                  │
│           (dockerd)                      │
└─────────────────┬───────────────────────┘
                  │
    ┌─────────────┼─────────────┐
    │             │             │
┌───▼───┐   ┌────▼────┐   ┌───▼───┐
│Container│  │ Container│  │Container│
│  App   │  │   Nginx  │  │  Redis  │
└────────┘   └─────────┘   └────────┘
```

## Key Docker Terminology

### Image

A read-only template containing application code, libraries, and dependencies.

**Example:**
```dockerfile
FROM php:8.3-fpm-alpine
# This pulls the PHP 8.3 FPM Alpine image
```

### Container

A runnable instance of an image. Containers can be started, stopped, and deleted.

**Example:**
```bash
# Create and start a container
docker run -d --name mswms-app mswms-backend:latest
```

### Dockerfile

A text file containing instructions to build a Docker image.

**Example:**
```dockerfile
FROM php:8.3-fpm-alpine
WORKDIR /var/www/html
COPY . .
RUN composer install --optimize-autoloader --no-dev
CMD ["php-fpm"]
```

### Docker Compose

A tool for defining and running multi-container Docker applications using YAML.

**Example:**
```yaml
version: '3.8'
services:
  app:
    build: .
    ports:
      - "9000:9000"
  db:
    image: postgres:15
```

### Volume

Persistent storage that survives container restarts.

**Example:**
```yaml
volumes:
  - postgres_data:/var/lib/postgresql/data

volumes:
  postgres_data:
```

### Network

Virtual network allowing containers to communicate.

**Example:**
```yaml
networks:
  mswms-network:
    driver: bridge
```

## Essential Docker Commands

### Image Management

```bash
# Pull an image from registry
docker pull php:8.3-fpm-alpine

# List local images
docker images

# Build an image from Dockerfile
docker build -t mswms-backend:latest .

# Remove an image
docker rmi mswms-backend:latest

# Prune unused images
docker image prune -a
```

### Container Management

```bash
# Run a container
docker run -d --name mswms-app mswms-backend:latest

# List running containers
docker ps

# List all containers (including stopped)
docker ps -a

# Stop a container
docker stop mswms-app

# Start a stopped container
docker start mswms-app

# Restart a container
docker restart mswms-app

# Remove a container
docker rm mswms-app

# View container logs
docker logs mswms-app

# Follow logs in real-time
docker logs -f mswms-app
```

### Executing Commands in Containers

```bash
# Execute command in running container
docker exec mswms-app php artisan --version

# Interactive shell
docker exec -it mswms-app bash

# Run as specific user
docker exec -u www-data mswms-app php artisan cache:clear
```

### Docker Compose Commands

```bash
# Start all services
docker compose up -d

# Stop all services
docker compose down

# View logs
docker compose logs

# Follow logs
docker compose logs -f

# View specific service logs
docker compose logs -f app

# Restart services
docker compose restart

# Rebuild and restart
docker compose up -d --build

# View running services
docker compose ps

# Execute command in service
docker compose exec app php artisan migrate

# Run new container for command
docker compose run --rm app php artisan test
```

### Volume Management

```bash
# List volumes
docker volume ls

# Create a volume
docker volume create postgres_data

# Inspect a volume
docker volume inspect postgres_data

# Remove a volume
docker volume rm postgres_data

# Prune unused volumes
docker volume prune
```

### Network Management

```bash
# List networks
docker network ls

# Create a network
docker network create mswms-network

# Inspect a network
docker network inspect mswms-network

# Remove a network
docker network rm mswms-network
```

## Docker for Laravel: Key Concepts

### PHP-FPM Container

Laravel runs on PHP-FPM (FastCGI Process Manager) inside Docker.

**Key Points:**
- PHP-FPM listens on port 9000
- Nginx proxies PHP requests to PHP-FPM
- Static files served directly by Nginx

### Multi-Stage Builds

Build assets in one container, copy to production container.

**Example:**
```dockerfile
# Stage 1: Build assets
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# Stage 2: Production
FROM php:8.3-fpm-alpine
COPY --from=frontend /app/public/build /var/www/html/public/build
```

### Health Checks

Monitor container health automatically.

**Example:**
```dockerfile
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://localhost:8080/api/health || exit 1
```

### Environment Variables

Pass configuration to containers.

**Example:**
```yaml
services:
  app:
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
      - DB_HOST=postgres
      - DB_PASSWORD=${DB_PASSWORD}
```

## Common Docker Patterns for Laravel

### Pattern 1: Separate App and Web Server

```yaml
services:
  app:
    build: .
    volumes:
      - ./app:/var/www/html/app
      - ./config:/var/www/html/config
  
  web:
    image: nginx:alpine
    ports:
      - "80:80"
    volumes:
      - ./public:/var/www/html/public
```

**Benefits:**
- Clear separation of concerns
- Independent scaling
- Better security

### Pattern 2: Shared Volumes for Development

```yaml
services:
  app:
    volumes:
      - ./:/var/www/html
      - /var/www/html/vendor  # Don't overwrite vendor
```

**Benefits:**
- Live code reloading
- No rebuild needed for changes
- Faster development

### Pattern 3: Production Optimization

```yaml
services:
  app:
    build:
      context: .
      target: production
    volumes:
      - app_storage:/var/www/html/storage
  
volumes:
  app_storage:
```

**Benefits:**
- Optimized image size
- Persistent storage
- Production-ready

## Docker Best Practices for Laravel

### 1. Use Multi-Stage Builds

```dockerfile
# Bad: Single stage, large image
FROM php:8.3-fpm
COPY . .
RUN composer install

# Good: Multi-stage, optimized
FROM composer AS vendor
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader

FROM php:8.3-fpm-alpine
COPY --from=vendor /app/vendor /var/www/html/vendor
```

### 2. Don't Run as Root

```dockerfile
# Bad: Running as root
FROM php:8.3-fpm

# Good: Create and use non-root user
FROM php:8.3-fpm-alpine
RUN adduser -D -u 1000 www-data
USER www-data
```

### 3. Use .dockerignore

```
# .dockerignore
.git
node_modules
vendor
.env
storage/logs/*
storage/framework/cache/*
.idea
.vscode
*.md
```

### 4. Minimize Layers

```dockerfile
# Bad: Multiple RUN commands
RUN apt-get update
RUN apt-get install -y git
RUN apt-get install -y curl

# Good: Combined RUN commands
RUN apt-get update && apt-get install -y \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*
```

### 5. Use Specific Versions

```dockerfile
# Bad: Using latest
FROM php:latest
FROM node:latest

# Good: Specific versions
FROM php:8.3-fpm-alpine
FROM node:20-alpine
```

## Docker Networking for Laravel

### Default Network Behavior

Containers on the same Docker Compose network can communicate by service name:

```yaml
services:
  app:
    # Can reach postgres at hostname "postgres"
    environment:
      - DB_HOST=postgres
  
  postgres:
    image: postgres:15
```

### Network Types

| Type | Use Case |
|------|----------|
| bridge | Default, isolated network |
| host | Share host network (not recommended) |
| none | No networking |
| overlay | Multi-host (Swarm) |

### Port Mapping

```yaml
services:
  app:
    ports:
      - "9000:9000"  # host:container
  
  web:
    ports:
      - "8080:80"   # Access app at localhost:8080
```

## Troubleshooting Docker

### Common Issues

**Container Won't Start:**
```bash
# Check logs
docker logs container_name

# Check container status
docker ps -a

# Inspect container
docker inspect container_name
```

**Can't Connect to Database:**
```bash
# Verify network
docker network inspect mswms-network

# Test connection from app container
docker exec -it app-container ping postgres

# Check database is running
docker compose ps
```

**Permission Issues:**
```bash
# Fix volume permissions
docker compose exec app chown -R www-data:www-data /var/www/html/storage

# Or run as root temporarily
docker compose exec -u root app chown -R www-data:www-data /var/www/html
```

**Disk Space Issues:**
```bash
# Check disk usage
docker system df

# Prune unused resources
docker system prune -a

# Remove unused volumes
docker volume prune
```

## Next Steps

After understanding these fundamentals:

1. **Create Dockerfile** → [Dockerfile Creation](02-dockerfile-creation.md)
2. **Set up Docker Compose** → [Docker Compose Setup](03-docker-compose-setup.md)
3. **Configure Environment** → [Environment Configuration](04-environment-configuration.md)

---

**Previous Section**: [← Overview](00-overview.md)  
**Next Section**: [Dockerfile Creation →](02-dockerfile-creation.md)
