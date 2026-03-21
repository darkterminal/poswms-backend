<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear cache between tests to prevent pollution
        \Illuminate\Support\Facades\Cache::flush();
    }

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

    public function test_can_get_dashboard_metrics(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();

        // Create products and inventory
        $product1 = Product::factory()->forTenant($tenant->id)->create(['price' => 100, 'cost' => 50, 'min_stock' => 10]);
        $product2 = Product::factory()->forTenant($tenant->id)->create(['price' => 200, 'cost' => 100, 'min_stock' => 5]);

        Inventory::factory()->forTenant($tenant->id)->create([
            'product_id' => $product1->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'available' => 80,
            'reserved' => 20,
            'cost' => 50,
        ]);

        Inventory::factory()->forTenant($tenant->id)->create([
            'product_id' => $product2->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 50,
            'available' => 45,
            'reserved' => 5,
            'cost' => 100,
        ]);

        // Create orders
        $order1 = Order::factory()->forTenant($tenant->id)->create([
            'status' => 'fulfilled',
            'subtotal' => 500,
        ]);

        $order2 = Order::factory()->forTenant($tenant->id)->create([
            'status' => 'confirmed',
            'subtotal' => 300,
        ]);

        $order3 = Order::factory()->forTenant($tenant->id)->create([
            'status' => 'pending',
            'subtotal' => 200,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$tenant->id}/dashboard");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period',
                    'sales' => [
                        'revenue' => ['current', 'previous', 'growth_percentage'],
                        'orders_count' => ['current', 'previous', 'growth_percentage'],
                        'average_order_value',
                    ],
                    'inventory' => [
                        'total_products',
                        'total_quantity',
                        'total_available',
                        'total_reserved',
                        'total_value',
                        'low_stock_count',
                        'out_of_stock_count',
                        'health_percentage',
                    ],
                    'orders' => [
                        'status_counts' => [
                            'total',
                            'pending',
                            'confirmed',
                            'fulfilled',
                            'cancelled',
                        ],
                        'todays_orders',
                        'pending_fulfillment',
                    ],
                ],
            ]);
    }

    public function test_dashboard_sales_metrics_are_accurate(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        // Create fulfilled orders (counted in revenue)
        Order::factory()->forTenant($tenant->id)->count(3)->create([
            'status' => 'fulfilled',
            'subtotal' => 1000,
        ]);

        // Create confirmed order (counted in revenue)
        Order::factory()->forTenant($tenant->id)->create([
            'status' => 'confirmed',
            'subtotal' => 500,
        ]);

        // Create pending order (NOT counted in revenue)
        Order::factory()->forTenant($tenant->id)->create([
            'status' => 'pending',
            'subtotal' => 200,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$tenant->id}/dashboard?period=today");

        $response->assertStatus(200);

        $data = $response->json('data');

        // Revenue should be 3*1000 + 500 = 3500 (only fulfilled and confirmed)
        $this->assertEquals(3500, $data['sales']['revenue']['current']);

        // Orders count should be 4 (3 fulfilled + 1 confirmed)
        $this->assertEquals(4, $data['sales']['orders_count']['current']);

        // Average order value = 3500 / 4 = 875
        $this->assertEquals(875, $data['sales']['average_order_value']);
    }

    public function test_dashboard_inventory_metrics_are_accurate(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();

        // Normal stock product
        $product1 = Product::factory()->forTenant($tenant->id)->create(['price' => 100, 'cost' => 50, 'min_stock' => 10]);
        Inventory::factory()->forTenant($tenant->id)->create([
            'product_id' => $product1->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'available' => 80,
            'reserved' => 20,
            'cost' => 50,
        ]);

        // Low stock product (available <= min_stock)
        $product2 = Product::factory()->forTenant($tenant->id)->create(['price' => 200, 'cost' => 100, 'min_stock' => 50]);
        Inventory::factory()->forTenant($tenant->id)->create([
            'product_id' => $product2->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 30,
            'available' => 25,
            'reserved' => 5,
            'cost' => 100,
        ]);

        // Out of stock product
        $product3 = Product::factory()->forTenant($tenant->id)->create(['price' => 150, 'cost' => 75, 'min_stock' => 20]);
        Inventory::factory()->forTenant($tenant->id)->create([
            'product_id' => $product3->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 0,
            'available' => 0,
            'reserved' => 0,
            'cost' => 75,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$tenant->id}/dashboard");

        $response->assertStatus(200);

        $data = $response->json('data');

        // Total products: 3
        $this->assertEquals(3, $data['inventory']['total_products']);

        // Total quantity: 100 + 30 + 0 = 130
        $this->assertEquals(130, $data['inventory']['total_quantity']);

        // Total available: 80 + 25 + 0 = 105
        $this->assertEquals(105, $data['inventory']['total_available']);

        // Total reserved: 20 + 5 + 0 = 25
        $this->assertEquals(25, $data['inventory']['total_reserved']);

        // Total value: (100*50) + (30*100) + (0*75) = 5000 + 3000 + 0 = 8000
        $this->assertEquals(8000, $data['inventory']['total_value']);

        // Low stock count: 2 (product2 and product3)
        $this->assertEquals(2, $data['inventory']['low_stock_count']);

        // Out of stock count: 1 (product3)
        $this->assertEquals(1, $data['inventory']['out_of_stock_count']);

        // Health percentage: (3-2)/3 * 100 = 33.33%
        $this->assertEquals(33.33, $data['inventory']['health_percentage']);
    }

    public function test_dashboard_order_metrics_are_accurate(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        // Create orders with different statuses
        Order::factory()->forTenant($tenant->id)->count(2)->create(['status' => 'pending']);
        Order::factory()->forTenant($tenant->id)->count(3)->create(['status' => 'confirmed']);
        Order::factory()->forTenant($tenant->id)->count(4)->create(['status' => 'fulfilled']);
        Order::factory()->forTenant($tenant->id)->create(['status' => 'cancelled']);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$tenant->id}/dashboard");

        $response->assertStatus(200);

        $data = $response->json('data');

        $this->assertEquals(10, $data['orders']['status_counts']['total']);
        $this->assertEquals(2, $data['orders']['status_counts']['pending']);
        $this->assertEquals(3, $data['orders']['status_counts']['confirmed']);
        $this->assertEquals(4, $data['orders']['status_counts']['fulfilled']);
        $this->assertEquals(1, $data['orders']['status_counts']['cancelled']);

        // Pending fulfillment = pending + confirmed = 2 + 3 = 5
        $this->assertEquals(5, $data['orders']['pending_fulfillment']);
    }

    public function test_dashboard_supports_period_filtering(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        // Create orders for different periods
        Order::factory()->forTenant($tenant->id)->create([
            'status' => 'fulfilled',
            'subtotal' => 1000,
            'created_at' => now()->startOfDay(),
        ]);

        Order::factory()->forTenant($tenant->id)->create([
            'status' => 'fulfilled',
            'subtotal' => 2000,
            'created_at' => now()->subDays(2),
        ]);

        // Test with period=today
        $responseToday = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$tenant->id}/dashboard?period=today");

        $responseToday->assertStatus(200);
        $this->assertEquals(1000, $responseToday->json('data.sales.revenue.current'));

        // Test with period=all (should include all orders)
        $responseAll = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$tenant->id}/dashboard?period=all");

        $responseAll->assertStatus(200);
        $this->assertEquals(3000, $responseAll->json('data.sales.revenue.current'));
    }

    public function test_dashboard_handles_empty_data(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$tenant->id}/dashboard");

        $response->assertStatus(200);

        $data = $response->json('data');

        // Should have zero values for empty data
        $this->assertEquals(0, $data['sales']['revenue']['current']);
        $this->assertEquals(0, $data['sales']['orders_count']['current']);
        $this->assertEquals(0, $data['inventory']['total_products']);
        $this->assertEquals(100, $data['inventory']['health_percentage']); // 100% when no products
        $this->assertEquals(0, $data['orders']['status_counts']['total']);
    }
}
