<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'company' => fake()->company(),
            'tax_id' => fake()->unique()->numerify('###-##-####'),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country' => fake()->country(),
            'postal_code' => fake()->postcode(),
            'pricing_tier_id' => null,
            'credit_limit' => 0,
            'balance' => 0,
            'settings' => null,
            'active' => true,
        ];
    }

    public function forTenant($tenantId): static
    {
        return $this->state(fn(array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }

    public function withPricingTier($tierId): static
    {
        return $this->state(fn(array $attributes) => [
            'pricing_tier_id' => $tierId,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'active' => false,
        ]);
    }
}
