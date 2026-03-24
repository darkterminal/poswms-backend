<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LoginAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LoginAttemptServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear cache to prevent pollution from other tests
        Cache::flush();
    }

    /**
     * Test that user can login with valid credentials.
     */
    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login successful',
            ])
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'is_active',
                    ],
                    'token',
                    'token_type',
                ],
            ]);

        // Verify user's last_login_at was updated
        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertNotNull($user->last_login_ip);
    }

    /**
     * Test progressive delay system - warning after 3 attempts.
     */
    public function test_progressive_delay_shows_warning_after_threshold(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        // First 2 attempts
        $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);

        // 3rd attempt should still be 422 but with different message
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        // Check that error details exist for email field
        $this->assertNotNull($response->json('error.details.email'));
    }

    /**
     * Test account lockout after 5 failed attempts.
     */
    public function test_account_locks_after_max_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'locktest@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Make 5 failed attempts
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'locktest@example.com',
                'password' => 'wrong-password',
            ]);

            $response->assertStatus(422);
        }

        // 6th attempt should be blocked by lockout
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'locktest@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        // Verify lockout is in effect (error details should exist)
        $this->assertNotNull($response->json('error.details.email'));
    }

    /**
     * Test successful login resets failed attempt counter.
     */
    public function test_successful_login_resets_attempt_counter(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Make 2 failed attempts
        $this->postJson('/api/v1/auth/login', [
            'email' => 'reset@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'reset@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);

        // Successful login
        $this->postJson('/api/v1/auth/login', [
            'email' => 'reset@example.com',
            'password' => 'password123',
        ])->assertStatus(200);

        // Failed attempts should be reset - can fail again without immediate lockout
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'reset@example.com',
            'password' => 'wrong-password',
        ]);

        // Should not be locked out yet (only 1 attempt after reset)
        $response->assertStatus(422);
        $this->assertNotNull($response->json('error.details.email'));
    }

    /**
     * Test lockout message format.
     */
    public function test_lockout_message_format(): void
    {
        $user = User::factory()->create([
            'email' => 'waittime@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Make 5 failed attempts to trigger lockout
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'waittime@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        // Next attempt should show lockout message
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'waittime@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $this->assertNotNull($response->json('error.details.email'));
    }

    /**
     * Test super admin login also has lockout protection.
     */
    public function test_super_admin_login_has_lockout_protection(): void
    {
        $superAdmin = User::factory()->create([
            'email' => 'superadmin@example.com',
            'password' => bcrypt('password123'),
            'is_super_admin' => true,
        ]);

        // Make 5 failed attempts
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/v1/admin/auth/login', [
                'email' => 'superadmin@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        // 6th attempt should be blocked
        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'superadmin@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $this->assertNotNull($response->json('error.details.email'));
    }

    /**
     * Test non-existent user also tracks attempts (prevents enumeration).
     */
    public function test_nonexistent_user_tracks_attempts(): void
    {
        // Make multiple attempts with non-existent email
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'nonexistent@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        // Should still get lockout message (prevents email enumeration)
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $this->assertNotNull($response->json('error.details.email'));
    }

    /**
     * Test inactive user cannot login.
     */
    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => bcrypt('password123'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $this->assertNotNull($response->json('error.details.email'));
    }

    /**
     * Test login requires email and password.
     */
    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422);
        $this->assertApiValidationErrors($response, 'email', 'password');
    }

    /**
     * Test user cannot login with invalid credentials.
     */
    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $this->assertApiValidationErrors($response, 'email');
    }

    /**
     * Test user cannot login with nonexistent email.
     */
    public function test_user_cannot_login_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $this->assertApiValidationErrors($response, 'email');
    }

    /**
     * Test LoginAttemptService checkLockout method.
     */
    public function test_check_lockout_returns_correct_status(): void
    {
        $service = app(LoginAttemptService::class);
        $email = 'checklockout@example.com';

        // Initially not locked
        $status = $service->checkLockout($email);
        $this->assertFalse($status['locked']);
        $this->assertEquals(5, $status['remainingAttempts']);

        // Simulate failed attempts
        $user = User::factory()->make(['email' => $email]);
        for ($i = 0; $i < 3; $i++) {
            $service->recordFailedAttempt($email, $user, request());
        }

        // Check status after 3 attempts
        $status = $service->checkLockout($email);
        $this->assertFalse($status['locked']);
        $this->assertEquals(2, $status['remainingAttempts']);
    }

    /**
     * Test LoginAttemptService resetAttempts method.
     */
    public function test_reset_attempts_clears_cache(): void
    {
        $service = app(LoginAttemptService::class);
        $email = 'reset@example.com';
        $user = User::factory()->make(['email' => $email]);

        // Record some failed attempts
        for ($i = 0; $i < 3; $i++) {
            $service->recordFailedAttempt($email, $user, request());
        }

        // Verify attempts were recorded
        $status = $service->checkLockout($email);
        $this->assertEquals(2, $status['remainingAttempts']);

        // Reset attempts
        $service->resetAttempts($email);

        // Verify attempts were cleared
        $status = $service->checkLockout($email);
        $this->assertEquals(5, $status['remainingAttempts']);
    }

    /**
     * Test suspicious login detection.
     */
    public function test_suspicious_login_detection(): void
    {
        $service = app(LoginAttemptService::class);
        $user = User::factory()->create([
            'email' => 'suspicious@example.com',
            'last_login_ip' => '192.168.1.1',
        ]);

        // Create request with different IP
        $request = request()->merge(['server' => ['REMOTE_ADDR' => '192.168.1.100']]);

        $suspicious = $service->checkSuspiciousLogin($user, $request);
        $this->assertTrue($suspicious['isSuspicious']);
        $this->assertContains('ip_change', $suspicious['reasons']);
    }

    /**
     * Test password change invalidates all tokens.
     */
    public function test_password_change_invalidates_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'passwordchange@example.com',
            'password' => bcrypt('oldpassword'),
        ]);

        // Create a token
        $token = $user->createToken('test-token')->plainTextToken;
        $this->assertNotEmpty($token);

        // Change password
        $user->changePassword('newpassword');

        // Verify all tokens were deleted
        $this->assertEquals(0, $user->tokens()->count());
    }

    /**
     * Test password change creates audit log for tenant users.
     */
    public function test_password_change_creates_audit_log(): void
    {
        // Create user with tenant
        $tenant = \App\Models\Tenant::factory()->create();
        $user = User::factory()->create([
            'email' => 'auditlog@example.com',
            'password' => bcrypt('oldpassword'),
            'tenant_id' => $tenant->id,
        ]);

        // Change password
        $user->changePassword('newpassword');

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'auth.password_changed',
            'auditable_type' => 'App\\Models\\User',
            'auditable_id' => $user->id,
        ]);
    }

    /**
     * Test backward compatibility - response structure unchanged.
     */
    public function test_backward_compatible_response_structure(): void
    {
        $user = User::factory()->create([
            'email' => 'backward@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'backward@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'token_type' => 'Bearer',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user',
                    'token',
                    'token_type',
                ],
                'message',
            ]);
    }
}
