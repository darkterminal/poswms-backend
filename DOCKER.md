# Docker Setup Guide - POS WMS Backend

This guide explains how to run the POS WMS Backend API using Docker.

## Quick Start

### 1. Generate Application Key (First Time Only)

```bash
# If you don't have an APP_KEY, generate one
php artisan key:generate --show
```

Copy the generated key and add it to your `.env` file.

### 2. Configure Environment

Copy the example environment file and configure it:

```bash
cp .env.example .env
```

Edit `.env` and set at minimum:

```env
APP_KEY=base64:your-generated-key-here
APP_ENV=local
APP_DEBUG=true

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=poswms
DB_USERNAME=poswms
DB_PASSWORD=secret

REDIS_HOST=redis
REDIS_PORT=6379
```

### 3. Start Docker Containers

```bash
# Build and start all services
docker-compose up -d --build

# View logs
docker-compose logs -f

# View specific service logs
docker-compose logs -f app
docker-compose logs -f db
docker-compose logs -f redis
```

### 4. Run Migrations

```bash
# Run migrations inside the container
docker-compose exec app php artisan migrate

# Or run with force flag for production
docker-compose exec app php artisan migrate --force
```

### 5. Seed Database (Optional)

```bash
docker-compose exec app php artisan db:seed
```

### 6. Access the API

```
http://localhost:8080
```

The API is available at `http://localhost:8080/api/v1/`

## Services

| Service | Container Name | Port | Description |
|---------|---------------|------|-------------|
| App | `poswms-api` | 8080 | Laravel API (Nginx + PHP-FPM) |
| Database | `poswms-db` | 5432 | PostgreSQL 16 |
| Redis | `poswms-redis` | 6379 | Redis 7 (Cache & Sessions) |

## Common Commands

### Container Management

```bash
# Start all services
docker-compose up -d

# Stop all services
docker-compose down

# Restart services
docker-compose restart

# Rebuild and restart
docker-compose up -d --build --force-recreate

# View running containers
docker-compose ps
```

### Execute Commands

```bash
# Run artisan commands
docker-compose exec app php artisan <command>

# Examples
docker-compose exec app php artisan route:list
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:cache
docker-compose exec app php artisan queue:work
docker-compose exec app php artisan schedule:work

# Access container shell
docker-compose exec app bash

# Run tests
docker-compose exec app php artisan test
```

### Database Operations

```bash
# Access PostgreSQL
docker-compose exec db psql -U poswms -d poswms

# Backup database
docker-compose exec db pg_dump -U poswms poswms > backup.sql

# Restore database
docker-compose exec -T db psql -U poswms -d poswms < backup.sql
```

### Logs

```bash
# View all logs
docker-compose logs -f

# View app logs only
docker-compose logs -f app

# View Laravel logs inside container
docker-compose exec app tail -f storage/logs/laravel.log
```

## Development Mode

For development with hot-reload, uncomment the `app-dev` service in `docker-compose.yml`:

```yaml
# Uncomment this section in docker-compose.yml
app-dev:
  build:
    context: .
    dockerfile: Dockerfile.dev
  # ... rest of config
```

Then run:

```bash
docker-compose up -d app-dev db redis
```

## Queue Worker

To enable queue processing, uncomment the `queue-worker` service:

```bash
# Start queue worker
docker-compose up -d queue-worker

# View queue logs
docker-compose logs -f queue-worker
```

## Scheduler

To enable Laravel scheduler, uncomment the `scheduler` service:

```bash
# Start scheduler
docker-compose up -d scheduler
```

## Production Deployment

### 1. Set Environment Variables

```bash
APP_ENV=production
APP_DEBUG=false
APP_KEY=your-production-key

DB_CONNECTION=pgsql
DB_HOST=your-production-db-host
DB_DATABASE=poswms_production
DB_USERNAME=poswms_prod
DB_PASSWORD=strong-password

# Redis
REDIS_HOST=redis

# Security
CSP_MODE=strict
SECURITY_STRICT_MODE=STRICT
```

### 2. Build Production Image

```bash
docker-compose -f docker-compose.yml build --no-cache
```

### 3. Deploy

```bash
docker-compose up -d
```

### 4. Run Migrations

```bash
docker-compose exec app php artisan migrate --force
```

## Health Check

The application includes a health check endpoint:

```bash
curl http://localhost:8080/api/health
```

Or check container health:

```bash
docker inspect --format='{{.State.Health.Status}}' poswms-api
```

## Troubleshooting

### Container Won't Start

```bash
# Check logs
docker-compose logs app

# Check if ports are in use
netstat -tulpn | grep 8080
```

### Database Connection Issues

```bash
# Test database connection
docker-compose exec app php artisan tinker --execute "DB::connection()->getPdo();"

# Check database is ready
docker-compose exec db pg_isready
```

### Permission Issues

```bash
# Fix storage permissions
docker-compose exec app chown -R appuser:appgroup storage bootstrap/cache
docker-compose exec app chmod -R 775 storage bootstrap/cache
```

### Clear Caches

```bash
docker-compose exec app php artisan optimize:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan view:clear
```

## Volume Management

### Backup Volumes

```bash
# Backup PostgreSQL data
docker run --rm -v poswms-backend_postgres_data:/data -v $(pwd):/backup alpine tar czf /backup/postgres-backup.tar.gz -C /data .

# Backup Redis data
docker run --rm -v poswms-backend_redis_data:/data -v $(pwd):/backup alpine tar czf /backup/redis-backup.tar.gz -C /data .
```

### Restore Volumes

```bash
# Restore PostgreSQL
docker run --rm -v poswms-backend_postgres_data:/data -v $(pwd):/backup alpine tar xzf /backup/postgres-backup.tar.gz -C /data

# Restore Redis
docker run --rm -v poswms-backend_redis_data:/data -v $(pwd):/backup alpine tar xzf /backup/redis-backup.tar.gz -C /data
```

### Remove Volumes (Warning: Deletes Data!)

```bash
# Remove all volumes
docker-compose down -v

# Remove specific volume
docker volume rm poswms-backend_postgres_data
```

## Environment Variables

Key environment variables in `.env`:

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_PORT` | 8080 | Host port for the application |
| `DB_PORT_EXTERNAL` | 5432 | Host port for PostgreSQL |
| `REDIS_PORT_EXTERNAL` | 6379 | Host port for Redis |
| `APP_ENV` | production | Application environment |
| `APP_DEBUG` | false | Enable debug mode |
| `DB_CONNECTION` | sqlite | Database driver |
| `DB_HOST` | db | Database host (internal) |
| `DB_DATABASE` | poswms | Database name |
| `DB_USERNAME` | poswms | Database username |
| `DB_PASSWORD` | secret | Database password |

## Security Notes

- Change default database passwords in production
- Use strong `APP_KEY` in production
- Set `APP_DEBUG=false` in production
- Enable `SECURITY_STRICT_MODE=STRICT` in production
- Configure proper CSP headers
- Use HTTPS in production (add reverse proxy)

## Next Steps

1. Configure your API endpoints (see `API_DESIGN.md`)
2. Set up authentication with Laravel Sanctum
3. Configure queue workers for background jobs
4. Set up monitoring and logging
5. Configure CI/CD pipeline

## Support

For issues or questions:
- Check Laravel logs: `docker-compose exec app tail -f storage/logs/laravel.log`
- Review application configuration: `docker-compose exec app php artisan config:clear`
- Check database schema: `docker-compose exec app php artisan migrate:status`
