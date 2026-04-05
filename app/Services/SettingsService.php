<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class SettingsService
{
    /**
     * Default settings grouped by category.
     */
    protected array $defaults = [
        'application' => [
            'name' => ['value' => 'POS WMS Backend', 'type' => 'string'],
            'url' => ['value' => 'http://localhost:8000', 'type' => 'string'],
            'timezone' => ['value' => 'UTC', 'type' => 'string'],
            'locale' => ['value' => 'en', 'type' => 'string'],
            'fallback_locale' => ['value' => 'en', 'type' => 'string'],
            'debug' => ['value' => true, 'type' => 'boolean'],
            'default_currency' => ['value' => 'USD', 'type' => 'string'],
        ],
        'database' => [
            'default' => ['value' => 'sqlite', 'type' => 'string'],
        ],
        'cache' => [
            'default' => ['value' => 'database', 'type' => 'string'],
            'prefix' => ['value' => 'poswms', 'type' => 'string'],
        ],
        'queue' => [
            'default' => ['value' => 'database', 'type' => 'string'],
        ],
        'mail' => [
            'default' => ['value' => 'smtp', 'type' => 'string'],
            'from_address' => ['value' => 'noreply@example.com', 'type' => 'string'],
            'from_name' => ['value' => 'POS WMS', 'type' => 'string'],
        ],
        'features' => [
            'rate_limiting' => ['value' => true, 'type' => 'boolean'],
            'audit_logging' => ['value' => true, 'type' => 'boolean'],
            'webhooks' => ['value' => true, 'type' => 'boolean'],
            'exports' => ['value' => true, 'type' => 'boolean'],
        ],
    ];

    /**
     * Get all settings grouped by group.
     * Falls back to config values if database settings don't exist.
     */
    public function getAll(): array
    {
        $dbSettings = Setting::getAllGrouped();

        // Merge with config values as fallback
        return [
            'application' => array_merge($this->getConfigDefaults('application'), $dbSettings['application'] ?? []),
            'database' => array_merge($this->getConfigDefaults('database'), $dbSettings['database'] ?? []),
            'cache' => array_merge($this->getConfigDefaults('cache'), $dbSettings['cache'] ?? []),
            'queue' => array_merge($this->getConfigDefaults('queue'), $dbSettings['queue'] ?? []),
            'mail' => array_merge($this->getConfigDefaults('mail'), $dbSettings['mail'] ?? []),
            'features' => array_merge($this->getConfigDefaults('features'), $dbSettings['features'] ?? []),
        ];
    }

    /**
     * Update settings.
     */
    public function update(array $settings, ?int $userId = null): array
    {
        $updated = [];

        foreach ($settings as $group => $values) {
            if (! is_array($values)) {
                continue;
            }

            foreach ($values as $key => $value) {
                $fullKey = "{$group}.{$key}";
                
                // Determine type
                $type = $this->defaults[$group][$key]['type'] ?? null;
                
                // Set the setting
                Setting::set($fullKey, $value, $type, $userId);
                
                $updated[$group][$key] = $value;
                
                Log::info("System setting updated: {$fullKey}", [
                    'value' => $value,
                    'user_id' => $userId,
                ]);
            }
        }

        // Clear settings cache
        Setting::clearCache();

        return $updated;
    }

    /**
     * Initialize default settings in database.
     */
    public function initializeDefaults(?int $userId = null): void
    {
        foreach ($this->defaults as $group => $settings) {
            foreach ($settings as $key => $config) {
                $fullKey = "{$group}.{$key}";
                
                // Only create if doesn't exist
                if (! Setting::where('key', $fullKey)->exists()) {
                    Setting::set($fullKey, $config['value'], $config['type'], $userId);
                }
            }
        }

        Log::info('Default system settings initialized');
    }

    /**
     * Get config defaults for a group.
     */
    protected function getConfigDefaults(string $group): array
    {
        $defaults = [];

        foreach ($this->defaults[$group] ?? [] as $key => $config) {
            $defaults[$key] = $config['value'];
        }

        return $defaults;
    }

    /**
     * Get a single setting value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }

    /**
     * Reset a setting to its default value.
     */
    public function reset(string $key): bool
    {
        $group = explode('.', $key)[0];
        $settingKey = last(explode('.', $key));

        if (isset($this->defaults[$group][$settingKey])) {
            $default = $this->defaults[$group][$settingKey];
            Setting::set($key, $default['value'], $default['type']);

            return true;
        }

        return false;
    }

    /**
     * Get settings history.
     */
    public function getHistory(string $key, int $limit = 10): array
    {
        return Setting::where('key', $key)
            ->with('modifiedBy')
            ->orderBy('last_modified_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($setting) {
                return [
                    'value' => $setting->typed_value,
                    'modified_at' => $setting->last_modified_at?->toIso8601String(),
                    'modified_by' => $setting->modifiedBy?->name,
                    'modified_by_email' => $setting->modifiedBy?->email,
                ];
            })
            ->toArray();
    }
}
