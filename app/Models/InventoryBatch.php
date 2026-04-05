<?php

namespace App\Models;

use App\Models\Concerns\HasMoney;
use App\Models\Concerns\ScopedByTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class InventoryBatch extends Model
{
    use HasFactory, ScopedByTenant, HasMoney;

    protected function moneyFields(): array
    {
        return ['unit_cost'];
    }

    /**
     * The attributes that are mass assignable.
     * Security: Explicitly defined to prevent mass assignment vulnerabilities (OWASP A04).
     */
    protected $fillable = [
        'tenant_id',
        'product_id',
        'warehouse_id',
        'supplier_id',
        'batch_number',
        'lot_number',
        'received_date',
        'expiry_date',
        'unit_cost',
        'initial_quantity',
        'remaining_quantity',
        'status',
        'notes',
        'metadata',
    ];

    /**
     * The attributes that should be hidden.
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:4',
            'received_date' => 'date',
            'expiry_date' => 'date',
            'initial_quantity' => 'integer',
            'remaining_quantity' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * Boot the model - add security validations.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Validate and sanitize batch data before saving (OWASP A04, A08)
        static::saving(function (self $batch) {
            // Ensure quantity values are non-negative
            if ($batch->initial_quantity < 0) {
                Log::warning('Negative initial quantity detected in inventory batch', [
                    'batch_id' => $batch->id,
                    'product_id' => $batch->product_id,
                    'tenant_id' => $batch->tenant_id,
                ]);
                $batch->initial_quantity = 0;
            }

            if ($batch->remaining_quantity < 0) {
                Log::warning('Negative remaining quantity detected in inventory batch', [
                    'batch_id' => $batch->id,
                    'product_id' => $batch->product_id,
                    'tenant_id' => $batch->tenant_id,
                ]);
                $batch->remaining_quantity = 0;
            }

            // Ensure unit_cost is non-negative (OWASP A04)
            if ($batch->unit_cost < 0) {
                Log::warning('Negative unit cost detected in inventory batch', [
                    'batch_id' => $batch->id,
                    'product_id' => $batch->product_id,
                    'tenant_id' => $batch->tenant_id,
                ]);
                $batch->unit_cost = 0;
            }

            // Sanitize text fields to prevent XSS (OWASP A03)
            if ($batch->batch_number) {
                $batch->batch_number = strip_tags(trim($batch->batch_number));
            }

            if ($batch->lot_number) {
                $batch->lot_number = strip_tags(trim($batch->lot_number));
            }

            if ($batch->notes) {
                $batch->notes = strip_tags(trim($batch->notes));
            }

            // Validate status whitelist (OWASP A04)
            $allowedStatuses = ['active', 'consumed', 'expired', 'cancelled'];
            if ($batch->status && ! in_array($batch->status, $allowedStatuses)) {
                Log::warning('Invalid batch status detected', [
                    'batch_id' => $batch->id,
                    'status' => $batch->status,
                    'tenant_id' => $batch->tenant_id,
                ]);
                $batch->status = 'active';
            }
        });

        // Log batch changes for audit trail (OWASP A09)
        static::updated(function (self $batch) {
            $changes = $batch->getChanges();
            if (! empty($changes)) {
                Log::info('Inventory batch updated', [
                    'batch_id' => $batch->id,
                    'product_id' => $batch->product_id,
                    'tenant_id' => $batch->tenant_id,
                    'changes' => $changes,
                ]);
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
     * Scope to get active batches.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get batches expiring soon.
     */
    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays($days));
    }

    /**
     * Scope to get expired batches.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now());
    }

    /**
     * Scope to filter by product.
     */
    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope to filter by warehouse.
     */
    public function scopeForWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Scope to search by batch or lot number.
     */
    public function scopeByBatchNumber(Builder $query, string $batchNumber): Builder
    {
        return $query->where('batch_number', $batchNumber);
    }

    /**
     * Get batches with remaining stock.
     */
    public function scopeWithRemainingStock(Builder $query): Builder
    {
        return $query->where('remaining_quantity', '>', 0);
    }

    /**
     * Check if batch is expired.
     */
    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    /**
     * Check if batch is expiring soon.
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        if ($this->expiry_date === null) {
            return false;
        }

        return $this->expiry_date->diffInDays(now()) <= $days;
    }

    /**
     * Get days until expiry.
     */
    public function daysUntilExpiry(): ?int
    {
        if ($this->expiry_date === null) {
            return null;
        }

        return max(0, $this->expiry_date->diffInDays(now()));
    }

    /**
     * Consume quantity from this batch.
     */
    public function consumeQuantity(int $quantity): void
    {
        $this->remaining_quantity = max(0, $this->remaining_quantity - $quantity);

        if ($this->remaining_quantity === 0) {
            $this->status = 'consumed';
        }

        $this->save();
    }

    /**
     * Add quantity to this batch.
     */
    public function addQuantity(int $quantity): void
    {
        $this->remaining_quantity += $quantity;
        $this->initial_quantity += $quantity;
        $this->save();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supplier_id');
    }

    public function layers(): HasMany
    {
        return $this->hasMany(InventoryLayer::class, 'batch_id');
    }

    /**
     * Get batch with product and warehouse details.
     */
    public static function getWithDetails(int $tenantId): \Illuminate\Support\Collection
    {
        return self::forTenant($tenantId)
            ->with(['product:id,name,sku', 'warehouse:id,name,code'])
            ->orderBy('received_date', 'desc')
            ->get();
    }

    /**
     * Get expiring batches summary.
     */
    public static function getExpiringSummary(int $tenantId, int $days = 30): array
    {
        $batches = self::forTenant($tenantId)
            ->expiringSoon($days)
            ->withRemainingStock()
            ->get();

        return [
            'count' => $batches->count(),
            'total_quantity' => $batches->sum('remaining_quantity'),
            'total_value' => $batches->sum(fn($b) => $b->remaining_quantity * $b->unit_cost),
            'batches' => $batches->map(fn($b) => [
                'batch_number' => $b->batch_number,
                'product_name' => $b->product->name ?? null,
                'expiry_date' => $b->expiry_date?->toDateString(),
                'remaining_quantity' => $b->remaining_quantity,
                'days_until_expiry' => $b->daysUntilExpiry(),
            ]),
        ];
    }
}
