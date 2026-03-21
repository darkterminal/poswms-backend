<?php

declare(strict_types=1);

/**
 * REST Client Configuration
 * 
 * Copy this file to .rest-client-config.php and customize for your environment.
 * This file is ignored by .gitignore so you can store sensitive credentials.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL of your POS WMS API.
    |
    */
    'base_url' => env('API_BASE_URL', 'http://localhost:8000'),

    /*
    |--------------------------------------------------------------------------
    | Tenant ID
    |--------------------------------------------------------------------------
    |
    | Default tenant ID to use for testing.
    |
    */
    'tenant_id' => env('API_TENANT_ID', 1),

    /*
    |--------------------------------------------------------------------------
    | Authentication Credentials
    |--------------------------------------------------------------------------
    |
    | Default credentials to use for authentication.
    | NEVER commit real credentials to version control!
    |
    */
    'email' => env('API_TEST_EMAIL', 'admin@demo.com'),
    'password' => env('API_TEST_PASSWORD', 'password'),

    /*
    |--------------------------------------------------------------------------
    | Verbose Mode
    |--------------------------------------------------------------------------
    |
    | Enable verbose output for debugging.
    |
    */
    'verbose' => env('API_VERBOSE', false),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Request timeout in seconds.
    |
    */
    'timeout' => env('API_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Test Settings
    |--------------------------------------------------------------------------
    |
    | Configure which tests to run by default.
    |
    */
    'tests' => [
        'run_all' => true,
        'cleanup' => true,
        'specific_endpoint' => null, // e.g., 'products', 'orders'
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment-Specific Configurations
    |--------------------------------------------------------------------------
    |
    | Pre-configured settings for different environments.
    |
    */
    'environments' => [
        'local' => [
            'base_url' => 'http://localhost:8000',
            'tenant_id' => 1,
            'email' => 'test@example.com',
            'password' => 'password',
        ],
        
        'development' => [
            'base_url' => 'https://dev-api.example.com',
            'tenant_id' => 1,
            'email' => 'dev@example.com',
            'password' => 'dev-password',
        ],
        
        'staging' => [
            'base_url' => 'https://staging-api.example.com',
            'tenant_id' => 1,
            'email' => 'staging@example.com',
            'password' => 'staging-password',
        ],
        
        'production' => [
            'base_url' => 'https://api.example.com',
            'tenant_id' => 1,
            'email' => 'admin@example.com',
            'password' => 'DO_NOT_USE_IN_TESTS',
        ],
    ],
];
