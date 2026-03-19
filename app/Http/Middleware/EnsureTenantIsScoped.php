<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantIsScoped
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract tenant_id from route parameter (expected: /api/v1/tenants/{tenant_id}/...)
        $tenantId = $request->route('tenant_id');

        if (! $tenantId) {
            return response()->json([
                'message' => 'Tenant ID is required',
                'errors' => ['tenant_id' => ['The tenant_id parameter is missing from the request URL']],
            ], Response::HTTP_BAD_REQUEST);
        }

        // Find and validate tenant
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return response()->json([
                'message' => 'Tenant not found',
                'errors' => ['tenant_id' => ['The specified tenant does not exist']],
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if tenant is active
        if (! $tenant->isActive()) {
            return response()->json([
                'message' => 'Tenant is not active',
                'errors' => ['tenant_id' => ['The specified tenant is not active']],
            ], Response::HTTP_FORBIDDEN);
        }

        // Attach tenant to request for use in controllers
        $request->merge(['tenant' => $tenant]);

        // Set tenant context for the authenticated user
        if ($request->user()) {
            // Ensure user belongs to this tenant
            if ($request->user()->tenant_id !== $tenant->id) {
                return response()->json([
                    'message' => 'Unauthorized access to tenant',
                    'errors' => ['tenant_id' => ['You do not have access to this tenant']],
                ], Response::HTTP_FORBIDDEN);
            }
        }

        return $next($request);
    }
}
