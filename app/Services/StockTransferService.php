<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\StockMovement;
use Exception;

class StockTransferService
{
    /**
     * Transfer stock from one location to another
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

        // Check if sufficient stock is available
        if ($sourceInventory->available < $quantity) {
            throw new Exception(
                "Insufficient stock. Available: {$sourceInventory->available}, Requested: {$quantity}"
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
            storeId: $fromStoreId,
            warehouseId: $fromWarehouseId,
            userId: $userId,
            reason: $reason ?? 'Stock transfer',
            reference: 'TRF-'.uniqid()
        );

        StockMovement::recordMovement(
            tenantId: $tenantId,
            productId: $productId,
            type: 'transfer_in',
            quantity: $quantity,
            quantityBefore: $destQtyBefore,
            quantityAfter: $destinationInventory->quantity,
            inventoryId: $destinationInventory->id,
            storeId: $toStoreId,
            warehouseId: $toWarehouseId,
            userId: $userId,
            reason: $reason ?? 'Stock transfer',
            reference: 'TRF-'.uniqid()
        );

        return [
            'success' => true,
            'message' => "Transferred {$quantity} units successfully",
            'source_inventory' => $sourceInventory,
            'destination_inventory' => $destinationInventory,
        ];
    }

    /**
     * Get transferable inventory for a product
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
