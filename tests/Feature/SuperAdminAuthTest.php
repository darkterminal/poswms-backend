<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear cache to prevent rate limit pollution from other tests
        \Illuminate\Support\Facades\Cache::flush();
    }

    private function createSuperAdmin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    private function createRegularUser(): User
    {
        return User::factory()->create([
            'is_super_admin' => false,
        ]);
    }

    /**
     * Test super admin can login with valid credentials.
     */
    public function test_super_admin_can_login(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => $superAdmin->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'is_super_admin'],
                    'token',
                    'token_type',
                ],
                'message',
            ]);
    }

    /**
     * Test regular user cannot login as super admin.
     */
    public function test_regular_user_cannot_login_as_super_admin(): void
    {
        $regularUser = $this->createRegularUser();

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => $regularUser->email,
            'password' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    /**
     * Test super admin login fails with invalid credentials.
     */
    public function test_super_admin_login_fails_with_invalid_credentials(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => $superAdmin->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    /**
     * Test super admin login fails with non-existent email.
     */
    public function test_super_admin_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    /**
     * Test super admin can logout.
     */
    public function test_super_admin_can_logout(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $token = $superAdmin->createToken('super-admin-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/admin/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Super admin logout successful');
    }

    /**
     * Test super admin can get authenticated user details.
     */
    public function test_super_admin_can_get_me(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $token = $superAdmin->createToken('super-admin-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/admin/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'is_super_admin'],
                ],
                'message',
            ])
            ->assertJsonPath('data.user.id', $superAdmin->id);
    }

    /**
     * Test unauthenticated user cannot access super admin endpoints.
     */
    public function test_unauthenticated_user_cannot_access_super_admin_endpoints(): void
    {
        $response = $this->getJson('/api/v1/admin/auth/me');

        $response->assertStatus(401);
    }

    /**
     * Test regular user cannot access super admin endpoints.
     */
    public function test_regular_user_cannot_access_super_admin_endpoints(): void
    {
        $regularUser = $this->createRegularUser();
        $token = $regularUser->createToken('regular-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/admin/auth/me');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthorized. Super admin access required.');
    }
}
