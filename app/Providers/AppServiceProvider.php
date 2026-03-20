<?php

namespace App\Providers;

use App\AuditLogService;
use App\Models\Product;
use App\Observers\AuditObserver;
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
            // Higher limit for authenticated users, lower for guests
            return $request->user()
                ? Limit::perMinute(100)->by($request->user()->id)
                : Limit::perMinute(30)->by($request->ip());
        });

        // Admin rate limiter - higher limits for admin operations
        RateLimiter::for('api-admin', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(200)->by($request->user()->id)
                : Limit::perMinute(10)->by($request->ip());
        });

        // Heavy operations (imports, exports, bulk operations)
        RateLimiter::for('api-heavy', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(20)->by($request->user()->id)
                : Limit::perMinute(5)->by($request->ip());
        });

        // Authentication endpoints - strict limits to prevent brute force
        RateLimiter::for('auth', function (Request $request) {
            return [
                Limit::perMinute(10)->by($request->ip()),
                Limit::perHour(50)->by($request->ip()),
            ];
        });
    }
}
