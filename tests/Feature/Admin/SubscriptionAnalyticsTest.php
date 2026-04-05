<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SubscriptionAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected Tenant $tenant1;
    protected Tenant $tenant2;
    protected Tenant $tenant3;

    protected function setUp(): void
    {
        parent::setUp();

        // Create super admin
        $this->superAdmin = User::factory()->create([
            'email' => 'admin@poswms.com',
            'password' => Hash::make('password'),
            'is_super_admin' => true,
        ]);

        // Create tenants with different subscription plans
        $this->tenant1 = Tenant::factory()->create([
            'name' => 'Starter Tenant',
            'subscription_plan' => 'starter',
            'subscription_ends_at' => now()->addDays(60),
            'status' => 'active',
        ]);

        $this->tenant2 = Tenant::factory()->create([
            'name' => 'Professional Tenant',
            'subscription_plan' => 'professional',
            'subscription_ends_at' => now()->addDays(20), // Expiring soon
            'status' => 'active',
        ]);

        $this->tenant3 = Tenant::factory()->create([
            'name' => 'Enterprise Tenant',
            'subscription_plan' => 'enterprise',
            'subscription_ends_at' => now()->addDays(180),
            'status' => 'active',
        ]);
    }

    /**
     * Test getting subscription statistics.
     */
    public function test_can_get_subscription_statistics(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/subscriptions/stats');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_subscriptions', 3)
            ->assertJsonPath('data.active_subscriptions', 2) // tenant2 is expiring (within 30 days)
            ->assertJsonPath('data.expiring_subscriptions', 1) // tenant2
            ->assertJsonPath('data.expired_subscriptions', 0)
            ->assertJsonPath('data.monthly_recurring_revenue', 427) // 29 + 99 + 299
            ->assertJsonPath('data.plan_distribution.starter', 1)
            ->assertJsonPath('data.plan_distribution.professional', 1)
            ->assertJsonPath('data.plan_distribution.enterprise', 1);
    }

    /**
     * Test getting expiring subscriptions.
     */
    public function test_can_get_expiring_subscriptions(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/subscriptions/expiring?days=30');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.tenants.0.name', 'Professional Tenant')
            ->assertJsonPath('data.tenants.0.subscription_plan', 'professional');
    }

    /**
     * Test getting expiring subscriptions with custom days.
     */
    public function test_can_get_expiring_subscriptions_with_custom_days(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/subscriptions/expiring?days=90');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.count', 2); // tenant1 (60 days) and tenant2 (20 days)
    }

    /**
     * Test getting subscription history.
     */
    public function test_can_get_subscription_history(): void
    {
        // Create some audit logs for subscription changes
        AuditLog::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'user_id' => $this->superAdmin->id,
            'event_type' => 'updated',
            'description' => 'Subscription plan updated',
            'auditable_type' => Tenant::class,
            'auditable_id' => $this->tenant1->id,
            'old_values' => ['subscription_plan' => 'professional'],
            'new_values' => ['subscription_plan' => 'starter'],
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/v1/admin/subscriptions/{$this->tenant1->id}/history");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant_id', $this->tenant1->id)
            ->assertJsonPath('data.total_changes', 1);
    }

    /**
     * Test getting revenue metrics.
     */
    public function test_can_get_revenue_metrics(): void
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/subscriptions/revenue?period=30d');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mrr', 427) // 29 + 99 + 299
            ->assertJsonPath('data.arr', 5124) // 427 * 12
            ->assertJsonPath('data.period', '30d');
    }

    /**
     * Test revenue metrics with different periods.
     */
    public function test_revenue_metrics_with_different_periods(): void
    {
        $periods = ['30d', '90d', '180d', '365d'];

        foreach ($periods as $period) {
            $response = $this->actingAs($this->superAdmin, 'sanctum')
                ->getJson("/api/v1/admin/subscriptions/revenue?period={$period}");

            $response->assertStatus(200)
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.period', $period);
        }
    }

    /**
     * Test subscription statistics with no subscriptions.
     */
    public function test_subscription_statistics_with_no_subscriptions(): void
    {
        Tenant::query()->delete();

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/subscriptions/stats');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_subscriptions', 0)
            ->assertJsonPath('data.monthly_recurring_revenue', 0)
            ->assertJsonPath('data.churn_rate', 0);
    }

    /**
     * Test subscription statistics with expired subscriptions.
     */
    public function test_subscription_statistics_with_expired_subscriptions(): void
    {
        $expiredTenant = Tenant::factory()->create([
            'name' => 'Expired Tenant',
            'subscription_plan' => 'starter',
            'subscription_ends_at' => now()->subDays(10),
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/subscriptions/stats');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.expired_subscriptions', 1);
    }

    /**
     * Test that non-super admin cannot access subscription analytics.
     */
    public function test_non_super_admin_cannot_access_subscription_analytics(): void
    {
        $regularUser = User::factory()->create([
            'email' => 'user@poswms.com',
            'password' => Hash::make('password'),
            'is_super_admin' => false,
        ]);

        $response = $this->actingAs($regularUser, 'sanctum')
            ->getJson('/api/v1/admin/subscriptions/stats');

        $response->assertStatus(403);
    }

    /**
     * Test subscription statistics includes correct plan distribution.
     */
    public function test_subscription_statistics_includes_correct_plan_distribution(): void
    {
        // Create more tenants with different plans
        Tenant::factory()->count(5)->create([
            'subscription_plan' => 'starter',
            'subscription_ends_at' => now()->addDays(90),
            'status' => 'active',
        ]);

        Tenant::factory()->count(3)->create([
            'subscription_plan' => 'professional',
            'subscription_ends_at' => now()->addDays(90),
            'status' => 'active',
        ]);

        Tenant::factory()->count(2)->create([
            'subscription_plan' => 'enterprise',
            'subscription_ends_at' => now()->addDays(90),
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/admin/subscriptions/stats');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.plan_distribution.starter', 6) // 1 + 5
            ->assertJsonPath('data.plan_distribution.professional', 4) // 1 + 3
            ->assertJsonPath('data.plan_distribution.enterprise', 3); // 1 + 2
    }
}
