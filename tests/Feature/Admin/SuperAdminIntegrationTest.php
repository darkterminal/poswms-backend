<?php

declare(strict_types = 1);

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Super Admin Integration Tests.
 *
 * End-to-end workflow tests for Super Admin module
 * covering complete user journeys and multi-step operations
 */
class SuperAdminIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create super admin user
        $this->superAdmin = User::factory()->create([
            'email' => 'superadmin@example.com',
            'is_super_admin' => true,
        ]);

        // Login and get token
        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'superadmin@example.com',
            'password' => 'password',
        ]);

        $this->token = $response->json('data.token');
    }

    /**
     * Test complete tenant onboarding workflow.
     *
     * Flow: Create Tenant → View Tenant → Update Tenant → Get Stats
     */
    public function test_complete_tenant_onboarding_workflow(): void
    {
        // Step 1: Create a new tenant
        $tenantData = [
            'name' => 'Acme Corporation',
            'slug' => 'acme-corp',
            'company_name' => 'Acme Corporation Inc',
            'email' => 'contact@acme.com',
            'status' => 'active',
            'trial_ends_at' => now()->addDays(30)->toIso8601String(),
        ];

        $createResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/admin/tenants', $tenantData);

        $createResponse->assertStatus(201)
            ->assertJsonPath('success', true);

        $tenantId = $createResponse->json('data.tenant.id');
        $this->assertNotNull($tenantId);

        // Step 2: View the created tenant
        $viewResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson("/api/v1/admin/tenants/{$tenantId}");

        $viewResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Step 3: Update the tenant
        $updateData = [
            'name' => 'Acme Corporation Updated',
        ];

        $updateResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->putJson("/api/v1/admin/tenants/{$tenantId}", $updateData);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Step 4: Get tenant statistics
        $statsResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson("/api/v1/admin/tenants/{$tenantId}/stats");

        $statsResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * Test tenant subscription management workflow.
     *
     * Flow: Create Tenant → Update Trial → Extend Trial → Update Subscription → Extend Subscription
     */
    public function test_tenant_subscription_lifecycle_workflow(): void
    {
        // Create a tenant on trial using factory
        $tenant = Tenant::factory()->onTrial()->create();

        // Step 1: Update trial end date
        $newTrialDate = now()->addDays(45)->toIso8601String();
        $trialResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->postJson("/api/v1/admin/tenants/{$tenant->id}/trial", [
            'trial_ends_at' => $newTrialDate,
        ]);

        $trialResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Step 2: Extend trial by 15 days
        $extendResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->postJson("/api/v1/admin/tenants/{$tenant->id}/trial/extend", [
            'days' => 15,
        ]);

        $extendResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Step 3: Update subscription end date
        $newSubscriptionDate = now()->addMonths(12)->toIso8601String();
        $subscriptionResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->postJson("/api/v1/admin/tenants/{$tenant->id}/subscription", [
            'subscription_ends_at' => $newSubscriptionDate,
        ]);

        $subscriptionResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Step 4: Extend subscription by 6 months (API expects days)
        $extendSubResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->postJson("/api/v1/admin/tenants/{$tenant->id}/subscription/extend", [
            'days' => 180, // 6 months in days
        ]);

        $extendSubResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * Test tenant suspension and reactivation workflow.
     *
     * Flow: Create Active Tenant → Suspend Tenant → Activate Tenant
     */
    public function test_tenant_suspension_and_reactivation_workflow(): void
    {
        // Create an active tenant
        $tenant = Tenant::factory()->create([
            'status' => 'active',
        ]);

        // Step 1: Suspend the tenant
        $suspendResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->postJson("/api/v1/admin/tenants/{$tenant->id}/suspend");

        $suspendResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'status' => 'suspended',
        ]);

        // Step 2: Activate the tenant
        $activateResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->postJson("/api/v1/admin/tenants/{$tenant->id}/activate");

        $activateResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'status' => 'active',
        ]);
    }

    /**
     * Test user impersonation workflow.
     *
     * Flow: Create User → Generate Impersonation Token
     */
    public function test_user_impersonation_workflow(): void
    {
        // Create a regular user
        $user = User::factory()->create([
            'email' => 'john.doe@example.com',
            'name' => 'John Doe',
        ]);

        // Step 1: Generate impersonation token
        $impersonateResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->postJson("/api/v1/admin/users/{$user->id}/impersonate", [
            'duration_minutes' => 30,
        ]);

        $impersonateResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $impersonationToken = $impersonateResponse->json('data.token');
        $this->assertNotNull($impersonationToken);
        $this->assertNotEmpty($impersonationToken);
    }

    /**
     * Test system dashboard workflow.
     *
     * Flow: Get Overview → Get Revenue → Get Usage → Get Alerts
     */
    public function test_system_dashboard_workflow(): void
    {
        // Create some test data
        Tenant::factory()->count(5)->create();
        User::factory()->count(10)->create();

        // Step 1: Get system overview
        $overviewResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/dashboard');

        $overviewResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Step 2: Get revenue metrics
        $revenueResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/dashboard/revenue');

        $revenueResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Step 3: Get usage analytics
        $usageResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/dashboard/usage');

        $usageResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Step 4: Get system alerts
        $alertsResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/dashboard/alerts');

        $alertsResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * Test audit logs workflow.
     *
     * Flow: View Audit Logs → Get Summary
     */
    public function test_audit_logs_workflow(): void
    {
        // Create a test tenant to generate audit logs
        Tenant::factory()->create([
            'name' => 'Audit Test Corp',
        ]);

        // Step 1: Get global audit logs
        $logsResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/audit-logs');

        $logsResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Step 2: Get audit logs summary
        $summaryResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/audit-logs/summary');

        $summaryResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * Test system settings workflow.
     *
     * Flow: Get Settings → Clear Cache → Health Check
     */
    public function test_system_settings_workflow(): void
    {
        // Step 1: Get current system settings
        $settingsResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/settings');

        $settingsResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Step 2: Clear system cache
        $cacheResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/admin/settings/clear-cache');

        $cacheResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Step 3: Get system health
        $healthResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/settings/health');

        $healthResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * Test complete tenant lifecycle with deletion.
     *
     * Flow: Create → View → Suspend → Delete → Verify Soft Deleted
     */
    public function test_complete_tenant_lifecycle_with_deletion(): void
    {
        // Step 1: Create tenant
        $tenantData = [
            'name' => 'Lifecycle Test Inc',
            'slug' => 'lifecycle-test',
            'company_name' => 'Lifecycle Test Inc',
            'email' => 'test@lifecycle.com',
            'status' => 'active',
        ];

        $createResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/admin/tenants', $tenantData);

        $createResponse->assertStatus(201);
        $tenantId = $createResponse->json('data.tenant.id');

        // Step 2: View tenant
        $viewResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson("/api/v1/admin/tenants/{$tenantId}");

        $viewResponse->assertStatus(200);

        // Step 3: Suspend tenant
        $suspendResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->postJson("/api/v1/admin/tenants/{$tenantId}/suspend");

        $suspendResponse->assertStatus(200);

        // Step 4: Delete tenant (soft delete)
        $deleteResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->deleteJson("/api/v1/admin/tenants/{$tenantId}");

        $deleteResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Verify soft delete - should still exist in database
        $this->assertDatabaseHas('tenants', [
            'id' => $tenantId,
        ]);
    }

    /**
     * Test user search workflow.
     *
     * Flow: Create Users → Search Users → Filter by Tenant
     */
    public function test_user_search_workflow(): void
    {
        // Create test users
        $tenant = Tenant::factory()->create();
        User::factory()->count(5)->create(['tenant_id' => $tenant->id]);

        // Step 1: Search users with pagination
        $searchResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/users?page=1&per_page=10');

        $searchResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Step 2: Filter by tenant
        $filterByTenantResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson("/api/v1/admin/users?tenant_id={$tenant->id}");

        $filterByTenantResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /**
     * Test super admin authentication workflow.
     *
     * Flow: Get Profile → Logout
     */
    public function test_super_admin_authentication_workflow(): void
    {
        // Step 1: Get profile with existing token
        $meResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/auth/me');

        $meResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Step 2: Logout
        $logoutResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/admin/auth/logout');

        $logoutResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
