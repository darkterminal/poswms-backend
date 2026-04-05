<?php

namespace App\Providers;

use App\Services\MoneyService;
use Illuminate\Support\ServiceProvider;

class MoneyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(MoneyService::class, function ($app) {
            return new MoneyService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/money.php' => config_path('money.php'),
        ], 'money-config');
    }
}
