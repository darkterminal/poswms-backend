<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all tenants or create default permissions for new tenants
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            // Skip creating permissions without tenant - tenant_id is required
            return;
        }

        foreach ($tenants as $tenant) {
            $this->createDefaultPermissions($tenant);
        }
    }

    /**
     * Create default permissions and roles for a tenant.
     */
    private function createDefaultPermissions(?Tenant $tenant): void
    {
        $tenantId = $tenant?->id;

        // Define default permissions
        $permissions = [
            // Products
            ['name' => 'View Products', 'slug' => 'products.view', 'group' => 'products'],
            ['name' => 'Create Products', 'slug' => 'products.create', 'group' => 'products'],
            ['name' => 'Edit Products', 'slug' => 'products.edit', 'group' => 'products'],
            ['name' => 'Delete Products', 'slug' => 'products.delete', 'group' => 'products'],

            // Orders
            ['name' => 'View Orders', 'slug' => 'orders.view', 'group' => 'orders'],
            ['name' => 'Create Orders', 'slug' => 'orders.create', 'group' => 'orders'],
            ['name' => 'Edit Orders', 'slug' => 'orders.edit', 'group' => 'orders'],
            ['name' => 'Delete Orders', 'slug' => 'orders.delete', 'group' => 'orders'],
            ['name' => 'Fulfill Orders', 'slug' => 'orders.fulfill', 'group' => 'orders'],

            // Inventory
            ['name' => 'View Inventory', 'slug' => 'inventory.view', 'group' => 'inventory'],
            ['name' => 'Manage Inventory', 'slug' => 'inventory.manage', 'group' => 'inventory'],
            ['name' => 'Adjust Stock', 'slug' => 'inventory.adjust', 'group' => 'inventory'],

            // Customers
            ['name' => 'View Customers', 'slug' => 'customers.view', 'group' => 'customers'],
            ['name' => 'Manage Customers', 'slug' => 'customers.manage', 'group' => 'customers'],

            // Reports
            ['name' => 'View Reports', 'slug' => 'reports.view', 'group' => 'reports'],
            ['name' => 'Export Reports', 'slug' => 'reports.export', 'group' => 'reports'],

            // Settings
            ['name' => 'Manage Settings', 'slug' => 'settings.manage', 'group' => 'settings'],
            ['name' => 'Manage Users', 'slug' => 'users.manage', 'group' => 'settings'],
            ['name' => 'Manage Roles', 'slug' => 'roles.manage', 'group' => 'settings'],
        ];

        // Create permissions - check existence for this specific tenant only
        foreach ($permissions as $permissionData) {
            $permission = Permission::where('tenant_id', $tenantId)
                ->where('slug', $permissionData['slug'])
                ->first();

            if ($permission) {
                // Update existing permission for this tenant
                $permission->update([
                    'name' => $permissionData['name'],
                    'group' => $permissionData['group'],
                    'description' => "Allows user to {$permissionData['name']}",
                ]);
            } else {
                // Create new permission for this tenant
                Permission::create([
                    'tenant_id' => $tenantId,
                    'name' => $permissionData['name'],
                    'slug' => $permissionData['slug'],
                    'group' => $permissionData['group'],
                    'description' => "Allows user to {$permissionData['name']}",
                ]);
            }
        }

        // Create default roles
        $roles = [
            [
                'name' => 'Tenant Admin',
                'slug' => 'tenant_admin',
                'description' => 'Full tenant access',
                'is_system' => false,
                'permissions' => ['*'], // All permissions
            ],
            [
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Can manage daily operations',
                'is_system' => false,
                'permissions' => [
                    'products.view',
                    'products.create',
                    'products.edit',
                    'orders.view',
                    'orders.create',
                    'orders.edit',
                    'orders.fulfill',
                    'inventory.view',
                    'inventory.manage',
                    'customers.view',
                    'customers.manage',
                    'reports.view',
                    'reports.export',
                ],
            ],
            [
                'name' => 'Warehouse Staff',
                'slug' => 'warehouse_staff',
                'description' => 'Can manage inventory and fulfill orders',
                'is_system' => false,
                'permissions' => [
                    'products.view',
                    'orders.view',
                    'orders.fulfill',
                    'inventory.view',
                    'inventory.manage',
                    'inventory.adjust',
                ],
            ],
            [
                'name' => 'Store Staff',
                'slug' => 'store_staff',
                'description' => 'Can view products and create orders',
                'is_system' => false,
                'permissions' => [
                    'products.view',
                    'orders.view',
                    'orders.create',
                    'customers.view',
                ],
            ],
            [
                'name' => 'Viewer',
                'slug' => 'viewer',
                'description' => 'Read-only access',
                'is_system' => false,
                'permissions' => [
                    'products.view',
                    'orders.view',
                    'inventory.view',
                    'customers.view',
                    'reports.view',
                ],
            ],
        ];

        foreach ($roles as $roleData) {
            // Check if role already exists for this specific tenant
            $role = Role::where('tenant_id', $tenantId)
                ->where('slug', $roleData['slug'])
                ->first();

            if ($role) {
                // Update existing role for this tenant
                $role->update([
                    'name' => $roleData['name'],
                    'description' => $roleData['description'],
                    'is_system' => $roleData['is_system'],
                    'permissions' => $roleData['permissions'],
                ]);
            } else {
                // Create new role for this tenant (don't update existing global roles)
                Role::create([
                    'tenant_id' => $tenantId,
                    'name' => $roleData['name'],
                    'slug' => $roleData['slug'],
                    'description' => $roleData['description'],
                    'is_system' => $roleData['is_system'],
                    'permissions' => $roleData['permissions'],
                ]);
            }
        }
    }
}
