<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\InventoryLayer;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\FifoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryValuationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Warehouse $warehouse;
    private Product $product;
    private User $admin;
    private string $token;
    private FifoService $fifoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->active()->create();
        $this->warehouse = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $this->product = Product::factory()->forTenant($this->tenant->id)->create();

        $this->admin = User::factory()->forTenant($this->tenant->id)->create();
        Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin',
            'slug' => 'admin',
            'permissions' => ['*'],
            'is_system' => true,
        ]);
        $this->admin->assignRole('admin');
        $this->token = $this->admin->createToken('test-token')->plainTextToken;

        $this->fifoService = app(FifoService::class);
    }

    public function test_can_get_fifo_valuation(): void
    {
        // Arrange: Create inventory with FIFO layers
        $inventory = Inventory::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'store_id' => null,
            'quantity' => 0,
            'reserved' => 0,
            'available' => 0,
            'cost' => 0,
        ]);

        $this->fifoService->addStock($inventory, 100, 10.00);
        $this->fifoService->addStock($inventory, 150, 12.50);

        // Act
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/valuation");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_quantity',
                    'total_available',
                    'total_value',
                    'layer_count',
                    'by_product',
                    'by_warehouse',
                ],
            ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(250, $data['total_quantity']);
        $this->assertGreaterThanOrEqual(2, $data['layer_count']);
    }

    public function test_can_get_fifo_valuation_filtered_by_warehouse(): void
    {
        // Arrange: Create two warehouses with inventory using unique products
        $warehouse2 = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product1 = Product::factory()->forTenant($this->tenant->id)->create();
        $product2 = Product::factory()->forTenant($this->tenant->id)->create();

        $inventory1 = Inventory::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product1->id,
            'warehouse_id' => $this->warehouse->id,
            'store_id' => null,
            'quantity' => 0,
            'reserved' => 0,
            'available' => 0,
            'cost' => 0,
        ]);

        $inventory2 = Inventory::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product2->id,
            'warehouse_id' => $warehouse2->id,
            'store_id' => null,
            'quantity' => 0,
            'reserved' => 0,
            'available' => 0,
            'cost' => 0,
        ]);

        $this->fifoService->addStock($inventory1, 100, 10.00);
        $this->fifoService->addStock($inventory2, 200, 15.00);

        // Act: Filter by warehouse 1
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/valuation?warehouse_id={$this->warehouse->id}");

        // Assert
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(100, $data['total_quantity']);
        // Verify warehouse 2 data is excluded
        foreach ($data['by_warehouse'] as $wh) {
            $this->assertNotEquals($warehouse2->id, $wh['warehouse_id']);
        }
    }

    public function test_valuation_returns_error_without_tenant_id(): void
    {
        // Act: Call endpoint without tenant_id in URL
        // The route /api/v1/reports/inventory/valuation doesn't exist outside the tenant group,
        // so it returns 404. This verifies the route is properly scoped.
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/v1/reports/inventory/valuation');

        // Assert: Should be rejected (404 = route not found outside tenant group)
        $response->assertStatus(404);
    }

    public function test_can_get_cogs_report(): void
    {
        // Arrange: Create inventory and consume stock
        $inventory = Inventory::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'store_id' => null,
            'quantity' => 0,
            'reserved' => 0,
            'available' => 0,
            'cost' => 0,
        ]);

        $this->fifoService->addStock($inventory, 100, 10.00);
        $this->fifoService->consumeStock($inventory->fresh(), 50, 'out');

        $dateFrom = now()->subDays(7)->format('Y-m-d');
        $dateTo = now()->format('Y-m-d');

        // Act
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/cogs?date_from={$dateFrom}&date_to={$dateTo}");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period',
                    'summary' => [
                        'total_quantity',
                        'total_cost',
                        'movement_count',
                        'average_unit_cost',
                    ],
                    'by_product',
                ],
            ]);
    }

    public function test_cogs_returns_400_without_date_range(): void
    {
        // Act
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/cogs");

        // Assert
        $response->assertStatus(422);
    }

    public function test_can_get_weighted_average_cost(): void
    {
        // Arrange: Use unique product
        $product = Product::factory()->forTenant($this->tenant->id)->create();
        $inventory = Inventory::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'store_id' => null,
            'quantity' => 0,
            'reserved' => 0,
            'available' => 0,
            'cost' => 0,
        ]);

        $this->fifoService->addStock($inventory, 100, 10.00);
        $this->fifoService->addStock($inventory, 200, 15.00);

        // Act
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/weighted-average");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_quantity',
                        'total_value',
                        'weighted_average_cost',
                    ],
                    'by_product',
                ],
            ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(300, $data['summary']['total_quantity']);
    }

    public function test_can_get_value_trends(): void
    {
        // Arrange: Create some stock movements
        $inventory = Inventory::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'store_id' => null,
            'quantity' => 0,
            'reserved' => 0,
            'available' => 0,
            'cost' => 0,
        ]);

        $this->fifoService->addStock($inventory, 100, 10.00);

        // Act
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/value-trends?days=30");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'report_type' => 'cash_flow',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'report_type',
                    'current_value',
                    'period_days',
                    'trends',
                ],
            ]);
    }

    public function test_can_reconcile_inventory(): void
    {
        // Arrange
        $inventory = Inventory::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'store_id' => null,
            'quantity' => 0,
            'reserved' => 0,
            'available' => 0,
            'cost' => 0,
        ]);

        // Act
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/reconcile", [
                'inventory_id' => $inventory->id,
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'reconciled',
                    'before',
                    'after',
                ],
            ]);
    }

    public function test_reconcile_returns_422_for_nonexistent_inventory(): void
    {
        // Act
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/reconcile", [
                'inventory_id' => 99999,
            ]);

        // Assert: Validation fails first (exists rule), returning 422
        $response->assertStatus(422);
    }

    public function test_can_export_valuation_csv(): void
    {
        // Arrange
        $inventory = Inventory::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'store_id' => null,
            'quantity' => 0,
            'reserved' => 0,
            'available' => 0,
            'cost' => 0,
        ]);

        $this->fifoService->addStock($inventory, 100, 10.00);

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'text/csv',
        ])->get("/api/v1/tenants/{$this->tenant->id}/reports/inventory/valuation/export");

        // Assert
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        // Verify CSV content has correct column order (Quantity, Available, Value, Avg Cost)
        $content = $response->streamedContent();
        $lines = explode("\n", trim($content));
        $header = str_getcsv($lines[0]);
        $this->assertEquals('Product ID', $header[0]);
        $this->assertEquals('Quantity', $header[1]);
        $this->assertEquals('Available', $header[2]);
        $this->assertEquals('Value', $header[3]);
        $this->assertEquals('Average Cost', $header[4]);

        // Data row: quantity and available should differ when there's reserved stock
        // In this case they're equal (no reservations), but the column should exist
        $dataRow = str_getcsv($lines[1]);
        $this->assertEquals($this->product->id, (int) $dataRow[0]);
        $this->assertGreaterThanOrEqual(100, (int) $dataRow[1]); // quantity
        $this->assertGreaterThanOrEqual(0, (int) $dataRow[2]);   // available (separate column)
    }

    public function test_cannot_access_another_tenants_valuation(): void
    {
        // Arrange: Create a second tenant with unique product
        $tenant2 = Tenant::factory()->active()->create();
        $warehouse2 = Warehouse::factory()->forTenant($tenant2->id)->create();
        $product2 = Product::factory()->forTenant($tenant2->id)->create();

        $inventory2 = Inventory::create([
            'tenant_id' => $tenant2->id,
            'product_id' => $product2->id,
            'warehouse_id' => $warehouse2->id,
            'store_id' => null,
            'quantity' => 0,
            'reserved' => 0,
            'available' => 0,
            'cost' => 0,
        ]);

        $this->fifoService->addStock($inventory2, 500, 25.00);

        // Act: Tenant 1's admin tries to access Tenant 2's valuation
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson("/api/v1/tenants/{$tenant2->id}/reports/inventory/valuation");

        // Assert: Should be forbidden
        $response->assertStatus(403);
    }

    public function test_reconcile_rejects_another_tenants_inventory(): void
    {
        // Arrange: Create a second tenant with inventory
        $tenant2 = Tenant::factory()->active()->create();
        $warehouse2 = Warehouse::factory()->forTenant($tenant2->id)->create();
        $product2 = Product::factory()->forTenant($tenant2->id)->create();

        $inventory2 = Inventory::create([
            'tenant_id' => $tenant2->id,
            'product_id' => $product2->id,
            'warehouse_id' => $warehouse2->id,
            'store_id' => null,
            'quantity' => 0,
            'reserved' => 0,
            'available' => 0,
            'cost' => 0,
        ]);

        // Act: Tenant 1's admin tries to reconcile Tenant 2's inventory
        // Using tenant 1's URL but passing tenant 2's inventory_id
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/reconcile", [
                'inventory_id' => $inventory2->id,
            ]);

        // Assert: Should fail — either validation error (422) or not found (404)
        // because the inventory doesn't belong to tenant 1
        $this->assertTrue(
            $response->status() === 422 || $response->status() === 404,
            "Expected 422 or 404 but got {$response->status()}"
        );
    }

    public function test_stock_movement_defaults_total_cost_to_zero(): void
    {
        // Arrange: Create a stock movement without unit_cost
        $movement = StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => 'out',
            'quantity' => 10,
            'quantity_before' => 100,
            'quantity_after' => 90,
            'warehouse_id' => $this->warehouse->id,
            'reason' => 'Test movement without cost',
            // No unit_cost provided
        ]);

        // Assert: total_cost should default to 0, not NULL
        $this->assertNotNull($movement->total_cost);
        $this->assertEquals(0, $movement->total_cost);
    }

    public function test_stock_movement_calculates_total_cost_from_unit_cost(): void
    {
        // Arrange: Create a stock movement with unit_cost
        $movement = StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => 'out',
            'quantity' => 10,
            'unit_cost' => 15.50,
            'quantity_before' => 100,
            'quantity_after' => 90,
            'warehouse_id' => $this->warehouse->id,
            'reason' => 'Test movement with cost',
        ]);

        // Assert: total_cost should be calculated automatically
        $this->assertNotNull($movement->total_cost);
        $this->assertEquals(155.00, $movement->total_cost);
    }

    public function test_cogs_report_handles_movements_without_cost(): void
    {
        // Arrange: Create inventory and consume stock (creates movements with cost)
        $inventory = Inventory::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'store_id' => null,
            'quantity' => 0,
            'reserved' => 0,
            'available' => 0,
            'cost' => 0,
        ]);

        $this->fifoService->addStock($inventory, 100, 10.00);
        $this->fifoService->consumeStock($inventory->fresh(), 50, 'out');

        // Also create a movement without unit_cost (simulates legacy data)
        StockMovement::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => 'out',
            'quantity' => 5,
            'quantity_before' => 50,
            'quantity_after' => 45,
            'warehouse_id' => $this->warehouse->id,
            'reason' => 'Manual adjustment without cost',
            // No unit_cost — total_cost should default to 0
        ]);

        $dateFrom = now()->subDays(7)->format('Y-m-d');
        $dateTo = now()->format('Y-m-d');

        // Act
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/cogs?date_from={$dateFrom}&date_to={$dateTo}");

        // Assert: Should return successfully (no NULL errors)
        $response->assertStatus(200);
        $data = $response->json('data');
        // total_cost should be a number, not null
        $this->assertIsNumeric($data['summary']['total_cost']);
    }

    public function test_valuation_requires_reports_view_permission(): void
    {
        // Arrange: Create user without reports.view permission
        $userWithoutPermission = User::factory()->forTenant($this->tenant->id)->create();
        Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Store Staff',
            'slug' => 'store_staff',
            'permissions' => ['products.view', 'orders.view', 'orders.create', 'inventory.view'],
            'is_system' => true,
        ]);
        $userWithoutPermission->assignRole('store_staff');
        $tokenWithoutPermission = $userWithoutPermission->createToken('test-token')->plainTextToken;

        // Act: Try to access valuation without reports.view permission
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $tokenWithoutPermission])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/valuation");

        // Assert: Should be forbidden
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient permissions. Required: reports.view',
            ]);
    }

    public function test_cogs_requires_reports_view_permission(): void
    {
        // Arrange: Create user without reports.view permission
        $userWithoutPermission = User::factory()->forTenant($this->tenant->id)->create();
        Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Store Staff',
            'slug' => 'store_staff',
            'permissions' => ['products.view', 'orders.view', 'orders.create', 'inventory.view'],
            'is_system' => true,
        ]);
        $userWithoutPermission->assignRole('store_staff');
        $tokenWithoutPermission = $userWithoutPermission->createToken('test-token')->plainTextToken;

        $dateFrom = now()->subDays(7)->format('Y-m-d');
        $dateTo = now()->format('Y-m-d');

        // Act: Try to access COGS without reports.view permission
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $tokenWithoutPermission])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/cogs?date_from={$dateFrom}&date_to={$dateTo}");

        // Assert: Should be forbidden
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient permissions. Required: reports.view',
            ]);
    }

    public function test_weighted_average_requires_reports_view_permission(): void
    {
        // Arrange: Create user without reports.view permission
        $userWithoutPermission = User::factory()->forTenant($this->tenant->id)->create();
        Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Store Staff',
            'slug' => 'store_staff',
            'permissions' => ['products.view', 'orders.view', 'orders.create', 'inventory.view'],
            'is_system' => true,
        ]);
        $userWithoutPermission->assignRole('store_staff');
        $tokenWithoutPermission = $userWithoutPermission->createToken('test-token')->plainTextToken;

        // Act: Try to access weighted average without reports.view permission
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $tokenWithoutPermission])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/weighted-average");

        // Assert: Should be forbidden
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient permissions. Required: reports.view',
            ]);
    }

    public function test_value_trends_requires_reports_view_permission(): void
    {
        // Arrange: Create user without reports.view permission
        $userWithoutPermission = User::factory()->forTenant($this->tenant->id)->create();
        Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Store Staff',
            'slug' => 'store_staff',
            'permissions' => ['products.view', 'orders.view', 'orders.create', 'inventory.view'],
            'is_system' => true,
        ]);
        $userWithoutPermission->assignRole('store_staff');
        $tokenWithoutPermission = $userWithoutPermission->createToken('test-token')->plainTextToken;

        // Act: Try to access value trends without reports.view permission
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $tokenWithoutPermission])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/value-trends?days=30");

        // Assert: Should be forbidden
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient permissions. Required: reports.view',
            ]);
    }

    public function test_reconcile_requires_reports_view_permission(): void
    {
        // Arrange: Create user without reports.view permission
        $userWithoutPermission = User::factory()->forTenant($this->tenant->id)->create();
        Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Store Staff',
            'slug' => 'store_staff',
            'permissions' => ['products.view', 'orders.view', 'orders.create', 'inventory.view'],
            'is_system' => true,
        ]);
        $userWithoutPermission->assignRole('store_staff');
        $tokenWithoutPermission = $userWithoutPermission->createToken('test-token')->plainTextToken;

        $inventory = Inventory::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'store_id' => null,
            'quantity' => 0,
            'reserved' => 0,
            'available' => 0,
            'cost' => 0,
        ]);

        // Act: Try to reconcile without reports.view permission
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $tokenWithoutPermission])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/reconcile", [
                'inventory_id' => $inventory->id,
            ]);

        // Assert: Should be forbidden
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient permissions. Required: reports.view',
            ]);
    }

    public function test_export_valuation_requires_reports_view_permission(): void
    {
        // Arrange: Create user without reports.view permission
        $userWithoutPermission = User::factory()->forTenant($this->tenant->id)->create();
        Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Store Staff',
            'slug' => 'store_staff',
            'permissions' => ['products.view', 'orders.view', 'orders.create', 'inventory.view'],
            'is_system' => true,
        ]);
        $userWithoutPermission->assignRole('store_staff');
        $tokenWithoutPermission = $userWithoutPermission->createToken('test-token')->plainTextToken;

        // Act: Try to export valuation without reports.view permission
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $tokenWithoutPermission,
            'Accept' => 'text/csv',
        ])->get("/api/v1/tenants/{$this->tenant->id}/reports/inventory/valuation/export");

        // Assert: Should be forbidden
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient permissions. Required: reports.view',
            ]);
    }

    public function test_user_with_reports_view_permission_can_access_valuation(): void
    {
        // Arrange: Create user with reports.view permission (Viewer role)
        $userWithPermission = User::factory()->forTenant($this->tenant->id)->create();
        Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Viewer',
            'slug' => 'viewer',
            'permissions' => ['products.view', 'orders.view', 'inventory.view', 'reports.view'],
            'is_system' => true,
        ]);
        $userWithPermission->assignRole('viewer');
        $tokenWithPermission = $userWithPermission->createToken('test-token')->plainTextToken;

        // Create some inventory data
        $inventory = Inventory::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'store_id' => null,
            'quantity' => 0,
            'reserved' => 0,
            'available' => 0,
            'cost' => 0,
        ]);

        $this->fifoService->addStock($inventory, 100, 10.00);

        // Act: Access valuation with reports.view permission
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $tokenWithPermission])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/valuation");

        // Assert: Should succeed
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /**
     * Test valuation pagination.
     */
    public function test_valuation_pagination(): void
    {
        // Delete existing layers first for clean test
        InventoryLayer::where('tenant_id', $this->tenant->id)->delete();

        // Create 5 layers with unique products to ensure count matches
        for ($i = 0; $i < 5; $i++) {
            $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
            $inventory = Inventory::create([
                'tenant_id' => $this->tenant->id,
                'product_id' => $product->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 10,
                'available' => 10,
            ]);

            InventoryLayer::factory()->create([
                'tenant_id' => $this->tenant->id,
                'product_id' => $product->id,
                'inventory_id' => $inventory->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 10,
                'available' => 10,
                'total_cost' => 100,
            ]);
        }

        // Test limit
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/valuation?limit=2");
        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.limit', 2)
            ->assertJsonPath('data.pagination.total', 5)
            ->assertJsonCount(2, 'data.by_product');

        // Test offset
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/valuation?limit=2&offset=4");
        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.offset', 4)
            ->assertJsonPath('data.pagination.total', 5)
            ->assertJsonCount(1, 'data.by_product');
    }

    /**
     * Test weighted average cost pagination.
     */
    public function test_weighted_average_cost_pagination(): void
    {
        // Delete existing layers first
        InventoryLayer::where('tenant_id', $this->tenant->id)->delete();

        // Create 5 layers with unique products
        for ($i = 0; $i < 5; $i++) {
            $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
            $inventory = Inventory::create([
                'tenant_id' => $this->tenant->id,
                'product_id' => $product->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 10,
                'available' => 10,
            ]);

            InventoryLayer::factory()->create([
                'tenant_id' => $this->tenant->id,
                'product_id' => $product->id,
                'inventory_id' => $inventory->id,
                'warehouse_id' => $this->warehouse->id,
                'quantity' => 10,
                'total_cost' => 100,
            ]);
        }

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/weighted-average?limit=3");
        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.limit', 3)
            ->assertJsonPath('data.pagination.total', 5)
            ->assertJsonCount(3, 'data.by_product');
    }

    /**
     * Test value trends days capping.
     */
    public function test_value_trends_days_capping(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/value-trends?days=400");
        $response->assertStatus(200)
            ->assertJsonPath('data.period_days', 365);
    }

    /**
     * Test value trends handles negative adjustments correctly.
     */
    public function test_value_trends_handles_negative_adjustments(): void
    {
        // 1. Arrange: Clear today's movements for clean test
        StockMovement::where('tenant_id', $this->tenant->id)
            ->whereDate('created_at', date('Y-m-d'))
            ->delete();

        // 2. Create inventory
        $inventory = Inventory::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'store_id' => null,
            'quantity' => 100,
            'reserved' => 0,
            'available' => 100,
            'cost' => 10.00,
        ]);

        // 3. Add a positive adjustment (e.g., found 10 units)
        // quantityAfter > quantityBefore => +1
        StockMovement::recordMovement(
            tenantId: $this->tenant->id,
            productId: $this->product->id,
            type: 'adjustment',
            quantity: 10,
            quantityBefore: 100,
            quantityAfter: 110,
            inventoryId: $inventory->id,
            warehouseId: $inventory->warehouse_id,
            unitCost: 10.00,
            reason: 'Found stock'
        );

        // 4. Add a negative adjustment (e.g., damaged 20 units)
        // quantityAfter < quantityBefore => -1
        StockMovement::recordMovement(
            tenantId: $this->tenant->id,
            productId: $this->product->id,
            type: 'adjustment',
            quantity: 20,
            quantityBefore: 110,
            quantityAfter: 90,
            inventoryId: $inventory->id,
            warehouseId: $inventory->warehouse_id,
            unitCost: 10.00,
            reason: 'Damaged'
        );

        // 5. Act: Get value trends
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/reports/inventory/value-trends");

        // 6. Assert: Net adjustment should be (10 * 10) - (20 * 10) = -100
        $response->assertStatus(200);

        $trends = $response->json('data.trends');
        $today = date('Y-m-d');

        $todayTrend = collect($trends)->firstWhere('date', $today);

        $this->assertNotNull($todayTrend, 'Trend for today not found');
        $this->assertEquals(-100.00, (float) $todayTrend['value_adjustments']);
    }
}
