<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();
        $users = User::all();

        $notifications = [
            // System notifications
            [
                'type' => 'system',
                'title' => 'System Maintenance Scheduled',
                'message' => 'Scheduled maintenance will occur on April 15, 2026 from 2:00 AM to 4:00 AM UTC.',
                'priority' => 'high',
                'data' => ['maintenance_date' => '2026-04-15', 'duration_hours' => 2],
            ],
            [
                'type' => 'system',
                'title' => 'New Feature Released',
                'message' => 'Bulk operations are now available for tenant management. Check out the new features!',
                'priority' => 'medium',
                'data' => ['feature' => 'bulk_operations'],
            ],
            [
                'type' => 'system',
                'title' => 'Security Update',
                'message' => 'A critical security patch has been applied. Please review the security advisory.',
                'priority' => 'urgent',
                'data' => ['advisory_id' => 'SEC-2026-001'],
            ],
            
            // Tenant notifications
            [
                'type' => 'tenant',
                'title' => 'New Tenant Registered',
                'message' => 'Acme Corp has joined the platform. Welcome them!',
                'priority' => 'low',
                'data' => ['tenant_action' => 'registration'],
            ],
            [
                'type' => 'tenant',
                'title' => 'Subscription Expiring Soon',
                'message' => 'TechStart Inc\'s subscription will expire in 7 days. Consider sending a renewal reminder.',
                'priority' => 'high',
                'data' => ['days_until_expiry' => 7],
            ],
            
            // Order notifications
            [
                'type' => 'order',
                'title' => 'High Order Volume Alert',
                'message' => 'Order volume has increased by 45% in the last 24 hours across all tenants.',
                'priority' => 'medium',
                'data' => ['increase_percentage' => 45],
            ],
            [
                'type' => 'order',
                'title' => 'Order Processing Delay',
                'message' => 'Some tenants are experiencing delays in order processing. Investigate recommended.',
                'priority' => 'high',
                'data' => ['affected_tenants' => 3],
            ],
            
            // Inventory notifications
            [
                'type' => 'inventory',
                'title' => 'Low Stock Alert',
                'message' => 'Multiple tenants have reported low stock levels on critical items.',
                'priority' => 'medium',
                'data' => ['items_count' => 15],
            ],
            [
                'type' => 'inventory',
                'title' => 'Inventory Sync Completed',
                'message' => 'Successfully synchronized inventory across all warehouses for 12 tenants.',
                'priority' => 'low',
                'data' => ['tenants_synced' => 12],
            ],
            
            // User notifications
            [
                'type' => 'user',
                'title' => 'Unusual Login Activity',
                'message' => 'Multiple failed login attempts detected for several user accounts.',
                'priority' => 'high',
                'data' => ['failed_attempts' => 25],
            ],
            [
                'type' => 'user',
                'title' => 'New Admin User Created',
                'message' => 'A new admin user has been added to the system. Review permissions if needed.',
                'priority' => 'low',
                'data' => ['user_role' => 'admin'],
            ],
        ];

        foreach ($notifications as $index => $notification) {
            Notification::create([
                'tenant_id' => $tenants->random()->id ?? null,
                'user_id' => $users->random()->id ?? null,
                'type' => $notification['type'],
                'title' => $notification['title'],
                'message' => $notification['message'],
                'priority' => $notification['priority'],
                'data' => $notification['data'],
                'read_at' => $index % 3 === 0 ? now()->subDays(rand(1, 5)) : null,
                'created_at' => now()->subDays(rand(0, 10)),
            ]);
        }

        $this->command->info('Notifications seeded successfully!');
    }
}
