<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreApiKeyRequest;
use App\Http\Requests\Admin\UpdateApiKeyRequest;
use App\Models\ApiKey;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    /**
     * Display a listing of API keys for a tenant.
     */
    public function index(Tenant $tenant): JsonResponse
    {
        $apiKeys = $tenant->apiKeys()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($apiKey) {
                return [
                    'id' => $apiKey->id,
                    'tenant_id' => $apiKey->tenant_id,
                    'user_id' => $apiKey->user_id,
                    'name' => $apiKey->name,
                    'key_preview' => substr($apiKey->key, 0, 8) . '...' . substr($apiKey->key, -4),
                    'abilities' => $apiKey->abilities ?? [],
                    'last_used_at' => $apiKey->last_used_at?->toIso8601String(),
                    'expires_at' => $apiKey->expires_at?->toIso8601String(),
                    'is_expired' => $apiKey->isExpired(),
                    'is_active' => !$apiKey->isExpired(),
                    'created_at' => $apiKey->created_at->toIso8601String(),
                    'updated_at' => $apiKey->updated_at->toIso8601String(),
                    'user' => $apiKey->user ? [
                        'id' => $apiKey->user->id,
                        'name' => $apiKey->user->name,
                        'email' => $apiKey->user->email,
                    ] : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $apiKeys,
        ]);
    }

    /**
     * Store a newly created API key.
     */
    public function store(StoreApiKeyRequest $request, Tenant $tenant): JsonResponse
    {
        $apiKey = $tenant->apiKeys()->create([
            'user_id' => auth()->id(),
            'name' => $request->validated('name'),
            'key' => Str::random(48),
            'abilities' => $request->validated('abilities'),
            'expires_at' => $request->validated('expires_at'),
        ]);

        // Return the full key only once on creation
        return response()->json([
            'success' => true,
            'message' => 'API key created successfully',
            'data' => [
                'api_key' => $apiKey,
                'full_key' => $apiKey->key, // Only shown once
            ],
        ], 201);
    }

    /**
     * Display the specified API key.
     */
    public function show(Tenant $tenant, int $apiKey): JsonResponse
    {
        $apiKey = $tenant->apiKeys()->with('user')->findOrFail($apiKey);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $apiKey->id,
                'tenant_id' => $apiKey->tenant_id,
                'user_id' => $apiKey->user_id,
                'name' => $apiKey->name,
                'key_preview' => substr($apiKey->key, 0, 8) . '...' . substr($apiKey->key, -4),
                'abilities' => $apiKey->abilities ?? [],
                'last_used_at' => $apiKey->last_used_at?->toIso8601String(),
                'expires_at' => $apiKey->expires_at?->toIso8601String(),
                'is_expired' => $apiKey->isExpired(),
                'is_active' => !$apiKey->isExpired(),
                'created_at' => $apiKey->created_at->toIso8601String(),
                'updated_at' => $apiKey->updated_at->toIso8601String(),
                'user' => $apiKey->user ? [
                    'id' => $apiKey->user->id,
                    'name' => $apiKey->user->name,
                    'email' => $apiKey->user->email,
                ] : null,
            ],
        ]);
    }

    /**
     * Update the specified API key.
     */
    public function update(UpdateApiKeyRequest $request, Tenant $tenant, int $apiKeyId): JsonResponse
    {
        $apiKey = $tenant->apiKeys()->findOrFail($apiKeyId);

        $validated = $request->validated();

        $apiKey->update([
            'name' => $validated['name'] ?? $apiKey->name,
            'abilities' => $validated['abilities'] ?? $apiKey->abilities,
            'expires_at' => $validated['expires_at'] ?? $apiKey->expires_at,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'API key updated successfully',
            'data' => $apiKey,
        ]);
    }

    /**
     * Remove the specified API key.
     */
    public function destroy(Tenant $tenant, int $apiKeyId): JsonResponse
    {
        $apiKey = $tenant->apiKeys()->findOrFail($apiKeyId);

        $apiKey->delete();

        return response()->json([
            'success' => true,
            'message' => 'API key deleted successfully',
        ]);
    }

    /**
     * Regenerate the API key.
     */
    public function regenerate(Tenant $tenant, int $apiKeyId): JsonResponse
    {
        $apiKey = $tenant->apiKeys()->findOrFail($apiKeyId);

        $newKey = Str::random(48);
        $apiKey->update(['key' => $newKey]);

        return response()->json([
            'success' => true,
            'message' => 'API key regenerated successfully',
            'data' => [
                'full_key' => $newKey, // Only shown once
                'key_preview' => substr($newKey, 0, 8) . '...' . substr($newKey, -4),
            ],
        ]);
    }

    /**
     * Get API key usage statistics.
     */
    public function stats(Tenant $tenant): JsonResponse
    {
        $total = $tenant->apiKeys()->count();
        $active = $tenant->apiKeys()->active()->count();
        $expired = $total - $active;
        $neverUsed = $tenant->apiKeys()->whereNull('last_used_at')->count();
        $used = $total - $neverUsed;

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'active' => $active,
                'expired' => $expired,
                'used' => $used,
                'never_used' => $neverUsed,
            ],
        ]);
    }
}
