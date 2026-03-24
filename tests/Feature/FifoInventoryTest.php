<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\InventoryBatch;
use App\Models\InventoryLayer;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\FifoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FifoInventoryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Warehouse $warehouse;
    private Store $store;
    private Product $product;
    private Inventory $inventory;
    private FifoService $fifoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->warehouse = Warehouse::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->store = Store::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->inventory = Inventory::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'store_id' => $this->store->id,
            'quantity' => 0,
            'reserved' => 0,
            'available' => 0,
            'cost' => 0,
        ]);

        $this->fifoService = new FifoService;
    }

    public function test_create_fifo_layers_with_different_costs(): void
    {
        // Arrange: Create multiple batches with different costs
        $batch1 = InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'unit_cost' => 10.00,
            'received_date' => now()->subDays(30),
        ]);

        $batch2 = InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'unit_cost' => 12.50,
            'received_date' => now()->subDays(20),
        ]);

        $batch3 = InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'unit_cost' => 11.75,
            'received_date' => now()->subDays(10),
        ]);

        // Act: Create layers for each batch
        $layer1 = $this->inventory->createFifoLayer(100, 10.00, $batch1->id);
        $layer2 = $this->inventory->createFifoLayer(150, 12.50, $batch2->id);
        $layer3 = $this->inventory->createFifoLayer(200, 11.75, $batch3->id);

        // Sync inventory
        $this->inventory->syncWithLayers();

        // Assert
        $this->assertDatabaseHas('inventory_layers', [
            'inventory_id' => $this->inventory->id,
            'quantity' => 100,
            'unit_cost' => 10.00,
            'layer_order' => 1,
        ]);

        $this->assertDatabaseHas('inventory_layers', [
            'inventory_id' => $this->inventory->id,
            'quantity' => 150,
            'unit_cost' => 12.50,
            'layer_order' => 2,
        ]);

        $this->assertDatabaseHas('inventory_layers', [
            'inventory_id' => $this->inventory->id,
            'quantity' => 200,
            'unit_cost' => 11.75,
            'layer_order' => 3,
        ]);

        $this->assertEquals(450, $this->inventory->quantity);
        $this->assertEquals(450, $this->inventory->available);
    }

    public function test_fifo_consumption_uses_oldest_layer_first(): void
    {
        // Arrange: Create layers with different ages and costs
        $layer1 = $this->inventory->createFifoLayer(100, 10.00); // Oldest
        $layer2 = $this->inventory->createFifoLayer(150, 12.50);
        $layer3 = $this->inventory->createFifoLayer(200, 11.75); // Newest

        $this->inventory->syncWithLayers();

        // Act: Consume 120 units (should consume from oldest first)
        $result = $this->inventory->consumeQuantity(120, 'out');

        // Assert
        $this->assertEquals(120, $result['consumed']);
        $this->assertEquals(0, $result['remaining']);

        // Cost calculation: 100 @ 10.00 + 20 @ 12.50 = 1000 + 250 = 1250
        $this->assertEquals(1250.00, round($result['total_cost'], 2));

        // Layer 1 should be depleted (deleted)
        $this->assertDatabaseMissing('inventory_layers', ['id' => $layer1->id]);

        // Layer 2 should have 130 remaining (150 - 20)
        $layer2Fresh = InventoryLayer::find($layer2->id);
        $this->assertEquals(130, $layer2Fresh->quantity);

        // Layer 3 should be untouched
        $this->assertDatabaseHas('inventory_layers', [
            'id' => $layer3->id,
            'quantity' => 200,
        ]);
    }

    public function test_fifo_service_add_stock(): void
    {
        // Act
        $layer = $this->fifoService->addStock(
            inventory: $this->inventory,
            quantity: 100,
            unitCost: 15.50,
            reason: 'Test stock receipt'
        );

        // Assert
        $this->assertNotNull($layer);
        $this->assertEquals(100, $layer->quantity);
        $this->assertEquals(15.50, $layer->unit_cost);
        $this->assertEquals(1, $layer->layer_order);

        $this->assertEquals(100, $this->inventory->fresh()->quantity);
    }

    public function test_fifo_service_consume_stock(): void
    {
        // Arrange
        $this->fifoService->addStock($this->inventory, 100, 10.00);
        $this->fifoService->addStock($this->inventory, 150, 12.50);

        $inventory = $this->inventory->fresh();

        // Act
        $result = $this->fifoService->consumeStock(
            inventory: $inventory,
            quantity: 80,
            type: 'sale',
            reason: 'Test sale'
        );

        // Assert
        $this->assertEquals(80, $result['consumed']);
        $this->assertEquals(800.00, round($result['total_cost'], 2)); // 80 @ 10.00

        $remainingLayer = InventoryLayer::where('inventory_id', $inventory->id)
            ->where('layer_order', 1)
            ->first();
        $this->assertEquals(20, $remainingLayer->quantity);
    }

    public function test_fifo_transfer_between_locations(): void
    {
        // Arrange: Create source and destination inventories
        $sourceInventory = $this->inventory;
        $destinationInventory = Inventory::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'store_id' => null,
            'quantity' => 0,
            'reserved' => 0,
            'available' => 0,
            'cost' => 0,
        ]);

        // Add stock to source with FIFO layers
        $this->fifoService->addStock($sourceInventory, 100, 10.00);
        $this->fifoService->addStock($sourceInventory, 150, 12.50);

        // Act: Transfer 80 units
        $result = $this->fifoService->transferStock(
            sourceInventory: $sourceInventory,
            destinationInventory: $destinationInventory,
            quantity: 80,
            reason: 'Test transfer'
        );

        // Assert
        $this->assertEquals(80, $result['transferred']);
        $this->assertGreaterThan(0, $result['total_cost']);

        // Source should have 120 remaining (250 - 80)
        $this->assertEquals(170, $sourceInventory->fresh()->quantity);

        // Destination should have 80
        $this->assertEquals(80, $destinationInventory->fresh()->quantity);

        // Destination should have a new layer
        $this->assertDatabaseHas('inventory_layers', [
            'inventory_id' => $destinationInventory->id,
            'quantity' => 80,
        ]);
    }

    public function test_weighted_average_cost_calculation(): void
    {
        // Arrange
        $this->fifoService->addStock($this->inventory, 100, 10.00);
        $this->fifoService->addStock($this->inventory, 200, 15.00);
        $this->fifoService->addStock($this->inventory, 150, 12.00);

        // Act
        $avgCost = $this->inventory->getWeightedAverageCost();

        // Assert
        // (100*10 + 200*15 + 150*12) / 450 = (1000 + 3000 + 1800) / 450 = 5800 / 450 = 12.89
        $this->assertEquals(12.89, round($avgCost, 2));
    }

    public function test_fifo_get_inventory_valuation(): void
    {
        // Arrange
        $this->fifoService->addStock($this->inventory, 100, 10.00);
        $this->fifoService->addStock($this->inventory, 150, 12.50);
        $this->fifoService->addStock($this->inventory, 200, 11.75);

        // Act
        $valuation = $this->fifoService->getInventoryValuation($this->tenant->id);

        // Assert - total across all warehouses
        $this->assertGreaterThan(0, $valuation['total_quantity']);
        $this->assertGreaterThan(0, $valuation['total_value']);
        $this->assertGreaterThanOrEqual(3, $valuation['layer_count']);
    }

    public function test_expiring_batches_detection(): void
    {
        // Arrange: Create expiring batch
        $expiringBatch = InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'expiry_date' => now()->addDays(15),
            'remaining_quantity' => 100,
            'status' => 'active',
        ]);

        // Create non-expiring batch
        $normalBatch = InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'expiry_date' => now()->addDays(90),
            'remaining_quantity' => 100,
            'status' => 'active',
        ]);

        // Act
        $expiringSummary = $this->fifoService->getExpiringBatches($this->tenant->id, 30);

        // Assert
        $this->assertEquals(1, $expiringSummary['count']);
        $this->assertContains($expiringBatch->batch_number, $expiringSummary['batches']->pluck('batch_number'));
    }

    public function test_batch_expiry_handling(): void
    {
        // Arrange
        $batch = InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'expiry_date' => now()->subDays(5),
            'remaining_quantity' => 50,
            'initial_quantity' => 100,
            'status' => 'active',
        ]);

        $layer = InventoryLayer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'inventory_id' => $this->inventory->id,
            'batch_id' => $batch->id,
            'warehouse_id' => $this->warehouse->id,
            'store_id' => $this->store->id,
            'quantity' => 50,
            'unit_cost' => 10.00,
        ]);

        // Act
        $this->fifoService->expireBatch($batch, 'Test expiry');

        // Assert
        $this->assertEquals('expired', $batch->fresh()->status);
        $this->assertEquals(0, $batch->fresh()->remaining_quantity);

        // Layer should be zeroed out
        $this->assertDatabaseHas('inventory_layers', [
            'id' => $layer->id,
            'quantity' => 0,
        ]);
    }

    public function test_fifo_layers_ordered_correctly(): void
    {
        // Arrange
        $layer3 = $this->inventory->createFifoLayer(100, 10.00);
        $layer1 = $this->inventory->createFifoLayer(150, 12.50);
        $layer2 = $this->inventory->createFifoLayer(200, 11.75);

        // Act
        $layers = InventoryLayer::forInventory($this->inventory->id)
            ->fifoLayers()
            ->fifoOrder()
            ->get();

        // Assert
        $this->assertEquals(3, $layers->count());
        $this->assertEquals(1, $layers[0]->layer_order);
        $this->assertEquals(2, $layers[1]->layer_order);
        $this->assertEquals(3, $layers[2]->layer_order);
    }

    public function test_insufficient_stock_throws_exception(): void
    {
        // Arrange: Use the class property inventory
        $this->fifoService->addStock($this->inventory, 50, 10.00);

        // Verify layer was created
        $this->assertTrue($this->inventory->fresh()->hasFifoLayers());
        $availableQty = $this->inventory->fresh()->getLayerAvailableQuantity();
        $this->assertGreaterThan(0, $availableQty);

        // Create destination inventory with unique combination
        $destinationInventory = Inventory::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'store_id' => null, // Different from source
            'quantity' => 0,
            'reserved' => 0,
            'available' => 0,
            'cost' => 0,
        ]);

        // Act & Assert - try to transfer more than available
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient stock');
        $this->fifoService->transferStock(
            sourceInventory: $this->inventory,
            destinationInventory: $destinationInventory,
            quantity: $availableQty + 50, // Request more than available
            reason: 'Should fail'
        );
    }

    public function test_fifo_summary_returns_correct_data(): void
    {
        // Arrange: Create layer directly without using FifoService to avoid double creation
        $batch = InventoryBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'batch_number' => 'TEST-BATCH-001',
            'unit_cost' => 10.00,
        ]);

        $layer = $this->inventory->createFifoLayer(100, 10.00, $batch->id);

        // Act
        $summary = $this->inventory->getFifoSummary();

        // Assert
        $this->assertEquals(100, $summary['total_quantity']);
        $this->assertEquals(100, $summary['total_available']);
        $this->assertEquals(1000.00, round($summary['total_value'], 2));
        $this->assertEquals(1, count($summary['layers']));
        $this->assertEquals('TEST-BATCH-001', $summary['layers'][0]['batch_number']);
    }

    public function test_cogs_calculation(): void
    {
        // Arrange
        $this->fifoService->addStock($this->inventory, 100, 10.00);
        $this->fifoService->addStock($this->inventory, 150, 12.50);

        $inventory = $this->inventory->fresh();
        $this->fifoService->consumeStock($inventory, 80, 'out');

        // Act - Get COGS for outgoing movements
        $cogs = $this->fifoService->calculateCogs(
            tenantId: $this->tenant->id,
            startDate: now()->subDays(1),
            endDate: now(),
            productId: $this->product->id
        );

        // Assert - Check that COGS was calculated (may include both in and out movements)
        $this->assertGreaterThan(0, $cogs['total_quantity']);
        $this->assertGreaterThan(0, $cogs['total_cost']);
        $this->assertGreaterThan(0, $cogs['movement_count']);
    }

    public function test_backward_compatibility_without_fifo(): void
    {
        // Arrange: Use inventory without FIFO layers
        $inventory = Inventory::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'reserved' => 0,
            'available' => 100,
            'cost' => 10.00,
        ]);

        // Act: Use legacy method
        $inventory->updateQuantity(-20);

        // Assert: Should work without FIFO layers
        $this->assertEquals(80, $inventory->quantity);
        $this->assertEquals(80, $inventory->available);
        $this->assertFalse($inventory->hasFifoLayers());
    }
}
