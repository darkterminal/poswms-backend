<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create demo tenants
        $tenants = [
            [
                'name' => 'Acme Corporation',
                'slug' => 'acme-corp',
                'company_name' => 'Acme Corporation Inc.',
                'email' => 'admin@acme.com',
                'phone' => '+1-555-0100',
                'address' => '123 Business Ave',
                'city' => 'New York',
                'state' => 'NY',
                'country' => 'United States',
                'postal_code' => '10001',
                'timezone' => 'America/New_York',
                'currency' => 'USD',
                'status' => 'active',
                'settings' => ['theme' => 'light', 'notifications' => true, 'low_stock_threshold' => 20],
                'trial_ends_at' => null,
                'subscription_ends_at' => now()->addYear(),
            ],
            [
                'name' => 'TechMart Retail',
                'slug' => 'techmart',
                'company_name' => 'TechMart Retail LLC',
                'email' => 'admin@techmart.com',
                'phone' => '+1-555-0200',
                'address' => '456 Commerce Blvd',
                'city' => 'Los Angeles',
                'state' => 'CA',
                'country' => 'United States',
                'postal_code' => '90001',
                'timezone' => 'America/Los_Angeles',
                'currency' => 'USD',
                'status' => 'active',
                'settings' => ['theme' => 'dark', 'notifications' => true, 'low_stock_threshold' => 15],
                'trial_ends_at' => null,
                'subscription_ends_at' => now()->addMonths(6),
            ],
            [
                'name' => 'Global Supplies',
                'slug' => 'global-supplies',
                'company_name' => 'Global Supplies International',
                'email' => 'admin@globalsupplies.com',
                'phone' => '+44-20-5550300',
                'address' => '789 Trade Street',
                'city' => 'London',
                'state' => 'England',
                'country' => 'United Kingdom',
                'postal_code' => 'SW1A 1AA',
                'timezone' => 'Europe/London',
                'currency' => 'GBP',
                'status' => 'active',
                'settings' => ['theme' => 'light', 'notifications' => false, 'low_stock_threshold' => 25],
                'trial_ends_at' => now()->addWeeks(2),
                'subscription_ends_at' => null,
            ],
        ];

        foreach ($tenants as $tenantData) {
            Tenant::firstOrCreate(
                ['slug' => $tenantData['slug']],
                $tenantData
            );
        }

        // Create additional random tenants for testing
        Tenant::factory()->count(2)->active()->create();
    }
}
