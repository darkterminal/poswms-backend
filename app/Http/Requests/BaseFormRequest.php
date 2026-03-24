<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

/**
 * Base Form Request with soft-enforcement authorization.
 *
 * This class provides authorization helpers that default to ALLOW
 * for backward compatibility, but log warnings for missing permissions.
 */
abstract class BaseFormRequest extends FormRequest
{
    /**
     * Check authorization with soft enforcement.
     *
     * @param  string|null  $permission  The permission slug to check
     * @param  string  $action  The action being performed (for logging)
     * @return bool True if authorized, always returns true for backward compatibility
     */
    protected function authorizeSoft(?string $permission = null, string $action = 'access'): bool
    {
        // If no permission specified, allow but log for monitoring
        if ($permission === null) {
            Log::warning('Form request without permission check', [
                'request_class' => static::class,
                'action' => $action,
                'user_id' => $this->user()?->id,
                'user_email' => $this->user()?->email,
                'ip' => $this->ip(),
            ]);

            return true;
        }

        // Check if user has the permission
        $user = $this->user();

        if (! $user) {
            return false;
        }

        // Super admins and admins have all permissions
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        $hasPermission = $user->hasPermission($permission);

        if (! $hasPermission) {
            Log::warning('Authorization check failed', [
                'permission' => $permission,
                'action' => $action,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ip' => $this->ip(),
            ]);
        }

        return $hasPermission;
    }

    /**
     * Check if user has any of the given permissions (soft enforcement).
     *
     * @param  array<string>  $permissions  List of permission slugs
     * @param  string  $action  The action being performed
     * @return bool True if user has any permission or for backward compatibility
     */
    protected function authorizeAnySoft(array $permissions, string $action = 'access'): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        // Super admins and admins have all permissions
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        $hasAnyPermission = $user->hasAnyPermission($permissions);

        if (! $hasAnyPermission) {
            Log::warning('Authorization check failed - no matching permissions', [
                'permissions' => $permissions,
                'action' => $action,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ip' => $this->ip(),
            ]);
        }

        return $hasAnyPermission;
    }
}
