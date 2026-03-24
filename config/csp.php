<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Content Security Policy Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration allows you to define CSP directives based on the
    | application environment. Production uses strict policies while
    | development uses relaxed policies for easier debugging.
    |
    | Supported modes: 'strict', 'legacy', 'custom'
    | - strict: Uses nonce-based script/style loading (recommended for production)
    | - legacy: Allows 'unsafe-inline' and 'unsafe-eval' (for development)
    | - custom: Uses the directives array below
    |
    */

    /*
    |--------------------------------------------------------------------------
    | CSP Mode by Environment
    |--------------------------------------------------------------------------
    |
    | Define which CSP mode to use for each environment. This allows you to
    | use relaxed policies in development and strict policies in production.
    |
    */

    'mode' => env('CSP_MODE', 'auto'),

    'environment_modes' => [
        'local' => 'legacy',      // Relaxed for development
        'development' => 'legacy', // Relaxed for development
        'staging' => 'strict',     // Strict for staging
        'production' => 'strict',  // Strict for production
    ],

    /*
    |--------------------------------------------------------------------------
    | Nonce Configuration
    |--------------------------------------------------------------------------
    |
    | When using 'strict' mode, a unique nonce is generated per request.
    | This nonce should be added to inline scripts and styles.
    |
    | The nonce is automatically shared with views via the $cspNonce variable.
    |
    */

    'nonce_length' => 16, // Bytes (base64 encoded = 24 chars)

    /*
    |--------------------------------------------------------------------------
    | CSP Directives (Custom Mode)
    |--------------------------------------------------------------------------
    |
    | If you set mode to 'custom', define your CSP directives here.
    | Leave empty to use the default strict/legacy modes.
    |
    */

    'directives' => [],

    /*
    |--------------------------------------------------------------------------
    | Default Directives (Legacy Mode)
    |--------------------------------------------------------------------------
    |
    | These directives are used when CSP mode is 'legacy' (development).
    | They allow 'unsafe-inline' and 'unsafe-eval' for easier debugging.
    |
    */

    'legacy_directives' => [
        'default-src' => ["'self'"],
        'script-src' => ["'self'", "'unsafe-inline'", "'unsafe-eval'", 'https://unpkg.com', 'https://cdn.jsdelivr.net'],
        'style-src' => ["'self'", "'unsafe-inline'", 'https://unpkg.com', 'https://cdn.jsdelivr.net'],
        'img-src' => ["'self'", 'data:', 'https:'],
        'font-src' => ["'self'"],
        'connect-src' => ["'self'", 'https://unpkg.com', 'https://cdn.jsdelivr.net', 'https://validator.swagger.io'],
        'frame-ancestors' => ["'none'"],
        'base-uri' => ["'self'"],
        'form-action' => ["'self'"],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Directives (Strict Mode)
    |--------------------------------------------------------------------------
    |
    | These directives are used when CSP mode is 'strict' (production).
    | Nonces will be automatically injected for script-src and style-src.
    |
    */

    'strict_directives' => [
        'default-src' => ["'self'"],
        'script-src' => ["'self'", 'https://unpkg.com', 'https://cdn.jsdelivr.net'], // nonce added automatically
        'style-src' => ["'self'", 'https://unpkg.com', 'https://cdn.jsdelivr.net'], // nonce added automatically
        'img-src' => ["'self'", 'data:', 'https:'],
        'font-src' => ["'self'"],
        'connect-src' => ["'self'", 'https://unpkg.com', 'https://cdn.jsdelivr.net', 'https://validator.swagger.io'],
        'frame-ancestors' => ["'none'"],
        'base-uri' => ["'self'"],
        'form-action' => ["'self'"],
        'upgrade-insecure-requests' => true, // Force HTTPS in production
    ],

    /*
    |--------------------------------------------------------------------------
    | Report URI
    |--------------------------------------------------------------------------
    |
    | Set a URI to receive CSP violation reports. Leave null to disable.
    | This is useful for monitoring CSP violations in production.
    |
    */

    'report_uri' => env('CSP_REPORT_URI', null),

    /*
    |--------------------------------------------------------------------------
    | Report Only Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, uses Content-Security-Policy-Report-Only header instead
    | of Content-Security-Policy. Useful for testing policies before enforcement.
    |
    */

    'report_only' => env('CSP_REPORT_ONLY', false),

    /*
    |--------------------------------------------------------------------------
    | Allowed Script Sources (Additional)
    |--------------------------------------------------------------------------
    |
    | Additional script sources to allow beyond the defaults.
    | These are merged with the default directives.
    |
    */

    'additional_script_sources' => [],

    /*
    |--------------------------------------------------------------------------
    | Allowed Style Sources (Additional)
    |--------------------------------------------------------------------------
    |
    | Additional style sources to allow beyond the defaults.
    | These are merged with the default directives.
    |
    */

    'additional_style_sources' => [],

    /*
    |--------------------------------------------------------------------------
    | Enable CSP
    |--------------------------------------------------------------------------
    |
    | Set to false to completely disable CSP headers. Not recommended for production.
    |
    */

    'enabled' => env('CSP_ENABLED', true),
];
