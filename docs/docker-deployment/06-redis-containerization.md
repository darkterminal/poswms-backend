# Redis Containerization for MSWMS

## Overview

This document covers containerizing Redis for MSWMS, including configuration for caching, sessions, and queue management.

## Why Redis?

**Advantages for MSWMS:**
- Extremely fast (in-memory)
- Rich data structures
- Pub/sub capabilities
- Persistence options (RDB/AOF)
- Built-in replication
- Lua scripting support
- Active community

## Basic Redis Container

### Minimal Configuration

```yaml
# docker-compose.yml
services:
  redis:
    image: redis:7-alpine
    container_name: mswms-redis
    restart: unless-stopped
    ports:
      - "6379:6379"
    command: redis-server --appendonly yes
    volumes:
      - redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

volumes:
  redis_data:
    driver: local
```

## Production Configuration

### Complete Redis Service

```yaml
services:
  redis:
    image: redis:7-alpine
    container_name: mswms-redis
    restart: unless-stopped
    command: >
      redis-server
      --appendonly yes
      --requirepass ${REDIS_PASSWORD}
      --maxmemory 2gb
      --maxmemory-policy allkeys-lru
      --tcp-keepalive 300
      --timeout 0
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
      - ./docker/redis/redis.conf:/usr/local/etc/redis/redis.conf:ro
    networks:
      - mswms-network
    healthcheck:
      test: ["CMD", "redis-cli", "-a", "${REDIS_PASSWORD}", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 10s
    deploy:
      resources:
        limits:
          cpus: '2.0'
          memory: 2G
```

### Redis Configuration File

**docker/redis/redis.conf:**
```ini
# Redis Production Configuration

# Network
bind 0.0.0.0
port 6379
protected-mode yes
tcp-backlog 511
timeout 0
tcp-keepalive 300

# General
daemonize no
supervised systemd
pidfile /var/run/redis/redis-server.pid
loglevel notice
logfile ""

# Snapshotting (RDB)
save 900 1
save 300 10
save 60 10000
stop-writes-on-bgsave-error yes
rdbcompression yes
rdbchecksum yes
dbfilename dump.rdb
dir /data

# Replication
replica-serve-stale-data yes
replica-read-only yes
repl-diskless-sync no

# Security
requirepass YOUR_SECURE_PASSWORD
rename-command FLUSHDB ""
rename-command FLUSHALL ""
rename-command DEBUG ""
rename-command CONFIG ""

# Memory Management
maxmemory 2gb
maxmemory-policy allkeys-lru

# Lazy Freeing
lazyfree-lazy-eviction yes
lazyfree-lazy-expire yes
lazyfree-lazy-server-del yes
replica-lazy-flush yes

# Append Only Mode (AOF)
appendonly yes
appendfilename "appendonly.aof"
appendfsync everysec
no-appendfsync-on-rewrite no
auto-aof-rewrite-percentage 100
auto-aof-rewrite-min-size 64mb
aof-load-truncated yes
aof-use-rdb-preamble yes

# Lua Scripting
lua-time-limit 5000

# Slow Log
slowlog-log-slower-than 10000
slowlog-max-len 256

# Latency Monitor
latency-monitor-threshold 100

# Event Notification
notify-keyspace-events "Ex"

# Advanced Config
hash-max-ziplist-entries 512
hash-max-ziplist-value 64
list-max-ziplist-size -2
list-compress-depth 0
set-max-intset-entries 512
zset-max-ziplist-entries 128
zset-max-ziplist-value 64
hll-sparse-max-bytes 3000
stream-node-max-bytes 4096
stream-node-max-entries 100
activerehashing yes
client-output-buffer-limit normal 0 0 0
client-output-buffer-limit replica 256mb 64mb 60
client-output-buffer-limit pubsub 32mb 8mb 60
hz 10
dynamic-hz yes
aof-rewrite-incremental-fsync yes
rdb-save-incremental-fsync yes
```

## Redis for Different Use Cases

### Cache Configuration

```yaml
services:
  redis-cache:
    image: redis:7-alpine
    command: >
      redis-server
      --maxmemory 1gb
      --maxmemory-policy allkeys-lru
      --appendonly no
    volumes:
      - redis_cache_data:/data
```

### Session Storage

