<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The default currency code used when none is specified. This should match
    | your system-wide default currency setting.
    |
    */
    'default_currency' => env('MONEY_DEFAULT_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Exchange Rate Provider
    |--------------------------------------------------------------------------
    |
    | Configure the exchange rate provider. Supported drivers:
    |   - "database": Use exchange rates stored in the database
    |   - "ecb": Fetch from European Central Bank (free, ~30 currencies)
    |   - "manual": Use manually configured rates only
    |
    */
    'exchange' => [
        'driver' => env('EXCHANGE_RATE_DRIVER', 'database'),

        // ECB API configuration (used when driver is "ecb")
        'ecb' => [
            'enabled' => env('EXCHANGE_RATE_ECB_ENABLED', true),
            'sync_schedule' => 'daily', // Laravel cron expression
        ],

        // Manual fallback rates (used when driver can't fetch live rates)
        'manual_rates' => [
            // 'EUR' => 1.0,
            // 'USD' => 1.08,
            // 'IDR' => 0.000064,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Formatting
    |--------------------------------------------------------------------------
    |
    | Default formatting options for money display.
    |
    */
    'formatting' => [
        'decimal_places' => 2,
        'decimal_separator' => '.',
        'thousands_separator' => ',',
    ],
];
