<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryLayer;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FifoService
{
    /**
     * Consume stock from inventory using FIFO method.
     * Returns detailed information about the consumption.
     *
     * @return array{consumed: int, remaining: int, total_cost: float, layers: array}
     *
     * @throws \RuntimeException if insufficient stock
     */
    public function consumeStock(
        Inventory $inventory,
        int $quantity,
        ?string $type = 'out',
        ?int $orderId = null,
        ?string $reason = null
    ): array {
        // Security: Use database locking to prevent race conditions (OWASP A08)
        return DB::transaction(function () use ($inventory, $quantity, $type, $orderId, $reason) {
            // Lock the inventory row for update to prevent concurrent modifications
            $lockedInventory = Inventory::where('id', $inventory->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedInventory) {
                Log::error('Failed to lock inventory for FIFO consumption', [
                    'inventory_id' => $inventory->id,
                    'product_id' => $inventory->product_id,
                    'tenant_id' => $inventory->tenant_id,
                ]);
                throw new \RuntimeException('Failed to lock inventory for update');
            }

            // Check available stock with locked data
            $availableStock = $lockedInventory->hasFifoLayers()
                ? $lockedInventory->getLayerAvailableQuantity()
                : $lockedInventory->available;

            // Security: Validate stock availability to prevent negative inventory (OWASP A08)
            if ($availableStock < $quantity) {
                Log::warning('Insufficient stock for FIFO consumption', [
                    'inventory_id' => $inventory->id,
                    'product_id' => $inventory->product_id,
                    'tenant_id' => $inventory->tenant_id,
                    'available' => $availableStock,
                    'requested' => $quantity,
                ]);
                throw new \RuntimeException(
                    "Insufficient stock. Available: {$availableStock}, Requested: {$quantity}"
                );
            }

            // Security: Validate quantity is positive (OWASP A04)
            if ($quantity <= 0) {
                Log::warning('Invalid quantity for FIFO consumption', [
                    'inventory_id' => $inventory->id,
                    'quantity' => $quantity,
                    'tenant_id' => $inventory->tenant_id,
                ]);
                throw new \RuntimeException('Quantity must be positive');
            }

            $result = $lockedInventory->consumeQuantity($quantity, $type, $orderId);

            // Record summary stock movement if not already recorded by layers
            if ($result['consumed'] > 0) {
                try {
                    StockMovement::recordMovement(
                        tenantId: $lockedInventory->tenant_id,
                        productId: $lockedInventory->product_id,
                        type: $type ?? 'out',
                        quantity: $result['consumed'],
                        quantityBefore: $lockedInventory->quantity + $result['consumed'],
                        quantityAfter: $lockedInventory->quantity,
                        inventoryId: $lockedInventory->id,
                        warehouseId: $lockedInventory->warehouse_id,
                        storeId: $lockedInventory->store_id,
                        orderId: $orderId,
                        reason: $reason ?? 'FIFO stock consumption'
                    );
                } catch (\Exception $e) {
                    // Log the error but don't fail the transaction
                    Log::error('Failed to record stock movement', [
                        'error' => $e->getMessage(),
                        'inventory_id' => $lockedInventory->id,
                        'product_id' => $lockedInventory->product_id,
                    ]);
                }
            }

            // Security: Log FIFO consumption for audit trail (OWASP A09)
            Log::info('FIFO stock consumed', [
                'inventory_id' => $lockedInventory->id,
                'product_id' => $lockedInventory->product_id,
                'tenant_id' => $lockedInventory->tenant_id,
                'quantity' => $result['consumed'],
                'total_cost' => $result['total_cost'],
                'order_id' => $orderId,
            ]);

            return $result;
        });
    }

    /**
     * Add stock to inventory creating a new FIFO layer.
     * Security: Validates input and uses database locking (OWASP A04, A08).
     */
    public function addStock(
        Inventory $inventory,
        int $quantity,
        float $unitCost,
        ?InventoryBatch $batch = null,
        ?string $reason = null
    ): InventoryLayer {
        return DB::transaction(function () use ($inventory, $quantity, $unitCost, $batch, $reason) {
            // Security: Validate quantity and cost (OWASP A04)
            if ($quantity <= 0) {
                Log::warning('Invalid quantity for FIFO stock addition', [
                    'inventory_id' => $inventory->id,
                    'quantity' => $quantity,
                    'tenant_id' => $inventory->tenant_id,
                ]);
                throw new \RuntimeException('Quantity must be positive');
            }

            if ($unitCost < 0) {
                Log::warning('Negative unit cost for FIFO stock addition', [
                    'inventory_id' => $inventory->id,
                    'unit_cost' => $unitCost,
                    'tenant_id' => $inventory->tenant_id,
                ]);
                throw new \RuntimeException('Unit cost cannot be negative');
            }

            $quantityBefore = $inventory->quantity;

            // Create or use batch with validation
            if ($batch === null) {
                $batch = $this->createBatch(
                    tenantId: $inventory->tenant_id,
                    productId: $inventory->product_id,
                    warehouseId: $inventory->warehouse_id,
                    quantity: $quantity,
                    unitCost: $unitCost
                );
            } else {
                // Security: Verify batch belongs to same tenant (OWASP A01)
                if ($batch->tenant_id !== $inventory->tenant_id) {
                    Log::error('Batch tenant mismatch', [
                        'batch_tenant_id' => $batch->tenant_id,
                        'inventory_tenant_id' => $inventory->tenant_id,
                        'batch_id' => $batch->id,
                        'inventory_id' => $inventory->id,
                    ]);
                    throw new \RuntimeException('Batch does not belong to the same tenant');
                }
                $batch->addQuantity($quantity);
            }

            // Create FIFO layer
            $layer = $inventory->createFifoLayer(
                quantity: $quantity,
                unitCost: $unitCost,
                batchId: $batch->id
            );

            // Update inventory
            $inventory->updateQuantity($quantity, $unitCost, $batch->id);

            // Record stock movement
            StockMovement::recordFifoMovement(
                tenantId: $inventory->tenant_id,
                productId: $inventory->product_id,
                type: 'in',
                quantity: $quantity,
                quantityBefore: $quantityBefore,
                quantityAfter: $inventory->quantity,
                layerId: $layer->id,
                unitCost: $unitCost,
                inventoryId: $inventory->id,
                warehouseId: $inventory->warehouse_id,
                reason: $reason ?? 'Stock received'
            );

            // Security: Log stock addition for audit trail (OWASP A09)
            Log::info('FIFO stock added', [
                'inventory_id' => $inventory->id,
                'product_id' => $inventory->product_id,
                'tenant_id' => $inventory->tenant_id,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'batch_id' => $batch->id,
                'layer_id' => $layer->id,
            ]);

            return $layer;
        });
    }

    /**
     * Transfer stock between locations using FIFO.
     * Consumes from source location using FIFO, creates new layer at destination.
     * Security: Validates tenant ownership and uses database locking (OWASP A01, A08).
     */
    public function transferStock(
        Inventory $sourceInventory,
        Inventory $destinationInventory,
        int $quantity,
        ?string $reason = null
    ): array {
        return DB::transaction(function () use ($sourceInventory, $destinationInventory, $quantity, $reason) {
            // Security: Verify both inventories belong to same tenant (OWASP A01)
            if ($sourceInventory->tenant_id !== $destinationInventory->tenant_id) {
                Log::error('Transfer between different tenants detected', [
                    'source_tenant_id' => $sourceInventory->tenant_id,
                    'destination_tenant_id' => $destinationInventory->tenant_id,
                    'source_inventory_id' => $sourceInventory->id,
                    'destination_inventory_id' => $destinationInventory->id,
                ]);
                throw new \RuntimeException('Cannot transfer between different tenants');
            }

            // Security: Validate quantity (OWASP A04)
            if ($quantity <= 0) {
                Log::warning('Invalid quantity for stock transfer', [
                    'quantity' => $quantity,
                    'tenant_id' => $sourceInventory->tenant_id,
                ]);
                throw new \RuntimeException('Quantity must be positive');
            }

            // Consume from source using FIFO (includes locking)
            $consumptionResult = $this->consumeStock(
                inventory: $sourceInventory,
                quantity: $quantity,
                type: 'transfer',
                reason: $reason ?? 'Stock transfer out'
            );

            if ($consumptionResult['consumed'] === 0) {
                Log::warning('Stock transfer with zero consumption', [
                    'source_inventory_id' => $sourceInventory->id,
                    'destination_inventory_id' => $destinationInventory->id,
                    'tenant_id' => $sourceInventory->tenant_id,
                ]);
                throw new \RuntimeException('Insufficient stock for transfer');
            }

            // Calculate weighted average cost for transfer
            $avgCost = $consumptionResult['total_cost'] / $consumptionResult['consumed'];

            // Add to destination at same cost
            $destinationLayer = $this->addStock(
                inventory: $destinationInventory,
                quantity: $consumptionResult['consumed'],
                unitCost: $avgCost,
                reason: $reason ?? 'Stock transfer in'
            );

            // Security: Log transfer for audit trail (OWASP A09)
            Log::info('Stock transferred', [
                'source_inventory_id' => $sourceInventory->id,
                'destination_inventory_id' => $destinationInventory->id,
                'tenant_id' => $sourceInventory->tenant_id,
                'quantity' => $consumptionResult['consumed'],
                'total_cost' => $consumptionResult['total_cost'],
            ]);

            return [
                'consumed' => $consumptionResult['consumed'],
                'transferred' => $consumptionResult['consumed'],
                'total_cost' => $consumptionResult['total_cost'],
                'average_cost' => $avgCost,
                'destination_layer_id' => $destinationLayer->id,
                'source_layers' => $consumptionResult['layers'],
            ];
        });
    }

    /**
     * Create a new inventory batch.
     */
    public function createBatch(
        int $tenantId,
        int $productId,
        int $warehouseId,
        int $quantity,
        float $unitCost,
        ?string $batchNumber = null,
        ?string $lotNumber = null,
        ?\DateTimeInterface $receivedDate = null,
        ?\DateTimeInterface $expiryDate = null,
        ?int $supplierId = null,
        ?string $notes = null
    ): InventoryBatch {
        return InventoryBatch::create([
            'tenant_id' => $tenantId,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'supplier_id' => $supplierId,
            'batch_number' => $batchNumber !== null ? strip_tags(trim($batchNumber)) : $this->generateBatchNumber($tenantId),
            'lot_number' => $lotNumber !== null ? strip_tags(trim($lotNumber)) : null,
            'received_date' => $receivedDate ?? now(),
            'expiry_date' => $expiryDate,
            'unit_cost' => $unitCost,
            'initial_quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'status' => 'active',
            'notes' => $notes !== null ? strip_tags(trim($notes)) : null,
        ]);
    }

    /**
     * Get FIFO layers for a product in a warehouse.
     */
    public function getLayersForProduct(
        int $productId,
        int $warehouseId,
        ?int $tenantId = null
    ): Collection {
        $query = InventoryLayer::forProduct($productId)
            ->forWarehouse($warehouseId)
            ->fifoLayers()
            ->fifoOrder();

        if ($tenantId !== null) {
            $query->forTenant($tenantId);
        }

        return $query->with('batch', 'inventory')->get();
    }

    /**
     * Get available stock for a product using FIFO layers.
     */
    public function getAvailableStock(
        int $productId,
        int $warehouseId,
        ?int $tenantId = null
    ): array {
        $layers = $this->getLayersForProduct($productId, $warehouseId, $tenantId);

        return [
            'total_quantity' => $layers->sum('quantity'),
            'total_available' => $layers->sum('available'),
            'total_reserved' => $layers->sum('reserved'),
            'total_value' => $layers->sum('total_cost'),
            'weighted_average_cost' => $layers->sum('total_cost') / max(1, $layers->sum('quantity')),
            'layer_count' => $layers->count(),
            'oldest_layer_date' => $layers->first()?->batch?->received_date,
            'newest_layer_date' => $layers->last()?->batch?->received_date,
        ];
    }

    /**
     * Check if there's sufficient stock available using FIFO layers.
     */
    public function hasSufficientStock(
        int $productId,
        int $warehouseId,
        int $quantity,
        ?int $tenantId = null
    ): bool {
        $layers = $this->getLayersForProduct($productId, $warehouseId, $tenantId);
        $totalAvailable = $layers->sum('available');

        return $totalAvailable >= $quantity;
    }

    /**
     * Get expiring batches that need attention.
     */
    public function getExpiringBatches(
        int $tenantId,
        int $days = 30
    ): array {
        return InventoryBatch::getExpiringSummary($tenantId, $days);
    }

    /**
     * Mark batch as expired.
     */
    public function expireBatch(InventoryBatch $batch, ?string $reason = null): void
    {
        DB::transaction(function () use ($batch, $reason) {
            $remainingQty = $batch->remaining_quantity;

            $batch->status = 'expired';
            $batch->remaining_quantity = 0;
            $batch->save();

            // Delete or zero out associated layers
            $batch->layers()->update([
                'quantity' => 0,
                'available' => 0,
                'reserved' => 0,
            ]);

            // Record stock movement for expiry
            StockMovement::recordMovement(
                tenantId: $batch->tenant_id,
                productId: $batch->product_id,
                type: 'adjustment',
                quantity: $remainingQty,
                quantityBefore: $remainingQty,
                quantityAfter: 0,
                warehouseId: $batch->warehouse_id,
                reason: $reason ?? 'Batch expired'
            );
        });
    }

    /**
     * Calculate Cost of Goods Sold (COGS) for a period.
     */
    public function calculateCogs(
        int $tenantId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?int $productId = null
    ): array {
        $query = StockMovement::forTenant($tenantId)
            ->byType('out')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($productId !== null) {
            $query->where('product_id', $productId);
        }

        $movements = $query->get();

        return [
            'total_quantity' => $movements->sum('quantity'),
            'total_cost' => $movements->sum('total_cost') ?? 0.0,
            'movement_count' => $movements->count(),
            'average_unit_cost' => $movements->sum('quantity') > 0
                ? $movements->sum('total_cost') / $movements->sum('quantity')
                : 0.0,
        ];
    }

    /**
     * Get FIFO valuation for inventory.
     */
    public function getInventoryValuation(
        int $tenantId,
        ?int $warehouseId = null,
        int $limit = 100,
        int $offset = 0,
        ?\DateTimeInterface $asOfDate = null
    ): array {
        $query = InventoryLayer::forTenant($tenantId)
            ->fifoLayers()
            ->withStock();

        if ($warehouseId !== null) {
            $query->forWarehouse($warehouseId);
        }

        if ($asOfDate !== null) {
            // Reconstruct layer state at a point in time by filtering layers
            // that existed and were not fully consumed/adjusted as of the date
            $query->where('created_at', '<=', $asOfDate)
                ->where(function ($q) use ($asOfDate) {
                    $q->whereNull('updated_at')
                        ->orWhere('updated_at', '>', $asOfDate);
                });
        }

        // Calculate totals before pagination
        $totalQuantity = (clone $query)->sum('quantity');
        $totalAvailable = (clone $query)->sum('available');
        $totalValue = (clone $query)->sum('total_cost');
        $totalCount = (clone $query)->count();

        $layers = $query->with(['product', 'warehouse', 'batch'])
            ->offset($offset)
            ->limit($limit)
            ->get();

        return [
            'total_quantity' => $totalQuantity,
            'total_available' => $totalAvailable,
            'total_value' => $totalValue,
            'layer_count' => $layers->count(),
            'total_count' => $totalCount,
            'by_product' => $layers->groupBy('product_id')->map(fn($group) => [
                'quantity' => $group->sum('quantity'),
                'available' => $group->sum('available'),
                'value' => $group->sum('total_cost'),
                'average_cost' => $group->sum('quantity') > 0
                    ? $group->sum('total_cost') / $group->sum('quantity')
                    : 0.0,
            ]),
            'by_warehouse' => $layers->groupBy('warehouse_id')->map(fn($group) => [
                'quantity' => $group->sum('quantity'),
                'value' => $group->sum('total_cost'),
            ]),
        ];
    }

    /**
     * Generate unique batch number.
     */
    protected function generateBatchNumber(int $tenantId): string
    {
        $prefix = 'BATCH';
        $date = now()->format('Ymd');
        $random = strtoupper(bin2hex(random_bytes(3)));

        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Clean up consumed and expired layers.
     */
    public function cleanupOldLayers(?int $olderThanDays = 90): int
    {
        $cutoffDate = now()->subDays($olderThanDays ?? 90);

        // Delete layers with zero quantity older than cutoff
        $deleted = InventoryLayer::where('quantity', 0)
            ->where('updated_at', '<', $cutoffDate)
            ->delete();

        return $deleted;
    }

    /**
     * Reconcile inventory quantities with layer totals.
     */
    public function reconcileInventory(Inventory $inventory): array
    {
        $layerQuantity = $inventory->layers()->fifoLayers()->sum('quantity');
        $layerReserved = $inventory->layers()->fifoLayers()->sum('reserved');
        $layerAvailable = $layerQuantity - $layerReserved;

        $discrepancy = [
            'quantity_diff' => $inventory->quantity - $layerQuantity,
            'reserved_diff' => $inventory->reserved - $layerReserved,
            'available_diff' => $inventory->available - $layerAvailable,
        ];

        // If there's a discrepancy, sync with layers
        if (array_sum(array_map('abs', $discrepancy)) > 0) {
            $inventory->quantity = $layerQuantity;
            $inventory->reserved = $layerReserved;
            $inventory->available = $layerAvailable;
            $inventory->save();
        }

        return [
            'reconciled' => array_sum(array_map('abs', $discrepancy)) > 0,
            'before' => [
                'quantity' => $inventory->getOriginal('quantity'),
                'reserved' => $inventory->getOriginal('reserved'),
                'available' => $inventory->getOriginal('available'),
            ],
            'after' => [
                'quantity' => $inventory->quantity,
                'reserved' => $inventory->reserved,
                'available' => $inventory->available,
            ],
        ];
    }
}
