<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
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
            // Create main warehouse for each tenant
            $mainWarehouse = Warehouse::where('tenant_id', $tenant->id)
                ->where('code', 'WH-MAIN-001')
                ->first();

            if ($mainWarehouse) {
                $mainWarehouse->update([
                    'name' => $tenant->name . ' - Main Warehouse',
                    'address' => $tenant->address,
                    'city' => $tenant->city,
                    'state' => $tenant->state,
                    'country' => $tenant->country,
                    'postal_code' => $tenant->postal_code,
                    'phone' => $tenant->phone,
                    'email' => $tenant->email,
                    'latitude' => fake()->latitude(),
                    'longitude' => fake()->longitude(),
                    'settings' => ['capacity' => 10000, 'currency' => $tenant->currency],
                    'active' => true,
                ]);
            } else {
                // Check if code exists globally
                $existing = Warehouse::where('code', 'WH-MAIN-001')->first();
                if ($existing) {
                    $existing->update([
                        'tenant_id' => $tenant->id,
                        'name' => $tenant->name . ' - Main Warehouse',
                        'address' => $tenant->address,
                        'city' => $tenant->city,
                        'state' => $tenant->state,
                        'country' => $tenant->country,
                        'postal_code' => $tenant->postal_code,
                        'phone' => $tenant->phone,
                        'email' => $tenant->email,
                        'latitude' => fake()->latitude(),
                        'longitude' => fake()->longitude(),
                        'settings' => ['capacity' => 10000, 'currency' => $tenant->currency],
                        'active' => true,
                    ]);
                } else {
                    Warehouse::create([
                        'tenant_id' => $tenant->id,
                        'code' => 'WH-MAIN-001',
                        'name' => $tenant->name . ' - Main Warehouse',
                        'address' => $tenant->address,
                        'city' => $tenant->city,
                        'state' => $tenant->state,
                        'country' => $tenant->country,
                        'postal_code' => $tenant->postal_code,
                        'phone' => $tenant->phone,
                        'email' => $tenant->email,
                        'latitude' => fake()->latitude(),
                        'longitude' => fake()->longitude(),
                        'settings' => ['capacity' => 10000, 'currency' => $tenant->currency],
                        'active' => true,
                    ]);
                }
            }

            // Create additional warehouses using factory
            Warehouse::factory()
                ->count(fake()->numberBetween(1, 3))
                ->forTenant($tenant->id)
                ->create();
        }
    }
}
