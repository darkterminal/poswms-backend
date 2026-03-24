<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductPriceLevel;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPriceLevel>
 */
class ProductPriceLevelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'level_name' => fake()->randomElement(['piece', 'pack', 'carton', 'box', 'case']),
            'level_order' => fake()->numberBetween(1, 5),
            'unit_size' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 100, 100000),
            'cost' => fake()->randomFloat(2, 50, 50000),
            'barcode' => fake()->ean13(),
            'active' => true,
        ];
    }

    /**
     * Set the tenant ID.
     */
    public function forTenant($tenantId): static
    {
        return $this->state(fn(array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Set the product ID.
     */
    public function forProduct($productId): static
    {
        return $this->state(fn(array $attributes) => [
            'product_id' => $productId,
        ]);
    }

    /**
     * Indicate that the price level is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Create a base unit price level (level_order = 1, unit_size = 1).
     */
    public function baseUnit(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'level_order' => 1,
                'unit_size' => 1,
                'level_name' => 'piece',
            ];
        });
    }

    /**
     * Create a pack price level.
     */
    public function pack(int $size = 10): static
    {
        return $this->state(function (array $attributes) use ($size) {
            return [
                'level_order' => 2,
                'unit_size' => $size,
                'level_name' => 'pack',
            ];
        });
    }

    /**
     * Create a carton price level.
     */
    public function carton(int $size = 100): static
    {
        return $this->state(function (array $attributes) use ($size) {
            return [
                'level_order' => 3,
                'unit_size' => $size,
                'level_name' => 'carton',
            ];
        });
    }
}
