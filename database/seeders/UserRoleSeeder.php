<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * User Role Seeder
 *
 * Assigns appropriate roles to existing users based on their email/position.
 * This seeder should be run after RealisticDataSeeder to ensure all users
 * have proper role assignments.
 */
class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo 'Seeding user roles...' . PHP_EOL;

        // First ensure all tenants have roles created
        echo 'Ensuring roles exist for all tenants...' . PHP_EOL;
        $this->call(RolePermissionSeeder::class);

        // Define role assignments by tenant
        $this->seedTokoSembakoJaya();
        $this->seedElektronikNusantara();
        $this->seedSumberMakmurJaya();

        echo 'User role seeding completed!' . PHP_EOL;
    }

    /**
     * Seed roles for Toko Sembako Jaya users
     */
    private function seedTokoSembakoJaya(): void
    {
        $this->assignRoles(
            'toko-sembako-jaya',
            [
                'admin@tokosembako.com' => ['tenant_admin'],
                'kasir1@tokosembako.com' => ['store_staff'],
            ]
        );
    }

    /**
     * Seed roles for Elektronik Nusantara users
     */
    private function seedElektronikNusantara(): void
    {
        $this->assignRoles(
            'elektronik-nusantara',
            [
                'admin@elektroniknusantara.co.id' => ['tenant_admin'],
                'manager@elektroniknusantara.co.id' => ['manager'],
                'kasir.senayan@elektroniknusantara.co.id' => ['store_staff'],
                'gudang@elektroniknusantara.co.id' => ['warehouse_staff'],
            ]
        );
    }

    /**
     * Seed roles for PT Sumber Makmur Jaya users
     */
    private function seedSumberMakmurJaya(): void
    {
        $this->assignRoles(
            'sumber-makmur-jaya',
            [
                'admin@makmurjaya.co.id' => ['tenant_admin'],
                'ceo@makmurjaya.co.id' => ['tenant_admin'],
                'operations@makmurjaya.co.id' => ['manager'],
                'warehouse.jkt@makmurjaya.co.id' => ['warehouse_staff'],
                'warehouse.sby@makmurjaya.co.id' => ['warehouse_staff'],
                'store.jkt@makmurjaya.co.id' => ['store_staff'],
                'hr@makmurjaya.co.id' => ['manager'],
                'finance@makmurjaya.co.id' => ['manager'],
            ]
        );
    }

    /**
     * Assign roles to users by email for a given tenant
     */
    private function assignRoles(string $tenantSlug, array $userRoleMap): void
    {
        $tenant = \App\Models\Tenant::where('slug', $tenantSlug)->first();

        if (!$tenant) {
            $this->command->warn("  ⚠ Tenant '{$tenantSlug}' not found, skipping...");
            return;
        }

        echo "  Seeding roles for {$tenant->name}..." . PHP_EOL;

        $rolesCache = [];

        foreach ($userRoleMap as $email => $roleSlugs) {
            $user = User::where('email', $email)->first();

            if (!$user) {
                $this->command->warn("    ⚠ User '{$email}' not found, skipping...");
                continue;
            }

            // Ensure user's tenant_id matches
            if ($user->tenant_id !== $tenant->id) {
                $this->command->warn("    ⚠ User '{$email}' belongs to different tenant, skipping...");
                continue;
            }

            foreach ($roleSlugs as $roleSlug) {
                // Cache roles to avoid repeated queries
                if (!isset($rolesCache[$roleSlug])) {
                    $role = Role::where('tenant_id', $tenant->id)
                        ->where('slug', $roleSlug)
                        ->first();

                    if ($role) {
                        $rolesCache[$roleSlug] = $role;
                    } else {
                        $this->command->warn("    ⚠ Role '{$roleSlug}' not found for tenant {$tenant->name}");
                        continue;
                    }
                }

                $role = $rolesCache[$roleSlug];

                // Attach role if not already assigned (include tenant_id in pivot)
                if (!$user->roles->contains($role->id)) {
                    $user->roles()->attach($role->id, ['tenant_id' => $tenant->id]);
                    echo "    ✓ Assigned '{$role->name}' to {$user->name}" . PHP_EOL;
                }
            }
        }
    }
}
