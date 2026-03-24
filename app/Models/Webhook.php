<?php

namespace App\Models;

use App\Models\Concerns\ScopedByTenant;
use Database\Factories\WebhookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    /** @use HasFactory<WebhookFactory> */
    use HasFactory, ScopedByTenant;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'url',
        'secret',
        'events',
        'active',
        'content_type',
        'headers',
        'retry_count',
        'timeout',
        'last_triggered_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'headers' => 'encrypted:array',
            'active' => 'boolean',
            'retry_count' => 'integer',
            'timeout' => 'integer',
            'last_triggered_at' => 'datetime',
            'secret' => 'encrypted',
        ];
    }

    /**
     * Get the tenant that owns the webhook.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the delivery attempts for this webhook.
     */
    public function deliveryAttempts(): HasMany
    {
        return $this->hasMany(WebhookDeliveryAttempt::class);
    }

    /**
     * Scope a query to only include webhooks for a specific tenant.
     */
    public function scopeForTenant($query, int $tenantId): void
    {
        $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include active webhooks.
     */
    public function scopeActive($query): void
    {
        $query->where('active', true);
    }

    /**
     * Scope a query to only include webhooks that listen for a specific event.
     */
    public function scopeForEvent($query, string $event): void
    {
        $query->whereJsonContains('events', $event);
    }

    /**
     * Check if this webhook listens for a specific event.
     */
    public function listensForEvent(string $event): bool
    {
        return in_array($event, $this->events, true);
    }

    /**
     * Get the headers for this webhook request.
     */
    public function getHeaders(): array
    {
        $headers = $this->headers ?? [];

        // Set content type header
        $headers['Content-Type'] = $this->content_type === 'form-data'
            ? 'application/x-www-form-urlencoded'
            : 'application/json';

        return $headers;
    }
}
