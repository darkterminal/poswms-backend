<?php

namespace Tests\Feature;

use App\AuditLogService;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $adminUser;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();

        // Create admin role and user
        $adminRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Administrator role',
            'permissions' => [],
            'is_system' => true,
        ]);

        $this->adminUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->adminUser->assignRole($adminRole);

        // Create manager role and user
        $managerRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Manager',
            'slug' => 'manager',
            'description' => 'Manager role',
            'permissions' => [],
            'is_system' => true,
        ]);

        $this->regularUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->regularUser->assignRole($managerRole);
    }

    public function test_audit_log_creation_via_service(): void
    {
        $service = app(AuditLogService::class);

        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $auditLog = $service->logCreated($product);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'created',
            'auditable_type' => Product::class,
            'auditable_id' => $product->id,
        ]);

        $this->assertEquals('created', $auditLog->event_type);
        $this->assertEquals($product->id, $auditLog->auditable_id);
        $this->assertNotNull($auditLog->new_values);
    }

    public function test_audit_log_update_event(): void
    {
        $service = app(AuditLogService::class);

        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Original Name',
            'price' => 100,
        ]);

        $oldValues = ['name' => 'Original Name', 'price' => 100];
        $newValues = ['name' => 'Updated Name', 'price' => 150];

        $auditLog = $service->logUpdated($product, $oldValues, $newValues);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'updated',
            'auditable_type' => Product::class,
            'auditable_id' => $product->id,
        ]);

        $this->assertEquals('updated', $auditLog->event_type);
        $this->assertEquals(['name' => 'Original Name', 'price' => 100], $auditLog->old_values);
        $this->assertEquals(['name' => 'Updated Name', 'price' => 150], $auditLog->new_values);
    }

    public function test_audit_log_delete_event(): void
    {
        $service = app(AuditLogService::class);

        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $productValues = $product->toArray();

        $auditLog = $service->logDeleted($product, $productValues);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'deleted',
            'auditable_type' => Product::class,
            'auditable_id' => $product->id,
        ]);

        $this->assertEquals('deleted', $auditLog->event_type);
        $this->assertNotNull($auditLog->old_values);
    }

    public function test_audit_log_login_event(): void
    {
        $service = app(AuditLogService::class);

        $auditLog = $service->logLogin($this->adminUser);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'logged_in',
            'auditable_type' => User::class,
            'auditable_id' => $this->adminUser->id,
            'user_id' => $this->adminUser->id,
        ]);

        $this->assertEquals('logged_in', $auditLog->event_type);
        $this->assertArrayHasKey('email', $auditLog->metadata);
        $this->assertArrayHasKey('role', $auditLog->metadata);
    }

    public function test_audit_log_logout_event(): void
    {
        $service = app(AuditLogService::class);

        $auditLog = $service->logLogout($this->adminUser);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'logged_out',
            'auditable_type' => User::class,
            'auditable_id' => $this->adminUser->id,
        ]);

        $this->assertEquals('logged_out', $auditLog->event_type);
    }

    public function test_audit_log_index_endpoint(): void
    {
        AuditLog::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/audit-logs");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'tenant_id',
                        'user_id',
                        'event_type',
                        'auditable_type',
                        'auditable_id',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_audit_log_show_endpoint(): void
    {
        $auditLog = AuditLog::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/audit-logs/{$auditLog->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $auditLog->id);
    }

    public function test_audit_log_summary_endpoint(): void
    {
        AuditLog::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'event_type' => 'created',
        ]);
        AuditLog::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'event_type' => 'updated',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/audit-logs/summary");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_events',
                    'by_event_type',
                    'by_user',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(5, $data['total_events']);
    }

    public function test_audit_log_filter_by_event_type(): void
    {
        AuditLog::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'event_type' => 'created',
        ]);
        AuditLog::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'event_type' => 'deleted',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/audit-logs?event_type=created");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_audit_log_filter_by_user(): void
    {
        AuditLog::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
        ]);
        AuditLog::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->regularUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/audit-logs?user_id={$this->adminUser->id}");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_audit_log_filter_by_date_range(): void
    {
        $startDate = now()->subDays(5)->format('Y-m-d');
        $endDate = now()->subDays(2)->format('Y-m-d');

        AuditLog::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now()->subDays(3),
        ]);
        AuditLog::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/audit-logs?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_audit_log_by_user_endpoint(): void
    {
        AuditLog::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/audit-logs/by-user/{$this->adminUser->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                ],
            ]);
    }

    public function test_audit_log_requires_admin_role(): void
    {
        $storeStaffRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Store Staff',
            'slug' => 'store_staff',
            'description' => 'Store staff role',
            'permissions' => [],
            'is_system' => true,
        ]);

        $nonAdminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $nonAdminUser->assignRole($storeStaffRole);

        $response = $this->actingAs($nonAdminUser)
            ->getJson("/api/v1/tenants/{$this->tenant->id}/audit-logs");

        $response->assertStatus(403);
    }

    public function test_audit_log_requires_authentication(): void
    {
        $response = $this->getJson("/api/v1/tenants/{$this->tenant->id}/audit-logs");

        $response->assertStatus(401);
    }

    public function test_audit_log_observer_logs_create_event(): void
    {
        $this->actingAs($this->adminUser);

        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'created',
            'auditable_type' => Product::class,
            'auditable_id' => $product->id,
        ]);
    }

    public function test_audit_log_observer_logs_update_event(): void
    {
        $this->actingAs($this->adminUser);

        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Original Name',
        ]);

        $product->update(['name' => 'Updated Name']);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'updated',
            'auditable_type' => Product::class,
            'auditable_id' => $product->id,
        ]);

        $auditLog = AuditLog::where('auditable_type', Product::class)
            ->where('auditable_id', $product->id)
            ->where('event_type', 'updated')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertArrayHasKey('name', $auditLog->new_values);
    }

    public function test_audit_log_observer_logs_delete_event(): void
    {
        $this->actingAs($this->adminUser);

        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
        $productId = $product->id;

        $product->delete();

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'deleted',
            'auditable_type' => Product::class,
            'auditable_id' => $productId,
        ]);
    }

    public function test_audit_log_scopes(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        AuditLog::factory()->count(3)->create(['tenant_id' => $tenant1->id]);
        AuditLog::factory()->count(2)->create(['tenant_id' => $tenant2->id]);

        $tenant1Logs = AuditLog::forTenant($tenant1->id)->count();
        $tenant2Logs = AuditLog::forTenant($tenant2->id)->count();

        $this->assertEquals(3, $tenant1Logs);
        $this->assertEquals(2, $tenant2Logs);
    }

    public function test_audit_log_metadata_is_stored(): void
    {
        $service = app(AuditLogService::class);

        $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

        $metadata = ['source' => 'API', 'reason' => 'Bulk import', 'batch_id' => 123];
        $auditLog = $service->logCreated($product, $metadata);

        $this->assertEquals('API', $auditLog->metadata['source']);
        $this->assertEquals('Bulk import', $auditLog->metadata['reason']);
        $this->assertEquals(123, $auditLog->metadata['batch_id']);
    }
}

