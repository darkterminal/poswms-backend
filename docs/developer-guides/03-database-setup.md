# Database Setup and Configuration

## Overview

This document provides comprehensive instructions for setting up and configuring the database for MSWMS in staging and production environments. The system supports both PostgreSQL (recommended) and MySQL/MariaDB.

## Database Selection

### PostgreSQL (Recommended)

**Advantages:**
- Better support for complex queries
- Superior JSON/JSONB support
- Advanced indexing capabilities
- Better concurrency control (MVCC)
- Built-in full-text search
- Strong data integrity features

**Recommended for:**
- Production environments
- High-traffic applications
- Complex reporting requirements
- Multi-tenant architectures

### MySQL/MariaDB

**Advantages:**
- Widely supported
- Easy to set up and manage
- Good performance for read-heavy workloads
- Familiar to most developers

**Recommended for:**
- Staging environments
- Smaller deployments
- Teams with MySQL expertise

## PostgreSQL Installation and Configuration

### Ubuntu/Debian Installation

```bash
# Add PostgreSQL repository
wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo apt-key add -
echo "deb http://apt.postgresql.org/pub/repos/apt/ $(lsb_release -cs)-pgdg main" | sudo tee /etc/apt/sources.list.d/pgdg.list

# Update and install
sudo apt update
sudo apt install -y postgresql-15 postgresql-contrib-15 postgresql-client-15

# Start and enable service
sudo systemctl start postgresql
sudo systemctl enable postgresql
sudo systemctl status postgresql
```

### CentOS/RHEL Installation

```bash
# Add PostgreSQL repository
sudo yum install -y https://download.postgresql.org/pub/repos/yum/reporpms/EL-9-x86_64/pgdg-redhat-repo-latest.noarch.rpm

# Disable default PostgreSQL module
sudo dnf -qy module disable postgresql

# Install PostgreSQL
sudo yum install -y postgresql15 postgresql15-server postgresql15-contrib

# Initialize database
sudo /usr/pgsql-15/bin/postgresql-15-setup --initdb

# Start and enable service
sudo systemctl start postgresql-15
sudo systemctl enable postgresql-15
sudo systemctl status postgresql-15
```

### PostgreSQL Configuration

**Edit postgresql.conf:**
```bash
sudo nano /etc/postgresql/15/main/postgresql.conf
```

**Key Configuration Settings:**
```ini
# Connection Settings
listen_addresses = '*'
port = 5432
max_connections = 200

# Memory Settings
shared_buffers = 256MB              # 25% of RAM for dedicated DB server
effective_cache_size = 768MB        # 75% of RAM
work_mem = 16MB                     # For complex queries
maintenance_work_mem = 128MB        # For VACUUM, CREATE INDEX

# Write Ahead Log
wal_level = replica
max_wal_senders = 3
wal_keep_size = 64MB

# Query Logging
log_statement = 'ddl'
log_min_duration_statement = 1000   # Log queries > 1 second
log_checkpoints = on
log_connections = on
log_disconnections = on
log_lock_waits = on

# Autovacuum
autovacuum = on
autovacuum_max_workers = 3
autovacuum_naptime = 1min
autovacuum_vacuum_threshold = 50
autovacuum_analyze_threshold = 50
```

**Edit pg_hba.conf (Host-Based Authentication):**
```bash
sudo nano /etc/postgresql/15/main/pg_hba.conf
```

**Configuration:**
```conf
# TYPE  DATABASE        USER            ADDRESS                 METHOD

# Local connections
local   all             postgres                                peer
local   all             all                                     peer

# IPv4 local connections
host    all             all             127.0.0.1/32            scram-sha-256

# IPv6 local connections
host    all             all             ::1/128                 scram-sha-256

# Application server connections (replace with your server IP)
host    mswms_staging   mswms_staging_user  192.168.1.0/24      scram-sha-256
host    mswms_production mswms_prod_user   192.168.1.0/24      scram-sha-256

# SSL connections (production)
hostssl all             all             0.0.0.0/0               scram-sha-256
```

**Restart PostgreSQL:**
```bash
sudo systemctl restart postgresql
```

### Create Database and User

