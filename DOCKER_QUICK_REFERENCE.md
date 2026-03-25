# Docker Quick Reference - POS WMS Backend

## First Time Setup

```bash
# 1. Copy environment file
cp .env.docker .env

# 2. Generate APP_KEY (if PHP is available locally)
php artisan key:generate

# 3. Or use the automated setup script
./docker-start.sh
```

## Basic Commands

### Start/Stop

```bash
# Start all services (detached mode)
docker-compose up -d

# Start with build
docker-compose up -d --build

# Start specific services only
docker-compose up -d app db

# Stop all services
docker-compose down

# Stop and remove volumes (WARNING: deletes data!)
docker-compose down -v

# Restart services
docker-compose restart

# Restart specific service
docker-compose restart app
```

### View Logs

```bash
# View all logs
docker-compose logs -f

# View specific service logs
docker-compose logs -f app
docker-compose logs -f db
docker-compose logs -f redis

# View last 100 lines
docker-compose logs --tail=100 app

# View logs with timestamps
docker-compose logs -f --timestamps app
```

### Execute Commands

```bash
# Access container shell
docker-compose exec app bash

# Run artisan commands
docker-compose exec app php artisan <command>

# Examples
docker-compose exec app php artisan route:list
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan optimize
docker-compose exec app php artisan queue:work
docker-compose exec app php artisan schedule:work

# Run tests
docker-compose exec app php artisan test
docker-compose exec app php artisan test --filter=SpecificTest
```

### Database Operations

```bash
# Access PostgreSQL CLI
docker-compose exec db psql -U poswms -d poswms

# Run SQL file
docker-compose exec -T db psql -U poswms -d poswms < backup.sql

# Backup database
docker-compose exec db pg_dump -U poswms poswms > backup.sql

# Restore from backup
cat backup.sql | docker-compose exec -T db psql -U poswms -d poswms

# Check database status
docker-compose exec db pg_isready
```

### Redis Operations

```bash
# Access Redis CLI
docker-compose exec redis redis-cli

# Check Redis info
docker-compose exec redis redis-cli info

# Monitor Redis commands
docker-compose exec redis redis-cli monitor

# Clear all Redis data (development only!)
docker-compose exec redis redis-cli FLUSHALL
```

## Health Checks

```bash
# Check container health
docker inspect --format='{{.State.Health.Status}}' poswms-api

# Check all containers status
docker-compose ps

# Test health endpoint
curl http://localhost:8080/api/health

# View container stats
docker stats
```

## Cleanup

```bash
# Remove stopped containers
docker-compose down

# Remove all containers, networks, and volumes
docker-compose down -v --remove-orphans

# Remove dangling images
docker image prune

# Remove all unused images
docker image prune -a

# View disk usage
docker system df
```

## Troubleshooting

### Container Won't Start

```bash
# Check logs for errors
docker-compose logs app

# Check if ports are in use
netstat -tulpn | grep 8080
lsof -i :8080

# Rebuild containers
docker-compose up -d --build --force-recreate

# Remove and recreate
docker-compose down
docker-compose up -d
```

### Database Connection Issues

```bash
# Wait for database to be ready
sleep 10

# Test database connection from app container
docker-compose exec app php artisan tinker --execute "DB::connection()->getPdo();"

# Check database is accepting connections
docker-compose exec db pg_isready -U poswms

# Reset database
docker-compose down -v
docker-compose up -d db
docker-compose exec app php artisan migrate --force
```

### Permission Issues

```bash
# Fix storage permissions
docker-compose exec app chown -R appuser:appgroup storage bootstrap/cache
docker-compose exec app chmod -R 775 storage bootstrap/cache

# Or from host (if using bind mounts)
sudo chown -R $USER:$USER storage bootstrap/cache
```

### Clear Caches

```bash
# Clear all caches
docker-compose exec app php artisan optimize:clear

# Clear specific caches
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear
```

### Reset Everything

```bash
# Complete reset (WARNING: deletes all data!)
docker-compose down -v
docker system prune -a
rm -rf storage/database.sqlite
cp .env.docker .env
docker-compose up -d --build
docker-compose exec app php artisan migrate --force
```

## Production Deployment

### Build Production Image

```bash
docker-compose -f docker-compose.yml build --no-cache
```

### Deploy

```bash
# Set environment variables
export APP_ENV=production
export APP_DEBUG=false
export APP_KEY=your-production-key

# Start services
docker-compose up -d

# Run migrations
docker-compose exec app php artisan migrate --force

# Clear caches
docker-compose exec app php artisan optimize
```

### Update Deployment

```bash
# Pull latest changes (if using git)
git pull origin main

# Rebuild and restart
docker-compose up -d --build --force-recreate

# Run new migrations
docker-compose exec app php artisan migrate --force

# Clear caches
docker-compose exec app php artisan optimize
```

## Environment Variables

Key variables in `.env`:

```bash
# Application
APP_PORT=8080                    # Host port for app
APP_ENV=local                    # Environment (local/production)
APP_DEBUG=true                   # Debug mode
APP_KEY=base64:...              # Application key

# Database
DB_PORT_EXTERNAL=5432           # Host port for PostgreSQL
DB_CONNECTION=pgsql             # Database driver
DB_HOST=db                      # Internal DB host
DB_DATABASE=poswms              # Database name
DB_USERNAME=poswms              # Database user
DB_PASSWORD=secret              # Database password

# Redis
REDIS_PORT_EXTERNAL=6379        # Host port for Redis
REDIS_HOST=redis                # Internal Redis host
```

## Services Overview

| Service | Container | Port | Purpose |
|---------|-----------|------|---------|
| app | poswms-api | 8080 | Laravel API (Nginx + PHP-FPM) |
| db | poswms-db | 5432 | PostgreSQL database |
| redis | poswms-redis | 6379 | Redis cache/sessions |

## Volumes

| Volume | Purpose |
|--------|---------|
| postgres_data | PostgreSQL data |
| redis_data | Redis persistence |
| sqlite_data | SQLite database (if used) |
| storage_data | Application storage |
| logs_data | Application logs |

## Network

All containers are connected to the `poswms-network` bridge network.

```bash
# View network
docker network inspect poswms-backend_poswms-network

# Connect additional container
docker network connect poswms-backend_poswms-network <container>
```

## Additional Resources

- [DOCKER.md](DOCKER.md) - Complete Docker documentation
- [API_DESIGN.md](API_DESIGN.md) - API design specification
- [README.md](README.md) - Project overview

## Tips

1. **Development**: Use `app-dev` service (uncomment in docker-compose.yml) for hot-reload
2. **Queue Workers**: Uncomment `queue-worker` service to process background jobs
3. **Scheduler**: Uncomment `scheduler` service for Laravel task scheduling
4. **Backups**: Regularly backup PostgreSQL volume data
5. **Security**: Change default passwords in production
6. **Monitoring**: Check health endpoint regularly
7. **Logs**: Set up log aggregation for production
8. **Performance**: Enable OPcache and optimize PHP-FPM settings
