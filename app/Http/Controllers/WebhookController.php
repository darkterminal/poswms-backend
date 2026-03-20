<?php

namespace App\Http\Controllers;

use App\Models\Webhook;
use App\Models\WebhookDeliveryAttempt;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
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

        return response()->json([
            'success' => true,
            'message' => 'Webhook created successfully',
            'data' => $webhook,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->route('tenant_id');

        $webhook = Webhook::query()
            ->forTenant($tenantId)
            ->with('deliveryAttempts:id,webhook_id,event_type,attempt_number,response_status,success,created_at')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $webhook,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $webhook = Webhook::query()->forTenant($tenantId)->findOrFail($id);

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

        $webhook->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Webhook updated successfully',
            'data' => $webhook->fresh(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $webhook = Webhook::query()->forTenant($tenantId)->findOrFail($id);

        $webhook->delete();

        return response()->json([
            'success' => true,
            'message' => 'Webhook deleted successfully',
        ]);
    }

    /**
     * Test a webhook by sending a test event.
     */
    public function test(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $webhook = Webhook::query()->forTenant($tenantId)->findOrFail($id);

        $webhookService = app(WebhookService::class);

        $testPayload = [
            'test' => true,
            'message' => 'This is a test webhook event',
            'timestamp' => now()->toIso8601String(),
        ];

        $result = $webhookService->dispatchToWebhook($webhook, 'webhook.test', $testPayload);

        return response()->json([
            'success' => true,
            'message' => $result['success'] ? 'Test webhook delivered successfully' : 'Test webhook failed',
            'data' => $result,
        ]);
    }

    /**
     * Get delivery attempts for a webhook.
     */
    public function deliveryAttempts(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->route('tenant_id');

        // Verify webhook belongs to tenant
        Webhook::query()->forTenant($tenantId)->findOrFail($id);

        $attempts = WebhookDeliveryAttempt::query()
            ->where('webhook_id', $id)
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
    public function retry(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $webhook = Webhook::query()->forTenant($tenantId)->findOrFail($id);

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
