<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminRole(Tenant $tenant): Role
    {
        return Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Administrator role',
            'permissions' => ['*'],
            'is_system' => true,
        ]);
    }

    private function createTestRole(Tenant $tenant, string $slug = 'test_role', array $permissions = []): Role
    {
        return Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Role',
            'slug' => $slug,
            'description' => 'A test role',
            'permissions' => $permissions,
            'is_system' => false,
        ]);
    }

    public function test_admin_can_create_role(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $this->createAdminRole($tenant);
        $admin = User::factory()->forTenant($tenant->id)->create();
        $admin->assignRole('admin');

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson("/api/v1/tenants/{$tenant->id}/roles", [
            'name' => 'Custom Role',
            'slug' => 'custom_role',
            'description' => 'A custom role',
            'permissions' => ['products.view', 'orders.view'],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'role' => [
                        'name' => 'Custom Role',
                        'slug' => 'custom_role',
                    ],
                ],
            ]);
    }

    public function test_non_admin_cannot_create_role(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $this->createAdminRole($tenant);
        $this->createTestRole($tenant, 'viewer', ['products.view']);
        $user = User::factory()->forTenant($tenant->id)->create();
        $user->assignRole('viewer');

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson("/api/v1/tenants/{$tenant->id}/roles", [
            'name' => 'Test Role',
            'slug' => 'test_role',
        ]);

        $response->assertStatus(403);
    }

    public function test_can_list_roles(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $this->createAdminRole($tenant);
        $admin = User::factory()->forTenant($tenant->id)->create();
        $admin->assignRole('admin');

        $this->createTestRole($tenant, 'role_1');
        $this->createTestRole($tenant, 'role_2');
        $this->createTestRole($tenant, 'role_3');

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson("/api/v1/tenants/{$tenant->id}/roles");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'roles' => [
                        '*' => ['id', 'name', 'slug', 'permissions'],
                    ],
                ],
            ]);
    }

    public function test_can_get_single_role(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $this->createAdminRole($tenant);
        $admin = User::factory()->forTenant($tenant->id)->create();
        $admin->assignRole('admin');

        $role = $this->createTestRole($tenant, 'single_role');

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson("/api/v1/tenants/{$tenant->id}/roles/{$role->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'role' => [
                        'id' => $role->id,
                        'slug' => 'single_role',
                    ],
                ],
            ]);
    }

    public function test_can_update_role(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $this->createAdminRole($tenant);
        $admin = User::factory()->forTenant($tenant->id)->create();
        $admin->assignRole('admin');

        $role = $this->createTestRole($tenant, 'update_role');

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->putJson("/api/v1/tenants/{$tenant->id}/roles/{$role->id}", [
            'name' => 'Updated Role',
            'permissions' => ['products.view'],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'role' => [
                        'name' => 'Updated Role',
                    ],
                ],
            ]);
    }

    public function test_cannot_delete_system_role(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $adminRole = $this->createAdminRole($tenant);
        $admin = User::factory()->forTenant($tenant->id)->create();
        $admin->assignRole('admin');

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->deleteJson("/api/v1/tenants/{$tenant->id}/roles/{$adminRole->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete system role.',
            ]);
    }

    public function test_can_assign_role_to_user(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $this->createAdminRole($tenant);
        $admin = User::factory()->forTenant($tenant->id)->create();
        $admin->assignRole('admin');

        $role = $this->createTestRole($tenant, 'assignable_role');
        $user = User::factory()->forTenant($tenant->id)->create();

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson("/api/v1/tenants/{$tenant->id}/users/{$user->id}/assign-role", [
            'role_id' => $role->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Role assigned successfully.',
            ]);

        $this->assertTrue($user->fresh()->hasRole($role->slug));
    }

    public function test_user_has_permission_through_role(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $role = $this->createTestRole($tenant, 'permission_role', ['products.view', 'orders.create']);
        $user = User::factory()->forTenant($tenant->id)->create();
        $user->assignRole($role);

        $this->assertTrue($user->hasPermission('products.view'));
        $this->assertTrue($user->hasPermission('orders.create'));
        $this->assertFalse($user->hasPermission('products.delete'));
    }

    public function test_admin_has_all_permissions(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $adminRole = $this->createAdminRole($tenant);
        $admin = User::factory()->forTenant($tenant->id)->create();
        $admin->assignRole($adminRole);

        $this->assertTrue($admin->hasPermission('products.view'));
        $this->assertTrue($admin->hasPermission('orders.delete'));
        $this->assertTrue($admin->hasPermission('any.permission'));
    }
}
