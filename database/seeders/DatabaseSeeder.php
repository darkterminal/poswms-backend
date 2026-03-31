<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed super admin first (global, no tenant dependency)
        $this->call([
            SuperAdminSeeder::class,
        ]);

        // Seed reference data first (no tenant dependency)
        $this->call([
            CountrySeeder::class,
        ]);

        // Option 1: Seed with realistic business data (recommended for development/demo)
        // This creates tier-specific tenants with realistic scenarios
        // Note: RealisticDataSeeder handles currencies, roles, permissions, and pricing tiers internally
        $this->call([
            RealisticDataSeeder::class,
        ]);

        // Option 2: Seed with traditional approach (comment out if using RealisticDataSeeder)
        // Uncomment the following blocks if you want to use the traditional seeding approach:
        /*
        // Seed tenants
        $this->call([
            TenantSeeder::class,
        ]);

        // Seed tenant-scoped reference data
        $this->call([
            CurrencySeeder::class,
        ]);

        // Seed roles and permissions for all tenants
        $this->call([
            RolePermissionSeeder::class,
            PricingTierSeeder::class,
            PricingRuleSeeder::class,
        ]);

        // Seed core entities (depend on tenants)
        $this->call([
            StoreSeeder::class,
            WarehouseSeeder::class,
            CategorySeeder::class,
        ]);

        // Seed products and customers
        $this->call([
            ProductSeeder::class,
            CustomerSeeder::class,
        ]);

        // Seed inventory and related data
        $this->call([
            InventorySeeder::class,
        ]);

        // Seed FIFO batches and layers
        $this->call([
            InventoryBatchSeeder::class,
            InventoryLayerSeeder::class,
            StockMovementSeeder::class,
        ]);

        // Seed orders last (depends on products, customers, inventory)
        $this->call([
            OrderSeeder::class,
        ]);

        // Create default admin user for first tenant
        $this->createAdminUser();
        */
    }

    /**
     * Create a default admin user for the first tenant.
     */
    private function createAdminUser(): void
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            return;
        }

        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@demo.com'],
            [
                'name' => 'Demo Admin',
                'email' => 'admin@demo.com',
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'email_verified_at' => now(),
            ]
        );

        // Create additional test users
        User::firstOrCreate(
            ['email' => 'manager@demo.com'],
            [
                'name' => 'Demo Manager',
                'email' => 'manager@demo.com',
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'staff@demo.com'],
            [
                'name' => 'Demo Staff',
                'email' => 'staff@demo.com',
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'email_verified_at' => now(),
            ]
        );
    }
}
