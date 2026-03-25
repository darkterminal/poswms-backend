# Monitoring and Logging Setup

## Overview

This document provides comprehensive instructions for setting up monitoring, logging, and alerting for MSWMS. Proper monitoring and logging are essential for maintaining application health, performance, and security.

## Application Logging

### Laravel Log Configuration

**Log Channels:**
```php
// config/logging.php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single'],
        'ignore_exceptions' => false,
    ],

    'single' => [
        'driver' => 'single',
        'path' => storage_path('logs/laravel.log'),
        'level' => env('LOG_LEVEL', 'debug'),
    ],

    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => 14,
    ],

    'errorlog' => [
        'driver' => 'errorlog',
        'level' => env('LOG_LEVEL', 'debug'),
    ],

    'null' => [
        'driver' => 'monolog',
        'handler' => Monolog\Handler\NullHandler::class,
    ],

    'emergency' => [
        'path' => storage_path('logs/laravel.log'),
    ],
],
```

### Environment-Specific Logging

**Staging:**
```ini
LOG_CHANNEL=daily
LOG_LEVEL=info
LOG_DEPRECATIONS_CHANNEL=null
```

**Production:**
```ini
LOG_CHANNEL=errorlog
LOG_LEVEL=error
LOG_DEPRECATIONS_CHANNEL=null
```

### Custom Log Channels

**Add to logging.php:**
```php
'audit' => [
    'driver' => 'daily',
    'path' => storage_path('logs/audit.log'),
    'level' => 'info',
    'days' => 365,
],

'performance' => [
    'driver' => 'daily',
    'path' => storage_path('logs/performance.log'),
    'level' => 'debug',
    'days' => 7,
],

'security' => [
    'driver' => 'daily',
    'path' => storage_path('logs/security.log'),
    'level' => 'warning',
    'days' => 365,
],
```

### Usage Examples

```php
use Illuminate\Support\Facades\Log;

// Standard logging
Log::info('User logged in', ['user_id' => $userId]);
Log::warning('Low stock alert', ['product_id' => $productId]);
Log::error('Payment failed', ['order_id' => $orderId, 'error' => $exception->getMessage()]);

// Custom channel
Log::channel('audit')->info('User permissions changed', [
    'user_id' => $userId,
    'changes' => $changes,
]);

Log::channel('security')->warning('Failed login attempt', [
    'email' => $email,
    'ip' => $request->ip(),
]);

// Contextual logging
Log::build([
    'driver' => 'single',
    'path' => storage_path('logs/orders.log'),
])->info('Order created', ['order_id' => $order->id]);
```

## System Monitoring

### Server Metrics to Monitor

**CPU:**
- Usage percentage
- Load average (1, 5, 15 minutes)
- Process count

**Memory:**
- RAM usage
- Swap usage
- Cache usage

**Disk:**
- Disk space
- Disk I/O
- Inode usage

**Network:**
- Bandwidth usage
- Connection count
- Packet errors

### Install Monitoring Tools

**htop (Interactive Process Viewer):**
```bash
sudo apt install -y htop

# Usage
htop
```

**iotop (Disk I/O Monitoring):**
```bash
sudo apt install -y iotop

# Usage
sudo iotop
```

**nethogs (Network Monitoring):**
```bash
sudo apt install -y nethogs

# Usage
sudo nethogs eth0
```

**glances (System Overview):**
```bash
sudo apt install -y glances

# Usage
glances

# Web mode
glances -w
# Access at http://localhost:61208
```

### Custom Monitoring Script

```bash
#!/bin/bash
# /usr/local/bin/system-monitor.sh

LOG_FILE="/var/log/mswms/system-monitor.log"
ALERT_EMAIL="admin@example.com"

# CPU Usage
CPU_USAGE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)

# Memory Usage
MEMORY_USAGE=$(free | grep Mem | awk '{printf("%.2f"), $3/$2 * 100.0}')

# Disk Usage
DISK_USAGE=$(df -h / | tail -1 | awk '{print $5}' | cut -d'%' -f1)

# Load Average
LOAD_AVG=$(uptime | awk -F'load average:' '{print $2}' | xargs)

# Log metrics
echo "$(date '+%Y-%m-%d %H:%M:%S') - CPU: ${CPU_USAGE}%, Memory: ${MEMORY_USAGE}%, Disk: ${DISK_USAGE}%, Load: ${LOAD_AVG}" >> $LOG_FILE

# Send alerts
if (( $(echo "$CPU_USAGE > 80" | bc -l) )); then
    echo "ALERT: CPU usage is ${CPU_USAGE}%" | mail -s "CPU Alert" $ALERT_EMAIL
fi

if (( $(echo "$MEMORY_USAGE > 80" | bc -l) )); then
    echo "ALERT: Memory usage is ${MEMORY_USAGE}%" | mail -s "Memory Alert" $ALERT_EMAIL
fi

if (( $(echo "$DISK_USAGE > 80" | bc -l) )); then
    echo "ALERT: Disk usage is ${DISK_USAGE}%" | mail -s "Disk Alert" $ALERT_EMAIL
fi
```

