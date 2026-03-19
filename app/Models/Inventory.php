<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    use HasFactory;

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
