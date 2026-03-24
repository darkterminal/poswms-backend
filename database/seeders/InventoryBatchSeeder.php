<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class InventoryBatchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouses = Warehouse::all();
        $products = Product::whereHas('inventories')->get();

        if ($warehouses->isEmpty() || $products->isEmpty()) {
            $this->command->info('No warehouses or products with inventory found. Skipping batch seeding.');

            return;
        }

        $batchesCreated = 0;

        $products->each(function ($product) use (&$batchesCreated) {
            $inventories = $product->inventories()->with('warehouse')->get();

            foreach ($inventories as $inventory) {
                // Create 1-3 batches per inventory item
                $batchCount = random_int(1, 3);

                for ($i = 0; $i < $batchCount; $i++) {
                    $receivedDate = now()->subDays(random_int(1, 90));
                    $expiryDate = random_int(0, 10) > 3 ? now()->addDays(random_int(30, 365)) : null;
                    $quantity = random_int(20, 200);
                    $unitCost = $inventory->cost ?? random_float(5, 50);

                    $batch = InventoryBatch::create([
                        'tenant_id' => $inventory->tenant_id,
                        'product_id' => $product->id,
                        'warehouse_id' => $inventory->warehouse_id,
                        'batch_number' => 'BATCH-' . strtoupper(uniqid('' . $product->id . '-')),
                        'lot_number' => 'LOT-' . strtoupper(fake()->unique()->lexify('????')),
                        'received_date' => $receivedDate,
                        'expiry_date' => $expiryDate,
                        'unit_cost' => $unitCost,
                        'initial_quantity' => $quantity,
                        'remaining_quantity' => $quantity,
                        'status' => 'active',
                        'notes' => 'Seeded batch for testing FIFO',
                    ]);

                    $batchesCreated++;
                }
            }
        });

        $this->command->info("Created {$batchesCreated} inventory batches.");
    }

    /**
     * Create batches for specific inventory with FIFO layers.
     */
    public function createWithLayers(Inventory $inventory, int $layerCount = 3): void
    {
        $baseCost = $inventory->cost ?? 10.0;
        $baseQty = 100;

        for ($i = 0; $i < $layerCount; $i++) {
            $receivedDate = now()->subDays(($layerCount - $i) * 10);
            $quantity = $baseQty - ($i * 10);
            $unitCost = $baseCost + ($i * 2);

            $batch = InventoryBatch::create([
                'tenant_id' => $inventory->tenant_id,
                'product_id' => $inventory->product_id,
                'warehouse_id' => $inventory->warehouse_id,
                'batch_number' => 'BATCH-FIFO-' . strtoupper(uniqid()),
                'received_date' => $receivedDate,
                'expiry_date' => now()->addDays(180),
                'unit_cost' => $unitCost,
                'initial_quantity' => $quantity,
                'remaining_quantity' => $quantity,
                'status' => 'active',
            ]);

            // Create corresponding layer
            $inventory->layers()->create([
                'tenant_id' => $inventory->tenant_id,
                'product_id' => $inventory->product_id,
                'batch_id' => $batch->id,
                'warehouse_id' => $inventory->warehouse_id,
                'store_id' => $inventory->store_id,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'layer_order' => $i + 1,
                'is_fifo_layer' => true,
            ]);
        }

        // Sync inventory with layers
        $inventory->syncWithLayers();
    }
}
