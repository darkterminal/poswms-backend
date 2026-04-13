<?php

namespace Tests\Feature;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
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

    public function test_can_add_stock(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();
        $product = Product::factory()->forTenant($tenant->id)->create();

        $inventory = Inventory::factory()->forTenant($tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'available' => 100,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$tenant->id}/inventory/adjust", [
                'inventory_id' => $inventory->id,
                'quantity' => 50,
                'adjustment_type' => 'add',
                'reason' => 'Stock count correction',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'quantity' => 150,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'inventory_id' => $inventory->id,
            'type' => 'adjustment',
            'quantity' => 50,
        ]);
    }

    public function test_can_subtract_stock(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();
        $product = Product::factory()->forTenant($tenant->id)->create();

        $inventory = Inventory::factory()->forTenant($tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'available' => 100,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$tenant->id}/inventory/adjust", [
                'inventory_id' => $inventory->id,
                'quantity' => 30,
                'adjustment_type' => 'subtract',
                'reason' => 'Damaged goods',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'quantity' => 70,
        ]);
    }

    public function test_can_set_exact_quantity(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();
        $product = Product::factory()->forTenant($tenant->id)->create();

        $inventory = Inventory::factory()->forTenant($tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'available' => 100,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$tenant->id}/inventory/adjust", [
                'inventory_id' => $inventory->id,
                'quantity' => 150,
                'adjustment_type' => 'set',
                'reason' => 'Physical stock count',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'quantity' => 150,
        ]);
    }

    public function test_cannot_subtract_more_than_available(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();
        $product = Product::factory()->forTenant($tenant->id)->create();

        $inventory = Inventory::factory()->forTenant($tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 50,
            'available' => 50,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$tenant->id}/inventory/adjust", [
                'inventory_id' => $inventory->id,
                'quantity' => 100,
                'adjustment_type' => 'subtract',
                'reason' => 'Should fail',
            ]);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_add_stock_with_unit_cost_creates_fifo_layer(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();
        $product = Product::factory()->forTenant($tenant->id)->create();

        $inventory = Inventory::factory()->forTenant($tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 0,
            'available' => 0,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$tenant->id}/inventory/adjust", [
                'inventory_id' => $inventory->id,
                'quantity' => 100,
                'adjustment_type' => 'add',
                'unit_cost' => 15.50,
                'batch_number' => 'TEST-BATCH-001',
                'reason' => 'New stock received',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('inventories', [
            'id' => $inventory->id,
            'quantity' => 100,
        ]);

        $this->assertDatabaseHas('inventory_layers', [
            'inventory_id' => $inventory->id,
            'quantity' => 100,
            'unit_cost' => 15.50,
        ]);

        $this->assertDatabaseHas('inventory_batches', [
            'batch_number' => 'TEST-BATCH-001',
            'unit_cost' => 15.50,
        ]);
    }

    public function test_can_get_adjustment_history(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();
        $product = Product::factory()->forTenant($tenant->id)->create();

        $inventory = Inventory::factory()->forTenant($tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'available' => 100,
        ]);

        // Make an adjustment
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$tenant->id}/inventory/adjust", [
                'inventory_id' => $inventory->id,
                'quantity' => 50,
                'adjustment_type' => 'add',
                'reason' => 'Stock count correction',
            ]);

        // Get history
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$tenant->id}/inventory/{$inventory->id}/adjustments");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'inventory',
                    'movements' => [
                        '*' => [
                            'id',
                            'type',
                            'quantity',
                            'quantity_before',
                            'quantity_after',
                            'reason',
                            'reference',
                            'created_at',
                        ],
                    ],
                ],
            ]);
    }

    public function test_validation_requires_reason(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();
        $product = Product::factory()->forTenant($tenant->id)->create();

        $inventory = Inventory::factory()->forTenant($tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
        ]);

        // Create non-admin user without wildcard permissions
        $user = User::factory()->forTenant($tenant->id)->create();
        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Staff',
            'slug' => 'staff',
            'permissions' => ['inventory.adjust'],
            'is_system' => false,
        ]);
        $user->assignRole('staff');
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$tenant->id}/inventory/adjust", [
                'inventory_id' => $inventory->id,
                'quantity' => 50,
                'adjustment_type' => 'add',
                // Missing 'reason'
            ]);

        $response->assertStatus(422);
    }

    public function test_validation_requires_valid_adjustment_type(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();
        $product = Product::factory()->forTenant($tenant->id)->create();

        $inventory = Inventory::factory()->forTenant($tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
        ]);

        // Create non-admin user
        $user = User::factory()->forTenant($tenant->id)->create();
        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Staff',
            'slug' => 'staff',
            'permissions' => ['inventory.adjust'],
            'is_system' => false,
        ]);
        $user->assignRole('staff');
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$tenant->id}/inventory/adjust", [
                'inventory_id' => $inventory->id,
                'quantity' => 50,
                'adjustment_type' => 'invalid_type',
                'reason' => 'Test',
            ]);

        $response->assertStatus(422);
    }
}
