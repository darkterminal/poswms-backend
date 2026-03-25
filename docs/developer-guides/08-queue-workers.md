# Queue Workers Configuration and Management

## Overview

This document provides comprehensive instructions for configuring, deploying, and managing queue workers for MSWMS. Queue workers process background jobs asynchronously, improving application performance and user experience.

## Queue Architecture

### Queue Connections

MSWMS uses multiple queue connections for different job types:

| Connection | Purpose | Driver | Priority |
|------------|---------|--------|----------|
| `default` | General jobs | Redis/Database | Normal |
| `emails` | Email notifications | Redis/Database | High |
| `reports` | Report generation | Redis/Database | Low |
| `imports` | Data imports | Redis/Database | Low |
| `exports` | Data exports | Redis/Database | Low |
| `webhooks` | Webhook delivery | Redis/Database | High |

### Job Types

**High Priority (Process Immediately):**
- User notifications
- Password resets
- Email confirmations
- Webhook deliveries
- Payment processing

**Normal Priority (Process Soon):**
- Order confirmations
- Inventory updates
- Audit logging
- Cache warming

**Low Priority (Process When Available):**
- Report generation
- Data exports
- Data imports
- Bulk operations
- Analytics processing

## Queue Driver Selection

### Redis (Recommended for Production)

**Advantages:**
- Fast performance
- Supports delayed jobs
- Better concurrency
- Built-in job locking
- Job prioritization

**Configuration:**
```ini
QUEUE_CONNECTION=redis
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=default
```

### Database (Recommended for Staging)

**Advantages:**
- Simple setup
- No additional infrastructure
- Easy to monitor
- Transactional safety

**Configuration:**
```ini
QUEUE_CONNECTION=database
DB_QUEUE_CONNECTION=pgsql
DB_QUEUE_TABLE=jobs
```

### Sync (Development Only)

**Advantages:**
- No worker needed
- Immediate execution
- Easy debugging

**Configuration:**
```ini
QUEUE_CONNECTION=sync
```

## Queue Setup

### Database Queue Setup

**1. Create Jobs Table:**
```bash
php artisan queue:table
php artisan migrate
```

**2. Create Failed Jobs Table:**
```bash
php artisan queue:failed-table
php artisan migrate
```

**3. Verify Tables:**
```bash
php artisan tinker --execute="Schema::hasTable('jobs') && Schema::hasTable('failed_jobs');"
```

### Redis Queue Setup

**1. Configure Redis Connection:**
```php
// config/database.php
'redis' => [
    'default' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
    ],
],
```

**2. Configure Queue Connection:**
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

## Supervisor Configuration

### Install Supervisor

**Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install -y supervisor
```

**CentOS/RHEL:**
```bash
sudo yum install -y supervisor
```

### Create Supervisor Configuration

**Staging Environment:**
```bash
sudo nano /etc/supervisor/conf.d/mswms-worker.conf
```

```ini
[program:mswms-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/mswms/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --memory=512
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/mswms-worker.log
stopwaitsecs=3600
```

**Production Environment:**
```bash
sudo nano /etc/supervisor/conf.d/mswms-worker.conf
```

```ini
[program:mswms-worker-default]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/mswms/artisan queue:work redis --queue=default --sleep=3 --tries=3 --max-time=3600 --memory=512
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/supervisor/mswms-worker-default.log
stopwaitsecs=3600

[program:mswms-worker-emails]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/mswms/artisan queue:work redis --queue=emails --sleep=3 --tries=3 --max-time=3600 --memory=512
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/mswms-worker-emails.log
stopwaitsecs=3600

[program:mswms-worker-webhooks]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/mswms/artisan queue:work redis --queue=webhooks --sleep=3 --tries=3 --max-time=3600 --memory=512
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/mswms-worker-webhooks.log
stopwaitsecs=3600

[program:mswms-worker-reports]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/mswms/artisan queue:work redis --queue=reports --sleep=5 --tries=2 --max-time=7200 --memory=1024
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/mswms-worker-reports.log
stopwaitsecs=7200
```

### Create Log Directory

```bash
sudo mkdir -p /var/log/supervisor
sudo chown -R www-data:www-data /var/log/supervisor
```

### Start Supervisor

```bash
# Update supervisor
sudo supervisorctl reread
sudo supervisorctl update

# Start workers
sudo supervisorctl start mswms-worker:*

# Check status
sudo supervisorctl status

# Enable supervisor on boot
sudo systemctl enable supervisor
sudo systemctl start supervisor
```

## Manual Worker Management

### Start Workers

**Single Worker:**
```bash
php artisan queue:work redis --sleep=3 --tries=3 --timeout=60
```

**Multiple Workers (Screen/Tmux):**
```bash
# Start in screen session
screen -S worker-1
php artisan queue:work redis --queue=default --sleep=3 --tries=3

# New screen session
screen -S worker-2
php artisan queue:work redis --queue=emails --sleep=3 --tries=3
```

### Worker Options

```bash
# Basic options
php artisan queue:work <connection> [options]

# Common options
--queue=<queue>        # Process specific queue
--sleep=<seconds>      # Sleep time when no jobs (default: 3)
--tries=<times>        # Number of times to attempt job (default: 1)
--timeout=<seconds>    # Max seconds a job can run (default: 60)
--max-time=<seconds>   # Max seconds worker will run
--memory=<MB>          # Memory limit (default: 128)
--stop-when-empty      # Stop when queue is empty
--force                # Force run in maintenance mode
```

### Restart Workers

**After Code Deployment:**
```bash
# Graceful restart (recommended)
php artisan queue:restart

# Wait for workers to finish current jobs
sleep 10

# Check if workers restarted
sudo supervisorctl status
```

**Force Restart:**
```bash
# Restart all workers
sudo supervisorctl restart mswms-worker:*

# Restart specific worker
sudo supervisorctl restart mswms-worker-default:0
```

### Stop Workers

```bash
# Stop all workers
sudo supervisorctl stop mswms-worker:*

# Remove from supervisor
sudo supervisorctl remove mswms-worker:*

# Or disable and stop
sudo systemctl stop supervisor
```

## Job Monitoring

### Queue Commands

```bash
# Monitor all queues
php artisan queue:monitor redis

# Monitor specific queue
php artisan queue:monitor redis --queue=emails

# List failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry <ID>

# Retry all failed jobs
php artisan queue:retry all

# Flush failed jobs
php artisan queue:flush

# Clear queue
php artisan queue:clear redis --queue=emails
```

### Check Queue Size

```bash
# Database queue
php artisan tinker --execute="DB::table('jobs')->count();"

# Redis queue
php artisan tinker --execute="Redis::llen('queues:default');"

# Check multiple queues
php artisan tinker --execute="
    echo 'Default: ' . Redis::llen('queues:default') . PHP_EOL;
    echo 'Emails: ' . Redis::llen('queues:emails') . PHP_EOL;
    echo 'Reports: ' . Redis::llen('queues:reports') . PHP_EOL;
"
```

### View Failed Jobs

```bash
# List failed jobs
php artisan queue:failed

# View specific failed job
php artisan tinker --execute="DB::table('failed_jobs')->find(1);"

# Check failed job details
tail -f /var/log/supervisor/mswms-worker.log | grep -i "failed"
```

## Job Classes

### Creating Jobs

```bash
# Create job class
php artisan make:job ProcessOrder
php artisan make:job SendEmailNotification
php artisan make:job GenerateReport
php artisan make:job ProcessWebhook
```

### Example Job Class

```php
<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The order instance.
     */
    public Order $order;

    /**
     * Create a new job instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Processing order', ['order_id' => $this->order->id]);

        // Process order logic
        $this->order->process();

        // Send confirmation email
        SendOrderConfirmation::dispatch($this->order);

        // Update inventory
        UpdateInventory::dispatch($this->order);

        Log::info('Order processed successfully', ['order_id' => $this->order->id]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Order processing failed', [
            'order_id' => $this->order->id,
            'error' => $exception->getMessage(),
        ]);

        // Notify admin
        // Send alert email
    }
}
```

### Dispatching Jobs

```php
use App\Jobs\ProcessOrder;

// Dispatch to default queue
ProcessOrder::dispatch($order);

// Dispatch to specific queue
ProcessOrder::dispatch($order)->onQueue('emails');

// Dispatch with delay
ProcessOrder::dispatch($order)->delay(now()->addMinutes(10));

// Dispatch with custom connection
ProcessOrder::dispatch($order)->onConnection('redis');

// Chain multiple jobs
ProcessOrder::withChain([
    new SendOrderConfirmation($order),
    new UpdateInventory($order),
])->dispatch($order);
```

## Performance Optimization

### Worker Tuning

**Memory Management:**
```ini
# Supervisor configuration
command=php /var/www/mswms/artisan queue:work redis --memory=512 --max-time=3600
```

**Process Count:**
```ini
# Adjust based on server resources
# Formula: numprocs = (CPU cores * 2) + 1
numprocs=4  # For 2-core server
numprocs=8  # For 4-core server
```

### Batch Processing

```php
use App\Jobs\ProcessOrder;
use Illuminate\Support\Facades\Bus;

// Batch dispatch
$orders = Order::where('status', 'pending')->get();

Bus::batch(
    $orders->map(fn($order) => new ProcessOrder($order))->toArray()
)->dispatch();

// Batch with callbacks
Bus::batch([
    new ProcessOrder($order1),
    new ProcessOrder($order2),
])
->then(function (Illuminate\Bus\Batch $batch) {
    // All jobs completed successfully
    Log::info('Batch completed');
})
->catch(function (Illuminate\Bus\Batch $batch, \Throwable $e) {
    // First batch job failure
    Log::error('Batch failed', ['error' => $e->getMessage()]);
})
->finally(function (Illuminate\Bus\Batch $batch) {
    // Batch has finished executing
    Log::info('Batch finished');
})
->dispatch();
```

### Rate Limiting

```php
// Limit job execution
ProcessOrder::dispatch($order)->rateLimit(10, 60);  // 10 jobs per 60 seconds

// Globally rate limited
ProcessOrder::dispatch($order)->limit(['tenant' => $order->tenant_id], 100);
```

## Error Handling

### Retry Logic

**Configure Retry Strategy:**
```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class ProcessOrder implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 10;

    /**
     * Maximum seconds a job can run.
     */
    public int $timeout = 60;

    public function handle(): void
    {
        // Job logic
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(24);
    }
}
```

### Failed Job Handling

**Configure Failed Job Handler:**
```php
// config/queue.php
'failed' => [
    'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
    'database' => env('DB_CONNECTION', 'pgsql'),
    'table' => 'failed_jobs',
],
```

**Custom Failed Job Handling:**
```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessOrder implements ShouldQueue
{
    use Queueable;

    public function failed(\Throwable $exception): void
    {
        // Log error
        \Log::error('Job failed', [
            'job' => self::class,
            'error' => $exception->getMessage(),
        ]);

        // Notify admin
        \Mail::to('admin@example.com')->send(
            new \App\Mail\JobFailedNotification($exception)
        );

        // Create support ticket
        // SupportTicket::create([...]);
    }
}
```

## Monitoring and Alerting

### Health Check Endpoint

```php
// routes/api.php
Route::get('/health/queue', function () {
    $queues = [
        'default' => \Illuminate\Support\Facades\Redis::llen('queues:default'),
        'emails' => \Illuminate\Support\Facades\Redis::llen('queues:emails'),
        'reports' => \Illuminate\Support\Facades\Redis::llen('queues:reports'),
    ];

    $failed = \DB::table('failed_jobs')->count();

    return response()->json([
        'queues' => $queues,
        'failed_jobs' => $failed,
        'status' => $failed > 100 ? 'warning' : 'healthy',
    ]);
});
```

### Monitoring Script

```bash
#!/bin/bash
# /usr/local/bin/monitor-queues.sh

