<?php

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemDashboardTest extends TestCase
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
     * Test super admin can access system overview dashboard.
     */
    public function test_super_admin_can_access_system_overview(): void
    {
        $response = $this->getJson('/api/v1/admin/dashboard', [
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tenants' => [
                        'total',
                        'active',
                        'suspended',
                        'inactive',
                        'on_trial',
                        'expiring_trials',
                    ],
                    'users' => [
                        'total',
                        'super_admins',
                        'tenant_users',
                        'without_tenant',
                    ],
                    'business' => [
                        'total_stores',
                        'total_warehouses',
                        'total_products',
                        'total_customers',
                        'total_orders',
                        'total_inventory_items',
                        'total_inventory_value',
                        'total_revenue',
                    ],
                    'system_health' => [
                        'failed_jobs',
                        'recent_webhook_failures',
                        'active_webhooks',
                        'inactive_webhooks',
                        'cache_entries',
                        'active_sessions',
                        'health_score',
                    ],
                ],
                'message',
            ]);
    }

    /**
     * Test system overview returns correct tenant metrics.
     */
    public function test_system_overview_returns_correct_tenant_metrics(): void
    {
        // Create test tenants with clear states
        Tenant::factory()->create(['status' => 'active', 'trial_ends_at' => null]);
        Tenant::factory()->create(['status' => 'active', 'trial_ends_at' => null]);
        Tenant::factory()->create(['status' => 'suspended', 'trial_ends_at' => null]);
        Tenant::factory()->create(['status' => 'inactive', 'trial_ends_at' => null]);
        Tenant::factory()->create([
            'status' => 'active',
            'trial_ends_at' => now()->addDays(3), // Expiring soon
        ]);
        Tenant::factory()->create([
            'status' => 'active',
            'trial_ends_at' => now()->addDays(10), // On trial but not expiring soon
        ]);
        Tenant::factory()->create([
            'status' => 'active',
            'trial_ends_at' => now()->subDays(1), // Trial ended
        ]);

        $response = $this->getJson('/api/v1/admin/dashboard', [
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.tenants.total', 7)
            ->assertJsonPath('data.tenants.active', 5)
            ->assertJsonPath('data.tenants.suspended', 1)
            ->assertJsonPath('data.tenants.inactive', 1)
            ->assertJsonPath('data.tenants.on_trial', 2)
            ->assertJsonPath('data.tenants.expiring_trials', 1);
    }

    /**
     * Test super admin can access revenue dashboard.
     */
    public function test_super_admin_can_access_revenue_dashboard(): void
    {
        $response = $this->getJson('/api/v1/admin/dashboard/revenue', [
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_revenue' => [
                        'total',
                        'order_count',
                        'average_order_value',
                    ],
                    'revenue_by_tenant',
                    'revenue_trends',
                    'top_performing_tenants',
                ],
                'message',
            ]);
    }

    /**
     * Test revenue dashboard accepts period parameter.
     */
    public function test_revenue_dashboard_accepts_period_parameter(): void
    {
        $periods = ['today', 'week', 'month', 'year', 'all'];

        foreach ($periods as $period) {
            $response = $this->getJson('/api/v1/admin/dashboard/revenue?period=' . $period, [
                'Authorization' => 'Bearer ' . $this->superAdminToken(),
            ]);

            $response->assertStatus(200)
                ->assertJsonPath('success', true);
        }
    }

    /**
     * Test super admin can access usage dashboard.
     */
    public function test_super_admin_can_access_usage_dashboard(): void
    {
        $response = $this->getJson('/api/v1/admin/dashboard/usage', [
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tenant_activity' => [
                        'new_tenants',
                        'activated_tenants',
                    ],
                    'user_activity' => [
                        'new_users',
                    ],
                    'resource_usage' => [
                        'total_stores',
                        'total_warehouses',
                        'total_products',
                        'total_inventory_items',
                    ],
                    'api_usage' => [
                        'total_api_tokens',
                        'tokens_used_today',
                    ],
                ],
                'message',
            ]);
    }

    /**
     * Test super admin can access alerts dashboard.
     */
    public function test_super_admin_can_access_alerts_dashboard(): void
    {
        $response = $this->getJson('/api/v1/admin/dashboard/alerts', [
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tenant_alerts',
                    'system_alerts',
                    'recent_issues',
                ],
                'message',
            ]);
    }

    /**
     * Test alerts dashboard returns expiring trial alerts.
     */
    public function test_alerts_dashboard_returns_expiring_trial_alerts(): void
    {
        // Create tenant with expiring trial
        Tenant::factory()->create([
            'name' => 'Expiring Trial Tenant',
            'trial_ends_at' => now()->addDays(5),
        ]);

        $response = $this->getJson('/api/v1/admin/dashboard/alerts', [
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'type' => 'trial_expiring',
            ]);
    }

    /**
     * Test alerts dashboard returns suspended tenant alerts.
     */
    public function test_alerts_dashboard_returns_suspended_tenant_alerts(): void
    {
        // Create suspended tenant
        Tenant::factory()->create([
            'name' => 'Suspended Tenant',
            'status' => 'suspended',
        ]);

        $response = $this->getJson('/api/v1/admin/dashboard/alerts', [
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'type' => 'tenant_suspended',
            ]);
    }

    /**
     * Test unauthenticated user cannot access dashboard endpoints.
     */
    public function test_unauthenticated_user_cannot_access_dashboard_endpoints(): void
    {
        $endpoints = [
            '/api/v1/admin/dashboard',
            '/api/v1/admin/dashboard/revenue',
            '/api/v1/admin/dashboard/usage',
            '/api/v1/admin/dashboard/alerts',
        ];

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint)
                ->assertStatus(401);
        }
    }

    /**
     * Test regular user cannot access dashboard endpoints.
     */
    public function test_regular_user_cannot_access_dashboard_endpoints(): void
    {
        $regularUser = User::factory()->create(['is_super_admin' => false]);
        $token = $regularUser->createToken('regular-user-token')->plainTextToken;

        $endpoints = [
            '/api/v1/admin/dashboard',
            '/api/v1/admin/dashboard/revenue',
            '/api/v1/admin/dashboard/usage',
            '/api/v1/admin/dashboard/alerts',
        ];

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint, [
                'Authorization' => 'Bearer ' . $token,
            ])
                ->assertStatus(403)
                ->assertJsonPath('error.code', 'access_denied')
                ->assertJsonPath('error.message', 'Unauthorized. Super admin access required.');
        }
    }

    /**
     * Test system health score calculation.
     */
    public function test_system_health_score_is_calculated(): void
    {
        $response = $this->getJson('/api/v1/admin/dashboard', [
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ]);

        $response->assertStatus(200);

        $healthScore = $response->json('data.system_health.health_score');
        $this->assertIsNumeric($healthScore);
        $this->assertGreaterThanOrEqual(0, $healthScore);
        $this->assertLessThanOrEqual(100, $healthScore);
    }

    /**
     * Test revenue trends returns data structure.
     */
    public function test_revenue_trends_returns_data_structure(): void
    {
        $response = $this->getJson('/api/v1/admin/dashboard/revenue', [
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ]);

        $response->assertStatus(200);

        $trends = $response->json('data.revenue_trends');
        $this->assertIsArray($trends);
    }

    /**
     * Test top performing tenants returns data structure.
     */
    public function test_top_performing_tenants_returns_data_structure(): void
    {
        $response = $this->getJson('/api/v1/admin/dashboard/revenue', [
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ]);

        $response->assertStatus(200);

        $tenants = $response->json('data.top_performing_tenants');
        $this->assertIsArray($tenants);
    }

    /**
     * Test revenue by tenant returns data structure.
     */
    public function test_revenue_by_tenant_returns_data_structure(): void
    {
        $response = $this->getJson('/api/v1/admin/dashboard/revenue', [
            'Authorization' => 'Bearer ' . $this->superAdminToken(),
        ]);

        $response->assertStatus(200);

        $tenants = $response->json('data.revenue_by_tenant');
        $this->assertIsArray($tenants);
    }

    /**
     * Test dashboard endpoints include success flag.
     */
    public function test_dashboard_endpoints_include_success_flag(): void
    {
        $endpoints = [
            '/api/v1/admin/dashboard',
            '/api/v1/admin/dashboard/revenue',
            '/api/v1/admin/dashboard/usage',
            '/api/v1/admin/dashboard/alerts',
        ];

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint, [
                'Authorization' => 'Bearer ' . $this->superAdminToken(),
            ])
                ->assertStatus(200)
                ->assertJsonPath('success', true);
        }
    }
}
