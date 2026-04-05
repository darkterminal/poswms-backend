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

class InventoryLayer extends Model
{
    use HasFactory, ScopedByTenant, HasMoney;

    protected function moneyFields(): array
    {
        return ['unit_cost', 'total_cost'];
    }

    /**
     * The attributes that are mass assignable.
     * Security: Explicitly defined to prevent mass assignment vulnerabilities (OWASP A04).
     */
    protected $fillable = [
        'tenant_id',
        'product_id',
        'inventory_id',
        'batch_id',
        'warehouse_id',
        'store_id',
        'quantity',
        'reserved',
        'available',
        'unit_cost',
        'total_cost',
        'layer_order',
        'is_fifo_layer',
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
            'quantity' => 'integer',
            'reserved' => 'integer',
            'available' => 'integer',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
            'layer_order' => 'integer',
            'is_fifo_layer' => 'boolean',
        ];
    }

    /**
     * Boot the model - add security validations and auto-calculations.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Validate and sanitize FIFO layer data before saving (OWASP A04, A08)
        static::saving(function (self $layer) {
            // Ensure quantity values are non-negative
            if ($layer->quantity < 0) {
                Log::warning('Negative quantity detected in inventory layer', [
                    'layer_id' => $layer->id,
                    'inventory_id' => $layer->inventory_id,
                    'product_id' => $layer->product_id,
                    'tenant_id' => $layer->tenant_id,
                ]);
                $layer->quantity = 0;
            }

            if ($layer->reserved < 0) {
                Log::warning('Negative reserved quantity detected in inventory layer', [
                    'layer_id' => $layer->id,
                    'inventory_id' => $layer->inventory_id,
                    'product_id' => $layer->product_id,
                    'tenant_id' => $layer->tenant_id,
                ]);
                $layer->reserved = 0;
            }

            // Ensure unit_cost is non-negative (OWASP A04)
            if ($layer->unit_cost < 0) {
                Log::warning('Negative unit cost detected in inventory layer', [
                    'layer_id' => $layer->id,
                    'inventory_id' => $layer->inventory_id,
                    'product_id' => $layer->product_id,
                    'tenant_id' => $layer->tenant_id,
                ]);
                $layer->unit_cost = 0;
            }

            // Auto-calculate total_cost and available (data integrity - OWASP A08)
            $layer->total_cost = $layer->quantity * $layer->unit_cost;
            $layer->available = $layer->quantity - $layer->reserved;
        });

        // Log layer changes for audit trail (OWASP A09)
        static::updated(function (self $layer) {
            $changes = $layer->getChanges();
            if (! empty($changes)) {
                Log::info('Inventory layer updated', [
                    'layer_id' => $layer->id,
                    'inventory_id' => $layer->inventory_id,
                    'product_id' => $layer->product_id,
                    'tenant_id' => $layer->tenant_id,
                    'changes' => $changes,
                ]);
            }
        });

        // Log layer creation (OWASP A09)
        static::created(function (self $layer) {
            Log::info('Inventory layer created', [
                'layer_id' => $layer->id,
                'inventory_id' => $layer->inventory_id,
                'product_id' => $layer->product_id,
                'tenant_id' => $layer->tenant_id,
                'quantity' => $layer->quantity,
                'unit_cost' => $layer->unit_cost,
            ]);
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
     * Scope to get FIFO layers only.
     */
    public function scopeFifoLayers(Builder $query): Builder
    {
        return $query->where('is_fifo_layer', true);
    }

    /**
     * Scope to get layers with available stock.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('available', '>', 0);
    }

    /**
     * Scope to get layers with stock.
     */
    public function scopeWithStock(Builder $query): Builder
    {
        return $query->where('quantity', '>', 0);
    }

    /**
     * Scope to filter by inventory.
     */
    public function scopeForInventory(Builder $query, int $inventoryId): Builder
    {
        return $query->where('inventory_id', $inventoryId);
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
     * Scope to order by FIFO (oldest first).
     */
    public function scopeFifoOrder(Builder $query): Builder
    {
        return $query->orderBy('layer_order', 'asc');
    }

    /**
     * Scope to order by LIFO (newest first).
     */
    public function scopeLifoOrder(Builder $query): Builder
    {
        return $query->orderBy('layer_order', 'desc');
    }

    /**
     * Get the next layer order for an inventory.
     */
    public static function getNextLayerOrder(int $inventoryId): int
    {
        $maxOrder = self::where('inventory_id', $inventoryId)->max('layer_order');

        return ($maxOrder ?? 0) + 1;
    }

    /**
     * Consume quantity from this layer using FIFO.
     * Returns the actual quantity consumed and the cost.
     */
    public function consumeQuantity(int $quantity): array
    {
        $consumed = min($quantity, $this->available);
        $cost = $consumed * $this->unit_cost;

        if ($consumed > 0) {
            $this->quantity -= $consumed;
            $this->available = $this->quantity - $this->reserved;

            if ($this->quantity <= 0) {
                $this->delete();
            } else {
                $this->save();
            }
        }

        return [
            'consumed' => $consumed,
            'cost' => $cost,
            'unit_cost' => $this->unit_cost,
            'layer_id' => $this->id,
        ];
    }

    /**
     * Reserve quantity in this layer.
     */
    public function reserveQuantity(int $quantity): void
    {
        $toReserve = min($quantity, $this->available);
        $this->reserved += $toReserve;
        $this->save();
    }

    /**
     * Release reserved quantity in this layer.
     */
    public function releaseQuantity(int $quantity): void
    {
        $this->reserved = max(0, $this->reserved - $quantity);
        $this->save();
    }

    /**
     * Add quantity to this layer.
     */
    public function addQuantity(int $quantity, ?float $unitCost = null): void
    {
        if ($unitCost !== null) {
            $this->unit_cost = $unitCost;
        }

        $this->quantity += $quantity;
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

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'layer_id');
    }

    /**
     * Get layers for inventory ordered by FIFO.
     */
    public static function getForInventoryFifo(int $inventoryId): \Illuminate\Support\Collection
    {
        return self::forInventory($inventoryId)
            ->fifoLayers()
            ->fifoOrder()
            ->get();
    }

    /**
     * Get total available quantity across all FIFO layers for an inventory.
     */
    public static function getTotalAvailableForInventory(int $inventoryId): int
    {
        return self::forInventory($inventoryId)
            ->fifoLayers()
            ->available()
            ->sum('available');
    }

    /**
     * Get weighted average cost for inventory.
     */
    public static function getWeightedAverageCost(int $inventoryId): float
    {
        $layers = self::forInventory($inventoryId)
            ->fifoLayers()
            ->withStock()
            ->get();

        $totalQuantity = $layers->sum('quantity');
        $totalCost = $layers->sum('total_cost');

        return $totalQuantity > 0 ? $totalCost / $totalQuantity : 0.0;
    }

    /**
     * Create a new FIFO layer.
     */
    public static function createFifoLayer(
        int $tenantId,
        int $productId,
        int $inventoryId,
        int $warehouseId,
        int $quantity,
        float $unitCost,
        ?int $batchId = null,
        ?int $storeId = null
    ): self {
        return self::create([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'inventory_id' => $inventoryId,
            'batch_id' => $batchId,
            'warehouse_id' => $warehouseId,
            'store_id' => $storeId,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'layer_order' => self::getNextLayerOrder($inventoryId),
            'is_fifo_layer' => true,
        ]);
    }
}
