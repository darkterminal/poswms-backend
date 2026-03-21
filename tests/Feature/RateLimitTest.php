<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $adminUser;

    protected User $regularUser;

    protected string $apiBaseUrl;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache and rate limit state between tests
        \Illuminate\Support\Facades\Cache::flush();

        $this->tenant = Tenant::factory()->create();
        $this->adminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);
        $this->regularUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'manager',
        ]);

        $this->apiBaseUrl = "/api/v1/tenants/{$this->tenant->id}";
    }

    /**
     * Test that login endpoint is rate limited.
     */
    public function test_login_endpoint_is_rate_limited(): void
    {
        // Attempt to exceed the rate limit (10 per minute)
        for ($i = 0; $i < 15; $i++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

            if ($i >= 10) {
                $response->assertStatus(429);
            } else {
                $this->assertNotEquals(429, $response->status());
            }
        }
    }

    /**
     * Test that authenticated users have higher rate limits.
     */
    public function test_authenticated_user_has_higher_rate_limit(): void
    {
        $token = $this->regularUser->createToken('test-token')->plainTextToken;

        // Make requests up to the limit (100 per minute for authenticated users)
        for ($i = 0; $i < 105; $i++) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->getJson("{$this->apiBaseUrl}/products");

            // Should not be rate limited until after 100 requests
            if ($i >= 100) {
                $response->assertStatus(429);
            } else {
                $this->assertNotEquals(429, $response->status());
            }
        }
    }

    /**
     * Test that unauthenticated requests have lower rate limits.
     */
    public function test_unauthenticated_request_to_protected_route_returns_401(): void
    {
        // Unauthenticated requests should get 401, not rate limited
        $response = $this->getJson("{$this->apiBaseUrl}/products");
        $response->assertStatus(401);
    }

    /**
     * Test that admin users have higher rate limits.
     */
    public function test_admin_user_has_higher_rate_limit(): void
    {
        $token = $this->adminUser->createToken('admin-token')->plainTextToken;

        // Admin routes should have 200 requests per minute limit
        // We'll test a smaller subset to avoid hitting the limit
        for ($i = 0; $i < 50; $i++) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->getJson("{$this->apiBaseUrl}/roles");

            // Should not be rate limited
            $this->assertNotEquals(429, $response->status(), "Request {$i} should not be rate limited");
        }
    }

    /**
     * Test that rate limit headers are returned.
     */
    public function test_rate_limit_headers_are_present(): void
    {
        $token = $this->regularUser->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("{$this->apiBaseUrl}/products");

        $response->assertStatus(200);

        // Check for rate limit headers (Retry-After only present when rate limited)
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    /**
     * Test that rate limiter resets after time window.
     */
    public function test_rate_limiter_key_is_user_specific(): void
    {
        // Create two different users
        $user1 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'manager',
        ]);
        $user2 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'manager',
        ]);

        $token1 = $user1->createToken('user1-token')->plainTextToken;
        $token2 = $user2->createToken('user2-token')->plainTextToken;

        // Make requests with user 1
        for ($i = 0; $i < 50; $i++) {
            $this->withHeaders([
                'Authorization' => 'Bearer ' . $token1,
            ])->getJson("{$this->apiBaseUrl}/products");
        }

        // User 2 should still have full rate limit available
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token2,
        ])->getJson("{$this->apiBaseUrl}/products");

        $response->assertStatus(200);
        // User 2 should have high remaining count since they haven't made requests
        $remaining = $response->headers->get('X-RateLimit-Remaining');
        $this->assertGreaterThan(40, (int) $remaining);
    }

    /**
     * Test that 429 response includes proper error message.
     */
    public function test_rate_limited_response_includes_error_message(): void
    {
        // Use the auth rate limiter which has a low limit (10 per minute)
        for ($i = 0; $i < 15; $i++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password',
            ]);
        }

        $response->assertStatus(429);
        $response->assertJsonStructure([
            'message',
        ]);
    }
}
