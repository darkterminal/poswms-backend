<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            return;
        }

        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'precision' => 2, 'active' => true],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'precision' => 2, 'active' => true],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'precision' => 2, 'active' => true],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'precision' => 0, 'active' => true],
            ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'C$', 'precision' => 2, 'active' => true],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$', 'precision' => 2, 'active' => true],
            ['code' => 'CHF', 'name' => 'Swiss Franc', 'symbol' => 'CHF', 'precision' => 2, 'active' => true],
            ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥', 'precision' => 2, 'active' => true],
            ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹', 'precision' => 2, 'active' => true],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$', 'precision' => 2, 'active' => true],
            ['code' => 'HKD', 'name' => 'Hong Kong Dollar', 'symbol' => 'HK$', 'precision' => 2, 'active' => true],
            ['code' => 'NZD', 'name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'precision' => 2, 'active' => true],
            ['code' => 'SEK', 'name' => 'Swedish Krona', 'symbol' => 'kr', 'precision' => 2, 'active' => true],
            ['code' => 'NOK', 'name' => 'Norwegian Krone', 'symbol' => 'kr', 'precision' => 2, 'active' => true],
            ['code' => 'DKK', 'name' => 'Danish Krone', 'symbol' => 'kr', 'precision' => 2, 'active' => true],
            ['code' => 'ZAR', 'name' => 'South African Rand', 'symbol' => 'R', 'precision' => 2, 'active' => true],
            ['code' => 'BRL', 'name' => 'Brazilian Real', 'symbol' => 'R$', 'precision' => 2, 'active' => true],
            ['code' => 'MXN', 'name' => 'Mexican Peso', 'symbol' => '$', 'precision' => 2, 'active' => true],
            ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'د.إ', 'precision' => 2, 'active' => true],
            ['code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => '﷼', 'precision' => 2, 'active' => true],
        ];

        foreach ($tenants as $tenant) {
            foreach ($currencies as $currencyData) {
                // Check if currency exists for this tenant
                $currency = Currency::where('tenant_id', $tenant->id)
                    ->where('code', $currencyData['code'])
                    ->first();

                if ($currency) {
                    // Update existing
                    $currency->update([
                        'name' => $currencyData['name'],
                        'symbol' => $currencyData['symbol'],
                        'precision' => $currencyData['precision'],
                        'active' => $currencyData['active'],
                    ]);
                } else {
                    // Create new - but first check if code exists globally
                    $existingCurrency = Currency::where('code', $currencyData['code'])->first();

                    if ($existingCurrency) {
                        // Update the existing one with new tenant_id
                        $existingCurrency->update([
                            'tenant_id' => $tenant->id,
                            'name' => $currencyData['name'],
                            'symbol' => $currencyData['symbol'],
                            'precision' => $currencyData['precision'],
                            'active' => $currencyData['active'],
                        ]);
                    } else {
                        // Create new
                        Currency::create([
                            'tenant_id' => $tenant->id,
                            'code' => $currencyData['code'],
                            'name' => $currencyData['name'],
                            'symbol' => $currencyData['symbol'],
                            'precision' => $currencyData['precision'],
                            'active' => $currencyData['active'],
                        ]);
                    }
                }
            }

            // Set default currency based on tenant settings
            $defaultCurrency = $tenant->currency ?? 'USD';
            // First, unset all defaults for this tenant
            Currency::where('tenant_id', $tenant->id)->update(['is_default' => false]);

            // Then set the default
            $currency = Currency::where('tenant_id', $tenant->id)
                ->where('code', $defaultCurrency)
                ->first();

            if ($currency) {
                $currency->update(['is_default' => true]);
            }
        }
    }
}
