<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            return;
        }

        foreach ($tenants as $tenant) {
            $products = $tenant->products;
            $warehouses = $tenant->warehouses;

            if ($products->isEmpty() || $warehouses->isEmpty()) {
                continue;
            }

            $mainWarehouse = $warehouses->first();

            // Ensure all products have inventory in the main warehouse
            foreach ($products as $product) {
                $inventory = Inventory::where('tenant_id', $tenant->id)
                    ->where('product_id', $product->id)
                    ->where('warehouse_id', $mainWarehouse->id)
                    ->first();

                if ($inventory) {
                    $inventory->update([
                        'quantity' => fake()->numberBetween(20, 200),
                        'reserved' => fake()->numberBetween(0, 20),
                    ]);
                } else {
                    $quantity = fake()->numberBetween(20, 200);
                    $reserved = fake()->numberBetween(0, 20);

                    Inventory::create([
                        'tenant_id' => $tenant->id,
                        'product_id' => $product->id,
                        'warehouse_id' => $mainWarehouse->id,
                        'quantity' => $quantity,
                        'reserved' => $reserved,
                        'available' => $quantity - $reserved,
                        'cost' => $product->cost,
                        'notes' => 'Stock from InventorySeeder',
                    ]);
                }
            }

            // Create inventory records for additional warehouses (optional)
            if ($warehouses->count() > 1) {
                foreach ($warehouses->skip(1) as $warehouse) {
                    // Add inventory for some products in other warehouses
                    $sampleProducts = $products->random(min(10, $products->count()));

                    foreach ($sampleProducts as $product) {
                        $existingInventory = Inventory::where('tenant_id', $tenant->id)
                            ->where('product_id', $product->id)
                            ->where('warehouse_id', $warehouse->id)
                            ->first();

                        if (! $existingInventory) {
                            $quantity = fake()->numberBetween(10, 100);
                            Inventory::create([
                                'tenant_id' => $tenant->id,
                                'product_id' => $product->id,
                                'warehouse_id' => $warehouse->id,
                                'quantity' => $quantity,
                                'reserved' => 0,
                                'available' => $quantity,
                                'cost' => $product->cost,
                                'notes' => 'Additional warehouse stock',
                            ]);
                        }
                    }
                }
            }

            // Create some low stock items for testing alerts
            $lowStockProducts = $products->random(min(3, $products->count()));
            foreach ($lowStockProducts as $product) {
                $inventory = Inventory::where('tenant_id', $tenant->id)
                    ->where('product_id', $product->id)
                    ->where('warehouse_id', $mainWarehouse->id)
                    ->first();

                if ($inventory) {
                    $inventory->update([
                        'quantity' => fake()->numberBetween(0, 5),
                        'notes' => 'Low stock item for testing',
                    ]);
                }
            }
        }
    }
}
