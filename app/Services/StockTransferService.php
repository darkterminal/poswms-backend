<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\StockMovement;
use Exception;

class StockTransferService
{
    public function __construct(
        private readonly FifoService $fifoService
    ) {}

    /**
     * Transfer stock from one location to another using FIFO.
     *
     * @throws Exception
     */
    public function transfer(
        int $tenantId,
        int $productId,
        int $quantity,
        ?int $fromWarehouseId = null,
        ?int $fromStoreId = null,
        ?int $toWarehouseId = null,
        ?int $toStoreId = null,
        ?int $userId = null,
        ?string $reason = null
    ): array {
        // Validate source location
        if (! $fromWarehouseId && ! $fromStoreId) {
            throw new Exception('Source location (warehouse or store) is required');
        }

        // Validate destination location
        if (! $toWarehouseId && ! $toStoreId) {
            throw new Exception('Destination location (warehouse or store) is required');
        }

        // Find source inventory
        $sourceInventory = Inventory::where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where(function ($query) use ($fromWarehouseId, $fromStoreId) {
                if ($fromWarehouseId) {
                    $query->where('warehouse_id', $fromWarehouseId);
                }
                if ($fromStoreId) {
                    $query->where('store_id', $fromStoreId);
                }
            })
            ->first();

        if (! $sourceInventory) {
            throw new Exception('Source inventory not found');
        }

        // Check if sufficient stock is available (FIFO-aware)
        $availableStock = $sourceInventory->hasFifoLayers()
            ? $sourceInventory->getLayerAvailableQuantity()
            : $sourceInventory->available;

        if ($availableStock < $quantity) {
            throw new Exception(
                "Insufficient stock. Available: {$availableStock}, Requested: {$quantity}"
            );
        }

        // Find or create destination inventory
        $destinationInventory = Inventory::where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where(function ($query) use ($toWarehouseId, $toStoreId) {
                if ($toWarehouseId) {
                    $query->where('warehouse_id', $toWarehouseId);
                }
                if ($toStoreId) {
                    $query->where('store_id', $toStoreId);
                }
            })
            ->first();

        if (! $destinationInventory) {
            $destinationInventory = Inventory::create([
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'warehouse_id' => $toWarehouseId,
                'store_id' => $toStoreId,
                'quantity' => 0,
                'reserved' => 0,
                'available' => 0,
                'cost' => $sourceInventory->cost,
            ]);
        }

        // Use FIFO service for transfer if source has FIFO layers
        if ($sourceInventory->hasFifoLayers()) {
            return $this->fifoTransfer(
                sourceInventory: $sourceInventory,
                destinationInventory: $destinationInventory,
                quantity: $quantity,
                userId: $userId,
                reason: $reason
            );
        }

        // Fallback to legacy transfer method
        return $this->legacyTransfer(
            tenantId: $tenantId,
            productId: $productId,
            quantity: $quantity,
            sourceInventory: $sourceInventory,
            destinationInventory: $destinationInventory,
            userId: $userId,
            reason: $reason
        );
    }

    /**
     * Perform FIFO-aware transfer using FifoService.
     */
    private function fifoTransfer(
        Inventory $sourceInventory,
        Inventory $destinationInventory,
        int $quantity,
        ?int $userId = null,
        ?string $reason = null
    ): array {
        $reference = 'TRF-' . uniqid();

        // Use FifoService for transfer
        $transferResult = $this->fifoService->transferStock(
            sourceInventory: $sourceInventory,
            destinationInventory: $destinationInventory,
            quantity: $quantity,
            reason: $reason ?? 'Stock transfer'
        );

        // Record additional stock movements with reference
        StockMovement::where('tenant_id', $sourceInventory->tenant_id)
            ->where('reference', $reference)
            ->update(['reference' => $reference]);

        return [
            'success' => true,
            'message' => "Transferred {$quantity} units successfully using FIFO",
            'source_inventory' => $sourceInventory->fresh(),
            'destination_inventory' => $destinationInventory->fresh(),
            'fifo_details' => $transferResult,
            'reference' => $reference,
        ];
    }

    /**
     * Perform legacy transfer (backward compatible).
     */
    private function legacyTransfer(
        int $tenantId,
        int $productId,
        int $quantity,
        Inventory $sourceInventory,
        Inventory $destinationInventory,
        ?int $userId = null,
        ?string $reason = null
    ): array {
        $reference = 'TRF-' . uniqid();

        // Record quantity before
        $sourceQtyBefore = $sourceInventory->quantity;
        $destQtyBefore = $destinationInventory->quantity;

        // Deduct from source
        $sourceInventory->quantity -= $quantity;
        $sourceInventory->available = $sourceInventory->quantity - $sourceInventory->reserved;
        $sourceInventory->save();

        // Add to destination
        $destinationInventory->quantity += $quantity;
        $destinationInventory->available = $destinationInventory->quantity - $destinationInventory->reserved;
        $destinationInventory->save();

        // Record stock movements
        StockMovement::recordMovement(
            tenantId: $tenantId,
            productId: $productId,
            type: 'transfer_out',
            quantity: $quantity,
            quantityBefore: $sourceQtyBefore,
            quantityAfter: $sourceInventory->quantity,
            inventoryId: $sourceInventory->id,
            storeId: $sourceInventory->store_id,
            warehouseId: $sourceInventory->warehouse_id,
            userId: $userId,
            reason: $reason ?? 'Stock transfer',
            reference: $reference
        );

        StockMovement::recordMovement(
            tenantId: $tenantId,
            productId: $productId,
            type: 'transfer_in',
            quantity: $quantity,
            quantityBefore: $destQtyBefore,
            quantityAfter: $destinationInventory->quantity,
            inventoryId: $destinationInventory->id,
            storeId: $destinationInventory->store_id,
            warehouseId: $destinationInventory->warehouse_id,
            userId: $userId,
            reason: $reason ?? 'Stock transfer',
            reference: $reference
        );

        return [
            'success' => true,
            'message' => "Transferred {$quantity} units successfully",
            'source_inventory' => $sourceInventory,
            'destination_inventory' => $destinationInventory,
            'reference' => $reference,
        ];
    }

    /**
     * Get transferable inventory for a product.
     */
    public function getTransferableInventory(int $tenantId, int $productId, ?int $locationId = null, string $locationType = 'warehouse'): array
    {
        $query = Inventory::where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('available', '>', 0);

        if ($locationType === 'warehouse' && $locationId) {
            $query->where('warehouse_id', $locationId);
        } elseif ($locationType === 'store' && $locationId) {
            $query->where('store_id', $locationId);
        }

        return $query->with(['product', 'warehouse', 'store'])->get()->toArray();
    }
}