/**
 * Tests for Super Admin Global Audit Log endpoints.
 */
class GlobalAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperAdmin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    private function superAdminToken(): string
    {
        return $this->createSuperAdmin()->createToken('super-admin-token')->plainTextToken;
    }

    /**
     * Test super admin can access global audit logs.
     */
    public function test_super_admin_can_access_global_audit_logs(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        // Create audit logs for both tenants
        AuditLog::factory()->count(3)->create(['tenant_id' => $tenant1->id]);
        AuditLog::factory()->count(2)->create(['tenant_id' => $tenant2->id]);

        $response = $this->getJson('/api/v1/admin/audit-logs', [
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
                'message',
            ]);

        // Should return all 5 audit logs
        $this->assertCount(5, $response->json('data'));
    }

    /**
     * Test super admin can filter global audit logs by tenant.
     */
    public function test_super_admin_can_filter_audit_logs_by_tenant(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        AuditLog::factory()->count(3)->create(['tenant_id' => $tenant1->id]);
        AuditLog::factory()->count(2)->create(['tenant_id' => $tenant2->id]);

        $response = $this->getJson('/api/v1/admin/audit-logs?tenant_id=' . $tenant1->id, [
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ]);

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    /**
     * Test super admin can filter global audit logs by event type.
     */
    public function test_super_admin_can_filter_audit_logs_by_event_type(): void
    {
        $tenant = Tenant::factory()->create();

        AuditLog::factory()->count(2)->create(['tenant_id' => $tenant->id, 'event_type' => 'created']);
        AuditLog::factory()->count(3)->create(['tenant_id' => $tenant->id, 'event_type' => 'updated']);

        $response = $this->getJson('/api/v1/admin/audit-logs?event_type=created', [
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ]);

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    /**
     * Test super admin can access global audit summary.
     */
    public function test_super_admin_can_access_global_audit_summary(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        AuditLog::factory()->count(3)->create(['tenant_id' => $tenant1->id, 'event_type' => 'created']);
        AuditLog::factory()->count(2)->create(['tenant_id' => $tenant2->id, 'event_type' => 'updated']);

        $response = $this->getJson('/api/v1/admin/audit-logs/summary', [
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_events',
                    'by_event_type',
                    'by_tenant',
                    'by_user',
                    'recent_activity',
                ],
            ]);

        $this->assertEquals(5, $response->json('data.total_events'));
    }

    /**
     * Test unauthenticated user cannot access global audit logs.
     */
    public function test_unauthenticated_user_cannot_access_global_audit_logs(): void
    {
        $this->getJson('/api/v1/admin/audit-logs')
            ->assertStatus(401);
    }

    /**
     * Test regular user cannot access global audit logs.
     */
    public function test_regular_user_cannot_access_global_audit_logs(): void
    {
        $regularUser = User::factory()->create(['is_super_admin' => false]);
        $token = $regularUser->createToken('regular-user-token')->plainTextToken;

        $this->getJson('/api/v1/admin/audit-logs', [
            'Authorization' => 'Bearer ' . $token,
        ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Unauthorized. Super admin access required.');
    }
}
