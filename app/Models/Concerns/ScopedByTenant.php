<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;

/**
 * Trait for models that should be automatically scoped by tenant_id.
 *
 * Usage: Add this trait to any model that has a tenant_id column
 * to enable automatic tenant filtering on all queries.
 */
trait ScopedByTenant
{
    /**
     * Boot the trait and register the global scope.
     */
    public static function bootScopedByTenant(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    /**
     * Scope a query to only include records for a specific tenant.
     * Use this to override the global scope with an explicit tenant.
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include records for the current tenant context.
     */
    public function scopeCurrentTenant($query)
    {
        return $query; // Global scope already handles this
    }

    /**
     * Get a new query builder without the tenant scope.
     * WARNING: Use with caution - bypasses tenant security!
     */
    public static function queryWithoutTenantScope()
    {
        return (new static)->newQuery()->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Set the tenant_id attribute.
     */
    public function setTenantIdAttribute($value): void
    {
        $this->attributes['tenant_id'] = (int) $value;
    }
}
