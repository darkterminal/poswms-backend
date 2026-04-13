<?php

namespace App\Models;

use App\Models\Concerns\ScopedByTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class InventoryCount extends Model
{
    use HasFactory, ScopedByTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'warehouse_id',
        'store_id',
        'name',
        'description',
        'status',
        'started_by',
        'completed_by',
        'approved_by',
        'started_at',
        'completed_at',
        'approved_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Scope to filter by tenant.
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get active counts (draft or in_progress).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['draft', 'in_progress']);
    }

    /**
     * Scope to get completed counts.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereIn('status', ['completed', 'approved']);
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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryCountItem::class, 'count_id');
    }

    /**
     * Start the count.
     */
    public function start(?int $userId = null): void
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
            'started_by' => $userId,
        ]);
    }

    /**
     * Complete the count.
     */
    public function complete(?int $userId = null): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => $userId,
        ]);
    }

    /**
     * Approve the count and apply adjustments.
     */
    public function approve(?int $userId = null): void
    {
        DB::transaction(function () use ($userId) {
            $this->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $userId,
            ]);

            // Apply adjustments for variances with full audit trail
            $this->items()->with(['inventory', 'product'])->get()->each(function ($item) use ($userId) {
                if ($item->variance !== 0 && $item->inventory) {
                    $inventory = $item->inventory;
                    $quantityBefore = $inventory->quantity;
                    $quantityAfter = $quantityBefore + $item->variance;

                    // Record stock movement for audit trail
                    StockMovement::recordMovement(
                        tenantId: $this->tenant_id,
                        productId: $item->product_id,
                        type: 'adjustment',
                        quantity: $item->variance,
                        quantityBefore: $quantityBefore,
                        quantityAfter: $quantityAfter,
                        inventoryId: $inventory->id,
                        storeId: $inventory->store_id,
                        warehouseId: $inventory->warehouse_id,
                        userId: $userId,
                        reason: 'Inventory count adjustment',
                        reference: "Count: {$this->name} (ID: {$this->id})",
                        unitCost: $inventory->cost !== null ? (float) $inventory->cost : null
                    );

                    // Apply the adjustment with cost for FIFO layer creation
                    $inventory->updateQuantity($item->variance, $inventory->cost !== null ? (float) $inventory->cost : null);
                }
            });
        });
    }

    /**
     * Cancel the count.
     */
    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    /**
     * Get summary statistics.
     */
    public function getSummary(): array
    {
        $stats = $this->items()
            ->selectRaw('
                COUNT(*) as total_items,
                COUNT(counted_quantity) as counted_items,
                SUM(CASE WHEN variance != 0 THEN 1 ELSE 0 END) as items_with_variance,
                COALESCE(SUM(variance), 0) as total_variance
            ')
            ->first();

        $totalItems = (int) $stats->total_items;
        $countedItems = (int) $stats->counted_items;
        $itemsWithVariance = (int) $stats->items_with_variance;
        $totalVariance = (int) $stats->total_variance;

        return [
            'total_items' => $totalItems,
            'counted_items' => $countedItems,
            'pending_items' => $totalItems - $countedItems,
            'items_with_variance' => $itemsWithVariance,
            'total_variance' => $totalVariance,
            'accuracy_percentage' => $countedItems > 0
                ? round((($countedItems - $itemsWithVariance) / $countedItems) * 100, 2)
                : 0,
        ];
    }
}
