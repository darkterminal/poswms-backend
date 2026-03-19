<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(Tenant $tenant): User
    {
        $admin = User::factory()->forTenant($tenant->id)->create();
        \App\Models\Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin',
            'slug' => 'admin',
            'permissions' => ['*'],
            'is_system' => true,
        ]);
        $admin->assignRole('admin');
        return $admin;
    }

    public function test_can_track_inventory(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();
        $product = Product::factory()->forTenant($tenant->id)->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$tenant->id}/inventory", [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'quantity' => 100,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('inventories', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
        ]);
    }

    public function test_inventory_quantity_update(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $inventory = Inventory::factory()->forTenant($tenant->id)->create(['quantity' => 100, 'available' => 100]);

        $inventory->updateQuantity(50);

        $this->assertEquals(150, $inventory->quantity);
        $this->assertEquals(150, $inventory->available);
    }

    public function test_inventory_reserve_quantity(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $inventory = Inventory::factory()->forTenant($tenant->id)->create(['quantity' => 100, 'reserved' => 0, 'available' => 100]);

        $inventory->reserveQuantity(30);

        $this->assertEquals(30, $inventory->reserved);
        $this->assertEquals(70, $inventory->available);
    }

    public function test_stock_movement_recorded(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $product = Product::factory()->forTenant($tenant->id)->create();

        \App\Models\StockMovement::recordMovement(
            $tenant->id,
            $product->id,
            'in',
            100,
            0,
            100
        );

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 100,
        ]);
    }
}
