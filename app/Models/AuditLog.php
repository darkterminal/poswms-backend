<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    /**
     * High-risk security event types.
     */
    public const HIGH_RISK_EVENTS = [
        // Authentication events
        'auth.login_failed',
        'auth.login_locked',
        'auth.logout',
        'auth.token_revoked',
        'auth.impersonation_started',
        'auth.impersonation_ended',

        // Authorization events
        'authorization.denied',
        'permission.assigned',
        'permission.revoked',
        'role.assigned',
        'role.revoked',
        'role.created',
        'role.updated',
        'role.deleted',

        // User management events
        'user.created',
        'user.updated',
        'user.deleted',
        'user.deactivated',
        'user.activated',

        // Webhook events
        'webhook.created',
        'webhook.updated',
        'webhook.deleted',
        'webhook.ssrf_blocked',
        'webhook.test_blocked',

        // Security events
        'security.ssrf_detected',
        'security.rate_limit_exceeded',
        'security.suspicious_activity',
        'security.data_export',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'event_type',
        'description',
        'auditable_type',
        'auditable_id',
        'url',
        'ip_address',
        'user_agent',
        'old_values',
        'new_values',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Get the tenant that owns the audit log.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent auditable model.
     */
    public function auditable(): BelongsTo
    {
        return $this->belongsTo();
    }

    /**
     * Scope a query to only include audit logs for a specific tenant.
     */
    public function scopeForTenant($query, int $tenantId): void
    {
        $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include audit logs for a specific event type.
     */
    public function scopeEventType($query, string $eventType): void
    {
        $query->where('event_type', $eventType);
    }

    /**
     * Scope a query to only include audit logs for a specific auditable model.
     */
    public function scopeForAuditable($query, string $type, int $id): void
    {
        $query->where('auditable_type', $type)
            ->where('auditable_id', $id);
    }

    /**
     * Scope a query to only include audit logs for a specific user.
     */
    public function scopeForUser($query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include audit logs within a date range.
     */
    public function scopeBetweenDates($query, string $startDate, string $endDate): void
    {
        $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include high-risk security events.
     */
    public function scopeHighRisk($query): void
    {
        $query->whereIn('event_type', self::HIGH_RISK_EVENTS);
    }

    /**
     * Scope a query to only include authentication events.
     */
    public function scopeAuthEvents($query): void
    {
        $query->where('event_type', 'like', 'auth.%');
    }

    /**
     * Scope a query to only include authorization events.
     */
    public function scopeAuthorizationEvents($query): void
    {
        $query->where('event_type', 'like', 'authorization.%');
    }

    /**
     * Scope a query to only include webhook security events.
     */
    public function scopeWebhookEvents($query): void
    {
        $query->where('event_type', 'like', 'webhook.%');
    }

    /**
     * Scope a query to only include security events.
     */
    public function scopeSecurityEvents($query): void
    {
        $query->where('event_type', 'like', 'security.%');
    }

    /**
     * Scope a query to only include failed authentication attempts.
     */
    public function scopeFailedAuth($query): void
    {
        $query->where(function ($q) {
            $q->where('event_type', 'auth.login_failed')
                ->orWhere('event_type', 'auth.login_locked');
        });
    }

    /**
     * Scope a query to only include permission/role changes.
     */
    public function scopePermissionChanges($query): void
    {
        $query->where(function ($q) {
            $q->where('event_type', 'like', 'permission.%')
                ->orWhere('event_type', 'like', 'role.%');
        });
    }

    /**
     * Check if this audit log entry is a high-risk event.
     */
    public function isHighRisk(): bool
    {
        return in_array($this->event_type, self::HIGH_RISK_EVENTS, true);
    }

    /**
     * Get the risk level for this event.
     *
     * @return string Risk level (high, medium, low)
     */
    public function getRiskLevelAttribute(): string
    {
        return match (true) {
            str_contains($this->event_type, 'ssrf'),
            str_contains($this->event_type, 'locked') => 'high',
            str_contains($this->event_type, 'denied'),
            str_contains($this->event_type, 'failed'),
            str_contains($this->event_type, 'suspicious') => 'medium',
            default => 'low',
        };
    }
}
