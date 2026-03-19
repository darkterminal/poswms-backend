<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'name' => fake()->word(),
            'slug' => fake()->unique()->word(),
            'description' => fake()->sentence(),
            'permissions' => [],
            'is_system' => false,
        ];
    }

    /**
     * Indicate that the role is a system role.
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    /**
     * Indicate that the role is admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Admin',
            'slug' => 'admin',
            'is_system' => true,
            'permissions' => ['*'],
        ]);
    }

    /**
     * Indicate that the role is a manager.
     */
    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Manager',
            'slug' => 'manager',
            'permissions' => ['products.view', 'products.create', 'products.edit', 'orders.view', 'orders.manage'],
        ]);
    }

    /**
     * Indicate that the role is a user.
     */
    public function user(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'User',
            'slug' => 'user',
            'permissions' => ['products.view', 'orders.view'],
        ]);
    }

    /**
     * Indicate that the role belongs to a tenant.
     */
    public function forTenant($tenantId): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenantId,
        ]);
    }
}
