<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementStatsTest extends TestCase
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

    public function test_stats_accurately_aggregates_movements(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $product = Product::factory()->forTenant($tenant->id)->create();

        // 1. Stock In: 100 units
        StockMovement::factory()->forTenant($tenant->id)->create([
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 100,
            'unit_cost' => 10.0,
            'total_cost' => 1000.0,
        ]);

        // 2. Sale: -30 units (Should be Out)
        StockMovement::factory()->forTenant($tenant->id)->create([
            'product_id' => $product->id,
            'type' => 'sale',
            'quantity' => -30,
            'unit_cost' => 10.0,
            'total_cost' => -300.0,
        ]);

        // 3. Return: 10 units (Should be In)
        StockMovement::factory()->forTenant($tenant->id)->create([
            'product_id' => $product->id,
            'type' => 'return',
            'quantity' => 10,
            'unit_cost' => 10.0,
            'total_cost' => 100.0,
        ]);

        // 4. Adjustment: -5 units (Should be Adjustment AND Out)
        StockMovement::factory()->forTenant($tenant->id)->create([
            'product_id' => $product->id,
            'type' => 'adjustment',
            'quantity' => -5,
            'unit_cost' => 10.0,
            'total_cost' => -50.0,
        ]);

        // 5. Transfer In: 20 units (Should be In AND Transfer)
        StockMovement::factory()->forTenant($tenant->id)->create([
            'product_id' => $product->id,
            'type' => 'transfer_in',
            'quantity' => 20,
            'unit_cost' => 10.0,
            'total_cost' => 200.0,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$tenant->id}/movements/stats");

        $response->assertStatus(200);
        $data = $response->json('data');

        // EXPECTED:
        // total_in: 100 (in) + 10 (return) + 20 (transfer_in) = 130
        // total_out: 30 (sale) + 5 (adjustment) = 35
        // total_adjustments: 5
        // total_transfers: 20
        // total_value: 1000 - 300 + 100 - 50 + 200 = 950

        // Current (BROKEN) logic will likely yield different results.
        // Let's see what it does.
        
        $this->assertEquals(130, $data['total_in'], 'total_in mismatch');
        $this->assertEquals(35, $data['total_out'], 'total_out mismatch');
        $this->assertEquals(5, $data['total_adjustments'], 'total_adjustments mismatch');
        $this->assertEquals(20, $data['total_transfers'], 'total_transfers mismatch');
        $this->assertEquals(950, $data['total_value'], 'total_value mismatch');
    }

    public function test_super_admin_can_get_global_stats(): void
    {
        $tenant1 = Tenant::factory()->active()->create();
        $tenant2 = Tenant::factory()->active()->create();

        $product1 = Product::factory()->forTenant($tenant1->id)->create();
        $product2 = Product::factory()->forTenant($tenant2->id)->create();

        // Create movements for both tenants
        StockMovement::factory()->create([
            'tenant_id' => $tenant1->id,
            'product_id' => $product1->id,
            'quantity' => 100,
        ]);
        StockMovement::factory()->create([
            'tenant_id' => $tenant2->id,
            'product_id' => $product2->id,
            'quantity' => 50,
        ]);

        $superAdmin = User::factory()->superAdmin()->create();
        $token = $superAdmin->createToken('admin-token')->plainTextToken;

        // Global stats (all tenants)
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/admin/pos/movements/stats");

        $response->assertStatus(200);
        $this->assertEquals(150, $response->json('data.total_in'));

        // Tenant 1 specific stats
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/admin/pos/movements/stats?tenant_id={$tenant1->id}");

        $response->assertStatus(200);
        $this->assertEquals(100, $response->json('data.total_in'));
    }

    public function test_can_export_stock_movements(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $admin = $this->createAdmin($tenant);
        $token = $admin->createToken('test-token')->plainTextToken;

        $product = Product::factory()->forTenant($tenant->id)->create();
        StockMovement::factory()->count(5)->create([
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->get("/api/v1/tenants/{$tenant->id}/movements/export");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeaderContains('Content-Disposition', 'attachment; filename="stock-movements-');
        
        $content = $response->streamedContent();
        $this->assertStringContainsString('ID', $content);
        $this->assertStringContainsString('Tenant', $content);
        $this->assertStringContainsString('Product Name', $content);
        $this->assertStringContainsString('SKU', $content);
        $this->assertStringContainsString($product->name, $content);
    }
}
