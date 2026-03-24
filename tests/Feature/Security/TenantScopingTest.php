<?php

namespace Tests\Feature\Security;

use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantScopingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant1;
    private Tenant $tenant2;
    private User $user1;
    private User $user2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant1 = Tenant::factory()->create(['slug' => 'tenant1']);
        $this->tenant2 = Tenant::factory()->create(['slug' => 'tenant2']);

        $this->user1 = User::factory()->create([
            'tenant_id' => $this->tenant1->id,
        ]);

        $this->user2 = User::factory()->create([
            'tenant_id' => $this->tenant2->id,
        ]);
    }

    public function test_user_cannot_access_another_tenant_products(): void
    {
        Sanctum::actingAs($this->user1);

        // Create product in tenant2
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant2->id,
        ]);

        // Try to access via tenant1's context
        $response = $this->getJson("/api/v1/tenants/{$this->tenant1->id}/products/{$product->id}");

        // Should not find the product (scoped to different tenant)
        $response->assertStatus(404);
    }

    public function test_user_can_access_own_tenant_products(): void
    {
        Sanctum::actingAs($this->user1);

        $product = Product::factory()->create([
            'tenant_id' => $this->tenant1->id,
        ]);

        $response = $this->getJson("/api/v1/tenants/{$this->tenant1->id}/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.product.id', $product->id);
    }

    public function test_product_index_is_tenant_scoped(): void
    {
        Sanctum::actingAs($this->user1);

        // Create products in both tenants
        Product::factory()->count(3)->create(['tenant_id' => $this->tenant1->id]);
        Product::factory()->count(2)->create(['tenant_id' => $this->tenant2->id]);

        $response = $this->getJson("/api/v1/tenants/{$this->tenant1->id}/products");

        $response->assertStatus(200);
        // Should only see tenant1's products (3, not 5)
        $data = $response->json('data.pagination.total');
        $this->assertEquals(3, $data);
    }

    public function test_user_cannot_access_another_tenant_orders(): void
    {
        Sanctum::actingAs($this->user1);

        $order = Order::factory()->create([
            'tenant_id' => $this->tenant2->id,
        ]);

        $response = $this->getJson("/api/v1/tenants/{$this->tenant1->id}/orders/{$order->id}");

        $response->assertStatus(404);
    }

    public function test_user_can_access_own_tenant_orders(): void
    {
        Sanctum::actingAs($this->user1);

        $order = Order::factory()->create([
            'tenant_id' => $this->tenant1->id,
        ]);

        $response = $this->getJson("/api/v1/tenants/{$this->tenant1->id}/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.order.id', $order->id);
    }

    public function test_cross_tenant_data_modification_is_blocked(): void
    {
        Sanctum::actingAs($this->user1);

        $product = Product::factory()->create([
            'tenant_id' => $this->tenant2->id,
        ]);

        // Try to update product in different tenant
        $response = $this->putJson("/api/v1/tenants/{$this->tenant1->id}/products/{$product->id}", [
            'name' => 'Hacked Product Name',
        ]);

        // Should fail (404 because product not found in tenant1's scope due to global scope)
        $response->assertStatus(404);
    }

    public function test_cross_tenant_deletion_is_blocked(): void
    {
        Sanctum::actingAs($this->user1);

        $product = Product::factory()->create([
            'tenant_id' => $this->tenant2->id,
        ]);

        $response = $this->deleteJson("/api/v1/tenants/{$this->tenant1->id}/products/{$product->id}");

        // Should fail (404 because product not found in tenant1's scope due to global scope)
        $response->assertStatus(404);

        // Verify product still exists
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'tenant_id' => $this->tenant2->id,
        ]);
    }
}
