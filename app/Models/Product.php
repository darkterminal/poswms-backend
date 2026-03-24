<?php

namespace App\Models;

use App\Models\Concerns\ScopedByTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, ScopedByTenant, SoftDeletes;

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
}
