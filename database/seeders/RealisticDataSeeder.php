<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Realistic Data Seeder.
 *
 * Creates real-world business scenarios for each tenant tier:
 * - Starter: Small retail shop, single warehouse, limited products, basic operations
 * - Professional: Growing business, multiple stores, moderate inventory, active orders
 * - Enterprise: Large operation, multiple warehouses/stores, complex inventory, high order volume
 */
class RealisticDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // Seed reference data first (currencies, roles, permissions, pricing tiers)
            $this->seedReferenceData();

            // Seed tier-specific tenants
            $this->seedStarterTenant();
            $this->seedProfessionalTenant();
            $this->seedEnterpriseTenant();

            // Assign roles to users after all tenants and users are created
            $this->command->call(UserRoleSeeder::class);
        });
    }

    /**
     * Seed reference data (currencies, roles, permissions, pricing tiers).
     */
    private function seedReferenceData(): void
    {
        echo "Seeding reference data...\n";

        // Seed currencies
        $this->command->call(CurrencySeeder::class);

        // Seed roles and permissions
        $this->command->call(RolePermissionSeeder::class);

        // Seed pricing tiers
        $this->command->call(PricingTierSeeder::class);

        // Seed pricing rules
        $this->command->call(PricingRuleSeeder::class);

        echo "  ✓ Reference data seeded\n";
    }

    /**
     * Seed a starter tier tenant - small retail shop
     * Simulates: Toko Kelontong / Minimarket kecil.
     */
    private function seedStarterTenant(): void
    {
        echo "  Seeding Starter Tier Tenant (Toko Sembako Jaya)...\n";

        // Create tenant
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'toko-sembako-jaya'],
            [
                'name' => 'Toko Sembako Jaya',
                'company_name' => 'UD Sembako Jaya',
                'email' => 'admin@tokosembako.com',
                'phone' => '+62-274-5551234',
                'address' => 'Jl. Malioboro No. 45',
                'city' => 'Yogyakarta',
                'state' => 'DI Yogyakarta',
                'country' => 'Indonesia',
                'postal_code' => '55271',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'status' => 'active',
                'subscription_plan' => 'starter',
                'settings' => ['theme' => 'light', 'notifications' => true, 'low_stock_threshold' => 10],
                'trial_ends_at' => now()->addDays(15),
                'subscription_ends_at' => null,
            ]
        );

        // Create simple warehouse (single location)
        $warehouse = Warehouse::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'WH-SMJ-001'],
            [
                'name' => 'Gudang Utama Sembako Jaya',
                'address' => 'Jl. Malioboro No. 45A',
                'city' => 'Yogyakarta',
                'state' => 'DI Yogyakarta',
                'country' => 'Indonesia',
                'postal_code' => '55271',
                'phone' => '+62-274-5551234',
                'email' => 'gudang@tokosembako.com',
                'latitude' => -7.7956,
                'longitude' => 110.3695,
                'settings' => ['capacity' => 1000, 'currency' => 'IDR'],
                'active' => true,
            ]
        );

        // Create single retail store
        $store = Store::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'ST-SMJ-001'],
            [
                'name' => 'Toko Sembako Jaya - Cabang Utama',
                'address' => 'Jl. Malioboro No. 45',
                'city' => 'Yogyakarta',
                'state' => 'DI Yogyakarta',
                'country' => 'Indonesia',
                'postal_code' => '55271',
                'phone' => '+62-274-5551234',
                'email' => 'toko@tokosembako.com',
                'settings' => ['tax_rate' => 0.0, 'currency' => 'IDR'],
                'active' => true,
            ]
        );

        // Create basic categories for sembako
        $categories = $this->createSembakoCategories($tenant);

        // Create essential products (50 SKUs - typical for small toko)
        $products = $this->createSembakoProducts($tenant, $categories, $warehouse, 50);

        // Create local customers (30 customers - neighborhood buyers)
        $customers = $this->createLocalCustomers($tenant, 30);

        // Create realistic order history (3 months, 5-10 orders per day)
        $this->createRetailOrders($tenant, $store, $warehouse, $customers, $products, 90, 8);

        // Create users for this tenant
        $this->createTenantUsers($tenant, 'starter');

        echo "    ✓ Created starter tenant with {$products->count()} products, {$customers->count()} customers\n";
    }

    /**
     * Seed a professional tier tenant - growing retail chain
     * Simulates: Toko elektronik dengan beberapa cabang.
     */
    private function seedProfessionalTenant(): void
    {
        echo "  Seeding Professional Tier Tenant (Elektronik Nusantara)...\n";

        // Create tenant
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'elektronik-nusantara'],
            [
                'name' => 'Elektronik Nusantara',
                'company_name' => 'CV Elektronik Nusantara',
                'email' => 'admin@elektroniknusantara.co.id',
                'phone' => '+62-21-5552000',
                'address' => 'Jl. Gatot Subroto No. 88',
                'city' => 'Jakarta Selatan',
                'state' => 'DKI Jakarta',
                'country' => 'Indonesia',
                'postal_code' => '12720',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'status' => 'active',
                'subscription_plan' => 'professional',
                'settings' => ['theme' => 'dark', 'notifications' => true, 'low_stock_threshold' => 15],
                'trial_ends_at' => null,
                'subscription_ends_at' => now()->addMonths(8),
            ]
        );

        // Create main warehouse
        $mainWarehouse = Warehouse::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'WH-EN-001'],
            [
                'name' => 'Gudang Pusat Elektronik Nusantara',
                'address' => 'Kawasan Industri Pulogadung',
                'city' => 'Jakarta Timur',
                'state' => 'DKI Jakarta',
                'country' => 'Indonesia',
                'postal_code' => '13930',
                'phone' => '+62-21-5552001',
                'email' => 'gudang@elektroniknusantara.co.id',
                'latitude' => -6.1751,
                'longitude' => 106.8650,
                'settings' => ['capacity' => 5000, 'currency' => 'IDR'],
                'active' => true,
            ]
        );

        // Create secondary warehouse
        $warehouse2 = Warehouse::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'WH-EN-002'],
            [
                'name' => 'Gudang Tangerang',
                'address' => 'Kawasan Industri GIIC',
                'city' => 'Tangerang',
                'state' => 'Banten',
                'country' => 'Indonesia',
                'postal_code' => '15710',
                'phone' => '+62-21-5552002',
                'email' => 'tangerang@elektroniknusantara.co.id',
                'latitude' => -6.2297,
                'longitude' => 106.5717,
                'settings' => ['capacity' => 3000, 'currency' => 'IDR'],
                'active' => true,
            ]
        );

        // Create 3 retail stores
        $stores = [];
        $storeLocations = [
            ['name' => 'Elektronik Nusantara - Senayan', 'city' => 'Jakarta Selatan', 'code' => 'ST-EN-001'],
            ['name' => 'Elektronik Nusantara - Kelapa Gading', 'city' => 'Jakarta Utara', 'code' => 'ST-EN-002'],
            ['name' => 'Elektronik Nusantara - Pondok Indah', 'city' => 'Jakarta Selatan', 'code' => 'ST-EN-003'],
        ];

        foreach ($storeLocations as $location) {
            $stores[] = Store::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $location['code']],
                [
                    'name' => $location['name'],
                    'address' => 'Jl. Raya ' . $location['city'] . ' No. ' . fake()->numberBetween(1, 200),
                    'city' => $location['city'],
                    'state' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                    'postal_code' => fake()->numerify('#####'),
                    'phone' => '+62-21-555' . fake()->numberBetween(2000, 2999),
                    'email' => strtolower(str_replace(' ', '.', $location['name'])) . '@elektroniknusantara.co.id',
                    'settings' => ['tax_rate' => 0.11, 'currency' => 'IDR'],
                    'active' => true,
                ]
            );
        }

        // Create electronics categories
        $categories = $this->createElectronicsCategories($tenant);

        // Create electronics products (200 SKUs)
        $products = $this->createElectronicsProducts($tenant, $categories, $mainWarehouse, 200);

        // Create B2B and B2C customers (100 customers)
        $customers = $this->createMixedCustomers($tenant, 100);

        // Create realistic order history (90 days, ~15 orders per day for demo purposes)
        // Note: In production, this would be much higher, but for demo we keep it manageable
        $this->createRetailOrders($tenant, $stores[0], $mainWarehouse, $customers, $products, 90, 15);

        // Create users for this tenant
        $this->createTenantUsers($tenant, 'professional');

        echo "    ✓ Created professional tenant with {$products->count()} products, {$customers->count()} customers, " . count($stores) . " stores\n";
    }

    /**
     * Seed an enterprise tier tenant - large distribution company
     * Simulates: Distributor nasional dengan operasi kompleks.
     */
    private function seedEnterpriseTenant(): void
    {
        echo "  Seeding Enterprise Tier Tenant (Sumber Makmur Jaya - Distributor Nasional)...\n";

        // Create tenant (already exists in TenantSeeder, update if needed)
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'sumber-makmur-jaya'],
            [
                'name' => 'PT Sumber Makmur Jaya',
                'company_name' => 'PT Sumber Makmur Jaya',
                'email' => 'admin@makmurjaya.co.id',
                'phone' => '+62-21-5550100',
                'address' => 'Jl. Jend. Sudirman Kav. 52',
                'city' => 'Jakarta Pusat',
                'state' => 'DKI Jakarta',
                'country' => 'Indonesia',
                'postal_code' => '10220',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'status' => 'active',
                'subscription_plan' => 'enterprise',
                'settings' => ['theme' => 'light', 'notifications' => true, 'low_stock_threshold' => 20, 'enable_fifo' => true],
                'trial_ends_at' => null,
                'subscription_ends_at' => now()->addYear(),
            ]
        );

        // Update tenant subscription plan if needed
        if ($tenant->subscription_plan !== 'enterprise') {
            $tenant->update(['subscription_plan' => 'enterprise']);
        }

        // Create multiple warehouses across Indonesia
        $warehouses = [];
        $warehouseLocations = [
            ['code' => 'WH-SMJ-JKT-001', 'name' => 'Gudang Pusat Jakarta', 'city' => 'Jakarta', 'lat' => -6.2088, 'lng' => 106.8456, 'capacity' => 20000],
            ['code' => 'WH-SMJ-SBY-001', 'name' => 'Gudang Surabaya', 'city' => 'Surabaya', 'lat' => -7.2575, 'lng' => 112.7521, 'capacity' => 15000],
            ['code' => 'WH-SMJ-MDN-001', 'name' => 'Gudang Medan', 'city' => 'Medan', 'lat' => 3.5952, 'lng' => 98.6722, 'capacity' => 10000],
            ['code' => 'WH-SMJ-MKS-001', 'name' => 'Gudang Makassar', 'city' => 'Makassar', 'lat' => -5.1477, 'lng' => 119.4327, 'capacity' => 8000],
        ];

        foreach ($warehouseLocations as $location) {
            $warehouses[] = Warehouse::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $location['code']],
                [
                    'name' => $location['name'],
                    'address' => 'Kawasan Industri ' . $location['city'],
                    'city' => $location['city'],
                    'state' => $this->getStateForCity($location['city']),
                    'country' => 'Indonesia',
                    'postal_code' => fake()->numerify('#####'),
                    'phone' => '+62-' . fake()->numerify('###-####'),
                    'email' => strtolower(str_replace(' ', '.', $location['name'])) . '@makmurjaya.co.id',
                    'latitude' => $location['lat'],
                    'longitude' => $location['lng'],
                    'settings' => ['capacity' => $location['capacity'], 'currency' => 'IDR'],
                    'active' => true,
                ]
            );
        }

        // Create multiple stores across Indonesia
        $stores = [];
        $storeCities = [
            'Jakarta Pusat', 'Jakarta Selatan', 'Jakarta Barat', 'Jakarta Timur', 'Jakarta Utara',
            'Bandung', 'Surabaya', 'Yogyakarta', 'Semarang', 'Medan', 'Makassar', 'Denpasar',
            'Palembang', 'Balikpapan', 'Pontianak', 'Manado',
        ];

        foreach ($storeCities as $city) {
            $storeCode = 'ST-SMJ-' . strtoupper(substr(str_replace(' ', '', $city), 0, 3)) . '-' . str_pad(count($stores) + 1, 3, '0', STR_PAD_LEFT);
            $stores[] = Store::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $storeCode],
                [
                    'name' => 'SMJ Cabang ' . $city,
                    'address' => 'Jl. Raya ' . $city . ' No. ' . fake()->numberBetween(1, 500),
                    'city' => $city,
                    'state' => $this->getStateForCity($city),
                    'country' => 'Indonesia',
                    'postal_code' => fake()->numerify('#####'),
                    'phone' => '+62-' . fake()->numerify('###-####'),
                    'email' => strtolower(str_replace(' ', '', $city)) . '@makmurjaya.co.id',
                    'settings' => ['tax_rate' => 0.11, 'currency' => 'IDR'],
                    'active' => true,
                ]
            );
        }

        // Create comprehensive categories
        $categories = $this->createComprehensiveCategories($tenant);

        // Create diverse products (100 SKUs across multiple categories - reduced for demo performance)
        $products = $this->createDiverseProducts($tenant, $categories, $warehouses[0], 100);

        // Create B2B wholesale customers and retail customers (100 customers)
        $customers = $this->createEnterpriseCustomers($tenant, 100);

        // Create realistic order history (30 days, ~20 orders per day for demo purposes)
        // Note: In production, this would be much higher, but for demo we keep it manageable
        $this->createEnterpriseOrders($tenant, $stores, $warehouses, $customers, $products, 30, 20);

        // Create users for this tenant with full hierarchy
        $this->createTenantUsers($tenant, 'enterprise');

        echo "    ✓ Created enterprise tenant with {$products->count()} products, {$customers->count()} customers, " . count($stores) . ' stores, ' . count($warehouses) . " warehouses\n";
    }

    /**
     * Create categories for sembako (grocery) products.
     */
    private function createSembakoCategories(Tenant $tenant): array
    {
        $categories = [];
        $categoryData = [
            ['name' => 'Beras & Mie', 'sub' => ['Beras', 'Mie Instan', 'Mie Kering']],
            ['name' => 'Minyak & Bumbu', 'sub' => ['Minyak Goreng', 'Kecap', 'Saus', 'Bumbu Dapur']],
            ['name' => 'Minuman', 'sub' => ['Teh', 'Kopi', 'Susu', 'Air Mineral', 'Minuman Ringan']],
            ['name' => 'Makanan Ringan', 'sub' => ['Kerupuk', 'Biskuit', 'Coklat', 'Snack']],
            ['name' => 'Kebutuhan Pokok', 'sub' => ['Gula', 'Garam', 'Tepung', 'Telur']],
        ];

        foreach ($categoryData as $parent) {
            $parentSlug = $tenant->slug . '-' . Str::slug($parent['name']);
            $parentCat = Category::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $parentSlug],
                [
                    'name' => $parent['name'],
                    'description' => "Kategori {$parent['name']}",
                    'parent_id' => null,
                    'sort_order' => 1,
                    'active' => true,
                ]
            );
            $categories[] = $parentCat;

            foreach ($parent['sub'] as $sub) {
                $subSlug = $tenant->slug . '-' . Str::slug($sub);
                Category::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => $subSlug],
                    [
                        'name' => $sub,
                        'description' => "Produk {$sub}",
                        'parent_id' => $parentCat->id,
                        'sort_order' => 1,
                        'active' => true,
                    ]
                );
            }
        }

        return $categories;
    }

    /**
     * Create categories for electronics products.
     */
    private function createElectronicsCategories(Tenant $tenant): array
    {
        $categories = [];
        $categoryData = [
            ['name' => 'Smartphone & Tablet', 'sub' => ['Smartphone', 'Tablet', 'Aksesoris HP']],
            ['name' => 'Komputer & Laptop', 'sub' => ['Laptop', 'Desktop', 'Komponen PC', 'Aksesoris Komputer']],
            ['name' => 'Audio & Video', 'sub' => ['Headphone', 'Speaker', 'Smart TV', 'Kamera']],
            ['name' => 'Elektronik Rumah', 'sub' => ['AC', 'Kulkas', 'Mesin Cuci', 'Microwave']],
            ['name' => 'Gaming', 'sub' => ['Console', 'Game', 'Aksesoris Gaming']],
        ];

        foreach ($categoryData as $parent) {
            $parentSlug = $tenant->slug . '-' . Str::slug($parent['name']);
            $parentCat = Category::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $parentSlug],
                [
                    'name' => $parent['name'],
                    'description' => "Kategori {$parent['name']}",
                    'parent_id' => null,
                    'sort_order' => 1,
                    'active' => true,
                ]
            );
            $categories[] = $parentCat;

            foreach ($parent['sub'] as $sub) {
                $subSlug = $tenant->slug . '-' . Str::slug($sub);
                Category::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => $subSlug],
                    [
                        'name' => $sub,
                        'description' => "Produk {$sub}",
                        'parent_id' => $parentCat->id,
                        'sort_order' => 1,
                        'active' => true,
                    ]
                );
            }
        }

        return $categories;
    }

    /**
     * Create comprehensive categories for enterprise.
     */
    private function createComprehensiveCategories(Tenant $tenant): array
    {
        $categories = [];
        $categoryData = [
            ['name' => 'Elektronik', 'sub' => ['Smartphone', 'Laptop', 'Audio', 'Kamera', 'Aksesoris']],
            ['name' => 'Fashion', 'sub' => ['Pria', 'Wanita', 'Anak', 'Sepatu', 'Tas']],
            ['name' => 'Rumah Tangga', 'sub' => ['Furniture', 'Dekorasi', 'Dapur', 'Kamar Mandi']],
            ['name' => 'Kesehatan & Kecantikan', 'sub' => ['Skincare', 'Makeup', 'Suplemen', 'Alat Kesehatan']],
            ['name' => 'Olahraga', 'sub' => ['Fitness', 'Sepeda', 'Outdoor', 'Sepatu Olahraga']],
            ['name' => 'Otomotif', 'sub' => ['Aksesoris Mobil', 'Aksesoris Motor', 'Oli', 'Ban']],
            ['name' => 'Ibu & Anak', 'sub' => ['Popok', 'Makanan Bayi', 'Mainan', 'Pakaian Anak']],
            ['name' => 'Makanan & Minuman', 'sub' => ['Sembako', 'Snack', 'Minuman', 'Makanan Kaleng']],
        ];

        foreach ($categoryData as $parent) {
            $parentSlug = $tenant->slug . '-' . Str::slug($parent['name']);
            $parentCat = Category::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $parentSlug],
                [
                    'name' => $parent['name'],
                    'description' => "Kategori {$parent['name']}",
                    'parent_id' => null,
                    'sort_order' => 1,
                    'active' => true,
                ]
            );
            $categories[] = $parentCat;

            foreach ($parent['sub'] as $sub) {
                $subSlug = $tenant->slug . '-' . Str::slug($sub);
                Category::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => $subSlug],
                    [
                        'name' => $sub,
                        'description' => "Produk {$sub}",
                        'parent_id' => $parentCat->id,
                        'sort_order' => 1,
                        'active' => true,
                    ]
                );
            }
        }

        return $categories;
    }

    /**
     * Create sembako products for starter tenant.
     */
    private function createSembakoProducts(Tenant $tenant, array $categories, Warehouse $warehouse, int $count): \Illuminate\Support\Collection
    {
        $products = collect();
        $productData = [
            ['name' => 'Beras Premium 5kg', 'sku' => 'BRP-001', 'price' => 65000, 'cost' => 55000, 'category' => 'Beras'],
            ['name' => 'Beras Medium 5kg', 'sku' => 'BRM-002', 'price' => 55000, 'cost' => 48000, 'category' => 'Beras'],
            ['name' => 'Mie Instan Goreng (Dus 40)', 'sku' => 'MIG-003', 'price' => 110000, 'cost' => 95000, 'category' => 'Mie'],
            ['name' => 'Mie Instan Kuah (Dus 40)', 'sku' => 'MIK-004', 'price' => 108000, 'cost' => 93000, 'category' => 'Mie'],
            ['name' => 'Minyak Goreng 2L', 'sku' => 'MYG-005', 'price' => 38000, 'cost' => 32000, 'category' => 'Minyak'],
            ['name' => 'Minyak Goreng 1L', 'sku' => 'MYG-006', 'price' => 22000, 'cost' => 18000, 'category' => 'Minyak'],
            ['name' => 'Gula Pasir 1kg', 'sku' => 'GPP-007', 'price' => 14000, 'cost' => 12000, 'category' => 'Gula'],
            ['name' => 'Gula Pasir 5kg', 'sku' => 'GPP-008', 'price' => 65000, 'cost' => 58000, 'category' => 'Gula'],
            ['name' => 'Teh Celup (Box 25)', 'sku' => 'THC-009', 'price' => 8000, 'cost' => 6000, 'category' => 'Teh'],
            ['name' => 'Kopi Instan (Box 10)', 'sku' => 'KPI-010', 'price' => 25000, 'cost' => 20000, 'category' => 'Kopi'],
            ['name' => 'Susu Kental Manis 400g', 'sku' => 'SKM-011', 'price' => 18000, 'cost' => 15000, 'category' => 'Susu'],
            ['name' => 'Susu UHT 1L', 'sku' => 'SUH-012', 'price' => 16000, 'cost' => 13000, 'category' => 'Susu'],
            ['name' => 'Kecap Manis 620ml', 'sku' => 'KCM-013', 'price' => 20000, 'cost' => 16000, 'category' => 'Kecap'],
            ['name' => 'Kecap Manis 320ml', 'sku' => 'KCM-014', 'price' => 12000, 'cost' => 9500, 'category' => 'Kecap'],
            ['name' => 'Saus Sambal 350ml', 'sku' => 'SSB-015', 'price' => 15000, 'cost' => 12000, 'category' => 'Saus'],
            ['name' => 'Saus Tiram 350ml', 'sku' => 'STM-016', 'price' => 16000, 'cost' => 13000, 'category' => 'Saus'],
            ['name' => 'Garam Dapur 500g', 'sku' => 'GMD-017', 'price' => 5000, 'cost' => 3500, 'category' => 'Garam'],
            ['name' => 'Tepung Terigu 1kg', 'sku' => 'TPT-018', 'price' => 12000, 'cost' => 9500, 'category' => 'Tepung'],
            ['name' => 'Telur Ayam 1kg', 'sku' => 'TLY-019', 'price' => 28000, 'cost' => 25000, 'category' => 'Telur'],
            ['name' => 'Air Mineral 600ml (Dus 24)', 'sku' => 'ARM-020', 'price' => 48000, 'cost' => 38000, 'category' => 'Air'],
        ];

        foreach ($productData as $data) {
            $category = collect($categories)->firstWhere('slug', Str::slug($data['category'])) ?? $categories[0];

            $product = Product::firstOrCreate(
                ['tenant_id' => $tenant->id, 'sku' => $data['sku']],
                [
                    'name' => $data['name'],
                    'category_id' => $category->id,
                    'description' => "{$data['name']} berkualitas untuk kebutuhan sehari-hari",
                    'price' => $data['price'],
                    'cost' => $data['cost'],
                    'tax_rate' => 0,
                    'unit' => 'pack',
                    'min_stock' => 20,
                    'max_stock' => 500,
                    'track_inventory' => true,
                    'active' => true,
                ]
            );

            // Create inventory
            Inventory::firstOrCreate(
                ['tenant_id' => $tenant->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                [
                    'quantity' => fake()->numberBetween(50, 200),
                    'reserved' => 0,
                    'available' => fake()->numberBetween(50, 200),
                    'cost' => $data['cost'],
                    'notes' => 'Initial stock',
                ]
            );

            $products->push($product);
        }

        return $products;
    }

    /**
     * Create electronics products for professional tenant.
     */
    private function createElectronicsProducts(Tenant $tenant, array $categories, Warehouse $warehouse, int $count): \Illuminate\Support\Collection
    {
        $products = collect();
        $productData = [
            ['name' => 'Samsung Galaxy A54 5G', 'sku' => 'SGA-001', 'price' => 5999000, 'cost' => 5200000, 'category' => 'Smartphone'],
            ['name' => 'Xiaomi Redmi Note 12', 'sku' => 'XRN-002', 'price' => 2499000, 'cost' => 2100000, 'category' => 'Smartphone'],
            ['name' => 'OPPO Reno8 T', 'sku' => 'OPR-003', 'price' => 3999000, 'cost' => 3500000, 'category' => 'Smartphone'],
            ['name' => 'iPhone 14 128GB', 'sku' => 'IP14-004', 'price' => 13999000, 'cost' => 12500000, 'category' => 'Smartphone'],
            ['name' => 'iPad 10.2 WiFi 64GB', 'sku' => 'IPD-005', 'price' => 5499000, 'cost' => 4800000, 'category' => 'Tablet'],
            ['name' => 'Samsung Tab A8', 'sku' => 'STA-006', 'price' => 2799000, 'cost' => 2400000, 'category' => 'Tablet'],
            ['name' => 'ASUS Vivobook 14', 'sku' => 'AVB-007', 'price' => 6499000, 'cost' => 5800000, 'category' => 'Laptop'],
            ['name' => 'Lenovo IdeaPad Slim 3', 'sku' => 'LIS-008', 'price' => 5299000, 'cost' => 4700000, 'category' => 'Laptop'],
            ['name' => 'HP 14s', 'sku' => 'HP14-009', 'price' => 5799000, 'cost' => 5200000, 'category' => 'Laptop'],
            ['name' => 'MacBook Air M1', 'sku' => 'MBA-010', 'price' => 14999000, 'cost' => 13500000, 'category' => 'Laptop'],
            ['name' => 'Sony WH-1000XM5', 'sku' => 'SWH-011', 'price' => 4999000, 'cost' => 4200000, 'category' => 'Headphone'],
            ['name' => 'AirPods Pro 2', 'sku' => 'APP-012', 'price' => 3799000, 'cost' => 3200000, 'category' => 'Headphone'],
            ['name' => 'JBL Flip 6', 'sku' => 'JFL-013', 'price' => 1799000, 'cost' => 1400000, 'category' => 'Speaker'],
            ['name' => 'Samsung 43" Crystal UHD', 'sku' => 'S43-014', 'price' => 4999000, 'cost' => 4300000, 'category' => 'Smart TV'],
            ['name' => 'LG 50" 4K UHD', 'sku' => 'LG50-015', 'price' => 6499000, 'cost' => 5700000, 'category' => 'Smart TV'],
            ['name' => 'Canon EOS R50', 'sku' => 'CER-016', 'price' => 10999000, 'cost' => 9500000, 'category' => 'Kamera'],
            ['name' => 'Sony A6400', 'sku' => 'SA6-017', 'price' => 13999000, 'cost' => 12200000, 'category' => 'Kamera'],
            ['name' => 'PlayStation 5', 'sku' => 'PS5-018', 'price' => 8999000, 'cost' => 7800000, 'category' => 'Console'],
            ['name' => 'Xbox Series X', 'sku' => 'XBX-019', 'price' => 7999000, 'cost' => 7000000, 'category' => 'Console'],
            ['name' => 'Nintendo Switch OLED', 'sku' => 'NSO-020', 'price' => 5499000, 'cost' => 4800000, 'category' => 'Console'],
        ];

        foreach ($productData as $data) {
            $category = collect($categories)->firstWhere('slug', Str::slug($data['category'])) ?? $categories[0];

            $product = Product::firstOrCreate(
                ['tenant_id' => $tenant->id, 'sku' => $data['sku']],
                [
                    'name' => $data['name'],
                    'category_id' => $category->id,
                    'description' => "{$data['name']} - Produk elektronik berkualitas tinggi",
                    'price' => $data['price'],
                    'cost' => $data['cost'],
                    'tax_rate' => 11,
                    'unit' => 'piece',
                    'min_stock' => 5,
                    'max_stock' => 100,
                    'track_inventory' => true,
                    'active' => true,
                ]
            );

            // Create inventory with FIFO batches
            $quantity = fake()->numberBetween(10, 50);
            Inventory::firstOrCreate(
                ['tenant_id' => $tenant->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                [
                    'quantity' => $quantity,
                    'reserved' => fake()->numberBetween(0, 5),
                    'available' => $quantity,
                    'cost' => $data['cost'],
                    'notes' => 'Initial stock',
                ]
            );

            // Create inventory batch for FIFO tracking
            InventoryBatch::create([
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'batch_number' => 'BATCH-' . $data['sku'] . '-' . now()->format('Ymd'),
                'lot_number' => 'LOT-' . fake()->numerify('######'),
                'received_date' => now()->subDays(fake()->numberBetween(1, 30)),
                'expiry_date' => null,
                'unit_cost' => $data['cost'],
                'initial_quantity' => $quantity,
                'remaining_quantity' => $quantity,
                'status' => 'active',
                'notes' => 'Initial batch',
            ]);

            $products->push($product);
        }

        return $products;
    }

    /**
     * Create diverse products for enterprise tenant.
     */
    private function createDiverseProducts(Tenant $tenant, array $categories, Warehouse $warehouse, int $count): \Illuminate\Support\Collection
    {
        $products = collect();

        // Mix of products across all categories
        $productTemplates = [
            ['name' => 'Samsung Galaxy S23 Ultra', 'sku' => 'SGS-001', 'price' => 19999000, 'cost' => 17500000, 'category' => 'Smartphone'],
            ['name' => 'iPhone 15 Pro Max', 'sku' => 'IPM-002', 'price' => 24999000, 'cost' => 22000000, 'category' => 'Smartphone'],
            ['name' => 'MacBook Pro 14" M3', 'sku' => 'MBP-003', 'price' => 29999000, 'cost' => 26500000, 'category' => 'Laptop'],
            ['name' => 'Sony A7 IV', 'sku' => 'SA7-004', 'price' => 32999000, 'cost' => 29000000, 'category' => 'Kamera'],
            ['name' => 'Dyson V15 Detect', 'sku' => 'DYV-005', 'price' => 11999000, 'cost' => 10200000, 'category' => 'Elektronik Rumah'],
            ['name' => 'LG Side by Side Refrigerator', 'sku' => 'LGS-006', 'price' => 18999000, 'cost' => 16500000, 'category' => 'Elektronik Rumah'],
            ['name' => 'Nike Air Max 270', 'sku' => 'NAM-007', 'price' => 2499000, 'cost' => 1800000, 'category' => 'Sepatu'],
            ['name' => 'Adidas Ultraboost 22', 'sku' => 'ADU-008', 'price' => 2799000, 'cost' => 2100000, 'category' => 'Sepatu'],
            ['name' => 'IKEA POÄNG Chair', 'sku' => 'IKP-009', 'price' => 1999000, 'cost' => 1500000, 'category' => 'Furniture'],
            ['name' => 'Philips Air Fryer XXL', 'sku' => 'PHA-010', 'price' => 3999000, 'cost' => 3400000, 'category' => 'Dapur'],
        ];

        foreach ($productTemplates as $data) {
            $category = collect($categories)->firstWhere('slug', Str::slug($data['category'])) ?? $categories[0];

            $product = Product::firstOrCreate(
                ['tenant_id' => $tenant->id, 'sku' => $data['sku']],
                [
                    'name' => $data['name'],
                    'category_id' => $category->id,
                    'description' => "{$data['name']} - Premium quality product",
                    'price' => $data['price'],
                    'cost' => $data['cost'],
                    'tax_rate' => 11,
                    'unit' => 'piece',
                    'min_stock' => 10,
                    'max_stock' => 200,
                    'track_inventory' => true,
                    'active' => true,
                ]
            );

            // Create inventory across multiple warehouses
            foreach ($tenant->warehouses as $wh) {
                $quantity = fake()->numberBetween(20, 100);
                Inventory::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'product_id' => $product->id, 'warehouse_id' => $wh->id],
                    [
                        'quantity' => $quantity,
                        'reserved' => fake()->numberBetween(0, 10),
                        'available' => $quantity,
                        'cost' => $data['cost'],
                        'notes' => 'Stock at ' . $wh->city,
                    ]
                );
            }

            $products->push($product);
        }

        return $products;
    }

    /**
     * Create local customers for starter tenant.
     */
    private function createLocalCustomers(Tenant $tenant, int $count): \Illuminate\Support\Collection
    {
        $customers = collect();
        $names = [
            'Budi Santoso', 'Siti Aminah', 'Ahmad Hidayat', 'Dewi Lestari', 'Eko Prasetyo',
            'Rina Wijaya', 'Hendra Gunawan', 'Maya Kusuma', 'Agus Setiawan', 'Fitri Handayani',
            'Joko Susilo', 'Ratna Sari', 'Doni Pratama', 'Indah Permata', 'Rudi Hartono',
        ];

        $cities = ['Yogyakarta', 'Sleman', 'Bantul', 'Klaten', 'Magelang'];

        for ($i = 0; $i < $count; $i++) {
            $name = $names[array_rand($names)] . ' ' . ($i + 1);
            $email = strtolower(str_replace(' ', '.', $name)) . '@email.com';

            $customer = Customer::firstOrCreate(
                ['tenant_id' => $tenant->id, 'email' => $email],
                [
                    'name' => $name,
                    'phone' => '+62-8' . fake()->numerify('##-####-####'),
                    'company' => null,
                    'tax_id' => null,
                    'address' => fake()->address(),
                    'city' => fake()->randomElement($cities),
                    'state' => 'DI Yogyakarta',
                    'country' => 'Indonesia',
                    'postal_code' => fake()->numerify('#####'),
                    'pricing_tier_id' => null,
                    'credit_limit' => 0,
                    'balance' => 0,
                    'settings' => ['newsletter' => false, 'preferred_contact' => 'whatsapp'],
                    'active' => true,
                ]
            );

            $customers->push($customer);
        }

        return $customers;
    }

    /**
     * Create mixed B2B and B2C customers for professional tenant.
     */
    private function createMixedCustomers(Tenant $tenant, int $count): \Illuminate\Support\Collection
    {
        $customers = collect();
        $pricingTiers = $tenant->pricingTiers;

        $individualNames = [
            'Andi Wijaya', 'Ratna Sari', 'Doni Pratama', 'Indah Permata', 'Rudi Hartono',
            'Lina Marlina', 'Joko Susilo', 'Nina Zulkarnaen', 'Bayu Aji', 'Dian Purnama',
        ];

        $companyNames = [
            'PT Maju Jaya', 'CV Berkah Sentosa', 'UD Sumber Rejeki', 'PT Teknologi Nusantara',
            'CV Digital Solutions', 'UD Mitra Abadi', 'PT Retail Indonesia', 'CV Sumber Makmur',
        ];

        for ($i = 0; $i < $count; $i++) {
            $isBusiness = fake()->boolean(40);
            $tier = fake()->randomElement($pricingTiers);

            if ($isBusiness) {
                $company = fake()->randomElement($companyNames);
                $name = $company;
                $email = strtolower(str_replace(' ', '.', $company)) . $i . '@company.com';
            } else {
                $name = fake()->randomElement($individualNames) . ' ' . ($i + 1);
                $email = strtolower(str_replace(' ', '.', $name)) . '@email.com';
            }

            $customer = Customer::firstOrCreate(
                ['tenant_id' => $tenant->id, 'email' => $email],
                [
                    'name' => $name,
                    'phone' => '+62-8' . fake()->numerify('##-####-####'),
                    'company' => $isBusiness ? $company : null,
                    'tax_id' => $isBusiness ? fake()->numerify('##.###.###.#-###.###') : null,
                    'address' => fake()->address(),
                    'city' => fake()->randomElement(['Jakarta', 'Tangerang', 'Bekasi', 'Depok', 'Bogor']),
                    'state' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                    'postal_code' => fake()->numerify('#####'),
                    'pricing_tier_id' => $tier?->id,
                    'credit_limit' => $isBusiness ? fake()->randomElement([5000000, 10000000, 25000000]) : 0,
                    'balance' => 0,
                    'settings' => ['newsletter' => true, 'preferred_contact' => 'email'],
                    'active' => true,
                ]
            );

            $customers->push($customer);
        }

        return $customers;
    }

    /**
     * Create enterprise customers (wholesale + retail).
     */
    private function createEnterpriseCustomers(Tenant $tenant, int $count): \Illuminate\Support\Collection
    {
        $customers = collect();
        $pricingTiers = $tenant->pricingTiers;

        $wholesalers = [
            ['name' => 'PT Distributor Nasional', 'city' => 'Jakarta', 'credit' => 100000000],
            ['name' => 'CV Mitra Retail', 'city' => 'Surabaya', 'credit' => 50000000],
            ['name' => 'UD Sumber Jaya', 'city' => 'Bandung', 'credit' => 30000000],
            ['name' => 'PT Retail Indonesia', 'city' => 'Medan', 'credit' => 75000000],
            ['name' => 'CV Berkah Mandiri', 'city' => 'Makassar', 'credit' => 40000000],
        ];

        $retailNames = [
            'Andi Wijaya', 'Ratna Sari', 'Doni Pratama', 'Indah Permata', 'Rudi Hartono',
            'Lina Marlina', 'Joko Susilo', 'Nina Zulkarnaen', 'Bayu Aji', 'Dian Purnama',
        ];

        for ($i = 0; $i < $count; $i++) {
            $isWholesaler = fake()->boolean(20);

            if ($isWholesaler && count($wholesalers) > 0) {
                $wholesaler = array_shift($wholesalers);
                $name = $wholesaler['name'];
                $email = strtolower(str_replace(' ', '.', $name)) . '@company.com';
                $tier = $pricingTiers->where('slug', 'wholesale')->first() ?? $pricingTiers->last();
                $credit = $wholesaler['credit'];
                $city = $wholesaler['city'];
            } else {
                $name = fake()->randomElement($retailNames) . ' ' . ($i + 1);
                $email = strtolower(str_replace(' ', '.', $name)) . $i . '@email.com';
                $tier = fake()->randomElement($pricingTiers);
                $credit = fake()->randomElement([0, 5000000, 10000000]);
                $city = fake()->randomElement(['Jakarta', 'Surabaya', 'Bandung', 'Medan', 'Makassar', 'Denpasar']);
            }

            $customer = Customer::firstOrCreate(
                ['tenant_id' => $tenant->id, 'email' => $email],
                [
                    'name' => $name,
                    'phone' => '+62-8' . fake()->numerify('##-####-####'),
                    'company' => $isWholesaler ? $name : null,
                    'tax_id' => fake()->numerify('##.###.###.#-###.###'),
                    'address' => fake()->address(),
                    'city' => $city,
                    'state' => $this->getStateForCity($city),
                    'country' => 'Indonesia',
                    'postal_code' => fake()->numerify('#####'),
                    'pricing_tier_id' => $tier?->id,
                    'credit_limit' => $credit,
                    'balance' => 0,
                    'settings' => ['newsletter' => true, 'preferred_contact' => $isWholesaler ? 'email' : 'whatsapp'],
                    'active' => true,
                ]
            );

            $customers->push($customer);
        }

        return $customers;
    }

    /**
     * Create realistic retail orders.
     */
    private function createRetailOrders(
        Tenant $tenant,
        Store $store,
        Warehouse $warehouse,
        \Illuminate\Support\Collection $customers,
        \Illuminate\Support\Collection $products,
        int $daysBack,
        int $avgOrdersPerDay
    ): void {
        echo "    Creating order history ({$daysBack} days, ~{$avgOrdersPerDay} orders/day)...\n";

        $startDate = now()->subDays($daysBack);
        $endDate = now();
        $orderCount = 0;

        for ($date = $startDate; $date <= $endDate; $date->addDay()) {
            // Skip some days for realism (e.g., closed on major holidays)
            if (fake()->boolean(5)) {
                continue;
            }

            // Variable orders per day
            $ordersToday = fake()->numberBetween(
                max(1, $avgOrdersPerDay - 5),
                $avgOrdersPerDay + 5
            );

            for ($i = 0; $i < $ordersToday; $i++) {
                $customer = $customers->random();
                $orderProducts = $products->random(fake()->numberBetween(1, 5));

                $subtotal = 0;
                $items = [];

                foreach ($orderProducts as $product) {
                    $qty = fake()->numberBetween(1, 3);
                    $unitPrice = $product->price;
                    $subtotal += $unitPrice * $qty;
                    $items[] = ['product' => $product, 'qty' => $qty, 'price' => $unitPrice];
                }

                $status = fake()->randomElement(['pending', 'confirmed', 'processing', 'fulfilled', 'cancelled']);
                $orderDate = $date->copy()->addHours(fake()->numberBetween(8, 20));

                $order = Order::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customer->id,
                    'store_id' => $store->id,
                    'warehouse_id' => $warehouse->id,
                    'order_number' => 'ORD-' . $tenant->slug . '-' . strtoupper(fake()->unique()->bothify('???####')),
                    'status' => $status,
                    'type' => 'sale',
                    'subtotal' => $subtotal,
                    'tax' => $subtotal * 0.11,
                    'discount' => fake()->randomElement([0, 0, 0, 5000, 10000]),
                    'shipping' => fake()->randomElement([0, 0, 15000, 25000]),
                    'notes' => fake()->optional(0.2)->sentence(),
                    'shipping_address' => $customer->address,
                    'shipping_city' => $customer->city,
                    'shipping_state' => $customer->state,
                    'shipping_country' => 'Indonesia',
                    'shipping_postal_code' => $customer->postal_code,
                    'created_at' => $orderDate,
                    'updated_at' => $orderDate,
                ]);

                // Create order items
                foreach ($items as $item) {
                    OrderItem::create([
                        'tenant_id' => $tenant->id,
                        'order_id' => $order->id,
                        'product_id' => $item['product']->id,
                        'quantity' => $item['qty'],
                        'unit_price' => $item['price'],
                        'tax' => $item['price'] * $item['qty'] * 0.11,
                        'discount' => 0,
                        'total' => $item['price'] * $item['qty'] * 1.11,
                        'created_at' => $orderDate,
                        'updated_at' => $orderDate,
                    ]);
                }

                // Update order timestamps for fulfilled orders
                if ($status === 'fulfilled') {
                    $order->update([
                        'confirmed_at' => $orderDate->copy()->addMinutes(fake()->numberBetween(5, 30)),
                        'fulfilled_at' => $orderDate->copy()->addHours(fake()->numberBetween(1, 24)),
                    ]);
                }

                $orderCount++;
            }
        }

        echo "      Created {$orderCount} orders\n";
    }

    /**
     * Create enterprise orders with complex scenarios.
     */
    private function createEnterpriseOrders(
        Tenant $tenant,
        array $stores,
        array $warehouses,
        \Illuminate\Support\Collection $customers,
        \Illuminate\Support\Collection $products,
        int $daysBack,
        int $avgOrdersPerDay
    ): void {
        echo "    Creating enterprise order history ({$daysBack} days, ~{$avgOrdersPerDay} orders/day)...\n";

        $startDate = now()->subDays($daysBack);
        $endDate = now();
        $orderCount = 0;
        $fulfilledCount = 0;

        for ($date = $startDate; $date <= $endDate; $date->addDay()) {
            $ordersToday = fake()->numberBetween(
                max(10, $avgOrdersPerDay - 20),
                $avgOrdersPerDay + 20
            );

            for ($i = 0; $i < $ordersToday; $i++) {
                $customer = $customers->random();
                $store = fake()->randomElement($stores);
                $warehouse = fake()->randomElement($warehouses);
                $orderProducts = $products->random(fake()->numberBetween(1, 10));

                $subtotal = 0;
                $items = [];

                foreach ($orderProducts as $product) {
                    $qty = fake()->numberBetween(1, 5);
                    $unitPrice = $product->price;
                    $subtotal += $unitPrice * $qty;
                    $items[] = ['product' => $product, 'qty' => $qty, 'price' => $unitPrice];
                }

                // More realistic status distribution
                $statusRand = fake()->numberBetween(1, 100);
                if ($statusRand <= 5) {
                    $status = 'pending';
                } elseif ($statusRand <= 15) {
                    $status = 'confirmed';
                } elseif ($statusRand <= 25) {
                    $status = 'processing';
                } elseif ($statusRand <= 85) {
                    $status = 'fulfilled';
                } else {
                    $status = 'cancelled';
                }

                $orderDate = $date->copy()->addHours(fake()->numberBetween(6, 22));

                $order = Order::create([
                    'tenant_id' => $tenant->id,
                    'customer_id' => $customer->id,
                    'store_id' => $store->id,
                    'warehouse_id' => $warehouse->id,
                    'order_number' => 'ORD-' . $tenant->slug . '-' . strtoupper(fake()->unique()->bothify('???####')),
                    'status' => $status,
                    'type' => 'sale',
                    'subtotal' => $subtotal,
                    'tax' => $subtotal * 0.11,
                    'discount' => fake()->randomElement([0, 0, 5000, 10000, 25000, 50000]),
                    'shipping' => fake()->randomElement([0, 15000, 25000, 50000, 75000]),
                    'payment_status' => fake()->randomElement(['paid', 'pending', 'unpaid']),
                    'payment_method' => fake()->randomElement(['bank_transfer', 'credit_card', 'cod', 'qris']),
                    'notes' => fake()->optional(0.3)->sentence(),
                    'shipping_address' => $customer->address,
                    'shipping_city' => $customer->city,
                    'shipping_state' => $customer->state,
                    'shipping_country' => 'Indonesia',
                    'shipping_postal_code' => $customer->postal_code,
                    'created_at' => $orderDate,
                    'updated_at' => $orderDate,
                ]);

                foreach ($items as $item) {
                    OrderItem::create([
                        'tenant_id' => $tenant->id,
                        'order_id' => $order->id,
                        'product_id' => $item['product']->id,
                        'quantity' => $item['qty'],
                        'unit_price' => $item['price'],
                        'tax' => $item['price'] * $item['qty'] * 0.11,
                        'discount' => 0,
                        'total' => $item['price'] * $item['qty'] * 1.11,
                        'created_at' => $orderDate,
                        'updated_at' => $orderDate,
                    ]);
                }

                if ($status === 'fulfilled') {
                    $order->update([
                        'confirmed_at' => $orderDate->copy()->addMinutes(fake()->numberBetween(5, 60)),
                        'fulfilled_at' => $orderDate->copy()->addHours(fake()->numberBetween(1, 48)),
                    ]);
                    $fulfilledCount++;
                }

                $orderCount++;
            }
        }

        echo "      Created {$orderCount} orders ({$fulfilledCount} fulfilled)\n";
    }

    /**
     * Create users for tenant based on tier.
     */
    private function createTenantUsers(Tenant $tenant, string $tier): void
    {
        $users = [];

        if ($tier === 'starter') {
            $users = [
                ['name' => 'Admin Sembako', 'email' => 'admin@tokosembako.com', 'role' => 'admin'],
                ['name' => 'Kasir 1', 'email' => 'kasir1@tokosembako.com', 'role' => 'staff'],
            ];
        } elseif ($tier === 'professional') {
            $users = [
                ['name' => 'Admin Elektronik', 'email' => 'admin@elektroniknusantara.co.id', 'role' => 'admin'],
                ['name' => 'Manager Operasional', 'email' => 'manager@elektroniknusantara.co.id', 'role' => 'manager'],
                ['name' => 'Kasir Senayan', 'email' => 'kasir.senayan@elektroniknusantara.co.id', 'role' => 'staff'],
                ['name' => 'Staff Gudang', 'email' => 'gudang@elektroniknusantara.co.id', 'role' => 'warehouse_staff'],
            ];
        } else { // enterprise
            $users = [
                ['name' => 'Super Admin SMJ', 'email' => 'admin@makmurjaya.co.id', 'role' => 'admin'],
                ['name' => 'CEO', 'email' => 'ceo@makmurjaya.co.id', 'role' => 'admin'],
                ['name' => 'Operations Director', 'email' => 'operations@makmurjaya.co.id', 'role' => 'manager'],
                ['name' => 'Warehouse Manager Jakarta', 'email' => 'warehouse.jkt@makmurjaya.co.id', 'role' => 'manager'],
                ['name' => 'Warehouse Manager Surabaya', 'email' => 'warehouse.sby@makmurjaya.co.id', 'role' => 'manager'],
                ['name' => 'Store Manager Jakarta', 'email' => 'store.jkt@makmurjaya.co.id', 'role' => 'manager'],
                ['name' => 'HR Manager', 'email' => 'hr@makmurjaya.co.id', 'role' => 'manager'],
                ['name' => 'Finance Manager', 'email' => 'finance@makmurjaya.co.id', 'role' => 'manager'],
            ];
        }

        foreach ($users as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => bcrypt('password'),
                    'tenant_id' => $tenant->id,
                    'role' => $userData['role'],
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Get state for Indonesian city.
     */
    private function getStateForCity(string $city): string
    {
        $states = [
            'Jakarta' => 'DKI Jakarta',
            'Jakarta Pusat' => 'DKI Jakarta',
            'Jakarta Selatan' => 'DKI Jakarta',
            'Jakarta Barat' => 'DKI Jakarta',
            'Jakarta Timur' => 'DKI Jakarta',
            'Jakarta Utara' => 'DKI Jakarta',
            'Bandung' => 'Jawa Barat',
            'Surabaya' => 'Jawa Timur',
            'Yogyakarta' => 'DI Yogyakarta',
            'Semarang' => 'Jawa Tengah',
            'Medan' => 'Sumatera Utara',
            'Makassar' => 'Sulawesi Selatan',
            'Denpasar' => 'Bali',
            'Palembang' => 'Sumatera Selatan',
            'Balikpapan' => 'Kalimantan Timur',
            'Pontianak' => 'Kalimantan Barat',
            'Manado' => 'Sulawesi Utara',
            'Tangerang' => 'Banten',
            'Bekasi' => 'Jawa Barat',
            'Depok' => 'Jawa Barat',
            'Bogor' => 'Jawa Barat',
            'Sleman' => 'DI Yogyakarta',
            'Bantul' => 'DI Yogyakarta',
            'Klaten' => 'Jawa Tengah',
            'Magelang' => 'Jawa Tengah',
        ];

        return $states[$city] ?? 'DKI Jakarta';
    }
}
