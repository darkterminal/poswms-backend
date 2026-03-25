# Redis Cache Setup and Configuration

## Overview

This document provides comprehensive instructions for installing, configuring, and optimizing Redis for MSWMS. Redis is used for caching, session storage, and queue management in staging and production environments.

## Redis Installation

### Ubuntu/Debian Installation

```bash
# Add Redis repository
sudo apt update
sudo apt install -y lsb-release curl gpg

curl -fsSL https://packages.redis.io/gpg | sudo gpg --dearmor -o /usr/share/keyrings/redis-archive-keyring.gpg
echo "deb [signed-by=/usr/share/keyrings/redis-archive-keyring.gpg] https://packages.redis.io/deb $(lsb_release -cs) main" | sudo tee /etc/apt/sources.list.d/redis.list

# Install Redis
sudo apt update
sudo apt install -y redis

# Start and enable service
sudo systemctl start redis
sudo systemctl enable redis
sudo systemctl status redis
```

### CentOS/RHEL Installation

```bash
# Add Redis repository
sudo yum install -y epel-release
sudo yum install -y redis

# Start and enable service
sudo systemctl start redis
sudo systemctl enable redis
sudo systemctl status redis
```

### Install PHP Redis Extension

```bash
# Ubuntu/Debian
sudo apt install -y php8.3-redis

# CentOS/RHEL
sudo yum install -y php-pecl-redis

# Restart PHP-FPM
sudo systemctl restart php8.3-fpm

# Verify installation
php -m | grep redis
```

## Redis Configuration

### Configuration File Location

- **Ubuntu/Debian**: `/etc/redis/redis.conf`
- **CentOS/RHEL**: `/etc/redis.conf`

### Basic Configuration

**Edit Redis Configuration:**
```bash
sudo nano /etc/redis/redis.conf
```

**Staging Configuration:**
```conf
# Network Settings
bind 127.0.0.1
port 6379
protected-mode yes
timeout 0
tcp-keepalive 300

# General Settings
daemonize yes
supervised systemd
pidfile /var/run/redis/redis-server.pid
loglevel notice
logfile /var/log/redis/redis-server.log

# Database Settings
databases 16

# Snapshotting (RDB Persistence)
save 900 1
save 300 10
save 60 10000
stop-writes-on-bgsave-error yes
rdbcompression yes
rdbchecksum yes
dbfilename dump.rdb
dir /var/lib/redis

# Replication
replica-serve-stale-data yes
replica-read-only yes
repl-diskless-sync no

# Security
requirepass your_staging_redis_password
rename-command FLUSHDB ""
rename-command FLUSHALL ""
rename-command DEBUG ""

# Memory Management
maxmemory 1gb
maxmemory-policy allkeys-lru

# Lazy Freeing
lazyfree-lazy-eviction no
lazyfree-lazy-expire no
lazyfree-lazy-server-del no
replica-lazy-flush no

# Append Only Mode (AOF Persistence)
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

# Cluster (Disabled for Staging)
cluster-enabled no

# Slow Log
slowlog-log-slower-than 10000
slowlog-max-len 128

# Latency Monitor
latency-monitor-threshold 100

# Event Notification
notify-keyspace-events ""

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

**Production Configuration:**
```conf
# Network Settings
bind 0.0.0.0
port 6379
protected-mode yes
timeout 0
tcp-keepalive 300

# General Settings
daemonize yes
supervised systemd
pidfile /var/run/redis/redis-server.pid
loglevel notice
logfile /var/log/redis/redis-server.log

# Database Settings
databases 16

# Snapshotting (RDB Persistence)
save 900 1
save 300 10
save 60 10000
stop-writes-on-bgsave-error yes
rdbcompression yes
rdbchecksum yes
dbfilename dump.rdb
dir /var/lib/redis

# Replication
replica-serve-stale-data yes
replica-read-only yes
repl-diskless-sync no

# Security
requirepass your_production_redis_password
rename-command FLUSHDB ""
rename-command FLUSHALL ""
rename-command DEBUG ""
rename-command CONFIG ""

# TLS/SSL (Production Only)
# tls-port 6380
# tls-cert-file /etc/redis/tls/redis.crt
# tls-key-file /etc/redis/tls/redis.key
# tls-ca-cert-file /etc/redis/tls/ca.crt
# tls-auth-clients yes

