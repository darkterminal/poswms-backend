<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'system_settings';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'group',
        'value',
        'type',
        'description',
        'is_public',
        'is_editable',
        'metadata',
        'last_modified_at',
        'modified_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'value' => 'array',
        'metadata' => 'array',
        'is_public' => 'boolean',
        'is_editable' => 'boolean',
        'last_modified_at' => 'datetime',
    ];

    /**
     * The cache key prefix.
     */
    protected const CACHE_PREFIX = 'system_settings.';

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Clear cache when setting is updated
        static::saved(function ($setting) {
            Cache::forget(self::CACHE_PREFIX . $setting->key);
            Cache::forget(self::CACHE_PREFIX . 'all');
            Cache::forget(self::CACHE_PREFIX . 'group.' . $setting->group);
        });

        // Clear cache when setting is deleted
        static::deleted(function ($setting) {
            Cache::forget(self::CACHE_PREFIX . $setting->key);
            Cache::forget(self::CACHE_PREFIX . 'all');
            Cache::forget(self::CACHE_PREFIX . 'group.' . $setting->group);
        });
    }

    /**
     * Get the user who last modified this setting.
     */
    public function modifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }

    /**
     * Get the typed value.
     */
    public function getTypedValueAttribute(): mixed
    {
        return $this->getValue($this->value, $this->type);
    }

    /**
     * Set the typed value.
     */
    public function setTypedValueAttribute(mixed $value): void
    {
        $this->value = $this->encodeValue($value, $this->type);
    }

    /**
     * Get value with proper type casting.
     */
    public function getValue(array $value, string $type): mixed
    {
        $decoded = $value['value'] ?? $value;

        return match ($type) {
            'boolean', 'bool' => (bool) $decoded,
            'integer', 'int' => (int) $decoded,
            'float', 'double' => (float) $decoded,
            'json' => is_string($decoded) ? json_decode($decoded, true) : $decoded,
            'array' => is_string($decoded) ? json_decode($decoded, true) : (array) $decoded,
            default => (string) $decoded,
        };
    }

    /**
     * Encode value for storage.
     */
    public function encodeValue(mixed $value, string $type): array
    {
        $encoded = match ($type) {
            'json', 'array' => is_array($value) ? $value : json_decode($value, true),
            default => $value,
        };

        return ['value' => $encoded];
    }

    /**
     * Get a setting by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember(
            self::CACHE_PREFIX . $key,
            now()->addHour(),
            function () use ($key, $default) {
                $setting = self::where('key', $key)->first();

                if (! $setting) {
                    return $default;
                }

                return $setting->typed_value;
            }
        );
    }

    /**
     * Set a setting value.
     */
    public static function set(string $key, mixed $value, ?string $type = null, ?int $userId = null): self
    {
        // Determine type if not provided
        if ($type === null) {
            $type = match (gettype($value)) {
                'boolean' => 'boolean',
                'integer' => 'integer',
                'double' => 'float',
                'array' => 'array',
                default => 'string',
            };
        }

        // Determine group from key (e.g., "application.name" -> "application")
        $group = explode('.', $key)[0];

        $setting = self::updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'value' => ['value' => $value],
                'type' => $type,
                'last_modified_at' => now(),
                'modified_by' => $userId,
            ]
        );

        return $setting;
    }

    /**
     * Get all settings grouped by group.
     */
    public static function getAllGrouped(): array
    {
        return Cache::remember(
            self::CACHE_PREFIX . 'all',
            now()->addHour(),
            function (): array {
                $settings = self::where('is_public', true)
                    ->orderBy('group')
                    ->orderBy('key')
                    ->get();

                return $settings->groupBy('group')->map(function ($group) {
                    return $group->mapWithKeys(function ($setting) {
                        // Extract the short key (e.g., "application.name" -> "name")
                        $parts = explode('.', $setting->key);
                        $key = end($parts);

                        return [$key => $setting->typed_value];
                    })->toArray();
                })->toArray();
            }
        );
    }

    /**
     * Get settings by group.
     */
    public static function getByGroup(string $group): array
    {
        return Cache::remember(
            self::CACHE_PREFIX . 'group.' . $group,
            now()->addHour(),
            function () use ($group): array {
                $settings = self::where('group', $group)
                    ->where('is_public', true)
                    ->get();

                return $settings->mapWithKeys(function ($setting) {
                    // Extract the short key (e.g., "application.name" -> "name")
                    $parts = explode('.', $setting->key);
                    $key = end($parts);

                    return [$key => $setting->typed_value];
                })->toArray();
            }
        );
    }

    /**
     * Clear all settings cache.
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_PREFIX . 'all');
        Cache::forget(self::CACHE_PREFIX . 'group.application');
        Cache::forget(self::CACHE_PREFIX . 'group.database');
        Cache::forget(self::CACHE_PREFIX . 'group.cache');
        Cache::forget(self::CACHE_PREFIX . 'group.queue');
        Cache::forget(self::CACHE_PREFIX . 'group.mail');
        Cache::forget(self::CACHE_PREFIX . 'group.features');
    }
}
