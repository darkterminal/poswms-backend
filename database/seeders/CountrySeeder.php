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
            ['name' => 'United States', 'code' => 'US', 'phone_code' => '+1'],
            ['name' => 'United Kingdom', 'code' => 'GB', 'phone_code' => '+44'],
            ['name' => 'Canada', 'code' => 'CA', 'phone_code' => '+1'],
            ['name' => 'Australia', 'code' => 'AU', 'phone_code' => '+61'],
            ['name' => 'Germany', 'code' => 'DE', 'phone_code' => '+49'],
            ['name' => 'France', 'code' => 'FR', 'phone_code' => '+33'],
            ['name' => 'Italy', 'code' => 'IT', 'phone_code' => '+39'],
            ['name' => 'Spain', 'code' => 'ES', 'phone_code' => '+34'],
            ['name' => 'Netherlands', 'code' => 'NL', 'phone_code' => '+31'],
            ['name' => 'Belgium', 'code' => 'BE', 'phone_code' => '+32'],
            ['name' => 'Sweden', 'code' => 'SE', 'phone_code' => '+46'],
            ['name' => 'Norway', 'code' => 'NO', 'phone_code' => '+47'],
            ['name' => 'Denmark', 'code' => 'DK', 'phone_code' => '+45'],
            ['name' => 'Finland', 'code' => 'FI', 'phone_code' => '+358'],
            ['name' => 'Poland', 'code' => 'PL', 'phone_code' => '+48'],
            ['name' => 'Switzerland', 'code' => 'CH', 'phone_code' => '+41'],
            ['name' => 'Austria', 'code' => 'AT', 'phone_code' => '+43'],
            ['name' => 'Ireland', 'code' => 'IE', 'phone_code' => '+353'],
            ['name' => 'Portugal', 'code' => 'PT', 'phone_code' => '+351'],
            ['name' => 'Greece', 'code' => 'GR', 'phone_code' => '+30'],
            ['name' => 'Japan', 'code' => 'JP', 'phone_code' => '+81'],
            ['name' => 'China', 'code' => 'CN', 'phone_code' => '+86'],
            ['name' => 'South Korea', 'code' => 'KR', 'phone_code' => '+82'],
            ['name' => 'India', 'code' => 'IN', 'phone_code' => '+91'],
            ['name' => 'Singapore', 'code' => 'SG', 'phone_code' => '+65'],
            ['name' => 'Malaysia', 'code' => 'MY', 'phone_code' => '+60'],
            ['name' => 'Thailand', 'code' => 'TH', 'phone_code' => '+66'],
            ['name' => 'Indonesia', 'code' => 'ID', 'phone_code' => '+62'],
            ['name' => 'Philippines', 'code' => 'PH', 'phone_code' => '+63'],
            ['name' => 'Vietnam', 'code' => 'VN', 'phone_code' => '+84'],
            ['name' => 'Brazil', 'code' => 'BR', 'phone_code' => '+55'],
            ['name' => 'Mexico', 'code' => 'MX', 'phone_code' => '+52'],
            ['name' => 'Argentina', 'code' => 'AR', 'phone_code' => '+54'],
            ['name' => 'Chile', 'code' => 'CL', 'phone_code' => '+56'],
            ['name' => 'Colombia', 'code' => 'CO', 'phone_code' => '+57'],
            ['name' => 'South Africa', 'code' => 'ZA', 'phone_code' => '+27'],
            ['name' => 'Egypt', 'code' => 'EG', 'phone_code' => '+20'],
            ['name' => 'Nigeria', 'code' => 'NG', 'phone_code' => '+234'],
            ['name' => 'Kenya', 'code' => 'KE', 'phone_code' => '+254'],
            ['name' => 'United Arab Emirates', 'code' => 'AE', 'phone_code' => '+971'],
            ['name' => 'Saudi Arabia', 'code' => 'SA', 'phone_code' => '+966'],
            ['name' => 'Israel', 'code' => 'IL', 'phone_code' => '+972'],
            ['name' => 'Turkey', 'code' => 'TR', 'phone_code' => '+90'],
            ['name' => 'Russia', 'code' => 'RU', 'phone_code' => '+7'],
            ['name' => 'Ukraine', 'code' => 'UA', 'phone_code' => '+380'],
            ['name' => 'New Zealand', 'code' => 'NZ', 'phone_code' => '+64'],
        ];

        foreach ($countries as $countryData) {
            Country::firstOrCreate(
                ['code' => $countryData['code']],
                $countryData
            );
        }
    }
}
