<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'name',
        'code',
        'capacity',
        'description',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * Scope to get active zones only.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Scope to filter by warehouse.
     */
    public function scopeForWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Scope to search by name or code.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%");
        });
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get all zones for a warehouse with warehouse details.
     */
    public static function getWithWarehouse(int $warehouseId): \Illuminate\Support\Collection
    {
        return self::forWarehouse($warehouseId)
            ->with('warehouse:id,name,code')
            ->get();
    }

    /**
     * Get zone usage statistics.
     */
    public function getUsageStats(): array
    {
        $inventoryCount = Inventory::where('warehouse_id', $this->warehouse_id)
            ->where('location', $this->code)
            ->count();

        return [
            'zone_id' => $this->id,
            'zone_code' => $this->code,
            'zone_name' => $this->name,
            'capacity' => $this->capacity,
            'inventory_count' => $inventoryCount,
            'utilization_percentage' => $this->capacity > 0
                ? round(($inventoryCount / $this->capacity) * 100, 2)
                : null,
        ];
    }

    /**
     * Check if zone is at capacity.
     */
    public function isAtCapacity(): bool
    {
        if ($this->capacity === null) {
            return false;
        }

        $inventoryCount = Inventory::where('warehouse_id', $this->warehouse_id)
            ->where('location', $this->code)
            ->count();

        return $inventoryCount >= $this->capacity;
    }
}
