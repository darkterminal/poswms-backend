<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Initializing system settings...');

        // Use the SettingsService to initialize defaults
        $settingsService = app(SettingsService::class);
        $settingsService->initializeDefaults();

        $this->command->info('✓ System settings initialized successfully');

        // Show count of settings created
        $count = Setting::count();
        $this->command->info("Total settings in database: {$count}");
    }
}
