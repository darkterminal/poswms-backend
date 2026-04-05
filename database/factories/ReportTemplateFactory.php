<?php

namespace Database\Factories;

use App\Models\ReportTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportTemplate>
 */
class ReportTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['sales', 'inventory', 'customer', 'custom'];
        $type = fake()->randomElement($types);

        return [
            'tenant_id' => fake()->randomElement([null, Tenant::factory()]),
            'created_by' => User::factory(),
            'updated_by' => null,
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'type' => $type,
            'config' => $this->generateConfig($type),
            'is_global' => fake()->boolean(20),
            'is_active' => true,
        ];
    }

    /**
     * Generate a sample config based on report type.
     */
    private function generateConfig(string $type): array
    {
        return match ($type) {
            'sales' => [
                'filters' => [
                    'date_range' => 'last_30_days',
                    'status' => ['confirmed', 'fulfilled'],
                    'store_id' => null,
                ],
                'columns' => ['date', 'order_count', 'revenue', 'avg_order_value'],
                'grouping' => 'daily',
                'sorting' => ['field' => 'date', 'direction' => 'desc'],
            ],
            'inventory' => [
                'filters' => [
                    'warehouse_id' => null,
                    'low_stock_only' => false,
                ],
                'columns' => ['product', 'warehouse', 'quantity', 'available', 'reserved'],
                'grouping' => 'warehouse',
                'sorting' => ['field' => 'quantity', 'direction' => 'asc'],
            ],
            'customer' => [
                'filters' => [
                    'active_only' => false,
                    'min_orders' => 0,
                ],
                'columns' => ['customer', 'email', 'total_orders', 'total_revenue'],
                'grouping' => null,
                'sorting' => ['field' => 'total_revenue', 'direction' => 'desc'],
            ],
            default => [
                'filters' => [],
                'columns' => [],
                'grouping' => null,
                'sorting' => ['field' => 'created_at', 'direction' => 'desc'],
            ],
        };
    }

    /**
     * Indicate that the template is global.
     */
    public function global(): static
    {
        return $this->state(fn(array $attributes) => [
            'tenant_id' => null,
            'is_global' => true,
        ]);
    }

    /**
     * Indicate that the template is for a specific tenant.
     */
    public function forTenant(?int $tenantId = null): static
    {
        return $this->state(fn(array $attributes) => [
            'tenant_id' => $tenantId ?? Tenant::factory(),
            'is_global' => false,
        ]);
    }

    /**
     * Indicate that the template is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }
}
