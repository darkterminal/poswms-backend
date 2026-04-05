<?php

namespace App\Services;

use App\Models\CurrencyExchangeRate;
use App\Models\Setting;
use App\Models\Tenant;
use Exchanger\CurrencyPair;
use Exchanger\ExchangeRateQuery;
use Exchanger\Exchanger;
use Exchanger\Service\EuropeanCentralBank;
use GuzzleHttp\Client;
use Http\Adapter\Guzzle7\Client as Guzzle7Adapter;
use Money\Currencies\ISOCurrencies;
use Money\Currency as MoneyCurrency;
use Money\Exception\UnknownCurrencyException;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use Money\Parser\DecimalMoneyParser;
use NumberFormatter;

class MoneyService
{
    protected ISOCurrencies $currencies;

    public function __construct()
    {
        $this->currencies = new ISOCurrencies();
    }

    /**
     * Convert a decimal amount to minor units (cents).
     */
    protected function toMinorUnits(float|string $amount, string $currencyCode): int
    {
        $currency = new MoneyCurrency($currencyCode);
        $subunit = $this->currencies->subunitFor($currency);

        return (int) round((float) $amount * pow(10, $subunit));
    }

    /**
     * Create a Money instance from a decimal amount and currency code.
     */
    public function make(float|int|string $amount, string $currencyCode = 'USD'): Money
    {
        $currency = new MoneyCurrency($currencyCode);
        $minorUnits = $this->toMinorUnits($amount, $currencyCode);

        return new Money($minorUnits, $currency);
    }

    /**
     * Create a Money instance from a minor unit (cents) amount.
     */
    public function makeFromMinorUnits(int $minorUnits, string $currencyCode = 'USD'): Money
    {
        $currency = new MoneyCurrency($currencyCode);

        return new Money($minorUnits, $currency);
    }

    /**
     * Convert money from one currency to another using stored exchange rates.
     */
    public function convert(Money $money, string $targetCurrency): Money
    {
        if ($money->getCurrency()->getCode() === $targetCurrency) {
            return $money;
        }

        $rate = $this->getExchangeRate(
            $money->getCurrency()->getCode(),
            $targetCurrency
        );

        if ($rate === null) {
            throw new \InvalidArgumentException(
                "No exchange rate found for {$money->getCurrency()->getCode()} to {$targetCurrency}"
            );
        }

        $decimalFormatter = new DecimalMoneyFormatter($this->currencies);
        $amount = (float) $decimalFormatter->format($money);
        $convertedAmount = $amount * $rate;

        return $this->make($convertedAmount, $targetCurrency);
    }

    /**
     * Get exchange rate between two currencies.
     * Returns rate as: 1 unit of $from = ? units of $to
     */
    public function getExchangeRate(string $from, string $to): ?float
    {
        if ($from === $to) {
            return 1.0;
        }

        // Check direct rate
        $directRate = CurrencyExchangeRate::getRate($from, $to);
        if ($directRate !== null) {
            return $directRate;
        }

        // Try inverse rate
        $inverseRate = CurrencyExchangeRate::getRate($to, $from);
        if ($inverseRate !== null && $inverseRate > 0) {
            return 1.0 / $inverseRate;
        }

        // Try cross-rate via a common base currency (USD or EUR)
        $baseCurrencies = ['USD', 'EUR'];
        foreach ($baseCurrencies as $base) {
            $fromBase = CurrencyExchangeRate::getRate($base, $from);
            $toBase = CurrencyExchangeRate::getRate($base, $to);

            if ($fromBase !== null && $toBase !== null && $fromBase > 0) {
                return $toBase / $fromBase;
            }
        }

        return null;
    }

    /**
     * Format money for display.
     */
    public function format(Money $money, ?string $locale = null): string
    {
        $locale = $locale ?? 'en_US';
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        $intlFormatter = new IntlMoneyFormatter($formatter, $this->currencies);

        return $intlFormatter->format($money);
    }

    /**
     * Format money as a simple decimal string (no locale-specific formatting).
     */
    public function formatDecimal(Money $money): string
    {
        $formatter = new DecimalMoneyFormatter($this->currencies);

        return $formatter->format($money);
    }

    /**
     * Parse a decimal string into a Money object.
     */
    public function parse(string $amount, string $currencyCode = 'USD'): Money
    {
        $parser = new DecimalMoneyParser($this->currencies);

        return $parser->parse($amount, new MoneyCurrency($currencyCode));
    }

    /**
     * Get the system default currency.
     */
    public function getDefaultCurrency(): string
    {
        return Setting::get('application.default_currency', config('money.default_currency', 'USD'));
    }

    /**
     * Get the tenant's base currency.
     */
    public function getTenantCurrency(Tenant $tenant): string
    {
        return $tenant->resolveCurrency();
    }

    /**
     * Convert a decimal amount between currencies.
     */
    public function convertAmount(float $amount, string $fromCurrency, string $toCurrency): float
    {
        $money = $this->make($amount, $fromCurrency);
        $converted = $this->convert($money, $toCurrency);

        return (float) $this->formatDecimal($converted);
    }

    /**
     * Get all available ISO currencies.
     */
    public function getAvailableCurrencies(): array
    {
        $currencies = [];
        foreach ($this->currencies as $currency) {
            $currencies[$currency->getCode()] = $currency->getCode();
        }

        return $currencies;
    }

