<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'permissions',
        'is_system',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_system' => 'boolean',
        ];
    }

    /**
     * Get the tenant that owns the role.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the permissions for the role.
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'tenant_id', 'tenant_id');
    }

    /**
     * Get the users assigned to this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    /**
     * Check if role has a specific permission.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        $permissions = $this->permissions ?? [];

        return in_array($permissionSlug, $permissions);
    }

    /**
     * Give permission to the role.
     */
    public function givePermission(string $permissionSlug): void
    {
        $permissions = $this->permissions ?? [];
        if (! in_array($permissionSlug, $permissions)) {
            $permissions[] = $permissionSlug;
            $this->update(['permissions' => $permissions]);
        }
    }

    /**
     * Revoke permission from the role.
     */
    public function revokePermission(string $permissionSlug): void
    {
        $permissions = $this->permissions ?? [];
        $key = array_search($permissionSlug, $permissions);
        if ($key !== false) {
            unset($permissions[$key]);
            $this->update(['permissions' => array_values($permissions)]);
        }
    }

    /**
     * Check if role is admin.
     */
    public function isAdmin(): bool
    {
        return $this->slug === 'admin';
    }

    /**
     * Check if role is a system role.
     */
    public function isSystem(): bool
    {
        return $this->is_system;
    }
}