### Schedule Monitoring

```bash
# Edit crontab
sudo crontab -e

# Run monitoring every 5 minutes
*/5 * * * * /usr/local/bin/system-monitor.sh
```

## Application Performance Monitoring (APM)

### Laravel Telescope (Staging Only)

**Install Telescope:**
```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
php artisan telescope:publish
```

**Configure Telescope:**
```php
// config/telescope.php
'enabled' => env('TELESCOPE_ENABLED', false),
'path' => env('TELESCOPE_PATH', 'telescope'),

'middleware' => [
    'web',
    Authorize::class,
],

'watchers' => [
    Watchers\CacheWatcher::class => true,
    Watchers\CommandWatcher::class => true,
    Watchers\DumpWatcher::class => true,
    Watchers\EventWatcher::class => true,
    Watchers\ExceptionWatcher::class => true,
    Watchers\JobWatcher::class => true,
    Watchers\LogWatcher::class => true,
    Watchers\MailWatcher::class => true,
    Watchers\ModelWatcher::class => true,
    Watchers\NotificationWatcher::class => true,
    Watchers\QueryWatcher::class => [
        'enabled' => true,
        'slow' => 100,
    ],
    Watchers\RedisWatcher::class => true,
    Watchers\RequestWatcher::class => [
        'enabled' => true,
        'size_limit' => null,
    ],
    Watchers\ScheduleWatcher::class => true,
    Watchers\ViewWatcher::class => true,
],
```

**Access Telescope:**
```
https://staging.mswms.example.com/telescope
```

### Blackfire.io (Performance Profiling)

**Install Blackfire:**
```bash
# Install Blackfire agent
wget -O - https://blackfire.io/api/installation.sh | sh

# Configure Blackfire
blackfire config:set server-id=YOUR_SERVER_ID
blackfire config:set server-token=YOUR_SERVER_TOKEN

# Install PHP probe
sudo blackfire agent:install

# Restart PHP-FPM
sudo systemctl restart php8.3-fpm
```

**Usage:**
```bash
# Profile a URL
blackfire curl https://api.mswms.example.com/api/v1/health

# Profile a command
blackfire run php artisan migrate:status
```

### New Relic (Production APM)

**Install New Relic:**
```bash
# Add repository
echo "deb http://apt.newrelic.com/debian/ newrelic non-free" | sudo tee /etc/apt/sources.list.d/newrelic.list
wget -O- https://download.newrelic.com/548C16BF.gpg | sudo apt-key add -

# Install agent
sudo apt update
sudo apt install -y newrelic-php5

# Configure
sudo newrelic-install install
sudo newrelic-license set YOUR_LICENSE_KEY

# Restart PHP-FPM
sudo systemctl restart php8.3-fpm
```

**Configure Laravel:**
```php
// Add to .env
NEW_RELIC_APP_NAME="MSWMS Production"
NEW_RELIC_LICENSE_KEY=your_license_key
```

## Log Aggregation

### ELK Stack (Elasticsearch, Logstash, Kibana)

**Install Filebeat (Log Shipper):**
```bash
# Add Elastic repository
wget -qO - https://artifacts.elastic.co/GPG-KEY-elasticsearch | sudo apt-key add -
echo "deb https://artifacts.elastic.co/packages/8.x/apt stable main" | sudo tee /etc/apt/sources.list.d/elastic-8.x.list

# Install Filebeat
sudo apt update
sudo apt install -y filebeat

# Configure Filebeat
sudo nano /etc/filebeat/filebeat.yml
```

