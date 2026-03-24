<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductPriceLevel;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ProductPriceLevelSeeder extends Seeder
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
            // Create Sampoerna Prima Kretek with price levels (user's example)
            $sampoerna = Product::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'sku' => 'SAM-PRM-001',
                ],
                [
                    'name' => 'Sampoerna Prima Kretek',
                    'description' => 'Premium clove cigarettes',
                    'price' => 24000, // Base price per piece
                    'cost' => 20000,
                    'tax_rate' => 0,
                    'unit' => 'piece',
                    'min_stock' => 10,
                    'max_stock' => 1000,
                    'track_inventory' => true,
                    'active' => true,
                ]
            );

            // Create price levels for Sampoerna
            $this->createOrUpdatePriceLevels($sampoerna, [
                [
                    'level_name' => 'piece',
                    'level_order' => 1,
                    'unit_size' => 1,
                    'price' => 24000,
                    'cost' => 20000,
                    'barcode' => '8992727000015',
                ],
                [
                    'level_name' => 'pack', // slop
                    'level_order' => 2,
                    'unit_size' => 10,
                    'price' => 236000,
                    'cost' => 196000,
                    'barcode' => '8992727000022',
                ],
                [
                    'level_name' => 'carton',
                    'level_order' => 3,
                    'unit_size' => 200,
                    'price' => 24500000,
                    'cost' => 20000000,
                    'barcode' => '8992727000039',
                ],
            ]);

            // Create additional sample products with price levels
            $products = [
                [
                    'product' => [
                        'sku' => 'IND-MLK-001',
                        'name' => 'Indomilk Sweetened Condensed Milk',
                        'price' => 15000,
                        'cost' => 12000,
                        'unit' => 'can',
                    ],
                    'levels' => [
                        ['level_name' => 'can', 'level_order' => 1, 'unit_size' => 1, 'price' => 15000, 'cost' => 12000],
                        ['level_name' => 'pack', 'level_order' => 2, 'unit_size' => 12, 'price' => 174000, 'cost' => 140000],
                        ['level_name' => 'carton', 'level_order' => 3, 'unit_size' => 48, 'price' => 680000, 'cost' => 550000],
                    ],
                ],
                [
                    'product' => [
                        'sku' => 'MNY-INS-001',
                        'name' => 'Mie Sedaap Instant Noodles',
                        'price' => 3500,
                        'cost' => 2800,
                        'unit' => 'pack',
                    ],
                    'levels' => [
                        ['level_name' => 'pack', 'level_order' => 1, 'unit_size' => 1, 'price' => 3500, 'cost' => 2800],
                        ['level_name' => 'dozen', 'level_order' => 2, 'unit_size' => 10, 'price' => 34000, 'cost' => 27500],
                        ['level_name' => 'carton', 'level_order' => 3, 'unit_size' => 40, 'price' => 132000, 'cost' => 108000],
                    ],
                ],
                [
                    'product' => [
                        'sku' => 'KOP-001',
                        'name' => 'Kopi Kapal Api Special Mix',
                        'price' => 12000,
                        'cost' => 9500,
                        'unit' => 'pack',
                    ],
                    'levels' => [
                        ['level_name' => 'pack', 'level_order' => 1, 'unit_size' => 1, 'price' => 12000, 'cost' => 9500],
                        ['level_name' => 'dozen', 'level_order' => 2, 'unit_size' => 10, 'price' => 115000, 'cost' => 92000],
                        ['level_name' => 'carton', 'level_order' => 3, 'unit_size' => 50, 'price' => 560000, 'cost' => 450000],
                    ],
                ],
            ];

            foreach ($products as $productData) {
                $product = Product::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'sku' => $productData['product']['sku'],
                    ],
                    array_merge($productData['product'], [
                        'tenant_id' => $tenant->id,
                        'description' => "Quality product: {$productData['product']['name']}",
                        'tax_rate' => 0,
                        'min_stock' => 20,
                        'max_stock' => 500,
                        'track_inventory' => true,
                        'active' => true,
                    ])
                );

                $this->createOrUpdatePriceLevels($product, $productData['levels']);
            }

            // Create some products with 4 price levels
            $luxuryProduct = Product::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'sku' => 'LUX-WHN-001',
                ],
                [
                    'name' => 'Luxury Whiskey 700ml',
                    'description' => 'Premium aged whiskey',
                    'price' => 750000,
                    'cost' => 500000,
                    'tax_rate' => 0,
                    'unit' => 'bottle',
                    'min_stock' => 5,
                    'max_stock' => 100,
                    'track_inventory' => true,
                    'active' => true,
                ]
            );

            $this->createOrUpdatePriceLevels($luxuryProduct, [
                ['level_name' => 'bottle', 'level_order' => 1, 'unit_size' => 1, 'price' => 750000, 'cost' => 500000],
                ['level_name' => 'pair', 'level_order' => 2, 'unit_size' => 2, 'price' => 1450000, 'cost' => 980000],
                ['level_name' => 'half_case', 'level_order' => 3, 'unit_size' => 6, 'price' => 4200000, 'cost' => 2850000],
                ['level_name' => 'case', 'level_order' => 4, 'unit_size' => 12, 'price' => 8100000, 'cost' => 5500000],
            ]);
        }
    }

    /**
     * Create or update price levels for a product.
     *
     * @param  Product  $product  The product to attach price levels to
     * @param  array  $levels  Array of price level data
     */
    private function createOrUpdatePriceLevels(Product $product, array $levels): void
    {
        foreach ($levels as $levelData) {
            ProductPriceLevel::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'level_order' => $levelData['level_order'],
                ],
                array_merge($levelData, [
                    'tenant_id' => $product->tenant_id,
                ])
            );
        }
    }
}
