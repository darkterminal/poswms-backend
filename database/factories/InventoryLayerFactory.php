<?php

namespace Database\Factories;

use App\Models\InventoryLayer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryLayer>
 */
class InventoryLayerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'product_id' => null,
            'inventory_id' => null,
            'batch_id' => null,
            'warehouse_id' => null,
            'store_id' => null,
            'quantity' => fake()->numberBetween(10, 500),
            'reserved' => 0,
            'available' => fake()->numberBetween(10, 500),
            'unit_cost' => fake()->randomFloat(4, 1, 100),
            'total_cost' => null,
            'layer_order' => 1,
            'is_fifo_layer' => true,
        ];
    }

    public function forTenant(int $tenantId): static
    {
        return $this->state(fn(array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }

    public function forInventory(int $inventoryId): static
    {
        return $this->state(fn(array $attributes) => [
            'inventory_id' => $inventoryId,
            'layer_order' => InventoryLayer::getNextLayerOrder($inventoryId),
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

    public function forBatch(int $batchId): static
    {
        return $this->state(fn(array $attributes) => [
            'batch_id' => $batchId,
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
            'quantity' => $quantity,
            'available' => $quantity,
        ]);
    }

    public function withReserved(int $reserved): static
    {
        return $this->state(fn(array $attributes) => [
            'reserved' => $reserved,
        ]);
    }

    public function oldest(): static
    {
        return $this->state(fn(array $attributes) => [
            'layer_order' => 1,
        ]);
    }

    public function newest(): static
    {
        return $this->state(fn(array $attributes) => [
            'layer_order' => 999,
        ]);
    }

    public function fifo(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_fifo_layer' => true,
        ]);
    }

    public function nonFifo(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_fifo_layer' => false,
        ]);
    }

    /**
     * Create a sequence of layers with different costs and dates.
     */
    public static function createSequence(
        int $inventoryId,
        int $productId,
        int $warehouseId,
        int $tenantId,
        int $count = 3
    ): \Illuminate\Support\Collection {
        $layers = collect();
        $baseCost = 10.0;
        $baseQty = 100;

        for ($i = 0; $i < $count; $i++) {
            $layer = self::create([
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'inventory_id' => $inventoryId,
                'warehouse_id' => $warehouseId,
                'quantity' => $baseQty - ($i * 10),
                'unit_cost' => $baseCost + ($i * 2),
                'layer_order' => $i + 1,
                'is_fifo_layer' => true,
            ]);
            $layers->push($layer);
        }

        return $layers;
    }
}
