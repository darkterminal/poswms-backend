<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
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

        // Sample customers
        $sampleCustomers = [
            ['name' => 'John Smith', 'email' => 'john.smith@email.com', 'company' => 'Smith Enterprises', 'type' => 'business'],
            ['name' => 'Sarah Johnson', 'email' => 'sarah.j@email.com', 'company' => null, 'type' => 'individual'],
            ['name' => 'Michael Brown', 'email' => 'm.brown@email.com', 'company' => 'Brown & Co', 'type' => 'business'],
            ['name' => 'Emily Davis', 'email' => 'emily.davis@email.com', 'company' => null, 'type' => 'individual'],
            ['name' => 'David Wilson', 'email' => 'd.wilson@email.com', 'company' => 'Wilson Trading', 'type' => 'business'],
            ['name' => 'Lisa Anderson', 'email' => 'lisa.a@email.com', 'company' => null, 'type' => 'individual'],
            ['name' => 'Robert Taylor', 'email' => 'r.taylor@email.com', 'company' => 'Taylor Industries', 'type' => 'business'],
            ['name' => 'Jennifer Martinez', 'email' => 'j.martinez@email.com', 'company' => null, 'type' => 'individual'],
            ['name' => 'William Garcia', 'email' => 'w.garcia@email.com', 'company' => 'Garcia Retail', 'type' => 'business'],
            ['name' => 'Amanda Lee', 'email' => 'amanda.lee@email.com', 'company' => null, 'type' => 'individual'],
        ];

        foreach ($tenants as $tenant) {
            // Get pricing tiers
            $pricingTiers = $tenant->pricingTiers;
            $bronzeTier = $pricingTiers->where('slug', 'bronze')->first();
            $silverTier = $pricingTiers->where('slug', 'silver')->first();
            $goldTier = $pricingTiers->where('slug', 'gold')->first();

            foreach ($sampleCustomers as $index => $customerData) {
                // Make email unique per tenant
                $uniqueEmail = $tenant->slug.'.'.$customerData['email'];

                // Check if customer already exists
                $customer = Customer::where('tenant_id', $tenant->id)
                    ->where('email', $uniqueEmail)
                    ->first();

                if ($customer) {
                    $customer->update([
                        'name' => $customerData['name'],
                        'phone' => fake()->phoneNumber(),
                        'company' => $customerData['company'],
                        'tax_id' => $customerData['type'] === 'business' ? fake()->numerify('##-#######') : null,
                        'address' => fake()->address(),
                        'city' => fake()->city(),
                        'state' => fake()->state(),
                        'country' => fake()->country(),
                        'postal_code' => fake()->postcode(),
                        'active' => true,
                    ]);
                } else {
                    // Assign pricing tier based on customer index
                    $pricingTierId = null;
                    if ($index % 5 === 0 && $goldTier) {
                        $pricingTierId = $goldTier->id;
                    } elseif ($index % 3 === 0 && $silverTier) {
                        $pricingTierId = $silverTier->id;
                    } elseif ($bronzeTier) {
                        $pricingTierId = $bronzeTier->id;
                    }

                    Customer::create([
                        'tenant_id' => $tenant->id,
                        'name' => $customerData['name'],
                        'email' => $uniqueEmail,
                        'phone' => fake()->phoneNumber(),
                        'company' => $customerData['company'],
                        'tax_id' => $customerData['type'] === 'business' ? fake()->numerify('##-#######') : null,
                        'address' => fake()->address(),
                        'city' => fake()->city(),
                        'state' => fake()->state(),
                        'country' => fake()->country(),
                        'postal_code' => fake()->postcode(),
                        'pricing_tier_id' => $pricingTierId,
                        'credit_limit' => $customerData['type'] === 'business' ? fake()->randomElement([1000, 2500, 5000, 10000]) : 0,
                        'balance' => 0,
                        'settings' => ['newsletter' => fake()->boolean(70), 'preferred_contact' => 'email'],
                        'active' => true,
                    ]);
                }
            }

            // Create additional random customers with unique emails
            for ($i = 0; $i < 15; $i++) {
                $randomEmail = $tenant->slug.'.customer'.$i.'@example.com';

                // Check if customer with this email already exists
                $existingCustomer = Customer::where('email', $randomEmail)->first();

                if (! $existingCustomer) {
                    Customer::create([
                        'tenant_id' => $tenant->id,
                        'name' => fake()->name(),
                        'email' => $randomEmail,
                        'phone' => fake()->phoneNumber(),
                        'company' => fake()->company(),
                        'address' => fake()->address(),
                        'city' => fake()->city(),
                        'state' => fake()->state(),
                        'country' => fake()->country(),
                        'postal_code' => fake()->postcode(),
                        'active' => true,
                    ]);
                }
            }
        }
    }
}
