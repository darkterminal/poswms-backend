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

        // Indonesian store names and locations
        $storeNames = [
            'Cabang Jakarta', 'Cabang Bandung', 'Cabang Surabaya', 'Cabang Yogyakarta',
            'Cabang Semarang', 'Cabang Medan', 'Cabang Makassar', 'Cabang Denpasar',
            'Cabang Palembang', 'Cabang Balikpapan', 'Cabang Manado', 'Cabang Batam',
        ];

        $storeAddresses = [
            ['address' => 'Jl. Jend. Sudirman Kav. 52', 'city' => 'Jakarta Pusat', 'state' => 'DKI Jakarta', 'postal_code' => '10220'],
            ['address' => 'Jl. Asia Afrika No. 8', 'city' => 'Bandung', 'state' => 'Jawa Barat', 'postal_code' => '40111'],
            ['address' => 'Jl. Basuki Rahmat No. 123', 'city' => 'Surabaya', 'state' => 'Jawa Timur', 'postal_code' => '60271'],
            ['address' => 'Jl. Malioboro No. 45', 'city' => 'Yogyakarta', 'state' => 'DI Yogyakarta', 'postal_code' => '55271'],
            ['address' => 'Jl. Pandanaran No. 28', 'city' => 'Semarang', 'state' => 'Jawa Tengah', 'postal_code' => '50134'],
            ['address' => 'Jl. Gatot Subroto No. 100', 'city' => 'Medan', 'state' => 'Sumatera Utara', 'postal_code' => '20112'],
            ['address' => 'Jl. Pettarani No. 77', 'city' => 'Makassar', 'state' => 'Sulawesi Selatan', 'postal_code' => '90221'],
            ['address' => 'Jl. Raya Kuta No. 15', 'city' => 'Denpasar', 'state' => 'Bali', 'postal_code' => '80361'],
            ['address' => 'Jl. Jend. Sudirman No. 88', 'city' => 'Palembang', 'state' => 'Sumatera Selatan', 'postal_code' => '30126'],
            ['address' => 'Jl. Jend. Sudirman No. 55', 'city' => 'Balikpapan', 'state' => 'Kalimantan Timur', 'postal_code' => '76114'],
            ['address' => 'Jl. Sam Ratulangi No. 30', 'city' => 'Manado', 'state' => 'Sulawesi Utara', 'postal_code' => '95115'],
            ['address' => 'Jl. Imam Bonjol No. 20', 'city' => 'Batam', 'state' => 'Kepulauan Riau', 'postal_code' => '29432'],
        ];

        foreach ($tenants as $tenant) {
            // Create main store for each tenant
            $mainStore = Store::where('tenant_id', $tenant->id)
                ->where('code', 'ST-MAIN-001')
                ->first();

            if ($mainStore) {
                $mainStore->update([
                    'name' => $tenant->name . ' - Store Utama',
                    'address' => $tenant->address,
                    'city' => $tenant->city,
                    'state' => $tenant->state,
                    'country' => $tenant->country,
                    'postal_code' => $tenant->postal_code,
                    'phone' => $tenant->phone,
                    'email' => $tenant->email,
                    'settings' => ['tax_rate' => 0.11, 'currency' => $tenant->currency], // Indonesia PPN 11%
                    'active' => true,
                ]);
            } else {
                // Check if code exists globally
                $existing = Store::where('code', 'ST-MAIN-001')->first();
                if ($existing) {
                    $existing->update([
                        'tenant_id' => $tenant->id,
                        'name' => $tenant->name . ' - Store Utama',
                        'address' => $tenant->address,
                        'city' => $tenant->city,
                        'state' => $tenant->state,
                        'country' => $tenant->country,
                        'postal_code' => $tenant->postal_code,
                        'phone' => $tenant->phone,
                        'email' => $tenant->email,
                        'settings' => ['tax_rate' => 0.11, 'currency' => $tenant->currency],
                        'active' => true,
                    ]);
                } else {
                    Store::create([
                        'tenant_id' => $tenant->id,
                        'code' => 'ST-MAIN-001',
                        'name' => $tenant->name . ' - Store Utama',
                        'address' => $tenant->address,
                        'city' => $tenant->city,
                        'state' => $tenant->state,
                        'country' => $tenant->country,
                        'postal_code' => $tenant->postal_code,
                        'phone' => $tenant->phone,
                        'email' => $tenant->email,
                        'settings' => ['tax_rate' => 0.11, 'currency' => $tenant->currency],
                        'active' => true,
                    ]);
                }
            }

            // Create additional stores with Indonesian names
            $usedIndexes = [];
            $storeCount = fake()->numberBetween(2, 4);

            for ($i = 0; $i < $storeCount; $i++) {
                $randomIndex = fake()->numberBetween(0, count($storeNames) - 1);

                // Avoid duplicate stores
                while (in_array($randomIndex, $usedIndexes)) {
                    $randomIndex = fake()->numberBetween(0, count($storeNames) - 1);
                }
                $usedIndexes[] = $randomIndex;

                Store::create([
                    'tenant_id' => $tenant->id,
                    'code' => 'ST-' . strtoupper(substr($tenant->slug, 0, 3)) . '-' . str_pad(($i + 2), 3, '0', STR_PAD_LEFT),
                    'name' => $storeNames[$randomIndex],
                    'address' => $storeAddresses[$randomIndex]['address'],
                    'city' => $storeAddresses[$randomIndex]['city'],
                    'state' => $storeAddresses[$randomIndex]['state'],
                    'country' => 'Indonesia',
                    'postal_code' => $storeAddresses[$randomIndex]['postal_code'],
                    'phone' => '+62-' . fake()->numerify('###-####'),
                    'email' => strtolower(str_replace(' ', '.', $storeNames[$randomIndex])) . '@' . $tenant->slug . '.com',
                    'settings' => ['tax_rate' => 0.11, 'currency' => $tenant->currency],
                    'active' => true,
                ]);
            }
        }
    }
}
