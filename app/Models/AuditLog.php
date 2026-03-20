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
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'event_type',
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
}
