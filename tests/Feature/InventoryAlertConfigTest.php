<?php

namespace Tests\Feature;

use App\Models\InventoryAlertConfig;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAlertConfigTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $adminUser;

    private User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();

        // Create admin role with all permissions
        $adminRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin',
            'slug' => 'admin',
            'permissions' => ['*'],
            'is_system' => true,
        ]);

        // Create viewer role with only view permission
        $viewerRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Viewer',
            'slug' => 'viewer',
            'permissions' => ['inventory.reports.view'],
            'is_system' => false,
        ]);

        $this->adminUser = User::factory()->forTenant($this->tenant->id)->create();
        $this->adminUser->assignRole($adminRole);

        $this->unauthorizedUser = User::factory()->forTenant($this->tenant->id)->create();
        $this->unauthorizedUser->assignRole($viewerRole);
    }

    private function adminToken(): string
    {
        return $this->adminUser->createToken('admin-token')->plainTextToken;
    }

    private function unauthorizedUserToken(): string
    {
        return $this->unauthorizedUser->createToken('unauthorized-token')->plainTextToken;
    }

    private function noPermissionToken(): string
    {
        $userWithoutPermission = User::factory()->forTenant($this->tenant->id)->create();
        $noPermissionRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'No Permission',
            'slug' => 'no-permission',
            'permissions' => ['products.view'],
            'is_system' => false,
        ]);
        $userWithoutPermission->assignRole($noPermissionRole);

        return $userWithoutPermission->createToken('no-permission-token')->plainTextToken;
    }

    // Note: Authorization enforcement is verified by the 403 response tests below.
    // GET endpoint tests require proper Sanctum token + tenant scoping setup.

    public function test_store_requires_inventory_counts_manage_permission(): void
    {
        $warehouse = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product = Product::factory()->forTenant($this->tenant->id)->create();

        $token = $this->unauthorizedUserToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/inventory/alert-configs", [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'min_threshold' => 10,
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized. You do not have permission to create alert configurations.',
        ]);
    }

    public function test_store_creates_config_with_valid_data(): void
    {
        $warehouse = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product = Product::factory()->forTenant($this->tenant->id)->create();

        $token = $this->adminToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/inventory/alert-configs", [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'min_threshold' => 10,
                'max_threshold' => 100,
                'alert_enabled' => true,
                'email_recipients' => ['admin@example.com', 'manager@example.com'],
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Alert configuration created successfully',
        ]);
        $response->assertJsonPath('data.product.id', $product->id);
        $response->assertJsonPath('data.min_threshold', 10);
        $response->assertJsonPath('data.email_recipients', ['admin@example.com', 'manager@example.com']);
    }

    public function test_store_prevents_duplicate_config(): void
    {
        $warehouse = Warehouse::factory()->forTenant($this->tenant->id)->create();
        $product = Product::factory()->forTenant($this->tenant->id)->create();

        // Create first config
        InventoryAlertConfig::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'min_threshold' => 10,
        ]);

        // Try to create duplicate
        $token = $this->adminToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/inventory/alert-configs", [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'min_threshold' => 20,
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Alert configuration already exists for this product and location',
        ]);
    }

    public function test_show_requires_inventory_reports_view_permission(): void
    {
        $product = Product::factory()->forTenant($this->tenant->id)->create();
        $config = InventoryAlertConfig::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'min_threshold' => 10,
        ]);

        $token = $this->unauthorizedUserToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/inventory/alert-configs/{$config->id}");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_show_returns_403_without_permission(): void
    {
        $token = $this->noPermissionToken();

        $product = Product::factory()->forTenant($this->tenant->id)->create();
        $config = InventoryAlertConfig::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'min_threshold' => 10,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/inventory/alert-configs/{$config->id}");

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized. You do not have permission to view alert configurations.',
        ]);
    }

    public function test_update_requires_inventory_counts_manage_permission(): void
    {
        $product = Product::factory()->forTenant($this->tenant->id)->create();
        $config = InventoryAlertConfig::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'min_threshold' => 10,
        ]);

        $token = $this->unauthorizedUserToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->putJson("/api/v1/tenants/{$this->tenant->id}/inventory/alert-configs/{$config->id}", [
                'min_threshold' => 20,
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized. You do not have permission to update alert configurations.',
        ]);
    }

    public function test_update_modifies_config(): void
    {
        $product = Product::factory()->forTenant($this->tenant->id)->create();
        $config = InventoryAlertConfig::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'min_threshold' => 10,
        ]);

        $token = $this->adminToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->putJson("/api/v1/tenants/{$this->tenant->id}/inventory/alert-configs/{$config->id}", [
                'min_threshold' => 25,
                'alert_enabled' => false,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Alert configuration updated successfully',
        ]);
        $response->assertJsonPath('data.min_threshold', 25);
        $response->assertJsonPath('data.alert_enabled', false);
    }

    public function test_destroy_requires_inventory_counts_manage_permission(): void
    {
        $product = Product::factory()->forTenant($this->tenant->id)->create();
        $config = InventoryAlertConfig::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'min_threshold' => 10,
        ]);

        $token = $this->unauthorizedUserToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->deleteJson("/api/v1/tenants/{$this->tenant->id}/inventory/alert-configs/{$config->id}");

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized. You do not have permission to delete alert configurations.',
        ]);
    }

    public function test_destroy_deletes_config(): void
    {
        $product = Product::factory()->forTenant($this->tenant->id)->create();
        $config = InventoryAlertConfig::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'min_threshold' => 10,
        ]);

        $token = $this->adminToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->deleteJson("/api/v1/tenants/{$this->tenant->id}/inventory/alert-configs/{$config->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Alert configuration deleted successfully',
        ]);

        $this->assertDatabaseMissing('inventory_alert_configs', [
            'id' => $config->id,
        ]);
    }

    public function test_add_recipient_requires_inventory_counts_manage_permission(): void
    {
        $product = Product::factory()->forTenant($this->tenant->id)->create();
        $config = InventoryAlertConfig::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'min_threshold' => 10,
        ]);

        $token = $this->unauthorizedUserToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/inventory/alert-configs/{$config->id}/add-recipient", [
                'email' => 'newuser@example.com',
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized. You do not have permission to manage alert recipients.',
        ]);
    }

    public function test_add_recipient_adds_email(): void
    {
        $product = Product::factory()->forTenant($this->tenant->id)->create();
        $config = InventoryAlertConfig::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'min_threshold' => 10,
            'email_recipients' => ['existing@example.com'],
        ]);

        $token = $this->adminToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/inventory/alert-configs/{$config->id}/add-recipient", [
                'email' => 'newuser@example.com',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Email recipient added successfully',
        ]);
        $response->assertJsonPath('data.email_recipients', ['existing@example.com', 'newuser@example.com']);
    }

    public function test_remove_recipient_requires_inventory_counts_manage_permission(): void
    {
        $product = Product::factory()->forTenant($this->tenant->id)->create();
        $config = InventoryAlertConfig::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'min_threshold' => 10,
            'email_recipients' => ['remove@example.com'],
        ]);

        $token = $this->unauthorizedUserToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/inventory/alert-configs/{$config->id}/remove-recipient", [
                'email' => 'remove@example.com',
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthorized. You do not have permission to manage alert recipients.',
        ]);
    }

    public function test_remove_recipient_removes_email(): void
    {
        $product = Product::factory()->forTenant($this->tenant->id)->create();
        $config = InventoryAlertConfig::create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'min_threshold' => 10,
            'email_recipients' => ['keep@example.com', 'remove@example.com'],
        ]);

        $token = $this->adminToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson("/api/v1/tenants/{$this->tenant->id}/inventory/alert-configs/{$config->id}/remove-recipient", [
                'email' => 'remove@example.com',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Email recipient removed successfully',
        ]);
        $response->assertJsonPath('data.email_recipients', ['keep@example.com']);
    }

    public function test_tenant_isolation_prevents_access_to_other_tenants_configs(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherTenantProduct = Product::factory()->forTenant($otherTenant->id)->create();
        $otherTenantConfig = InventoryAlertConfig::create([
            'tenant_id' => $otherTenant->id,
            'product_id' => $otherTenantProduct->id,
            'min_threshold' => 10,
        ]);

        // Try to access other tenant's config
        $token = $this->adminToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson("/api/v1/tenants/{$this->tenant->id}/inventory/alert-configs/{$otherTenantConfig->id}");

        $response->assertStatus(404);
    }
}
