<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
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

    public function test_can_list_permissions(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $this->createAdminRole($tenant);
        $admin = User::factory()->forTenant($tenant->id)->create();
        $admin->assignRole('admin');

        Permission::factory()->forTenant($tenant->id)->count(5)->create();

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson("/api/v1/tenants/{$tenant->id}/permissions");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'permissions' => [
                        '*' => ['id', 'name', 'slug', 'group'],
                    ],
                ],
            ]);
    }

    public function test_can_filter_permissions_by_group(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $this->createAdminRole($tenant);
        $admin = User::factory()->forTenant($tenant->id)->create();
        $admin->assignRole('admin');

        Permission::factory()->forTenant($tenant->id)->group('products')->create(['slug' => 'products.view']);
        Permission::factory()->forTenant($tenant->id)->group('products')->create(['slug' => 'products.create']);
        Permission::factory()->forTenant($tenant->id)->group('orders')->create(['slug' => 'orders.view']);

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson("/api/v1/tenants/{$tenant->id}/permissions?group=products");

        $response->assertStatus(200);
        $permissions = $response->json('data.permissions');
        $this->assertCount(2, $permissions);
        foreach ($permissions as $permission) {
            $this->assertEquals('products', $permission['group']);
        }
    }

    public function test_can_create_permission(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $this->createAdminRole($tenant);
        $admin = User::factory()->forTenant($tenant->id)->create();
        $admin->assignRole('admin');

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson("/api/v1/tenants/{$tenant->id}/permissions", [
            'name' => 'Test Permission',
            'slug' => 'test.permission',
            'group' => 'test',
            'description' => 'A test permission',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'permission' => [
                        'name' => 'Test Permission',
                        'slug' => 'test.permission',
                    ],
                ],
            ]);
    }

    public function test_permission_slug_must_be_unique_per_tenant(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $this->createAdminRole($tenant);
        $admin = User::factory()->forTenant($tenant->id)->create();
        $admin->assignRole('admin');

        Permission::factory()->forTenant($tenant->id)->create(['slug' => 'test.permission']);

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson("/api/v1/tenants/{$tenant->id}/permissions", [
            'name' => 'Duplicate Permission',
            'slug' => 'test.permission',
            'group' => 'test',
        ]);

        $response->assertStatus(422);
    }

    public function test_can_update_permission(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $this->createAdminRole($tenant);
        $admin = User::factory()->forTenant($tenant->id)->create();
        $admin->assignRole('admin');

        $permission = Permission::factory()->forTenant($tenant->id)->create();

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->putJson("/api/v1/tenants/{$tenant->id}/permissions/{$permission->id}", [
            'name' => 'Updated Permission',
            'group' => 'updated',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'permission' => [
                        'name' => 'Updated Permission',
                    ],
                ],
            ]);
    }

    public function test_can_delete_permission(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $this->createAdminRole($tenant);
        $admin = User::factory()->forTenant($tenant->id)->create();
        $admin->assignRole('admin');

        $permission = Permission::factory()->forTenant($tenant->id)->create();

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->deleteJson("/api/v1/tenants/{$tenant->id}/permissions/{$permission->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Permission deleted successfully.',
            ]);

        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
    }
}
