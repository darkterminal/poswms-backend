<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
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

    public function test_can_create_store(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$tenant->id}/stores", [
                'name' => 'Test Store',
                'code' => 'TEST-001',
                'city' => 'New York',
                'country' => 'USA',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
        $this->assertDatabaseHas('stores', ['name' => 'Test Store', 'code' => 'TEST-001']);
    }

    public function test_can_list_stores(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        Store::factory()->forTenant($tenant->id)->count(3)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$tenant->id}/stores");

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['stores' => [['id', 'name', 'code']]]]);
    }

    public function test_can_get_single_store(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $store = Store::factory()->forTenant($tenant->id)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$tenant->id}/stores/{$store->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['store' => ['id' => $store->id]]]);
    }

    public function test_can_update_store(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $store = Store::factory()->forTenant($tenant->id)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->putJson("/api/v1/tenants/{$tenant->id}/stores/{$store->id}", [
                'name' => 'Updated Store',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('stores', ['id' => $store->id, 'name' => 'Updated Store']);
    }

    public function test_can_delete_store(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $store = Store::factory()->forTenant($tenant->id)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->deleteJson("/api/v1/tenants/{$tenant->id}/stores/{$store->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('stores', ['id' => $store->id]);
    }
}
