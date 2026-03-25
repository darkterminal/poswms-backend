# Backup and Recovery

## Overview

This document provides comprehensive backup and recovery procedures for MSWMS. A robust backup strategy is essential for data protection, disaster recovery, and business continuity.

## Backup Strategy

### Backup Types

| Type | Description | Frequency | Retention |
|------|-------------|-----------|-----------|
| Full | Complete backup of all data | Daily | 30 days |
| Incremental | Changes since last backup | Hourly | 7 days |
| Transaction Log | Database transaction logs | Continuous | 7 days |
| Point-in-Time | Specific moment recovery | On-demand | 30 days |

### Backup Priorities

**Critical (RPO < 1 hour):**
- Production database
- User data
- Transaction data
- Configuration files

**Important (RPO < 24 hours):**
- Application logs
- Audit logs
- File uploads
- Queue data

**Optional (RPO < 7 days):**
- Debug logs
- Temporary files
- Cache data

## Database Backup

### PostgreSQL Backup

#### Full Backup with pg_dump

**Backup Script:**
```bash
#!/bin/bash
# /usr/local/bin/backup-postgresql.sh

set -e

# Configuration
BACKUP_DIR="/var/backups/postgresql"
DB_NAME="mswms_production"
DB_USER="mswms_prod_user"
DB_HOST="localhost"
RETENTION_DAYS=30
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR

# Create backup
echo "Starting backup of $DB_NAME..."
pg_dump -h $DB_HOST -U $DB_USER -F c -b -v $DB_NAME > $BACKUP_DIR/${DB_NAME}_${DATE}.dump

# Compress backup
echo "Compressing backup..."
gzip $BACKUP_DIR/${DB_NAME}_${DATE}.dump

# Verify backup
if [ -f "$BACKUP_DIR/${DB_NAME}_${DATE}.dump.gz" ]; then
    BACKUP_SIZE=$(du -h "$BACKUP_DIR/${DB_NAME}_${DATE}.dump.gz" | cut -f1)
    echo "Backup completed successfully: ${DB_NAME}_${DATE}.dump.gz ($BACKUP_SIZE)"
else
    echo "Backup failed!"
    exit 1
fi

# Delete old backups
echo "Cleaning up backups older than $RETENTION_DAYS days..."
find $BACKUP_DIR -name "*.dump.gz" -mtime +$RETENTION_DAYS -delete

# Upload to S3 (optional)
if command -v aws &> /dev/null; then
    echo "Uploading to S3..."
    aws s3 cp $BACKUP_DIR/${DB_NAME}_${DATE}.dump.gz s3://your-backup-bucket/postgresql/
fi

echo "Backup process completed."
```

**Schedule with Cron:**
```bash
sudo crontab -e

# Daily full backup at 2 AM
0 2 * * * /usr/local/bin/backup-postgresql.sh >> /var/log/backup-postgresql.log 2>&1

# Hourly incremental backup (using pg_basebackup for continuous archiving)
0 * * * * /usr/local/bin/backup-postgresql-incremental.sh >> /var/log/backup-postgresql.log 2>&1
```

#### Point-in-Time Recovery (PITR)

**Enable WAL Archiving:**
```ini
# postgresql.conf
wal_level = replica
archive_mode = on
archive_command = 'cp %p /var/lib/postgresql/wal_archive/%f'
archive_timeout = 300  # Force archive every 5 minutes
```

**Setup WAL Archive Directory:**
```bash
sudo mkdir -p /var/lib/postgresql/wal_archive
sudo chown postgres:postgres /var/lib/postgresql/wal_archive
sudo chmod 700 /var/lib/postgresql/wal_archive
```

**Restart PostgreSQL:**
```bash
sudo systemctl restart postgresql
```

#### pg_basebackup for Continuous Backup

