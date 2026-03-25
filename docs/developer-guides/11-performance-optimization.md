# Performance Optimization

## Overview

This document provides comprehensive performance optimization strategies for MSWMS. Implementing these optimizations will improve response times, reduce server load, and enhance user experience.

## Database Optimization

### Indexing Strategy

**Create Strategic Indexes:**
```sql
-- Primary indexes (usually auto-created)
CREATE INDEX idx_tenants_id ON tenants(id);
CREATE INDEX idx_users_id ON users(id);

-- Foreign key indexes
CREATE INDEX idx_users_tenant_id ON users(tenant_id);
CREATE INDEX idx_users_store_id ON users(store_id);
CREATE INDEX idx_users_warehouse_id ON users(warehouse_id);

CREATE INDEX idx_stores_tenant_id ON stores(tenant_id);
CREATE INDEX idx_stores_manager_id ON stores(manager_id);

CREATE INDEX idx_warehouses_tenant_id ON warehouses(tenant_id);
CREATE INDEX idx_warehouses_manager_id ON warehouses(manager_id);

CREATE INDEX idx_products_tenant_id ON products(tenant_id);
CREATE INDEX idx_products_category_id ON products(category_id);
CREATE INDEX idx_products_sku ON products(sku);

CREATE INDEX idx_inventory_tenant_id ON inventory(tenant_id);
CREATE INDEX idx_inventory_product_id ON inventory(product_id);
CREATE INDEX idx_inventory_warehouse_id ON inventory(warehouse_id);
CREATE INDEX idx_inventory_store_id ON inventory(store_id);

CREATE INDEX idx_orders_tenant_id ON orders(tenant_id);
CREATE INDEX idx_orders_store_id ON orders(store_id);
CREATE INDEX idx_orders_customer_id ON orders(customer_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_created_at ON orders(created_at);

CREATE INDEX idx_order_items_order_id ON order_items(order_id);
CREATE INDEX idx_order_items_product_id ON order_items(product_id);

-- Composite indexes for common queries
CREATE INDEX idx_inventory_product_warehouse ON inventory(product_id, warehouse_id);
CREATE INDEX idx_orders_tenant_status_created ON orders(tenant_id, status, created_at);
CREATE INDEX idx_orders_store_created ON orders(store_id, created_at);
```

**Verify Index Usage:**
```sql
-- PostgreSQL
EXPLAIN ANALYZE SELECT * FROM orders WHERE tenant_id = 'uuid' AND status = 'pending';

-- MySQL
EXPLAIN SELECT * FROM orders WHERE tenant_id = 'uuid' AND status = 'pending';
```

### Query Optimization

**Use Eager Loading:**
```php
// ❌ N+1 Query Problem
$orders = Order::all();
foreach ($orders as $order) {
    echo $order->customer->name;  // Query per order
}

// ✓ With Eager Loading
$orders = Order::with('customer', 'store', 'items.product')->get();
foreach ($orders as $order) {
    echo $order->customer->name;  // No additional query
}
```

**Select Only Needed Columns:**
```php
// ❌ Selecting all columns
$users = User::all();

// ✓ Select only needed columns
$users = User::select('id', 'name', 'email')->get();

// ✓ Using get() with columns
$users = User::query()
    ->select(['id', 'name', 'email'])
    ->where('is_active', true)
    ->get();
```

**Use Chunking for Large Datasets:**
```php
// ❌ Loading all records
$products = Product::all();
foreach ($products as $product) {
    // Process
}

// ✓ Using chunk
Product::chunk(100, function ($products) {
    foreach ($products as $product) {
        // Process
    }
});

// ✓ Using cursor (memory efficient)
foreach (Product::cursor() as $product) {
    // Process one at a time
}
```

**Optimize Joins:**
```php
// ✓ Efficient join with select
$orders = DB::table('orders')
    ->join('customers', 'orders.customer_id', '=', 'customers.id')
    ->join('stores', 'orders.store_id', '=', 'stores.id')
    ->select('orders.id', 'orders.order_number', 'customers.name as customer_name', 'stores.name as store_name')
    ->where('orders.tenant_id', $tenantId)
    ->orderBy('orders.created_at', 'desc')
    ->limit(50)
    ->get();
```

