<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['is_super_admin' => true, 'tenant_id' => null]);
        $this->tenant = Tenant::factory()->create();
    }

    public function test_can_get_sales_trend(): void
    {
        Order::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'fulfilled',
            'subtotal' => 100,
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/analytics/sales/trend?period=7d');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'trend' => [
                        '*' => ['date', 'orders', 'revenue', 'avg_order_value'],
                    ],
                ],
            ]);
    }

    public function test_can_get_order_status_distribution(): void
    {
        Order::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'pending']);
        Order::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'confirmed']);
        Order::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'fulfilled']);
        Order::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'fulfilled']);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/analytics/orders/status-distribution');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'distribution' => [
                        '*' => ['status', 'count', 'percentage'],
                    ],
                    'total',
                ],
            ]);
    }

    public function test_can_get_inventory_level_distribution(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        Inventory::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 0]);
        Inventory::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 5]);
        Inventory::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 25]);
        Inventory::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 100]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/analytics/inventory/level-distribution');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_can_get_top_products(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $order = Order::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'fulfilled']);

        DB::table('order_items')->insert([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 100,
            'total' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/analytics/products/top?limit=10');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'sort_by',
                    'period',
                    'products' => [
                        '*' => ['id', 'name', 'sku', 'total_quantity', 'total_revenue', 'order_count'],
                    ],
                ],
            ]);
    }

    public function test_can_get_customer_segments(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        Order::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => 'fulfilled',
            'total' => 200,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/analytics/customers/segments');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'by_segment',
                    'customers' => [
                        '*' => ['id', 'name', 'total_orders', 'total_spent', 'last_order_date', 'segment'],
                    ],
                ],
            ]);
    }

    public function test_can_get_tenant_comparison(): void
    {
        Tenant::factory()->count(3)->create(['status' => 'active']);

        foreach (Tenant::all() as $tenant) {
            Order::factory()->count(2)->create([
                'tenant_id' => $tenant->id,
                'status' => 'fulfilled',
                'total' => 500,
            ]);
        }

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/analytics/tenants/comparison?limit=10');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_can_get_activity_heatmap(): void
    {
        Order::factory()->count(10)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'fulfilled',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/analytics/activity/heatmap?period=30d');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_can_get_inventory_by_warehouse(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);

        Inventory::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
            'cost' => 50,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/analytics/inventory/by-warehouse');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_can_get_recurring_revenue(): void
    {
        Tenant::factory()->create(['status' => 'active', 'subscription_plan' => 'starter']);
        Tenant::factory()->create(['status' => 'active', 'subscription_plan' => 'professional']);
        Tenant::factory()->create(['status' => 'active', 'subscription_plan' => 'enterprise']);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/analytics/revenue/recurring');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'mrr',
                    'by_plan' => [
                        '*' => ['plan', 'count', 'revenue'],
                    ],
                ],
            ]);
    }
}
