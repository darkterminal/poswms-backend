<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for rate limiters used throughout
    | the application. All limits are designed to be tiered based on
    | user authentication status and operation type.
    |
    | Each limiter supports:
    | - max_attempts: Maximum requests allowed per decay period
    | - decay_rate_seconds: Time window for the limit
    | - block_duration_seconds: How long to block after exceeding limit
    |
    */

    /*
    |--------------------------------------------------------------------------
    | General API Rate Limits
    |--------------------------------------------------------------------------
    |
    | Default rate limits for standard API operations.
    | These apply to most CRUD operations and reads.
    |
    */

    'api' => [
        'authenticated' => [
            'max_attempts' => 100,
            'decay_rate_seconds' => 60,
            'block_duration_seconds' => 300,
        ],
        'guest' => [
            'max_attempts' => 30,
            'decay_rate_seconds' => 60,
            'block_duration_seconds' => 300,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin API Rate Limits
    |--------------------------------------------------------------------------
    |
    | Higher limits for admin operations that require more frequent access.
    | Applied to admin-only routes with role:admin middleware.
    |
    */

    'api_admin' => [
        'authenticated' => [
            'max_attempts' => 200,
            'decay_rate_seconds' => 60,
            'block_duration_seconds' => 300,
        ],
        'guest' => [
            'max_attempts' => 10,
            'decay_rate_seconds' => 60,
            'block_duration_seconds' => 300,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Heavy Operations Rate Limits (DEPRECATED)
    |--------------------------------------------------------------------------
    |
    | Legacy rate limiter for heavy operations.
    | Kept for backward compatibility.
    | Use 'api_exports' and 'api_webhook_test' for new implementations.
    |
    */

    'api_heavy' => [
        'authenticated' => [
            'max_attempts' => 20,
            'decay_rate_seconds' => 60,
            'block_duration_seconds' => 300,
        ],
        'guest' => [
            'max_attempts' => 5,
            'decay_rate_seconds' => 60,
            'block_duration_seconds' => 300,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Rate Limits
    |--------------------------------------------------------------------------
    |
    | Strict limits to prevent brute force attacks on login endpoints.
    | Uses multiple limits (per-minute and per-hour) for layered protection.
    |
    */

    'auth' => [
        'per_minute' => 10,
        'per_hour' => 50,
        'block_duration_seconds' => 600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Operations Rate Limits
    |--------------------------------------------------------------------------
    |
    | Rate limits for resource-heavy export endpoints.
    | These endpoints generate large CSV/Excel files and consume
    | significant server resources.
    |
    | Applied to:
    | - /reports/inventory/export/*
    | - /reports/sales/export/*
    |
    | Strategy:
    | - Lower limits to prevent resource exhaustion
    | - Longer block duration to discourage abuse
    | - Tiered by user role (admin vs regular user)
    |
    */

    'api_exports' => [
        'admin' => [
            'max_attempts' => 10,
            'decay_rate_seconds' => 60,
            'block_duration_seconds' => 600,
        ],
        'authenticated' => [
            'max_attempts' => 5,
            'decay_rate_seconds' => 60,
            'block_duration_seconds' => 600,
        ],
        'guest' => [
            'max_attempts' => 0, // Guests cannot access exports
            'decay_rate_seconds' => 60,
            'block_duration_seconds' => 600,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Test Rate Limits
    |--------------------------------------------------------------------------
    |
    | Rate limits for webhook testing endpoint.
    | Webhook tests make external HTTP requests and can be abused
    | for SSRF amplification or external service flooding.
    |
    | Applied to:
    | - POST /webhooks/{webhook}/test
    |
    | Strategy:
    | - Very strict limits (tests should be infrequent)
    | - Per-user tracking to prevent abuse
    | - Longer block duration to prevent repeated testing attacks
    |
    */

    'api_webhook_test' => [
        'authenticated' => [
            'max_attempts' => 5,
            'decay_rate_seconds' => 60,
            'block_duration_seconds' => 900, // 15 minutes
        ],
        'guest' => [
            'max_attempts' => 0, // Guests cannot test webhooks
            'decay_rate_seconds' => 60,
            'block_duration_seconds' => 900,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiter Response Configuration
    |--------------------------------------------------------------------------
    |
    | Customize the response when rate limit is exceeded.
    |
    */

    'response' => [
        'message' => 'Too many requests. Please try again later.',
        'code' => 'RATE_LIMIT_EXCEEDED',
        'retry_after_header' => true, // Include Retry-After header
        'rate_limit_headers' => true, // Include X-RateLimit-* headers
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging for rate limit events.
    |
    */

    'logging' => [
        'enabled' => true,
        'log_exceeded' => true, // Log when limit is exceeded
        'log_blocked' => true,  // Log when user is blocked
        'log_level' => 'warning',
    ],
];
