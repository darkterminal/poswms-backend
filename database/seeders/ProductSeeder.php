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

        // Sample products data
        $sampleProducts = [
            // Electronics
            ['name' => 'Wireless Bluetooth Headphones', 'sku' => 'ELEC-WBH-001', 'price' => 79.99, 'cost' => 35.00, 'category' => 'Audio'],
            ['name' => 'USB-C Charging Cable 2m', 'sku' => 'ELEC-USC-002', 'price' => 14.99, 'cost' => 5.00, 'category' => 'Accessories'],
            ['name' => 'Portable Power Bank 10000mAh', 'sku' => 'ELEC-PPB-003', 'price' => 39.99, 'cost' => 18.00, 'category' => 'Accessories'],
            ['name' => 'Wireless Mouse', 'sku' => 'ELEC-WMS-004', 'price' => 29.99, 'cost' => 12.00, 'category' => 'Computers'],
            ['name' => 'Mechanical Keyboard RGB', 'sku' => 'ELEC-MKR-005', 'price' => 89.99, 'cost' => 45.00, 'category' => 'Computers'],
            ['name' => 'HD Webcam 1080p', 'sku' => 'ELEC-HWC-006', 'price' => 59.99, 'cost' => 28.00, 'category' => 'Computers'],
            ['name' => 'Smartphone Stand Adjustable', 'sku' => 'ELEC-SSA-007', 'price' => 19.99, 'cost' => 7.00, 'category' => 'Accessories'],
            ['name' => 'LED Desk Lamp', 'sku' => 'ELEC-LDL-008', 'price' => 34.99, 'cost' => 15.00, 'category' => 'Accessories'],

            // Clothing
            ['name' => 'Cotton T-Shirt Classic', 'sku' => 'CLT-CTS-001', 'price' => 24.99, 'cost' => 8.00, 'category' => 'Men'],
            ['name' => 'Slim Fit Jeans', 'sku' => 'CLT-SFJ-002', 'price' => 49.99, 'cost' => 22.00, 'category' => 'Men'],
            ['name' => 'Casual Button-Down Shirt', 'sku' => 'CLT-CBS-003', 'price' => 39.99, 'cost' => 16.00, 'category' => 'Men'],
            ['name' => 'Women\'s Summer Dress', 'sku' => 'CLT-WSD-004', 'price' => 54.99, 'cost' => 24.00, 'category' => 'Women'],
            ['name' => 'Yoga Pants Stretch', 'sku' => 'CLT-YPS-005', 'price' => 44.99, 'cost' => 18.00, 'category' => 'Women'],
            ['name' => 'Kids Hoodie', 'sku' => 'CLT-KHD-006', 'price' => 34.99, 'cost' => 14.00, 'category' => 'Kids'],
            ['name' => 'Running Sneakers', 'sku' => 'CLT-RNS-007', 'price' => 79.99, 'cost' => 35.00, 'category' => 'Shoes'],
            ['name' => 'Leather Belt', 'sku' => 'CLT-LTB-008', 'price' => 29.99, 'cost' => 10.00, 'category' => 'Accessories'],

            // Home & Garden
            ['name' => 'Ceramic Coffee Mug Set', 'sku' => 'HOME-CCM-001', 'price' => 34.99, 'cost' => 12.00, 'category' => 'Kitchen'],
            ['name' => 'Non-Stick Frying Pan 28cm', 'sku' => 'HOME-NSF-002', 'price' => 44.99, 'cost' => 20.00, 'category' => 'Kitchen'],
            ['name' => 'Decorative Throw Pillow', 'sku' => 'HOME-DTP-003', 'price' => 24.99, 'cost' => 9.00, 'category' => 'Decor'],
            ['name' => 'Garden Hose 15m', 'sku' => 'HOME-GH15-004', 'price' => 39.99, 'cost' => 18.00, 'category' => 'Garden Tools'],
            ['name' => 'LED String Lights 10m', 'sku' => 'HOME-LSL-005', 'price' => 29.99, 'cost' => 11.00, 'category' => 'Lighting'],
            ['name' => 'Storage Basket Woven', 'sku' => 'HOME-SBW-006', 'price' => 34.99, 'cost' => 14.00, 'category' => 'Storage'],
            ['name' => 'Wall Clock Modern', 'sku' => 'HOME-WCM-007', 'price' => 44.99, 'cost' => 19.00, 'category' => 'Decor'],
            ['name' => 'Plant Pot Ceramic Large', 'sku' => 'HOME-PPC-008', 'price' => 29.99, 'cost' => 11.00, 'category' => 'Garden Tools'],

            // Office Supplies
            ['name' => 'Ballpoint Pens Blue 12pk', 'sku' => 'OFF-BPB-001', 'price' => 9.99, 'cost' => 3.00, 'category' => 'Pens'],
            ['name' => 'A4 Copy Paper 500 Sheets', 'sku' => 'OFF-CPR-002', 'price' => 12.99, 'cost' => 5.00, 'category' => 'Paper'],
            ['name' => 'Desk Organizer Mesh', 'sku' => 'OFF-DOM-003', 'price' => 24.99, 'cost' => 10.00, 'category' => 'Desk Accessories'],
            ['name' => 'Sticky Notes Assorted 6pk', 'sku' => 'OFF-SNA-004', 'price' => 7.99, 'cost' => 2.50, 'category' => 'Paper'],
            ['name' => 'Binder Clips Large 12pk', 'sku' => 'OFF-BCL-005', 'price' => 8.99, 'cost' => 3.00, 'category' => 'Desk Accessories'],
            ['name' => 'File Folders Letter 25pk', 'sku' => 'OFF-FFL-006', 'price' => 14.99, 'cost' => 6.00, 'category' => 'Storage'],
            ['name' => 'Stapler Heavy Duty', 'sku' => 'OFF-SHD-007', 'price' => 19.99, 'cost' => 8.00, 'category' => 'Desk Accessories'],
            ['name' => 'Whiteboard Markers 4pk', 'sku' => 'OFF-WBM-008', 'price' => 11.99, 'cost' => 4.00, 'category' => 'Pens'],
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
                        'description' => "High quality {$productData['name']} for your needs.",
                        'price' => $productData['price'],
                        'cost' => $productData['cost'],
                        'tax_rate' => 0,
                        'unit' => 'piece',
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
                            'description' => "High quality {$productData['name']} for your needs.",
                            'price' => $productData['price'],
                            'cost' => $productData['cost'],
                            'tax_rate' => 0,
                            'unit' => 'piece',
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
                            'description' => "High quality {$productData['name']} for your needs.",
                            'price' => $productData['price'],
                            'cost' => $productData['cost'],
                            'tax_rate' => 0,
                            'unit' => 'piece',
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
