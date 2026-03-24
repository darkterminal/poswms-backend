<?php

namespace Tests\Feature\Security;

use App\Support\CspNonce;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for Content Security Policy (CSP) and Debug Mode Protection.
 *
 * @see \App\Http\Middleware\SecurityHeadersMiddleware
 * @see \App\Http\Middleware\PreventDebugModeInProduction
 * @see CspNonce
 */
class CspAndDebugModeProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear any existing nonce
        CspNonce::clear();
    }

    /**
     * Test that security headers are applied to all responses.
     */
    public function test_security_headers_are_applied_to_api_responses(): void
    {
        // Use health check endpoint that doesn't require authentication
        $response = $this->get('/up');

        $response->assertStatus(200);

        // Check security headers
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy');
    }

    /**
     * Test CSP header is present in responses.
     */
    public function test_csp_header_is_present(): void
    {
        $response = $this->get('/up');

        $response->assertHeader('Content-Security-Policy');

        $cspHeader = $response->headers->get('Content-Security-Policy');
        $this->assertNotEmpty($cspHeader);
    }

    /**
     * Test CSP policy contains expected directives in legacy mode.
     */
    public function test_csp_policy_contains_required_directives_in_legacy_mode(): void
    {
        // Set to legacy mode for this test
        config(['csp.mode' => 'legacy']);
        config(['csp.environment_modes' => []]);

        $response = $this->get('/up');

        $cspHeader = $response->headers->get('Content-Security-Policy');

        // Legacy mode should contain unsafe-inline
        $this->assertStringContainsString("default-src 'self'", $cspHeader);
        $this->assertStringContainsString('script-src', $cspHeader);
        $this->assertStringContainsString('style-src', $cspHeader);
        $this->assertStringContainsString("'unsafe-inline'", $cspHeader);
        $this->assertStringContainsString("'unsafe-eval'", $cspHeader);
    }

    /**
     * Test CSP policy in strict mode does not contain unsafe-inline.
     */
    public function test_csp_policy_does_not_contain_unsafe_inline_in_strict_mode(): void
    {
        // Set to strict mode for this test
        config(['csp.mode' => 'strict']);
        config(['csp.environment_modes' => []]);

        $response = $this->get('/up');

        $cspHeader = $response->headers->get('Content-Security-Policy');

        // Strict mode should NOT contain unsafe-inline or unsafe-eval
        $this->assertStringContainsString("default-src 'self'", $cspHeader);
        $this->assertStringContainsString('script-src', $cspHeader);
        $this->assertStringContainsString('style-src', $cspHeader);
        $this->assertStringNotContainsString("'unsafe-inline'", $cspHeader);
        $this->assertStringNotContainsString("'unsafe-eval'", $cspHeader);

        // Should contain nonce
        $this->assertStringContainsString("'nonce-", $cspHeader);
    }

    /**
     * Test nonce generation.
     */
    public function test_nonce_generation(): void
    {
        $nonce = CspNonce::generate();

        $this->assertNotEmpty($nonce);
        $this->assertIsString($nonce);

        // Nonce should be base64 encoded
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/]+=*$/', $nonce);

        // Same nonce should be returned on subsequent calls
        $this->assertEquals($nonce, CspNonce::get());
    }

    /**
     * Test nonce is consistent within same request.
     */
    public function test_nonce_is_consistent_within_request(): void
    {
        $nonce1 = CspNonce::generate();
        $nonce2 = CspNonce::generate();

        $this->assertEquals($nonce1, $nonce2);
    }

    /**
     * Test nonce attribute generation.
     */
    public function test_nonce_attribute_generation(): void
    {
        CspNonce::clear();
        $attribute = CspNonce::attribute();

        $this->assertMatchesRegularExpression('/^nonce="[A-Za-z0-9+\/]+=*"$/', $attribute);
    }

    /**
     * Test CSP policy builder with nonce.
     */
    public function test_csp_policy_builder_includes_nonce(): void
    {
        CspNonce::clear();
        $nonce = CspNonce::generate();

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", 'https://example.com'],
        ];

        $policy = CspNonce::buildPolicy($directives);

        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("'nonce-{$nonce}'", $policy);
        $this->assertStringContainsString("'self'", $policy);
        $this->assertStringContainsString('https://example.com', $policy);
    }

    /**
     * Test environment-based CSP mode selection.
     */
    public function test_environment_based_csp_mode_selection(): void
    {
        // Test local environment uses legacy mode
        config(['app.env' => 'local']);
        config(['csp.mode' => 'auto']);

        $response = $this->get('/up');
        $cspHeader = $response->headers->get('Content-Security-Policy');

        // Local should use legacy mode with unsafe-inline
        $this->assertStringContainsString("'unsafe-inline'", $cspHeader);

        // Test production environment uses strict mode
        config(['app.env' => 'production']);
        CspNonce::clear();

        $response = $this->get('/up');
        $cspHeader = $response->headers->get('Content-Security-Policy');

        // Production should use strict mode without unsafe-inline
        $this->assertStringNotContainsString("'unsafe-inline'", $cspHeader);
        $this->assertStringContainsString("'nonce-", $cspHeader);
    }

    /**
     * Test HSTS header is not set in development.
     */
    public function test_hsts_header_not_set_in_development(): void
    {
        config(['app.env' => 'local']);

        $response = $this->get('/up');

        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    /**
     * Test HSTS header is set in production.
     */
    public function test_hsts_header_set_in_production(): void
    {
        config(['app.env' => 'production']);

        $response = $this->get('/up');

        $response->assertHeader('Strict-Transport-Security');
        $this->assertStringContainsString(
            'max-age=31536000; includeSubDomains; preload',
            $response->headers->get('Strict-Transport-Security')
        );
    }

    /**
     * Test CSP can be disabled via configuration.
     */
    public function test_csp_can_be_disabled_via_config(): void
    {
        config(['csp.enabled' => false]);

        $response = $this->get('/up');

        $response->assertHeaderMissing('Content-Security-Policy');
    }

    /**
     * Test report-only mode uses correct header.
     */
    public function test_report_only_mode_uses_correct_header(): void
    {
        config(['csp.report_only' => true]);

        $response = $this->get('/up');

        $response->assertHeader('Content-Security-Policy-Report-Only');
        $response->assertHeaderMissing('Content-Security-Policy');
    }

    /**
     * Test custom CSP directives.
     */
    public function test_custom_csp_directives(): void
    {
        config(['csp.mode' => 'custom']);
        config(['csp.directives' => [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", 'https://custom-cdn.com'],
            'img-src' => ["'self'", 'data:', 'https://custom-images.com'],
        ]]);

        $response = $this->get('/up');

        $cspHeader = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $cspHeader);
        $this->assertStringContainsString("script-src 'self' https://custom-cdn.com", $cspHeader);
        $this->assertStringContainsString("img-src 'self' data: https://custom-images.com", $cspHeader);
    }

    /**
     * Test additional script sources can be added.
     */
    public function test_additional_script_sources(): void
    {
        config(['csp.mode' => 'strict']);
        config(['csp.additional_script_sources' => ['https://analytics.example.com']]);

        $response = $this->get('/up');

        $cspHeader = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://analytics.example.com', $cspHeader);
    }

    /**
     * Test additional style sources can be added.
     */
    public function test_additional_style_sources(): void
    {
        config(['csp.mode' => 'strict']);
        config(['csp.additional_style_sources' => ['https://fonts.googleapis.com']]);

        $response = $this->get('/up');

        $cspHeader = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://fonts.googleapis.com', $cspHeader);
    }

    /**
     * Test frame-ancestors is set to none.
     */
    public function test_frame_ancestors_is_set_to_none(): void
    {
        $response = $this->get('/up');

        $cspHeader = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("frame-ancestors 'none'", $cspHeader);
    }

    /**
     * Test base-uri is set to self.
     */
    public function test_base_uri_is_set_to_self(): void
    {
        $response = $this->get('/up');

        $cspHeader = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("base-uri 'self'", $cspHeader);
    }

    /**
     * Test form-action is set to self.
     */
    public function test_form_action_is_set_to_self(): void
    {
        $response = $this->get('/up');

        $cspHeader = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("form-action 'self'", $cspHeader);
    }

    /**
     * Test Permissions-Policy header is set.
     */
    public function test_permissions_policy_header_is_set(): void
    {
        $response = $this->get('/up');

        $response->assertHeader('Permissions-Policy');

        $permissionsPolicy = $response->headers->get('Permissions-Policy');
        $this->assertStringContainsString('geolocation=()', $permissionsPolicy);
        $this->assertStringContainsString('microphone=()', $permissionsPolicy);
        $this->assertStringContainsString('camera=()', $permissionsPolicy);
    }

    /**
     * Test Cross-Origin headers are set.
     */
    public function test_cross_origin_headers_are_set(): void
    {
        $response = $this->get('/up');

        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('Cross-Origin-Embedder-Policy', 'require-corp');
        $response->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
    }

    /**
     * Test nonce helper function.
     */
    public function test_nonce_helper_function(): void
    {
        CspNonce::clear();

        $nonce1 = \csp_nonce();
        $nonce2 = \csp_nonce();

        // Should return same nonce within request
        $this->assertEquals($nonce1, $nonce2);
        $this->assertNotEmpty($nonce1);
    }
}
