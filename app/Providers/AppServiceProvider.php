<?php

namespace App\Providers;

use App\AuditLogService;
use App\Models\Product;
use App\Observers\AuditObserver;
use App\SecurityAuditLogger;
use App\Services\RateLimitService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AuditLogService::class, AuditLogService::class);
        $this->app->singleton(ExportService::class, ExportService::class);
        $this->app->singleton(RateLimitService::class, RateLimitService::class);
        $this->app->singleton(SecurityAuditLogger::class, SecurityAuditLogger::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register audit observer for Product model
        Product::observe(AuditObserver::class);

        // API rate limiter - default for all API routes
        RateLimiter::for('api', function (Request $request) {
            $limit = config('rate-limiting.api');
            $config = $request->user() ? $limit['authenticated'] : $limit['guest'];

            return Limit::perMinutes(
                (int) ($config['decay_rate_seconds'] / 60),
                (int) $config['max_attempts']
            )->by($request->user()?->id ?? $request->ip())
                ->response(fn() => $this->rateLimitResponse());
        });

        // Admin rate limiter - higher limits for admin operations
        RateLimiter::for('api-admin', function (Request $request) {
            $limit = config('rate-limiting.api_admin');
            $config = $request->user() ? $limit['authenticated'] : $limit['guest'];

            return Limit::perMinutes(
                (int) ($config['decay_rate_seconds'] / 60),
                (int) $config['max_attempts']
            )->by($request->user()?->id ?? $request->ip())
                ->response(fn() => $this->rateLimitResponse());
        });

        // Heavy operations (imports, exports, bulk operations) - DEPRECATED
        // Kept for backward compatibility, use api_exports instead
        RateLimiter::for('api-heavy', function (Request $request) {
            $limit = config('rate-limiting.api_heavy');
            $config = $request->user() ? $limit['authenticated'] : $limit['guest'];

            return Limit::perMinutes(
                (int) ($config['decay_rate_seconds'] / 60),
                (int) $config['max_attempts']
            )->by($request->user()?->id ?? $request->ip())
                ->response(fn() => $this->rateLimitResponse());
        });

        // Authentication endpoints - strict limits to prevent brute force
        RateLimiter::for('auth', function (Request $request) {
            $limit = config('rate-limiting.auth');

            return [
                Limit::perMinute($limit['per_minute'])->by($request->ip()),
                Limit::perHour($limit['per_hour'])->by($request->ip()),
            ];
        });

        // Export operations - resource-heavy endpoints
        // Applied to /reports/*/export/* routes
        RateLimiter::for('api-exports', function (Request $request) {
            $limit = config('rate-limiting.api_exports');

            // Determine user tier
            if ($request->user()?->hasRole('admin')) {
                $config = $limit['admin'];
            } elseif ($request->user()) {
                $config = $limit['authenticated'];
            } else {
                $config = $limit['guest'];
            }

            // Block guests entirely if max_attempts is 0
            if ($config['max_attempts'] === 0) {
                return Limit::none();
            }

            return Limit::perMinutes(
                (int) ($config['decay_rate_seconds'] / 60),
                (int) $config['max_attempts']
            )->by($request->user()?->id ?? $request->ip())
                ->response(fn() => $this->rateLimitResponse());
        });

        // Webhook test - strict limits to prevent SSRF amplification
        // Applied to POST /webhooks/{webhook}/test
        RateLimiter::for('api-webhook-test', function (Request $request) {
            $limit = config('rate-limiting.api_webhook_test');
            $config = $request->user() ? $limit['authenticated'] : $limit['guest'];

            // Block guests entirely if max_attempts is 0
            if ($config['max_attempts'] === 0) {
                return Limit::none();
            }

            return Limit::perMinutes(
                (int) ($config['decay_rate_seconds'] / 60),
                (int) $config['max_attempts']
            )->by($request->user()?->id ?? $request->ip())
                ->response(fn() => $this->rateLimitResponse());
        });
    }

    /**
     * Generate a standardized rate limit exceeded response.
     */
    private function rateLimitResponse(): \Illuminate\Http\JsonResponse
    {
        $config = config('rate-limiting.response');

        return response()->json([
            'success' => false,
            'error' => [
                'code' => $config['code'],
                'message' => $config['message'],
            ],
        ], 429, ['Retry-After' => '60']);
    }
}
