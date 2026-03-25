# Database Containerization for MSWMS

## Overview

This document covers containerizing PostgreSQL for MSWMS, including configuration, optimization, and best practices for production deployments.

## Why PostgreSQL?

**Advantages for MSWMS:**
- Excellent for complex queries
- Strong data integrity features
- Built-in full-text search
- JSON/JSONB support
- Advanced indexing (GIN, GiST)
- MVCC (Multi-Version Concurrency Control)
- Active community and extensions

## Basic PostgreSQL Container

### Minimal Configuration

```yaml
# docker-compose.yml
services:
  postgres:
    image: postgres:15-alpine
    container_name: mswms-postgres
    restart: unless-stopped
    environment:
      POSTGRES_DB: mswms_dev
      POSTGRES_USER: mswms_user
      POSTGRES_PASSWORD: mswms_password
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U mswms_user -d mswms_dev"]
      interval: 10s
      timeout: 5s
      retries: 5

volumes:
  postgres_data:
    driver: local
```

## Production Configuration

### Complete PostgreSQL Service

```yaml
services:
  postgres:
    image: postgres:15-alpine
    container_name: mswms-postgres
    restart: unless-stopped
    environment:
      # Database configuration
      POSTGRES_DB: mswms_production
      POSTGRES_USER: mswms_prod_user
      POSTGRES_PASSWORD: ${DB_PASSWORD}
      
      # PostgreSQL settings
      POSTGRES_INITDB_ARGS: "--encoding=UTF8 --lc-collate=C --lc-ctype=C"
      POSTGRES_HOST_AUTH_METHOD: "scram-sha-256"
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./docker/postgres/init.sql:/docker-entrypoint-initdb.d/init.sql:ro
      - ./docker/postgres/postgresql.conf:/etc/postgresql/postgresql.conf:ro
      - ./docker/postgres/pg_hba.conf:/etc/postgresql/pg_hba.conf:ro
    command: postgres -c config_file=/etc/postgresql/postgresql.conf
    networks:
      - mswms-network
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U mswms_prod_user -d mswms_production"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 30s
    deploy:
      resources:
        limits:
          cpus: '4.0'
          memory: 4G
```

### PostgreSQL Configuration File

**docker/postgres/postgresql.conf:**
```ini
# PostgreSQL Production Configuration

# Connection Settings
listen_addresses = '*'
port = 5432
max_connections = 200

# Memory Settings
shared_buffers = 1GB              # 25% of RAM
effective_cache_size = 3GB        # 75% of RAM
work_mem = 64MB                   # For complex queries
maintenance_work_mem = 512MB      # For VACUUM, CREATE INDEX

# Write Ahead Log
wal_level = replica
max_wal_senders = 3
wal_keep_size = 128MB
checkpoint_completion_target = 0.9

# Query Planning
random_page_cost = 1.1            # For SSD
effective_io_concurrency = 200    # For SSD
default_statistics_target = 100

# Logging
log_destination = 'stderr'
logging_collector = on
log_directory = 'log'
log_filename = 'postgresql-%Y-%m-%d_%H%M%S.log'
log_rotation_age = 1d
log_rotation_size = 100MB
log_min_duration_statement = 100  # Log queries > 100ms
log_checkpoints = on
log_connections = on
log_disconnections = on
log_lock_waits = on
log_temp_files = 0

# Autovacuum
autovacuum = on
autovacuum_max_workers = 3
autovacuum_naptime = 1min
autovacuum_vacuum_threshold = 50
autovacuum_analyze_threshold = 50
autovacuum_vacuum_scale_factor = 0.1
autovacuum_analyze_scale_factor = 0.05

# Replication (optional)
# max_wal_senders = 3
# max_replication_slots = 3
# hot_standby = on
```

### pg_hba.conf (Authentication)

**docker/postgres/pg_hba.conf:**
```conf
# PostgreSQL Client Authentication Configuration

# TYPE  DATABASE        USER            ADDRESS                 METHOD

# Local connections
local   all             postgres                                peer
local   all             all                                     peer

# IPv4 local connections
host    all             all             127.0.0.1/32            scram-sha-256

# IPv6 local connections
host    all             all             ::1/128                 scram-sha-256

# Container network connections
host    all             all             172.16.0.0/12           scram-sha-256

# Application server connections
host    mswms_production mswms_prod_user 0.0.0.0/0              scram-sha-256
```

## Initialization Script

### docker/postgres/init.sql

```sql
-- PostgreSQL Initialization Script
-- Runs automatically on first container start

-- Enable extensions
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Create application database (if not exists)
-- Note: This runs before the app connects

-- Set up monitoring views
CREATE OR REPLACE VIEW public.database_size AS
SELECT 
    datname,
    pg_size_pretty(pg_database_size(datname)) as size,
    pg_database_size(datname) as size_bytes
FROM pg_database
ORDER BY size_bytes DESC;

-- Grant permissions
GRANT CONNECT ON DATABASE mswms_production TO mswms_prod_user;
GRANT USAGE ON SCHEMA public TO mswms_prod_user;
```

## Database Management

### Backup Scripts

