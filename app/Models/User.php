<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'store_id', 'warehouse_id', 'is_super_admin', 'is_active', 'last_login_at', 'last_login_ip'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the tenant that the user belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the store that the user is associated with.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the warehouse that the user is associated with.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the roles assigned to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    /**
     * Assign a role to the user.
     */
    public function assignRole(Role|string $role): void
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->where('tenant_id', $this->tenant_id)->firstOrFail();
        }

        $this->roles()->syncWithoutDetaching([$role->id => ['tenant_id' => $this->tenant_id]]);
    }

    /**
     * Remove a role from the user.
     */
    public function removeRole(Role|string $role): void
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->where('tenant_id', $this->tenant_id)->firstOrFail();
        }

        $this->roles()->detach($role->id);
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $roleSlug): bool
    {
        return $this->roles()->where('slug', $roleSlug)->exists();
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $roleSlugs): bool
    {
        return $this->roles()->whereIn('slug', $roleSlugs)->exists();
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        // Admin has all permissions
        if ($this->hasRole('admin')) {
            return true;
        }

        // Check permissions from assigned roles
        foreach ($this->roles as $role) {
            if ($role->hasPermission($permissionSlug)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissionSlugs): bool
    {
        // Admin has all permissions
        if ($this->hasRole('admin')) {
            return true;
        }

        foreach ($permissionSlugs as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Check if user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin === true;
    }

    /**
     * Check if user is active.
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Change the user's password and invalidate all existing tokens.
     *
     * This method ensures that when a password is changed:
     * 1. All existing API tokens are revoked (forcing re-authentication)
     * 2. An audit log is created for security tracking
     * 3. A password changed notification can be sent
     *
     * @param  string  $newPassword  The new plain-text password
     * @param  int|null  $changedByUserId  ID of user who changed the password (null if self-change)
     */
    public function changePassword(string $newPassword, ?int $changedByUserId = null): void
    {
        $this->password = $newPassword;
        $this->save();

        // Invalidate all existing tokens (force re-authentication)
        $this->tokens()->delete();

        // Create audit log only if user has tenant_id
        if ($this->tenant_id) {
            AuditLog::create([
                'tenant_id' => $this->tenant_id,
                'user_id' => $this->id,
                'event_type' => 'auth.password_changed',
                'auditable_type' => 'App\\Models\\User',
                'auditable_id' => $this->id,
                'description' => 'Password changed' . ($changedByUserId ? ' by user ' . $changedByUserId : ''),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'metadata' => [
                    'changed_by_user_id' => $changedByUserId,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
        }

        // Optional: Send notification
        // $this->sendPasswordChangedNotification();
    }
}
