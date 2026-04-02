<?php

namespace Database\Factories;

use App\Models\ScheduledReport;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledReport>
 */
class ScheduledReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['sales', 'inventory', 'customer', 'custom'];
        $frequencies = ['daily', 'weekly', 'monthly'];
        $formats = ['csv', 'pdf', 'xlsx'];
        $type = fake()->randomElement($types);

        return [
            'tenant_id' => Tenant::factory(),
            'template_id' => null,
            'created_by' => User::factory(),
            'updated_by' => null,
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'type' => $type,
            'filters' => $this->generateFilters($type),
            'schedule_frequency' => fake()->randomElement($frequencies),
            'schedule_day' => fake()->numberBetween(1, 7),
            'schedule_time' => sprintf('%02d:00:00', fake()->numberBetween(6, 18)),
            'recipients' => [fake()->safeEmail()],
            'export_format' => fake()->randomElement($formats),
            'is_active' => true,
            'last_run_at' => null,
            'next_run_at' => null,
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
                'status' => ['confirmed', 'fulfilled'],
            ],
            'inventory' => [
                'warehouse_id' => null,
                'low_stock_only' => false,
            ],
            'customer' => [
                'active_only' => false,
                'min_orders' => 0,
            ],
            default => [],
        };
    }

    /**
     * Indicate that the report is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the report is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the report runs daily.
     */
    public function daily(): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_frequency' => 'daily',
            'schedule_day' => null,
        ]);
    }

    /**
     * Indicate that the report runs weekly.
     */
    public function weekly(int $dayOfWeek = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_frequency' => 'weekly',
            'schedule_day' => $dayOfWeek,
        ]);
    }

    /**
     * Indicate that the report runs monthly.
     */
    public function monthly(int $dayOfMonth = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'schedule_frequency' => 'monthly',
            'schedule_day' => $dayOfMonth,
        ]);
    }

    /**
     * Indicate that the report has been executed.
     */
    public function executed(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_run_at' => now()->subDay(),
            'next_run_at' => now()->addDay(),
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
