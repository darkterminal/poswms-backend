<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
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
            // Create main store for each tenant
            $mainStore = Store::where('tenant_id', $tenant->id)
                ->where('code', 'ST-MAIN-001')
                ->first();

            if ($mainStore) {
                $mainStore->update([
                    'name' => $tenant->name.' - Main Store',
                    'address' => $tenant->address,
                    'city' => $tenant->city,
                    'state' => $tenant->state,
                    'country' => $tenant->country,
                    'postal_code' => $tenant->postal_code,
                    'phone' => $tenant->phone,
                    'email' => $tenant->email,
                    'settings' => ['tax_rate' => 0.08, 'currency' => $tenant->currency],
                    'active' => true,
                ]);
            } else {
                // Check if code exists globally
                $existing = Store::where('code', 'ST-MAIN-001')->first();
                if ($existing) {
                    $existing->update([
                        'tenant_id' => $tenant->id,
                        'name' => $tenant->name.' - Main Store',
                        'address' => $tenant->address,
                        'city' => $tenant->city,
                        'state' => $tenant->state,
                        'country' => $tenant->country,
                        'postal_code' => $tenant->postal_code,
                        'phone' => $tenant->phone,
                        'email' => $tenant->email,
                        'settings' => ['tax_rate' => 0.08, 'currency' => $tenant->currency],
                        'active' => true,
                    ]);
                } else {
                    Store::create([
                        'tenant_id' => $tenant->id,
                        'code' => 'ST-MAIN-001',
                        'name' => $tenant->name.' - Main Store',
                        'address' => $tenant->address,
                        'city' => $tenant->city,
                        'state' => $tenant->state,
                        'country' => $tenant->country,
                        'postal_code' => $tenant->postal_code,
                        'phone' => $tenant->phone,
                        'email' => $tenant->email,
                        'settings' => ['tax_rate' => 0.08, 'currency' => $tenant->currency],
                        'active' => true,
                    ]);
                }
            }

            // Create additional stores using factory
            Store::factory()
                ->count(fake()->numberBetween(2, 5))
                ->forTenant($tenant->id)
                ->create();
        }
    }
}
