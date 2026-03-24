<?php

namespace Tests\Feature\Security;

use App\Services\WebhookService;
use Tests\TestCase;

class WebhookTimestampValidationTest extends TestCase
{
    private WebhookService $webhookService;
    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->webhookService = app(WebhookService::class);
        $this->secret = 'test-secret-key-123';
    }

    public function test_valid_timestamp_within_tolerance(): void
    {
        $timestamp = now()->toIso8601String();
        $payload = [
            'event' => 'test.event',
            'timestamp' => $timestamp,
            'data' => ['test' => 'value'],
        ];

        $signature = $this->webhookService->generateSignature($payload, $this->secret);

        $result = $this->webhookService->verifySignature($payload, $signature, $this->secret, 300, true);

        $this->assertTrue($result);
    }

    public function test_old_timestamp_is_rejected(): void
    {
        $timestamp = now()->subMinutes(10)->toIso8601String();
        $payload = [
            'event' => 'test.event',
            'timestamp' => $timestamp,
            'data' => ['test' => 'value'],
        ];

        $signature = $this->webhookService->generateSignature($payload, $this->secret);

        $result = $this->webhookService->verifySignature($payload, $signature, $this->secret, 300, true);

        $this->assertFalse($result);
    }

    public function test_missing_timestamp_is_rejected(): void
    {
        $payload = [
            'event' => 'test.event',
            'data' => ['test' => 'value'],
            // No timestamp
        ];

        $signature = $this->webhookService->generateSignature($payload, $this->secret);

        $result = $this->webhookService->verifySignature($payload, $signature, $this->secret, 300, true);

        $this->assertFalse($result);
    }

    public function test_invalid_timestamp_format_is_rejected(): void
    {
        $payload = [
            'event' => 'test.event',
            'timestamp' => 'invalid-timestamp-format',
            'data' => ['test' => 'value'],
        ];

        $signature = $this->webhookService->generateSignature($payload, $this->secret);

        $result = $this->webhookService->verifySignature($payload, $signature, $this->secret, 300, true);

        $this->assertFalse($result);
    }

    public function test_future_timestamp_is_rejected(): void
    {
        $timestamp = now()->addMinutes(10)->toIso8601String();
        $payload = [
            'event' => 'test.event',
            'timestamp' => $timestamp,
            'data' => ['test' => 'value'],
        ];

        $signature = $this->webhookService->generateSignature($payload, $this->secret);

        $result = $this->webhookService->verifySignature($payload, $signature, $this->secret, 300, true);

        $this->assertFalse($result);
    }

    public function test_timestamp_at_tolerance_boundary(): void
    {
        // Test at exactly 5 minutes (300 seconds)
        $timestamp = now()->subSeconds(299)->toIso8601String();
        $payload = [
            'event' => 'test.event',
            'timestamp' => $timestamp,
            'data' => ['test' => 'value'],
        ];

        $signature = $this->webhookService->generateSignature($payload, $this->secret);

        $result = $this->webhookService->verifySignature($payload, $signature, $this->secret, 300, true);

        $this->assertTrue($result);
    }

    public function test_timestamp_beyond_tolerance_boundary(): void
    {
        // Test at 5 minutes and 1 second (301 seconds)
        $timestamp = now()->subSeconds(301)->toIso8601String();
        $payload = [
            'event' => 'test.event',
            'timestamp' => $timestamp,
            'data' => ['test' => 'value'],
        ];

        $signature = $this->webhookService->generateSignature($payload, $this->secret);

        $result = $this->webhookService->verifySignature($payload, $signature, $this->secret, 300, true);

        $this->assertFalse($result);
    }

    public function test_custom_tolerance_is_respected(): void
    {
        $timestamp = now()->subSeconds(10)->toIso8601String();
        $payload = [
            'event' => 'test.event',
            'timestamp' => $timestamp,
            'data' => ['test' => 'value'],
        ];

        $signature = $this->webhookService->generateSignature($payload, $this->secret);

        // With 5 second tolerance, should fail
        $result = $this->webhookService->verifySignature($payload, $signature, $this->secret, 5, true);

        $this->assertFalse($result);

        // With 15 second tolerance, should pass
        $result = $this->webhookService->verifySignature($payload, $signature, $this->secret, 15, true);

        $this->assertTrue($result);
    }

    public function test_invalid_signature_is_rejected_regardless_of_timestamp(): void
    {
        $timestamp = now()->toIso8601String();
        $payload = [
            'event' => 'test.event',
            'timestamp' => $timestamp,
            'data' => ['test' => 'value'],
        ];

        $wrongSignature = 'wrong-signature';

        $result = $this->webhookService->verifySignature($payload, $wrongSignature, $this->secret, 300, true);

        $this->assertFalse($result);
    }

    public function test_backward_compatibility_without_timestamp(): void
    {
        // Test backward compatibility - no timestamp required by default
        $payload = [
            'event' => 'test.event',
            'data' => ['test' => 'value'],
        ];

        $signature = $this->webhookService->generateSignature($payload, $this->secret);

        // Should pass without timestamp (backward compatibility)
        $result = $this->webhookService->verifySignature($payload, $signature, $this->secret);

        $this->assertTrue($result);
    }
}
