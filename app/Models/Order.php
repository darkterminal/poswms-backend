<?php

namespace App\Models;

use App\Models\Concerns\HasMoney;
use App\Models\Concerns\ScopedByTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, ScopedByTenant, SoftDeletes, HasMoney;

    protected function moneyFields(): array
    {
        return ['subtotal', 'tax', 'discount', 'shipping', 'total'];
    }

    protected $fillable = [
        'tenant_id', 'order_number', 'customer_id', 'store_id', 'warehouse_id',
        'user_id', 'status', 'type', 'subtotal', 'tax', 'discount', 'shipping',
        'payment_status', 'payment_method', 'notes', 'shipping_address',
        'shipping_city', 'shipping_state', 'shipping_country', 'shipping_postal_code',
        'confirmed_at', 'fulfilled_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'discount' => 'decimal:2',
            'shipping' => 'decimal:2',
            'total' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saving(function ($order) {
            // Calculate total if not explicitly set or if items changed
            if ($order->isDirty(['subtotal', 'tax', 'discount', 'shipping']) || $order->total == 0) {
                $order->total = $order->subtotal + $order->tax + $order->shipping - $order->discount;
            }
        });
    }

    /**
     * Scope to filter by tenant.
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange(Builder $query, ?string $startDate, ?string $endDate): Builder
    {
        return $query->when($startDate, fn($q) => $q->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('created_at', '<=', $endDate));
    }

    /**
     * Scope to get pending orders.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get confirmed orders.
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Scope to get fulfilled orders.
     */
    public function scopeFulfilled(Builder $query): Builder
    {
        return $query->where('status', 'fulfilled');
    }

    /**
     * Scope to get cancelled orders.
     */
    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get order summary for tenant with optimized query.
     */
    public static function getSummaryForTenant(int $tenantId): array
    {
        return [
            'total' => self::forTenant($tenantId)->count(),
            'pending' => self::forTenant($tenantId)->pending()->count(),
            'confirmed' => self::forTenant($tenantId)->confirmed()->count(),
            'fulfilled' => self::forTenant($tenantId)->fulfilled()->count(),
            'cancelled' => self::forTenant($tenantId)->cancelled()->count(),
            'revenue' => self::forTenant($tenantId)
                ->whereIn('status', ['confirmed', 'fulfilled'])
                ->sum('subtotal'),
        ];
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isFulfilled(): bool
    {
        return $this->status === 'fulfilled';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function confirm(): void
    {
        $this->update(['status' => 'confirmed', 'confirmed_at' => now()]);
    }

    public function fulfill(): void
    {
        $this->update(['status' => 'fulfilled', 'fulfilled_at' => now()]);
    }

    public function cancel(): void
    {
        $this->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }
}
