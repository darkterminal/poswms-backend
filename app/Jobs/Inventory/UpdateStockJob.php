<?php

namespace App\Jobs\Inventory;

use App\Models\Inventory;
use App\Models\StockMovement;
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
        public ?string $reason = null
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
            reference: 'JOB-'.uniqid()
        );
    }
}
