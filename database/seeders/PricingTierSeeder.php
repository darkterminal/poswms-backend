<?php

namespace Database\Seeders;

use App\Models\PricingTier;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class PricingTierSeeder extends Seeder
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

        $tiers = [
            [
                'name' => 'Bronze',
                'slug' => 'bronze',
                'description' => 'Standard pricing for regular customers',
                'priority' => 1,
                'active' => true,
            ],
            [
                'name' => 'Silver',
                'slug' => 'silver',
                'description' => 'Discounted pricing for valued customers',
                'priority' => 2,
                'active' => true,
            ],
            [
                'name' => 'Gold',
                'slug' => 'gold',
                'description' => 'Best pricing for premium customers',
                'priority' => 3,
                'active' => true,
            ],
            [
                'name' => 'Wholesale',
                'slug' => 'wholesale',
                'description' => 'Special pricing for wholesale partners',
                'priority' => 4,
                'active' => true,
            ],
        ];

        foreach ($tenants as $tenant) {
            foreach ($tiers as $tierData) {
                $tier = PricingTier::where('tenant_id', $tenant->id)
                    ->where('slug', $tierData['slug'])
                    ->first();

                if ($tier) {
                    $tier->update([
                        'name' => $tierData['name'],
                        'description' => $tierData['description'],
                        'priority' => $tierData['priority'],
                        'active' => $tierData['active'],
                    ]);
                } else {
                    // Check if slug exists globally
                    $existing = PricingTier::where('slug', $tierData['slug'])->first();
                    if ($existing) {
                        $existing->update([
                            'tenant_id' => $tenant->id,
                            'name' => $tierData['name'],
                            'description' => $tierData['description'],
                            'priority' => $tierData['priority'],
                            'active' => $tierData['active'],
                        ]);
                    } else {
                        PricingTier::create([
                            'tenant_id' => $tenant->id,
                            'name' => $tierData['name'],
                            'slug' => $tierData['slug'],
                            'description' => $tierData['description'],
                            'priority' => $tierData['priority'],
                            'active' => $tierData['active'],
                        ]);
                    }
                }
            }
        }
    }
}
