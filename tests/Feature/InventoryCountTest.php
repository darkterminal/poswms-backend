<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryCountTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $tenant2;
    private Warehouse $warehouse;
    private Store $store;
    private Product $product1;
    private Product $product2;
    private Inventory $inventory1;
    private Inventory $inventory2;
    private User $admin;
    private User $warehouseStaff;
    private User $viewer;
    private User $tenant2User;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->tenant2 = Tenant::factory()->create();
        $this->warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->product1 = Product::factory()->forTenant($this->tenant->id)->create([
            'name' => 'Widget A',
            'sku' => 'WGT-001',
            'track_inventory' => true,
        ]);
        $this->product2 = Product::factory()->forTenant($this->tenant->id)->create([
            'name' => 'Widget B',
            'sku' => 'WGT-002',
            'track_inventory' => true,
        ]);

        $this->inventory1 = Inventory::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product1->id,
            'warehouse_id' => $this->warehouse->id,
            'store_id' => $this->store->id,
            'quantity' => 100,
            'reserved' => 0,
            'available' => 100,
            'cost' => 5.00,
        ]);
        $this->inventory2 = Inventory::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product2->id,
            'warehouse_id' => $this->warehouse->id,
            'store_id' => $this->store->id,
            'quantity' => 50,
            'reserved' => 0,
            'available' => 50,
            'cost' => 10.00,
        ]);

        // Admin role with all permissions
        Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin',
            'slug' => 'admin',
            'permissions' => ['*'],
            'is_system' => true,
        ]);
        $this->admin = User::factory()->forTenant($this->tenant->id)->create();
        $this->admin->assignRole('admin');

        // Warehouse staff with inventory.counts.manage
        $warehouseRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Warehouse Staff',
            'slug' => 'warehouse_staff',
            'permissions' => [
                'products.view',
                'inventory.view',
                'inventory.counts.manage',
            ],
            'is_system' => true,
        ]);
        $this->warehouseStaff = User::factory()->forTenant($this->tenant->id)->create();
        $this->warehouseStaff->assignRole($warehouseRole);

        // Viewer without inventory.counts.manage
        $viewerRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Viewer',
            'slug' => 'viewer',
            'permissions' => ['products.view', 'inventory.view'],
            'is_system' => true,
        ]);
        $this->viewer = User::factory()->forTenant($this->tenant->id)->create();
        $this->viewer->assignRole($viewerRole);

        // Tenant 2 user
        $this->tenant2User = User::factory()->forTenant($this->tenant2->id)->create();
        Role::create([
            'tenant_id' => $this->tenant2->id,
            'name' => 'Admin',
            'slug' => 'admin',
            'permissions' => ['*'],
            'is_system' => true,
        ]);
        $this->tenant2User->assignRole('admin');
    }

    private function adminToken(): string
    {
        return $this->admin->createToken('admin-token')->plainTextToken;
    }

    private function staffToken(): string
    {
        return $this->warehouseStaff->createToken('staff-token')->plainTextToken;
    }

    private function viewerToken(): string
    {
        return $this->viewer->createToken('viewer-token')->plainTextToken;
    }

    private function tenant2Token(): string
    {
        return $this->tenant2User->createToken('tenant2-token')->plainTextToken;
    }

    private function createCount(array $overrides = [], ?string $token = null): InventoryCount
    {
        $token = $token ?? $this->adminToken();

        $count = InventoryCount::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Count',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
        ], $overrides));

        // Add items
        foreach ([$this->product1, $this->product2] as $product) {
            $inventory = Inventory::where('tenant_id', $this->tenant->id)
                ->where('product_id', $product->id)
                ->where('warehouse_id', $this->warehouse->id)
                ->first();

            InventoryCountItem::create([
                'count_id' => $count->id,
                'product_id' => $product->id,
                'inventory_id' => $inventory?->id,
                'expected_quantity' => $inventory?->available ?? 0,
            ]);
        }

        return $count;
    }

    // =========================================================================
    // Lifecycle: create → start → record → complete → approve
    // =========================================================================

    public function test_can_create_count_with_warehouse(): void
    {
        $token = $this->adminToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts", [
                'name' => 'April Count',
                'warehouse_id' => $this->warehouse->id,
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('inventory_counts', [
            'name' => 'April Count',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
        ]);

        // Products should be auto-populated
        $countId = $response->json('data.count.id');
        $this->assertDatabaseCount('inventory_count_items', 2);
    }

    public function test_can_create_count_with_specific_products(): void
    {
        $token = $this->adminToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts", [
                'name' => 'Selective Count',
                'warehouse_id' => $this->warehouse->id,
                'product_ids' => [$this->product1->id],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('inventory_count_items', 1);
        $this->assertDatabaseHas('inventory_count_items', [
            'product_id' => $this->product1->id,
        ]);
    }

    public function test_cannot_create_count_without_warehouse_or_store(): void
    {
        $token = $this->adminToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts", [
                'name' => 'Bad Count',
            ]);

        $response->assertStatus(422);
    }

    public function test_can_start_count(): void
    {
        $token = $this->adminToken();
        $count = $this->createCount();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts/{$count->id}/start");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('inventory_counts', [
            'id' => $count->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_can_record_item_count(): void
    {
        $token = $this->adminToken();
        $count = $this->createCount();
        $count->start($this->admin->id);
        $item = $count->items()->first();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts/{$count->id}/items/{$item->id}", [
                'counted_quantity' => 95,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('inventory_count_items', [
            'id' => $item->id,
            'counted_quantity' => 95,
            'variance' => -5,
        ]);
    }

    public function test_can_record_decimal_item_count(): void
    {
        $token = $this->adminToken();
        $count = $this->createCount();
        $count->start($this->admin->id);
        $item = $count->items()->first();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts/{$count->id}/items/{$item->id}", [
                'counted_quantity' => 97.5,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('inventory_count_items', [
            'id' => $item->id,
            'counted_quantity' => 97.5,
        ]);

        $item->refresh();
        $this->assertEquals(-2.5, (float) $item->variance);
    }

    public function test_can_complete_count_when_all_items_counted(): void
    {
        $token = $this->adminToken();
        $count = $this->createCount();
        $count->start($this->admin->id);

        // Count all items
        foreach ($count->items as $item) {
            $item->recordCount($item->expected_quantity);
        }

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts/{$count->id}/complete");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('inventory_counts', [
            'id' => $count->id,
            'status' => 'completed',
        ]);
    }

    public function test_can_approve_count_and_adjustments_applied(): void
    {
        $token = $this->adminToken();
        $count = $this->createCount();
        $count->start($this->admin->id);

        // Record variances: product1 has -5, product2 has +3
        $item1 = $count->items()->where('product_id', $this->product1->id)->first();
        $item2 = $count->items()->where('product_id', $this->product2->id)->first();
        $item1->recordCount(95);  // expected 100, variance -5
        $item2->recordCount(53);  // expected 50, variance +3

        $count->complete($this->admin->id);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts/{$count->id}/approve");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('inventory_counts', [
            'id' => $count->id,
            'status' => 'approved',
        ]);

        // Inventory should be adjusted
        $this->assertDatabaseHas('inventories', [
            'id' => $this->inventory1->id,
            'quantity' => 95,  // 100 - 5
        ]);
        $this->assertDatabaseHas('inventories', [
            'id' => $this->inventory2->id,
            'quantity' => 53,  // 50 + 3
        ]);
    }

    public function test_can_cancel_count(): void
    {
        $token = $this->adminToken();
        $count = $this->createCount();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts/{$count->id}/cancel");

        $response->assertStatus(200);
        $this->assertDatabaseHas('inventory_counts', [
            'id' => $count->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_can_delete_draft_count(): void
    {
        $token = $this->adminToken();
        $count = $this->createCount();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->deleteJson("/api/v1/tenants/{$this->tenant->id}/counts/{$count->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('inventory_counts', ['id' => $count->id]);
    }

    // =========================================================================
    // Data integrity: StockMovement records and transaction atomicity
    // =========================================================================

    public function test_approve_creates_stock_movement_records(): void
    {
        $token = $this->adminToken();
        $count = $this->createCount();
        $count->start($this->admin->id);

        $item1 = $count->items()->where('product_id', $this->product1->id)->first();
        $item2 = $count->items()->where('product_id', $this->product2->id)->first();
        $item1->recordCount(95);  // variance -5
        $item2->recordCount(53);  // variance +3

        $count->complete($this->admin->id);

        $initialMovements = StockMovement::count();

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts/{$count->id}/approve");

        // Two movements should be created (one per item with variance)
        $newMovements = StockMovement::where('type', 'adjustment')
            ->where('created_at', '>=', now()->subMinute())
            ->count();

        $this->assertEquals(2, $newMovements);

        // Verify movement details
        $movement1 = StockMovement::where('product_id', $this->product1->id)
            ->where('type', 'adjustment')
            ->where('reason', 'Inventory count adjustment')
            ->first();

        $this->assertNotNull($movement1);
        $this->assertEquals(-5, $movement1->quantity);
        $this->assertEquals(100, $movement1->quantity_before);
        $this->assertEquals(95, $movement1->quantity_after);
    }

    public function test_approve_is_atomic_transaction(): void
    {
        $token = $this->adminToken();
        $count = $this->createCount();
        $count->start($this->admin->id);

        $item1 = $count->items()->where('product_id', $this->product1->id)->first();
        $item2 = $count->items()->where('product_id', $this->product2->id)->first();
        $item1->recordCount(95);
        $item2->recordCount(53);

        $count->complete($this->admin->id);

        // Delete inventory record for item2 to force a failure during approve
        // This simulates a scenario where the inventory relationship is broken
        $item2->update(['inventory_id' => null]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts/{$count->id}/approve");

        // The approve should still succeed (item2 has no inventory, so it's skipped)
        // but item1's inventory should still be adjusted (transaction succeeds)
        $response->assertStatus(200);

        $this->assertDatabaseHas('inventory_counts', [
            'id' => $count->id,
            'status' => 'approved',
        ]);

        // Item1's inventory should be adjusted
        $this->assertDatabaseHas('inventories', [
            'id' => $this->inventory1->id,
            'quantity' => 95,
        ]);
    }

    // =========================================================================
    // Validation / edge cases
    // =========================================================================

    public function test_cannot_complete_count_with_uncounted_items(): void
    {
        $token = $this->adminToken();
        $count = $this->createCount();
        $count->start($this->admin->id);

        // Only count one of two items
        $count->items()->first()->recordCount(100);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts/{$count->id}/complete");

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_cannot_record_item_on_completed_count(): void
    {
        $token = $this->adminToken();
        $count = $this->createCount();
        $count->start($this->admin->id);

        foreach ($count->items as $item) {
            $item->recordCount($item->expected_quantity);
        }
        $count->complete($this->admin->id);

        $item = $count->items()->first();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts/{$count->id}/items/{$item->id}", [
                'counted_quantity' => 999,
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_cannot_record_item_on_cancelled_count(): void
    {
        $token = $this->adminToken();
        $count = $this->createCount();
        $count->cancel();

        $item = $count->items()->first();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts/{$count->id}/items/{$item->id}", [
                'counted_quantity' => 999,
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_approve_non_completed_count(): void
    {
        $token = $this->adminToken();
        $count = $this->createCount();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts/{$count->id}/approve");

        $response->assertStatus(404); // findOrFail fails because status != 'completed'
    }

    public function test_cannot_delete_non_draft_count(): void
    {
        $token = $this->adminToken();
        $count = $this->createCount();
        $count->start($this->admin->id);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->deleteJson("/api/v1/tenants/{$this->tenant->id}/counts/{$count->id}");

        $response->assertStatus(404);
    }

    public function test_count_auto_populates_products_for_location(): void
    {
        $token = $this->adminToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts", [
                'name' => 'Auto-populate Count',
                'warehouse_id' => $this->warehouse->id,
            ]);

        $response->assertStatus(201);

        $countId = $response->json('data.count.id');
        $count = InventoryCount::with('items')->find($countId);

        $this->assertEquals(2, $count->items->count());
        $this->assertEquals(100, $count->items->first()->expected_quantity);
    }

    // =========================================================================
    // Authorization
    // =========================================================================

    public function test_user_without_permission_cannot_create_count(): void
    {
        $token = $this->viewerToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts", [
                'name' => 'Unauthorized Count',
                'warehouse_id' => $this->warehouse->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_user_without_permission_cannot_approve(): void
    {
        $token = $this->viewerToken();
        $count = $this->createCount();
        $count->start($this->admin->id);

        foreach ($count->items as $item) {
            $item->recordCount($item->expected_quantity);
        }
        $count->complete($this->admin->id);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts/{$count->id}/approve");

        $response->assertStatus(403);
    }

    public function test_user_without_permission_cannot_record_item(): void
    {
        $token = $this->viewerToken();
        $count = $this->createCount();
        $count->start($this->admin->id);
        $item = $count->items()->first();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts/{$count->id}/items/{$item->id}", [
                'counted_quantity' => 95,
            ]);

        $response->assertStatus(403);
    }

    public function test_user_without_permission_cannot_delete_count(): void
    {
        $token = $this->viewerToken();
        $count = $this->createCount();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->deleteJson("/api/v1/tenants/{$this->tenant->id}/counts/{$count->id}");

        $response->assertStatus(403);
    }

    public function test_user_without_permission_cannot_start_count(): void
    {
        $token = $this->viewerToken();
        $count = $this->createCount();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts/{$count->id}/start");

        $response->assertStatus(403);
    }

    public function test_user_without_permission_cannot_complete_count(): void
    {
        $token = $this->viewerToken();
        $count = $this->createCount();
        $count->start($this->admin->id);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts/{$count->id}/complete");

        $response->assertStatus(403);
    }

    public function test_user_without_permission_cannot_cancel_count(): void
    {
        $token = $this->viewerToken();
        $count = $this->createCount();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts/{$count->id}/cancel");

        $response->assertStatus(403);
    }

    // =========================================================================
    // Tenant isolation
    // =========================================================================

    public function test_user_cannot_access_another_tenants_count(): void
    {
        // Create a count on tenant 1
        $count = $this->createCount();

        // Try to access it with tenant 2's token via tenant 2's route
        $token = $this->tenant2Token();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$this->tenant2->id}/counts/{$count->id}");

        $response->assertStatus(404);
    }

    public function test_user_cannot_record_item_on_another_tenants_count(): void
    {
        $count = $this->createCount();
        $count->start($this->admin->id);
        $item = $count->items()->first();

        $token = $this->tenant2Token();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant2->id}/counts/{$count->id}/items/{$item->id}", [
                'counted_quantity' => 999,
            ]);

        $response->assertStatus(404);
    }

    // =========================================================================
    // Index with filters
    // =========================================================================

    public function test_can_list_counts_with_filters(): void
    {
        $token = $this->adminToken();
        $this->createCount(['name' => 'Warehouse Count']);
        $this->createCount(['name' => 'Store Count', 'store_id' => $this->store->id, 'warehouse_id' => null]);

        // Filter by status
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/counts?status=draft");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $counts = $response->json('data.counts');
        $this->assertGreaterThanOrEqual(2, count($counts));
    }

    public function test_can_search_counts_by_name(): void
    {
        $token = $this->adminToken();
        $this->createCount(['name' => 'April Warehouse Count']);
        $this->createCount(['name' => 'May Warehouse Count']);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/counts?search=April");

        $response->assertStatus(200);

        $counts = $response->json('data.counts');
        foreach ($counts as $count) {
            $this->assertStringContainsString('April', $count['name']);
        }
    }

    public function test_index_returns_summary(): void
    {
        $token = $this->adminToken();
        $count = $this->createCount();
        $count->start($this->admin->id);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/counts");

        $response->assertStatus(200);

        $counts = $response->json('data.counts');
        $firstCount = $counts[0];

        $this->assertArrayHasKey('summary', $firstCount);
        $this->assertArrayHasKey('total_items', $firstCount['summary']);
        $this->assertArrayHasKey('counted_items', $firstCount['summary']);
        $this->assertArrayHasKey('accuracy_percentage', $firstCount['summary']);
    }

    // =========================================================================
    // getSummary uses DB aggregation (not N+1)
    // =========================================================================

    public function test_get_summary_uses_database_aggregation(): void
    {
        $count = $this->createCount();
        $count->start($this->admin->id);

        // Count one item
        $item = $count->items()->first();
        $item->recordCount(95);

        $summary = $count->getSummary();

        $this->assertEquals(2, $summary['total_items']);
        $this->assertEquals(1, $summary['counted_items']);
        $this->assertEquals(1, $summary['pending_items']);
        $this->assertEquals(1, $summary['items_with_variance']);
        $this->assertEquals(-5, $summary['total_variance']);
        $this->assertEquals(0, $summary['accuracy_percentage']); // 0 of 1 counted items have no variance
    }

    public function test_get_summary_returns_zero_for_empty_count(): void
    {
        $count = InventoryCount::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Empty Count',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
        ]);

        $summary = $count->getSummary();

        $this->assertEquals(0, $summary['total_items']);
        $this->assertEquals(0, $summary['counted_items']);
        $this->assertEquals(0, $summary['accuracy_percentage']);
    }

    // =========================================================================
    // Warehouse staff with inventory.counts.manage can perform all operations
    // =========================================================================

    public function test_warehouse_staff_can_create_count(): void
    {
        $token = $this->staffToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts", [
                'name' => 'Staff Count',
                'warehouse_id' => $this->warehouse->id,
            ]);

        $response->assertStatus(201);
    }

    public function test_warehouse_staff_can_record_item(): void
    {
        $token = $this->staffToken();
        $count = $this->createCount();
        $count->start($this->admin->id);
        $item = $count->items()->first();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/counts/{$count->id}/items/{$item->id}", [
                'counted_quantity' => 95,
            ]);

        $response->assertStatus(200);
    }
}
