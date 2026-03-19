<?php

namespace Database\Factories;

use App\Models\PricingTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingTier>
 */
class PricingTierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'name' => fake()->unique()->word().' Tier',
            'slug' => fake()->unique()->word(),
            'description' => fake()->sentence(),
            'priority' => 0,
            'active' => true,
        ];
    }

    public function forTenant($tenantId): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }

    public function bronze(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Bronze',
            'slug' => 'bronze',
            'priority' => 1,
        ]);
    }

    public function silver(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Silver',
            'slug' => 'silver',
            'priority' => 2,
        ]);
    }

    public function gold(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Gold',
            'slug' => 'gold',
            'priority' => 3,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
