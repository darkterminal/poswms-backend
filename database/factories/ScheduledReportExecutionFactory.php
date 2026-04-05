<?php

namespace Database\Factories;

use App\Models\ScheduledReport;
use App\Models\ScheduledReportExecution;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledReportExecution>
 */
class ScheduledReportExecutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scheduled_report_id' => ScheduledReport::factory(),
            'tenant_id' => Tenant::factory(),
            'executed_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'success' => fake()->boolean(90),
            'records_count' => fake()->numberBetween(10, 1000),
            'file_path' => null,
            'file_format' => fake()->randomElement(['csv', 'pdf', 'xlsx']),
            'file_size' => fake()->numberBetween(1024, 1048576),
            'error_message' => null,
            'recipients_notified' => [fake()->safeEmail()],
        ];
    }

    /**
     * Indicate that the execution was successful.
     */
    public function successful(): static
    {
        return $this->state(fn(array $attributes) => [
            'success' => true,
            'error_message' => null,
            'file_path' => 'scheduled_reports/' . fake()->uuid() . '.' . $attributes['file_format'],
        ]);
    }

    /**
     * Indicate that the execution failed.
     */
    public function failed(): static
    {
        return $this->state(fn(array $attributes) => [
            'success' => false,
            'error_message' => fake()->sentence(),
            'records_count' => null,
            'file_path' => null,
            'recipients_notified' => [],
        ]);
    }

    /**
     * Indicate that the execution is for a specific scheduled report.
     */
    public function forScheduledReport(int $scheduledReportId): static
    {
        return $this->state(fn(array $attributes) => [
            'scheduled_report_id' => $scheduledReportId,
        ]);
    }

    /**
     * Indicate that the execution is for a specific tenant.
     */
    public function forTenant(int $tenantId): static
    {
        return $this->state(fn(array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }
}
