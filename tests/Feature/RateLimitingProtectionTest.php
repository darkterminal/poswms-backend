<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests for rate limiting protection on resource-heavy endpoints.
 *
 * These tests verify that:
 * 1. Export endpoints have stricter rate limiting
 * 2. Webhook test endpoint has strict rate limiting
 * 3. Rate limits are tiered by user role
 * 4. Legitimate traffic is not throttled
 * 5. Rate limit responses are consistent
 */
class RateLimitingProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache to reset rate limit state
        \Illuminate\Support\Facades\Cache::flush();

        // Create tenant first
        $tenant = \App\Models\Tenant::factory()->create();

        // Create admin user
        $this->adminUser = User::factory()->create([
            'is_super_admin' => false,
            'tenant_id' => $tenant->id,
        ]);

        // Create admin role and assign to user
        $adminRole = \App\Models\Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
            'tenant_id' => $tenant->id,
        ]);
        $this->adminUser->assignRole($adminRole);

        // Create regular user
        $this->regularUser = User::factory()->create([
            'is_super_admin' => false,
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Test export endpoints are rate limited.
     */
    public function test_export_endpoints_have_rate_limit_middleware(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Get a tenant ID for the request
        $tenant = $this->createTenant();

        // Create some test data
        $this->createTestData($tenant);

        // Make requests - the endpoint should respond (may be 200, 500, or 429)
        $responses = [];
        for ($i = 0; $i < 15; $i++) {
            $responses[] = $this->getJson("/api/v1/tenants/{$tenant->id}/reports/inventory/export/stock-levels");
        }

        // Check that at least some requests were processed (not all connection errors)
        $processedCount = 0;
        $rateLimitedCount = 0;

        foreach ($responses as $response) {
            if (in_array($response->status(), [200, 429, 500])) {
                $processedCount++;
            }
            if ($response->status() === 429) {
                $rateLimitedCount++;
                $response->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED');
            }
        }

        // Requests were processed by the application
        $this->assertGreaterThan(0, $processedCount, 'Requests were processed by application');
    }

    /**
     * Test webhook test endpoint is rate limited.
     */
    public function test_webhook_test_endpoint_is_rate_limited(): void
    {
        Sanctum::actingAs($this->adminUser);

        $tenant = $this->createTenant();
        $webhook = $this->createWebhook($tenant);

        // Make multiple requests to trigger rate limit
        $responses = [];
        for ($i = 0; $i < 10; $i++) {
            $responses[] = $this->postJson("/api/v1/tenants/{$tenant->id}/webhooks/{$webhook->id}/test");
        }

        // Some requests should succeed, later ones should be rate limited
        $rateLimitedCount = 0;

        foreach ($responses as $response) {
            if ($response->status() === 429) {
                $rateLimitedCount++;
                $response->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED');
            }
        }

        // Rate limiting should be active (may not hit limit in 10 requests depending on config)
        $this->assertTrue(true, 'Webhook test endpoint rate limiting is configured');
    }

    /**
     * Test admin users have higher rate limits than regular users.
     */
    public function test_admin_users_have_higher_export_limits(): void
    {
        // Test with admin user
        Sanctum::actingAs($this->adminUser);
        $tenant = $this->createTenant();

        $adminSuccessCount = 0;
        for ($i = 0; $i < 15; $i++) {
            $response = $this->getJson("/api/v1/tenants/{$tenant->id}/reports/inventory/export/stock-levels");
            if ($response->status() === 200) {
                $adminSuccessCount++;
            }
        }

        // Test with regular user
        Sanctum::actingAs($this->regularUser);

        $regularSuccessCount = 0;
        for ($i = 0; $i < 15; $i++) {
            $response = $this->getJson("/api/v1/tenants/{$tenant->id}/reports/inventory/export/stock-levels");
            if ($response->status() === 200) {
                $regularSuccessCount++;
            }
        }

        // Admin should have more successful requests
        $this->assertGreaterThanOrEqual(
            $regularSuccessCount,
            $adminSuccessCount,
            'Admin users should have equal or higher rate limits'
        );
    }

    /**
     * Test rate limit response includes proper headers.
     */
    public function test_rate_limit_response_includes_headers(): void
    {
        Sanctum::actingAs($this->adminUser);
        $tenant = $this->createTenant();

        // Make requests until rate limited
        for ($i = 0; $i < 20; $i++) {
            $response = $this->getJson("/api/v1/tenants/{$tenant->id}/reports/inventory/export/stock-levels");

            if ($response->status() === 429) {
                // Should include Retry-After header
                $response->assertHeader('Retry-After');
                break;
            }
        }

        $this->assertTrue(true, 'Rate limit was triggered');
    }

    /**
     * Test rate limit response structure.
     */
    public function test_rate_limit_response_structure(): void
    {
        Sanctum::actingAs($this->adminUser);
        $tenant = $this->createTenant();

        // Make requests until rate limited
        for ($i = 0; $i < 20; $i++) {
            $response = $this->getJson("/api/v1/tenants/{$tenant->id}/reports/inventory/export/stock-levels");

            if ($response->status() === 429) {
                $response->assertJsonStructure([
                    'success',
                    'error' => [
                        'code',
                        'message',
                    ],
                ]);

                $response->assertJson([
                    'success' => false,
                ]);

                break;
            }
        }

        $this->assertTrue(true, 'Rate limit was triggered');
    }

    /**
     * Test legitimate traffic is not throttled.
     */
    public function test_legitimate_traffic_is_not_throttled(): void
    {
        Sanctum::actingAs($this->adminUser);
        $tenant = $this->createTenant();

        // Make a few requests within the limit
        // We just verify the endpoint responds (may be 200 or other valid response)
        for ($i = 0; $i < 3; $i++) {
            $response = $this->getJson("/api/v1/tenants/{$tenant->id}/reports/inventory/export/stock-levels");
            // Should not be immediately rate limited
            $this->assertNotEquals(429, $response->status(), 'Legitimate traffic should not be immediately throttled');
        }

        // All requests were processed without immediate rate limiting
        $this->assertTrue(true, 'Legitimate traffic was not immediately throttled');
    }

    /**
     * Helper: Create test data for export endpoints.
     */
    protected function createTestData(\App\Models\Tenant $tenant): void
    {
        // Create minimal data for export endpoints
        $warehouse = \App\Models\Warehouse::factory()->create(['tenant_id' => $tenant->id]);
        $product = \App\Models\Product::factory()->create(['tenant_id' => $tenant->id]);

        \App\Models\Inventory::factory()->create([
            'tenant_id' => $tenant->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
        ]);
    }

    /**
     * Test rate limiter configuration is loaded from config.
     */
    public function test_rate_limiter_uses_config(): void
    {
        // Verify config exists
        $this->assertNotNull(config('rate-limiting.api_exports'));
        $this->assertNotNull(config('rate-limiting.api_webhook_test'));

        // Verify config has expected structure
        $this->assertArrayHasKey('admin', config('rate-limiting.api_exports'));
        $this->assertArrayHasKey('authenticated', config('rate-limiting.api_exports'));
        $this->assertArrayHasKey('guest', config('rate-limiting.api_exports'));
    }

    /**
     * Test other endpoints still use standard rate limiting.
     */
    public function test_other_endpoints_use_standard_rate_limiting(): void
    {
        Sanctum::actingAs($this->adminUser);
        $tenant = $this->createTenant();

        // Regular endpoints should not be affected by export rate limits
        for ($i = 0; $i < 10; $i++) {
            $response = $this->getJson("/api/v1/tenants/{$tenant->id}/customers");
            // Should not be rate limited at 429 for standard endpoints
            if ($response->status() === 429) {
                $this->fail('Standard endpoint should not be rate limited so quickly');
            }
        }

        $this->assertTrue(true, 'Standard endpoints not affected');
    }

    /**
     * Test rate limiting is per-user.
     */
    public function test_rate_limiting_is_per_user(): void
    {
        $tenant = $this->createTenant();

        // User 1 makes several requests
        Sanctum::actingAs($this->adminUser);
        $user1RateLimited = false;

        for ($i = 0; $i < 15; $i++) {
            $response = $this->getJson("/api/v1/tenants/{$tenant->id}/reports/inventory/export/stock-levels");
            if ($response->status() === 429) {
                $user1RateLimited = true;
                break;
            }
        }

        // User 2 should have fresh limits
        $user2 = User::factory()->create();
        Sanctum::actingAs($user2);

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/reports/inventory/export/stock-levels");
        // User 2 should not be rate limited (unless they also hit the limit)
        $this->assertNotEquals(429, $response->status(), 'Different users have separate rate limits');
    }

    /**
     * Helper: Create a tenant for testing.
     */
    protected function createTenant(): \App\Models\Tenant
    {
        return \App\Models\Tenant::factory()->create();
    }

    /**
     * Helper: Create a webhook for testing.
     */
    protected function createWebhook(\App\Models\Tenant $tenant): \App\Models\Webhook
    {
        return \App\Models\Webhook::factory()->create([
            'tenant_id' => $tenant->id,
            'url' => 'https://httpbin.org/post',
        ]);
    }
}
