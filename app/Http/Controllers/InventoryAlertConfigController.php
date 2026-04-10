<?php

namespace App\Http\Controllers;

use App\Models\InventoryAlertConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryAlertConfigController extends Controller
{
    /**
     * List alert configurations for a tenant.
     */
    public function index(Request $request, int $tenantId): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'nullable|integer|exists:products,id',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'store_id' => 'nullable|integer|exists:stores,id',
            'enabled' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = InventoryAlertConfig::forTenant($tenantId)
            ->with(['product:id,name,sku', 'warehouse:id,name,code', 'store:id,name,code']);

        if (isset($validated['product_id'])) {
            $query->where('product_id', $validated['product_id']);
        }

        if (isset($validated['warehouse_id'])) {
            $query->where('warehouse_id', $validated['warehouse_id']);
        }

        if (isset($validated['store_id'])) {
            $query->where('store_id', $validated['store_id']);
        }

        if (isset($validated['enabled'])) {
            $query->where('alert_enabled', $validated['enabled']);
        }

        $perPage = $validated['per_page'] ?? 15;
        $configs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $configs->map(fn($config) => [
                'id' => $config->id,
                'tenant_id' => $config->tenant_id,
                'product' => [
                    'id' => $config->product->id,
                    'name' => $config->product->name,
                    'sku' => $config->product->sku,
                ],
                'warehouse' => $config->warehouse ? [
                    'id' => $config->warehouse->id,
                    'name' => $config->warehouse->name,
                    'code' => $config->warehouse->code,
                ] : null,
                'store' => $config->store ? [
                    'id' => $config->store->id,
                    'name' => $config->store->name,
                    'code' => $config->store->code,
                ] : null,
                'min_threshold' => $config->min_threshold,
                'max_threshold' => $config->max_threshold,
                'alert_enabled' => $config->alert_enabled,
                'email_recipients' => $config->email_recipients ?? [],
                'created_at' => $config->created_at->toIso8601String(),
                'updated_at' => $config->updated_at->toIso8601String(),
            ]),
            'meta' => [
                'pagination' => [
                    'current_page' => $configs->currentPage(),
                    'last_page' => $configs->lastPage(),
                    'per_page' => $configs->perPage(),
                    'total' => $configs->total(),
                ],
            ],
        ]);
    }

    /**
     * Create a new alert configuration.
     */
    public function store(Request $request, int $tenantId): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'store_id' => 'nullable|integer|exists:stores,id',
            'min_threshold' => 'required|integer|min:0',
            'max_threshold' => 'nullable|integer|min:0',
            'alert_enabled' => 'boolean',
            'email_recipients' => 'nullable|array',
            'email_recipients.*' => 'email',
        ]);

        // Check for duplicate config
        $existing = InventoryAlertConfig::getConfigForProduct(
            $tenantId,
            $validated['product_id'],
            $validated['warehouse_id'] ?? null,
            $validated['store_id'] ?? null
        );

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Alert configuration already exists for this product and location',
            ], 422);
        }

        $config = InventoryAlertConfig::create([
            'tenant_id' => $tenantId,
            'product_id' => $validated['product_id'],
            'warehouse_id' => $validated['warehouse_id'] ?? null,
            'store_id' => $validated['store_id'] ?? null,
            'min_threshold' => $validated['min_threshold'],
            'max_threshold' => $validated['max_threshold'] ?? null,
            'alert_enabled' => $validated['alert_enabled'] ?? true,
            'email_recipients' => $validated['email_recipients'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatConfig($config),
            'message' => 'Alert configuration created successfully',
        ], 201);
    }

    /**
     * Get a single alert configuration.
     */
    public function show(Request $request, int $tenantId, int $configId): JsonResponse
    {
        $config = InventoryAlertConfig::forTenant($tenantId)
            ->where('id', $configId)
            ->with(['product:id,name,sku', 'warehouse:id,name,code', 'store:id,name,code'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $this->formatConfig($config),
        ]);
    }

    /**
     * Update an alert configuration.
     */
    public function update(Request $request, int $tenantId, int $configId): JsonResponse
    {
        $config = InventoryAlertConfig::forTenant($tenantId)->findOrFail($configId);

        $validated = $request->validate([
            'min_threshold' => 'sometimes|integer|min:0',
            'max_threshold' => 'nullable|integer|min:0',
            'alert_enabled' => 'sometimes|boolean',
            'email_recipients' => 'sometimes|array',
            'email_recipients.*' => 'email',
        ]);

        $config->update($validated);

        return response()->json([
            'success' => true,
            'data' => $this->formatConfig($config->fresh(['product', 'warehouse', 'store'])),
            'message' => 'Alert configuration updated successfully',
        ]);
    }

    /**
     * Delete an alert configuration.
     */
    public function destroy(Request $request, int $tenantId, int $configId): JsonResponse
    {
        $config = InventoryAlertConfig::forTenant($tenantId)->findOrFail($configId);
        $config->delete();

        return response()->json([
            'success' => true,
            'message' => 'Alert configuration deleted successfully',
        ]);
    }

    /**
     * Add email recipient to alert configuration.
     */
    public function addRecipient(Request $request, int $tenantId, int $configId): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $config = InventoryAlertConfig::forTenant($tenantId)->findOrFail($configId);
        $config->addRecipient($validated['email']);

        return response()->json([
            'success' => true,
            'data' => $this->formatConfig($config->fresh()),
            'message' => 'Email recipient added successfully',
        ]);
    }

    /**
     * Remove email recipient from alert configuration.
     */
    public function removeRecipient(Request $request, int $tenantId, int $configId): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $config = InventoryAlertConfig::forTenant($tenantId)->findOrFail($configId);
        $config->removeRecipient($validated['email']);

        return response()->json([
            'success' => true,
            'data' => $this->formatConfig($config->fresh()),
            'message' => 'Email recipient removed successfully',
        ]);
    }

    /**
     * Format config for JSON response.
     */
    protected function formatConfig(InventoryAlertConfig $config): array
    {
        return [
            'id' => $config->id,
            'tenant_id' => $config->tenant_id,
            'product' => $config->product ? [
                'id' => $config->product->id,
                'name' => $config->product->name,
                'sku' => $config->product->sku,
            ] : null,
            'warehouse' => $config->warehouse ? [
                'id' => $config->warehouse->id,
                'name' => $config->warehouse->name,
                'code' => $config->warehouse->code,
            ] : null,
            'store' => $config->store ? [
                'id' => $config->store->id,
                'name' => $config->store->name,
                'code' => $config->store->code,
            ] : null,
            'min_threshold' => $config->min_threshold,
            'max_threshold' => $config->max_threshold,
            'alert_enabled' => $config->alert_enabled,
            'email_recipients' => $config->email_recipients ?? [],
            'created_at' => $config->created_at->toIso8601String(),
            'updated_at' => $config->updated_at->toIso8601String(),
        ];
    }
}
