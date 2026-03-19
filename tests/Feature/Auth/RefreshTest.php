<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_refresh_token(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $user = User::factory()->forTenant($tenant->id)->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson("/api/v1/tenants/{$tenant->id}/auth/refresh");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Token refreshed successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                ],
            ]);

        // Verify new token works
        $newToken = $response->json('data.token');
        $this->assertNotEmpty($newToken);
        $this->assertNotEquals($token, $newToken);
    }

    public function test_refresh_token_invalidates_old_token(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $user = User::factory()->forTenant($tenant->id)->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Count tokens before refresh
        $tokenCountBefore = $user->tokens()->count();
        $this->assertEquals(1, $tokenCountBefore);

        // Refresh the token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson("/api/v1/tenants/{$tenant->id}/auth/refresh");

        $response->assertStatus(200);
        $newToken = $response->json('data.token');

        // Old token should be deleted, so count should still be 1 (new token replaced old)
        $tokenCountAfter = $user->fresh()->tokens()->count();
        $this->assertEquals(1, $tokenCountAfter);

        // New token should work
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$newToken,
        ])->getJson("/api/v1/tenants/{$tenant->id}/auth/me");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User retrieved successfully',
            ]);
    }

    public function test_unauthenticated_user_cannot_refresh_token(): void
    {
        $tenant = Tenant::factory()->active()->create();

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/auth/refresh");

        $response->assertStatus(401);
    }
}
