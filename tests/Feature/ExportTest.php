<?php

namespace Tests\Feature;

use App\ExportService;
use App\Jobs\ExportJob;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();

        $adminRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Administrator role',
            'permissions' => [],
            'is_system' => true,
        ]);

        $this->adminUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->adminUser->assignRole($adminRole);
    }

    public function test_export_service_generates_csv(): void
    {
        $service = app(ExportService::class);

        $data = [
            ['name' => 'Product A', 'price' => 100, 'quantity' => 10],
            ['name' => 'Product B', 'price' => 200, 'quantity' => 5],
        ];

        $columns = [
            'name' => 'Product Name',
            'price' => 'Price',
            'quantity' => 'Quantity',
        ];

        $csv = $service->generateCsvString($data, $columns);

        // CSV escapes fields with quotes
        $this->assertStringContainsString('Product Name', $csv);
        $this->assertStringContainsString('Product A', $csv);
        $this->assertStringContainsString('100', $csv);
    }

    public function test_export_service_handles_null_values(): void
    {
        $service = app(ExportService::class);

        $data = [
            ['name' => 'Product A', 'price' => null, 'quantity' => 10],
        ];

        $columns = [
            'name' => 'Product Name',
            'price' => 'Price',
            'quantity' => 'Quantity',
        ];

        $csv = $service->generateCsvString($data, $columns);

        $this->assertStringContainsString('"Product A",,10', $csv);
    }

    public function test_export_service_formats_dates(): void
    {
        $service = app(ExportService::class);

        $date = now();
        $data = [
            ['name' => 'Order', 'created_at' => $date],
        ];

        $columns = [
            'name' => 'Name',
            'created_at' => 'Created At',
        ];

        $csv = $service->generateCsvString($data, $columns);

        $this->assertStringContainsString($date->format('Y-m-d H:i:s'), $csv);
    }

    public function test_export_service_formats_booleans(): void
    {
        $service = app(ExportService::class);

        $data = [
            ['name' => 'Item', 'active' => true, 'deleted' => false],
        ];

        $columns = [
            'name' => 'Name',
            'active' => 'Active',
            'deleted' => 'Deleted',
        ];

        $csv = $service->generateCsvString($data, $columns);

        $this->assertStringContainsString('Yes', $csv);
        $this->assertStringContainsString('No', $csv);
    }

    public function test_export_service_returns_available_formats(): void
    {
        $service = app(ExportService::class);

        $formats = $service->getAvailableFormats();

        $this->assertArrayHasKey('csv', $formats);
        $this->assertArrayHasKey('pdf', $formats);
        $this->assertEquals('CSV (Comma Separated Values)', $formats['csv']);
    }

    public function test_export_revenue_endpoint(): void
    {
        Order::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'fulfilled',
            'subtotal' => 100,
            'tax' => 10,
            'discount' => 5,
            'shipping' => 15,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/sales/export/revenue");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeaderContains('Content-Disposition', 'attachment');
    }

    public function test_export_orders_by_period_endpoint(): void
    {
        Order::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/sales/export/orders-by-period");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_export_top_products_endpoint(): void
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'fulfilled',
        ]);

        OrderItem::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 50,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/sales/export/top-products");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_export_stock_levels_endpoint(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/export/stock-levels");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_export_movements_endpoint(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/export/movements");

        // Empty data is ok - should still return CSV
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_export_low_stock_endpoint(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/export/low-stock");

        // Empty data is ok - should still return CSV
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_export_requires_authentication(): void
    {
        $response = $this->getJson("/api/v1/tenants/{$this->tenant->id}/reports/sales/export/revenue");

        $response->assertStatus(401);
    }

    public function test_export_requires_admin_role(): void
    {
        $storeStaffRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Store Staff',
            'slug' => 'store_staff',
            'description' => 'Store staff role',
            'permissions' => [],
            'is_system' => true,
        ]);

        $nonAdminUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $nonAdminUser->assignRole($storeStaffRole);

        $response = $this->actingAs($nonAdminUser)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/sales/export/revenue");

        $response->assertStatus(403);
    }

    public function test_export_job_can_be_queued(): void
    {
        Queue::fake();

        $data = [
            ['name' => 'Product A', 'price' => 100],
        ];

        $columns = ['name' => 'Name', 'price' => 'Price'];

        ExportJob::dispatch(
            type: 'test_export',
            data: $data,
            columns: $columns,
            format: 'csv',
            tenantId: $this->tenant->id,
            userId: $this->adminUser->id
        );

        Queue::assertPushed(ExportJob::class, function (ExportJob $job) {
            return $job->tenantId === $this->tenant->id
                && $job->type === 'test_export';
        });
    }

    public function test_export_job_generates_filename(): void
    {
        $job = new ExportJob(
            type: 'sales_report',
            data: [],
            columns: [],
            format: 'csv',
            tenantId: $this->tenant->id
        );

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('generateFilename');
        $method->setAccessible(true);

        $filename = $method->invoke($job);

        $this->assertStringStartsWith('sales_report_', $filename);
        $this->assertStringEndsWith('.csv', $filename);
    }

    public function test_export_job_tags(): void
    {
        $job = new ExportJob(
            type: 'inventory_report',
            data: [],
            columns: [],
            format: 'csv',
            tenantId: 123
        );

        $tags = $job->tags();

        $this->assertContains('export:inventory_report', $tags);
        $this->assertContains('tenant:123', $tags);
    }

    public function test_export_with_date_range_filter(): void
    {
        $startDate = now()->subDays(5)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'fulfilled',
            'created_at' => now()->subDays(3),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/sales/export/revenue?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200);
    }

    public function test_export_csv_response_has_utf8_bom(): void
    {
        $service = app(ExportService::class);

        $data = [['name' => 'Test']];
        $columns = ['name' => 'Name'];

        $csv = $service->generateCsvString($data, $columns);

        // Check for UTF-8 BOM
        $bom = chr(0xEF).chr(0xBB).chr(0xBF);
        $this->assertStringStartsWith($bom, $csv);
    }
}
