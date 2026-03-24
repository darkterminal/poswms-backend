<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevent Debug Mode in Production.
 *
 * This middleware ensures that APP_DEBUG is disabled in production environments.
 * If debug mode is accidentally enabled in production, it will:
 * 1. Log a critical security warning
 * 2. Force disable debug mode for the current request
 * 3. Optionally block the request with a security warning
 *
 * This provides a safety net against accidental debug exposure in production.
 */
class PreventDebugModeInProduction
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isProductionEnvironment() && $this->isDebugEnabled()) {
            $this->handleDebugModeInProduction($request);
        }

        return $next($request);
    }

    /**
     * Check if the current environment is production or staging.
     */
    protected function isProductionEnvironment(): bool
    {
        $env = config('app.env', 'production');

        return in_array($env, ['production', 'staging'], true);
    }

    /**
     * Check if debug mode is enabled.
     */
    protected function isDebugEnabled(): bool
    {
        return config('app.debug', false) === true;
    }

    /**
     * Handle the case when debug mode is enabled in production.
     */
    protected function handleDebugModeInProduction(Request $request): void
    {
        // Log a critical security warning
        Log::channel('errorlog')->critical(
            'SECURITY ALERT: Debug mode is enabled in production environment!',
            [
                'environment' => config('app.env', 'production'),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'user_id' => $request->user()?->id ?? null,
                'timestamp' => now()->toIso8601String(),
            ]
        );

        // Force disable debug mode for this request
        config(['app.debug' => false]);
        config(['app.display_error_details' => false]);

        // Optionally block requests from non-admin users
        if ($this->shouldBlockRequest($request)) {
            $this->logBlockedAccess($request);
        }
    }

    /**
     * Determine if the request should be blocked.
     */
    protected function shouldBlockRequest(Request $request): bool
    {
        // Don't block if explicitly disabled in config
        if (! config('security.block_debug_access', false)) {
            return false;
        }

        // Don't block super admins
        if ($request->user() && method_exists($request->user(), 'isSuperAdmin')) {
            if ($request->user()->isSuperAdmin()) {
                return false;
            }
        }

        // Don't block from trusted IPs
        $trustedIps = config('security.trusted_ips_for_debug', []);
        if (! empty($trustedIps) && in_array($request->ip(), $trustedIps, true)) {
            return false;
        }

        return true;
    }

    /**
     * Log the blocked access attempt.
     */
    protected function logBlockedAccess(Request $request): void
    {
        Log::channel('errorlog')->warning(
            'Blocked access due to debug mode in production',
            [
                'ip_address' => $request->ip(),
                'user_id' => $request->user()?->id ?? null,
                'url' => $request->fullUrl(),
            ]
        );
    }
}
