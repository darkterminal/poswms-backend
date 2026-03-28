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

        // Sample customers - Indonesian names
        $sampleCustomers = [
            ['name' => 'Budi Santoso', 'email' => 'budi.santoso@email.com', 'company' => 'CV Santoso Jaya', 'type' => 'business'],
            ['name' => 'Siti Nurhaliza', 'email' => 'siti.nurhaliza@email.com', 'company' => null, 'type' => 'individual'],
            ['name' => 'Ahmad Hidayat', 'email' => 'ahmad.hidayat@email.com', 'company' => 'PT Hidayat Brothers', 'type' => 'business'],
            ['name' => 'Dewi Lestari', 'email' => 'dewi.lestari@email.com', 'company' => null, 'type' => 'individual'],
            ['name' => 'Eko Prasetyo', 'email' => 'eko.prasetyo@email.com', 'company' => 'UD Prasetyo', 'type' => 'business'],
            ['name' => 'Rina Wijaya', 'email' => 'rina.wijaya@email.com', 'company' => null, 'type' => 'individual'],
            ['name' => 'Hendra Gunawan', 'email' => 'hendra.gunawan@email.com', 'company' => 'PT Gunawan Sejahtera', 'type' => 'business'],
            ['name' => 'Maya Kusuma', 'email' => 'maya.kusuma@email.com', 'company' => null, 'type' => 'individual'],
            ['name' => 'Agus Setiawan', 'email' => 'agus.setiawan@email.com', 'company' => 'Toko Agung Jaya', 'type' => 'business'],
            ['name' => 'Fitri Handayani', 'email' => 'fitri.handayani@email.com', 'company' => null, 'type' => 'individual'],
        ];

        foreach ($tenants as $tenant) {
            // Get pricing tiers
            $pricingTiers = $tenant->pricingTiers;
            $bronzeTier = $pricingTiers->where('slug', 'bronze')->first();
            $silverTier = $pricingTiers->where('slug', 'silver')->first();
            $goldTier = $pricingTiers->where('slug', 'gold')->first();

            foreach ($sampleCustomers as $index => $customerData) {
                // Make email unique per tenant
                $uniqueEmail = $tenant->slug . '.' . $customerData['email'];

                // Check if customer already exists
                $customer = Customer::where('tenant_id', $tenant->id)
                    ->where('email', $uniqueEmail)
                    ->first();

                if ($customer) {
                    $customer->update([
                        'name' => $customerData['name'],
                        'phone' => '+62-' . fake()->numerify('###-####'),
                        'company' => $customerData['company'],
                        'tax_id' => $customerData['type'] === 'business' ? fake()->numerify('##.###.###.#-###.###') : null, // Indonesian NPWP format
                        'address' => fake()->address(),
                        'city' => fake()->randomElement(['Jakarta', 'Bandung', 'Surabaya', 'Yogyakarta', 'Semarang', 'Medan', 'Makassar', 'Denpasar']),
                        'state' => fake()->randomElement(['DKI Jakarta', 'Jawa Barat', 'Jawa Timur', 'DI Yogyakarta', 'Jawa Tengah', 'Sumatera Utara', 'Sulawesi Selatan', 'Bali']),
                        'country' => 'Indonesia',
                        'postal_code' => fake()->numerify('#####'),
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
                        'phone' => '+62-' . fake()->numerify('###-####'),
                        'company' => $customerData['company'],
                        'tax_id' => $customerData['type'] === 'business' ? fake()->numerify('##.###.###.#-###.###') : null,
                        'address' => fake()->address(),
                        'city' => fake()->randomElement(['Jakarta', 'Bandung', 'Surabaya', 'Yogyakarta', 'Semarang', 'Medan', 'Makassar', 'Denpasar']),
                        'state' => fake()->randomElement(['DKI Jakarta', 'Jawa Barat', 'Jawa Timur', 'DI Yogyakarta', 'Jawa Tengah', 'Sumatera Utara', 'Sulawesi Selatan', 'Bali']),
                        'country' => 'Indonesia',
                        'postal_code' => fake()->numerify('#####'),
                        'pricing_tier_id' => $pricingTierId,
                        'credit_limit' => $customerData['type'] === 'business' ? fake()->randomElement([5000000, 10000000, 25000000, 50000000]) : 0, // IDR
                        'balance' => 0,
                        'settings' => ['newsletter' => fake()->boolean(70), 'preferred_contact' => 'whatsapp'],
                        'active' => true,
                    ]);
                }
            }

            // Create additional random customers with Indonesian names and unique emails
            $indonesianNames = [
                ['name' => 'Andi Wijaya', 'company' => 'PT Wijaya Mandiri'],
                ['name' => 'Ratna Sari', 'company' => 'CV Sari Indah'],
                ['name' => 'Doni Pratama', 'company' => 'UD Pratama Jaya'],
                ['name' => 'Indah Permata', 'company' => 'Toko Permata'],
                ['name' => 'Rudi Hartono', 'company' => 'PT Hartono Brothers'],
                ['name' => 'Lina Marlina', 'company' => null],
                ['name' => 'Joko Susilo', 'company' => 'CV Susilo Abadi'],
                ['name' => 'Nina Zulkarnaen', 'company' => null],
                ['name' => 'Bayu Aji', 'company' => 'UD Aji Sentosa'],
                ['name' => 'Dian Purnama', 'company' => 'PT Purnama Jaya'],
                ['name' => 'Arief Budiman', 'company' => 'CV Budiman'],
                ['name' => 'Titi Kamal', 'company' => null],
                ['name' => 'Fajar Nugraha', 'company' => 'PT Nugraha Sejahtera'],
                ['name' => 'Putri Anggraini', 'company' => null],
                ['name' => 'Imam Nawawi', 'company' => 'UD Nawawi'],
            ];

            foreach ($indonesianNames as $index => $customerData) {
                $randomEmail = $tenant->slug . '.customer' . $index . '@example.com';

                // Check if customer with this email already exists
                $existingCustomer = Customer::where('email', $randomEmail)->first();

                if (! $existingCustomer) {
                    Customer::create([
                        'tenant_id' => $tenant->id,
                        'name' => $customerData['name'],
                        'email' => $randomEmail,
                        'phone' => '+62-' . fake()->numerify('###-####'),
                        'company' => $customerData['company'],
                        'address' => fake()->address(),
                        'city' => fake()->randomElement(['Jakarta', 'Bandung', 'Surabaya', 'Yogyakarta', 'Semarang']),
                        'state' => fake()->randomElement(['DKI Jakarta', 'Jawa Barat', 'Jawa Timur', 'DI Yogyakarta', 'Jawa Tengah']),
                        'country' => 'Indonesia',
                        'postal_code' => fake()->numerify('#####'),
                        'active' => true,
                    ]);
                }
            }
        }
    }
}
