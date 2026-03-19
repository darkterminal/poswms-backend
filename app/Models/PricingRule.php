<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'pricing_tier_id', 'product_id', 'category_id',
        'type', 'operation', 'value', 'min_quantity', 'max_quantity',
        'starts_at', 'ends_at', 'active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pricingTier(): BelongsTo
    {
        return $this->belongsTo(PricingTier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isActive(): bool
    {
        return $this->active && $this->isWithinDateRange();
    }

    public function isWithinDateRange(): bool
    {
        $now = now();
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }
        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    public function appliesToQuantity(int $quantity): bool
    {
        if ($quantity < $this->min_quantity) {
            return false;
        }
        if ($this->max_quantity && $quantity > $this->max_quantity) {
            return false;
        }

        return true;
    }

    public function calculatePrice(float $basePrice, int $quantity): float
    {
        if (! $this->isActive() || ! $this->appliesToQuantity($quantity)) {
            return $basePrice;
        }

        switch ($this->operation) {
            case 'add':
                return $this->type === 'percentage'
                    ? $basePrice + ($basePrice * $this->value / 100)
                    : $basePrice + $this->value;
            case 'subtract':
                return $this->type === 'percentage'
                    ? $basePrice - ($basePrice * $this->value / 100)
                    : $basePrice - $this->value;
            case 'replace':
                return $this->value;
            default:
                return $basePrice;
        }
    }
}
