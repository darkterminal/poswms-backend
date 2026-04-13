<?php

namespace App\Models;

use App\Models\Concerns\HasMoney;
use App\Models\Concerns\ScopedByTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasMoney, ScopedByTenant, SoftDeletes;

    protected function moneyFields(): array
    {
        return ['price', 'cost'];
    }

    protected $fillable = [
        'tenant_id', 'category_id', 'name', 'sku', 'barcode', 'description',
        'price', 'cost', 'tax_rate', 'unit', 'min_stock', 'max_stock',
        'image', 'images', 'attributes', 'track_inventory', 'active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'images' => 'array',
            'attributes' => 'array',
            'track_inventory' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(PricingRule::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function priceLevels(): HasMany
    {
        return $this->hasMany(ProductPriceLevel::class)->orderBy('level_order');
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getTotalStock(): int
    {
        return $this->inventories()->sum('quantity');
    }

    public function getAvailableStock(): int
    {
        return $this->inventories()->sum('available');
    }

    public function isLowStock(): bool
    {
        return $this->getAvailableStock() <= $this->min_stock;
    }

    /**
     * Check if product has multiple price levels.
     */
    public function hasPriceLevels(): bool
    {
        return $this->priceLevels()->count() > 0;
    }

    /**
     * Get price for a specific level name (e.g., 'piece', 'pack', 'carton').
     * Returns the base price if level not found.
     */
    public function getPriceForLevel(string $levelName): ?float
    {
        $priceLevel = $this->priceLevels()
            ->where('level_name', $levelName)
            ->where('active', true)
            ->first();

        return $priceLevel ? (float) $priceLevel->price : null;
    }

    /**
     * Get price for a specific level order (1 = base, 2 = pack, 3 = carton, etc.).
     * Returns the base price if level not found.
     */
    public function getPriceForOrder(int $levelOrder): ?float
    {
        $priceLevel = $this->priceLevels()
            ->where('level_order', $levelOrder)
            ->where('active', true)
            ->first();

        return $priceLevel ? (float) $priceLevel->price : null;
    }

    /**
     * Get all active price levels with their details.
     */
    public function getAllPriceLevels(): array
    {
        return $this->priceLevels()
            ->where('active', true)
            ->get()
            ->map(function (ProductPriceLevel $level) {
                return [
                    'level_name' => $level->level_name,
                    'level_order' => $level->level_order,
                    'unit_size' => $level->unit_size,
                    'price' => (float) $level->price,
                    'cost' => (float) $level->cost,
                    'barcode' => $level->barcode,
                    'price_per_base_unit' => $level->getPricePerBaseUnit(),
                ];
            })
            ->toArray();
    }

    /**
     * Get price for a given quantity, automatically selecting the best price level.
     * Returns the most cost-effective combination of price levels.
     */
    public function calculatePriceForQuantity(int $quantity): float
    {
        if (! $this->hasPriceLevels()) {
            return (float) $this->price * $quantity;
        }

        $priceLevels = $this->priceLevels()
            ->where('active', true)
            ->get()
            ->sortByDesc('level_order')
            ->values();

        if ($priceLevels->isEmpty()) {
            return (float) $this->price * $quantity;
        }

        $totalPrice = 0;
        $remaining = $quantity;

        foreach ($priceLevels as $level) {
            if ($remaining <= 0) {
                break;
            }

            $levelSize = $level->unit_size;
            $levelCount = intdiv($remaining, $levelSize);

            if ($levelCount > 0) {
                $totalPrice += $levelCount * (float) $level->price;
                $remaining -= $levelCount * $levelSize;
            }
        }

        // Handle remaining units at base price
        if ($remaining > 0) {
            $baseLevel = $priceLevels->firstWhere('unit_size', 1);
            if ($baseLevel) {
                $totalPrice += $remaining * (float) $baseLevel->price;
            } else {
                // Fallback: use the smallest available unit
                $smallestLevel = $priceLevels
                    ->sortBy('unit_size')
                    ->firstWhere('unit_size', '<=', $remaining);

                if ($smallestLevel) {
                    $totalPrice += $remaining * (float) $smallestLevel->price;
                } else {
                    $totalPrice += $remaining * (float) $this->price;
                }
            }
        }

        return $totalPrice;
    }
}
