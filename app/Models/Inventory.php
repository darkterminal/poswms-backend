<?php

namespace App\Models;

use App\Models\Concerns\ScopedByTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    use HasFactory, ScopedByTenant;

    protected $fillable = [
        'tenant_id', 'product_id', 'store_id', 'warehouse_id',
        'quantity', 'reserved', 'available', 'cost', 'location', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
        ];
    }

    /**
     * Scope to filter by tenant.
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('inventories.tenant_id', $tenantId);
    }

    /**
     * Scope to get low stock items.
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('available', '<=', 'min_stock');
    }

    /**
     * Scope to get out of stock items.
     */
    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('available', 0);
    }

    /**
     * Scope to get items with stock.
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('available', '>', 0);
    }

    /**
     * Scope to filter by warehouse.
     */
    public function scopeForWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Scope to filter by store.
     */
    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get inventory with product details optimized for listing.
     */
    public static function getWithProductDetails(int $tenantId): \Illuminate\Support\Collection
    {
        return self::forTenant($tenantId)
            ->with(['product:id,name,sku,min_stock', 'warehouse:id,name,code', 'store:id,name,code'])
            ->get();
    }

    /**
     * Get low stock summary for tenant.
     */
    public static function getLowStockSummary(int $tenantId): array
    {
        return [
            'count' => self::forTenant($tenantId)
                ->join('products', 'inventories.product_id', '=', 'products.id')
                ->whereColumn('inventories.available', '<=', 'products.min_stock')
                ->count(),
            'value' => self::forTenant($tenantId)
                ->join('products', 'inventories.product_id', '=', 'products.id')
                ->whereColumn('inventories.available', '<=', 'products.min_stock')
                ->sum('inventories.cost'),
        ];
    }

    public function updateQuantity(int $adjustment): void
    {
        $this->quantity += $adjustment;
        $this->available = $this->quantity - $this->reserved;
        $this->save();
    }

    public function reserveQuantity(int $quantity): void
    {
        $this->reserved += $quantity;
        $this->available = $this->quantity - $this->reserved;
        $this->save();
    }

    public function releaseQuantity(int $quantity): void
    {
        $this->reserved = max(0, $this->reserved - $quantity);
        $this->available = $this->quantity - $this->reserved;
        $this->save();
    }
}
