<?php

namespace Database\Factories;

use App\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Webhook>
 */
class WebhookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'name' => fake()->unique()->words(3, true),
            'url' => fake()->unique()->url(),
            'secret' => fake()->randomAscii(),
            'events' => ['order.created', 'order.updated'],
            'active' => true,
            'content_type' => 'json',
            'headers' => ['X-Custom-Header' => 'value'],
            'retry_count' => 3,
            'timeout' => 30,
        ];
    }

    /**
     * Indicate that the webhook is active.
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'active' => true,
        ]);
    }

    /**
     * Indicate that the webhook is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Indicate that the webhook has a secret.
     */
    public function withSecret(): static
    {
        return $this->state(fn(array $attributes) => [
            'secret' => 'test-secret-key-' . fake()->randomNumber(8),
        ]);
    }

    /**
     * Indicate that the webhook uses form-data content type.
     */
    public function formData(): static
    {
        return $this->state(fn(array $attributes) => [
            'content_type' => 'form-data',
        ]);
    }

    /**
     * Indicate that the webhook has custom retry settings.
     */
    public function withRetrySettings(int $count = 5, int $timeout = 60): static
    {
        return $this->state(fn(array $attributes) => [
            'retry_count' => $count,
            'timeout' => $timeout,
        ]);
    }
}
