<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file contains security-related settings for the
    | application. It provides additional safety nets and security controls.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Debug Mode Protection
    |--------------------------------------------------------------------------
    |
    | These settings control the behavior when debug mode is accidentally
    | enabled in production environments.
    |
    */

    // Block requests from non-admin users when debug is enabled in production
    'block_debug_access' => env('SECURITY_BLOCK_DEBUG_ACCESS', false),

    // IP addresses that are allowed to access the app even when debug is enabled
    'trusted_ips_for_debug' => env('SECURITY_TRUSTED_IPS', '') !== ''
        ? explode(',', (string) env('SECURITY_TRUSTED_IPS'))
        : [],

    /*
    |--------------------------------------------------------------------------
    | Error Display Settings
    |--------------------------------------------------------------------------
    |
    | Control how errors are displayed in different environments.
    |
    */

    // Always hide detailed errors in production regardless of APP_DEBUG
    'hide_errors_in_production' => true,

    /*
    |--------------------------------------------------------------------------
    | Security Logging
    |--------------------------------------------------------------------------
    |
    | Configure security event logging behavior.
    |
    */

    // Log security-sensitive events
    'log_security_events' => env('SECURITY_LOG_EVENTS', true),

    // Log level for security events
    'security_log_level' => env('SECURITY_LOG_LEVEL', 'warning'),
];
