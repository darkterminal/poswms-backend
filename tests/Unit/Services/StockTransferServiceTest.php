<?php

namespace Tests\Unit\Services;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockTransferService $service;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StockTransferService::class);
        $this->tenant = Tenant::factory()->create();
    }

    public function test_transfer_between_warehouses(): void
    {
        $warehouse1 = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $warehouse2 = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product = Product::factory()->forTenant($this->tenant->id)->create();

        $inventory = Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse1->id,
            'quantity' => 100,
            'reserved' => 0,
            'available' => 100,
        ]);

        $result = $this->service->transfer(
            tenantId: $this->tenant->id,
            productId: $product->id,
            quantity: 30,
            fromWarehouseId: $warehouse1->id,
            toWarehouseId: $warehouse2->id
        );

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $warehouse1->id,
            'quantity' => 70,
        ]);
        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $warehouse2->id,
            'quantity' => 30,
        ]);
    }

    public function test_transfer_from_warehouse_to_store(): void
    {
        $warehouse = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $store = Store::factory()->forTenant($this->tenant->id)->create();
        $product = Product::factory()->forTenant($this->tenant->id)->create();

        Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'available' => 100,
        ]);

        $result = $this->service->transfer(
            tenantId: $this->tenant->id,
            productId: $product->id,
            quantity: 25,
            fromWarehouseId: $warehouse->id,
            toStoreId: $store->id
        );

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('inventories', [
            'store_id' => $store->id,
            'quantity' => 25,
        ]);
    }

    public function test_transfer_requires_source_location(): void
    {
        $product = Product::factory()->forTenant($this->tenant->id)->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Source location (warehouse or store) is required');

        $this->service->transfer(
            tenantId: $this->tenant->id,
            productId: $product->id,
            quantity: 10
        );
    }

    public function test_transfer_requires_destination_location(): void
    {
        $warehouse = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product = Product::factory()->forTenant($this->tenant->id)->create();

        Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'available' => 100,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Destination location (warehouse or store) is required');

        $this->service->transfer(
            tenantId: $this->tenant->id,
            productId: $product->id,
            quantity: 10,
            fromWarehouseId: $warehouse->id
        );
    }

    public function test_transfer_requires_source_inventory(): void
    {
        $warehouse1 = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $warehouse2 = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product = Product::factory()->forTenant($this->tenant->id)->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Source inventory not found');

        $this->service->transfer(
            tenantId: $this->tenant->id,
            productId: $product->id,
            quantity: 10,
            fromWarehouseId: $warehouse1->id,
            toWarehouseId: $warehouse2->id
        );
    }

    public function test_transfer_checks_available_quantity(): void
    {
        $warehouse1 = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $warehouse2 = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product = Product::factory()->forTenant($this->tenant->id)->create();

        Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse1->id,
            'quantity' => 50,
            'reserved' => 30,
            'available' => 20,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->service->transfer(
            tenantId: $this->tenant->id,
            productId: $product->id,
            quantity: 30,
            fromWarehouseId: $warehouse1->id,
            toWarehouseId: $warehouse2->id
        );
    }

    public function test_transfer_creates_destination_inventory_if_not_exists(): void
    {
        $warehouse1 = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $warehouse2 = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product = Product::factory()->forTenant($this->tenant->id)->create(['price' => 100, 'cost' => 50]);

        Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse1->id,
            'quantity' => 100,
            'available' => 100,
            'cost' => 50,
        ]);

        $this->service->transfer(
            tenantId: $this->tenant->id,
            productId: $product->id,
            quantity: 30,
            fromWarehouseId: $warehouse1->id,
            toWarehouseId: $warehouse2->id
        );

        $this->assertDatabaseHas('inventories', [
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse2->id,
            'quantity' => 30,
        ]);
    }

    public function test_transfer_records_stock_movements(): void
    {
        $warehouse1 = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $warehouse2 = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product = Product::factory()->forTenant($this->tenant->id)->create();

        Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse1->id,
            'quantity' => 100,
            'available' => 100,
        ]);

        $this->service->transfer(
            tenantId: $this->tenant->id,
            productId: $product->id,
            quantity: 30,
            fromWarehouseId: $warehouse1->id,
            toWarehouseId: $warehouse2->id,
            reason: 'Test transfer'
        );

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'transfer_out',
            'quantity' => 30,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'transfer_in',
            'quantity' => 30,
        ]);
    }

    public function test_get_transferable_inventory(): void
    {
        $warehouse = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product = Product::factory()->forTenant($this->tenant->id)->create();

        Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'available' => 80,
        ]);

        Inventory::factory()->forTenant($this->tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 50,
            'available' => 0,
        ]);

        $result = $this->service->getTransferableInventory(
            tenantId: $this->tenant->id,
            productId: $product->id,
            locationId: $warehouse->id
        );

        $this->assertCount(1, $result);
        $this->assertEquals(80, $result[0]['available']);
    }
}
