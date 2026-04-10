<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockAdjustmentRequest;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Services\StockAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockAdjustmentController extends Controller
{
    public function __construct(
        private StockAdjustmentService $adjustmentService
    ) {}

    /**
     * Adjust inventory stock.
     */
    public function adjust(StockAdjustmentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->adjustmentService->adjustStock(
                tenantId: $request->route('tenant_id'),
                inventoryId: $validated['inventory_id'],
                quantity: $validated['quantity'],
                adjustmentType: $validated['adjustment_type'],
                reason: $validated['reason'],
                unitCost: $validated['unit_cost'] ?? null,
                batchNumber: $validated['batch_number'] ?? null,
                notes: $validated['notes'] ?? null,
                userId: $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Inventory adjusted successfully',
                'data' => [
                    'inventory' => $result['inventory']->load(['product', 'warehouse', 'store']),
                    'movement' => $result['movement'],
                    'quantity_before' => $result['quantity_before'],
                    'quantity_after' => $result['quantity_after'],
                    'adjustment' => $result['adjustment'],
                ],
            ], 200);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get adjustment history for an inventory record.
     */
    public function history(Request $request, int $inventoryId): JsonResponse
    {
        $inventory = Inventory::where('tenant_id', $request->route('tenant_id'))
            ->where('id', $inventoryId)
            ->firstOrFail();

        $movements = StockMovement::where('tenant_id', $request->route('tenant_id'))
            ->where('inventory_id', $inventoryId)
            ->where('type', 'adjustment')
            ->with(['user:id,name,email', 'layer'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'inventory' => $inventory->load(['product', 'warehouse', 'store']),
                'movements' => $movements,
            ],
        ], 200);
    }
}