**Base Backup Script:**
```bash
#!/bin/bash
# /usr/local/bin/backup-pg-base.sh

set -e

BACKUP_DIR="/var/backups/postgresql/base"
DATE=$(date +%Y%m%d_%H%M%S)

# Create base backup
pg_basebackup -h localhost -U postgres -D $BACKUP_DIR/base_$DATE -Ft -z -P -X stream

# Compress and archive
echo "Base backup completed: base_$DATE"

# Upload to S3
aws s3 cp $BACKUP_DIR/base_$DATE.tar.gz s3://your-backup-bucket/postgresql/base/

# Clean up local base backups older than 7 days
find $BACKUP_DIR -name "base_*.tar.gz" -mtime +7 -delete
```

### MySQL Backup

#### Full Backup with mysqldump

**Backup Script:**
```bash
#!/bin/bash
# /usr/local/bin/backup-mysql.sh

set -e

# Configuration
BACKUP_DIR="/var/backups/mysql"
DB_NAME="mswms_production"
DB_USER="mswms_prod_user"
DB_HOST="localhost"
RETENTION_DAYS=30
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR

# Create backup
echo "Starting backup of $DB_NAME..."
mysqldump -h $DB_HOST -u $DB_USER -p --single-transaction --quick --lock-tables=false $DB_NAME | gzip > $BACKUP_DIR/${DB_NAME}_${DATE}.sql.gz

# Verify backup
if [ -f "$BACKUP_DIR/${DB_NAME}_${DATE}.sql.gz" ]; then
    BACKUP_SIZE=$(du -h "$BACKUP_DIR/${DB_NAME}_${DATE}.sql.gz" | cut -f1)
    echo "Backup completed successfully: ${DB_NAME}_${DATE}.sql.gz ($BACKUP_SIZE)"
else
    echo "Backup failed!"
    exit 1
fi

# Delete old backups
echo "Cleaning up backups older than $RETENTION_DAYS days..."
find $BACKUP_DIR -name "*.sql.gz" -mtime +$RETENTION_DAYS -delete

# Upload to S3
if command -v aws &> /dev/null; then
    aws s3 cp $BACKUP_DIR/${DB_NAME}_${DATE}.sql.gz s3://your-backup-bucket/mysql/
fi

echo "Backup process completed."
```

#### MySQL Binary Log Backup

**Enable Binary Logging:**
```ini
# my.cnf
[mysqld]
log_bin = /var/log/mysql/mysql-bin.log
binlog_format = ROW
expire_logs_days = 7
max_binlog_size = 100M
```

**Backup Binary Logs:**
```bash
#!/bin/bash
# /usr/local/bin/backup-mysql-binlog.sh

BACKUP_DIR="/var/backups/mysql/binlog"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Flush and backup binary logs
mysql -u root -p -e "FLUSH BINARY LOGS;"
cp /var/log/mysql/mysql-bin.* $BACKUP_DIR/

# Compress
gzip $BACKUP_DIR/mysql-bin.*

# Upload to S3
aws s3 cp $BACKUP_DIR/ s3://your-backup-bucket/mysql/binlog/ --recursive

# Clean up old binlogs
find $BACKUP_DIR -name "*.gz" -mtime +7 -delete
```

## File Backup

### Application Files Backup

**Backup Script:**
```bash
#!/bin/bash
# /usr/local/bin/backup-files.sh

set -e

# Configuration
APP_DIR="/var/www/mswms"
BACKUP_DIR="/var/backups/files"
RETENTION_DAYS=14
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR

# Directories to backup
BACKUP_PATHS=(
    "storage/app"
    ".env"
)

# Create backup
echo "Starting file backup..."
cd $APP_DIR

# Create tar archive
tar -czf $BACKUP_DIR/files_$DATE.tar.gz \
    --exclude='storage/logs' \
    --exclude='storage/framework/cache' \
    --exclude='storage/framework/sessions' \
    --exclude='storage/framework/views' \
    --exclude='vendor' \
    --exclude='node_modules' \
    ${BACKUP_PATHS[@]}

# Verify backup
if [ -f "$BACKUP_DIR/files_$DATE.tar.gz" ]; then
    BACKUP_SIZE=$(du -h "$BACKUP_DIR/files_$DATE.tar.gz" | cut -f1)
    echo "File backup completed: files_$DATE.tar.gz ($BACKUP_SIZE)"
else
    echo "File backup failed!"
    exit 1
fi

# Upload to S3
if command -v aws &> /dev/null; then
    aws s3 cp $BACKUP_DIR/files_$DATE.tar.gz s3://your-backup-bucket/files/
fi

# Clean up old backups
find $BACKUP_DIR -name "files_*.tar.gz" -mtime +$RETENTION_DAYS -delete

echo "File backup process completed."
```