**docker/postgres/backup.sh:**
```bash
#!/bin/bash
# PostgreSQL Backup Script

set -e

BACKUP_DIR="/var/backups/postgresql"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="mswms_production"
DB_USER="mswms_prod_user"
RETENTION_DAYS=30

# Create backup directory
mkdir -p $BACKUP_DIR

# Create backup
echo "Starting backup of $DB_NAME..."
pg_dump -h localhost -U $DB_USER -F c -b -v $DB_NAME > $BACKUP_DIR/${DB_NAME}_${DATE}.dump

# Compress backup
gzip $BACKUP_DIR/${DB_NAME}_${DATE}.dump

# Upload to S3 (optional)
if command -v aws &> /dev/null; then
    aws s3 cp $BACKUP_DIR/${DB_NAME}_${DATE}.dump.gz s3://your-bucket/postgresql/
fi

# Delete old backups
find $BACKUP_DIR -name "*.dump.gz" -mtime +$RETENTION_DAYS -delete

echo "Backup completed: ${DB_NAME}_${DATE}.dump.gz"
```

### Restore Script

**docker/postgres/restore.sh:**
```bash
#!/bin/bash
# PostgreSQL Restore Script

set -e

BACKUP_FILE=$1
DB_NAME="mswms_production"
DB_USER="mswms_prod_user"

if [ -z "$BACKUP_FILE" ]; then
    echo "Usage: $0 <backup_file.dump.gz>"
    exit 1
fi

echo "Starting restore from: $BACKUP_FILE"

# Decompress
gunzip -c $BACKUP_FILE > /tmp/restore_backup.dump

# Restore
pg_restore -h localhost -U $DB_USER -d $DB_NAME /tmp/restore_backup.dump

# Clean up
rm /tmp/restore_backup.dump

echo "Restore completed"
```

## Performance Optimization

### Indexing Strategy

```sql
-- Create indexes for MSWMS tables

-- Tenants
CREATE INDEX idx_tenants_status ON tenants(status);
CREATE INDEX idx_tenants_created_at ON tenants(created_at);

-- Users
CREATE INDEX idx_users_tenant_id ON users(tenant_id);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);

-- Orders
CREATE INDEX idx_orders_tenant_id ON orders(tenant_id);
CREATE INDEX idx_orders_store_id ON orders(store_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_created_at ON orders(created_at);
CREATE INDEX idx_orders_tenant_status_created ON orders(tenant_id, status, created_at);

-- Inventory
CREATE INDEX idx_inventory_product_id ON inventory(product_id);
CREATE INDEX idx_inventory_warehouse_id ON inventory(warehouse_id);
CREATE INDEX idx_inventory_product_warehouse ON inventory(product_id, warehouse_id);
```

### Query Optimization

```sql
-- Enable query logging for slow queries
ALTER SYSTEM SET log_min_duration_statement = 100;
SELECT pg_reload_conf();

-- View slow queries
SELECT query, calls, total_exec_time, mean_exec_time
FROM pg_stat_statements
ORDER BY mean_exec_time DESC
LIMIT 10;

-- Analyze tables
ANALYZE tenants;
ANALYZE users;
ANALYZE orders;
ANALYZE inventory;

-- Vacuum tables
VACUUM ANALYZE;
```

## Monitoring

### Health Checks

```yaml
healthcheck:
  test: ["CMD-SHELL", "pg_isready -U mswms_prod_user -d mswms_production"]
  interval: 10s
  timeout: 5s
  retries: 5
  start_period: 30s
```

### Prometheus Metrics (Optional)

```yaml
# Add postgres-exporter for Prometheus
postgres-exporter:
  image: prometheuscommunity/postgres-exporter:latest
  container_name: mswms-postgres-exporter
  environment:
    DATA_SOURCE_NAME: "postgresql://mswms_prod_user:${DB_PASSWORD}@postgres:5432/mswms_production?sslmode=disable"
  ports:
    - "9187:9187"
  networks:
    - mswms-network
  depends_on:
    - postgres
```

### Useful Queries

```sql
-- Database size
SELECT pg_size_pretty(pg_database_size('mswms_production'));

-- Table sizes
SELECT 
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size
FROM pg_tables
WHERE schemaname = 'public'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;

-- Active connections
SELECT count(*) FROM pg_stat_activity;

-- Long running queries
SELECT pid, now() - pg_stat_activity.query_start AS duration, query
FROM pg_stat_activity
WHERE state != 'idle'
ORDER BY duration DESC;

-- Cache hit ratio
SELECT 
    sum(heap_blks_hit) / (sum(heap_blks_hit) + sum(heap_blks_read)) as ratio
FROM pg_statio_user_tables;
```

## Troubleshooting

### Connection Issues

```bash
# Check if PostgreSQL is running
docker compose ps postgres

# View logs
docker compose logs postgres

# Test connection
docker compose exec postgres psql -U mswms_prod_user -d mswms_production

# Check listening ports
docker compose exec postgres netstat -tlnp | grep 5432
```

### Performance Issues

```sql
-- Check for locks
SELECT * FROM pg_locks WHERE NOT granted;

-- Kill long-running query
SELECT pg_terminate_backend(pid) 
FROM pg_stat_activity 
WHERE state = 'active' 
AND query_start < NOW() - INTERVAL '30 minutes';

-- Check bloat
SELECT 
    schemaname || '.' || relname AS table,
    n_dead_tup AS dead_tuples
FROM pg_stat_user_tables
ORDER BY n_dead_tup DESC;
```

### Backup/Restore Issues

```bash
# List available backups
ls -lht /var/backups/postgresql/

# Test backup integrity
pg_restore --list backup.dump

# Restore to test database first
createdb test_restore
pg_restore -d test_restore backup.dump
```

---

**Previous Section**: [← Environment Configuration](04-environment-configuration.md)  
**Next Section**: [Redis Containerization →](06-redis-containerization.md)