# Memory Management
maxmemory 2gb
maxmemory-policy allkeys-lru

# Lazy Freeing
lazyfree-lazy-eviction yes
lazyfree-lazy-expire yes
lazyfree-lazy-server-del yes
replica-lazy-flush yes

# Append Only Mode (AOF Persistence)
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

# Cluster (Production)
# cluster-enabled yes
# cluster-config-file nodes.conf
# cluster-node-timeout 5000
# cluster-replica-validity-factor 10
# cluster-migration-barrier 1
# cluster-require-full-coverage yes

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

### Restart Redis Service

```bash
sudo systemctl restart redis
sudo systemctl status redis
```

## Redis Security

### 1. Configure Authentication

```bash
# Set password via redis-cli
redis-cli
> CONFIG SET requirepass "your_secure_password"
> ACL SETUSER default on >your_secure_password ~* +@all
> exit

# Test authentication
redis-cli -a your_secure_password ping
```

### 2. Configure Firewall

**UFW (Ubuntu):**
```bash
# Allow Redis from application server only
sudo ufw allow from 192.168.1.0/24 to any port 6379 proto tcp
sudo ufw deny 6379
```

**firewalld (CentOS):**
```bash
sudo firewall-cmd --permanent --add-rich-rule='rule family="ipv4" source address="192.168.1.0/24" port port="6379" protocol="tcp" accept'
sudo firewall-cmd --reload
```

### 3. Disable Dangerous Commands

```conf
# In redis.conf
rename-command FLUSHDB ""
rename-command FLUSHALL ""
rename-command DEBUG ""
rename-command CONFIG ""
rename-command KEYS ""
```

### 4. Enable TLS (Production)

**Generate Certificates:**
```bash
# Create CA
openssl genrsa -out ca.key 4096
openssl req -new -x509 -days 3650 -key ca.key -out ca.crt

# Create server certificate
openssl genrsa -out redis-server.key 2048
openssl req -new -key redis-server.key -out redis-server.csr
openssl x509 -req -days 365 -in redis-server.csr -CA ca.crt -CAkey ca.key -CAcreateserial -out redis-server.crt

# Copy certificates
sudo cp ca.crt /etc/redis/tls/
sudo cp redis-server.crt /etc/redis/tls/
sudo cp redis-server.key /etc/redis/tls/
sudo chown redis:redis /etc/redis/tls/*
sudo chmod 600 /etc/redis/tls/*.key
```

**Configure TLS in redis.conf:**
```conf
tls-port 6380
tls-cert-file /etc/redis/tls/redis-server.crt
tls-key-file /etc/redis/tls/redis-server.key
tls-ca-cert-file /etc/redis/tls/ca.crt
tls-auth-clients yes
tls-replication yes
tls-cluster yes
```

## Laravel Redis Configuration

### Update .env File

```ini
# Redis Configuration
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=your_redis_password
REDIS_PORT=6379
REDIS_CLIENT=phpredis
REDIS_DB=0

# Cache Configuration
CACHE_STORE=redis
CACHE_PREFIX=mswms_staging

# Session Configuration
SESSION_DRIVER=redis
SESSION_CONNECTION=default

# Queue Configuration
QUEUE_CONNECTION=redis
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default
```

### Configure Redis Connections

**config/database.php:**
```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),

    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', 'mswms_staging' . '-database-'),
        'persistent' => env('REDIS_PERSISTENT', false),
    ],

    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
        'max_retries' => env('REDIS_MAX_RETRIES', 3),
        'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
        'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
        'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
    ],

    'cache' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '1'),
        'max_retries' => env('REDIS_MAX_RETRIES', 3),
        'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
        'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
        'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
    ],
],
```

### Test Redis Connection

```bash
# Test from command line
php artisan tinker --execute="Redis::ping();"

# Test cache store
php artisan tinker --execute="Cache::put('test_key', 'test_value', 60); Cache::get('test_key');"

# Test session store
php artisan tinker --execute="Session::put('test', 'value'); Session::get('test');"
```

## Redis Usage in MSWMS

### Cache Configuration

