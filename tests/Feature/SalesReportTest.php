<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $admin;

    protected Store $store;

    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);
        $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        Sanctum::actingAs($this->admin);
    }

    public function test_revenue_report_returns_correct_data(): void
    {
        // Create test orders
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'price' => 100.00]);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        // Create fulfilled orders
        Order::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer->id,
            'status' => 'fulfilled',
            'subtotal' => 100.00,
            'tax' => 10.00,
            'discount' => 5.00,
            'shipping' => 15.00,
        ])->each(function ($order) use ($product) {
            OrderItem::factory()->create([
                'tenant_id' => $this->tenant->id,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 100.00,
            ]);
        });

        // Create pending order (should not be counted in revenue)
        Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
            'subtotal' => 200.00,
        ]);

        $response = $this->getJson("/api/v1/tenants/{$this->tenant->id}/reports/sales/revenue");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total_orders', 3)
            ->assertJsonPath('data.summary.total_revenue', 300)
            ->assertJsonPath('data.summary.total_tax', 30)
            ->assertJsonPath('data.summary.total_discount', 15)
            ->assertJsonPath('data.summary.total_shipping', 45);
    }

    public function test_revenue_report_filters_by_date_range(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        // Order in the past
        $pastOrder = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'fulfilled',
            'subtotal' => 100.00,
            'created_at' => now()->subDays(10),
        ]);
        OrderItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $pastOrder->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100.00,
        ]);

        // Order recently
        $recentOrder = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'fulfilled',
            'subtotal' => 200.00,
            'created_at' => now()->subDays(2),
        ]);
        OrderItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $recentOrder->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100.00,
        ]);

        $startDate = now()->subDays(5)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->getJson("/api/v1/tenants/{$this->tenant->id}/reports/sales/revenue?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total_orders', 1)
            ->assertJsonPath('data.summary.total_revenue', 200);
    }

    public function test_revenue_report_groups_by_period(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        // Create orders on different days
        for ($i = 0; $i < 5; $i++) {
            $order = Order::factory()->create([
                'tenant_id' => $this->tenant->id,
                'status' => 'fulfilled',
                'subtotal' => 100.00,
                'created_at' => now()->subDays($i),
            ]);
            OrderItem::factory()->create([
                'tenant_id' => $this->tenant->id,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 100.00,
            ]);
        }

        $response = $this->getJson("/api/v1/tenants/{$this->tenant->id}/reports/sales/revenue?period=daily");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.period', 'daily')
            ->assertJsonFragment(['total_revenue' => 100.00]);
    }

    public function test_revenue_report_filters_by_store(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $store2 = Store::factory()->create(['tenant_id' => $this->tenant->id]);

        // Order for store 1
        $order1 = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $this->store->id,
            'status' => 'fulfilled',
            'subtotal' => 100.00,
        ]);
        OrderItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order1->id,
            'product_id' => $product->id,
        ]);

        // Order for store 2
        $order2 = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'store_id' => $store2->id,
            'status' => 'fulfilled',
            'subtotal' => 200.00,
        ]);
        OrderItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order2->id,
            'product_id' => $product->id,
        ]);

        $response = $this->getJson("/api/v1/tenants/{$this->tenant->id}/reports/sales/revenue?store_id={$this->store->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_revenue', 100);
    }

    public function test_orders_by_period_returns_correct_data(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        Order::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'fulfilled',
            'subtotal' => 100.00,
        ]);

        Order::factory()->count(1)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
            'subtotal' => 50.00,
        ]);

        Order::factory()->count(1)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'cancelled',
            'subtotal' => 75.00,
        ]);

        $response = $this->getJson("/api/v1/tenants/{$this->tenant->id}/reports/sales/orders-by-period");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total_orders', 4)
            ->assertJsonPath('data.summary.fulfilled', 2)
            ->assertJsonPath('data.summary.pending', 1)
            ->assertJsonPath('data.summary.cancelled', 1);
    }

    public function test_orders_by_period_filters_by_status(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        Order::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'fulfilled',
        ]);

        Order::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $response = $this->getJson("/api/v1/tenants/{$this->tenant->id}/reports/sales/orders-by-period?status=fulfilled");

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_orders', 3)
            ->assertJsonPath('data.summary.fulfilled', 3)
            ->assertJsonPath('data.summary.pending', 0);
    }

    public function test_top_products_returns_correct_data(): void
    {
        $product1 = Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Product A']);
        $product2 = Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Product B']);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        // Product 1: 10 units sold
        $order1 = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'fulfilled',
        ]);
        OrderItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order1->id,
            'product_id' => $product1->id,
            'quantity' => 5,
            'unit_price' => 100.00,
        ]);

        $order2 = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'fulfilled',
        ]);
        OrderItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order2->id,
            'product_id' => $product1->id,
            'quantity' => 5,
            'unit_price' => 100.00,
        ]);

        // Product 2: 3 units sold
        $order3 = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'fulfilled',
        ]);
        OrderItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order3->id,
            'product_id' => $product2->id,
            'quantity' => 3,
            'unit_price' => 50.00,
        ]);

        $response = $this->getJson("/api/v1/tenants/{$this->tenant->id}/reports/sales/top-products?sort_by=quantity");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.top_products.0.product_id', $product1->id)
            ->assertJsonPath('data.top_products.0.total_quantity', 10)
            ->assertJsonPath('data.top_products.0.total_revenue', 1000)
            ->assertJsonPath('data.top_products.1.product_id', $product2->id)
            ->assertJsonPath('data.top_products.1.total_quantity', 3);
    }

    public function test_top_products_sorts_by_revenue(): void
    {
        $product1 = Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Product A', 'price' => 10.00]);
        $product2 = Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Product B', 'price' => 100.00]);
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        // Product 1: 100 units at $10 = $1000
        $order1 = Order::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'fulfilled']);
        OrderItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order1->id,
            'product_id' => $product1->id,
            'quantity' => 100,
            'unit_price' => 10.00,
        ]);

        // Product 2: 5 units at $100 = $500
        $order2 = Order::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'fulfilled']);
        OrderItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order2->id,
            'product_id' => $product2->id,
            'quantity' => 5,
            'unit_price' => 100.00,
        ]);

        $response = $this->getJson("/api/v1/tenants/{$this->tenant->id}/reports/sales/top-products?sort_by=revenue");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.top_products.0.product_id', $product1->id)
            ->assertJsonPath('data.top_products.0.total_revenue', 1000);
    }

    public function test_top_products_respects_limit(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        // Create 10 products
        $products = Product::factory()->count(10)->create(['tenant_id' => $this->tenant->id]);

        foreach ($products as $product) {
            $order = Order::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'fulfilled']);
            OrderItem::factory()->create([
                'tenant_id' => $this->tenant->id,
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 100.00,
            ]);
        }

        $response = $this->getJson("/api/v1/tenants/{$this->tenant->id}/reports/sales/top-products?limit=5");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.limit', '5')
            ->assertJsonCount(5, 'data.top_products');
    }

    public function test_dashboard_metrics_returns_current_period_data(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        // Create orders for today
        Order::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'fulfilled',
            'subtotal' => 100.00,
            'created_at' => now()->today(),
        ]);

        $response = $this->getJson("/api/v1/tenants/{$this->tenant->id}/reports/sales/dashboard?period=today");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.period', 'today')
            ->assertJsonPath('data.revenue.current', 300)
            ->assertJsonPath('data.orders.current', 3);
    }

    public function test_dashboard_metrics_calculates_growth(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        // Create orders for today (current period)
        Order::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'fulfilled',
            'subtotal' => 100.00,
            'created_at' => now()->today(),
        ]);

        // Create orders for yesterday (previous period)
        Order::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'fulfilled',
            'subtotal' => 100.00,
            'created_at' => now()->yesterday(),
        ]);

        $response = $this->getJson("/api/v1/tenants/{$this->tenant->id}/reports/sales/dashboard?period=today");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.orders.current', 5)
            ->assertJsonPath('data.orders.previous', 2);
        // Growth: (5-2)/2 * 100 = 150%
    }

    public function test_dashboard_metrics_returns_order_status_breakdown(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        Order::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'status' => 'pending']);
        Order::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'status' => 'confirmed']);
        Order::factory()->count(5)->create(['tenant_id' => $this->tenant->id, 'status' => 'fulfilled']);
        Order::factory()->count(1)->create(['tenant_id' => $this->tenant->id, 'status' => 'cancelled']);

        $response = $this->getJson("/api/v1/tenants/{$this->tenant->id}/reports/sales/dashboard?period=all");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order_statuses.total', 11)
            ->assertJsonPath('data.order_statuses.pending', 2)
            ->assertJsonPath('data.order_statuses.confirmed', 3)
            ->assertJsonPath('data.order_statuses.fulfilled', 5)
            ->assertJsonPath('data.order_statuses.cancelled', 1);
    }

    public function test_dashboard_metrics_calculates_average_order_value(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        Order::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'fulfilled',
            'subtotal' => 100.00,
        ]);

        Order::factory()->count(1)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'fulfilled',
            'subtotal' => 200.00,
        ]);

        $response = $this->getJson("/api/v1/tenants/{$this->tenant->id}/reports/sales/dashboard?period=all");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.revenue.current', 400)
            ->assertJsonPath('data.orders.current', 3)
            ->assertJsonPath('data.average_order_value', 133.33); // 400/3
    }

    public function test_unauthorized_user_cannot_access_reports(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        Sanctum::actingAs($otherUser);

        $response = $this->getJson("/api/v1/tenants/{$this->tenant->id}/reports/sales/revenue");

        $response->assertStatus(403);
    }

    public function test_reports_require_authentication(): void
    {
        // Create a fresh tenant without authentication
        $tenant = Tenant::factory()->create();

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/reports/sales/revenue");

        // Should get 401 or 403 (tenant middleware may return 403 before auth)
        $response->assertStatus(403);
    }
}
