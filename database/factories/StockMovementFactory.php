<?php

namespace Database\Factories;

use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'product_id' => null,
            'inventory_id' => null,
            'store_id' => null,
            'warehouse_id' => null,
            'order_id' => null,
            'user_id' => null,
            'type' => fake()->randomElement(['in', 'out', 'transfer', 'adjustment']),
            'quantity' => fake()->numberBetween(-100, 100),
            'quantity_before' => fake()->numberBetween(0, 1000),
            'quantity_after' => fake()->numberBetween(0, 1000),
            'reason' => null,
            'reference' => null,
        ];
    }

    public function forTenant($tenantId): static
    {
        return $this->state(fn(array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }

    public function stockIn(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'in',
        ]);
    }

    public function stockOut(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'out',
        ]);
    }
}
