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
            ->when($tenantId, fn ($query) => $query->forTenant($tenantId))
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
     */
    public function generateSignature(array $payload, string $secret): string
    {
        $payloadString = json_encode($payload, JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha256', $payloadString, $secret);
    }

    /**
     * Verify a webhook signature.
     *
     * @param  array<string, mixed>  $payload  The received payload
     * @param  string  $signature  The signature to verify
     * @param  string  $secret  The webhook secret
     */
    public function verifySignature(array $payload, string $signature, string $secret): bool
    {
        $expectedSignature = $this->generateSignature($payload, $secret);

        return hash_equals($expectedSignature, $signature);
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
        $webhookPayload = [
            'event' => $eventType,
            'timestamp' => now()->toIso8601String(),
            'data' => $payload,
        ];

        // Add signature if secret is set
        if ($webhook->secret) {
            $webhookPayload['signature'] = $this->generateSignature($webhookPayload, $webhook->secret);
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
