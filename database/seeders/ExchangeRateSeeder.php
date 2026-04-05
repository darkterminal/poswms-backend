<?php

namespace Database\Seeders;

use App\Models\CurrencyExchangeRate;
use Illuminate\Database\Seeder;

class ExchangeRateSeeder extends Seeder
{
    /**
     * Default exchange rates relative to USD.
     * These are approximate rates and should be synced from ECB in production.
     *
     * Format: 1 USD = X target_currency
     */
    protected array $defaultRates = [
        'EUR' => 0.92,
        'GBP' => 0.79,
        'JPY' => 149.50,
        'IDR' => 15650.00,
        'SGD' => 1.34,
        'MYR' => 4.47,
        'THB' => 34.50,
        'PHP' => 56.50,
        'VND' => 24500.00,
        'CNY' => 7.24,
        'AUD' => 1.53,
        'CAD' => 1.36,
        'CHF' => 0.88,
        'INR' => 83.10,
        'KRW' => 1320.00,
        'HKD' => 7.82,
        'NZD' => 1.63,
        'AED' => 3.67,
        'SAR' => 3.75,
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->defaultRates as $code => $rate) {
            CurrencyExchangeRate::updateRate(
                'USD',
                $code,
                $rate,
                'seed'
            );
        }

        // Also add inverse rates for completeness
        foreach ($this->defaultRates as $code => $rate) {
            if ($code !== 'USD') {
                CurrencyExchangeRate::updateRate(
                    $code,
                    'USD',
                    1 / $rate,
                    'seed'
                );
            }
        }

        // Add cross-rates for major pairs
        $this->addCrossRates();
    }

    /**
     * Add common cross-rates between major currencies.
     */
    protected function addCrossRates(): void
    {
        $crossRates = [
            ['EUR', 'GBP', 0.86],
            ['EUR', 'JPY', 162.50],
            ['EUR', 'IDR', 17010.00],
            ['EUR', 'SGD', 1.46],
            ['GBP', 'JPY', 189.24],
            ['GBP', 'IDR', 19810.00],
            ['SGD', 'MYR', 3.34],
            ['SGD', 'IDR', 11679.00],
            ['JPY', 'IDR', 104.68],
        ];

        foreach ($crossRates as [$from, $to, $rate]) {
            CurrencyExchangeRate::updateRate($from, $to, $rate, 'seed');
        }
    }
}
