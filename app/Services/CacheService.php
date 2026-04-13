<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    /**
     * Cache key prefixes for different data types.
     */
    private const PREFIX_DASHBOARD = 'dashboard:';
    private const PREFIX_INVENTORY = 'inventory:';
    private const PREFIX_REPORTS = 'reports:';
    private const PREFIX_SETTINGS = 'settings:';

    /**
     * Default cache TTL in seconds (15 minutes).
     */
    private const DEFAULT_TTL = 900;

    /**
     * Long cache TTL in seconds (1 hour).
     */
    private const LONG_TTL = 3600;

    /**
     * Get cached dashboard metrics or execute callback.
     */
    public function rememberDashboardMetrics(int $tenantId, string $period, callable $callback): mixed
    {
        // Skip cache in testing environment
        if (app()->environment('testing')) {
            return $callback();
        }

        $key = $this->getDashboardKey($tenantId, $period);

        return Cache::remember($key, self::DEFAULT_TTL, $callback);
    }

    /**
     * Clear dashboard cache for tenant.
     */
    public function clearDashboardCache(int $tenantId): void
    {
        Cache::forget($this->getDashboardKey($tenantId, 'today'));
        Cache::forget($this->getDashboardKey($tenantId, 'week'));
        Cache::forget($this->getDashboardKey($tenantId, 'month'));
        Cache::forget($this->getDashboardKey($tenantId, 'year'));
        Cache::forget($this->getDashboardKey($tenantId, 'all'));
    }

    /**
     * Get cached inventory metrics or execute callback.
     */
    public function rememberInventoryMetrics(int $tenantId, callable $callback): mixed
    {
        // Skip cache in testing environment
        if (app()->environment('testing')) {
            return $callback();
        }

        $key = $this->getInventoryKey($tenantId);

        return Cache::remember($key, self::DEFAULT_TTL, $callback);
    }

    /**
     * Clear inventory cache for tenant.
     */
    public function clearInventoryCache(int $tenantId): void
    {
        Cache::forget($this->getInventoryKey($tenantId));
        Cache::forget($this->getInventoryKey($tenantId, 'summary'));
        Cache::forget($this->getInventoryKey($tenantId, 'low-stock'));
    }

    /**
     * Get cached report data or execute callback.
     */
    public function rememberReport(string $reportType, int $tenantId, array $params, callable $callback): mixed
    {
        // Skip cache in testing environment
        if (app()->environment('testing')) {
            return $callback();
        }

        $key = $this->getReportKey($reportType, $tenantId, $params);

        return Cache::remember($key, self::DEFAULT_TTL, $callback);
    }

    /**
     * Clear report cache for tenant.
     */
    public function clearReportCache(int $tenantId, ?string $reportType = null): void
    {
        if ($reportType) {
            Cache::forget($this->getReportKey($reportType, $tenantId, []));
        } else {
            // Clear all report caches for tenant (pattern-based)
            $this->clearTaggedCache("reports:tenant:{$tenantId}");
        }
    }

    /**
     * Get cached settings or execute callback.
     */
    public function rememberSettings(string $key, callable $callback): mixed
    {
        return Cache::remember(self::PREFIX_SETTINGS . $key, self::LONG_TTL, $callback);
    }

    /**
     * Clear settings cache.
     */
    public function clearSettingsCache(string $key): void
    {
        Cache::forget(self::PREFIX_SETTINGS . $key);
    }

    /**
     * Cache a generic value with tags.
     */
    public function tagAndRemember(string $tag, string $key, int $ttl, callable $callback): mixed
    {
        $driver = config('cache.default');

        // Tagged cache only supported by Redis and Memcached
        if (! in_array($driver, ['redis', 'memcached'])) {
            // Fallback to regular cache without tags
            return Cache::remember("{$tag}:{$key}", $ttl, $callback);
        }

        return Cache::tags([$tag])->remember($key, $ttl, $callback);
    }

    /**
     * Clear cache by tag.
     */
    public function clearTaggedCache(string $tag): void
    {
        $driver = config('cache.default');

        // Tagged cache only supported by Redis and Memcached
        if (! in_array($driver, ['redis', 'memcached'])) {
            // Log warning and skip - cache will expire naturally via TTL
            \Log::warning("Cache tags not supported for driver: {$driver}. Tagged cache skipped.");

            return;
        }

        Cache::tags([$tag])->flush();
    }

    /**
     * Clear all cache for a tenant.
     * Call this when critical data changes (e.g., product update, order fulfillment).
     */
    public function clearTenantCache(int $tenantId): void
    {
        $this->clearDashboardCache($tenantId);
        $this->clearInventoryCache($tenantId);
        $this->clearReportCache($tenantId);

        // Clear tagged caches
        $this->clearTaggedCache("tenant:{$tenantId}");
    }

    /**
     * Get dashboard cache key.
     */
    private function getDashboardKey(int $tenantId, string $period): string
    {
        return self::PREFIX_DASHBOARD . "tenant:{$tenantId}:{$period}:" . now()->format('Y-m-d-H');
    }

    /**
     * Get inventory cache key.
     */
    private function getInventoryKey(int $tenantId, ?string $type = 'metrics'): string
    {
        return self::PREFIX_INVENTORY . "tenant:{$tenantId}:{$type}:" . now()->format('Y-m-d-H');
    }

    /**
     * Get report cache key.
     */
    private function getReportKey(string $reportType, int $tenantId, array $params): string
    {
        $paramHash = md5(json_encode($params));

        return self::PREFIX_REPORTS . "tenant:{$tenantId}:{$reportType}:{$paramHash}:" . now()->format('Y-m-d');
    }

    /**
     * Get cache statistics.
     */
    public function getStats(): array
    {
        return [
            'driver' => config('cache.default'),
            'prefix' => config('cache.prefix'),
            'stores' => array_keys(config('cache.stores')),
        ];
    }

    /**
     * Flush all cache (use with caution).
     */
    public function flushAll(): void
    {
        Cache::flush();
    }
}