**Staging Environment:**
```bash
# Connect to PostgreSQL
sudo -u postgres psql

-- Create database
CREATE DATABASE mswms_staging
    WITH 
    ENCODING = 'UTF8'
    LC_COLLATE = 'en_US.UTF-8'
    LC_CTYPE = 'en_US.UTF-8'
    TEMPLATE = template0;

-- Create user
CREATE USER mswms_staging_user WITH
    LOGIN
    PASSWORD 'your_secure_password'
    NOSUPERUSER
    NOCREATEDB
    NOCREATEROLE
    INHERIT
    NOREPLICATION
    CONNECTION LIMIT -1;

-- Grant privileges
GRANT CONNECT ON DATABASE mswms_staging TO mswms_staging_user;
GRANT USAGE ON SCHEMA public TO mswms_staging_user;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO mswms_staging_user;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO mswms_staging_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO mswms_staging_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO mswms_staging_user;

-- Enable extensions
\c mswms_staging
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- Exit
\q
```

**Production Environment:**
```bash
# Connect to PostgreSQL
sudo -u postgres psql

-- Create database
CREATE DATABASE mswms_production
    WITH 
    ENCODING = 'UTF8'
    LC_COLLATE = 'en_US.UTF-8'
    LC_CTYPE = 'en_US.UTF-8'
    TEMPLATE = template0;

-- Create user
CREATE USER mswms_prod_user WITH
    LOGIN
    PASSWORD 'your_secure_production_password'
    NOSUPERUSER
    NOCREATEDB
    NOCREATEROLE
    INHERIT
    NOREPLICATION
    CONNECTION LIMIT -1;

-- Grant privileges
GRANT CONNECT ON DATABASE mswms_production TO mswms_prod_user;
GRANT USAGE ON SCHEMA public TO mswms_prod_user;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO mswms_prod_user;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO mswms_prod_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO mswms_prod_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO mswms_prod_user;

-- Enable extensions
\c mswms_production
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- Exit
\q
```

## MySQL Installation and Configuration

### Ubuntu/Debian Installation

```bash
# Install MySQL Server
sudo apt update
sudo apt install -y mysql-server

# Secure installation
sudo mysql_secure_installation

# Start and enable service
sudo systemctl start mysql
sudo systemctl enable mysql
sudo systemctl status mysql
```

### MySQL Configuration

**Edit my.cnf:**
```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

**Key Configuration Settings:**
```ini
[mysqld]
# Basic Settings
bind-address = 0.0.0.0
port = 3306
max_connections = 200

# Character Set
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci

# InnoDB Settings
innodb_buffer_pool_size = 1G          # 70% of RAM for dedicated DB
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 1
innodb_flush_method = O_DIRECT
innodb_file_per_table = 1

# Query Cache (MySQL 5.7, disabled in 8.0)
# query_cache_type = 1
# query_cache_size = 64M

# Logging
slow_query_log = 1
slow_query_log_file = /var/log/mysql/mysql-slow.log
long_query_time = 2
log_queries_not_using_indexes = 1

# Binary Logging (for replication)
log_bin = /var/log/mysql/mysql-bin.log
server_id = 1
binlog_format = ROW
expire_logs_days = 7
```

**Restart MySQL:**
```bash
sudo systemctl restart mysql
```

### Create Database and User

**Staging Environment:**
```bash
# Connect to MySQL
sudo mysql -u root -p

-- Create database
CREATE DATABASE mswms_staging 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER 'mswms_staging_user'@'%' 
    IDENTIFIED BY 'your_secure_password';

-- Grant privileges
GRANT ALL PRIVILEGES ON mswms_staging.* TO 'mswms_staging_user'@'%';
FLUSH PRIVILEGES;

-- Exit
EXIT;
```

**Production Environment:**
```bash
# Connect to MySQL
sudo mysql -u root -p

-- Create database
CREATE DATABASE mswms_production 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER 'mswms_prod_user'@'%' 
    IDENTIFIED BY 'your_secure_production_password';

-- Grant privileges
GRANT ALL PRIVILEGES ON mswms_production.* TO 'mswms_prod_user'@'%';
FLUSH PRIVILEGES;

-- Exit
EXIT;
```

## Laravel Database Configuration

### Update .env File

**PostgreSQL:**
```ini
DB_CONNECTION=pgsql
DB_HOST=your_database_host
DB_PORT=5432
DB_DATABASE=mswms_staging
DB_USERNAME=mswms_staging_user
DB_PASSWORD=your_secure_password
```

**MySQL:**
```ini
DB_CONNECTION=mysql
DB_HOST=your_database_host
DB_PORT=3306
DB_DATABASE=mswms_staging
DB_USERNAME=mswms_staging_user
DB_PASSWORD=your_secure_password
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
```

### Run Migrations

```bash
# Test database connection
php artisan tinker --execute="DB::connection()->getPdo();"

# Run migrations
php artisan migrate --force

# Check migration status
php artisan migrate:status

