<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\StockMovement;
use App\Services\LowStockAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryReportController extends Controller
{
    public function __construct(
        private LowStockAlertService $alertService
    ) {}

    /**
     * Get low stock alerts
     */
    public function lowStock(Request $request): JsonResponse
    {
        $alerts = $this->alertService->checkLowStock($request->route('tenant_id'));

        return response()->json([
            'success' => true,
            'data' => $alerts,
        ]);
    }

    /**
     * Get inventory report
     */
    public function report(Request $request): JsonResponse
    {
        $warehouseId = $request->query('warehouse_id');
        $storeId = $request->query('store_id');

        $report = $this->alertService->generateReport(
            tenantId: $request->route('tenant_id'),
            warehouseId: $warehouseId,
            storeId: $storeId
        );

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Get stock levels report
     */
    public function stockLevels(Request $request): JsonResponse
    {
        $query = Inventory::where('tenant_id', $request->route('tenant_id'))
            ->with(['product', 'warehouse', 'store']);

        $warehouseId = $request->query('warehouse_id');
        $storeId = $request->query('store_id');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $inventories = $query->get()->map(function ($inventory) {
            return [
                'id' => $inventory->id,
                'product' => [
                    'id' => $inventory->product->id,
                    'name' => $inventory->product->name,
                    'sku' => $inventory->product->sku,
                ],
                'location' => [
                    'warehouse' => $inventory->warehouse?->name,
                    'store' => $inventory->store?->name,
                ],
                'quantity' => $inventory->quantity,
                'reserved' => $inventory->reserved,
                'available' => $inventory->available,
                'cost' => $inventory->cost,
                'total_value' => $inventory->quantity * $inventory->cost,
            ];
        });

        $totalValue = $inventories->sum('total_value');

        return response()->json([
            'success' => true,
            'data' => [
                'inventories' => $inventories,
                'summary' => [
                    'total_items' => $inventories->count(),
                    'total_quantity' => $inventories->sum('quantity'),
                    'total_available' => $inventories->sum('available'),
                    'total_reserved' => $inventories->sum('reserved'),
                    'total_value' => round($totalValue, 2),
                ],
            ],
        ]);
    }

    /**
     * Get inventory movement history
     */
    public function movements(Request $request): JsonResponse
    {
        $productId = $request->query('product_id');
        $warehouseId = $request->query('warehouse_id');
        $limit = $request->query('limit', 50);

        $query = StockMovement::where('tenant_id', $request->route('tenant_id'))
            ->with(['product', 'warehouse', 'store', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($productId) {
            $query->where('product_id', $productId);
        }

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $movements = $query->get();

        return response()->json([
            'success' => true,
            'data' => ['movements' => $movements],
        ]);
    }
}
