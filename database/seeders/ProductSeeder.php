<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
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

        // Sample products data with Indonesian names and IDR pricing
        $sampleProducts = [
            // Electronics (Elektronik)
            ['name' => 'Headphone Bluetooth Wireless', 'sku' => 'ELEC-HBW-001', 'price' => 299000, 'cost' => 150000, 'category' => 'Audio', 'unit' => 'piece'],
            ['name' => 'Kabel USB-C 2 Meter', 'sku' => 'ELEC-KUC-002', 'price' => 45000, 'cost' => 18000, 'category' => 'Aksesoris', 'unit' => 'piece'],
            ['name' => 'Power Bank 10000mAh', 'sku' => 'ELEC-PWB-003', 'price' => 149000, 'cost' => 75000, 'category' => 'Aksesoris', 'unit' => 'piece'],
            ['name' => 'Mouse Wireless', 'sku' => 'ELEC-MWS-004', 'price' => 89000, 'cost' => 40000, 'category' => 'Komputer', 'unit' => 'piece'],
            ['name' => 'Keyboard Mechanical RGB', 'sku' => 'ELEC-KMR-005', 'price' => 450000, 'cost' => 225000, 'category' => 'Komputer', 'unit' => 'piece'],
            ['name' => 'Webcam HD 1080p', 'sku' => 'ELEC-WHD-006', 'price' => 275000, 'cost' => 135000, 'category' => 'Komputer', 'unit' => 'piece'],
            ['name' => 'Stand HP Adjustable', 'sku' => 'ELEC-SHP-007', 'price' => 75000, 'cost' => 30000, 'category' => 'Aksesoris', 'unit' => 'piece'],
            ['name' => 'Lampu Meja LED', 'sku' => 'ELEC-LML-008', 'price' => 125000, 'cost' => 60000, 'category' => 'Aksesoris', 'unit' => 'piece'],

            // Clothing (Pakaian)
            ['name' => 'Kaos Katun Premium', 'sku' => 'CLT-KKP-001', 'price' => 85000, 'cost' => 35000, 'category' => 'Pria', 'unit' => 'piece'],
            ['name' => 'Celana Jeans Slim Fit', 'sku' => 'CLT-CJS-002', 'price' => 199000, 'cost' => 95000, 'category' => 'Pria', 'unit' => 'piece'],
            ['name' => 'Kemeja Casual Lengan Panjang', 'sku' => 'CLT-KCL-003', 'price' => 175000, 'cost' => 80000, 'category' => 'Pria', 'unit' => 'piece'],
            ['name' => 'Gamis Syar-i Modern', 'sku' => 'CLT-GSM-004', 'price' => 249000, 'cost' => 120000, 'category' => 'Wanita', 'unit' => 'piece'],
            ['name' => 'Legging Yoga Stretch', 'sku' => 'CLT-LYS-005', 'price' => 135000, 'cost' => 65000, 'category' => 'Wanita', 'unit' => 'piece'],
            ['name' => 'Jaket Hoodie Anak', 'sku' => 'CLT-JHA-006', 'price' => 125000, 'cost' => 55000, 'category' => 'Anak', 'unit' => 'piece'],
            ['name' => 'Sepatu Sneaker Sport', 'sku' => 'CLT-SSS-007', 'price' => 299000, 'cost' => 145000, 'category' => 'Sepatu', 'unit' => 'pair'],
            ['name' => 'Ikat Pinggang Kulit Asli', 'sku' => 'CLT-IPK-008', 'price' => 95000, 'cost' => 40000, 'category' => 'Aksesoris', 'unit' => 'piece'],

            // Home & Garden (Rumah & Taman)
            ['name' => 'Set Cangkir Keramik 4 Pcs', 'sku' => 'HOME-SCK-001', 'price' => 125000, 'cost' => 55000, 'category' => 'Dapur', 'unit' => 'set'],
            ['name' => 'Wajan Anti Lengket 28cm', 'sku' => 'HOME-WAL-002', 'price' => 185000, 'cost' => 85000, 'category' => 'Dapur', 'unit' => 'piece'],
            ['name' => 'Bantal Hias Sofa', 'sku' => 'HOME-BHS-003', 'price' => 75000, 'cost' => 30000, 'category' => 'Dekorasi', 'unit' => 'piece'],
            ['name' => 'Selang Air 15 Meter', 'sku' => 'HOME-SAM-004', 'price' => 145000, 'cost' => 65000, 'category' => 'Alat Taman', 'unit' => 'roll'],
            ['name' => 'Lampu Hias LED 10 Meter', 'sku' => 'HOME-LHL-005', 'price' => 95000, 'cost' => 42000, 'category' => 'Pencahayaan', 'unit' => 'roll'],
            ['name' => 'Keranjang Rotan Storage', 'sku' => 'HOME-KRS-006', 'price' => 115000, 'cost' => 50000, 'category' => 'Penyimpanan', 'unit' => 'piece'],
            ['name' => 'Jam Dinding Minimalis', 'sku' => 'HOME-JDM-007', 'price' => 165000, 'cost' => 75000, 'category' => 'Dekorasi', 'unit' => 'piece'],
            ['name' => 'Pot Bunga Keramik Besar', 'sku' => 'HOME-PBK-008', 'price' => 95000, 'cost' => 42000, 'category' => 'Alat Taman', 'unit' => 'piece'],

            // Office Supplies (Alat Tulis Kantor)
            ['name' => 'Pulpen Biru Box 12 Pcs', 'sku' => 'OFF-PBB-001', 'price' => 35000, 'cost' => 15000, 'category' => 'Pena', 'unit' => 'box'],
            ['name' => 'Kertas HVS A4 500 Lembar', 'sku' => 'OFF-KHA-002', 'price' => 45000, 'cost' => 22000, 'category' => 'Kertas', 'unit' => 'rim'],
            ['name' => 'Organizer Meja Mesh', 'sku' => 'OFF-OMM-003', 'price' => 85000, 'cost' => 40000, 'category' => 'Aksesoris Meja', 'unit' => 'piece'],
            ['name' => 'Sticky Notes Warna-warni 6 Pack', 'sku' => 'OFF-SNW-004', 'price' => 28000, 'cost' => 12000, 'category' => 'Kertas', 'unit' => 'pack'],
            ['name' => 'Klip Kertas Besar 12 Pcs', 'sku' => 'OFF-KKB-005', 'price' => 32000, 'cost' => 14000, 'category' => 'Aksesoris Meja', 'unit' => 'box'],
            ['name' => 'Map Folder A4 25 Pcs', 'sku' => 'OFF-MFA-006', 'price' => 55000, 'cost' => 25000, 'category' => 'Penyimpanan', 'unit' => 'pack'],
            ['name' => 'Stapler Heavy Duty', 'sku' => 'OFF-SHD-007', 'price' => 75000, 'cost' => 35000, 'category' => 'Aksesoris Meja', 'unit' => 'piece'],
            ['name' => 'Spidol Whiteboard 4 Warna', 'sku' => 'OFF-SWB-008', 'price' => 42000, 'cost' => 18000, 'category' => 'Pena', 'unit' => 'set'],

            // Food & Beverages (Makanan & Minuman)
            ['name' => 'Kopi Bubuk 200g', 'sku' => 'FNB-KPB-001', 'price' => 45000, 'cost' => 22000, 'category' => 'Minuman', 'unit' => 'pack'],
            ['name' => 'Teh Hijau 50 Tea Bags', 'sku' => 'FNB-THH-002', 'price' => 35000, 'cost' => 16000, 'category' => 'Minuman', 'unit' => 'box'],
            ['name' => 'Gula Pasir 1kg', 'sku' => 'FNB-GPS-003', 'price' => 15000, 'cost' => 12000, 'category' => 'Bahan Masakan', 'unit' => 'kg'],
            ['name' => 'Minyak Goreng 2 Liter', 'sku' => 'FNB-MYG-004', 'price' => 42000, 'cost' => 35000, 'category' => 'Bahan Masakan', 'unit' => 'liter'],
            ['name' => 'Beras Premium 5kg', 'sku' => 'FNB-BRP-005', 'price' => 75000, 'cost' => 65000, 'category' => 'Sembako', 'unit' => 'pack'],
            ['name' => 'Mie Instan Box 40 Pcs', 'sku' => 'FNB-MIB-006', 'price' => 120000, 'cost' => 95000, 'category' => 'Makanan', 'unit' => 'box'],
            ['name' => 'Susu UHT 1 Liter', 'sku' => 'FNB-SUH-007', 'price' => 18000, 'cost' => 14000, 'category' => 'Minuman', 'unit' => 'liter'],
            ['name' => 'Kecap Manis 620ml', 'sku' => 'FNB-KMM-008', 'price' => 22000, 'cost' => 16000, 'category' => 'Bumbu', 'unit' => 'bottle'],
        ];

        foreach ($tenants as $tenant) {
            $warehouses = $tenant->warehouses;

            if ($warehouses->isEmpty()) {
                continue;
            }

            $mainWarehouse = $warehouses->first();

            foreach ($sampleProducts as $productData) {
                // Find category by name
                $category = Category::where('tenant_id', $tenant->id)
                    ->where('slug', Str::slug($productData['category']))
                    ->first();

                // Check if product exists for this tenant
                $product = Product::where('tenant_id', $tenant->id)
                    ->where('sku', $productData['sku'])
                    ->first();

                if ($product) {
                    $product->update([
                        'name' => $productData['name'],
                        'category_id' => $category?->id,
                        'description' => "{$productData['name']} berkualitas tinggi untuk kebutuhan Anda.",
                        'price' => $productData['price'],
                        'cost' => $productData['cost'],
                        'tax_rate' => 11, // Indonesia PPN 11%
                        'unit' => $productData['unit'] ?? 'pcs',
                        'min_stock' => 10,
                        'max_stock' => 500,
                        'track_inventory' => true,
                        'active' => true,
                    ]);
                } else {
                    // Check if SKU exists globally
                    $existing = Product::where('sku', $productData['sku'])->first();
                    if ($existing) {
                        $existing->update([
                            'tenant_id' => $tenant->id,
                            'name' => $productData['name'],
                            'category_id' => $category?->id,
                            'description' => "{$productData['name']} berkualitas tinggi untuk kebutuhan Anda.",
                            'price' => $productData['price'],
                            'cost' => $productData['cost'],
                            'tax_rate' => 11,
                            'unit' => $productData['unit'] ?? 'pcs',
                            'min_stock' => 10,
                            'max_stock' => 500,
                            'track_inventory' => true,
                            'active' => true,
                        ]);
                        $product = $existing;
                    } else {
                        $product = Product::create([
                            'tenant_id' => $tenant->id,
                            'sku' => $productData['sku'],
                            'name' => $productData['name'],
                            'category_id' => $category?->id,
                            'description' => "{$productData['name']} berkualitas tinggi untuk kebutuhan Anda.",
                            'price' => $productData['price'],
                            'cost' => $productData['cost'],
                            'tax_rate' => 11,
                            'unit' => $productData['unit'] ?? 'pcs',
                            'min_stock' => 10,
                            'max_stock' => 500,
                            'track_inventory' => true,
                            'active' => true,
                        ]);
                    }
                }

                // Create inventory record for the main warehouse
                $inventory = Inventory::where('tenant_id', $tenant->id)
                    ->where('product_id', $product->id)
                    ->where('warehouse_id', $mainWarehouse->id)
                    ->first();

                if ($inventory) {
                    $inventory->update([
                        'quantity' => fake()->numberBetween(50, 200),
                        'reserved' => 0,
                    ]);
                } else {
                    $quantity = fake()->numberBetween(50, 200);
                    Inventory::create([
                        'tenant_id' => $tenant->id,
                        'product_id' => $product->id,
                        'warehouse_id' => $mainWarehouse->id,
                        'quantity' => $quantity,
                        'reserved' => 0,
                        'available' => $quantity,
                        'cost' => $product->cost,
                        'notes' => 'Initial stock',
                    ]);
                }
            }

            // Create additional random products
            $categories = $tenant->categories()->whereNull('parent_id')->get();

            Product::factory()
                ->count(20)
                ->forTenant($tenant->id)
                ->create()
                ->each(function ($product) use ($categories, $mainWarehouse) {
                    // Assign random category
                    if ($categories->isNotEmpty()) {
                        $product->category_id = $categories->random()->id;
                        $product->save();
                    }

                    // Create inventory
                    $quantity = fake()->numberBetween(20, 150);
                    Inventory::create([
                        'tenant_id' => $product->tenant_id,
                        'product_id' => $product->id,
                        'warehouse_id' => $mainWarehouse->id,
                        'quantity' => $quantity,
                        'reserved' => 0,
                        'available' => $quantity,
                        'cost' => $product->cost,
                        'notes' => 'Initial stock',
                    ]);
                });
        }
    }
}
