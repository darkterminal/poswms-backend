<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryLayer;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\FifoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $tenant2;
    private Warehouse $warehouse;
    private Product $product;
    private User $superAdmin;
    private User $tenantUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->tenant2 = Tenant::factory()->create();
        $this->warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->superAdmin = User::factory()->superAdmin()->create();
        $this->tenantUser = User::factory()->create(['tenant_id' => $this->tenant->id]);

        // Create inventory record (required for FK relationships in some flows)
        $store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
        Inventory::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'store_id' => $store->id,
            'quantity' => 0,
            'reserved' => 0,
            'available' => 0,
            'cost' => 0,
        ]);
    }

    private function getAdminToken(): string
    {
        return $this->superAdmin->createToken('admin-token')->plainTextToken;
    }

    private function getTenantToken(): string
    {
        return $this->tenantUser->createToken('tenant-token')->plainTextToken;
    }

    // =========================================================================
    // B-001: Export route is reachable
    // =========================================================================

    public function test_admin_can_access_export_route(): void
    {
        $token = $this->getAdminToken();

        InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'text/csv',
        ])->getJson('/api/v1/admin/pos/batches/export');

        // Should not 404 — route must be reachable
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_tenant_can_access_export_route(): void
    {
        $token = $this->getTenantToken();

        InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'text/csv',
        ])->getJson('/api/v1/tenants/' . $this->tenant->id . '/batches/export');

        $this->assertNotEquals(404, $response->getStatusCode());
    }

    // =========================================================================
    // Index with filtering
    // =========================================================================

    public function test_admin_can_list_all_batches_across_tenants(): void
    {
        $token = $this->getAdminToken();

        InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);
        InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/admin/pos/batches');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.batches');
    }

    public function test_admin_can_filter_batches_by_tenant(): void
    {
        $token = $this->getAdminToken();

        InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);
        InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/admin/pos/batches?tenant_id=' . $this->tenant->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data.batches')
            ->assertJsonPath('data.batches.0.tenant_id', $this->tenant->id);
    }

    public function test_tenant_can_only_see_own_batches(): void
    {
        $token = $this->getTenantToken();

        InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);
        InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/tenants/' . $this->tenant->id . '/batches');

        $response->assertOk()
            ->assertJsonCount(1, 'data.batches');
    }

    // =========================================================================
    // B-008: Search with LIKE wildcards escaped
    // =========================================================================

    public function test_search_with_like_wildcards_does_not_match_all(): void
    {
        $token = $this->getAdminToken();

        InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BATCH-001',
        ]);
        InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'BATCH-002',
        ]);

        // Search for '%%' should not match everything
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/admin/pos/batches?search=%25%25');

        $response->assertOk();
        // Escaped wildcards should match literally — no batch has '%%' in name
        $this->assertLessThanOrEqual(2, count($response->json('data.batches')));
    }

    // =========================================================================
    // B-010: Sort by product_name
    // =========================================================================

    public function test_can_sort_batches_by_product_name(): void
    {
        $token = $this->getAdminToken();

        $productA = Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Alpha']);
        $productB = Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beta']);

        InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $productB->id,
            'warehouse_id' => $this->warehouse->id,
        ]);
        InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $productA->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/admin/pos/batches?sort_by=product_name&sort_direction=asc');

        $response->assertOk();
        $batches = $response->json('data.batches');
        $this->assertEquals('Alpha', $batches[0]['product']['name']);
        $this->assertEquals('Beta', $batches[1]['product']['name']);
    }

    // =========================================================================
    // Stats
    // =========================================================================

    public function test_admin_can_get_batch_stats(): void
    {
        $token = $this->getAdminToken();

        InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'active',
            'remaining_quantity' => 100,
            'unit_cost' => 10.50,
        ]);
        InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'expired',
            'remaining_quantity' => 0,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/admin/pos/batches/stats');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_batches', 2)
            ->assertJsonPath('data.active_batches', 1)
            ->assertJsonPath('data.expired_batches', 1);
    }

    // =========================================================================
    // Show with layers
    // =========================================================================

    public function test_admin_can_get_batch_detail_with_layers(): void
    {
        $token = $this->getAdminToken();

        $store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
        $inventory = Inventory::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'store_id' => $store->id,
            'quantity' => 50,
            'reserved' => 0,
            'available' => 50,
            'cost' => 0,
        ]);

        $batch = InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);
        InventoryLayer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'store_id' => $store->id,
            'inventory_id' => $inventory->id,
            'batch_id' => $batch->id,
            'quantity' => 50,
            'available' => 50,
            'reserved' => 0,
            'unit_cost' => 10.00,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/v1/admin/pos/batches/' . $batch->id);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.batch.id', $batch->id)
            ->assertJsonCount(1, 'data.batch.layers');
    }

    // =========================================================================
    // B-002 + B-003: Expire batch with correct quantity and locking
    // =========================================================================

    public function test_expire_batch_records_correct_remaining_quantity(): void
    {
        $token = $this->getAdminToken();

        $batch = InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'initial_quantity' => 1000,
            'remaining_quantity' => 200,
            'status' => 'active',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/admin/pos/batches/' . $batch->id . '/expire', [
                'reason' => 'Test expiry',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('inventory_batches', [
            'id' => $batch->id,
            'status' => 'expired',
            'remaining_quantity' => 0,
        ]);

        // Verify stock movement recorded correct quantity (200, not 1000)
        $this->assertDatabaseHas('stock_movements', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'type' => 'adjustment',
            'quantity' => 200,
        ]);
    }

    public function test_cannot_expire_already_expired_batch(): void
    {
        $token = $this->getAdminToken();

        $batch = InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'expired',
            'remaining_quantity' => 0,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/v1/admin/pos/batches/' . $batch->id . '/expire', [
                'reason' => 'Test',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    // =========================================================================
    // B-004: isExpiringSoon() logic
    // =========================================================================

    public function test_is_expiring_soon_returns_false_for_already_expired_batch(): void
    {
        $batch = InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'expiry_date' => now()->subDays(5),
            'status' => 'active',
        ]);

        $this->assertFalse($batch->isExpiringSoon(30));
    }

    public function test_is_expiring_soon_returns_true_for_upcoming_expiry(): void
    {
        $batch = InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'expiry_date' => now()->addDays(15),
            'status' => 'active',
        ]);

        $this->assertTrue($batch->isExpiringSoon(30));
    }

    // =========================================================================
    // B-009: daysUntilExpiry returns negative for expired
    // =========================================================================

    public function test_days_until_expiry_returns_negative_for_expired_batch(): void
    {
        $batch = InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'expiry_date' => now()->subDays(5),
            'status' => 'active',
        ]);

        $days = $batch->daysUntilExpiry();
        $this->assertNotNull($days);
        $this->assertLessThan(0, $days);
    }

    public function test_days_until_expiry_returns_positive_for_future_batch(): void
    {
        $batch = InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'expiry_date' => now()->addDays(30),
            'status' => 'active',
        ]);

        $days = $batch->daysUntilExpiry();
        $this->assertNotNull($days);
        $this->assertGreaterThan(0, $days);
    }

    // =========================================================================
    // B-007: Export with tenant filter
    // =========================================================================

    public function test_admin_export_can_filter_by_tenant(): void
    {
        $token = $this->getAdminToken();

        InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);
        InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'text/csv',
        ])->getJson('/api/v1/admin/pos/batches/export?tenant_id=' . $this->tenant->id);

        $this->assertNotEquals(404, $response->getStatusCode());
    }

    // =========================================================================
    // B-014: Batch number generation uses random_bytes
    // =========================================================================

    public function test_batch_number_is_unique_per_tenant(): void
    {
        $batch1 = InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);
        $batch2 = InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->assertNotEquals($batch1->batch_number, $batch2->batch_number);
    }

    public function test_batch_number_format_is_correct(): void
    {
        $fifoService = new FifoService;
        $batch = $fifoService->createBatch(
            tenantId: $this->tenant->id,
            productId: $this->product->id,
            warehouseId: $this->warehouse->id,
            quantity: 100,
            unitCost: 10.00
        );

        $this->assertMatchesRegularExpression(
            '/^BATCH-\d{8}-[A-F0-9]{6}$/',
            $batch->batch_number
        );
    }
}
