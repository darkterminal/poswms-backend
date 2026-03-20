<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
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
            'user_id' => User::factory(),
            'event_type' => fake()->randomElement(['created', 'updated', 'deleted', 'restored', 'logged_in', 'logged_out']),
            'auditable_type' => fake()->randomElement(['App\Models\Product', 'App\Models\Order', 'App\Models\Customer']),
            'auditable_id' => fake()->numberBetween(1, 1000),
            'url' => fake()->optional()->url(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'old_values' => fake()->optional()->randomElement([null, ['name' => 'Old Name', 'price' => 100]]),
            'new_values' => fake()->randomElement([['name' => 'New Name', 'price' => 150]]),
            'metadata' => fake()->optional()->randomElement([null, ['reason' => 'Bulk update', 'source' => 'API']]),
        ];
    }

    /**
     * Indicate that the audit log is for a create event.
     */
    public function created(): static
    {
        return $this->state(fn(array $attributes) => [
            'event_type' => 'created',
            'old_values' => null,
        ]);
    }

    /**
     * Indicate that the audit log is for an update event.
     */
    public function updated(): static
    {
        return $this->state(fn(array $attributes) => [
            'event_type' => 'updated',
        ]);
    }

    /**
     * Indicate that the audit log is for a delete event.
     */
    public function deleted(): static
    {
        return $this->state(fn(array $attributes) => [
            'event_type' => 'deleted',
            'new_values' => null,
        ]);
    }

    /**
     * Indicate that the audit log is for a login event.
     */
    public function login(): static
    {
        return $this->state(fn(array $attributes) => [
            'event_type' => 'logged_in',
            'auditable_type' => 'App\Models\User',
        ]);
    }

    /**
     * Indicate that the audit log is for a logout event.
     */
    public function logout(): static
    {
        return $this->state(fn(array $attributes) => [
            'event_type' => 'logged_out',
            'auditable_type' => 'App\Models\User',
        ]);
    }
}