### S3 Backup

**Configure AWS CLI:**
```bash
aws configure
# Enter AWS Access Key ID
# Enter AWS Secret Access Key
# Enter default region name
# Enter default output format
```

**Sync to S3:**
```bash
#!/bin/bash
# /usr/local/bin/sync-s3-backup.sh

SOURCE_DIR="/var/backups"
S3_BUCKET="s3://your-backup-bucket"

# Sync backups to S3
aws s3 sync $SOURCE_DIR $S3_BUCKET/backups/ \
    --exclude "*.tmp" \
    --exclude "*.log" \
    --storage-class STANDARD_IA

# Enable versioning on bucket
aws s3api put-bucket-versioning \
    --bucket your-backup-bucket \
    --versioning-configuration Status=Enabled

# Enable lifecycle policy for cost optimization
aws s3api put-bucket-lifecycle-configuration \
    --bucket your-backup-bucket \
    --lifecycle-configuration file://lifecycle-policy.json
```

**Lifecycle Policy (lifecycle-policy.json):**
```json
{
    "Rules": [
        {
            "ID": "MoveToGlacier",
            "Status": "Enabled",
            "Prefix": "backups/",
            "Transitions": [
                {
                    "Days": 30,
                    "StorageClass": "GLACIER"
                }
            ],
            "Expiration": {
                "Days": 365
            }
        }
    ]
}
```

## Backup Verification

### Automated Backup Testing

**Test Restore Script:**
```bash
#!/bin/bash
# /usr/local/bin/test-backup-restore.sh

set -e

BACKUP_DIR="/var/backups/postgresql"
TEST_DB="mswms_test_restore"
DB_USER="mswms_prod_user"

# Get latest backup
LATEST_BACKUP=$(ls -t $BACKUP_DIR/*.dump.gz | head -1)

if [ -z "$LATEST_BACKUP" ]; then
    echo "No backup found!"
    exit 1
fi

echo "Testing restore from: $LATEST_BACKUP"

# Decompress backup
gunzip -c $LATEST_BACKUP > /tmp/test_backup.dump

# Create test database
psql -U postgres -c "CREATE DATABASE $TEST_DB;"

# Restore backup
pg_restore -U $DB_USER -d $TEST_DB /tmp/test_backup.dump

# Verify restore
TABLE_COUNT=$(psql -U $DB_USER -d $TEST_DB -t -c "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public';")

if [ "$TABLE_COUNT" -gt 0 ]; then
    echo "Backup verification successful! Tables restored: $TABLE_COUNT"
    
    # Drop test database
    psql -U postgres -c "DROP DATABASE $TEST_DB;"
    
    # Clean up
    rm /tmp/test_backup.dump
else
    echo "Backup verification failed!"
    exit 1
fi
```

**Schedule Weekly Test:**
```bash
# Weekly backup test on Sunday at 4 AM
0 4 * * 0 /usr/local/bin/test-backup-restore.sh >> /var/log/backup-test.log 2>&1
```

### Backup Monitoring

