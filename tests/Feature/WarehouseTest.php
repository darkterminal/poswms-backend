<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(Tenant $tenant): User
    {
        $admin = User::factory()->forTenant($tenant->id)->create();
        Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin',
            'slug' => 'admin',
            'permissions' => ['*'],
            'is_system' => true,
        ]);
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_can_create_warehouse(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$tenant->id}/warehouses", [
                'name' => 'Test Warehouse',
                'code' => 'WH-001',
                'city' => 'Los Angeles',
                'country' => 'USA',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
        $this->assertDatabaseHas('warehouses', ['name' => 'Test Warehouse', 'code' => 'WH-001']);
    }

    public function test_can_list_warehouses(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        Warehouse::factory()->forTenant($tenant->id)->count(3)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$tenant->id}/warehouses");

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['warehouses' => [['id', 'name', 'code']]]]);
    }

    public function test_can_get_single_warehouse(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$tenant->id}/warehouses/{$warehouse->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['warehouse' => ['id' => $warehouse->id]]]);
    }

    public function test_can_update_warehouse(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->putJson("/api/v1/tenants/{$tenant->id}/warehouses/{$warehouse->id}", [
                'name' => 'Updated Warehouse',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id, 'name' => 'Updated Warehouse']);
    }

    public function test_can_delete_warehouse(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->deleteJson("/api/v1/tenants/{$tenant->id}/warehouses/{$warehouse->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('warehouses', ['id' => $warehouse->id]);
    }

    public function test_warehouse_scoped_to_tenant(): void
    {
        $tenant1 = Tenant::factory()->active()->create();
        $tenant2 = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant1);
        $warehouse = Warehouse::factory()->forTenant($tenant2->id)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$tenant1->id}/warehouses/{$warehouse->id}");

        $response->assertStatus(404);
    }
}
