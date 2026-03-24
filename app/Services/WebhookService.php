<?php

namespace App\Services;

use App\Models\Webhook;
use App\Models\WebhookDeliveryAttempt;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Trigger webhooks for a specific event.
     *
     * @param  string  $eventType  The type of event (e.g., 'order.created', 'product.updated')
     * @param  array<string, mixed>  $payload  The data to send with the webhook
     * @param  ?int  $tenantId  The tenant ID (optional, will filter webhooks by tenant if provided)
     * @return array<string, mixed> Results of the webhook dispatch
     */
    public function trigger(string $eventType, array $payload, ?int $tenantId = null): array
    {
        $webhooks = Webhook::query()
            ->when($tenantId, fn($query) => $query->forTenant($tenantId))
            ->active()
            ->forEvent($eventType)
            ->get();

        if ($webhooks->isEmpty()) {
            return [
                'triggered' => false,
                'webhooks_found' => 0,
                'event_type' => $eventType,
            ];
        }

        $results = [];
        foreach ($webhooks as $webhook) {
            $results[] = $this->dispatchToWebhook($webhook, $eventType, $payload);
        }

        return [
            'triggered' => true,
            'webhooks_found' => $webhooks->count(),
            'event_type' => $eventType,
            'results' => $results,
        ];
    }

    /**
     * Dispatch a webhook to a specific webhook endpoint.
     *
     * @param  Webhook  $webhook  The webhook to dispatch to
     * @param  string  $eventType  The event type
     * @param  array<string, mixed>  $payload  The payload to send
     * @return array<string, mixed> Result of the dispatch
     */
    public function dispatchToWebhook(Webhook $webhook, string $eventType, array $payload): array
    {
        $attemptNumber = 1;
        $startTime = microtime(true);

        try {
            $response = Http::withHeaders($webhook->getHeaders())
                ->withToken($webhook->secret ?? '')
                ->timeout($webhook->timeout)
                ->post($webhook->url, $this->preparePayload($webhook, $eventType, $payload));

            $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            $success = $response->successful();

            WebhookDeliveryAttempt::create([
                'webhook_id' => $webhook->id,
                'event_type' => $eventType,
                'url' => $webhook->url,
                'attempt_number' => $attemptNumber,
                'response_status' => $response->status(),
                'request_body' => json_encode($this->preparePayload($webhook, $eventType, $payload)),
                'response_body' => $response->body(),
                'response_time_ms' => $responseTime,
                'success' => $success,
            ]);

            if ($success) {
                $webhook->update(['last_triggered_at' => now()]);

                return [
                    'webhook_id' => $webhook->id,
                    'webhook_name' => $webhook->name,
                    'success' => true,
                    'status' => $response->status(),
                    'attempt' => $attemptNumber,
                ];
            }

            // Schedule retry if failed
            $nextRetryAt = $this->calculateNextRetry($attemptNumber);
            WebhookDeliveryAttempt::latest()->first()?->update(['next_retry_at' => $nextRetryAt]);

            return [
                'webhook_id' => $webhook->id,
                'webhook_name' => $webhook->name,
                'success' => false,
                'status' => $response->status(),
                'attempt' => $attemptNumber,
                'next_retry_at' => $nextRetryAt,
            ];
        } catch (ConnectionException $e) {
            return $this->handleDeliveryError($webhook, $eventType, $payload, $e->getMessage(), $attemptNumber);
        } catch (RequestException $e) {
            return $this->handleDeliveryError($webhook, $eventType, $payload, $e->getMessage(), $attemptNumber);
        } catch (\Exception $e) {
            return $this->handleDeliveryError($webhook, $eventType, $payload, $e->getMessage(), $attemptNumber);
        }
    }

    /**
     * Retry failed webhook deliveries.
     */
    public function retryFailedDeliveries(): int
    {
        $attempts = WebhookDeliveryAttempt::query()
            ->needsRetry()
            ->with('webhook')
            ->get();

        $retried = 0;

        foreach ($attempts as $attempt) {
            if (! $attempt->webhook || ! $attempt->webhook->active) {
                continue;
            }

            $webhook = $attempt->webhook;
            $attemptNumber = $attempt->attempt_number + 1;

            // Don't retry if max retries exceeded
            if ($attemptNumber > $webhook->retry_count) {
                continue;
            }

            $startTime = microtime(true);
            $payload = json_decode($attempt->request_body, true) ?? [];

            try {
                $response = Http::withHeaders($webhook->getHeaders())
                    ->withToken($webhook->secret ?? '')
                    ->timeout($webhook->timeout)
                    ->post($webhook->url, $payload);

                $responseTime = (microtime(true) - $startTime) * 1000;
                $success = $response->successful();

                WebhookDeliveryAttempt::create([
                    'webhook_id' => $webhook->id,
                    'event_type' => $attempt->event_type,
                    'url' => $webhook->url,
                    'attempt_number' => $attemptNumber,
                    'response_status' => $response->status(),
                    'request_body' => json_encode($payload),
                    'response_body' => $response->body(),
                    'response_time_ms' => $responseTime,
                    'success' => $success,
                ]);

                if ($success) {
                    $webhook->update(['last_triggered_at' => now()]);
                } else {
                    $nextRetryAt = $this->calculateNextRetry($attemptNumber);
                    WebhookDeliveryAttempt::latest()->first()?->update(['next_retry_at' => $nextRetryAt]);
                }

                $retried++;
            } catch (\Exception $e) {
                $nextRetryAt = $this->calculateNextRetry($attemptNumber);
                WebhookDeliveryAttempt::create([
                    'webhook_id' => $webhook->id,
                    'event_type' => $attempt->event_type,
                    'url' => $webhook->url,
                    'attempt_number' => $attemptNumber,
                    'error_message' => $e->getMessage(),
                    'next_retry_at' => $nextRetryAt,
                    'success' => false,
                ]);
                $retried++;
            }
        }

        return $retried;
    }

    /**
     * Generate a signature for webhook payload verification.
     *
     * @param  array<string, mixed>  $payload  The payload to sign
     * @param  string  $secret  The webhook secret
     * @param  bool  $includeTimestamp  Whether to include timestamp in signature (v2 format).
     *                                  Only applies if payload has 'timestamp' and 'data' keys but no 'event' key.
     */
    public function generateSignature(array $payload, string $secret, bool $includeTimestamp = false): string
    {
        // v2 format: include timestamp in signature calculation
        // v2 payloads have timestamp and data, but NOT event at root level
        $isV2Format = isset($payload['timestamp']) && isset($payload['data']) && ! isset($payload['event']);

        if ($includeTimestamp && $isV2Format) {
            // Signature covers both timestamp and data for v2
            $signaturePayload = $payload['timestamp'] . ':' . json_encode($payload['data'], JSON_UNESCAPED_SLASHES);

            return hash_hmac('sha256', $signaturePayload, $secret);
        }

        // v1 format (backward compatible): signature covers entire payload
        $payloadString = json_encode($payload, JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha256', $payloadString, $secret);
    }

    /**
     * Generate a v2 signature with timestamp for enhanced security.
     *
     * @param  array<string, mixed>  $data  The payload data (without timestamp)
     * @param  string  $secret  The webhook secret
     * @return array{signature: string, timestamp: string}
     */
    public function generateSignatureV2(array $data, string $secret): array
    {
        $timestamp = now()->toIso8601String();

        $payload = [
            'timestamp' => $timestamp,
            'data' => $data,
        ];

        $signature = $this->generateSignature($payload, $secret, true);

        return [
            'signature' => $signature,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * Verify a webhook signature with dual-mode support (v1 and v2).
     *
     * This method supports both:
     * - v1 (legacy): Simple HMAC signature without timestamp validation
     * - v2 (secure): HMAC signature with timestamp validation for replay protection
     *
     * @param  array<string, mixed>  $payload  The received payload
     * @param  string  $signature  The signature to verify
     * @param  string  $secret  The webhook secret
     * @param  int  $tolerance  Time tolerance in seconds (default: 5 minutes)
     * @param  bool  $requireTimestamp  Whether to require timestamp validation (default: false for backward compatibility)
     * @return bool True if signature is valid and timestamp is within tolerance (if required)
     */
    public function verifySignature(array $payload, string $signature, string $secret, int $tolerance = 300, bool $requireTimestamp = false): bool
    {
        // Detect signature version
        // v2 format: has timestamp and data, but NOT event at root level
        // v1 format: has event at root level (may or may not have timestamp)
        $isV2 = isset($payload['timestamp']) && isset($payload['data']) && ! isset($payload['event']);

        if ($isV2) {
            // v2 signature verification with timestamp validation
            return $this->verifySignatureV2($payload, $signature, $secret, $tolerance);
        }

        // v1 signature verification (backward compatible)
        $expectedSignature = $this->generateSignature($payload, $secret, false);

        if (! hash_equals($expectedSignature, $signature)) {
            logger()->warning('Webhook v1 signature verification failed', [
                'event' => $payload['event'] ?? 'unknown',
            ]);

            return false;
        }

        // If timestamp validation is required but payload has no timestamp, reject
        if ($requireTimestamp && ! isset($payload['timestamp'])) {
            logger()->warning('Webhook v1 payload missing timestamp (timestamp validation required)', [
                'event' => $payload['event'] ?? 'unknown',
            ]);

            return false;
        }

        // Validate timestamp if present in v1 format
        if (isset($payload['timestamp'])) {
            return $this->validateTimestamp($payload['timestamp'], $tolerance);
        }

        return true;
    }

    /**
     * Verify a v2 webhook signature with mandatory timestamp validation.
     *
     * @param  array<string, mixed>  $payload  The received payload (with timestamp and data)
     * @param  string  $signature  The signature to verify
     * @param  string  $secret  The webhook secret
     * @param  int  $tolerance  Time tolerance in seconds (default: 5 minutes)
     * @return bool True if signature is valid and timestamp is within tolerance
     */
    private function verifySignatureV2(array $payload, string $signature, string $secret, int $tolerance = 300): bool
    {
        // Validate timestamp first (prevent replay attacks)
        if (! $this->validateTimestamp($payload['timestamp'], $tolerance)) {
            return false;
        }

        // Verify signature (covers timestamp + data)
        $expectedSignature = $this->generateSignature($payload, $secret, true);

        if (! hash_equals($expectedSignature, $signature)) {
            logger()->warning('Webhook v2 signature verification failed', [
                'event' => $payload['event'] ?? 'unknown',
                'timestamp' => $payload['timestamp'] ?? 'missing',
            ]);

            return false;
        }

        return true;
    }

    /**
     * Validate a webhook timestamp to prevent replay attacks.
     *
     * @param  string  $timestamp  The timestamp to validate (ISO 8601 format)
     * @param  int  $tolerance  Time tolerance in seconds (default: 5 minutes)
     * @return bool True if timestamp is within tolerance
     */
    private function validateTimestamp(string $timestamp, int $tolerance = 300): bool
    {
        try {
            $parsedTimestamp = strtotime($timestamp);

            if ($parsedTimestamp === false) {
                logger()->warning('Webhook payload has invalid timestamp format', [
                    'timestamp' => $timestamp,
                ]);

                return false;
            }

            $currentTime = time();
            $timeDifference = abs($currentTime - $parsedTimestamp);

            if ($timeDifference > $tolerance) {
                logger()->warning('Webhook replay attack detected - timestamp outside tolerance', [
                    'timestamp' => $timestamp,
                    'time_difference' => $timeDifference,
                    'tolerance' => $tolerance,
                ]);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            logger()->error('Webhook timestamp validation error', [
                'error' => $e->getMessage(),
                'timestamp' => $timestamp,
            ]);

            return false;
        }
    }

    /**
     * Prepare the payload for the webhook.
     *
     * @param  Webhook  $webhook  The webhook
     * @param  string  $eventType  The event type
     * @param  array<string, mixed>  $payload  The original payload
     * @return array<string, mixed>|string The prepared payload
     */
    private function preparePayload(Webhook $webhook, string $eventType, array $payload): array|string
    {
        // v2 payload format with timestamp and signature
        $webhookPayload = [
            'event' => $eventType,
            'timestamp' => now()->toIso8601String(),
            'data' => $payload,
        ];

        // Add v2 signature if secret is set
        if ($webhook->secret) {
            $webhookPayload['signature'] = $this->generateSignature($webhookPayload, $webhook->secret, true);
        }

        if ($webhook->content_type === 'form-data') {
            return [
                'event' => $eventType,
                'timestamp' => now()->toIso8601String(),
                'data' => json_encode($payload),
            ];
        }

        return $webhookPayload;
    }

    /**
     * Handle delivery errors.
     *
     * @param  Webhook  $webhook  The webhook
     * @param  string  $eventType  The event type
     * @param  array<string, mixed>  $payload  The payload
     * @param  string  $errorMessage  The error message
     * @param  int  $attemptNumber  The attempt number
     * @return array<string, mixed> Error result
     */
    private function handleDeliveryError(
        Webhook $webhook,
        string $eventType,
        array $payload,
        string $errorMessage,
        int $attemptNumber
    ): array {
        $nextRetryAt = $this->calculateNextRetry($attemptNumber);

        WebhookDeliveryAttempt::create([
            'webhook_id' => $webhook->id,
            'event_type' => $eventType,
            'url' => $webhook->url,
            'attempt_number' => $attemptNumber,
            'error_message' => $errorMessage,
            'request_body' => json_encode($this->preparePayload($webhook, $eventType, $payload)),
            'next_retry_at' => $nextRetryAt,
            'success' => false,
        ]);

        Log::warning("Webhook delivery failed: {$webhook->name}", [
            'webhook_id' => $webhook->id,
            'event_type' => $eventType,
            'error' => $errorMessage,
            'attempt' => $attemptNumber,
        ]);

        return [
            'webhook_id' => $webhook->id,
            'webhook_name' => $webhook->name,
            'success' => false,
            'error' => $errorMessage,
            'attempt' => $attemptNumber,
            'next_retry_at' => $nextRetryAt,
        ];
    }

    /**
     * Calculate the next retry time using exponential backoff.
     *
     * @param  int  $attemptNumber  The current attempt number
     */
    private function calculateNextRetry(int $attemptNumber): Carbon
    {
        // Exponential backoff: 1min, 5min, 15min, 30min, 1hr, etc.
        $delayMinutes = match ($attemptNumber) {
            1 => 1,
            2 => 5,
            3 => 15,
            4 => 30,
            5 => 60,
            default => 120,
        };

        return now()->addMinutes($delayMinutes);
    }
}
