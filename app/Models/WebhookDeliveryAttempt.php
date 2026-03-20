<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDeliveryAttempt extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'webhook_id',
        'event_type',
        'url',
        'attempt_number',
        'response_status',
        'request_body',
        'response_body',
        'error_message',
        'response_time_ms',
        'success',
        'next_retry_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'attempt_number' => 'integer',
        'response_status' => 'integer',
        'response_time_ms' => 'float',
        'success' => 'boolean',
        'next_retry_at' => 'datetime',
    ];

    /**
     * Get the webhook that owns this delivery attempt.
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    /**
     * Scope a query to only include successful attempts.
     */
    public function scopeSuccessful($query): void
    {
        $query->where('success', true);
    }

    /**
     * Scope a query to only include failed attempts.
     */
    public function scopeFailed($query): void
    {
        $query->where('success', false);
    }

    /**
     * Scope a query to only include attempts for a specific event type.
     */
    public function scopeForEvent($query, string $eventType): void
    {
        $query->where('event_type', $eventType);
    }

    /**
     * Scope a query to only include attempts that need retry.
     */
    public function scopeNeedsRetry($query): void
    {
        $query->where('success', false)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now());
    }
}
