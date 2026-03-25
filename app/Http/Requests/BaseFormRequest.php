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
     * For backward compatibility, this ALWAYS returns true but logs warnings
     * when permissions are missing. This allows the application to continue
     * functioning while monitoring authorization gaps.
     *
     * @param  string|null  $permission  The permission slug to check
     * @param  string  $action  The action being performed (for logging)
     * @return bool Always returns true for backward compatibility
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
            // No user - allow for backward compatibility but log
            Log::warning('Form request without authenticated user', [
                'request_class' => static::class,
                'action' => $action,
                'permission' => $permission,
            ]);

            return true;
        }

        // Super admins and admins have all permissions
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        $hasPermission = $user->hasPermission($permission);

        if (! $hasPermission) {
            // Log warning but still allow for backward compatibility
            Log::warning('Authorization check failed - allowing for backward compatibility', [
                'permission' => $permission,
                'action' => $action,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ip' => $this->ip(),
            ]);
        }

        // Always return true for backward compatibility (soft enforcement)
        return true;
    }

    /**
     * Check if user has any of the given permissions (soft enforcement).
     *
     * For backward compatibility, this ALWAYS returns true but logs warnings
     * when permissions are missing. This allows the application to continue
     * functioning while monitoring authorization gaps.
     *
     * @param  array<string>  $permissions  List of permission slugs
     * @param  string  $action  The action being performed
     * @return bool Always returns true for backward compatibility
     */
    protected function authorizeAnySoft(array $permissions, string $action = 'access'): bool
    {
        $user = $this->user();

        if (! $user) {
            // No user - allow for backward compatibility but log
            Log::warning('Form request without authenticated user', [
                'request_class' => static::class,
                'action' => $action,
                'permissions' => $permissions,
            ]);

            return true;
        }

        // Super admins and admins have all permissions
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        $hasAnyPermission = $user->hasAnyPermission($permissions);

        if (! $hasAnyPermission) {
            // Log warning but still allow for backward compatibility
            Log::warning('Authorization check failed - no matching permissions - allowing for backward compatibility', [
                'permissions' => $permissions,
                'action' => $action,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ip' => $this->ip(),
            ]);
        }

        // Always return true for backward compatibility (soft enforcement)
        return true;
    }
}
