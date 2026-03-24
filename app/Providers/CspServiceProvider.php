<?php

namespace App\Providers;

use App\Support\CspNonce;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Factory as ViewFactory;

class CspServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge the CSP configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/csp.php',
            'csp'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish the CSP configuration
        $this->publishes([
            __DIR__ . '/../../config/csp.php' => config_path('csp.php'),
        ], ['csp', 'csp-config']);

        // Share the CSP nonce with all views if it exists
        $this->app->afterResolving(ViewFactory::class, function (ViewFactory $view): void {
            if (CspNonce::has()) {
                $view->share('cspNonce', CspNonce::get());
            }
        });

        // Create a composer that runs for all views to ensure nonce is available
        $this->app->booted(function (): void {
            view()->composer('*', function ($view): void {
                if (CspNonce::has() && $view instanceof ViewFactory) {
                    $view->share('cspNonce', CspNonce::get());
                }
            });
        });
    }
}
