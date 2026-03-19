<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_get_their_details(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $user = User::factory()->forTenant($tenant->id)->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson("/api/v1/tenants/{$tenant->id}/auth/me");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User retrieved successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => 'Test User',
                        'email' => 'test@example.com',
                    ],
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_get_details(): void
    {
        $tenant = Tenant::factory()->active()->create();

        $response = $this->getJson("/api/v1/tenants/{$tenant->id}/auth/me");

        $response->assertStatus(401);
    }
}
