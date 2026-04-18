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

class InventoryReportTest extends TestCase
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

    public function test_can_get_low_stock_alerts(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();
        $product = Product::factory()->forTenant($tenant->id)->create(['min_stock' => 50]);

        // Create low stock inventory
        Inventory::factory()->forTenant($tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 30,
            'available' => 30,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$tenant->id}/reports/inventory/low-stock");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_alerts',
                    'critical',
                    'warning',
                    'items',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(1, $data['total_alerts']);
    }

    public function test_can_get_inventory_report(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();
        $product = Product::factory()->forTenant($tenant->id)->create(['min_stock' => 20]);

        Inventory::factory()->forTenant($tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'available' => 80,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$tenant->id}/reports/inventory");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_products',
                        'low_stock_count',
                        'out_of_stock_count',
                        'health_percentage',
                    ],
                    'low_stock_items',
                    'out_of_stock_items',
                ],
            ]);
    }

    public function test_can_get_stock_levels_report(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();
        $product = Product::factory()->forTenant($tenant->id)->create(['price' => 100, 'cost' => 50]);

        Inventory::factory()->forTenant($tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'available' => 80,
            'reserved' => 20,
            'cost' => 50,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$tenant->id}/reports/inventory/stock-levels");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'inventories',
                    'summary' => [
                        'total_items',
                        'total_quantity',
                        'total_available',
                        'total_reserved',
                        'total_value',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(100, $data['summary']['total_quantity']);
        $this->assertEquals(80, $data['summary']['total_available']);
        $this->assertEquals(5000, $data['summary']['total_value']); // 100 * 50
    }

    public function test_low_stock_detects_critical_levels(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $warehouse = Warehouse::factory()->forTenant($tenant->id)->create();
        $product = Product::factory()->forTenant($tenant->id)->create(['min_stock' => 100]);

        // Create critical stock (0 available)
        Inventory::factory()->forTenant($tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 0,
            'available' => 0,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$tenant->id}/reports/inventory/low-stock");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(1, $data['total_alerts']);
        $this->assertEquals(1, $data['critical']);
    }

    public function test_report_filters_by_warehouse(): void
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
            'quantity' => 100,
        ]);

        Inventory::factory()->forTenant($tenant->id)->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse2->id,
            'quantity' => 50,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$tenant->id}/reports/inventory/stock-levels?warehouse_id={$warehouse1->id}");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(1, $data['summary']['total_items']);
        $this->assertEquals(100, $data['summary']['total_quantity']);
    }
}