**Filebeat Configuration:**
```yaml
filebeat.inputs:
- type: log
  enabled: true
  paths:
    - /var/www/mswms/storage/logs/laravel.log
  fields:
    application: mswms
  fields_under_root: true

- type: log
  enabled: true
  paths:
    - /var/log/nginx/mswms_*.log
  fields:
    application: nginx
  fields_under_root: true

- type: log
  enabled: true
  paths:
    - /var/log/supervisor/mswms-*.log
  fields:
    application: supervisor
  fields_under_root: true

output.elasticsearch:
  hosts: ["localhost:9200"]
  # Or for remote Elasticsearch
  # hosts: ["elasticsearch.example.com:9200"]
  # username: "elastic"
  # password: "your_password"

processors:
  - add_host_metadata: ~
  - add_cloud_metadata: ~
```

**Start Filebeat:**
```bash
sudo systemctl enable filebeat
sudo systemctl start filebeat
sudo systemctl status filebeat
```

### Graylog (Alternative Log Management)

**Install Graylog Sidecar:**
```bash
# Add repository
wget https://packages.graylog2.org/repo/packages/graylog-5.0-repository_latest.deb
sudo dpkg -i graylog-5.0-repository_latest.deb
sudo apt update

# Install sidecar
sudo apt install -y graylog-sidecar

# Configure
sudo nano /etc/graylog/sidecar/sidecar.yml
```

## Alerting System

### Laravel Notifications for Alerts

**Create Alert Service:**
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Notification;
use App\Notifications\SystemAlert;

class AlertService
{
    public function sendAlert(string $type, string $message, array $context = []): void
    {
        $admins = \App\Models\User::where('role', 'super_admin')->get();

        Notification::send($admins, new SystemAlert($type, $message, $context));

        // Also log
        \Log::channel('security')->alert($type, $message, $context);
    }

    public function cpuAlert(float $usage): void
    {
        $this->sendAlert('CPU_HIGH', "CPU usage is {$usage}%", ['usage' => $usage]);
    }

    public function memoryAlert(float $usage): void
    {
        $this->sendAlert('MEMORY_HIGH', "Memory usage is {$usage}%", ['usage' => $usage]);
    }

    public function diskAlert(float $usage): void
    {
        $this->sendAlert('DISK_HIGH', "Disk usage is {$usage}%", ['usage' => $usage]);
    }

    public function errorRateAlert(int $count): void
    {
        $this->sendAlert('ERROR_RATE_HIGH', "High error rate detected: {$count} errors", ['count' => $count]);
    }
}
```

### Uptime Monitoring

**Uptime Robot Setup:**
1. Create account at uptimerobot.com
2. Add new monitor: `https://api.mswms.example.com/api/health`
3. Set check interval: 5 minutes
4. Configure alert contacts (email, SMS)

**Custom Health Check:**
```php
// routes/api.php
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
        'version' => config('app.version'),
    ]);
});

Route::get('/health/detailed', function () {
    $checks = [
        'database' => false,
        'cache' => false,
        'queue' => false,
        'storage' => false,
    ];

    try {
        \DB::connection()->getPdo();
        $checks['database'] = true;
    } catch (\Exception $e) {
        // Database down
    }

    try {
        \Cache::put('health_check', true, 10);
        $checks['cache'] = \Cache::get('health_check') === true;
    } catch (\Exception $e) {
        // Cache down
    }

    try {
        $checks['queue'] = \DB::table('jobs')->count() >= 0;
    } catch (\Exception $e) {
        // Queue down
    }

    try {
        \Storage::disk('local')->exists('test');
        $checks['storage'] = true;
    } catch (\Exception $e) {
        // Storage down
    }

    $allHealthy = collect($checks)->every(fn($status) => $status === true);

    return response()->json([
        'status' => $allHealthy ? 'healthy' : 'unhealthy',
        'checks' => $checks,
        'timestamp' => now()->toIso8601String(),
    ], $allHealthy ? 200 : 503);
});
```

### Scheduled Health Checks

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Check queue health
    $schedule->call(function () {
        $queueSize = \Redis::llen('queues:default');
        if ($queueSize > 1000) {
            \Log::channel('security')->warning('Queue size critical', ['size' => $queueSize]);
            // Send alert
        }
    })->everyFiveMinutes();

    // Check failed jobs
    $schedule->call(function () {
        $failedCount = \DB::table('failed_jobs')->count();
        if ($failedCount > 50) {
            \Log::channel('security')->warning('High failed jobs count', ['count' => $failedCount]);
            // Send alert
        }
    })->hourly();

    // Check disk space
    $schedule->exec('df -h / | tail -1 | awk \'{print $5}\' | cut -d\'%\' -f1')
             ->everyFiveMinutes()
             ->then(function ($output) {
                 if ((int)$output > 80) {
                     \Log::channel('security')->warning('Disk space low', ['usage' => $output . '%']);
                 }
             });
}
```

## Log Rotation

### Configure Logrotate

**Laravel Logs:**
```bash
sudo nano /etc/logrotate.d/mswms-laravel
```

```
/var/www/mswms/storage/logs/laravel.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 0644 www-data www-data
    postrotate
        systemctl reload php8.3-fpm
    endscript
}

