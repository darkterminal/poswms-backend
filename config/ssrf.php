<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SSRF Protection Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the Server-Side Request Forgery (SSRF) protection settings
    | for webhook URLs and other external HTTP requests.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Security Strict Mode
    |--------------------------------------------------------------------------
    |
    | Defines the security enforcement level for SSRF protection.
    | Supported modes:
    | - OFF: Log only, no blocking (development/testing)
    | - SOFT: Partial enforcement (staging/pre-production)
    | - STRICT: Full enforcement with all security checks (production)
    |
    | Default: OFF (log only)
    |
    */
    'strict_mode' => env('SECURITY_STRICT_MODE', 'OFF'),

    /*
    |--------------------------------------------------------------------------
    | Allowlist Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, only URLs from the allowlist below will be permitted.
    | This provides the highest level of security but requires maintaining
    | a list of trusted domains.
    |
    */
    'allowlist_enabled' => env('SSRF_ALLOWLIST_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Allowed Domains
    |--------------------------------------------------------------------------
    |
    | List of domains that are allowed to receive webhook requests.
    | Wildcards are supported (e.g., '*.example.com').
    |
    | These domains are only checked when allowlist_enabled is true.
    | When allowlist_enabled is false, all public domains are allowed
    | (subject to private IP blocking).
    |
    */
    'allowed_domains' => [
        // Example:
        // 'hooks.slack.com',
        // 'discord.com',
        // '*.example.com',
        // 'api.github.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Blocked IP Patterns
    |--------------------------------------------------------------------------
    |
    | Additional IP patterns to block (regex format).
    | These are merged with the default blocked patterns in UrlValidationService.
    |
    */
    'blocked_ip_patterns' => [
        // Additional custom patterns can be added here
    ],

    /*
    |--------------------------------------------------------------------------
    | Blocked Hostnames
    |--------------------------------------------------------------------------
    |
    | Additional hostnames to block.
    | These are merged with the default blocked hostnames in UrlValidationService.
    |
    */
    'blocked_hostnames' => [
        // Additional custom hostnames can be added here
    ],

    /*
    |--------------------------------------------------------------------------
    | DNS Rebinding Protection
    |--------------------------------------------------------------------------
    |
    | Enable DNS rebinding protection by resolving hostnames twice with a delay.
    | This prevents attackers from using DNS rebinding to bypass IP checks.
    |
    | Note: This adds a small delay (~100ms) to URL validation.
    |
    */
    'dns_rebinding_protection' => env('SSRF_DNS_REBINDING_PROTECTION', true),

    /*
    |--------------------------------------------------------------------------
    | Redirect Validation
    |--------------------------------------------------------------------------
    |
    | When enabled, the service will check if URLs redirect to blocked locations.
    | This prevents attackers from using redirects to bypass SSRF protection.
    |
    | Note: This makes an additional HTTP HEAD request during validation.
    |
    */
    'validate_redirects' => env('SSRF_VALIDATE_REDIRECTS', false),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging behavior for SSRF-related events.
    |
    */
    'logging' => [
        // Log blocked SSRF attempts
        'log_blocked' => env('SSRF_LOG_BLOCKED', true),

        // Log successful validations (verbose)
        'log_validated' => env('SSRF_LOG_VALIDATED', false),

        // Log level for blocked attempts (info, warning, error, critical)
        'blocked_level' => env('SSRF_LOG_LEVEL', 'warning'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Enable audit logging for SSRF events. Creates entries in the audit_logs
    | table for security analysis and compliance.
    |
    */
    'audit_logging' => env('SSRF_AUDIT_LOGGING', true),

    /*
    |--------------------------------------------------------------------------
    | Testing Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, some security checks are relaxed for testing purposes:
    | - DNS rebinding check is skipped
    | - Non-resolvable hostnames may be allowed
    |
    | This should only be enabled in testing environments.
    |
    */
    'testing_mode' => env('SSRF_TESTING_MODE', false),
];
