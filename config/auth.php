<?php

use App\Models\User;

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    /*
    |--------------------------------------------------------------------------
    | Login Attempt Security Settings
    |--------------------------------------------------------------------------
    |
    | Configure the progressive delay and lockout system for failed login
    | attempts. This helps prevent brute force attacks while avoiding
    | sudden lockouts that could block legitimate users.
    |
    | Strategy: Soft lockout with progressive delays
    | - Warning after X attempts (warning_threshold)
    | - Progressive delays increase with each attempt
    | - Hard lockout after max_attempts
    | - Exponential backoff for repeated lockouts
    |
    */

    'login' => [
        // Maximum failed attempts before hard lockout
        'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),

        // Number of attempts before showing warning
        'warning_threshold' => (int) env('LOGIN_WARNING_THRESHOLD', 3),

        // Base lockout duration in seconds (5 minutes)
        'base_lockout_duration' => (int) env('LOGIN_BASE_LOCKOUT_DURATION', 300),

        // Maximum lockout duration in seconds (30 minutes)
        'max_lockout_duration' => (int) env('LOGIN_MAX_LOCKOUT_DURATION', 1800),

        // Cache TTL for failed attempts in seconds (15 minutes)
        'cache_ttl' => (int) env('LOGIN_CACHE_TTL', 900),

        // Delay multiplier for progressive delays (2x = 2s, 4s, 8s, 16s...)
        'delay_multiplier' => (int) env('LOGIN_DELAY_MULTIPLIER', 2),

        // Hours considered unusual for login (for suspicious activity detection)
        'unusual_hours' => [0, 1, 2, 3, 4, 5],
    ],
];