```yaml
services:
  redis-session:
    image: redis:7-alpine
    command: >
      redis-server
      --maxmemory 512mb
      --maxmemory-policy allkeys-lru
      --appendonly yes
    volumes:
      - redis_session_data:/data
```

### Queue Backend

```yaml
services:
  redis-queue:
    image: redis:7-alpine
    command: >
      redis-server
      --maxmemory 1gb
      --maxmemory-policy noeviction
      --appendonly yes
    volumes:
      - redis_queue_data:/data
```

## Laravel Integration

### Environment Configuration

```ini
# .env
REDIS_HOST=redis
REDIS_PASSWORD=${REDIS_PASSWORD}
REDIS_PORT=6379

# Cache
CACHE_STORE=redis
CACHE_PREFIX=mswms_prod

# Session
SESSION_DRIVER=redis
SESSION_CONNECTION=default
SESSION_LIFETIME=480

# Queue
QUEUE_CONNECTION=redis
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default
```

### Database Configuration

**config/database.php:**
```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),

    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', 'mswms_prod' . '-database-'),
        'persistent' => env('REDIS_PERSISTENT', false),
    ],

    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', 'redis'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
        'max_retries' => env('REDIS_MAX_RETRIES', 3),
    ],

    'cache' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', 'redis'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '1'),
        'max_retries' => env('REDIS_MAX_RETRIES', 3),
    ],
],
```

## Redis Commands and Usage

### Basic Commands

```bash
# Connect to Redis
docker compose exec redis redis-cli -a YOUR_PASSWORD

# Ping server
redis-cli -a YOUR_PASSWORD ping

# Set/Get value
redis-cli -a YOUR_PASSWORD SET key value
redis-cli -a YOUR_PASSWORD GET key

# Set with expiration
redis-cli -a YOUR_PASSWORD SETEX key 3600 value

# Delete key
redis-cli -a YOUR_PASSWORD DEL key

# List all keys (use with caution)
redis-cli -a YOUR_PASSWORD KEYS "*"

# Better: Scan for keys
redis-cli -a YOUR_PASSWORD SCAN 0 COUNT 100
```

### Cache Management

```bash
# Clear all cache
redis-cli -a YOUR_PASSWORD FLUSHDB

# View memory usage
redis-cli -a YOUR_PASSWORD INFO memory

# View keyspace
redis-cli -a YOUR_PASSWORD INFO keyspace

# Check slow queries
redis-cli -a YOUR_PASSWORD SLOWLOG GET 10
```

### Queue Management

```bash
# View queue length
redis-cli -a YOUR_PASSWORD LLEN queues:default

# View queue items
redis-cli -a YOUR_PASSWORD LRANGE queues:default 0 10

# Clear queue
redis-cli -a YOUR_PASSWORD DEL queues:default
```

## Monitoring

### Redis CLI Monitoring

```bash
# Real-time monitoring
redis-cli -a YOUR_PASSWORD MONITOR

# Stats
redis-cli -a YOUR_PASSWORD INFO stats

# Clients
redis-cli -a YOUR_PASSWORD INFO clients

# Persistence
redis-cli -a YOUR_PASSWORD INFO persistence

# Memory
redis-cli -a YOUR_PASSWORD INFO memory

# CPU
redis-cli -a YOUR_PASSWORD INFO cpu
```

### Redis Commander (Web UI)

```yaml
services:
  redis-commander:
    image: rediscommander/redis-commander:latest
    container_name: mswms-redis-commander
    restart: unless-stopped
    environment:
      REDIS_HOSTS: local:redis:${REDIS_PASSWORD}
    ports:
      - "8082:8081"
    networks:
      - mswms-network
    depends_on:
      - redis
```

Access at: `http://localhost:8082`

### Prometheus Metrics (Optional)

```yaml
services:
  redis-exporter:
    image: oliver006/redis_exporter:latest
    container_name: mswms-redis-exporter
    command:
      - --redis.addr=redis
      - --redis.password=${REDIS_PASSWORD}
    ports:
      - "9121:9121"
    networks:
      - mswms-network
    depends_on:
      - redis
```

## Performance Optimization

### Memory Optimization

```ini
# redis.conf
maxmemory 2gb
maxmemory-policy allkeys-lru

# Enable lazy freeing
lazyfree-lazy-eviction yes
lazyfree-lazy-expire yes
```