**Backup Status Script:**
```bash
#!/bin/bash
# /usr/local/bin/check-backup-status.sh

BACKUP_DIR="/var/backups"
ALERT_EMAIL="admin@example.com"
MAX_AGE_HOURS=25

# Check latest backup
LATEST_BACKUP=$(find $BACKUP_DIR -name "*.gz" -type f -printf '%T@ %p\n' | sort -n | tail -1 | cut -d' ' -f2-)

if [ -z "$LATEST_BACKUP" ]; then
    echo "CRITICAL: No backups found!" | mail -s "Backup Alert" $ALERT_EMAIL
    exit 1
fi

# Check backup age
BACKUP_AGE=$(( ($(date +%s) - $(stat -c %Y "$LATEST_BACKUP")) / 3600 ))

if [ $BACKUP_AGE -gt $MAX_AGE_HOURS ]; then
    echo "WARNING: Latest backup is $BACKUP_AGE hours old" | mail -s "Backup Age Alert" $ALERT_EMAIL
fi

# Check backup size
BACKUP_SIZE=$(du -h "$LATEST_BACKUP" | cut -f1)
echo "Latest backup: $LATEST_BACKUP (Age: ${BACKUP_AGE}h, Size: $BACKUP_SIZE)"
```

## Recovery Procedures

### Database Recovery

#### PostgreSQL Recovery

**Full Restore:**
```bash
#!/bin/bash
# /usr/local/bin/restore-postgresql.sh

set -e

BACKUP_FILE=$1
DB_NAME="mswms_production"
DB_USER="mswms_prod_user"

if [ -z "$BACKUP_FILE" ]; then
    echo "Usage: $0 <backup_file.dump.gz>"
    exit 1
fi

echo "Starting restore from: $BACKUP_FILE"

# Decompress backup
gunzip -c $BACKUP_FILE > /tmp/restore_backup.dump

# Drop and recreate database (DANGER: Data loss!)
psql -U postgres -c "DROP DATABASE IF EXISTS $DB_NAME;"
psql -U postgres -c "CREATE DATABASE $DB_NAME;"

# Restore backup
pg_restore -U $DB_USER -d $DB_NAME /tmp/restore_backup.dump

# Verify restore
TABLE_COUNT=$(psql -U $DB_USER -d $DB_NAME -t -c "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public';")

echo "Restore completed! Tables restored: $TABLE_COUNT"

# Clean up
rm /tmp/restore_backup.dump
```

**Point-in-Time Recovery:**
```bash
#!/bin/bash
# /usr/local/bin/restore-pitr.sh

set -e

TARGET_TIME=$1  # Format: '2024-01-15 14:30:00'
RECOVERY_DIR="/var/lib/postgresql/recovery"

echo "Starting point-in-time recovery to: $TARGET_TIME"

# Stop PostgreSQL
sudo systemctl stop postgresql

# Create recovery directory
mkdir -p $RECOVERY_DIR

# Restore base backup
tar -xzf /var/backups/postgresql/base/latest.tar.gz -C $RECOVERY_DIR

# Create recovery.signal file
touch $RECOVERY_DIR/recovery.signal

# Configure recovery target
cat >> $RECOVERY_DIR/postgresql.auto.conf << EOF
restore_command = 'cp /var/lib/postgresql/wal_archive/%f %p'
recovery_target_time = '$TARGET_TIME'
recovery_target_action = 'promote'
EOF

# Replace data directory
sudo mv /var/lib/postgresql/current /var/lib/postgresql/old
sudo mv $RECOVERY_DIR /var/lib/postgresql/current

# Set permissions
sudo chown -R postgres:postgres /var/lib/postgresql/current
sudo chmod 700 /var/lib/postgresql/current

# Start PostgreSQL
sudo systemctl start postgresql

echo "Point-in-time recovery completed"
```

#### MySQL Recovery

**Full Restore:**
```bash
#!/bin/bash
# /usr/local/bin/restore-mysql.sh

set -e

BACKUP_FILE=$1
DB_NAME="mswms_production"
DB_USER="mswms_prod_user"

if [ -z "$BACKUP_FILE" ]; then
    echo "Usage: $0 <backup_file.sql.gz>"
    exit 1
fi

echo "Starting restore from: $BACKUP_FILE"

# Drop and recreate database
mysql -u root -p -e "DROP DATABASE IF EXISTS $DB_NAME;"
mysql -u root -p -e "CREATE DATABASE $DB_NAME;"

# Restore backup
gunzip -c $BACKUP_FILE | mysql -u $DB_USER -p $DB_NAME

echo "Restore completed"
```

