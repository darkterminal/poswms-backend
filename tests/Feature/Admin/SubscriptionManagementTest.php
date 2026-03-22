<?php

namespace Tests\Feature\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create super admin
        $this->superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'is_super_admin' => true,
        ]);

        // Create tenant
        $this->tenant = Tenant::factory()->create();
    }

    public function test_super_admin_can_update_tenant_trial(): void
    {
        $trialEndDate = now()->addDays(30)->toDateString();

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/trial", [
                'trial_ends_at' => $trialEndDate,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'tenant_id' => $this->tenant->id,
                    'tenant_name' => $this->tenant->name,
                    'trial_ends_at' => $this->tenant->fresh()->trial_ends_at->toIso8601String(),
                ],
                'message' => 'Tenant trial period updated successfully',
            ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->id,
        ]);

        $this->assertEquals(
            $trialEndDate,
            $this->tenant->fresh()->trial_ends_at->toDateString()
        );
    }

    public function test_super_admin_can_extend_tenant_trial(): void
    {
        $this->tenant->update(['trial_ends_at' => now()->addDays(10)]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/trial/extend", [
                'days' => 15,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'tenant_id' => $this->tenant->id,
                    'days_added' => 15,
                ],
                'message' => 'Tenant trial period extended successfully',
            ]);

        $freshTenant = $this->tenant->fresh();
        $this->assertLessThanOrEqual(-24, $freshTenant->trial_ends_at->diffInDays(now()));
        $this->assertGreaterThanOrEqual(-26, $freshTenant->trial_ends_at->diffInDays(now()));
    }

    public function test_super_admin_can_extend_tenant_trial_without_existing_trial(): void
    {
        $this->tenant->update(['trial_ends_at' => null]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/trial/extend", [
                'days' => 30,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Tenant trial period extended successfully',
            ]);

        $freshTenant = $this->tenant->fresh();
        $this->assertNotNull($freshTenant->trial_ends_at);
        $this->assertLessThanOrEqual(-29, $freshTenant->trial_ends_at->diffInDays(now()));
        $this->assertGreaterThanOrEqual(-31, $freshTenant->trial_ends_at->diffInDays(now()));
    }

    public function test_super_admin_can_update_tenant_subscription(): void
    {
        $subscriptionEndDate = now()->addMonths(12)->toDateString();

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/subscription", [
                'subscription_ends_at' => $subscriptionEndDate,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'tenant_id' => $this->tenant->id,
                    'subscription_ends_at' => $this->tenant->fresh()->subscription_ends_at->toIso8601String(),
                ],
                'message' => 'Tenant subscription updated successfully',
            ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $this->tenant->id,
        ]);

        $this->assertEquals(
            $subscriptionEndDate,
            $this->tenant->fresh()->subscription_ends_at->toDateString()
        );
    }

    public function test_super_admin_can_extend_tenant_subscription(): void
    {
        $this->tenant->update(['subscription_ends_at' => now()->addDays(30)]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/subscription/extend", [
                'days' => 90,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'tenant_id' => $this->tenant->id,
                    'days_added' => 90,
                ],
                'message' => 'Tenant subscription extended successfully',
            ]);

        $freshTenant = $this->tenant->fresh();
        $this->assertLessThanOrEqual(-119, $freshTenant->subscription_ends_at->diffInDays(now()));
        $this->assertGreaterThanOrEqual(-121, $freshTenant->subscription_ends_at->diffInDays(now()));
    }

    public function test_super_admin_can_extend_tenant_subscription_without_existing_subscription(): void
    {
        $this->tenant->update(['subscription_ends_at' => null]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/subscription/extend", [
                'days' => 365,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Tenant subscription extended successfully',
            ]);

        $freshTenant = $this->tenant->fresh();
        $this->assertNotNull($freshTenant->subscription_ends_at);
        $this->assertLessThanOrEqual(-364, $freshTenant->subscription_ends_at->diffInDays(now()));
        $this->assertGreaterThanOrEqual(-366, $freshTenant->subscription_ends_at->diffInDays(now()));
    }

    public function test_super_admin_can_cancel_tenant_subscription(): void
    {
        $this->tenant->update(['subscription_ends_at' => now()->addMonths(6)]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/subscription/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'tenant_id' => $this->tenant->id,
                    'cancelled_at' => now()->toIso8601String(),
                ],
                'message' => 'Tenant subscription cancelled successfully (access until end of period)',
            ]);

        // Subscription end date should remain the same (access until end of period)
        $this->assertEquals(
            now()->addMonths(6)->format('Y-m-d'),
            $this->tenant->fresh()->subscription_ends_at->format('Y-m-d')
        );
    }

    public function test_cannot_cancel_subscription_when_no_active_subscription(): void
    {
        $this->tenant->update(['subscription_ends_at' => null]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/subscription/cancel");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'NO_ACTIVE_SUBSCRIPTION',
                    'message' => 'Tenant does not have an active subscription to cancel',
                ],
            ]);
    }

    public function test_cannot_cancel_subscription_when_subscription_expired(): void
    {
        $this->tenant->update(['subscription_ends_at' => now()->subDays(30)]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/subscription/cancel");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'NO_ACTIVE_SUBSCRIPTION',
                    'message' => 'Tenant does not have an active subscription to cancel',
                ],
            ]);
    }

    public function test_super_admin_can_convert_tenant_from_trial_to_paid(): void
    {
        $this->tenant->update([
            'trial_ends_at' => now()->addDays(15),
            'subscription_ends_at' => null,
        ]);

        $subscriptionEndDate = now()->addMonths(12)->toDateString();

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/convert-to-paid", [
                'subscription_ends_at' => $subscriptionEndDate,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'tenant_id' => $this->tenant->id,
                    'subscription_ends_at' => $this->tenant->fresh()->subscription_ends_at->toIso8601String(),
                ],
                'message' => 'Tenant converted from trial to paid subscription successfully',
            ]);

        $freshTenant = $this->tenant->fresh();
        $this->assertNull($freshTenant->trial_ends_at);
        $this->assertNotNull($freshTenant->subscription_ends_at);
    }

    public function test_non_super_admin_cannot_access_subscription_endpoints(): void
    {
        $regularUser = User::factory()->create(['role' => 'admin', 'is_super_admin' => false]);

        $response = $this->actingAs($regularUser)
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/trial", [
                'trial_ends_at' => now()->addDays(30)->toDateString(),
            ]);

        $response->assertStatus(403);
    }

    public function test_subscription_endpoints_require_authentication(): void
    {
        $response = $this->postJson("/api/v1/admin/tenants/{$this->tenant->id}/trial", [
            'trial_ends_at' => now()->addDays(30)->toDateString(),
        ]);

        $response->assertStatus(401);
    }

    public function test_trial_update_validates_date_format(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/trial", [
                'trial_ends_at' => 'invalid-date',
            ]);

        $response->assertStatus(422);
        $this->assertApiValidationErrors($response, 'trial_ends_at');
    }

    public function test_subscription_update_validates_date_format(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/subscription", [
                'subscription_ends_at' => 'invalid-date',
            ]);

        $response->assertStatus(422);
        $this->assertApiValidationErrors($response, 'subscription_ends_at');
    }

    public function test_extend_trial_validates_days_field(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/trial/extend", [
                'days' => 'not-a-number',
            ]);

        $response->assertStatus(422);
        $this->assertApiValidationErrors($response, 'days');
    }

    public function test_extend_trial_requires_positive_days(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/trial/extend", [
                'days' => -5,
            ]);

        $response->assertStatus(422);
        $this->assertApiValidationErrors($response, 'days');
    }

    public function test_extend_subscription_validates_days_field(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/subscription/extend", [
                'days' => 'not-a-number',
            ]);

        $response->assertStatus(422);
        $this->assertApiValidationErrors($response, 'days');
    }

    public function test_extend_subscription_requires_positive_days(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/v1/admin/tenants/{$this->tenant->id}/subscription/extend", [
                'days' => 0,
            ]);

        $response->assertStatus(422);
        $this->assertApiValidationErrors($response, 'days');
    }
}