/var/www/mswms/storage/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 0644 www-data www-data
}
```

**Nginx Logs:**
```bash
sudo nano /etc/logrotate.d/mswms-nginx
```

```
/var/log/nginx/mswms_*.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 0640 www-data adm
    sharedscripts
    postrotate
        [ -f /var/run/nginx.pid ] && kill -USR1 `cat /var/run/nginx.pid`
    endscript
}
```

**Supervisor Logs:**
```bash
sudo nano /etc/logrotate.d/mswms-supervisor
```

```
/var/log/supervisor/mswms-*.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 0644 www-data www-data
}
```

### Test Logrotate

```bash
# Test configuration
sudo logrotate -d /etc/logrotate.d/mswms-laravel

# Force rotation
sudo logrotate -f /etc/logrotate.d/mswms-laravel
```

## Dashboard Setup

### Grafana Dashboard

**Install Grafana:**
```bash
# Add repository
sudo apt install -y apt-transport-https software-properties-common
wget -q -O - https://packages.grafana.com/gpg.key | sudo apt-key add -
echo "deb https://packages.grafana.com/oss/deb stable main" | sudo tee /etc/apt/sources.list.d/grafana.list

# Install Grafana
sudo apt update
sudo apt install -y grafana

# Start Grafana
sudo systemctl enable grafana-server
sudo systemctl start grafana-server
```

**Access Grafana:**
```
http://your-server-ip:3000
Default credentials: admin/admin
```

### Key Metrics to Display

**System Dashboard:**
- CPU usage over time
- Memory usage over time
- Disk usage over time
- Network I/O
- Load average

**Application Dashboard:**
- Request rate (requests/minute)
- Response time (p50, p95, p99)
- Error rate
- Queue size
- Cache hit rate

**Database Dashboard:**
- Query rate
- Slow queries
- Connection count
- Replication lag (if applicable)

## Security Logging

### Audit Log Configuration

```php
// config/security-audit.php
return [
    'enabled' => true,
    'log_channel' => 'audit',
    'retention_days' => 365,

    'events' => [
        'user.login' => true,
        'user.logout' => true,
        'user.created' => true,
        'user.updated' => true,
        'user.deleted' => true,
        'permission.assigned' => true,
        'permission.revoked' => true,
        'data.exported' => true,
        'data.imported' => true,
        'config.changed' => true,
    ],
];
```

### Security Event Logging

```php
use App\Services\AuditLogger;

// Log security event
AuditLogger::log('user.login', [
    'user_id' => $user->id,
    'email' => $user->email,
    'ip' => $request->ip(),
    'user_agent' => $request->userAgent(),
    'timestamp' => now()->toIso8601String(),
]);

// Log failed login
AuditLogger::log('auth.failed', [
    'email' => $request->email,
    'ip' => $request->ip(),
    'timestamp' => now()->toIso8601String(),
]);
```

## Troubleshooting

### Common Issues

#### 1. Logs Not Being Written

```bash
# Check permissions
ls -la storage/logs/

# Fix permissions
chmod -R 775 storage/logs
chown -R www-data:www-data storage/logs

# Check disk space
df -h
```

#### 2. Log File Too Large

```bash
# Check log size
du -sh storage/logs/*

# Manually rotate
sudo logrotate -f /etc/logrotate.d/mswms-laravel

# Clear old logs
find storage/logs -name "*.log" -mtime +30 -delete
```

#### 3. Monitoring Not Working

```bash
# Check service status
sudo systemctl status filebeat
sudo systemctl status grafana-server

# Check logs
sudo tail -f /var/log/filebeat/filebeat.log

# Test connection
curl http://localhost:9200/_cluster/health
```

---

**Previous Section**: [← Queue Workers](08-queue-workers.md)  
**Next Section**: [Security Hardening →](10-security-hardening.md)
