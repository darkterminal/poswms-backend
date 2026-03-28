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
        $indonesianCities = [
            ['city' => 'Jakarta', 'state' => 'DKI Jakarta', 'postal_code' => '10110'],
            ['city' => 'Bandung', 'state' => 'Jawa Barat', 'postal_code' => '40111'],
            ['city' => 'Surabaya', 'state' => 'Jawa Timur', 'postal_code' => '60111'],
            ['city' => 'Yogyakarta', 'state' => 'DI Yogyakarta', 'postal_code' => '55111'],
            ['city' => 'Semarang', 'state' => 'Jawa Tengah', 'postal_code' => '50111'],
            ['city' => 'Medan', 'state' => 'Sumatera Utara', 'postal_code' => '20111'],
            ['city' => 'Makassar', 'state' => 'Sulawesi Selatan', 'postal_code' => '90111'],
            ['city' => 'Denpasar', 'state' => 'Bali', 'postal_code' => '80111'],
        ];

        $location = fake()->randomElement($indonesianCities);

        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(),
            'company_name' => fake()->company(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => '+62-' . fake()->numerify('###-####'),
            'address' => fake()->address(),
            'city' => $location['city'],
            'state' => $location['state'],
            'country' => 'Indonesia',
            'postal_code' => $location['postal_code'],
            'timezone' => fake()->randomElement(['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura']),
            'currency' => 'IDR',
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
        return $this->state(fn(array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the tenant is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'suspended',
        ]);
    }

    /**
     * Indicate that the tenant is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Indicate that the tenant is on trial.
     */
    public function onTrial(): static
    {
        return $this->state(fn(array $attributes) => [
            'trial_ends_at' => now()->addWeeks(2),
            'subscription_ends_at' => null,
        ]);
    }

    /**
     * Indicate that the tenant has a subscription.
     */
    public function withSubscription(): static
    {
        return $this->state(fn(array $attributes) => [
            'subscription_ends_at' => now()->addMonths(6),
        ]);
    }
}