**Cache Frequently Used Data:**
```php
use Illuminate\Support\Facades\Cache;

// Cache tenant configuration
$tenantConfig = Cache::remember(
    "tenant:{$tenantId}:config",
    now()->addHours(24),
    function () use ($tenantId) {
        return TenantConfig::where('tenant_id', $tenantId)->first();
    }
);

// Cache product data
$products = Cache::remember(
    "tenant:{$tenantId}:products:all",
    now()->addHours(1),
    function () use ($tenantId) {
        return Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();
    }
);

// Cache inventory levels
$inventory = Cache::remember(
    "warehouse:{$warehouseId}:inventory",
    now()->addMinutes(30),
    function () use ($warehouseId) {
        return Inventory::where('warehouse_id', $warehouseId)
            ->with('product')
            ->get();
    }
);
```

### Session Storage

**Configure Session Driver:**
```php
// config/session.php
'driver' => env('SESSION_DRIVER', 'redis'),
'connection' => env('SESSION_CONNECTION', 'default'),
'lifetime' => env('SESSION_LIFETIME', 480),
'expire_on_close' => false,
'encrypt' => env('SESSION_ENCRYPT', true),
```

### Queue Configuration

**Configure Queue Driver:**
```php
// config/queue.php
'redis' => [
    'driver' => 'redis',
    'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
    'queue' => env('REDIS_QUEUE', 'default'),
    'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
    'block_for' => null,
    'after_commit' => false,
],
```

**Process Jobs:**
```bash
# Start queue worker
php artisan queue:work redis --tries=3 --timeout=60

# Start multiple workers (supervisor)
php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
```

## Redis Performance Optimization

### Memory Optimization

**1. Set Memory Limit:**
```conf
maxmemory 2gb
maxmemory-policy allkeys-lru
```

**2. Monitor Memory Usage:**
```bash
redis-cli -a your_password INFO memory

# Check memory fragmentation
redis-cli -a your_password INFO stats | grep mem_fragmentation_ratio
```

**3. Clear Expired Keys:**
```bash
# Force expiration
redis-cli -a your_password MEMORY DOCTOR

# Analyze memory usage
redis-cli -a your_password MEMORY STATS
```

### Persistence Optimization

**RDB + AOF Hybrid:**
```conf
# RDB Snapshots
save 900 1
save 300 10
save 60 10000

# AOF
appendonly yes
appendfsync everysec
```

**Backup RDB File:**
```bash
# Copy RDB file
cp /var/lib/redis/dump.rdb /var/backups/redis/dump_$(date +%Y%m%d).rdb

# Compress backup
gzip /var/backups/redis/dump_$(date +%Y%m%d).rdb
```

### Connection Pooling

**Configure Max Clients:**
```conf
maxclients 10000
timeout 0
tcp-keepalive 300
```

## Redis Monitoring

### Redis CLI Commands

```bash
# Check server info
redis-cli -a your_password INFO

# Check memory
redis-cli -a your_password INFO memory

# Check connected clients
redis-cli -a your_password INFO clients

# Check persistence
redis-cli -a your_password INFO persistence

# Check slow log
redis-cli -a your_password SLOWLOG GET 10

# Check keyspace
redis-cli -a your_password INFO keyspace

# Monitor real-time
redis-cli -a your_password MONITOR

# Check database size
redis-cli -a your_password DBSIZE

# List all keys (use with caution in production)
redis-cli -a your_password KEYS "*"

# Better alternative - scan
redis-cli -a your_password SCAN 0 COUNT 100
```

### Redis CLI for Laravel Keys

```bash
# List cache keys
redis-cli -a your_password KEYS "mswms_staging*"

# List session keys
redis-cli -a your_password KEYS "mswms_staging*session*"

# List queue keys
redis-cli -a your_password KEYS "queues:*"

# Delete specific pattern
redis-cli -a your_password KEYS "mswms_staging:cache:*" | xargs redis-cli -a your_password DEL
```

### Redis Monitoring Tools

**Redis Commander (Web UI):**
```bash
# Install via npm
npm install -g redis-commander

# Start Redis Commander
redis-commander --redis-host localhost --redis-port 6379 --redis-password your_password

# Access at http://localhost:8081
```

**phpRedisAdmin:**
```bash
# Clone repository
git clone https://github.com/erikdubbelboer/phpRedisAdmin.git

# Configure config.sample.php
cp config.sample.php config.php
nano config.php

# Access via web browser
```

## Redis Cluster Setup (Production)

### Cluster Configuration

**Enable Cluster Mode:**
```conf
cluster-enabled yes
cluster-config-file nodes.conf
cluster-node-timeout 5000
cluster-replica-validity-factor 10
cluster-migration-barrier 1
cluster-require-full-coverage yes
```

