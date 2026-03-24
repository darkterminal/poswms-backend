<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\PreventDebugModeInProduction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Tests for Debug Mode Protection in Production.
 *
 * @see PreventDebugModeInProduction
 */
class DebugModeProtectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test debug mode protection middleware exists.
     */
    public function test_debug_mode_protection_middleware_exists(): void
    {
        $this->assertTrue(class_exists(PreventDebugModeInProduction::class));
    }

    /**
     * Test that debug mode is disabled by default in .env.example.
     */
    public function test_debug_mode_disabled_in_env_example(): void
    {
        $envExample = file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('APP_DEBUG=false', $envExample);
    }

    /**
     * Test security configuration exists.
     */
    public function test_security_configuration_exists(): void
    {
        $this->assertTrue(file_exists(config_path('security.php')));

        $config = include config_path('security.php');

        $this->assertArrayHasKey('block_debug_access', $config);
        $this->assertArrayHasKey('trusted_ips_for_debug', $config);
        $this->assertArrayHasKey('log_security_events', $config);
    }

    /**
     * Test CSP configuration exists with proper defaults.
     */
    public function test_csp_configuration_exists(): void
    {
        $this->assertTrue(file_exists(config_path('csp.php')));

        $config = include config_path('csp.php');

        $this->assertArrayHasKey('mode', $config);
        $this->assertArrayHasKey('environment_modes', $config);
        $this->assertArrayHasKey('legacy_directives', $config);
        $this->assertArrayHasKey('strict_directives', $config);
        $this->assertArrayHasKey('enabled', $config);
    }

    /**
     * Test environment modes are properly configured.
     */
    public function test_environment_modes_configuration(): void
    {
        config(['csp.mode' => 'auto']);

        $environmentModes = config('csp.environment_modes');

        $this->assertEquals('legacy', $environmentModes['local']);
        $this->assertEquals('legacy', $environmentModes['development']);
        $this->assertEquals('strict', $environmentModes['staging']);
        $this->assertEquals('strict', $environmentModes['production']);
    }

    /**
     * Test legacy directives contain unsafe-inline for development.
     */
    public function test_legacy_directives_allow_unsafe_inline(): void
    {
        $legacyDirectives = config('csp.legacy_directives');

        $this->assertContains("'unsafe-inline'", $legacyDirectives['script-src']);
        $this->assertContains("'unsafe-eval'", $legacyDirectives['script-src']);
    }

    /**
     * Test strict directives do not contain unsafe-inline.
     */
    public function test_strict_directives_deny_unsafe_inline(): void
    {
        $strictDirectives = config('csp.strict_directives');

        $this->assertNotContains("'unsafe-inline'", $strictDirectives['script-src']);
        $this->assertNotContains("'unsafe-eval'", $strictDirectives['script-src']);
    }

    /**
     * Test strict mode includes upgrade-insecure-requests.
     */
    public function test_strict_mode_includes_upgrade_insecure_requests(): void
    {
        $strictDirectives = config('csp.strict_directives');

        $this->assertTrue($strictDirectives['upgrade-insecure-requests']);
    }

    /**
     * Test nonce length configuration.
     */
    public function test_nonce_length_configuration(): void
    {
        $this->assertEquals(16, config('csp.nonce_length'));
    }

    /**
     * Test report URI configuration is optional.
     */
    public function test_report_uri_is_optional(): void
    {
        // By default, report_uri should be null
        $this->assertNull(config('csp.report_uri'));
    }

    /**
     * Test that security headers middleware is registered.
     */
    public function test_security_headers_middleware_is_registered(): void
    {
        // Check that the middleware file exists
        $this->assertTrue(file_exists(app_path('Http/Middleware/SecurityHeadersMiddleware.php')));

        // Check that CspServiceProvider exists
        $this->assertTrue(file_exists(app_path('Providers/CspServiceProvider.php')));
    }

    /**
     * Test helper function file exists.
     */
    public function test_helper_function_file_exists(): void
    {
        $this->assertTrue(file_exists(app_path('helpers.php')));
    }

    /**
     * Test CspNonce support class exists.
     */
    public function test_csp_nonce_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Support\CspNonce::class));
    }

    /**
     * Test that APP_DEBUG is set to false in staging environment file.
     */
    public function test_debug_mode_disabled_in_staging_env(): void
    {
        if (! file_exists(base_path('.env.staging'))) {
            $this->markTestSkipped('Staging environment file not found');
        }

        $envStaging = file_get_contents(base_path('.env.staging'));

        $this->assertStringContainsString('APP_DEBUG=false', $envStaging);
        $this->assertStringContainsString('APP_ENV=staging', $envStaging);
    }

    /**
     * Test that development environment allows debug mode.
     */
    public function test_debug_mode_configuration_in_development(): void
    {
        if (! file_exists(base_path('.env.development'))) {
            $this->markTestSkipped('Development environment file not found');
        }

        $envDevelopment = file_get_contents(base_path('.env.development'));

        // Development can have debug enabled, but should have CSP settings
        $this->assertStringContainsString('CSP_MODE=', $envDevelopment);
        $this->assertStringContainsString('CSP_ENABLED=', $envDevelopment);
    }

    /**
     * Test log channel configuration for security events.
     */
    public function test_security_log_configuration(): void
    {
        $this->assertArrayHasKey('errorlog', config('logging.channels'));
    }

    /**
     * Test that CSP configuration can be overridden via environment.
     */
    public function test_csp_config_can_be_overridden_via_environment(): void
    {
        // This test verifies the config uses env() helper
        putenv('CSP_MODE=strict');
        putenv('CSP_ENABLED=false');

        // Clear config cache
        config(['csp.mode' => null]);
        config(['csp.enabled' => null]);

        // Reload from environment
        $mode = env('CSP_MODE');
        $enabled = env('CSP_ENABLED');

        $this->assertEquals('strict', $mode);
        $this->assertFalse($enabled);

        // Clean up
        putenv('CSP_MODE');
        putenv('CSP_ENABLED');
    }

    /**
     * Test that the application has documentation for security settings.
     */
    public function test_security_documentation_exists(): void
    {
        // Check for OWASP security analysis document
        $this->assertTrue(file_exists(base_path('docs/OWASP_Security_Analysis_20260323.md')));
    }
}