### Database Configuration

**PostgreSQL Optimization:**
```ini
# postgresql.conf

# Memory Settings
shared_buffers = 2GB              # 25% of RAM
effective_cache_size = 6GB        # 75% of RAM
work_mem = 64MB                   # For complex queries
maintenance_work_mem = 512MB      # For VACUUM, CREATE INDEX

# Write Ahead Log
wal_level = replica
max_wal_senders = 3
wal_keep_size = 128MB

# Query Planning
random_page_cost = 1.1            # For SSD
effective_io_concurrency = 200    # For SSD

# Connections
max_connections = 200

# Logging
log_min_duration_statement = 100  # Log queries > 100ms
```

**MySQL Optimization:**
```ini
# my.cnf

# InnoDB Settings
innodb_buffer_pool_size = 4G              # 70% of RAM
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
innodb_io_capacity = 2000                 # For SSD
innodb_io_capacity_max = 4000

# Query Cache (MySQL 5.7 only)
query_cache_type = 1
query_cache_size = 128M
query_cache_limit = 2M

# Connections
max_connections = 200
thread_cache_size = 50

# Logging
slow_query_log = 1
long_query_time = 1
```

## Caching Strategies

### Application Caching

**Cache Configuration Queries:**
```php
use Illuminate\Support\Facades\Cache;

// Cache tenant configuration
$config = Cache::remember(
    "tenant:{$tenantId}:config",
    now()->addHours(24),
    function () use ($tenantId) {
        return TenantConfig::where('tenant_id', $tenantId)->first();
    }
);

// Cache with tags (Redis only)
Cache::tags(['tenant:' . $tenantId, 'products'])->remember(
    "tenant:{$tenantId}:products:active",
    now()->addHour(),
    function () use ($tenantId) {
        return Product::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();
    }
);

// Invalidate cache tags
Cache::tags(['tenant:' . $tenantId, 'products'])->flush();
```

**Cache Model Data:**
```php
// In Model class
class Product extends Model
{
    public function getCachedPriceAttribute(): float
    {
        return Cache::remember(
            "product:{$this->id}:price",
            now()->addMinutes(30),
            function () {
                return $this->price;
            }
        );
    }

    public function invalidatePriceCache(): void
    {
        Cache::forget("product:{$this->id}:price");
    }
}
```

**Cache API Responses:**
```php
// In Controller
public function index(Request $request)
{
    $cacheKey = 'products:' . md5(json_encode($request->query()));

    return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($request) {
        return ProductResource::collection(
            Product::query()
                ->filter($request->query())
                ->paginate(50)
        );
    });
}
```

### Redis Optimization

**Redis Configuration:**
```conf
# Memory Management
maxmemory 4gb
maxmemory-policy allkeys-lru

# Persistence
save 900 1
save 300 10
save 60 10000
appendonly yes
appendfsync everysec

# Performance
tcp-keepalive 300
timeout 0
```

**Use Redis Pipelining:**
```php
use Illuminate\Support\Facades\Redis;

// ❌ Multiple round trips
Redis::set('key1', 'value1');
Redis::set('key2', 'value2');
Redis::set('key3', 'value3');

// ✓ Pipeline (single round trip)
Redis::pipeline(function ($pipe) {
    $pipe->set('key1', 'value1');
    $pipe->set('key2', 'value2');
    $pipe->set('key3', 'value3');
});
```

### HTTP Caching

**Cache-Control Headers:**
```php
// In Controller
public function show(Product $product)
{
    $response = response()->json(new ProductResource($product));
    
    // Cache for 1 hour, public (can be cached by CDN)
    $response->header('Cache-Control', 'public, max-age=3600');
    
    // ETag for validation
    $response->setEtag(md5($product->updated_at));
    
    return $response;
}

// For static assets
public function assets()
{
    return response()->view('assets')
        ->header('Cache-Control', 'public, max-age=31536000, immutable');
}
```

