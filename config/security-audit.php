<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Audit Logging Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file controls the behavior of the security audit
    | logging system for OWASP A09:2021 - Security Logging and Monitoring.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Logging Mode
    |--------------------------------------------------------------------------
    |
    | Controls whether security events are logged synchronously or
    | asynchronously via queue. Async is recommended for production
    | to avoid performance degradation during critical operations.
    |
    | Supported: "async", "sync"
    |
    */

    'mode' => env('SECURITY_AUDIT_MODE', 'async'),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | The queue name to use for async security audit logging.
    | This should be a dedicated queue for security events.
    |
    */

    'queue' => env('SECURITY_AUDIT_QUEUE', 'security-audit'),

    /*
    |--------------------------------------------------------------------------
    | High-Risk Event Categories
    |--------------------------------------------------------------------------
    |
    | Define which event categories are considered high-risk and should
    | always be logged to the application log channel in addition to
    | the audit log database table.
    |
    */

    'high_risk_categories' => [
        'auth',
        'authorization',
        'security',
        'webhook',
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channel
    |--------------------------------------------------------------------------
    |
    | The log channel to use for security events. This should be configured
    | in config/logging.php to write to a dedicated security log file or
    | external logging service.
    |
    */

    'log_channel' => env('SECURITY_LOG_CHANNEL', 'security'),

    /*
    |--------------------------------------------------------------------------
    | Retention Period
    |--------------------------------------------------------------------------
    |
    | Number of days to retain audit log entries. Set to 0 for indefinite.
    | Consider compliance requirements (e.g., SOC 2, PCI DSS) when setting.
    |
    */

    'retention_days' => env('SECURITY_AUDIT_RETENTION', 365),

    /*
    |--------------------------------------------------------------------------
    | Alert Thresholds
    |--------------------------------------------------------------------------
    |
    | Configure thresholds for triggering alerts on security events.
    | These can be used by monitoring systems to detect anomalies.
    |
    */

    'alert_thresholds' => [
        // Number of failed login attempts before alerting
        'failed_logins' => env('SECURITY_ALERT_FAILED_LOGINS', 10),

        // Number of permission denials before alerting
        'permission_denials' => env('SECURITY_ALERT_PERMISSION_DENIALS', 20),

        // Number of SSRF attempts before alerting
        'ssrf_attempts' => env('SECURITY_ALERT_SSRF_ATTEMPTS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | IP Whitelist for Logging
    |--------------------------------------------------------------------------
    |
    | IP addresses or ranges to exclude from certain security logging.
    | Useful for excluding internal monitoring systems or trusted IPs.
    |
    */

    'ip_whitelist' => array_filter(
        explode(',', env('SECURITY_IP_WHITELIST', ''))
    ),

    /*
    |--------------------------------------------------------------------------
    | Event-Specific Settings
    |--------------------------------------------------------------------------
    |
    | Fine-tune logging behavior for specific event types.
    |
    */

    'events' => [
        // Authentication events
        'auth.login_failed' => [
            'enabled' => true,
            'log_level' => 'warning',
            'async' => false, // Log sync for immediate visibility
        ],

        'auth.login_locked' => [
            'enabled' => true,
            'log_level' => 'error',
            'async' => false,
        ],

        'auth.login_success' => [
            'enabled' => true,
            'log_level' => 'info',
            'async' => true,
        ],

        // Authorization events
        'authorization.denied' => [
            'enabled' => true,
            'log_level' => 'warning',
            'async' => true,
        ],

        // Webhook events
        'webhook.ssrf_blocked' => [
            'enabled' => true,
            'log_level' => 'error',
            'async' => false,
        ],

        // Security events
        'security.ssrf_detected' => [
            'enabled' => true,
            'log_level' => 'error',
            'async' => false,
        ],

        'security.suspicious_activity' => [
            'enabled' => true,
            'log_level' => 'warning',
            'async' => true,
        ],
    ],
];
