<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(),
            'company_name' => fake()->company(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country' => fake()->country(),
            'postal_code' => fake()->postcode(),
            'timezone' => fake()->randomElement(['UTC', 'America/New_York', 'America/Chicago', 'America/Los_Angeles']),
            'currency' => fake()->randomElement(['USD', 'EUR', 'GBP']),
            'status' => 'active',
            'settings' => ['theme' => 'light', 'notifications' => true],
            'trial_ends_at' => fake()->optional(0.8)->dateTimeBetween('+1 week', '+4 weeks'),
            'subscription_ends_at' => fake()->optional(0.7)->dateTimeBetween('+1 month', '+1 year'),
        ];
    }

    /**
     * Indicate that the tenant is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the tenant is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
        ]);
    }

    /**
     * Indicate that the tenant is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Indicate that the tenant is on trial.
     */
    public function onTrial(): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_ends_at' => now()->addWeeks(2),
            'subscription_ends_at' => null,
        ]);
    }

    /**
     * Indicate that the tenant has a subscription.
     */
    public function withSubscription(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_ends_at' => now()->addMonths(6),
        ]);
    }
}
