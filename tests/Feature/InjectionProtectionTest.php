<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tests for injection protection across the application.
 *
 * These tests verify that:
 * 1. ORDER BY injection is prevented in controllers
 * 2. Command injection is prevented in SystemSettingsController
 * 3. Backward compatibility is maintained for valid inputs
 */
class InjectionProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create super admin for authenticated requests
        $this->superAdmin = User::factory()->create([
            'is_super_admin' => true,
        ]);
    }

    /**
     * Test TenantController sorting with valid fields.
     */
    public function test_tenant_controller_accepts_valid_sort_fields(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $validFields = ['name', 'slug', 'company_name', 'email', 'status', 'created_at', 'updated_at'];

        foreach ($validFields as $field) {
            $response = $this->getJson('/api/v1/admin/tenants?sort_by=' . $field);

            $response->assertStatus(200);
            $response->assertJsonPath('success', true);
        }
    }

    /**
     * Test TenantController sorting falls back to default for invalid fields.
     */
    public function test_tenant_controller_falls_back_to_default_for_invalid_sort_field(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->getJson('/api/v1/admin/tenants?sort_by=invalid_field');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('meta.sorting.field', 'created_at');
    }

    /**
     * Test TenantController blocks SQL injection in sort field.
     */
    public function test_tenant_controller_blocks_sql_injection_in_sort_field(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $injectionPayloads = [
            'name; DROP TABLE tenants--',
            'created_at UNION SELECT * FROM users',
            'id, (SELECT SLEEP(5))',
            "name' OR '1'='1",
        ];

        foreach ($injectionPayloads as $payload) {
            $response = $this->getJson('/api/v1/admin/tenants?sort_by=' . urlencode($payload));

            $response->assertStatus(200);
            // Should fall back to default, not error
            $response->assertJsonPath('success', true);
            $response->assertJsonPath('meta.sorting.field', 'created_at');
        }
    }

    /**
     * Test UserController sorting validation.
     */
    public function test_user_controller_sorting_validation(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Valid field
        $response = $this->getJson('/api/v1/admin/users?sort_by=name');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // Invalid field - should be rejected by Form Request validation (422)
        $response = $this->getJson('/api/v1/admin/users?sort_by=invalid');
        $response->assertStatus(422);
    }

    /**
     * Test SystemSettingsController allows whitelisted commands.
     */
    public function test_system_settings_controller_allows_whitelisted_commands(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $allowedCommands = [
            'cache:clear',
            'config:clear',
            'route:clear',
            'view:clear',
            'optimize',
            'optimize:clear',
        ];

        foreach ($allowedCommands as $command) {
            $response = $this->postJson('/api/v1/admin/settings/run-command', [
                'command' => $command,
            ]);

            // Command should be accepted (may succeed or fail based on environment)
            $response->assertStatus(200);
        }
    }

    /**
     * Test SystemSettingsController blocks non-whitelisted commands.
     */
    public function test_system_settings_controller_blocks_non_whitelisted_commands(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $blockedCommands = [
            'migrate:fresh',
            'db:seed',
            'make:model',
            'tinker',
            'invalid:command',
        ];

        foreach ($blockedCommands as $command) {
            $response = $this->postJson('/api/v1/admin/settings/run-command', [
                'command' => $command,
            ]);

            $response->assertStatus(403);
            // Command may be blocked by regex (COMMAND_BLOCKED) or whitelist (COMMAND_NOT_ALLOWED)
            $response->assertJsonPath('error.code', fn($code) => in_array($code, ['COMMAND_NOT_ALLOWED', 'COMMAND_BLOCKED']));
        }
    }

    /**
     * Test SystemSettingsController blocks command injection attempts.
     */
    public function test_system_settings_controller_blocks_command_injection(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // These contain dangerous keywords that pass regex but are caught by pattern check
        $injectionAttempts = [
            'cache:clear:exec',  // Contains 'exec' keyword
            'config:clear:system',  // Contains 'system' keyword
            'route:clear:shell',  // Contains 'shell' keyword
            'optimize:eval',  // Contains 'eval' keyword
        ];

        foreach ($injectionAttempts as $payload) {
            $response = $this->postJson('/api/v1/admin/settings/run-command', [
                'command' => $payload,
            ]);

            $response->assertStatus(403);
        }
    }

    /**
     * Test SystemSettingsController blocks dangerous characters.
     */
    public function test_system_settings_controller_blocks_dangerous_characters(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // These will be caught by regex validation first (422)
        $dangerousPatternsRegex = [
            'cache:clear; ls',
            'config:clear | cat',
            'route:clear & whoami',
            'view:clear`id`',
            'optimize$(pwd)',
            'cache:clear{block}',
            'config:clear<redirect>',
            'route:clear!bang',
            'optimize*glob',
            'cache:clear?query',
        ];

        foreach ($dangerousPatternsRegex as $payload) {
            $response = $this->postJson('/api/v1/admin/settings/run-command', [
                'command' => $payload,
            ]);

            // Regex validation catches these first (422)
            $response->assertStatus(422);
        }
    }

    /**
     * Test SystemSettingsController validates command format.
     */
    public function test_system_settings_controller_validates_command_format(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Invalid formats should fail validation or be blocked
        $invalidFormats = [
            ['command' => 'CACHE:CLEAR', 'expectedStatus' => 422], // Uppercase - fails regex
            ['command' => 'cache clear', 'expectedStatus' => 422], // Space - fails regex
            ['command' => str_repeat('a', 101), 'expectedStatus' => 422], // Too long
        ];

        foreach ($invalidFormats as $payload) {
            $response = $this->postJson('/api/v1/admin/settings/run-command', $payload);

            $response->assertStatus($payload['expectedStatus']);
        }
    }

    /**
     * Test backward compatibility - existing query params still work.
     */
    public function test_backward_compatibility_for_valid_inputs(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Test that valid sorting still works as expected
        $response = $this->getJson('/api/v1/admin/tenants?sort_by=name&sort_order=asc');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('meta.sorting.field', 'name');
        $response->assertJsonPath('meta.sorting.order', 'asc');

        // Test default behavior when no sort params provided
        $response = $this->getJson('/api/v1/admin/tenants');
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    /**
     * Test sort order validation in controllers.
     */
    public function test_sort_order_validation_in_controllers(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Valid orders
        $response = $this->getJson('/api/v1/admin/tenants?sort_order=asc');
        $response->assertStatus(200);
        $response->assertJsonPath('meta.sorting.order', 'asc');

        $response = $this->getJson('/api/v1/admin/tenants?sort_order=desc');
        $response->assertStatus(200);
        $response->assertJsonPath('meta.sorting.order', 'desc');

        // Invalid order falls back to default
        $response = $this->getJson('/api/v1/admin/tenants?sort_order=invalid');
        $response->assertStatus(200);
        $response->assertJsonPath('meta.sorting.order', 'desc');

        // Case insensitive
        $response = $this->getJson('/api/v1/admin/tenants?sort_order=ASC');
        $response->assertStatus(200);
        $response->assertJsonPath('meta.sorting.order', 'asc');
    }

    /**
     * Test unauthenticated access is rejected.
     */
    public function test_unauthenticated_access_is_rejected(): void
    {
        // Without authentication
        $response = $this->getJson('/api/v1/admin/tenants?sort_by=name');
        $response->assertStatus(401);

        $response = $this->postJson('/api/v1/admin/settings/run-command', [
            'command' => 'cache:clear',
        ]);
        $response->assertStatus(401);
    }

    /**
     * Test non-super-admin cannot access system commands.
     */
    public function test_non_super_admin_cannot_access_system_commands(): void
    {
        $regularUser = User::factory()->create([
            'is_super_admin' => false,
        ]);

        Sanctum::actingAs($regularUser);

        $response = $this->postJson('/api/v1/admin/settings/run-command', [
            'command' => 'cache:clear',
        ]);

        // Should be forbidden for non-super-admins
        $response->assertStatus(403);
    }
}
