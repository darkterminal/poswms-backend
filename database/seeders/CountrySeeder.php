<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            // Southeast Asia (Priority)
            ['name' => 'Indonesia', 'code' => 'ID', 'code3' => 'IDN', 'phone_code' => '+62', 'capital' => 'Jakarta', 'currency' => 'IDR', 'region' => 'Asia', 'subregion' => 'South-Eastern Asia', 'active' => 1],
            ['name' => 'Singapore', 'code' => 'SG', 'code3' => 'SGP', 'phone_code' => '+65', 'capital' => 'Singapore', 'currency' => 'SGD', 'region' => 'Asia', 'subregion' => 'South-Eastern Asia', 'active' => 1],
            ['name' => 'Malaysia', 'code' => 'MY', 'code3' => 'MYS', 'phone_code' => '+60', 'capital' => 'Kuala Lumpur', 'currency' => 'MYR', 'region' => 'Asia', 'subregion' => 'South-Eastern Asia', 'active' => 1],
            ['name' => 'Thailand', 'code' => 'TH', 'code3' => 'THA', 'phone_code' => '+66', 'capital' => 'Bangkok', 'currency' => 'THB', 'region' => 'Asia', 'subregion' => 'South-Eastern Asia', 'active' => 1],
            ['name' => 'Philippines', 'code' => 'PH', 'code3' => 'PHL', 'phone_code' => '+63', 'capital' => 'Manila', 'currency' => 'PHP', 'region' => 'Asia', 'subregion' => 'South-Eastern Asia', 'active' => 1],
            ['name' => 'Vietnam', 'code' => 'VN', 'code3' => 'VNM', 'phone_code' => '+84', 'capital' => 'Hanoi', 'currency' => 'VND', 'region' => 'Asia', 'subregion' => 'South-Eastern Asia', 'active' => 1],

            // Other Asian Countries
            ['name' => 'Japan', 'code' => 'JP', 'code3' => 'JPN', 'phone_code' => '+81', 'capital' => 'Tokyo', 'currency' => 'JPY', 'region' => 'Asia', 'subregion' => 'Eastern Asia', 'active' => 1],
            ['name' => 'China', 'code' => 'CN', 'code3' => 'CHN', 'phone_code' => '+86', 'capital' => 'Beijing', 'currency' => 'CNY', 'region' => 'Asia', 'subregion' => 'Eastern Asia', 'active' => 1],
            ['name' => 'South Korea', 'code' => 'KR', 'code3' => 'KOR', 'phone_code' => '+82', 'capital' => 'Seoul', 'currency' => 'KRW', 'region' => 'Asia', 'subregion' => 'Eastern Asia', 'active' => 1],
            ['name' => 'India', 'code' => 'IN', 'code3' => 'IND', 'phone_code' => '+91', 'capital' => 'New Delhi', 'currency' => 'INR', 'region' => 'Asia', 'subregion' => 'Southern Asia', 'active' => 1],
            ['name' => 'United Arab Emirates', 'code' => 'AE', 'code3' => 'ARE', 'phone_code' => '+971', 'capital' => 'Abu Dhabi', 'currency' => 'AED', 'region' => 'Asia', 'subregion' => 'Western Asia', 'active' => 1],
            ['name' => 'Saudi Arabia', 'code' => 'SA', 'code3' => 'SAU', 'phone_code' => '+966', 'capital' => 'Riyadh', 'currency' => 'SAR', 'region' => 'Asia', 'subregion' => 'Western Asia', 'active' => 1],

            // Europe
            ['name' => 'United Kingdom', 'code' => 'GB', 'code3' => 'GBR', 'phone_code' => '+44', 'capital' => 'London', 'currency' => 'GBP', 'region' => 'Europe', 'subregion' => 'Northern Europe', 'active' => 1],
            ['name' => 'Germany', 'code' => 'DE', 'code3' => 'DEU', 'phone_code' => '+49', 'capital' => 'Berlin', 'currency' => 'EUR', 'region' => 'Europe', 'subregion' => 'Western Europe', 'active' => 1],
            ['name' => 'France', 'code' => 'FR', 'code3' => 'FRA', 'phone_code' => '+33', 'capital' => 'Paris', 'currency' => 'EUR', 'region' => 'Europe', 'subregion' => 'Western Europe', 'active' => 1],
            ['name' => 'Netherlands', 'code' => 'NL', 'code3' => 'NLD', 'phone_code' => '+31', 'capital' => 'Amsterdam', 'currency' => 'EUR', 'region' => 'Europe', 'subregion' => 'Western Europe', 'active' => 1],
            ['name' => 'Switzerland', 'code' => 'CH', 'code3' => 'CHE', 'phone_code' => '+41', 'capital' => 'Bern', 'currency' => 'CHF', 'region' => 'Europe', 'subregion' => 'Western Europe', 'active' => 1],

            // Americas
            ['name' => 'United States', 'code' => 'US', 'code3' => 'USA', 'phone_code' => '+1', 'capital' => 'Washington D.C.', 'currency' => 'USD', 'region' => 'Americas', 'subregion' => 'Northern America', 'active' => 1],
            ['name' => 'Canada', 'code' => 'CA', 'code3' => 'CAN', 'phone_code' => '+1', 'capital' => 'Ottawa', 'currency' => 'CAD', 'region' => 'Americas', 'subregion' => 'Northern America', 'active' => 1],
            ['name' => 'Australia', 'code' => 'AU', 'code3' => 'AUS', 'phone_code' => '+61', 'capital' => 'Canberra', 'currency' => 'AUD', 'region' => 'Oceania', 'subregion' => 'Australia and New Zealand', 'active' => 1],
            ['name' => 'New Zealand', 'code' => 'NZ', 'code3' => 'NZL', 'phone_code' => '+64', 'capital' => 'Wellington', 'currency' => 'NZD', 'region' => 'Oceania', 'subregion' => 'Australia and New Zealand', 'active' => 1],
        ];

        foreach ($countries as $countryData) {
            Country::firstOrCreate(
                ['code' => $countryData['code']],
                $countryData
            );
        }
    }
}
