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

        // Indonesian warehouse names and locations
        $warehouseNames = [
            'Gudang Jakarta Utara', 'Gudang Tangerang', 'Gudang Bekasi', 'Gudang Cikarang',
            'Gudang Bandung', 'Gudang Surabaya', 'Gudang Gresik', 'Gudang Medan',
            'Gudang Makassar', 'Gudang Balikpapan', 'Gudang Semarang', 'Gudang Yogyakarta',
        ];

        $warehouseLocations = [
            ['address' => 'Kawasan Industri Pulogadung, Jl. Rawa Gelam', 'city' => 'Jakarta Utara', 'state' => 'DKI Jakarta', 'postal_code' => '13930', 'lat' => -6.1751, 'lng' => 106.8650],
            ['address' => 'Kawasan Industri GIIC, Jl. Raya Serang', 'city' => 'Tangerang', 'state' => 'Banten', 'postal_code' => '15710', 'lat' => -6.2297, 'lng' => 106.5717],
            ['address' => 'Kawasan Industri MM2100, Cibitung', 'city' => 'Bekasi', 'state' => 'Jawa Barat', 'postal_code' => '17530', 'lat' => -6.2538, 'lng' => 107.1383],
            ['address' => 'Kawasan Industri EJIP, Cikarang', 'city' => 'Bekasi', 'state' => 'Jawa Barat', 'postal_code' => '17530', 'lat' => -6.2614, 'lng' => 107.1522],
            ['address' => 'Kawasan Industri Pulo Gadung', 'city' => 'Bandung', 'state' => 'Jawa Barat', 'postal_code' => '40132', 'lat' => -6.9175, 'lng' => 107.6191],
            ['address' => 'Kawasan Industri Rungkut', 'city' => 'Surabaya', 'state' => 'Jawa Timur', 'postal_code' => '60293', 'lat' => -7.3267, 'lng' => 112.7519],
            ['address' => 'Kawasan Industri Gresik', 'city' => 'Gresik', 'state' => 'Jawa Timur', 'postal_code' => '61118', 'lat' => -7.1503, 'lng' => 112.6536],
            ['address' => 'Kawasan Industri Medan', 'city' => 'Medan', 'state' => 'Sumatera Utara', 'postal_code' => '20228', 'lat' => 3.5952, 'lng' => 98.6722],
            ['address' => 'Kawasan Industri Makassar', 'city' => 'Makassar', 'state' => 'Sulawesi Selatan', 'postal_code' => '90245', 'lat' => -5.1477, 'lng' => 119.4327],
            ['address' => 'Kawasan Industri Balikpapan', 'city' => 'Balikpapan', 'state' => 'Kalimantan Timur', 'postal_code' => '76114', 'lat' => -1.2379, 'lng' => 116.8529],
            ['address' => 'Kawasan Industri Candi', 'city' => 'Semarang', 'state' => 'Jawa Tengah', 'postal_code' => '50135', 'lat' => -6.9667, 'lng' => 110.4167],
            ['address' => 'Kawasan Industri Yogyakarta', 'city' => 'Yogyakarta', 'state' => 'DI Yogyakarta', 'postal_code' => '55283', 'lat' => -7.7956, 'lng' => 110.3695],
        ];

        foreach ($tenants as $tenant) {
            // Create main warehouse for each tenant
            $mainWarehouse = Warehouse::where('tenant_id', $tenant->id)
                ->where('code', 'WH-MAIN-001')
                ->first();

            if ($mainWarehouse) {
                $mainWarehouse->update([
                    'name' => $tenant->name . ' - Gudang Utama',
                    'address' => $tenant->address,
                    'city' => $tenant->city,
                    'state' => $tenant->state,
                    'country' => $tenant->country,
                    'postal_code' => $tenant->postal_code,
                    'phone' => $tenant->phone,
                    'email' => $tenant->email,
                    'latitude' => -6.2088, // Jakarta coordinates
                    'longitude' => 106.8456,
                    'settings' => ['capacity' => 10000, 'currency' => $tenant->currency],
                    'active' => true,
                ]);
            } else {
                // Check if code exists globally
                $existing = Warehouse::where('code', 'WH-MAIN-001')->first();
                if ($existing) {
                    $existing->update([
                        'tenant_id' => $tenant->id,
                        'name' => $tenant->name . ' - Gudang Utama',
                        'address' => $tenant->address,
                        'city' => $tenant->city,
                        'state' => $tenant->state,
                        'country' => $tenant->country,
                        'postal_code' => $tenant->postal_code,
                        'phone' => $tenant->phone,
                        'email' => $tenant->email,
                        'latitude' => -6.2088,
                        'longitude' => 106.8456,
                        'settings' => ['capacity' => 10000, 'currency' => $tenant->currency],
                        'active' => true,
                    ]);
                } else {
                    Warehouse::create([
                        'tenant_id' => $tenant->id,
                        'code' => 'WH-MAIN-001',
                        'name' => $tenant->name . ' - Gudang Utama',
                        'address' => $tenant->address,
                        'city' => $tenant->city,
                        'state' => $tenant->state,
                        'country' => $tenant->country,
                        'postal_code' => $tenant->postal_code,
                        'phone' => $tenant->phone,
                        'email' => $tenant->email,
                        'latitude' => -6.2088,
                        'longitude' => 106.8456,
                        'settings' => ['capacity' => 10000, 'currency' => $tenant->currency],
                        'active' => true,
                    ]);
                }
            }

            // Create additional warehouses with Indonesian names
            $usedIndexes = [];
            $warehouseCount = fake()->numberBetween(1, 3);

            for ($i = 0; $i < $warehouseCount; $i++) {
                $randomIndex = fake()->numberBetween(0, count($warehouseNames) - 1);

                // Avoid duplicate warehouses
                while (in_array($randomIndex, $usedIndexes)) {
                    $randomIndex = fake()->numberBetween(0, count($warehouseNames) - 1);
                }
                $usedIndexes[] = $randomIndex;

                Warehouse::create([
                    'tenant_id' => $tenant->id,
                    'code' => 'WH-' . strtoupper(substr($tenant->slug, 0, 3)) . '-' . str_pad(($i + 2), 3, '0', STR_PAD_LEFT),
                    'name' => $warehouseNames[$randomIndex],
                    'address' => $warehouseLocations[$randomIndex]['address'],
                    'city' => $warehouseLocations[$randomIndex]['city'],
                    'state' => $warehouseLocations[$randomIndex]['state'],
                    'country' => 'Indonesia',
                    'postal_code' => $warehouseLocations[$randomIndex]['postal_code'],
                    'phone' => '+62-' . fake()->numerify('###-####'),
                    'email' => strtolower(str_replace(' ', '.', $warehouseNames[$randomIndex])) . '@' . $tenant->slug . '.com',
                    'latitude' => $warehouseLocations[$randomIndex]['lat'],
                    'longitude' => $warehouseLocations[$randomIndex]['lng'],
                    'settings' => ['capacity' => fake()->randomElement([5000, 10000, 15000, 20000]), 'currency' => $tenant->currency],
                    'active' => true,
                ]);
            }
        }
    }
}