    /**
     * Get currency info (code, name, decimal places).
     */
    public function getCurrencyInfo(string $currencyCode): ?array
    {
        try {
            $currency = new MoneyCurrency($currencyCode);

            // Get localized currency name using PHP's ResourceBundle
            $name = $this->getCurrencyName($currencyCode);

            return [
                'code' => $currency->getCode(),
                'name' => $name,
                'decimal_places' => $this->currencies->subunitFor($currency),
            ];
        } catch (UnknownCurrencyException) {
            return null;
        }
    }

    /**
     * Get localized currency name.
     */
    protected function getCurrencyName(string $currencyCode): string
    {
        // Try ResourceBundle for full currency name
        if (class_exists('\\ResourceBundle')) {
            try {
                $bundle = new \ResourceBundle('en', 'ICUDATA-currency');
                if (isset($bundle[$currencyCode])) {
                    return $bundle[$currencyCode];
                }
            } catch (\Exception) {
                // ResourceBundle not available, continue to fallback
            }
        }

        // Use static mapping for common currencies
        return $this->getCurrencyNameFromMapping($currencyCode);
    }

    /**
     * Get currency name from static mapping.
     */
    protected function getCurrencyNameFromMapping(string $code): string
    {
        $currencyNames = [
            'USD' => 'US Dollar',
            'EUR' => 'Euro',
            'GBP' => 'British Pound',
            'JPY' => 'Japanese Yen',
            'CNY' => 'Chinese Yuan',
            'AUD' => 'Australian Dollar',
            'CAD' => 'Canadian Dollar',
            'CHF' => 'Swiss Franc',
            'INR' => 'Indian Rupee',
            'IDR' => 'Indonesian Rupiah',
            'SGD' => 'Singapore Dollar',
            'MYR' => 'Malaysian Ringgit',
            'THB' => 'Thai Baht',
            'PHP' => 'Philippine Peso',
            'VND' => 'Vietnamese Dong',
            'KRW' => 'South Korean Won',
            'HKD' => 'Hong Kong Dollar',
            'NZD' => 'New Zealand Dollar',
            'AED' => 'UAE Dirham',
            'SAR' => 'Saudi Riyal',
            'BRL' => 'Brazilian Real',
            'MXN' => 'Mexican Peso',
            'ZAR' => 'South African Rand',
            'RUB' => 'Russian Ruble',
            'TRY' => 'Turkish Lira',
            'SEK' => 'Swedish Krona',
            'NOK' => 'Norwegian Krone',
            'DKK' => 'Danish Krone',
            'PLN' => 'Polish Zloty',
            'CZK' => 'Czech Koruna',
            'HUF' => 'Hungarian Forint',
            'RON' => 'Romanian Leu',
            'BGN' => 'Bulgarian Lev',
            'HRK' => 'Croatian Kuna',
            'ISK' => 'Icelandic Krona',
            'ILS' => 'Israeli Shekel',
            'EGP' => 'Egyptian Pound',
            'NGN' => 'Nigerian Naira',
            'KES' => 'Kenyan Shilling',
            'GHS' => 'Ghanaian Cedi',
            'PKR' => 'Pakistani Rupee',
            'BDT' => 'Bangladeshi Taka',
            'LKR' => 'Sri Lankan Rupee',
            'NPR' => 'Nepalese Rupee',
            'MMK' => 'Myanmar Kyat',
            'KHR' => 'Cambodian Riel',
            'LAK' => 'Lao Kip',
            'TWD' => 'New Taiwan Dollar',
            'PEN' => 'Peruvian Sol',
            'COP' => 'Colombian Peso',
            'CLP' => 'Chilean Peso',
            'ARS' => 'Argentine Peso',
            'UYU' => 'Uruguayan Peso',
        ];

        return $currencyNames[$code] ?? $code;
    }

    /**
     * Sync exchange rates from European Central Bank.
     * ECB provides rates with EUR as base currency.
     */
    public function syncRatesFromECB(): int
    {
        $httpClient = new Guzzle7Adapter(new Client());
        $ecbService = new EuropeanCentralBank($httpClient);
        $exchanger = new Exchanger($ecbService);

        // ECB provides rates for EUR base currency
        // Get all available ISO currencies except EUR
        $targetCurrencies = array_filter(
            $this->getAvailableCurrencies(),
            fn ($code) => $code !== 'EUR'
        );

        $ratesSynced = 0;

        foreach ($targetCurrencies as $currencyCode) {
            try {
                $currencyPair = new CurrencyPair('EUR', $currencyCode);
                $query = new ExchangeRateQuery($currencyPair);
                $rate = $exchanger->getExchangeRate($query);

                CurrencyExchangeRate::updateRate(
                    'EUR',
                    $currencyCode,
                    $rate->getValue(),
                    'ecb'
                );

                // Also store the inverse rate (currency to EUR)
                CurrencyExchangeRate::updateRate(
                    $currencyCode,
                    'EUR',
                    1.0 / $rate->getValue(),
                    'ecb'
                );

                $ratesSynced += 2;
            } catch (\Exception $e) {
                // Skip currencies that ECB doesn't support
                continue;
            }
        }

        return $ratesSynced;
    }

    /**
     * Format money in the tenant's currency.
     */
    public function formatForTenant(Money $money, Tenant $tenant, ?string $locale = null): string
    {
        return $this->format($money, $locale);
    }

    /**
     * Create money in tenant's currency from a decimal amount.
     */
    public function makeForTenant(float|int $amount, Tenant $tenant): Money
    {
        $currency = $this->getTenantCurrency($tenant);

        return $this->make($amount, $currency);
    }
}
