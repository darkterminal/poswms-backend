<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as EloquentScope;
use Illuminate\Support\Facades\Log;

/**
 * Global scope to automatically filter models by tenant_id.
 *
 * This scope prevents cross-tenant data access by ensuring all queries
 * are filtered by the current tenant context.
 */
class TenantScope implements EloquentScope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Get tenant ID from request context
        $tenantId = $this->getCurrentTenantId();

        // If no tenant ID in context, skip scoping (allows global queries)
        if ($tenantId === null) {
            Log::debug('TenantScope skipped - no tenant context', [
                'model' => get_class($model),
            ]);

            return;
        }

        // Apply tenant scoping
        $builder->where($model->getTable() . '.tenant_id', $tenantId);

        Log::debug('TenantScope applied', [
            'model' => get_class($model),
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Get the current tenant ID from the request context.
     */
    protected function getCurrentTenantId(): ?int
    {
        $request = request();

        if (! $request) {
            return null;
        }

        // Try to get from route parameter
        $tenantId = $request->route('tenant_id');

        if ($tenantId) {
            return (int) $tenantId;
        }

        // Try to get from request attributes (set by middleware)
        $tenantId = $request->attributes->get('current_tenant_id');

        if ($tenantId) {
            return (int) $tenantId;
        }

        // Try to get from authenticated user
        $user = $request->user();
        if ($user && $user->tenant_id) {
            return (int) $user->tenant_id;
        }

        return null;
    }

    /**
     * Extend the query builder with tenant-scoping helpers.
     */
    public function extend(Builder $builder): void
    {
        // Add method to temporarily disable scoping
        $builder->macro('withoutTenantScoping', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });

        // Add method to explicitly set tenant
        $builder->macro('forTenant', function (Builder $builder, int $tenantId) {
            return $builder->where('tenant_id', $tenantId);
        });
    }
}
