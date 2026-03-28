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
        // Create demo tenants - Indonesian companies
        $tenants = [
            [
                'name' => 'PT Sumber Makmur Jaya',
                'slug' => 'sumber-makmur-jaya',
                'company_name' => 'PT Sumber Makmur Jaya',
                'email' => 'admin@makmurjaya.co.id',
                'phone' => '+62-21-5550100',
                'address' => 'Jl. Jend. Sudirman No. 123',
                'city' => 'Jakarta Pusat',
                'state' => 'DKI Jakarta',
                'country' => 'Indonesia',
                'postal_code' => '10220',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'status' => 'active',
                'subscription_plan' => 'enterprise',
                'settings' => ['theme' => 'light', 'notifications' => true, 'low_stock_threshold' => 20],
                'trial_ends_at' => null,
                'subscription_ends_at' => now()->addYear(),
            ],
            [
                'name' => 'Toko Elektronik Sejahtera',
                'slug' => 'elektronik-sejahtera',
                'company_name' => 'CV Elektronik Sejahtera',
                'email' => 'admin@elektroniksejahtera.com',
                'phone' => '+62-31-5550200',
                'address' => 'Jl. Basuki Rahmat No. 456',
                'city' => 'Surabaya',
                'state' => 'Jawa Timur',
                'country' => 'Indonesia',
                'postal_code' => '60271',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'status' => 'active',
                'subscription_plan' => 'professional',
                'settings' => ['theme' => 'dark', 'notifications' => true, 'low_stock_threshold' => 15],
                'trial_ends_at' => null,
                'subscription_ends_at' => now()->addMonths(6),
            ],
            [
                'name' => 'CV Berkah Sentosa',
                'slug' => 'berkah-sentosa',
                'company_name' => 'CV Berkah Sentosa',
                'email' => 'admin@berkahsentosa.co.id',
                'phone' => '+62-22-5550300',
                'address' => 'Jl. Asia Afrika No. 789',
                'city' => 'Bandung',
                'state' => 'Jawa Barat',
                'country' => 'Indonesia',
                'postal_code' => '40111',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'status' => 'active',
                'subscription_plan' => 'starter',
                'settings' => ['theme' => 'light', 'notifications' => false, 'low_stock_threshold' => 25],
                'trial_ends_at' => now()->addWeeks(2),
                'subscription_ends_at' => null,
            ],
            [
                'name' => 'UD Mitra Abadi',
                'slug' => 'mitra-abadi',
                'company_name' => 'UD Mitra Abadi',
                'email' => 'admin@mitraabadi.com',
                'phone' => '+62-24-5550400',
                'address' => 'Jl. Pandanaran No. 321',
                'city' => 'Semarang',
                'state' => 'Jawa Tengah',
                'country' => 'Indonesia',
                'postal_code' => '50134',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'status' => 'active',
                'subscription_plan' => 'professional',
                'settings' => ['theme' => 'light', 'notifications' => true, 'low_stock_threshold' => 30],
                'trial_ends_at' => null,
                'subscription_ends_at' => now()->addMonths(3),
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
