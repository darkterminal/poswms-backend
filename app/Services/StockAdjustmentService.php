<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockAdjustmentService
{
    /**
     * Adjust inventory stock.
     *
     * @param  int  $tenantId  The tenant ID
     * @param  int  $inventoryId  The inventory record ID
     * @param  int  $quantity  The quantity to adjust
     * @param  string  $adjustmentType  The type of adjustment (set, add, subtract)
     * @param  float|null  $unitCost  Optional unit cost (for creating FIFO layers when adding)
     * @param  string  $reason  Reason for the adjustment
     * @param  string|null  $batchNumber  Optional batch number
     * @param  string|null  $notes  Optional notes
     * @param  int|null  $userId  The user performing the adjustment
     * @return array{inventory: Inventory, movement: StockMovement, quantity_before: int, quantity_after: int, adjustment: int}
     *
     * @throws \RuntimeException
     */
    public function adjustStock(
        int $tenantId,
        int $inventoryId,
        int $quantity,
        string $adjustmentType,
        string $reason,
        ?float $unitCost = null,
        ?string $batchNumber = null,
        ?string $notes = null,
        ?int $userId = null
    ): array {
        return DB::transaction(function () use (
            $tenantId,
            $inventoryId,
            $quantity,
            $adjustmentType,
            $reason,
            $unitCost,
            $batchNumber,
            $userId
        ) {
            // Find and lock the inventory record
            $inventory = Inventory::where('tenant_id', $tenantId)
                ->where('id', $inventoryId)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                Log::error('Inventory not found for adjustment', [
                    'inventory_id' => $inventoryId,
                    'tenant_id' => $tenantId,
                ]);
                throw new \RuntimeException('Inventory record not found');
            }

            $quantityBefore = $inventory->quantity;
            $adjustment = 0;

            // Calculate new quantity based on adjustment type
            match ($adjustmentType) {
                'set' => $this->handleSetAdjustment($inventory, $quantity),
                'add' => $this->handleAddAdjustment($inventory, $quantity, $unitCost, $batchNumber),
                'subtract' => $this->handleSubtractAdjustment($inventory, $quantity),
                default => throw new \RuntimeException('Invalid adjustment type'),
            };

            // Calculate the actual adjustment amount
            $adjustment = $inventory->quantity - $quantityBefore;

            // Record stock movement
            $movement = StockMovement::recordMovement(
                tenantId: $tenantId,
                productId: $inventory->product_id,
                type: 'adjustment',
                quantity: abs($adjustment),
                quantityBefore: $quantityBefore,
                quantityAfter: $inventory->quantity,
                inventoryId: $inventory->id,
                warehouseId: $inventory->warehouse_id,
                storeId: $inventory->store_id,
                userId: $userId,
                reason: $reason,
                reference: $this->generateReference($adjustmentType, $batchNumber),
                unitCost: $unitCost
            );

            // Log the adjustment
            Log::info('Stock adjusted', [
                'inventory_id' => $inventory->id,
                'product_id' => $inventory->product_id,
                'tenant_id' => $tenantId,
                'adjustment_type' => $adjustmentType,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $inventory->quantity,
                'adjustment' => $adjustment,
                'reason' => $reason,
                'user_id' => $userId,
            ]);

            return [
                'inventory' => $inventory,
                'movement' => $movement,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $inventory->quantity,
                'adjustment' => $adjustment,
            ];
        });
    }

    /**
     * Handle "set" adjustment - set inventory to exact quantity.
     */
    protected function handleSetAdjustment(Inventory $inventory, int $targetQuantity): void
    {
        $adjustment = $targetQuantity - $inventory->quantity;

        // If increasing stock with unit cost, create FIFO layer
        if ($adjustment > 0) {
            $inventory->updateQuantity($adjustment);
        } elseif ($adjustment < 0) {
            // Decreasing stock
            $inventory->quantity = $targetQuantity;
            $inventory->available = $inventory->quantity - $inventory->reserved;
            $inventory->save();
        }
    }

    /**
     * Handle "add" adjustment - add quantity to inventory.
     */
    protected function handleAddAdjustment(
        Inventory $inventory,
        int $quantity,
        ?float $unitCost = null,
        ?string $batchNumber = null
    ): void {
        // If unit cost provided, create or find batch and let updateQuantity handle FIFO layer creation
        if ($unitCost !== null) {
            $batch = null;

            // Create or find batch
            if ($batchNumber) {
                $batch = InventoryBatch::where('tenant_id', $inventory->tenant_id)
                    ->where('batch_number', $batchNumber)
                    ->first();
            }

            if (! $batch) {
                $batch = InventoryBatch::create([
                    'tenant_id' => $inventory->tenant_id,
                    'product_id' => $inventory->product_id,
                    'warehouse_id' => $inventory->warehouse_id,
                    'batch_number' => $batchNumber ?? $this->generateBatchNumber($inventory->tenant_id),
                    'received_date' => now(),
                    'unit_cost' => $unitCost,
                    'initial_quantity' => $quantity,
                    'remaining_quantity' => $quantity,
                    'status' => 'active',
                ]);
            }

            // updateQuantity() will create the FIFO layer automatically when unitCost is provided
            $inventory->updateQuantity($quantity, $unitCost, $batch->id);
        } else {
            // No unit cost, just add quantity without FIFO layer
            $inventory->updateQuantity($quantity);
        }
    }

    /**
     * Handle "subtract" adjustment - remove quantity from inventory.
     */
    protected function handleSubtractAdjustment(Inventory $inventory, int $quantity): void
    {
        // Validate sufficient stock
        $availableStock = $inventory->hasFifoLayers()
            ? $inventory->getLayerAvailableQuantity()
            : $inventory->available;

        if ($availableStock < $quantity) {
            throw new \RuntimeException(
                "Insufficient stock. Available: {$availableStock}, Requested: {$quantity}"
            );
        }

        // If FIFO layers exist, consume from them
        if ($inventory->hasFifoLayers()) {
            $inventory->consumeQuantity($quantity, 'adjustment');
        } else {
            // Legacy deduction
            $inventory->updateQuantity(-$quantity);
        }
    }

    /**
     * Generate reference number for adjustment.
     */
    protected function generateReference(string $adjustmentType, ?string $batchNumber = null): string
    {
        $prefix = match ($adjustmentType) {
            'set' => 'ADJ-SET',
            'add' => 'ADJ-ADD',
            'subtract' => 'ADJ-SUB',
            default => 'ADJ',
        };

        $batch = $batchNumber ? '-' . strtoupper($batchNumber) : '';
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(substr(uniqid(), -4));

        return "{$prefix}{$batch}-{$timestamp}-{$random}";
    }

    /**
     * Generate unique batch number.
     */
    protected function generateBatchNumber(int $tenantId): string
    {
        $prefix = 'ADJ-BATCH';
        $date = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -6));

        return "{$prefix}-{$date}-{$random}";
    }
}
