<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_logout(): void
    {
        $tenant = Tenant::factory()->active()->create();
        $user = User::factory()->forTenant($tenant->id)->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson("/api/v1/tenants/{$tenant->id}/auth/logout");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logout successful',
            ]);
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $tenant = Tenant::factory()->active()->create();

        $response = $this->postJson("/api/v1/tenants/{$tenant->id}/auth/logout");

        $response->assertStatus(401);
    }
}
