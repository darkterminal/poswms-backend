<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFulfillmentTest extends TestCase
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

    private function createInventory(Tenant $tenant, Product $product, int $quantity, ?Store $store = null, ?Warehouse $warehouse = null): Inventory
    {
        return Inventory::create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'store_id' => $store?->id,
            'warehouse_id' => $warehouse?->id,
            'quantity' => $quantity,
            'reserved' => 0,
            'available' => $quantity,
            'cost' => 10.00,
        ]);
    }

    public function test_order_fulfillment_deducts_inventory(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();
        $product = Product::factory()->forTenant($tenant->id)->create(['price' => 100]);
        $inventory = $this->createInventory($tenant, $product, 50, warehouse: $warehouse);

        // Create order with items in one request
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/orders", [
                'warehouse_id' => $warehouse->id,
                'status' => 'confirmed',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 100],
                ],
            ]);

        $response->assertStatus(201);
        $orderId = $response->json('data.order.id');

        // Now fulfill the order
        $fulfillResponse = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/orders/{$orderId}/fulfill");

        $fulfillResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify inventory was deducted
        $inventory->refresh();
        $this->assertEquals(45, $inventory->quantity);
        $this->assertEquals(45, $inventory->available);

        // Verify order status changed
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'fulfilled',
        ]);

        // Verify stock movement was created
        $this->assertDatabaseHas('stock_movements', [
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'type' => 'order_fulfillment',
            'order_id' => $orderId,
        ]);
    }

    public function test_cannot_fulfill_pending_order(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $order = Order::factory()->forTenant($tenant->id)->create(['status' => 'pending']);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/orders/{$order->id}/fulfill");

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_cannot_fulfill_with_insufficient_inventory(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();
        $product = Product::factory()->forTenant($tenant->id)->create(['price' => 100]);
        $this->createInventory($tenant, $product, 3, warehouse: $warehouse);

        // Create order with more items than available inventory
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/orders", [
                'warehouse_id' => $warehouse->id,
                'status' => 'confirmed',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 100],
                ],
            ]);

        $response->assertStatus(201);
        $orderId = $response->json('data.order.id');

        // Try to fulfill - should fail due to insufficient inventory
        $fulfillResponse = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/orders/{$orderId}/fulfill");

        $fulfillResponse->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_order_cancellation(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();
        $product = Product::factory()->forTenant($tenant->id)->create(['price' => 100]);
        $this->createInventory($tenant, $product, 50, warehouse: $warehouse);

        // Create order
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/orders", [
                'warehouse_id' => $warehouse->id,
                'status' => 'pending',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 100],
                ],
            ]);

        $response->assertStatus(201);
        $orderId = $response->json('data.order.id');

        // Cancel the order
        $cancelResponse = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/orders/{$orderId}/cancel");

        $cancelResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify order was cancelled
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'cancelled',
        ]);
    }

    public function test_cannot_cancel_fulfilled_order(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $order = Order::factory()->forTenant($tenant->id)->create(['status' => 'fulfilled']);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/orders/{$order->id}/cancel");

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_sequential_order_number_generation(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response1 = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/orders", [
                'status' => 'pending',
            ]);

        $response2 = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/orders", [
                'status' => 'pending',
            ]);

        $orderNumber1 = $response1->json('data.order.order_number');
        $orderNumber2 = $response2->json('data.order.order_number');

        // Verify sequential numbering (format: APPNAME-YYYYMM-XXXX)
        $this->assertMatchesRegularExpression('/[A-Z][a-z]+-\d{6}-\d{4}/', $orderNumber1);
        $this->assertMatchesRegularExpression('/[A-Z][a-z]+-\d{6}-\d{4}/', $orderNumber2);

        // Extract sequence numbers
        $seq1 = (int) substr($orderNumber1, -4);
        $seq2 = (int) substr($orderNumber2, -4);

        $this->assertEquals(1, $seq2 - $seq1);
    }

    public function test_custom_order_number_is_preserved(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $customOrderNumber = 'CUSTOM-ORDER-123';

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/orders", [
                'order_number' => $customOrderNumber,
                'status' => 'pending',
            ]);

        $response->assertStatus(201);
        $this->assertEquals($customOrderNumber, $response->json('data.order.order_number'));
    }
}
