<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
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

    public function test_can_create_order(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$tenant->id}/orders", [
                'customer_id' => null,
                'status' => 'pending',
                'items' => [],
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_order_status_transitions(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $order = Order::factory()->forTenant($tenant->id)->create(['status' => 'pending']);

        $this->assertTrue($order->isPending());
        $order->confirm();
        $this->assertTrue($order->isConfirmed());
        $order->fulfill();
        $this->assertTrue($order->isFulfilled());
    }

    public function test_order_can_be_cancelled(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $order = Order::factory()->forTenant($tenant->id)->create(['status' => 'pending']);

        $order->cancel();

        $this->assertTrue($order->isCancelled());
        $this->assertNotNull($order->cancelled_at);
    }

    public function test_order_with_items(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $product = Product::factory()->forTenant($tenant->id)->create(['price' => 100]);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$tenant->id}/orders", [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 100],
                ],
            ]);

        $response->assertStatus(201);
        $orderId = $response->json('data.order.id');
        $this->assertDatabaseHas('order_items', ['order_id' => $orderId, 'quantity' => 2]);
    }

    public function test_order_total_calculation(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $order = Order::factory()->forTenant($tenant->id)->create([
            'subtotal' => 200,
            'tax' => 20,
            'shipping' => 10,
            'discount' => 0,
        ]);

        $this->assertEquals(230, $order->total);
    }
}
