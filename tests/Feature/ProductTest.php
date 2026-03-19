<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
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

    public function test_can_create_product(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/products", [
                'name' => 'Test Product',
                'sku' => 'TEST-SKU-001',
                'price' => 99.99,
                'cost' => 50.00,
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
        $this->assertDatabaseHas('products', ['sku' => 'TEST-SKU-001']);
    }

    public function test_can_list_products(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        Product::factory()->forTenant($tenant->id)->count(5)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson("/api/v1/tenants/{$tenant->id}/products");

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['products' => [['id', 'name', 'sku']]]]);
    }

    public function test_product_has_category(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $category = Category::factory()->forTenant($tenant->id)->create();
        $product = Product::factory()->forTenant($tenant->id)->create(['category_id' => $category->id]);

        $this->assertEquals($category->id, $product->category_id);
        $this->assertEquals($category->name, $product->category->name);
    }

    public function test_product_stock_calculation(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $product = Product::factory()->forTenant($tenant->id)->create();

        $this->assertEquals(0, $product->getTotalStock());
        $this->assertEquals(0, $product->getAvailableStock());
    }

    public function test_product_low_stock_detection(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $product = Product::factory()->forTenant($tenant->id)->create(['min_stock' => 100]);

        $this->assertTrue($product->isLowStock());
    }
}
