<?php

namespace Database\Factories;

use App\Models\Webhook;
use App\Models\WebhookDeliveryAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDeliveryAttempt>
 */
class WebhookDeliveryAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'webhook_id' => Webhook::factory(),
            'event_type' => fake()->randomElement(['order.created', 'order.updated', 'order.deleted', 'product.created', 'product.updated']),
            'url' => fake()->url(),
            'attempt_number' => fake()->numberBetween(1, 3),
            'response_status' => fake()->randomElement([200, 201, 400, 404, 500]),
            'request_body' => json_encode(['test' => 'data']),
            'response_body' => json_encode(['status' => 'ok']),
            'response_time_ms' => fake()->randomFloat(2, 10, 1000),
            'success' => fake()->boolean(70),
            'next_retry_at' => fake()->optional()->dateTime(),
        ];
    }

    /**
     * Indicate that the delivery attempt was successful.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'success' => true,
            'response_status' => fake()->randomElement([200, 201]),
            'error_message' => null,
            'next_retry_at' => null,
        ]);
    }

    /**
     * Indicate that the delivery attempt failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'success' => false,
            'response_status' => fake()->randomElement([400, 404, 500, 502, 503]),
            'error_message' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the delivery attempt needs retry.
     */
    public function needsRetry(): static
    {
        return $this->state(fn (array $attributes) => [
            'success' => false,
            'next_retry_at' => now()->subMinute(),
        ]);
    }
}
