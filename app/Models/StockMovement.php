<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'product_id', 'inventory_id', 'store_id', 'warehouse_id',
        'order_id', 'user_id', 'type', 'quantity', 'quantity_before',
        'quantity_after', 'reason', 'reference',
    ];

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
        ?string $reference = null
    ): self {
        return self::create([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'inventory_id' => $inventoryId,
            'store_id' => $storeId,
            'warehouse_id' => $warehouseId,
            'order_id' => $orderId,
            'user_id' => $userId,
            'type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'reason' => $reason,
            'reference' => $reference,
        ]);
    }
}
