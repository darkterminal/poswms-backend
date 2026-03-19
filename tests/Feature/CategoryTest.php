<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
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

    public function test_can_create_category(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/categories", [
                'name' => 'Electronics',
                'description' => 'Electronic products',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
        $this->assertDatabaseHas('categories', ['name' => 'Electronics']);
    }

    public function test_can_create_category_with_parent(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $parent = Category::factory()->forTenant($tenant->id)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/categories", [
                'name' => 'Phones',
                'parent_id' => $parent->id,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('categories', ['name' => 'Phones', 'parent_id' => $parent->id]);
    }

    public function test_can_list_categories(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        Category::factory()->forTenant($tenant->id)->count(3)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson("/api/v1/tenants/{$tenant->id}/categories");

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['categories' => [['id', 'name', 'slug']]]]);
    }

    public function test_can_get_single_category(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $category = Category::factory()->forTenant($tenant->id)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson("/api/v1/tenants/{$tenant->id}/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['category' => ['id' => $category->id]]]);
    }

    public function test_can_update_category(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $category = Category::factory()->forTenant($tenant->id)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson("/api/v1/tenants/{$tenant->id}/categories/{$category->id}", [
                'name' => 'Updated Category',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Updated Category']);
    }

    public function test_can_delete_category(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $category = Category::factory()->forTenant($tenant->id)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->deleteJson("/api/v1/tenants/{$tenant->id}/categories/{$category->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_category_scoped_to_tenant(): void
    {
        $tenant1 = Tenant::factory()->active()->create();
        $tenant2 = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant1);
        $category = Category::factory()->forTenant($tenant2->id)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson("/api/v1/tenants/{$tenant1->id}/categories/{$category->id}");

        $response->assertStatus(404);
    }
}