**Conditional Requests:**
```php
public function show(Request $request, Product $product)
{
    $etag = md5($product->updated_at);
    
    if ($request->header('If-None-Match') === $etag) {
        return response()->setStatusCode(304);  // Not Modified
    }
    
    return response()->json(new ProductResource($product))
        ->setEtag($etag);
}
```

## PHP Optimization

### OPcache Configuration

**Edit php.ini:**
```ini
[opcache]
opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 512
opcache.interned_strings_buffer = 64
opcache.max_accelerated_files = 100000
opcache.revalidate_freq = 60
opcache.fast_shutdown = 1
opcache.enable_file_override = 1
opcache.validate_timestamps = 1
opcache.save_comments = 1
opcache.load_comments = 1
opcache.optimization_level = 0x7FFFBFFF
```

**Verify OPcache:**
```bash
php -v | grep -i opcache
php --ini | grep opcache
```

### Autoloader Optimization

**Optimize Class Autoloading:**
```bash
# Generate optimized autoloader
composer dump-autoload --optimize --classmap-authoritative

# Or during install
composer install --optimize-autoloader --classmap-authoritative
```

### PHP-FPM Optimization

**Configure PHP-FPM Pool:**
```ini
[www]
; Process Manager
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 10
pm.max_spare_servers = 30
pm.max_requests = 1000

; Performance
pm.max_requests_idle = 500
request_terminate_timeout = 60
request_slowlog_timeout = 10s

; Memory
php_admin_value[memory_limit] = 512M
```

## Application-Level Optimization

### Eloquent Optimization

**Use Lazy Loading Carefully:**
```php
// Prevent N+1 with lazy loading
Product::with('category', 'inventory')->get();

// Or disable lazy loading entirely
class Product extends Model
{
    protected $lazyLoading = false;
}
```

**Use Read/Write Connections:**
```php
// config/database.php
'mysql' => [
    'read' => [
        'host' => ['read-replica-1.example.com', 'read-replica-2.example.com'],
    ],
    'write' => [
        'host' => ['primary.example.com'],
    ],
    'sticky' => true,
],

// Usage - reads automatically go to replicas
$products = Product::all();  // Read replica

// Writes go to primary
Product::create([...]);  // Primary
```

**Use Database Transactions:**
```php
DB::transaction(function () use ($order) {
    $order->update(['status' => 'processing']);
    
    foreach ($order->items as $item) {
        Inventory::decrementStock($item->product_id, $item->quantity);
    }
    
    // All or nothing
});
```

### Queue Optimization

**Configure Queue Priorities:**
```php
// config/queue.php
'redis' => [
    'driver' => 'redis',
    'connection' => 'default',
    'queue' => 'default',
    'retry_after' => 90,
    'block_for' => 5,  // Wait 5 seconds for jobs
],
```

**Batch Job Processing:**
```php
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

$batch = Bus::batch([
    new ProcessOrder($order1),
    new ProcessOrder($order2),
    new ProcessOrder($order3),
])
->then(function (Batch $batch) {
    // All jobs completed successfully
    Log::info('Batch completed', ['batch_id' => $batch->id]);
})
->catch(function (Batch $batch, Throwable $e) {
    // First batch job failure
    Log::error('Batch failed', ['error' => $e->getMessage()]);
})
->finally(function (Batch $batch) {
    // Batch has finished executing
})
->dispatch();
```

### API Response Optimization

**Use API Resources:**
```php
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductCollection;

// Single resource
return new ProductResource($product);

// Collection
return ProductResource::collection(Product::paginate(50));

// With conditional loading
return new ProductResource($product->load(['category', 'inventory']));
```

**Pagination:**
```php
// Cursor pagination (better for large datasets)
$products = Product::cursor()->paginate(50);

// Simple pagination (faster, no total count)
$products = Product::simplePaginate(50);

// Standard pagination
$products = Product::paginate(50);
```

**Filter and Sort Efficiently:**
```php
public function index(Request $request)
{
    $query = Product::query();
    
    // Apply filters
    if ($request->has('category_id')) {
        $query->where('category_id', $request->category_id);
    }
    
    if ($request->has('min_price')) {
        $query->where('price', '>=', $request->min_price);
    }
    
    if ($request->has('max_price')) {
        $query->where('price', '<=', $request->max_price);
    }
    
    // Apply sorting
    if ($request->has('sort')) {
        $query->orderBy($request->sort, $request->get('order', 'asc'));
    }
    
    return ProductResource::collection($query->paginate(50));
}
```

