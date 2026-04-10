<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryTransferRequest;
use App\Services\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryTransferController extends Controller
{
    public function __construct(
        private StockTransferService $transferService
    ) {}

    /**
     * Transfer stock between locations.
     */
    public function transfer(InventoryTransferRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->transferService->transfer(
                tenantId: $request->route('tenant_id'),
                productId: $validated['product_id'],
                quantity: $validated['quantity'],
                fromWarehouseId: $validated['from_warehouse_id'] ?? null,
                fromStoreId: $validated['from_store_id'] ?? null,
                toWarehouseId: $validated['to_warehouse_id'] ?? null,
                toStoreId: $validated['to_store_id'] ?? null,
                userId: $request->user()?->id,
                reason: $validated['reason'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'source_inventory' => $result['source_inventory'],
                    'destination_inventory' => $result['destination_inventory'],
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get transferable inventory for a product.
     */
    public function getTransferableInventory(Request $request, int $productId): JsonResponse
    {
        $locationId = $request->query('location_id');
        $locationType = $request->query('location_type', 'warehouse');

        $inventory = $this->transferService->getTransferableInventory(
            tenantId: $request->route('tenant_id'),
            productId: $productId,
            locationId: $locationId,
            locationType: $locationType
        );

        return response()->json([
            'success' => true,
            'data' => ['inventory' => $inventory],
        ]);
    }
}
