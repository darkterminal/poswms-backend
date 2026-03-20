<?php

namespace App;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    /**
     * Log an audit event.
     *
     * @param  string  $eventType  The type of event (created, updated, deleted, etc.)
     * @param  Model  $auditable  The model being audited
     * @param  array<string, mixed>  $oldValues  The old values before the change
     * @param  array<string, mixed>  $newValues  The new values after the change
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function log(
        string $eventType,
        Model $auditable,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = []
    ): AuditLog {
        $request = request();

        return AuditLog::create([
            'tenant_id' => $auditable->tenant_id ?? $this->getCurrentTenantId(),
            'user_id' => $this->getCurrentUserId(),
            'event_type' => $eventType,
            'auditable_type' => get_class($auditable),
            'auditable_id' => $auditable->id,
            'url' => $request?->fullUrl(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log a create event.
     *
     * @param  Model  $auditable  The model that was created
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function logCreated(Model $auditable, array $metadata = []): AuditLog
    {
        return $this->log('created', $auditable, [], $auditable->toArray(), $metadata);
    }

    /**
     * Log an update event.
     *
     * @param  Model  $auditable  The model that was updated
     * @param  array<string, mixed>  $oldValues  The old values
     * @param  array<string, mixed>  $newValues  The new values
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function logUpdated(
        Model $auditable,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = []
    ): AuditLog {
        return $this->log('updated', $auditable, $oldValues, $newValues, $metadata);
    }

    /**
     * Log a delete event.
     *
     * @param  Model  $auditable  The model that was deleted
     * @param  array<string, mixed>  $oldValues  The values before deletion
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function logDeleted(Model $auditable, array $oldValues = [], array $metadata = []): AuditLog
    {
        return $this->log('deleted', $auditable, $oldValues, [], $metadata);
    }

    /**
     * Log a login event.
     *
     * @param  User  $user  The user who logged in
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function logLogin(User $user, array $metadata = []): AuditLog
    {
        $request = request();

        return AuditLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'event_type' => 'logged_in',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'url' => $request?->fullUrl(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => array_merge([
                'email' => $user->email,
                'role' => $user->role,
            ], $metadata),
        ]);
    }

    /**
     * Log a logout event.
     *
     * @param  User  $user  The user who logged out
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public function logLogout(User $user, array $metadata = []): AuditLog
    {
        $request = request();

        return AuditLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'event_type' => 'logged_out',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'url' => $request?->fullUrl(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => array_merge([
                'email' => $user->email,
            ], $metadata),
        ]);
    }

    /**
     * Get the current authenticated user ID.
     */
    private function getCurrentUserId(): ?int
    {
        return Auth::id();
    }

    /**
     * Get the current tenant ID.
     */
    private function getCurrentTenantId(): ?int
    {
        $user = Auth::user();

        return $user?->tenant_id;
    }
}
