<?php

namespace App\Http\Middleware;

use App\Support\CspNonce;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Prevent clickjacking attacks
        $response->headers->set('X-Frame-Options', 'DENY');

        // Enable XSS protection in browsers
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Referrer policy - only send origin for cross-origin requests
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Content Security Policy - environment-based configuration
        $this->applyCspPolicy($response, $request);

        // Permissions Policy - restrict browser features
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=()'
        );

        // HTTP Strict Transport Security (HSTS) - force HTTPS
        // Only enable in non-local environments
        if (! $this->isDevelopmentEnvironment()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Cross-Origin Opener Policy
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // Cross-Origin Embedder Policy
        $response->headers->set('Cross-Origin-Embedder-Policy', 'require-corp');

        // Cross-Origin Resource Policy
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        return $response;
    }

    /**
     * Apply Content Security Policy based on environment configuration.
     *
     * Supports three modes:
     * - legacy: Allows 'unsafe-inline' and 'unsafe-eval' (development)
     * - strict: Uses nonce-based loading (production)
     * - custom: Uses user-defined directives
     */
    protected function applyCspPolicy(Response $response, Request $request): void
    {
        if (! config('csp.enabled', true)) {
            return;
        }

        // Determine CSP mode based on environment
        $mode = $this->determineCspMode();

        // Generate nonce for strict mode (or if explicitly requested)
        if ($mode === 'strict') {
            CspNonce::generate(config('csp.nonce_length', 16));

            // Share nonce with views for inline scripts/styles
            if (function_exists('view') && $view = view()->shared('cspNonce', null)) {
                view()->share('cspNonce', CspNonce::get());
            }
        }

        // Build CSP header based on mode
        $cspHeader = $this->buildCspHeader($mode);

        // Use report-only mode if configured
        $headerName = config('csp.report_only', false)
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $response->headers->set($headerName, $cspHeader);

        // Add report-uri if configured
        if (config('csp.report_uri')) {
            $existingHeader = $response->headers->get($headerName);
            if (! str_contains($existingHeader, 'report-uri')) {
                $response->headers->set(
                    $headerName,
                    $existingHeader . '; report-uri ' . config('csp.report_uri')
                );
            }
        }
    }

    /**
     * Determine the CSP mode based on environment configuration.
     */
    protected function determineCspMode(): string
    {
        $configuredMode = config('csp.mode', 'auto');

        // If explicitly set, use that mode
        if ($configuredMode !== 'auto') {
            return $configuredMode;
        }

        // Auto-detect based on environment
        $env = config('app.env', 'production');
        $environmentModes = config('csp.environment_modes', [
            'local' => 'legacy',
            'development' => 'legacy',
            'staging' => 'strict',
            'production' => 'strict',
        ]);

        return $environmentModes[$env] ?? 'strict';
    }

    /**
     * Build the CSP header value based on the selected mode.
     */
    protected function buildCspHeader(string $mode): string
    {
        return match ($mode) {
            'legacy' => $this->buildLegacyCsp(),
            'strict' => $this->buildStrictCsp(),
            'custom' => $this->buildCustomCsp(),
            default => $this->buildStrictCsp(),
        };
    }

    /**
     * Build legacy CSP policy (development-friendly).
     *
     * Allows 'unsafe-inline' and 'unsafe-eval' for easier debugging.
     */
    protected function buildLegacyCsp(): string
    {
        $directives = config('csp.legacy_directives', [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'", 'https://unpkg.com', 'https://cdn.jsdelivr.net'],
            'style-src' => ["'self'", "'unsafe-inline'", 'https://unpkg.com', 'https://cdn.jsdelivr.net'],
            'img-src' => ["'self'", 'data:', 'https:'],
            'font-src' => ["'self'"],
            'connect-src' => ["'self'", 'https://unpkg.com', 'https://cdn.jsdelivr.net', 'https://validator.swagger.io'],
            'frame-ancestors' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
        ]);

        return $this->formatCspDirectives($directives);
    }

    /**
     * Build strict CSP policy (production-ready).
     *
     * Uses nonce-based loading for inline scripts and styles.
     */
    protected function buildStrictCsp(): string
    {
        $directives = config('csp.strict_directives', [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", 'https://unpkg.com', 'https://cdn.jsdelivr.net'],
            'style-src' => ["'self'", 'https://unpkg.com', 'https://cdn.jsdelivr.net'],
            'img-src' => ["'self'", 'data:', 'https:'],
            'font-src' => ["'self'"],
            'connect-src' => ["'self'", 'https://unpkg.com', 'https://cdn.jsdelivr.net', 'https://validator.swagger.io'],
            'frame-ancestors' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'upgrade-insecure-requests' => true,
        ]);

        // Add additional sources if configured
        $additionalScripts = config('csp.additional_script_sources', []);
        if (! empty($additionalScripts)) {
            $directives['script-src'] = array_merge(
                $directives['script-src'],
                $additionalScripts
            );
        }

        $additionalStyles = config('csp.additional_style_sources', []);
        if (! empty($additionalStyles)) {
            $directives['style-src'] = array_merge(
                $directives['style-src'],
                $additionalStyles
            );
        }

        return CspNonce::buildPolicy($directives);
    }

    /**
     * Build custom CSP policy from user configuration.
     */
    protected function buildCustomCsp(): string
    {
        $directives = config('csp.directives', []);

        if (empty($directives)) {
            // Fallback to strict mode if no custom directives defined
            return $this->buildStrictCsp();
        }

        return CspNonce::buildPolicy($directives);
    }

    /**
     * Format CSP directives into a header string.
     *
     * @param  array<string, array<string>|bool>  $directives
     */
    protected function formatCspDirectives(array $directives): string
    {
        $policy = [];

        foreach ($directives as $directive => $sources) {
            if ($sources === true) {
                // Boolean directive (e.g., 'upgrade-insecure-requests' => true)
                $policy[] = $directive;
            } elseif (is_array($sources)) {
                $policy[] = $directive . ' ' . implode(' ', $sources);
            }
        }

        return implode('; ', $policy);
    }

    /**
     * Check if the current environment is a development environment.
     */
    protected function isDevelopmentEnvironment(): bool
    {
        return in_array(config('app.env', 'production'), ['local', 'development'], true);
    }
}
