<?php

namespace App\Jobs\Inventory;

use App\Models\Inventory;
use App\Models\StockMovement;
use App\Services\FifoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateStockJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $tenantId,
        public int $inventoryId,
        public int $quantityAdjustment,
        public string $type = 'adjustment',
        public ?int $userId = null,
        public ?string $reason = null,
        public ?float $unitCost = null,
        public ?int $batchId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $inventory = Inventory::where('tenant_id', $this->tenantId)
            ->find($this->inventoryId);

        if (! $inventory) {
            return;
        }

        // If adding stock with cost, use FIFO service
        if ($this->quantityAdjustment > 0 && $this->unitCost !== null) {
            $this->handleFifoIn($inventory);
        } elseif ($this->quantityAdjustment < 0) {
            $this->handleFifoOut($inventory);
        } else {
            $this->handleLegacy($inventory);
        }
    }

    /**
     * Handle FIFO stock increase.
     */
    private function handleFifoIn(Inventory $inventory): void
    {
        $fifoService = app(FifoService::class);

        $batch = null;
        if ($this->batchId !== null) {
            $batch = \App\Models\InventoryBatch::find($this->batchId);
        }

        $layer = $fifoService->addStock(
            inventory: $inventory,
            quantity: $this->quantityAdjustment,
            unitCost: $this->unitCost,
            batch: $batch,
            reason: $this->reason
        );

        // Record stock movement
        StockMovement::recordFifoMovement(
            tenantId: $this->tenantId,
            productId: $inventory->product_id,
            type: $this->type,
            quantity: $this->quantityAdjustment,
            quantityBefore: $inventory->quantity - $this->quantityAdjustment,
            quantityAfter: $inventory->quantity,
            layerId: $layer->id,
            unitCost: $this->unitCost,
            inventoryId: $inventory->id,
            warehouseId: $inventory->warehouse_id,
            storeId: $inventory->store_id,
            userId: $this->userId,
            reason: $this->reason,
            reference: 'JOB-' . uniqid()
        );
    }

    /**
     * Handle FIFO stock decrease.
     */
    private function handleFifoOut(Inventory $inventory): void
    {
        $fifoService = app(FifoService::class);

        $quantityToConsume = abs($this->quantityAdjustment);
        $quantityBefore = $inventory->quantity;

        $result = $fifoService->consumeStock(
            inventory: $inventory,
            quantity: $quantityToConsume,
            type: $this->type,
            reason: $this->reason
        );

        // Record summary stock movement
        if ($result['consumed'] > 0) {
            StockMovement::recordMovement(
                tenantId: $this->tenantId,
                productId: $inventory->product_id,
                type: $this->type,
                quantity: $result['consumed'],
                quantityBefore: $quantityBefore,
                quantityAfter: $inventory->quantity,
                inventoryId: $inventory->id,
                warehouseId: $inventory->warehouse_id,
                storeId: $inventory->store_id,
                userId: $this->userId,
                reason: $this->reason,
                reference: 'JOB-' . uniqid()
            );
        }
    }

    /**
     * Handle legacy stock update (backward compatible).
     */
    private function handleLegacy(Inventory $inventory): void
    {
        $quantityBefore = $inventory->quantity;

        // Update quantity
        $inventory->updateQuantity($this->quantityAdjustment);

        $quantityAfter = $inventory->quantity;

        // Record stock movement
        StockMovement::recordMovement(
            tenantId: $this->tenantId,
            productId: $inventory->product_id,
            type: $this->type,
            quantity: abs($this->quantityAdjustment),
            quantityBefore: $quantityBefore,
            quantityAfter: $quantityAfter,
            inventoryId: $inventory->id,
            storeId: $inventory->store_id,
            warehouseId: $inventory->warehouse_id,
            userId: $this->userId,
            reason: $this->reason,
            reference: 'JOB-' . uniqid()
        );
    }
}
