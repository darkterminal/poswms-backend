<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Assert that the response has validation errors for the given keys.
     * Adapts Laravel's default assertJsonValidationErrors to our custom API error format.
     */
    protected function assertApiValidationErrors(TestResponse $response, string|array $keys): TestResponse
    {
        $keys = is_array($keys) ? $keys : [$keys];

        foreach ($keys as $key) {
            $response->assertJsonPath('success', false)
                ->assertJsonPath('error.code', 'validation_error');

            // Assert the field exists in the details
            $details = $response->json('error.details') ?? [];
            $this->assertArrayHasKey($key, $details, "Validation error for '{$key}' not found in response");
        }

        return $response;
    }
}
