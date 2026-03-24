<?php

namespace Tests\Unit\Services;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\OrderFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFulfillmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderFulfillmentService $service;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OrderFulfillmentService::class);
        $this->tenant = Tenant::factory()->create();
    }

    public function test_fulfill_order_deducts_inventory(): void
    {
        $warehouse = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product = Product::factory()->forTenant($this->tenant->id)->create();

        $inventory = Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'reserved' => 0,
            'available' => 100,
        ]);

        $order = Order::factory()->forTenant($this->tenant->id)->create([
            'status' => 'confirmed',
            'warehouse_id' => $warehouse->id,
        ]);

        OrderItem::factory()->forTenant($this->tenant->id)->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 50,
        ]);

        $this->service->fulfill($order);

        $inventory->refresh();
        $this->assertEquals(90, $inventory->quantity);
        $this->assertEquals(90, $inventory->available);

        $order->refresh();
        $this->assertEquals('fulfilled', $order->status);
    }

    public function test_fulfill_requires_confirmed_order(): void
    {
        $order = Order::factory()->forTenant($this->tenant->id)->create(['status' => 'pending']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Order must be confirmed before fulfillment');

        $this->service->fulfill($order);
    }

    public function test_fulfill_requires_sufficient_inventory(): void
    {
        $warehouse = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product = Product::factory()->forTenant($this->tenant->id)->create();

        Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
            'reserved' => 0,
            'available' => 5,
        ]);

        $order = Order::factory()->forTenant($this->tenant->id)->create([
            'status' => 'confirmed',
            'warehouse_id' => $warehouse->id,
        ]);

        OrderItem::factory()->forTenant($this->tenant->id)->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 50,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient inventory');

        $this->service->fulfill($order);
    }

    public function test_fulfill_requires_inventory_exists(): void
    {
        $warehouse = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product = Product::factory()->forTenant($this->tenant->id)->create();

        $order = Order::factory()->forTenant($this->tenant->id)->create([
            'status' => 'confirmed',
            'warehouse_id' => $warehouse->id,
        ]);

        OrderItem::factory()->forTenant($this->tenant->id)->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 50,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Inventory not found');

        $this->service->fulfill($order);
    }

    public function test_fulfill_records_stock_movement(): void
    {
        $warehouse = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product = Product::factory()->forTenant($this->tenant->id)->create();

        Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'available' => 100,
        ]);

        $order = Order::factory()->forTenant($this->tenant->id)->create([
            'status' => 'confirmed',
            'warehouse_id' => $warehouse->id,
            'order_number' => 'ORD-001',
        ]);

        OrderItem::factory()->forTenant($this->tenant->id)->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 15,
            'unit_price' => 50,
        ]);

        $this->service->fulfill($order);

        $this->assertDatabaseHas('stock_movements', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'type' => 'order_fulfillment',
            'order_id' => $order->id,
            'quantity' => 15,
        ]);
    }

    public function test_fulfill_multiple_items(): void
    {
        $warehouse = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product1 = Product::factory()->forTenant($this->tenant->id)->create();
        $product2 = Product::factory()->forTenant($this->tenant->id)->create();

        $inventory1 = Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product1->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'available' => 100,
        ]);

        $inventory2 = Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product2->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 50,
            'available' => 50,
        ]);

        $order = Order::factory()->forTenant($this->tenant->id)->create([
            'status' => 'confirmed',
            'warehouse_id' => $warehouse->id,
        ]);

        OrderItem::factory()->forTenant($this->tenant->id)->create([
            'order_id' => $order->id,
            'product_id' => $product1->id,
            'quantity' => 10,
            'unit_price' => 50,
        ]);

        OrderItem::factory()->forTenant($this->tenant->id)->create([
            'order_id' => $order->id,
            'product_id' => $product2->id,
            'quantity' => 5,
            'unit_price' => 30,
        ]);

        $this->service->fulfill($order);

        $inventory1->refresh();
        $inventory2->refresh();

        $this->assertEquals(90, $inventory1->quantity);
        $this->assertEquals(45, $inventory2->quantity);
    }

    public function test_cancel_order(): void
    {
        $order = Order::factory()->forTenant($this->tenant->id)->create([
            'status' => 'confirmed',
        ]);

        $this->service->cancel($order);

        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
        $this->assertNotNull($order->cancelled_at);
    }

    public function test_cannot_cancel_fulfilled_order(): void
    {
        $order = Order::factory()->forTenant($this->tenant->id)->create(['status' => 'fulfilled']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot cancel a fulfilled order');

        $this->service->cancel($order);
    }

    public function test_cancel_order_releases_reserved_quantity(): void
    {
        $product = Product::factory()->forTenant($this->tenant->id)->create();

        $inventory = Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product->id,
            'quantity' => 100,
            'reserved' => 20,
            'available' => 80,
        ]);

        $order = Order::factory()->forTenant($this->tenant->id)->create([
            'status' => 'confirmed',
        ]);

        OrderItem::factory()->forTenant($this->tenant->id)->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'unit_price' => 50,
        ]);

        $this->service->cancel($order);

        $inventory->refresh();
        $this->assertEquals(100, $inventory->quantity);
        $this->assertEquals(100, $inventory->available);
    }
}
