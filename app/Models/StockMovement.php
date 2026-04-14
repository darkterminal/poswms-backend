<?php

namespace App\Models;

use App\Models\Concerns\HasMoney;
use App\Models\Concerns\ScopedByTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory, HasMoney, ScopedByTenant;

    protected function moneyFields(): array
    {
        return ['unit_cost', 'total_cost'];
    }

    protected $fillable = [
        'tenant_id', 'product_id', 'inventory_id', 'layer_id', 'store_id', 'warehouse_id',
        'order_id', 'user_id', 'type', 'quantity', 'unit_cost', 'total_cost',
        'quantity_before', 'quantity_after', 'reason', 'reference',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
        ];
    }

    /**
     * Boot the model - auto-calculate total_cost.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $movement) {
            // If total_cost is null, try to calculate it from unit_cost
            if ($movement->total_cost === null && $movement->unit_cost !== null) {
                $movement->total_cost = $movement->quantity * $movement->unit_cost;
            }

            // Ensure total_cost is never null to prevent silent SUM() errors
            if ($movement->total_cost === null) {
                $movement->total_cost = 0;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(InventoryLayer::class, 'layer_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function recordMovement(
        int $tenantId,
        int $productId,
        string $type,
        int $quantity,
        int $quantityBefore,
        int $quantityAfter,
        ?int $inventoryId = null,
        ?int $storeId = null,
        ?int $warehouseId = null,
        ?int $orderId = null,
        ?int $userId = null,
        ?string $reason = null,
        ?string $reference = null,
        ?int $layerId = null,
        ?float $unitCost = null
    ): self {
        return self::create([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'inventory_id' => $inventoryId,
            'layer_id' => $layerId,
            'store_id' => $storeId,
            'warehouse_id' => $warehouseId,
            'order_id' => $orderId,
            'user_id' => $userId,
            'type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'reason' => $reason,
            'reference' => $reference,
        ]);
    }

    /**
     * Record a FIFO-aware stock movement with layer tracking.
     */
    public static function recordFifoMovement(
        int $tenantId,
        int $productId,
        string $type,
        int $quantity,
        int $quantityBefore,
        int $quantityAfter,
        int $layerId,
        float $unitCost,
        ?int $inventoryId = null,
        ?int $storeId = null,
        ?int $warehouseId = null,
        ?int $orderId = null,
        ?int $userId = null,
        ?string $reason = null,
        ?string $reference = null
    ): self {
        return self::create([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'inventory_id' => $inventoryId,
            'layer_id' => $layerId,
            'store_id' => $storeId,
            'warehouse_id' => $warehouseId,
            'order_id' => $orderId,
            'user_id' => $userId,
            'type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $quantity * $unitCost,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'reason' => $reason,
            'reference' => $reference,
        ]);
    }

    /**
     * Get total cost of movements for a period.
     */
    public static function getTotalCostForPeriod(
        int $tenantId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $type = null
    ): float {
        $query = self::forTenant($tenantId)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query->sum('total_cost') ?? 0.0;
    }

    /**
     * Scope to filter by tenant.
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope to filter by layer.
     */
    public function scopeForLayer(Builder $query, int $layerId): Builder
    {
        return $query->where('layer_id', $layerId);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
