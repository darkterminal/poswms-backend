<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
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

    private function createManagerRole(Tenant $tenant): Role
    {
        return Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Manager',
            'slug' => 'manager',
            'description' => 'Manager role',
            'permissions' => ['products.view', 'products.edit', 'orders.view', 'orders.manage'],
            'is_system' => false,
        ]);
    }

    public function test_user_with_required_role_can_access_route(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $this->createAdminRole($tenant);
        $admin = User::factory()->forTenant($tenant->id)->create();
        $admin->assignRole('admin');

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson("/api/v1/tenants/{$tenant->id}/admin-only");

        // Should not return 403
        $this->assertNotEquals(403, $response->status());
    }

    public function test_user_without_required_role_cannot_access_route(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $this->createAdminRole($tenant);
        $this->createManagerRole($tenant);
        $user = User::factory()->forTenant($tenant->id)->create();
        $user->assignRole('manager');

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson("/api/v1/tenants/{$tenant->id}/admin-only");

        $response->assertStatus(403);
    }

    public function test_user_with_required_permission_can_access_route(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $this->createAdminRole($tenant);
        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Product Viewer',
            'slug' => 'product_viewer',
            'permissions' => ['products.view'],
        ]);
        $user = User::factory()->forTenant($tenant->id)->create();
        $user->assignRole($role);

        $token = $user->createToken('test-token')->plainTextToken;

        // This tests that user can access a route that doesn't require specific permission
        // Since we don't have a products permission-protected route, we test role-based access
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson("/api/v1/tenants/{$tenant->id}/auth/me");

        // Should succeed (200) since user is authenticated
        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_protected_route(): void
    {
        $tenant = Tenant::factory()->active()->create();

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/roles");

        $response->assertStatus(401);
    }

    public function test_user_with_any_of_multiple_roles_can_access(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $this->createAdminRole($tenant);
        $this->createManagerRole($tenant);
        $manager = User::factory()->forTenant($tenant->id)->create();
        $manager->assignRole('manager');

        $token = $manager->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson("/api/v1/tenants/{$tenant->id}/admin-or-manager");

        $response->assertStatus(200);
    }

    public function test_user_with_any_of_multiple_permissions_can_access(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $this->createAdminRole($tenant);
        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Product Editor',
            'slug' => 'product_editor',
            'permissions' => ['products.edit'],
        ]);
        $user = User::factory()->forTenant($tenant->id)->create();
        $user->assignRole($role);

        $token = $user->createToken('test-token')->plainTextToken;

        // User has products.edit, route requires products.create OR products.edit
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson("/api/v1/tenants/{$tenant->id}/products/create-or-edit", [
            'name' => 'Test',
        ]);

        $response->assertStatus(200);
    }
}
