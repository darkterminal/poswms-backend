<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnsureTenantIsScopedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that request succeeds with valid tenant_id.
     */
    public function test_request_succeeds_with_valid_tenant_id(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $user = User::factory()->forTenant($tenant->id)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/auth/me");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /**
     * Test that request fails without tenant_id (route not found).
     */
    public function test_request_fails_without_tenant_id(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        // Without tenant_id, the route won't match (404)
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(404);
    }

    /**
     * Test that request fails with non-existent tenant_id.
     */
    public function test_request_fails_with_nonexistent_tenant_id(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/tenants/999/auth/me');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Tenant not found',
            ])
            ->assertJsonPath('errors.tenant_id.0', 'The specified tenant does not exist');
    }

    /**
     * Test that request fails with suspended tenant.
     */
    public function test_request_fails_with_suspended_tenant(): void
    {
        $tenant = Tenant::factory()->suspended()->create();
        $user = User::factory()->forTenant($tenant->id)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/auth/me");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Tenant is not active',
            ])
            ->assertJsonPath('errors.tenant_id.0', 'The specified tenant is not active');
    }

    /**
     * Test that request fails when user does not belong to tenant.
     */
    public function test_request_fails_when_user_does_not_belong_to_tenant(): void
    {
        $tenant1 = Tenant::factory()->active()->create();
        $tenant2 = Tenant::factory()->active()->create();
        $user = User::factory()->forTenant($tenant1->id)->create();

        Sanctum::actingAs($user);

        // User belongs to tenant1 but trying to access tenant2
        $response = $this->getJson("/api/v1/tenants/{$tenant2->id}/auth/me");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Unauthorized access to tenant',
            ])
            ->assertJsonPath('errors.tenant_id.0', 'You do not have access to this tenant');
    }

    /**
     * Test that unauthenticated request fails.
     */
    public function test_unauthenticated_request_fails(): void
    {
        $tenant = Tenant::factory()->active()->create();

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/auth/me");

        $response->assertStatus(401);
    }

    /**
     * Test that tenant is attached to request.
     */
    public function test_tenant_is_attached_to_request(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $user = User::factory()->forTenant($tenant->id)->create(['email' => 'test-tenant-attach@example.com']);

        Sanctum::actingAs($user);

        // This test verifies that the middleware successfully processes the request
        // The tenant should be available in the request for controllers to use
        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/auth/me");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'email' => 'test-tenant-attach@example.com',
                    ],
                ],
            ]);
    }

    /**
     * Test logout endpoint with tenant scoping.
     */
    public function test_logout_endpoint_with_tenant_scoping(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $user = User::factory()->forTenant($tenant->id)->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/auth/logout");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logout successful',
            ]);
    }

    /**
     * Test refresh endpoint with tenant scoping.
     */
    public function test_refresh_endpoint_with_tenant_scoping(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $user = User::factory()->forTenant($tenant->id)->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/auth/refresh");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }
}
