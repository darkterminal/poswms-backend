<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'order_number' => 'ORD-' . strtoupper(fake()->unique()->bothify('???###')),
            'customer_id' => null,
            'store_id' => null,
            'warehouse_id' => null,
            'user_id' => null,
            'status' => 'pending',
            'type' => 'sale',
            'subtotal' => fake()->randomFloat(2, 50, 5000),
            'tax' => fake()->randomFloat(2, 0, 500),
            'discount' => 0,
            'shipping' => fake()->randomFloat(2, 0, 100),
            'payment_status' => 'pending',
            'payment_method' => null,
            'notes' => null,
            'shipping_address' => fake()->address(),
            'shipping_city' => fake()->city(),
            'shipping_state' => fake()->state(),
            'shipping_country' => fake()->country(),
            'shipping_postal_code' => fake()->postcode(),
            'confirmed_at' => null,
            'fulfilled_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function forTenant($tenantId): static
    {
        return $this->state(fn(array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    public function fulfilled(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'fulfilled',
            'fulfilled_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }
}