### Persistence Optimization

```ini
# RDB + AOF hybrid
save 900 1
save 300 10
save 60 10000

appendonly yes
appendfsync everysec
```

### Network Optimization

```ini
tcp-keepalive 300
timeout 0
tcp-backlog 511
```

## Backup and Restore

### Backup Script

**docker/redis/backup.sh:**
```bash
#!/bin/bash
# Redis Backup Script

set -e

BACKUP_DIR="/var/backups/redis"
DATE=$(date +%Y%m%d_%H%M%S)
REDIS_PASSWORD="${REDIS_PASSWORD}"

# Create backup directory
mkdir -p $BACKUP_DIR

# Trigger BGSAVE
redis-cli -a $REDIS_PASSWORD BGSAVE

# Wait for BGSAVE
sleep 5

# Copy RDB file
cp /data/dump.rdb $BACKUP_DIR/dump_${DATE}.rdb

# Compress
gzip $BACKUP_DIR/dump_${DATE}.rdb

# Upload to S3 (optional)
if command -v aws &> /dev/null; then
    aws s3 cp $BACKUP_DIR/dump_${DATE}.rdb.gz s3://your-bucket/redis/
fi

# Delete old backups
find $BACKUP_DIR -name "*.gz" -mtime +7 -delete

echo "Backup completed: dump_${DATE}.rdb.gz"
```

### Restore Script

**docker/redis/restore.sh:**
```bash
#!/bin/bash
# Redis Restore Script

set -e

BACKUP_FILE=$1
REDIS_PASSWORD="${REDIS_PASSWORD}"

if [ -z "$BACKUP_FILE" ]; then
    echo "Usage: $0 <backup_file.rdb.gz>"
    exit 1
fi

# Decompress
gunzip -c $BACKUP_FILE > /tmp/dump.rdb

# Stop Redis
redis-cli -a $REDIS_PASSWORD SHUTDOWN NOSAVE || true

# Copy RDB file
cp /tmp/dump.rdb /data/dump.rdb

# Start Redis
redis-server --daemonize yes

echo "Restore completed"
```

## Security

### Authentication

```ini
# redis.conf
requirepass YOUR_SECURE_32_CHAR_PASSWORD
```

### Disable Dangerous Commands

```ini
# redis.conf
rename-command FLUSHDB ""
rename-command FLUSHALL ""
rename-command DEBUG ""
rename-command CONFIG ""
rename-command KEYS ""
```

### Network Security

```yaml
# docker-compose.yml
networks:
  mswms-network:
    driver: bridge
    internal: false  # Set to true for isolated network

# Only expose to necessary services
services:
  redis:
    networks:
      - mswms-network
    # Remove ports section for production
    # ports:
    #   - "6379:6379"
```

## Troubleshooting

### Connection Issues

```bash
# Check if Redis is running
docker compose ps redis

# View logs
docker compose logs redis

# Test connection
docker compose exec redis redis-cli -a YOUR_PASSWORD ping

# Check memory
docker compose exec redis redis-cli -a YOUR_PASSWORD INFO memory
```

### Memory Issues

```bash
# Check memory usage
redis-cli -a YOUR_PASSWORD INFO memory

# Find large keys
redis-cli -a YOUR_PASSWORD --bigkeys

# Clear expired keys
redis-cli -a YOUR_PASSWORD MEMORY DOCTOR

# Increase memory limit
redis-cli -a YOUR_PASSWORD CONFIG SET maxmemory 4gb
```

### Persistence Issues

```bash
# Check AOF status
redis-cli -a YOUR_PASSWORD INFO persistence

# Force BGSAVE
redis-cli -a YOUR_PASSWORD BGSAVE

# Check last save time
redis-cli -a YOUR_PASSWORD LASTSAVE
```

### Slow Queries

```bash
# View slow log
redis-cli -a YOUR_PASSWORD SLOWLOG GET 10

# Clear slow log
redis-cli -a YOUR_PASSWORD SLOWLOG RESET

# Check latency
redis-cli -a YOUR_PASSWORD --intrinsic-latency 100
```

---

**Previous Section**: [← Database Containerization](05-database-containerization.md)  
**Next Section**: [Nginx Reverse Proxy →](07-nginx-reverse-proxy.md)