### Application Recovery

**Full Application Restore:**
```bash
#!/bin/bash
# /usr/local/bin/restore-application.sh

set -e

BACKUP_FILE=$1
APP_DIR="/var/www/mswms"

if [ -z "$BACKUP_FILE" ]; then
    echo "Usage: $0 <backup_file.tar.gz>"
    exit 1
fi

echo "Starting application restore from: $BACKUP_FILE"

# Stop services
sudo systemctl stop nginx
sudo systemctl stop php8.3-fpm
sudo supervisorctl stop all

# Backup current state
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
sudo mv $APP_DIR ${APP_DIR}.backup.$TIMESTAMP

# Extract backup
mkdir -p $APP_DIR
tar -xzf $BACKUP_FILE -C $APP_DIR

# Set permissions
sudo chown -R www-data:www-data $APP_DIR
sudo chmod -R 755 $APP_DIR
sudo chmod -R 775 $APP_DIR/storage $APP_DIR/bootstrap/cache

# Start services
sudo systemctl start nginx
sudo systemctl start php8.3-fpm
sudo supervisorctl start all

echo "Application restore completed"
```

## Disaster Recovery Plan

### Recovery Time Objectives (RTO)

| Scenario | Target RTO | Procedure |
|----------|------------|-----------|
| Single file loss | < 1 hour | Restore from daily backup |
| Database corruption | < 4 hours | Restore from latest backup |
| Server failure | < 8 hours | Restore from S3 backup |
| Regional disaster | < 24 hours | Activate DR site |

### Disaster Recovery Checklist

**Immediate Actions:**
- [ ] Assess the scope of the disaster
- [ ] Notify stakeholders
- [ ] Activate disaster recovery team
- [ ] Document the incident timeline

**Recovery Steps:**
- [ ] Provision new infrastructure
- [ ] Restore database from backup
- [ ] Restore application files
- [ ] Configure environment variables
- [ ] Update DNS if necessary
- [ ] Verify all services are running
- [ ] Run health checks
- [ ] Test critical functionality

**Post-Recovery:**
- [ ] Monitor system stability
- [ ] Verify data integrity
- [ ] Notify users of service restoration
- [ ] Conduct post-incident review
- [ ] Update disaster recovery plan

### Backup Schedule Summary

| Backup Type | Schedule | Retention | Storage |
|-------------|----------|-----------|---------|
| Database (Full) | Daily 2 AM | 30 days | Local + S3 |
| Database (Incremental) | Hourly | 7 days | Local + S3 |
| Files | Daily 3 AM | 14 days | Local + S3 |
| Configuration | On change | 90 days | S3 |
| Logs | Weekly | 365 days | S3 Glacier |

## Backup Security

### Encryption

**Encrypt Backups:**
```bash
#!/bin/bash
# Encrypt backup with GPG

BACKUP_FILE=$1
GPG_RECIPIENT="backup-key@example.com"

# Encrypt
gpg --encrypt --recipient $GPG_RECIPIENT --output $BACKUP_FILE.gpg $BACKUP_FILE

# Remove unencrypted file
rm $BACKUP_FILE
```

**S3 Server-Side Encryption:**
```bash
aws s3 cp backup.dump.gz s3://your-backup-bucket/ \
    --server-side-encryption aws:kms \
    --sse-kms-key-id your-kms-key-id
```

### Access Control

**IAM Policy for Backup Bucket:**
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "BackupAccess",
            "Effect": "Allow",
            "Principal": {
                "AWS": "arn:aws:iam::123456789012:role/BackupRole"
            },
            "Action": [
                "s3:PutObject",
                "s3:GetObject",
                "s3:DeleteObject"
            ],
            "Resource": "arn:aws:s3:::your-backup-bucket/backups/*"
        }
    ]
}
```

---

**Previous Section**: [← Performance Optimization](11-performance-optimization.md)  
**Next Section**: [CI/CD Pipeline →](13-ci-cd-pipeline.md)
