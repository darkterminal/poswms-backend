<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Webhook;
use App\Models\WebhookDeliveryAttempt;
use App\Services\UrlValidationService;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private UrlValidationService $urlValidationService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');

        $webhooks = Webhook::query()
            ->forTenant($tenantId)
            ->withCount('deliveryAttempts')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $webhooks,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'secret' => ['nullable', 'string', 'max:255'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string'],
            'active' => ['boolean'],
            'content_type' => ['in:json,form-data'],
            'headers' => ['nullable', 'array'],
            'retry_count' => ['integer', 'min:0', 'max:10'],
            'timeout' => ['integer', 'min:1', 'max:300'],
        ]);

        // SSRF Protection: Validate URL against private IP ranges
        $tenantId = $request->route('tenant_id');
        $userId = $request->user()->id;

        // Skip DNS rebinding check in testing environment
        $skipDnsRebinding = app()->environment('testing');

        $urlValidationResult = $this->urlValidationService->validateUrl(
            $validated['url'],
            allowLegacy: false,
            tenantId: $tenantId,
            userId: $userId,
            skipDnsRebindingCheck: $skipDnsRebinding
        );

        if (! $urlValidationResult['valid']) {
            Log::warning('Webhook creation blocked: SSRF risk detected', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'url' => $validated['url'],
                'reason' => $urlValidationResult['error'],
                'risk_level' => $urlValidationResult['risk_level'] ?? 'high',
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SSRF_PROTECTION',
                    'message' => 'Webhook URL validation failed',
                    'details' => $urlValidationResult['error'],
                ],
            ], 422);
        }

        $webhook = Webhook::create([
            'tenant_id' => $request->route('tenant_id'),
            'name' => $validated['name'],
            'url' => $validated['url'],
            'secret' => $validated['secret'] ?? null,
            'events' => $validated['events'],
            'active' => $validated['active'] ?? true,
            'content_type' => $validated['content_type'] ?? 'json',
            'headers' => $validated['headers'] ?? [],
            'retry_count' => $validated['retry_count'] ?? 3,
            'timeout' => $validated['timeout'] ?? 30,
        ]);

        // Audit log for successful webhook creation
        AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'event_type' => 'webhook.created',
            'auditable_type' => Webhook::class,
            'auditable_id' => $webhook->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'url' => $validated['url'],
                'events' => $validated['events'],
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Webhook created successfully',
            'data' => $webhook,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $webhookId = $request->route('webhook');

        $webhook = Webhook::query()
            ->forTenant($tenantId)
            ->with('deliveryAttempts:id,webhook_id,event_type,attempt_number,response_status,success,created_at')
            ->findOrFail($webhookId);

        return response()->json([
            'success' => true,
            'data' => $webhook,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $webhookId = $request->route('webhook');
        $userId = $request->user()->id;

        $webhook = Webhook::query()->forTenant($tenantId)->findOrFail($webhookId);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'url' => ['sometimes', 'required', 'url', 'max:2048'],
            'secret' => ['nullable', 'string', 'max:255'],
            'events' => ['sometimes', 'required', 'array', 'min:1'],
            'events.*' => ['required', 'string'],
            'active' => ['boolean'],
            'content_type' => ['in:json,form-data'],
            'headers' => ['nullable', 'array'],
            'retry_count' => ['integer', 'min:0', 'max:10'],
            'timeout' => ['integer', 'min:1', 'max:300'],
        ]);

        // SSRF Protection: Validate URL if being updated
        if (isset($validated['url'])) {
            // Skip DNS rebinding check in testing environment
            $skipDnsRebinding = app()->environment('testing');

            $urlValidationResult = $this->urlValidationService->validateUrl(
                $validated['url'],
                allowLegacy: false,
                tenantId: $tenantId,
                userId: $userId,
                skipDnsRebindingCheck: $skipDnsRebinding
            );

            if (! $urlValidationResult['valid']) {
                Log::warning('Webhook update blocked: SSRF risk detected', [
                    'tenant_id' => $tenantId,
                    'user_id' => $userId,
                    'webhook_id' => $webhookId,
                    'url' => $validated['url'],
                    'reason' => $urlValidationResult['error'],
                    'risk_level' => $urlValidationResult['risk_level'] ?? 'high',
                ]);

                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'SSRF_PROTECTION',
                        'message' => 'Webhook URL validation failed',
                        'details' => $urlValidationResult['error'],
                    ],
                ], 422);
            }
        }

        $oldUrl = $webhook->url;
        $oldEvents = $webhook->events;

        $webhook->update($validated);

        // Audit log for webhook update
        $changes = [];
        if (isset($validated['url']) && $validated['url'] !== $oldUrl) {
            $changes['url'] = ['old' => $oldUrl, 'new' => $validated['url']];
        }
        if (isset($validated['events']) && $validated['events'] !== $oldEvents) {
            $changes['events'] = ['old' => $oldEvents, 'new' => $validated['events']];
        }

        if (! empty($changes)) {
            AuditLog::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'event_type' => 'webhook.updated',
                'auditable_type' => Webhook::class,
                'auditable_id' => $webhook->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'old_values' => $changes,
                'metadata' => [
                    'webhook_id' => $webhook->id,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Webhook updated successfully',
            'data' => $webhook->fresh(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $webhookId = $request->route('webhook');

        $webhook = Webhook::query()->forTenant($tenantId)->findOrFail($webhookId);

        $webhook->delete();

        return response()->json([
            'success' => true,
            'message' => 'Webhook deleted successfully',
        ]);
    }

    /**
     * Test a webhook by sending a test event.
     */
    public function test(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $webhookId = $request->route('webhook');
        $userId = $request->user()->id;

        $webhook = Webhook::query()->forTenant($tenantId)->findOrFail($webhookId);

        // SSRF Protection: Validate URL before testing
        // This prevents testing webhooks that point to internal networks
        // Skip DNS rebinding check in testing environment
        $skipDnsRebinding = app()->environment('testing');

        $urlValidationResult = $this->urlValidationService->validateUrl(
            $webhook->url,
            allowLegacy: true, // Allow testing existing webhooks but log risk
            tenantId: $tenantId,
            userId: $userId,
            skipDnsRebindingCheck: $skipDnsRebinding
        );

        if (! $urlValidationResult['valid']) {
            Log::warning('Webhook test blocked: SSRF risk detected', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'webhook_id' => $webhookId,
                'url' => $webhook->url,
                'reason' => $urlValidationResult['error'],
                'risk_level' => $urlValidationResult['risk_level'] ?? 'high',
            ]);

            AuditLog::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'event_type' => 'security.ssrf_test_blocked',
                'auditable_type' => Webhook::class,
                'auditable_id' => $webhookId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => [
                    'webhook_id' => $webhookId,
                    'url' => $webhook->url,
                    'reason' => $urlValidationResult['error'],
                    'risk_level' => $urlValidationResult['risk_level'] ?? 'high',
                ],
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SSRF_PROTECTION',
                    'message' => 'Webhook URL blocked for security reasons',
                    'details' => $urlValidationResult['error'],
                ],
            ], 422);
        }

        // Log warning if testing a potentially risky existing webhook
        if (isset($urlValidationResult['warning'])) {
            Log::warning('Testing webhook with SSRF risk (legacy URL)', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'webhook_id' => $webhookId,
                'url' => $webhook->url,
                'warning' => $urlValidationResult['warning'],
            ]);
        }

        $webhookService = app(WebhookService::class);

        $testPayload = [
            'test' => true,
            'message' => 'This is a test webhook event',
            'timestamp' => now()->toIso8601String(),
        ];

        $result = $webhookService->dispatchToWebhook($webhook, 'webhook.test', $testPayload);

        // Audit log for webhook test
        AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'event_type' => 'webhook.tested',
            'auditable_type' => Webhook::class,
            'auditable_id' => $webhook->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'webhook_id' => $webhook->id,
                'url' => $webhook->url,
                'success' => $result['success'],
                'status' => $result['status'] ?? null,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => $result['success'] ? 'Test webhook delivered successfully' : 'Test webhook failed',
            'data' => $result,
        ]);
    }

    /**
     * Get delivery attempts for a webhook.
     */
    public function deliveryAttempts(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $webhookId = $request->route('webhook');

        // Verify webhook belongs to tenant
        Webhook::query()->forTenant($tenantId)->findOrFail($webhookId);

        $attempts = WebhookDeliveryAttempt::query()
            ->where('webhook_id', $webhookId)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $attempts,
        ]);
    }

    /**
     * Retry failed delivery attempts for a webhook.
     */
    public function retry(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $webhookId = $request->route('webhook');
        $webhook = Webhook::query()->forTenant($tenantId)->findOrFail($webhookId);

        if (! $webhook->active) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot retry webhooks that are not active',
            ], 400);
        }

        $webhookService = app(WebhookService::class);
        $retried = $webhookService->retryFailedDeliveries();

        return response()->json([
            'success' => true,
            'message' => "Retried {$retried} failed delivery attempt(s)",
        ]);
    }
}
