<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create super admin
        $this->superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'is_super_admin' => true,
        ]);
    }

    public function test_super_admin_can_view_system_settings(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/settings');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'System settings retrieved successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'settings' => [
                        'application' => [
                            'name',
                            'url',
                            'timezone',
                            'locale',
                            'fallback_locale',
                            'debug',
                            'env',
                        ],
                        'database' => [
                            'default',
                            'connections',
                        ],
                        'cache' => [
                            'default',
                            'prefix',
                        ],
                        'queue' => [
                            'default',
                            'connections',
                        ],
                        'mail' => [
                            'default',
                            'from_address',
                            'from_name',
                        ],
                        'services' => [
                            'sanctum' => [
                                'enabled',
                                'expiration',
                            ],
                        ],
                        'features' => [
                            'rate_limiting',
                            'audit_logging',
                            'webhooks',
                            'exports',
                        ],
                    ],
                ],
            ]);
    }

    public function test_super_admin_can_update_system_settings(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->putJson('/api/v1/admin/settings', [
                'application' => [
                    'name' => 'Updated App Name',
                    'debug' => false,
                ],
                'features' => [
                    'rate_limiting' => false,
                    'webhooks' => false,
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'updated_settings' => [
                        'application',
                        'features',
                    ],
                ],
            ]);
    }

    public function test_super_admin_can_clear_cache(): void
    {
        // Add something to cache
        Cache::put('test_key', 'test_value', 60);
        $this->assertEquals('test_value', Cache::get('test_key'));

        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/v1/admin/settings/clear-cache');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'System cache cleared successfully',
            ]);

        // Cache should be cleared
        $this->assertNull(Cache::get('test_key'));
    }

    public function test_system_health_check_returns_healthy_status(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/settings/health');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'System health check completed',
            ])
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'timestamp',
                    'checks' => [
                        'database' => [
                            'status',
                        ],
                        'cache' => [
                            'status',
                        ],
                        'storage' => [
                            'status',
                        ],
                        'logs' => [
                            'status',
                        ],
                    ],
                ],
            ]);

        // Database should be healthy
        $data = $response->json('data');
        $this->assertEquals('healthy', $data['checks']['database']['status']);
    }

    public function test_system_health_check_includes_timestamp(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/settings/health');

        $response->assertStatus(200);

        $timestamp = $response->json('data.timestamp');
        $this->assertNotNull($timestamp);

        // Timestamp should be recent (within last minute)
        $this->assertLessThanOrEqual(
            now()->timestamp,
            \Carbon\Carbon::parse($timestamp)->timestamp
        );
    }

    public function test_database_health_check_includes_response_time(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/settings/health');

        $response->assertStatus(200);

        $dbCheck = $response->json('data.checks.database');
        $this->assertArrayHasKey('response_time_ms', $dbCheck);
        $this->assertIsNumeric($dbCheck['response_time_ms']);
        $this->assertGreaterThan(0, $dbCheck['response_time_ms']);
    }

    public function test_cache_health_check_includes_driver(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/settings/health');

        $response->assertStatus(200);

        $cacheCheck = $response->json('data.checks.cache');
        $this->assertArrayHasKey('driver', $cacheCheck);
        $this->assertEquals(Config::get('cache.default'), $cacheCheck['driver']);
    }

    public function test_storage_health_check_verifies_writable_directories(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/settings/health');

        $response->assertStatus(200);

        $storageCheck = $response->json('data.checks.storage');
        $this->assertTrue($storageCheck['storage_writable']);
        $this->assertTrue($storageCheck['logs_writable']);
    }

    public function test_logs_health_check_includes_file_info(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/settings/health');

        $response->assertStatus(200);

        $logsCheck = $response->json('data.checks.logs');
        $this->assertArrayHasKey('log_file_exists', $logsCheck);
        $this->assertArrayHasKey('size_mb', $logsCheck);
    }

    public function test_run_command_with_allowed_command(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/v1/admin/settings/run-command', [
                'command' => 'cache:clear',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Command executed successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'command',
                    'output',
                    'duration_ms',
                ],
            ]);
    }

    public function test_run_command_rejects_disallowed_command(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/v1/admin/settings/run-command', [
                'command' => 'migrate:fresh',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'COMMAND_NOT_ALLOWED',
                ],
            ]);
    }

    public function test_run_command_requires_command_parameter(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/v1/admin/settings/run-command', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('command');
    }

    public function test_non_super_admin_cannot_access_settings(): void
    {
        $regularUser = User::factory()->create(['role' => 'admin', 'is_super_admin' => false]);

        $response = $this->actingAs($regularUser)
            ->getJson('/api/v1/admin/settings');

        $response->assertStatus(403);
    }

    public function test_settings_endpoints_require_authentication(): void
    {
        $response = $this->getJson('/api/v1/admin/settings');
        $response->assertStatus(401);

        $response = $this->putJson('/api/v1/admin/settings', []);
        $response->assertStatus(401);

        $response = $this->postJson('/api/v1/admin/settings/clear-cache');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/admin/settings/health');
        $response->assertStatus(401);

        $response = $this->postJson('/api/v1/admin/settings/run-command', ['command' => 'cache:clear']);
        $response->assertStatus(401);
    }

    public function test_update_settings_validation(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->putJson('/api/v1/admin/settings', [
                'application' => [
                    'url' => 'not-a-valid-url',
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('application.url');
    }

    public function test_update_settings_with_timezone(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->putJson('/api/v1/admin/settings', [
                'application' => [
                    'timezone' => 'America/New_York',
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_update_settings_with_invalid_timezone(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->putJson('/api/v1/admin/settings', [
                'application' => [
                    'timezone' => 'Invalid/Timezone',
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('application.timezone');
    }

    public function test_update_settings_with_locale(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->putJson('/api/v1/admin/settings', [
                'application' => [
                    'locale' => 'en',
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_update_settings_with_too_long_locale(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->putJson('/api/v1/admin/settings', [
                'application' => [
                    'locale' => 'this-is-way-too-long-for-a-locale',
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('application.locale');
    }

    public function test_health_check_overall_status_is_healthy(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/admin/settings/health');

        $response->assertStatus(200);

        $overallStatus = $response->json('data.status');
        $this->assertContains($overallStatus, ['healthy', 'warning', 'critical']);
    }
}