THRESHOLD=1000
FAILED_THRESHOLD=50

# Check queue sizes
DEFAULT_SIZE=$(php /var/www/mswms/artisan tinker --execute="echo Redis::llen('queues:default');")
EMAILS_SIZE=$(php /var/www/mswms/artisan tinker --execute="echo Redis::llen('queues:emails');")
FAILED_COUNT=$(php /var/www/mswms/artisan tinker --execute="echo DB::table('failed_jobs')->count();")

# Alert if queues are too large
if [ "$DEFAULT_SIZE" -gt "$THRESHOLD" ]; then
    echo "ALERT: Default queue size is $DEFAULT_SIZE" | mail -s "Queue Alert" admin@example.com
fi

if [ "$EMAILS_SIZE" -gt "$THRESHOLD" ]; then
    echo "ALERT: Emails queue size is $EMAILS_SIZE" | mail -s "Queue Alert" admin@example.com
fi

# Alert if too many failed jobs
if [ "$FAILED_COUNT" -gt "$FAILED_THRESHOLD" ]; then
    echo "ALERT: $FAILED_COUNT failed jobs" | mail -s "Failed Jobs Alert" admin@example.com
fi
```

### Schedule Monitoring

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Monitor queue health every 5 minutes
    $schedule->exec('/usr/local/bin/monitor-queues.sh')
             ->everyFiveMinutes()
             ->appendOutputTo('/var/log/mswms/queue-monitor.log');

    // Retry failed jobs every hour
    $schedule->command('queue:retry all')
             ->hourly()
             ->when(function () {
                 return \DB::table('failed_jobs')->count() > 0;
             });

    // Clean old failed jobs weekly
    $schedule->exec('php artisan queue:flush')
             ->weekly();
}
```

## Troubleshooting

### Common Issues

#### 1. Jobs Not Processing

```bash
# Check if workers are running
sudo supervisorctl status

# Check queue size
php artisan queue:monitor redis

# Check worker logs
tail -f /var/log/supervisor/mswms-worker.log

# Restart workers
php artisan queue:restart
```

#### 2. Jobs Failing Repeatedly

```bash
# Check failed jobs
php artisan queue:failed

# View error details
php artisan tinker --execute="DB::table('failed_jobs')->latest()->first();"

# Retry specific job
php artisan queue:retry <ID>

# Check application logs
tail -f storage/logs/laravel.log | grep -i "failed"
```

#### 3. Memory Leaks

```bash
# Monitor worker memory
ps aux | grep "queue:work"

# Reduce max-time
command=php /var/www/mswms/artisan queue:work redis --max-time=1800

# Reduce memory limit
command=php /var/www/mswms/artisan queue:work redis --memory=256
```

#### 4. Queue Backlog

```bash
# Scale workers
sudo nano /etc/supervisor/conf.d/mswms-worker.conf
# Increase numprocs

# Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update

# Or manually process queue
php artisan queue:work --once --queue=emails
```

---

**Previous Section**: [← Deployment Steps](07-deployment-steps.md)  
**Next Section**: [Monitoring & Logging →](09-monitoring-logging.md)