### Create Cluster

```bash
# Start 6 Redis instances (3 masters, 3 replicas)
redis-server /etc/redis/redis-6379.conf
redis-server /etc/redis/redis-6380.conf
redis-server /etc/redis/redis-6381.conf
redis-server /etc/redis/redis-6382.conf
redis-server /etc/redis/redis-6383.conf
redis-server /etc/redis/redis-6384.conf

# Create cluster
redis-cli --cluster create \
    127.0.0.1:6379 127.0.0.1:6380 127.0.0.1:6381 \
    127.0.0.1:6382 127.0.0.1:6383 127.0.0.1:6384 \
    --cluster-replicas 1 \
    -a your_password
```

### Laravel Cluster Configuration

```php
'redis' => [
    'client' => 'phpredis',
    'options' => [
        'cluster' => 'redis',
        'prefix' => 'mswms_production-',
    ],
    'clusters' => [
        'default' => [
            [
                'host' => 'redis-node-1.example.com',
                'password' => 'your_password',
                'port' => 6379,
                'database' => 0,
            ],
            [
                'host' => 'redis-node-2.example.com',
                'password' => 'your_password',
                'port' => 6379,
                'database' => 0,
            ],
            [
                'host' => 'redis-node-3.example.com',
                'password' => 'your_password',
                'port' => 6379,
                'database' => 0,
            ],
        ],
        'cache' => [
            [
                'host' => 'redis-node-4.example.com',
                'password' => 'your_password',
                'port' => 6379,
                'database' => 0,
            ],
        ],
    ],
],
```

## Redis Backup and Recovery

### Backup Script

```bash
#!/bin/bash
# /usr/local/bin/backup-redis.sh

BACKUP_DIR="/var/backups/redis"
DATE=$(date +%Y%m%d_%H%M%S)
REDIS_PASSWORD="your_redis_password"

# Create backup directory
mkdir -p $BACKUP_DIR

# Trigger BGSAVE
redis-cli -a $REDIS_PASSWORD BGSAVE

# Wait for BGSAVE to complete
while [ $(redis-cli -a $REDIS_PASSWORD LASTSAVE) -eq $(redis-cli -a $REDIS_PASSWORD LASTSAVE) ]; do
    sleep 1
done

# Copy RDB file
cp /var/lib/redis/dump.rdb $BACKUP_DIR/dump_${DATE}.rdb

# Compress backup
gzip $BACKUP_DIR/dump_${DATE}.rdb

# Delete backups older than 30 days
find $BACKUP_DIR -name "*.gz" -mtime +30 -delete

echo "Backup completed: dump_${DATE}.rdb.gz"
```

### Recovery Procedure

```bash
# Stop Redis
sudo systemctl stop redis

# Restore RDB file
gunzip /var/backups/redis/dump_20260325.rdb.gz
cp /var/backups/redis/dump_20260325.rdb /var/lib/redis/dump.rdb

# Set permissions
chown redis:redis /var/lib/redis/dump.rdb
chmod 640 /var/lib/redis/dump.rdb

# Start Redis
sudo systemctl start redis

# Verify data
redis-cli -a your_password DBSIZE
```

## Troubleshooting

### Common Issues

#### 1. Connection Refused
```bash
# Check Redis is running
sudo systemctl status redis

# Check Redis is listening
netstat -tlnp | grep 6379

# Test connection
redis-cli -h your_host -p 6379 -a your_password ping
```

#### 2. Authentication Failed
```bash
# Verify password
redis-cli -a your_password ping

# Reset password
redis-cli
> CONFIG SET requirepass "new_password"
> exit
```

#### 3. Out of Memory
```bash
# Check memory usage
redis-cli -a your_password INFO memory

# Clear expired keys
redis-cli -a your_password MEMORY DOCTOR

# Increase memory limit
redis-cli -a your_password CONFIG SET maxmemory 4gb
```

#### 4. Slow Queries
```bash
# Check slow log
redis-cli -a your_password SLOWLOG GET 10

# Clear slow log
redis-cli -a your_password SLOWLOG RESET

# Monitor real-time
redis-cli -a your_password --intrinsic-latency 100
```

---

**Previous Section**: [← Database Setup](03-database-setup.md)  
**Next Section**: [Web Server Setup →](05-web-server-setup.md)
