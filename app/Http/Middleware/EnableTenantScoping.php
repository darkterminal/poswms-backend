<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to enable optional strict tenant scoping mode.
 *
 * When enabled, all tenant-scoped models will automatically
 * filter by the current tenant without requiring explicit scopes.
 */
class EnableTenantScoping
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Store tenant context in request for models to use
        if ($request->route('tenant_id')) {
            $request->attributes->set('current_tenant_id', $request->route('tenant_id'));
        }

        return $next($request);
    }
}
