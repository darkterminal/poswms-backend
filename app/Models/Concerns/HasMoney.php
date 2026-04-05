<?php

namespace App\Models\Concerns;

use Money\Currencies\ISOCurrencies;
use Money\Currency as MoneyCurrency;
use Money\Money;

/**
 * Trait HasMoney
 *
 * Provides Money object accessors for models with monetary fields.
 *
 * Usage:
 *   class Product extends Model
 *   {
 *       use HasMoney;
 *
 *       protected function moneyFields(): array
 *       {
 *           return ['price', 'cost'];
 *       }
 *   }
 *
 *   $product = Product::find(1);
 *   $money = $product->priceAsMoney(); // Money object
 *   $formatted = $product->formatPrice(); // Formatted string
 */
trait HasMoney
{
    /**
     * Return the list of monetary field names for this model.
     * Override in the using class.
     */
    protected function moneyFields(): array
    {
        return [];
    }

    /**
     * Currency code to use for Money objects.
     * Override this method for custom currency resolution.
     */
    protected function getMoneyCurrency(): string
    {
        // If the model has a resolveCurrency method, use it (e.g., Tenant)
        if (method_exists($this, 'resolveCurrency')) {
            return $this->resolveCurrency();
        }

        // If the model has a tenant relationship, use the tenant's currency
        if (method_exists($this, 'tenant')) {
            $tenant = $this->tenant;
            if ($tenant && method_exists($tenant, 'resolveCurrency')) {
                return $tenant->resolveCurrency();
            }
        }

        return config('money.default_currency', 'USD');
    }

    /**
     * Convert a decimal amount to minor units (cents).
     */
    protected function toMinorUnits(float|string $amount, string $currencyCode): int
    {
        $currencies = new ISOCurrencies();
        $currency = new MoneyCurrency($currencyCode);
        $subunit = $currencies->subunitFor($currency);

        return (int) round((float) $amount * pow(10, $subunit));
    }

    /**
     * Get a monetary field as a Money object.
     */
    public function asMoney(string $field, ?string $currency = null): Money
    {
        $fields = $this->moneyFields();

        if (! in_array($field, $fields, true)) {
            throw new \InvalidArgumentException("Field '{$field}' is not a monetary field on " . get_class($this));
        }

        $currencyCode = $currency ?? $this->getMoneyCurrency();
        $amount = $this->{$field} ?? 0;
        $minorUnits = $this->toMinorUnits($amount, $currencyCode);

        return new Money($minorUnits, new MoneyCurrency($currencyCode));
    }

    /**
     * Format a monetary field using locale-specific formatting.
     */
    public function formatMoney(string $field, ?string $locale = null): string
    {
        $money = $this->asMoney($field);

        return app(\App\Services\MoneyService::class)->format($money, $locale);
    }

    /**
     * Format a monetary field as a simple decimal string.
     */
    public function formatMoneyDecimal(string $field): string
    {
        $money = $this->asMoney($field);

        return app(\App\Services\MoneyService::class)->formatDecimal($money);
    }

    /**
     * Convert a monetary field to another currency.
     */
    public function convertMoney(string $field, string $targetCurrency): Money
    {
        $money = $this->asMoney($field);

        return app(\App\Services\MoneyService::class)->convert($money, $targetCurrency);
    }

    /**
     * Dynamic accessor for each money field.
     * E.g., $product->priceAsMoney() returns Money object for 'price' field.
     */
    public function __call($method, $parameters)
    {
        $fields = $this->moneyFields();

        foreach ($fields as $field) {
            $ucField = ucfirst($field);
            $methodName = $field . 'AsMoney';
            $formatMethod = 'format' . $ucField;
            $decimalMethod = 'format' . $ucField . 'Decimal';
            $convertMethod = 'convert' . $ucField;

            if ($method === $methodName) {
                return $this->asMoney($field, ...$parameters);
            }

            if ($method === $formatMethod) {
                return $this->formatMoney($field, ...$parameters);
            }

            if ($method === $decimalMethod) {
                return $this->formatMoneyDecimal($field);
            }

            if ($method === $convertMethod) {
                return $this->convertMoney($field, ...$parameters);
            }
        }

        return parent::__call($method, $parameters);
    }
}