# Seed database (optional)
php artisan db:seed
```

## Database Performance Optimization

### PostgreSQL Optimization

**Create Indexes for Common Queries:**
```sql
-- Tenants
CREATE INDEX idx_tenants_status ON tenants(status);
CREATE INDEX idx_tenants_created_at ON tenants(created_at);

-- Users
CREATE INDEX idx_users_tenant_id ON users(tenant_id);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);

-- Stores
CREATE INDEX idx_stores_tenant_id ON stores(tenant_id);
CREATE INDEX idx_stores_is_active ON stores(is_active);

-- Warehouses
CREATE INDEX idx_warehouses_tenant_id ON warehouses(tenant_id);

-- Products
CREATE INDEX idx_products_tenant_id ON products(tenant_id);
CREATE INDEX idx_products_sku ON products(sku);
CREATE INDEX idx_products_category_id ON products(category_id);

-- Inventory
CREATE INDEX idx_inventory_tenant_id ON inventory(tenant_id);
CREATE INDEX idx_inventory_product_id ON inventory(product_id);
CREATE INDEX idx_inventory_warehouse_id ON inventory(warehouse_id);
CREATE INDEX idx_inventory_store_id ON inventory(store_id);

-- Orders
CREATE INDEX idx_orders_tenant_id ON orders(tenant_id);
CREATE INDEX idx_orders_store_id ON orders(store_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_created_at ON orders(created_at);

-- Order Items
CREATE INDEX idx_order_items_order_id ON order_items(order_id);
CREATE INDEX idx_order_items_product_id ON order_items(product_id);
```

**Analyze Tables:**
```sql
ANALYZE tenants;
ANALYZE users;
ANALYZE stores;
ANALYZE warehouses;
ANALYZE products;
ANALYZE inventory;
ANALYZE orders;
ANALYZE order_items;
```

**Vacuum Tables:**
```sql
VACUUM ANALYZE;
```

### MySQL Optimization

**Create Indexes:**
```sql
-- Same indexes as PostgreSQL above

-- Additional InnoDB optimization
SET GLOBAL innodb_buffer_pool_size = 1073741824;  -- 1GB
SET GLOBAL innodb_log_file_size = 268435456;      -- 256MB
```

**Analyze Tables:**
```sql
ANALYZE TABLE tenants;
ANALYZE TABLE users;
ANALYZE TABLE stores;
ANALYZE TABLE warehouses;
ANALYZE TABLE products;
ANALYZE TABLE inventory;
ANALYZE TABLE orders;
ANALYZE TABLE order_items;
```

**Optimize Tables:**
```sql
OPTIMIZE TABLE tenants;
OPTIMIZE TABLE users;
OPTIMIZE TABLE stores;
OPTIMIZE TABLE warehouses;
OPTIMIZE TABLE products;
OPTIMIZE TABLE inventory;
OPTIMIZE TABLE orders;
OPTIMIZE TABLE order_items;
```

## Database Security

### PostgreSQL Security

**1. Enable SSL:**
```ini
# postgresql.conf
ssl = on
ssl_cert_file = '/etc/ssl/certs/ssl-cert-snakeoil.pem'
ssl_key_file = '/etc/ssl/private/ssl-cert-snakeoil.key'
ssl_min_protocol_version = 'TLSv1.2'
```

**2. Configure Password Encryption:**
```ini
# postgresql.conf
password_encryption = scram-sha-256
```

**3. Limit Connections:**
```sql
ALTER USER mswms_prod_user CONNECTION LIMIT 100;
```

**4. Enable Audit Logging:**
```sql
-- Install pgAudit extension
CREATE EXTENSION pgaudit;

-- Configure logging
ALTER SYSTEM SET pgaudit.log = 'all, -misc';
ALTER SYSTEM SET pgaudit.log_catalog = off;
ALTER SYSTEM SET pgaudit.log_client = off;
```

### MySQL Security

**1. Enable SSL:**
```ini
# my.cnf
[mysqld]
require_secure_transport = ON
ssl-ca = /etc/mysql/ssl/ca.pem
ssl-cert = /etc/mysql/ssl/server-cert.pem
ssl-key = /etc/mysql/ssl/server-key.pem
```

**2. Configure Password Validation:**
```sql
INSTALL PLUGIN validate_password SONAME 'validate_password.so';

SET GLOBAL validate_password_policy = 2;
SET GLOBAL validate_password_length = 12;
SET GLOBAL validate_password_number_count = 2;
SET GLOBAL validate_password_special_char_count = 1;
```

**3. Enable Audit Plugin (Enterprise):**
```sql
INSTALL PLUGIN audit_log SONAME 'audit_log.so';
SET GLOBAL audit_log_policy = ALL;
```

## Database Backup

### PostgreSQL Backup

**Create Backup Script:**
```bash
#!/bin/bash
# /usr/local/bin/backup-postgresql.sh

BACKUP_DIR="/var/backups/postgresql"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME=$1
DB_USER=$2

# Create backup directory
mkdir -p $BACKUP_DIR

# Create backup
pg_dump -h localhost -U $DB_USER -F c -b -v $DB_NAME > $BACKUP_DIR/${DB_NAME}_${DATE}.dump

# Compress backup
gzip $BACKUP_DIR/${DB_NAME}_${DATE}.dump

# Delete backups older than 30 days
find $BACKUP_DIR -name "*.gz" -mtime +30 -delete

echo "Backup completed: ${DB_NAME}_${DATE}.dump.gz"
```

**Schedule with Cron:**
```bash
# Edit crontab
crontab -e

# Add daily backup at 2 AM
0 2 * * * /usr/local/bin/backup-postgresql.sh mswms_production mswms_prod_user
```

### MySQL Backup

**Create Backup Script:**
```bash
#!/bin/bash
# /usr/local/bin/backup-mysql.sh

BACKUP_DIR="/var/backups/mysql"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME=$1
DB_USER=$2

# Create backup directory
mkdir -p $BACKUP_DIR

# Create backup
mysqldump -h localhost -u $DB_USER -p $DB_NAME | gzip > $BACKUP_DIR/${DB_NAME}_${DATE}.sql.gz

# Delete backups older than 30 days
find $BACKUP_DIR -name "*.gz" -mtime +30 -delete

echo "Backup completed: ${DB_NAME}_${DATE}.sql.gz"
```

## Database Monitoring

### PostgreSQL Monitoring

**Enable pg_stat_statements:**
```sql
-- Add to postgresql.conf
shared_preload_libraries = 'pg_stat_statements'

-- Create extension
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;

-- Query slow queries
SELECT query, calls, total_exec_time, mean_exec_time
FROM pg_stat_statements
ORDER BY mean_exec_time DESC
LIMIT 10;
```

**Check Database Size:**
```sql
SELECT 
    datname,
    pg_size_pretty(pg_database_size(datname)) as size
FROM pg_database
ORDER BY pg_database_size(datname) DESC;
```

**Check Active Connections:**
```sql
SELECT 
    datname,
    usename,
    application_name,
    client_addr,
    state,
    query_start,
    query
FROM pg_stat_activity
WHERE state != 'idle'
ORDER BY query_start;
```

### MySQL Monitoring

**Enable Slow Query Log:**
```sql
-- Check slow query log
SHOW VARIABLES LIKE 'slow_query_log';
SHOW VARIABLES LIKE 'long_query_time';

-- View slow queries
SELECT * FROM mysql.slow_log;
```

**Check Database Size:**
```sql
SELECT 
    table_schema as 'Database',
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.tables
GROUP BY table_schema
ORDER BY SUM(data_length + index_length) DESC;
```

**Check Active Connections:**
```sql
SHOW FULL PROCESSLIST;
```

## Troubleshooting

### Common PostgreSQL Issues

**Connection Refused:**
```bash
# Check PostgreSQL is running
sudo systemctl status postgresql

# Check pg_hba.conf
sudo nano /etc/postgresql/15/main/pg_hba.conf

# Check PostgreSQL logs
sudo tail -f /var/log/postgresql/postgresql-15-main.log
```

**Too Many Connections:**
```sql
-- Check current connections
SELECT count(*) FROM pg_stat_activity;

-- Check max connections
SHOW max_connections;

-- Kill idle connections
SELECT pg_terminate_backend(pid) 
FROM pg_stat_activity 
WHERE state = 'idle' 
AND query_start < NOW() - INTERVAL '30 minutes';
```

### Common MySQL Issues

**Connection Refused:**
```bash
# Check MySQL is running
sudo systemctl status mysql

# Check bind address
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf

# Check MySQL logs
sudo tail -f /var/log/mysql/error.log
```

**Too Many Connections:**
```sql
-- Check current connections
SHOW STATUS LIKE 'Threads_connected';

-- Check max connections
SHOW VARIABLES LIKE 'max_connections';

-- Kill idle connections
KILL (SELECT id FROM information_schema.processlist WHERE command = 'Sleep' AND time > 1800);
```

---

**Previous Section**: [← Environment Configuration](02-environment-configuration.md)  
**Next Section**: [Redis Cache Setup →](04-redis-cache-setup.md)
