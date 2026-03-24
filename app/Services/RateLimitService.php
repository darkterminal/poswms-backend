<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Rate limit service for monitoring and logging rate limit events.
 *
 * Provides centralized rate limit tracking, logging, and monitoring
 * capabilities across the application.
 */
class RateLimitService
{
    /**
     * Cache key prefix for rate limit tracking.
     */
    protected string $cachePrefix = 'rate_limit:';

    /**
     * Log a rate limit exceeded event.
     *
     * @param  Request  $request  The HTTP request
     * @param  string  $limiter  The name of the rate limiter
     * @param  int  $maxAttempts  Maximum allowed attempts
     * @param  int  $currentAttempts  Current attempt count
     */
    public function logRateLimitExceeded(
        Request $request,
        string $limiter,
        int $maxAttempts,
        int $currentAttempts
    ): void {
        if (! config('rate-limiting.logging.enabled')) {
            return;
        }

        $logLevel = config('rate-limiting.logging.log_level', 'warning');

        Log::log($logLevel, 'Rate limit exceeded', [
            'limiter' => $limiter,
            'max_attempts' => $maxAttempts,
            'current_attempts' => $currentAttempts,
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
            'method' => $request->method(),
        ]);
    }

    /**
     * Log a rate limit block event.
     *
     * @param  Request  $request  The HTTP request
     * @param  string  $limiter  The name of the rate limiter
     * @param  int  $blockDuration  Duration of the block in seconds
     */
    public function logRateLimitBlock(
        Request $request,
        string $limiter,
        int $blockDuration
    ): void {
        if (! config('rate-limiting.logging.enabled', true)) {
            return;
        }

        Log::warning('Rate limit block applied', [
            'limiter' => $limiter,
            'block_duration_seconds' => $blockDuration,
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
            'method' => $request->method(),
        ]);
    }

    /**
     * Get rate limit status for a user/IP.
     *
     * @param  Request  $request  The HTTP request
     * @param  string  $limiter  The name of the rate limiter
     * @return array{remaining: int, limit: int, reset: int, blocked: bool}
     */
    public function getStatus(Request $request, string $limiter): array
    {
        $key = $this->getCacheKey($request, $limiter);
        $data = Cache::get($key, ['attempts' => 0, 'blocked_until' => 0]);

        $now = now()->timestamp;
        $blockedUntil = $data['blocked_until'] ?? 0;
        $isBlocked = $blockedUntil > $now;

        return [
            'remaining' => max(0, ($data['limit'] ?? 0) - ($data['attempts'] ?? 0)),
            'limit' => $data['limit'] ?? 0,
            'reset' => $blockedUntil,
            'blocked' => $isBlocked,
        ];
    }

    /**
     * Clear rate limit for a user/IP.
     *
     * @param  Request  $request  The HTTP request
     * @param  string  $limiter  The name of the rate limiter
     */
    public function clearLimit(Request $request, string $limiter): void
    {
        $key = $this->getCacheKey($request, $limiter);
        Cache::forget($key);
    }

    /**
     * Get cache key for rate limit tracking.
     *
     * @param  Request  $request  The HTTP request
     * @param  string  $limiter  The name of the rate limiter
     */
    protected function getCacheKey(Request $request, string $limiter): string
    {
        $identifier = $request->user()?->id ?? $request->ip();

        return $this->cachePrefix . $limiter . ':' . md5($identifier);
    }

    /**
     * Check if current user is an admin.
     *
     * @param  Request  $request  The HTTP request
     */
    public function isAdminUser(Request $request): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        // Check for super admin
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        // Check for admin role
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('admin');
        }

        return false;
    }

    /**
     * Get rate limit configuration for a specific limiter.
     *
     * @param  string  $limiter  The name of the rate limiter
     * @param  string  $tier  User tier (authenticated, guest, admin)
     */
    public function getConfig(string $limiter, string $tier = 'authenticated'): ?array
    {
        $config = config("rate-limiting.{$limiter}");

        if (! $config || ! isset($config[$tier])) {
            return null;
        }

        return $config[$tier];
    }
}
