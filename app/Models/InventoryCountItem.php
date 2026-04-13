<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCountItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'count_id',
        'product_id',
        'inventory_id',
        'expected_quantity',
        'counted_quantity',
        'variance',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:2',
            'counted_quantity' => 'decimal:2',
            'variance' => 'decimal:2',
        ];
    }

    /**
     * Boot the model - auto-calculate variance.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $item) {
            if ($item->counted_quantity !== null) {
                $item->variance = $item->counted_quantity - $item->expected_quantity;
            }
        });
    }

    public function count(): BelongsTo
    {
        return $this->belongsTo(InventoryCount::class, 'count_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    /**
     * Record the counted quantity.
     */
    public function recordCount(float|int $countedQuantity, ?string $notes = null): void
    {
        $this->update([
            'counted_quantity' => $countedQuantity,
            'notes' => $notes ?? $this->notes,
        ]);
    }

    /**
     * Check if item has been counted.
     */
    public function isCounted(): bool
    {
        return $this->counted_quantity !== null;
    }

    /**
     * Get absolute variance.
     */
    public function absoluteVariance(): float
    {
        return abs((float) $this->variance);
    }

    /**
     * Check if item has variance.
     */
    public function hasVariance(): bool
    {
        return $this->variance !== 0;
    }
}
