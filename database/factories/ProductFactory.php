<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'category_id' => null,
            'name' => fake()->unique()->words(3, true),
            'sku' => 'SKU-' . strtoupper(fake()->unique()->bothify('???###')),
            'barcode' => fake()->unique()->ean13(),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 10, 1000),
            'cost' => fake()->randomFloat(2, 5, 500),
            'tax_rate' => 0,
            'unit' => 'piece',
            'min_stock' => 10,
            'max_stock' => null,
            'image' => null,
            'images' => null,
            'attributes' => null,
            'track_inventory' => true,
            'active' => true,
        ];
    }

    public function forTenant($tenantId): static
    {
        return $this->state(fn(array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }

    public function withCategory($categoryId): static
    {
        return $this->state(fn(array $attributes) => [
            'category_id' => $categoryId,
        ]);
    }

    public function lowStock(): static
    {
        return $this->state(fn(array $attributes) => [
            'min_stock' => 100,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'active' => false,
        ]);
    }
}
