<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTransferTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_can_transfer_stock_between_warehouses(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $warehouse1 = Warehouse::factory()->forTenant($tenant->id)->create();
        $warehouse2 = Warehouse::factory()->forTenant($tenant->id)->create();
        $product = Product::factory()->forTenant($tenant->id)->create();

        $inventory = Inventory::factory()->forTenant($tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse1->id,
            'quantity' => 100,
            'reserved' => 0,
            'available' => 100,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/inventory/transfer", [
                'product_id' => $product->id,
                'quantity' => 30,
                'from_warehouse_id' => $warehouse1->id,
                'to_warehouse_id' => $warehouse2->id,
                'reason' => 'Restocking warehouse 2',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $warehouse1->id,
            'quantity' => 70,
        ]);

        $this->assertDatabaseHas('inventories', [
            'warehouse_id' => $warehouse2->id,
            'quantity' => 30,
        ]);

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

    public function test_cannot_transfer_more_than_available(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $warehouse1 = Warehouse::factory()->forTenant($tenant->id)->create();
        $warehouse2 = Warehouse::factory()->forTenant($tenant->id)->create();
        $product = Product::factory()->forTenant($tenant->id)->create();

        Inventory::factory()->forTenant($tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse1->id,
            'quantity' => 50,
            'reserved' => 20,
            'available' => 30,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/inventory/transfer", [
                'product_id' => $product->id,
                'quantity' => 50,
                'from_warehouse_id' => $warehouse1->id,
                'to_warehouse_id' => $warehouse2->id,
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_can_transfer_from_warehouse_to_store(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();
        $store = Store::factory()->forTenant($tenant->id)->create();
        $product = Product::factory()->forTenant($tenant->id)->create();

        Inventory::factory()->forTenant($tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'reserved' => 0,
            'available' => 100,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/v1/tenants/{$tenant->id}/inventory/transfer", [
                'product_id' => $product->id,
                'quantity' => 25,
                'from_warehouse_id' => $warehouse->id,
                'to_store_id' => $store->id,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('inventories', [
            'store_id' => $store->id,
            'quantity' => 25,
        ]);
    }

    public function test_can_get_transferable_inventory(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();
        $product = Product::factory()->forTenant($tenant->id)->create();

        Inventory::factory()->forTenant($tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'available' => 80,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson("/api/v1/tenants/{$tenant->id}/inventory/product/{$product->id}/transferable");

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['inventory' => []]]);
    }
}