## Frontend Optimization

### Asset Optimization

**Minify and Combine Assets:**
```bash
# Build for production
npm run build

# Or with Vite
npm run preview
```

**Use CDN for Static Assets:**
```php
// config/filesystems.php
'public' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'url' => env('CDN_URL'),  // CDN URL
],
```

### Lazy Loading

**Lazy Load Images:**
```blade
<img src="{{ $product->image_url }}" 
     alt="{{ $product->name }}"
     loading="lazy"
     width="300"
     height="300">
```

**Lazy Load API Data:**
```javascript
// Fetch data when needed
async function loadProducts() {
    const response = await fetch('/api/v1/products');
    const products = await response.json();
    // Render products
}
```

## Monitoring and Profiling

### Laravel Debugbar (Staging Only)

**Install Debugbar:**
```bash
composer require barryvdh/laravel-debugbar --dev
```

**Access Debugbar:**
```
https://staging.mswms.example.com/_debugbar
```

### Blackfire Profiling

**Profile Endpoint:**
```bash
blackfire curl https://api.mswms.example.com/api/v1/products
```

**Analyze Results:**
- Check call count
- Identify slow queries
- Find memory leaks
- Optimize hot paths

### Query Logging

**Enable Query Logging (Staging):**
```php
// In service provider
DB::listen(function ($query) {
    Log::info('Query', [
        'sql' => $query->sql,
        'bindings' => $query->bindings,
        'time' => $query->time,
    ]);
});
```

**Identify Slow Queries:**
```sql
-- PostgreSQL
SELECT query, calls, total_exec_time, mean_exec_time
FROM pg_stat_statements
ORDER BY mean_exec_time DESC
LIMIT 10;

-- MySQL
SELECT * FROM mysql.slow_log
ORDER BY start_time DESC
LIMIT 10;
```

## Performance Checklist

### Database
- [ ] Indexes created for foreign keys
- [ ] Composite indexes for common queries
- [ ] Query execution time < 100ms
- [ ] No N+1 queries
- [ ] Database configuration optimized

### Caching
- [ ] Redis configured and running
- [ ] Frequently accessed data cached
- [ ] Cache invalidation implemented
- [ ] HTTP caching headers set
- [ ] CDN configured for static assets

### PHP
- [ ] OPcache enabled and configured
- [ ] Autoloader optimized
- [ ] PHP-FPM tuned
- [ ] Memory limits set appropriately

### Application
- [ ] Eloquent queries optimized
- [ ] Queue workers configured
- [ ] API responses paginated
- [ ] Lazy loading disabled where appropriate

### Monitoring
- [ ] APM tool installed
- [ ] Slow query logging enabled
- [ ] Performance benchmarks established
- [ ] Regular profiling scheduled

## Performance Benchmarks

### Target Metrics

| Metric | Target | Critical |
|--------|--------|----------|
| Response Time (p50) | < 100ms | > 500ms |
| Response Time (p95) | < 500ms | > 2000ms |
| Response Time (p99) | < 1000ms | > 5000ms |
| Error Rate | < 0.1% | > 1% |
| Database Query Time | < 50ms | > 500ms |
| Cache Hit Rate | > 90% | < 50% |
| Queue Processing Time | < 5s | > 30s |

### Load Testing

**Install Apache Bench:**
```bash
sudo apt install -y apache2-utils
```

**Run Load Test:**
```bash
# 100 requests, 10 concurrent
ab -n 100 -c 10 https://api.mswms.example.com/api/v1/health

# Analyze results
# Look for: Requests per second, Time per request
```

**Install wrk (Advanced):**
```bash
sudo apt install -y wrk

# Run load test
wrk -t12 -c400 -d30s https://api.mswms.example.com/api/v1/health
```

---

**Previous Section**: [← Security Hardening](10-security-hardening.md)  
**Next Section**: [Backup & Recovery →](12-backup-recovery.md)
