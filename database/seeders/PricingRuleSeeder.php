<?php

namespace Database\Seeders;

use App\Models\PricingRule;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class PricingRuleSeeder extends Seeder
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
            $pricingTiers = $tenant->pricingTiers;

            // Create sample pricing rules for each tier
            $rules = [
                [
                    'tier_slug' => 'silver',
                    'type' => 'bulk',
                    'operation' => 'add',
                    'value' => 5.00,
                    'min_quantity' => 10,
                    'max_quantity' => null,
                    'description' => '$5 discount for orders over 10 units',
                ],
                [
                    'tier_slug' => 'gold',
                    'type' => 'bulk',
                    'operation' => 'add',
                    'value' => 10.00,
                    'min_quantity' => 10,
                    'max_quantity' => null,
                    'description' => '$10 discount for orders over 10 units',
                ],
                [
                    'tier_slug' => 'gold',
                    'type' => 'percentage',
                    'operation' => 'add',
                    'value' => 15.00,
                    'min_quantity' => 50,
                    'max_quantity' => null,
                    'description' => '15% discount for orders over 50 units',
                ],
                [
                    'tier_slug' => 'wholesale',
                    'type' => 'bulk',
                    'operation' => 'add',
                    'value' => 25.00,
                    'min_quantity' => 20,
                    'max_quantity' => null,
                    'description' => '$25 discount for orders over 20 units',
                ],
                [
                    'tier_slug' => 'wholesale',
                    'type' => 'percentage',
                    'operation' => 'add',
                    'value' => 30.00,
                    'min_quantity' => 100,
                    'max_quantity' => null,
                    'description' => '30% discount for orders over 100 units',
                ],
            ];

            foreach ($rules as $ruleData) {
                $tier = $pricingTiers->where('slug', $ruleData['tier_slug'])->first();

                if (! $tier) {
                    continue;
                }

                // Check if rule exists
                $existingRule = PricingRule::where('tenant_id', $tenant->id)
                    ->where('pricing_tier_id', $tier->id)
                    ->where('type', $ruleData['type'])
                    ->where('operation', $ruleData['operation'])
                    ->where('value', $ruleData['value'])
                    ->first();

                if ($existingRule) {
                    $existingRule->update([
                        'min_quantity' => $ruleData['min_quantity'],
                        'max_quantity' => $ruleData['max_quantity'],
                        'active' => true,
                    ]);
                } else {
                    PricingRule::create([
                        'tenant_id' => $tenant->id,
                        'pricing_tier_id' => $tier->id,
                        'type' => $ruleData['type'],
                        'operation' => $ruleData['operation'],
                        'value' => $ruleData['value'],
                        'min_quantity' => $ruleData['min_quantity'],
                        'max_quantity' => $ruleData['max_quantity'],
                        'starts_at' => null,
                        'ends_at' => null,
                        'active' => true,
                    ]);
                }
            }
        }
    }
}
