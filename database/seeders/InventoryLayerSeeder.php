<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryLayer;
use Illuminate\Database\Seeder;
use Random\Randomizer;

class InventoryLayerSeeder extends Seeder
{

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $inventories = Inventory::whereHas('product')->get();

        if ($inventories->isEmpty()) {
            $this->command->info('No inventories found. Skipping layer seeding.');

            return;
        }

        $layersCreated = 0;

        $inventories->each(function ($inventory) use (&$layersCreated) {
            // Check if inventory already has layers
            if ($inventory->layers()->exists()) {
                return;
            }

            $randomizer = new Randomizer();

            // Create 2-4 FIFO layers per inventory
            $layerCount = random_int(2, 4);
            $baseCost = $inventory->cost ?? $randomizer->getFloat(5, 50);
            $remainingQty = $inventory->quantity;

            for ($i = 0; $i < $layerCount && $remainingQty > 0; $i++) {
                $quantity = min(random_int(20, 100), $remainingQty);
                $unitCost = $baseCost + ($i * $randomizer->getFloat(0.5, 3));
                $receivedDate = now()->subDays(($layerCount - $i) * 15);

                // Create batch for this layer
                $batch = InventoryBatch::create([
                    'tenant_id' => $inventory->tenant_id,
                    'product_id' => $inventory->product_id,
                    'warehouse_id' => $inventory->warehouse_id,
                    'batch_number' => 'BATCH-FIFO-' . strtoupper(uniqid('' . $inventory->id . '-')),
                    'received_date' => $receivedDate,
                    'expiry_date' => now()->addDays(random_int(60, 365)),
                    'unit_cost' => $unitCost,
                    'initial_quantity' => $quantity,
                    'remaining_quantity' => $quantity,
                    'status' => 'active',
                ]);

                // Create layer
                InventoryLayer::create([
                    'tenant_id' => $inventory->tenant_id,
                    'product_id' => $inventory->product_id,
                    'inventory_id' => $inventory->id,
                    'batch_id' => $batch->id,
                    'warehouse_id' => $inventory->warehouse_id,
                    'store_id' => $inventory->store_id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'layer_order' => $i + 1,
                    'is_fifo_layer' => true,
                ]);

                $remainingQty -= $quantity;
                $layersCreated++;
            }

            // Sync inventory with layers
            if ($layersCreated > 0) {
                $inventory->syncWithLayers();
            }
        });

        $this->command->info("Created {$layersCreated} inventory layers.");
    }

    /**
     * Create FIFO layers for testing with known values.
     */
    public function createTestLayers(
        Inventory $inventory,
        array $layers = [
            ['qty' => 100, 'cost' => 10.00, 'daysOld' => 30],
            ['qty' => 150, 'cost' => 12.50, 'daysOld' => 20],
            ['qty' => 200, 'cost' => 11.75, 'daysOld' => 10],
        ]
    ): void {
        foreach ($layers as $index => $layerData) {
            $batch = InventoryBatch::create([
                'tenant_id' => $inventory->tenant_id,
                'product_id' => $inventory->product_id,
                'warehouse_id' => $inventory->warehouse_id,
                'batch_number' => 'TEST-BATCH-' . strtoupper(uniqid('' . $index . '-')),
                'received_date' => now()->subDays($layerData['daysOld']),
                'unit_cost' => $layerData['cost'],
                'initial_quantity' => $layerData['qty'],
                'remaining_quantity' => $layerData['qty'],
                'status' => 'active',
            ]);

            InventoryLayer::create([
                'tenant_id' => $inventory->tenant_id,
                'product_id' => $inventory->product_id,
                'inventory_id' => $inventory->id,
                'batch_id' => $batch->id,
                'warehouse_id' => $inventory->warehouse_id,
                'store_id' => $inventory->store_id,
                'quantity' => $layerData['qty'],
                'unit_cost' => $layerData['cost'],
                'layer_order' => $index + 1,
                'is_fifo_layer' => true,
            ]);
        }

        $inventory->syncWithLayers();
    }
}
