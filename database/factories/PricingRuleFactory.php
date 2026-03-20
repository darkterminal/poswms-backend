<?php

namespace Database\Factories;

use App\Models\PricingRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingRule>
 */
class PricingRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'pricing_tier_id' => null,
            'product_id' => null,
            'category_id' => null,
            'type' => 'percentage',
            'operation' => 'subtract',
            'value' => fake()->randomFloat(2, 5, 50),
            'min_quantity' => 1,
            'max_quantity' => null,
            'starts_at' => null,
            'ends_at' => null,
            'active' => true,
        ];
    }

    public function forTenant($tenantId): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }

    public function forPricingTier($tierId): static
    {
        return $this->state(fn (array $attributes) => [
            'pricing_tier_id' => $tierId,
        ]);
    }

    public function forProduct($productId): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $productId,
        ]);
    }

    public function percentageDiscount(float $value): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'percentage',
            'operation' => 'subtract',
            'value' => $value,
        ]);
    }

    public function fixedDiscount(float $value): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'fixed',
            'operation' => 'subtract',
            'value' => $value,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
