<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class StockMovementSeeder extends Seeder
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

        $movementTypes = ['adjustment', 'in', 'out', 'sale', 'return', 'transfer'];
        $reasons = ['Manual adjustment', 'Stock transfer', 'Customer order', 'Return from customer', 'New shipment received', 'Damaged goods', 'Inventory count'];

        foreach ($tenants as $tenant) {
            $inventories = $tenant->inventories;
            $users = $tenant->users;

            if ($inventories->isEmpty()) {
                continue;
            }

            // Get or create a system user for movements
            $systemUser = $users->first();

            // Create stock movements for each inventory
            foreach ($inventories as $inventory) {
                // Create 3-10 movements per inventory item
                $movementCount = fake()->numberBetween(3, 10);

                $currentQuantity = $inventory->quantity ?? 100;

                for ($i = 0; $i < $movementCount; $i++) {
                    $movementType = fake()->randomElement($movementTypes);
                    $quantity = fake()->numberBetween(1, 50);

                    // Calculate quantity before and after
                    $quantityBefore = $currentQuantity;
                    if (in_array($movementType, ['in', 'return', 'adjustment'])) {
                        $currentQuantity += $quantity;
                    } elseif (in_array($movementType, ['out', 'sale', 'transfer'])) {
                        $currentQuantity = max(0, $currentQuantity - $quantity);
                    }
                    $quantityAfter = $currentQuantity;

                    // Generate realistic unit cost between $5.00 and $500.00
                    $unitCost = fake()->randomFloat(4, 5.0, 500.0);
                    $totalCost = $quantity * $unitCost;

                    StockMovement::create([
                        'tenant_id' => $tenant->id,
                        'inventory_id' => $inventory->id,
                        'product_id' => $inventory->product_id,
                        'warehouse_id' => $inventory->warehouse_id,
                        'user_id' => $systemUser?->id,
                        'type' => $movementType,
                        'quantity' => in_array($movementType, ['out', 'sale', 'transfer']) ? -$quantity : $quantity,
                        'quantity_before' => $quantityBefore,
                        'quantity_after' => $quantityAfter,
                        'unit_cost' => $unitCost,
                        'total_cost' => $totalCost,
                        'reference' => fake()->optional()->uuid(),
                        'reason' => fake()->randomElement($reasons),
                    ]);
                }

                // Update inventory to final quantity
                $inventory->update(['quantity' => $currentQuantity]);
            }
        }
    }
}
