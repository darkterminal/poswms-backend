<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AccountLockoutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = \App\Models\Tenant::factory()->create();
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
            'tenant_id' => $tenant->id,
        ]);

        // Clear any existing lockout cache
        Cache::flush();
    }

    public function test_successful_login_resets_failed_attempts(): void
    {
        // Simulate some failed attempts
        $lockoutKey = 'login_attempts:' . strtolower(trim($this->user->email));
        Cache::put($lockoutKey, 3, 900);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Verify cache was cleared
        $this->assertNull(Cache::get($lockoutKey));
    }

    public function test_failed_login_increments_attempt_counter(): void
    {
        $lockoutKey = 'login_attempts:' . strtolower(trim($this->user->email));

        // First failed attempt
        $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'wrongpassword',
        ])->assertStatus(422);

        $this->assertEquals(1, Cache::get($lockoutKey));

        // Second failed attempt
        $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'wrongpassword',
        ])->assertStatus(422);

        $this->assertEquals(2, Cache::get($lockoutKey));
    }

    public function test_account_locks_after_max_attempts(): void
    {
        $lockoutKey = 'login_attempts:' . strtolower(trim($this->user->email));

        // Simulate 5 failed attempts
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $this->user->email,
                'password' => 'wrongpassword',
            ])->assertStatus(422);
        }

        // Verify lockout is set
        $this->assertNotNull(Cache::get($lockoutKey . ':lockout_until'));

        // Next attempt should fail with lockout message
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment('Account locked');
    }

    public function test_locked_account_cannot_login(): void
    {
        $lockoutKey = 'login_attempts:' . strtolower(trim($this->user->email));

        // Lock the account
        Cache::put($lockoutKey, 5, 900);
        Cache::put($lockoutKey . ':lockout_until', now()->addMinutes(5)->timestamp, 300);

        // Try to login with correct password
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment('Account locked');
    }

    public function test_login_attempt_on_locked_account_is_logged(): void
    {
        $lockoutKey = 'login_attempts:' . strtolower(trim($this->user->email));

        // Lock the account
        Cache::put($lockoutKey, 5, 900);
        Cache::put($lockoutKey . ':lockout_until', now()->addMinutes(5)->timestamp, 300);

        $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password123',
        ])->assertStatus(422);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'auth.login_locked',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_successful_login_is_logged(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'auth.login_success',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_failed_login_is_logged(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'wrongpassword',
        ])->assertStatus(422);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'auth.login_failed',
            'user_id' => $this->user->id,
        ]);
    }
}
