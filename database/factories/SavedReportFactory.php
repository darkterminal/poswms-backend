<?php

namespace Database\Factories;

use App\Models\ReportTemplate;
use App\Models\SavedReport;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedReport>
 */
class SavedReportFactory extends Factory
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
        $tenantId = fake()->randomElement([null, Tenant::factory()]);

        return [
            'tenant_id' => $tenantId ?? Tenant::factory(),
            'template_id' => fake()->randomElement([null, ReportTemplate::factory()]),
            'created_by' => User::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'type' => $type,
            'filters' => $this->generateFilters($type),
            'data' => $this->generateSampleData($type),
            'file_path' => null,
            'file_format' => fake()->randomElement(['csv', 'pdf', 'xlsx']),
            'file_size' => fake()->numberBetween(1024, 1048576),
            'generated_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'expires_at' => fake()->optional(0.7)->dateTimeBetween('+1 month', '+6 months'),
        ];
    }

    /**
     * Generate sample filters based on report type.
     */
    private function generateFilters(string $type): array
    {
        return match ($type) {
            'sales' => [
                'date_range' => 'last_30_days',
                'start_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
                'end_date' => now()->format('Y-m-d'),
                'status' => ['confirmed', 'fulfilled'],
            ],
            'inventory' => [
                'warehouse_id' => null,
                'low_stock_only' => false,
                'include_zero_stock' => true,
            ],
            'customer' => [
                'active_only' => false,
                'min_orders' => 0,
                'date_from' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            ],
            default => [],
        };
    }

    /**
     * Generate sample report data based on type.
     */
    private function generateSampleData(string $type): ?array
    {
        return match ($type) {
            'sales' => [
                'summary' => [
                    'total_revenue' => fake()->randomFloat(2, 1000, 100000),
                    'total_orders' => fake()->numberBetween(10, 1000),
                    'avg_order_value' => fake()->randomFloat(2, 50, 500),
                ],
            ],
            'inventory' => [
                'summary' => [
                    'total_items' => fake()->numberBetween(100, 10000),
                    'total_value' => fake()->randomFloat(2, 5000, 500000),
                    'low_stock_count' => fake()->numberBetween(0, 50),
                ],
            ],
            'customer' => [
                'summary' => [
                    'total_customers' => fake()->numberBetween(50, 5000),
                    'active_customers' => fake()->numberBetween(10, 1000),
                ],
            ],
            default => null,
        };
    }

    /**
     * Indicate that the report has a file.
     */
    public function withFile(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_path' => 'saved_reports/' . fake()->uuid() . '.' . $attributes['file_format'],
            'file_size' => fake()->numberBetween(1024, 1048576),
        ]);
    }

    /**
     * Indicate that the report is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => fake()->dateTimeBetween('-1 month', '-1 day'),
        ]);
    }

    /**
     * Indicate that the report never expires.
     */
    public function neverExpires(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => null,
        ]);
    }

    /**
     * Indicate that the report is for a specific tenant.
     */
    public function forTenant(int $tenantId): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }
}
