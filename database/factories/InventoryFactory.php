<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'store_id' => null,
            'warehouse_id' => Warehouse::factory(),
            'quantity' => fake()->numberBetween(10, 1000),
            'reserved' => 0,
            'available' => fake()->numberBetween(10, 1000),
            'cost' => fake()->randomFloat(2, 5, 500),
            'location' => fake()->randomElement(['A1', 'B2', 'C3', 'D4']),
            'notes' => null,
        ];
    }

    public function forTenant($tenantId): static
    {
        return $this->state(fn(array $attributes) => [
            'tenant_id' => $tenantId,
            'product_id' => Product::factory()->forTenant($tenantId),
            'warehouse_id' => Warehouse::factory()->forTenant($tenantId),
        ]);
    }

    public function withProduct($productId): static
    {
        return $this->state(fn(array $attributes) => [
            'product_id' => $productId,
        ]);
    }

    public function withWarehouse($warehouseId): static
    {
        return $this->state(fn(array $attributes) => [
            'warehouse_id' => $warehouseId,
        ]);
    }

    public function lowStock(): static
    {
        return $this->state(fn(array $attributes) => [
            'quantity' => fake()->numberBetween(0, 10),
            'available' => fake()->numberBetween(0, 10),
        ]);
    }
}
