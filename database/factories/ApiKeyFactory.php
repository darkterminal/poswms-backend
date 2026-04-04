<?php

namespace Database\Factories;

use App\Models\ApiKey;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApiKey>
 */
class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'name' => $this->faker->words(3, true),
            'key' => Str::random(48),
            'abilities' => ['read', 'write'],
            'last_used_at' => null,
            'expires_at' => null,
        ];
    }

    /**
     * Indicate that the API key expires in 30 days.
     */
    public function expiringIn30Days(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->addDays(30),
        ]);
    }

    /**
     * Indicate that the API key is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDays(1),
        ]);
    }

    /**
     * Indicate that the API key has been used.
     */
    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_used_at' => now()->subHour(),
        ]);
    }

    /**
     * Indicate that the API key has read-only abilities.
     */
    public function readOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'abilities' => ['read'],
        ]);
    }

    /**
     * Indicate that the API key has full access.
     */
    public function fullAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'abilities' => [],
        ]);
    }
}
