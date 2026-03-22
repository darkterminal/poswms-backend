<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'is_super_admin' => true,
        ]);
    }

    private function createRegularUser(): User
    {
        return User::factory()->create([
            'is_super_admin' => false,
        ]);
    }

    private function superAdminToken(): string
    {
        return $this->createSuperAdmin()->createToken('super-admin-token')->plainTextToken;
    }

    /**
     * Test super admin can list tenants.
     */
    public function test_super_admin_can_list_tenants(): void
    {
        Tenant::factory()->count(5)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ])->getJson('/api/v1/admin/tenants');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'tenants' => [['id', 'name', 'slug', 'email', 'status']],
                    'pagination' => ['current_page', 'per_page', 'total', 'last_page', 'has_more'],
                ],
                'message',
            ]);
    }

    /**
     * Test super admin can filter tenants by search.
     */
    public function test_super_admin_can_search_tenants(): void
    {
        Tenant::factory()->create(['name' => 'Acme Corporation', 'slug' => 'acme-corp']);
        Tenant::factory()->create(['name' => 'Beta Industries', 'slug' => 'beta-ind']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ])->getJson('/api/v1/admin/tenants?search=acme');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.tenants.0.name', 'Acme Corporation');
    }

    /**
     * Test super admin can filter tenants by status.
     */
    public function test_super_admin_can_filter_tenants_by_status(): void
    {
        Tenant::factory()->create(['status' => 'active']);
        Tenant::factory()->create(['status' => 'suspended']);
        Tenant::factory()->create(['status' => 'inactive']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ])->getJson('/api/v1/admin/tenants?status=active');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 1);
    }

    /**
     * Test super admin can create a new tenant.
     */
    public function test_super_admin_can_create_tenant(): void
    {
        $tenantData = [
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'company_name' => 'Test Company Ltd.',
            'email' => 'test@tenant.com',
            'phone' => '+1234567890',
            'address' => '123 Test Street',
            'city' => 'Test City',
            'state' => 'Test State',
            'country' => 'Test Country',
            'postal_code' => '12345',
            'timezone' => 'UTC',
            'currency' => 'USD',
            'status' => 'active',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ])->postJson('/api/v1/admin/tenants', $tenantData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => ['tenant' => ['id', 'name', 'slug', 'email']],
                'message',
            ])
            ->assertJsonPath('data.tenant.name', 'Test Tenant');

        $this->assertDatabaseHas('tenants', [
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'email' => 'test@tenant.com',
        ]);
    }

    /**
     * Test super admin can view a specific tenant.
     */
    public function test_super_admin_can_view_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ])->getJson('/api/v1/admin/tenants/' . $tenant->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => ['tenant' => ['id', 'name', 'slug', 'email']],
                'message',
            ])
            ->assertJsonPath('data.tenant.id', $tenant->id);
    }

    /**
     * Test super admin can update a tenant.
     */
    public function test_super_admin_can_update_tenant(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Old Name', 'status' => 'active']);

        $updateData = [
            'name' => 'Updated Name',
            'status' => 'suspended',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ])->putJson('/api/v1/admin/tenants/' . $tenant->id, $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant.name', 'Updated Name')
            ->assertJsonPath('data.tenant.status', 'suspended');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Updated Name',
            'status' => 'suspended',
        ]);
    }

    /**
     * Test super admin can delete a tenant (soft delete).
     */
    public function test_super_admin_can_delete_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ])->deleteJson('/api/v1/admin/tenants/' . $tenant->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Tenant deleted successfully');

        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
    }

    /**
     * Test super admin can activate a tenant.
     */
    public function test_super_admin_can_activate_tenant(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'inactive']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ])->postJson('/api/v1/admin/tenants/' . $tenant->id . '/activate');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant.status', 'active');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'status' => 'active',
        ]);
    }

    /**
     * Test super admin can suspend a tenant.
     */
    public function test_super_admin_can_suspend_tenant(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ])->postJson('/api/v1/admin/tenants/' . $tenant->id . '/suspend');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant.status', 'suspended');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'status' => 'suspended',
        ]);
    }

    /**
     * Test super admin can get tenant statistics.
     */
    public function test_super_admin_can_get_tenant_stats(): void
    {
        $tenant = Tenant::factory()->create();

        // Create related data
        $tenant->users()->saveMany(User::factory()->count(3)->make());
        $tenant->stores()->saveMany(\App\Models\Store::factory()->count(2)->make());
        $tenant->warehouses()->saveMany(\App\Models\Warehouse::factory()->count(1)->make());

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ])->getJson('/api/v1/admin/tenants/' . $tenant->id . '/stats');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'tenant_id',
                    'tenant_name',
                    'stats' => [
                        'users',
                        'stores',
                        'warehouses',
                        'products',
                        'customers',
                        'inventory_items',
                        'orders',
                        'is_on_trial',
                        'has_active_subscription',
                    ],
                ],
                'message',
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stats.users', 3)
            ->assertJsonPath('data.stats.stores', 2)
            ->assertJsonPath('data.stats.warehouses', 1);
    }

    /**
     * Test regular user cannot access tenant management endpoints.
     */
    public function test_regular_user_cannot_access_tenant_management(): void
    {
        $regularUser = $this->createRegularUser();
        $token = $regularUser->createToken('regular-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/admin/tenants');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'access_denied')
            ->assertJsonPath('error.message', 'Unauthorized. Super admin access required.');
    }

    /**
     * Test unauthenticated user cannot access tenant management endpoints.
     */
    public function test_unauthenticated_user_cannot_access_tenant_management(): void
    {
        $response = $this->getJson('/api/v1/admin/tenants');

        $response->assertStatus(401);
    }

    /**
     * Test tenant creation validation.
     */
    public function test_tenant_creation_requires_valid_data(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ])->postJson('/api/v1/admin/tenants', []);

        $response->assertStatus(422);
        $this->assertApiValidationErrors($response, 'name', 'slug', 'email');
    }

    /**
     * Test tenant creation validates unique slug.
     */
    public function test_tenant_creation_validates_unique_slug(): void
    {
        Tenant::factory()->create(['slug' => 'existing-slug']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ])->postJson('/api/v1/admin/tenants', [
            'name' => 'Test Tenant',
            'slug' => 'existing-slug',
            'email' => 'test@tenant.com',
        ]);

        $response->assertStatus(422);
        $this->assertApiValidationErrors($response, 'slug');
    }

    /**
     * Test tenant creation validates unique email.
     */
    public function test_tenant_creation_validates_unique_email(): void
    {
        Tenant::factory()->create(['email' => 'existing@tenant.com']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ])->postJson('/api/v1/admin/tenants', [
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'email' => 'existing@tenant.com',
        ]);

        $response->assertStatus(422);
        $this->assertApiValidationErrors($response, 'email');
    }
}
