<?php

namespace Tests\Unit\Services;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\LowStockAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LowStockAlertServiceTest extends TestCase
{
    use RefreshDatabase;

    private LowStockAlertService $service;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LowStockAlertService::class);
        $this->tenant = Tenant::factory()->create();
    }

    public function test_check_low_stock_detects_low_inventory(): void
    {
        $warehouse = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product = Product::factory()->forTenant($this->tenant->id)->create([
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'min_stock' => 50,
        ]);

        Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 30,
            'available' => 30,
        ]);

        $result = $this->service->checkLowStock($this->tenant->id);

        $this->assertEquals(1, $result['total_alerts']);
        $this->assertCount(1, $result['items']);
        $this->assertEquals('Test Product', $result['items'][0]['product_name']);
        $this->assertEquals(30, $result['items'][0]['current_stock']);
        $this->assertEquals(50, $result['items'][0]['minimum_stock']);
        $this->assertEquals(20, $result['items'][0]['shortage']);
    }

    public function test_check_low_stock_detects_critical_levels(): void
    {
        $warehouse = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product = Product::factory()->forTenant($this->tenant->id)->create(['min_stock' => 100]);

        // Out of stock (critical)
        Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 0,
            'available' => 0,
        ]);

        // Very low stock (critical - below 25%)
        Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 20,
            'available' => 20,
        ]);

        $result = $this->service->checkLowStock($this->tenant->id);

        $this->assertEquals(2, $result['total_alerts']);
        $this->assertEquals(2, $result['critical']);
        $this->assertEquals(0, $result['warning']);
    }

    public function test_check_low_stock_detects_warning_levels(): void
    {
        $warehouse = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product = Product::factory()->forTenant($this->tenant->id)->create(['min_stock' => 100]);

        // Warning level (between 25% and 50%)
        Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 40,
            'available' => 40,
        ]);

        $result = $this->service->checkLowStock($this->tenant->id);

        $this->assertEquals(1, $result['total_alerts']);
        $this->assertEquals(0, $result['critical']);
        $this->assertEquals(1, $result['warning']);
        $this->assertEquals('warning', $result['items'][0]['severity']);
    }

    public function test_check_low_stock_ignores_healthy_inventory(): void
    {
        $warehouse = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product = Product::factory()->forTenant($this->tenant->id)->create(['min_stock' => 50]);

        Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'available' => 100,
        ]);

        $result = $this->service->checkLowStock($this->tenant->id);

        $this->assertEquals(0, $result['total_alerts']);
        $this->assertCount(0, $result['items']);
    }

    public function test_is_product_low_stock_returns_true(): void
    {
        $product = Product::factory()->forTenant($this->tenant->id)->create(['min_stock' => 50]);

        Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product->id,
            'quantity' => 20,
            'available' => 20,
        ]);

        $this->assertTrue($this->service->isProductLowStock($product->id));
    }

    public function test_is_product_low_stock_returns_false(): void
    {
        $product = Product::factory()->forTenant($this->tenant->id)->create(['min_stock' => 50]);

        Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product->id,
            'quantity' => 100,
            'available' => 100,
        ]);

        $this->assertFalse($this->service->isProductLowStock($product->id));
    }

    public function test_is_product_low_stock_returns_false_for_nonexistent_product(): void
    {
        $this->assertFalse($this->service->isProductLowStock(99999));
    }

    public function test_get_alert_recipients_returns_admin_emails(): void
    {
        $adminRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin',
            'slug' => 'admin',
            'permissions' => ['*'],
            'is_system' => true,
        ]);

        $user1 = User::factory()->forTenant($this->tenant->id)->create(['email' => 'admin1@example.com']);
        $user2 = User::factory()->forTenant($this->tenant->id)->create(['email' => 'admin2@example.com']);

        $user1->assignRole($adminRole);
        $user2->assignRole($adminRole);

        $recipients = $this->service->getAlertRecipients($this->tenant->id, 'admin');

        $this->assertCount(2, $recipients);
        $this->assertContains('admin1@example.com', $recipients);
        $this->assertContains('admin2@example.com', $recipients);
    }

    public function test_generate_report_summary(): void
    {
        $warehouse = Warehouse::factory()->forTenant($this->tenant->id)->create();

        // Healthy product
        $product1 = Product::factory()->forTenant($this->tenant->id)->create(['min_stock' => 10]);
        Inventory::factory()->forTenant($this->tenant->id)->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product1->id,
            'quantity' => 100,
            'available' => 100,
        ]);

        // Low stock product
        $product2 = Product::factory()->forTenant($this->tenant->id)->create(['min_stock' => 50]);
        Inventory::factory()->forTenant($this->tenant->id)->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product2->id,
            'quantity' => 20,
            'available' => 20,
        ]);

        // Out of stock product
        $product3 = Product::factory()->forTenant($this->tenant->id)->create(['min_stock' => 30]);
        Inventory::factory()->forTenant($this->tenant->id)->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product3->id,
            'quantity' => 0,
            'available' => 0,
        ]);

        $report = $this->service->generateReport($this->tenant->id);

        $this->assertEquals(3, $report['summary']['total_products']);
        $this->assertEquals(2, $report['summary']['low_stock_count']);
        $this->assertEquals(1, $report['summary']['out_of_stock_count']);
        $this->assertEquals(33.33, $report['summary']['health_percentage']);
    }

    public function test_generate_report_filters_by_warehouse(): void
    {
        $warehouse1 = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $warehouse2 = Warehouse::factory()->forTenant($this->tenant->id)->create();

        $product = Product::factory()->forTenant($this->tenant->id)->create(['min_stock' => 50]);

        Inventory::factory()->forTenant($this->tenant->id)->create([
            'warehouse_id' => $warehouse1->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'available' => 100,
        ]);

        Inventory::factory()->forTenant($this->tenant->id)->create([
            'warehouse_id' => $warehouse2->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'available' => 20,
        ]);

        $report = $this->service->generateReport($this->tenant->id, warehouseId: $warehouse2->id);

        $this->assertEquals(1, $report['summary']['total_products']);
        $this->assertEquals(1, $report['summary']['low_stock_count']);
    }

    public function test_generate_report_filters_by_store(): void
    {
        $store = Store::factory()->forTenant($this->tenant->id)->create();

        $product = Product::factory()->forTenant($this->tenant->id)->create(['min_stock' => 50]);

        Inventory::factory()->forTenant($this->tenant->id)->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'available' => 20,
        ]);

        $report = $this->service->generateReport($this->tenant->id, storeId: $store->id);

        $this->assertEquals(1, $report['summary']['total_products']);
        $this->assertEquals(1, $report['summary']['low_stock_count']);
    }

    public function test_severity_calculation_critical_out_of_stock(): void
    {
        $warehouse = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product = Product::factory()->forTenant($this->tenant->id)->create(['min_stock' => 100]);

        Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 0,
            'available' => 0,
        ]);

        $result = $this->service->checkLowStock($this->tenant->id);

        $this->assertEquals('critical', $result['items'][0]['severity']);
    }

    public function test_severity_calculation_critical_below_25_percent(): void
    {
        $warehouse = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product = Product::factory()->forTenant($this->tenant->id)->create(['min_stock' => 100]);

        Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 24,
            'available' => 24,
        ]);

        $result = $this->service->checkLowStock($this->tenant->id);

        $this->assertEquals('critical', $result['items'][0]['severity']);
    }

    public function test_severity_calculation_warning_between_25_and_50_percent(): void
    {
        $warehouse = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product = Product::factory()->forTenant($this->tenant->id)->create(['min_stock' => 100]);

        Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 40,
            'available' => 40,
        ]);

        $result = $this->service->checkLowStock($this->tenant->id);

        $this->assertEquals('warning', $result['items'][0]['severity']);
    }

    public function test_check_low_stock_with_multiple_products(): void
    {
        $warehouse = Warehouse::factory()->forTenant($this->tenant->id)->create();

        $product1 = Product::factory()->forTenant($this->tenant->id)->create(['min_stock' => 50, 'name' => 'Product 1']);
        $product2 = Product::factory()->forTenant($this->tenant->id)->create(['min_stock' => 30, 'name' => 'Product 2']);
        $product3 = Product::factory()->forTenant($this->tenant->id)->create(['min_stock' => 20, 'name' => 'Product 3']);

        Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product1->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 30,
            'available' => 30,
        ]);

        Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product2->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'available' => 100,
        ]);

        Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product3->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
            'available' => 10,
        ]);

        $result = $this->service->checkLowStock($this->tenant->id);

        $this->assertEquals(2, $result['total_alerts']);
        $this->assertContains('Product 1', collect($result['items'])->pluck('product_name')->toArray());
        $this->assertContains('Product 3', collect($result['items'])->pluck('product_name')->toArray());
    }
}
