<?php

namespace App\Http\Middleware;

use App\Services\WebhookService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function __construct(
        private WebhookService $webhookService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     * @param  string  $mode  Verification mode: 'strict' (v2 only), 'permissive' (v1+v2), or 'log' (log only)
     */
    public function handle(Request $request, Closure $next, string $mode = 'permissive'): Response
    {
        // Skip verification if mode is 'log' - just log for monitoring
        if ($mode === 'log') {
            $this->logSignatureStatus($request);

            return $next($request);
        }

        // Get signature from header
        $signature = $request->header('X-Webhook-Signature') ?? $request->header('X-Signature');
        $secret = $request->header('X-Webhook-Secret') ?? $request->header('X-Secret');

        // If no signature provided, allow request (backward compatibility)
        // Applications should enforce signature verification at the route level if needed
        if (! $signature || ! $secret) {
            if ($mode === 'strict') {
                return $this->rejectRequest('Missing signature or secret');
            }

            Log::info('Webhook received without signature (permissive mode)', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return $next($request);
        }

        // Get payload
        $payload = $request->all();

        // Verify signature with dual-mode support
        $isValid = $this->webhookService->verifySignature(
            $payload,
            $signature,
            $secret,
            300, // 5 minute tolerance
            $mode === 'strict' // Require timestamp only in strict mode
        );

        if (! $isValid) {
            Log::warning('Webhook signature verification failed', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'mode' => $mode,
            ]);

            return $this->rejectRequest('Invalid webhook signature');
        }

        Log::info('Webhook signature verified successfully', [
            'ip' => $request->ip(),
            'path' => $request->path(),
            'mode' => $mode,
        ]);

        return $next($request);
    }

    /**
     * Log signature verification status without blocking.
     */
    private function logSignatureStatus(Request $request): void
    {
        $signature = $request->header('X-Webhook-Signature') ?? $request->header('X-Signature');
        $secret = $request->header('X-Webhook-Secret') ?? $request->header('X-Secret');
        $payload = $request->all();

        if (! $signature || ! $secret) {
            Log::info('Webhook received without signature (monitoring mode)', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return;
        }

        $isValid = $this->webhookService->verifySignature($payload, $signature, $secret, 300, false);

        Log::info('Webhook signature verification (monitoring)', [
            'ip' => $request->ip(),
            'path' => $request->path(),
            'valid' => $isValid,
        ]);
    }

    /**
     * Reject the request with a 401 response.
     */
    private function rejectRequest(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'INVALID_SIGNATURE',
                'message' => $message,
            ],
        ], 401);
    }
}
