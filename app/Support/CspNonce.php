<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * CSP Nonce Generator.
 *
 * Generates and manages Content Security Policy nonces for secure inline script/style loading.
 *
 * Usage:
 *   // In controllers or middleware
 *   $nonce = CspNonce::generate();
 *
 *   // In Blade views
 *   <script nonce="{{ csp_nonce() }}">...</script>
 *
 *   // Check if nonce exists
 *   if (CspNonce::has()) { ... }
 */
class CspNonce
{
    /**
     * The current request's nonce.
     */
    protected static ?string $nonce = null;

    /**
     * Generate a new CSP nonce for the current request.
     *
     * The nonce is stored statically and reused throughout the request lifecycle
     * to ensure consistency across all views and components.
     *
     * @param  int  $length  Bytes (base64 encoded = ~1.33x length)
     * @return string Base64-encoded nonce
     */
    public static function generate(int $length = 16): string
    {
        if (static::$nonce !== null) {
            return static::$nonce;
        }

        static::$nonce = base64_encode(Str::random($length));

        return static::$nonce;
    }

    /**
     * Get the current nonce without generating a new one.
     */
    public static function get(): ?string
    {
        return static::$nonce;
    }

    /**
     * Check if a nonce has been generated for the current request.
     */
    public static function has(): bool
    {
        return static::$nonce !== null;
    }

    /**
     * Clear the current nonce (useful for testing).
     */
    public static function clear(): void
    {
        static::$nonce = null;
    }

    /**
     * Generate a nonce attribute for HTML tags.
     *
     * Usage: <script {!! CspNonce::attribute() !!}>...</script>
     */
    public static function attribute(): string
    {
        $nonce = static::get() ?? static::generate();

        return sprintf('nonce="%s"', $nonce);
    }

    /**
     * Generate a CSP header value with the current nonce.
     *
     * @param  array<string, array<string>|bool>  $directives
     */
    public static function buildPolicy(array $directives): string
    {
        $policy = [];

        foreach ($directives as $directive => $sources) {
            if ($sources === true) {
                // Boolean directive (e.g., 'upgrade-insecure-requests' => true)
                $policy[] = $directive;
            } elseif (is_array($sources)) {
                $values = implode(' ', $sources);

                // Add nonce to script-src and style-src if not in legacy mode
                if (in_array($directive, ['script-src', 'style-src'], true)) {
                    $nonce = static::get();
                    if ($nonce !== null && ! in_array("'unsafe-inline'", $sources, true)) {
                        $values = "'nonce-{$nonce}' {$values}";
                    }
                }

                $policy[] = "{$directive} {$values}";
            }
        }

        return implode('; ', $policy);
    }
}
