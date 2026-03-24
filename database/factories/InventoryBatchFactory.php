<?php

namespace Database\Factories;

use App\Models\InventoryBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryBatch>
 */
class InventoryBatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'product_id' => null,
            'warehouse_id' => null,
            'supplier_id' => null,
            'batch_number' => 'BATCH-' . strtoupper(uniqid()),
            'lot_number' => null,
            'received_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'expiry_date' => null,
            'unit_cost' => fake()->randomFloat(4, 1, 100),
            'initial_quantity' => fake()->numberBetween(10, 500),
            'remaining_quantity' => fake()->numberBetween(10, 500),
            'status' => 'active',
            'notes' => null,
            'metadata' => null,
        ];
    }

    public function forTenant(int $tenantId): static
    {
        return $this->state(fn(array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }

    public function forProduct(int $productId): static
    {
        return $this->state(fn(array $attributes) => [
            'product_id' => $productId,
        ]);
    }

    public function forWarehouse(int $warehouseId): static
    {
        return $this->state(fn(array $attributes) => [
            'warehouse_id' => $warehouseId,
        ]);
    }

    public function withExpiry(int $daysFromNow): static
    {
        return $this->state(fn(array $attributes) => [
            'expiry_date' => now()->addDays($daysFromNow),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn(array $attributes) => [
            'expiry_date' => now()->subDays(fake()->numberBetween(1, 30)),
            'status' => 'active',
            'remaining_quantity' => fake()->numberBetween(1, 100),
        ]);
    }

    public function consumed(): static
    {
        return $this->state(fn(array $attributes) => [
            'remaining_quantity' => 0,
            'status' => 'consumed',
        ]);
    }

    public function withLotNumber(string $lotNumber): static
    {
        return $this->state(fn(array $attributes) => [
            'lot_number' => $lotNumber,
        ]);
    }

    public function withBatchNumber(string $batchNumber): static
    {
        return $this->state(fn(array $attributes) => [
            'batch_number' => $batchNumber,
        ]);
    }

    public function withCost(float $unitCost): static
    {
        return $this->state(fn(array $attributes) => [
            'unit_cost' => $unitCost,
        ]);
    }

    public function withQuantity(int $quantity): static
    {
        return $this->state(fn(array $attributes) => [
            'initial_quantity' => $quantity,
            'remaining_quantity' => $quantity,
        ]);
    }
}
