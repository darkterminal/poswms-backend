<?php

use App\Exceptions\ApiExceptionHandler;
use App\Http\Middleware\EnsureTenantIsScoped;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // Apply security headers to all API requests
        $middleware->append(SecurityHeadersMiddleware::class);

        // Register tenant scoping middleware
        $middleware->alias([
            'tenant.scoped' => EnsureTenantIsScoped::class,
            'role' => EnsureUserHasRole::class,
            'permission' => EnsureUserHasPermission::class,
            'superadmin' => EnsureUserIsSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->reportable(function (Throwable $e) {
            //
        });

        $exceptions->renderable(function (Throwable $e, $request) {
            // Use custom API exception handler for API requests
            if ($request->is('api/*')) {
                $handler = new ApiExceptionHandler(app());

                return $handler->render($request, $e);
            }
        });
    })->create();
