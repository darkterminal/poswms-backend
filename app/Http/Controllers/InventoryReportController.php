<?php

namespace App\Http\Controllers;

use App\ExportService;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Services\LowStockAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryReportController extends Controller
{
    public function __construct(
        private LowStockAlertService $alertService,
        private ExportService $exportService
    ) {}

    /**
     * Get low stock alerts.
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
     * Get inventory report.
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
     * Get stock levels report.
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
                'product' => $inventory->product ? [
                    'id' => $inventory->product->id,
                    'name' => $inventory->product->name,
                    'sku' => $inventory->product->sku,
                ] : null,
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
     * Get inventory movement history.
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

    /**
     * Export stock levels report to CSV.
     */
    public function exportStockLevels(Request $request): StreamedResponse
    {
        $tenantId = $request->route('tenant_id');
        $warehouseId = $request->query('warehouse_id');
        $storeId = $request->query('store_id');

        $query = Inventory::where('inventories.tenant_id', $tenantId)
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->leftJoin('warehouses', 'inventories.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('stores', 'inventories.store_id', '=', 'stores.id')
            ->selectRaw('
                inventories.id,
                products.id as product_id,
                products.name as product_name,
                products.sku as product_sku,
                warehouses.name as warehouse,
                stores.name as store,
                inventories.quantity,
                inventories.reserved,
                inventories.available,
                inventories.cost,
                inventories.quantity * inventories.cost as total_value
            ');

        if ($warehouseId) {
            $query->where('inventories.warehouse_id', $warehouseId);
        }

        if ($storeId) {
            $query->where('inventories.store_id', $storeId);
        }

        $columns = [
            'id' => 'ID',
            'product_id' => 'Product ID',
            'product_name' => 'Product Name',
            'product_sku' => 'SKU',
            'warehouse' => 'Warehouse',
            'store' => 'Store',
            'quantity' => 'Quantity',
            'reserved' => 'Reserved',
            'available' => 'Available',
            'cost' => 'Cost',
            'total_value' => 'Total Value',
        ];

        $filename = sprintf('stock_levels_%s.csv', now()->format('Y-m-d'));

        return $this->exportService->exportCsvFromQuery($query, $columns, $filename);
    }

    /**
     * Export inventory movements report to CSV.
     */
    public function exportMovements(Request $request): StreamedResponse
    {
        $tenantId = $request->route('tenant_id');
        $productId = $request->query('product_id');
        $warehouseId = $request->query('warehouse_id');
        $limit = $request->query('limit', 1000);

        $query = StockMovement::where('stock_movements.tenant_id', $tenantId)
            ->join('products', 'stock_movements.product_id', '=', 'products.id')
            ->leftJoin('warehouses', 'stock_movements.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('stores', 'stock_movements.store_id', '=', 'stores.id')
            ->leftJoin('users', 'stock_movements.user_id', '=', 'users.id')
            ->selectRaw('
                stock_movements.id,
                products.name as product_name,
                products.sku as product_sku,
                warehouses.name as warehouse,
                stores.name as store,
                stock_movements.movement_type,
                stock_movements.quantity,
                stock_movements.quantity_before,
                stock_movements.quantity_after,
                stock_movements.reference,
                users.name as user,
                stock_movements.created_at
            ')
            ->orderBy('stock_movements.created_at', 'desc')
            ->limit($limit);

        if ($productId) {
            $query->where('stock_movements.product_id', $productId);
        }

        if ($warehouseId) {
            $query->where('stock_movements.warehouse_id', $warehouseId);
        }

        $columns = [
            'id' => 'ID',
            'product_name' => 'Product',
            'product_sku' => 'SKU',
            'warehouse' => 'Warehouse',
            'store' => 'Store',
            'movement_type' => 'Type',
            'quantity' => 'Quantity',
            'quantity_before' => 'Before',
            'quantity_after' => 'After',
            'reference' => 'Reference',
            'user' => 'User',
            'created_at' => 'Date',
        ];

        $filename = sprintf('inventory_movements_%s.csv', now()->format('Y-m-d'));

        return $this->exportService->exportCsvFromQuery($query, $columns, $filename);
    }

    /**
     * Export low stock alerts to CSV.
     */
    public function exportLowStock(Request $request): StreamedResponse
    {
        $alerts = $this->alertService->checkLowStock($request->route('tenant_id'));

        // Convert alerts to flat array for CSV
        $flatData = array_map(function ($item) {
            return [
                'product_id' => $item['product_id'] ?? '',
                'product_name' => $item['product_name'] ?? '',
                'product_sku' => $item['product_sku'] ?? '',
                'warehouse' => $item['warehouse_name'] ?? 'N/A',
                'store' => $item['store_name'] ?? 'N/A',
                'current_quantity' => $item['current_quantity'] ?? 0,
                'minimum_quantity' => $item['minimum_quantity'] ?? 0,
                'shortage' => $item['shortage'] ?? 0,
                'severity' => $item['severity'] ?? 'low',
            ];
        }, $alerts);

        $columns = [
            'product_id' => 'Product ID',
            'product_name' => 'Product Name',
            'product_sku' => 'SKU',
            'warehouse' => 'Warehouse',
            'store' => 'Store',
            'current_quantity' => 'Current Qty',
            'minimum_quantity' => 'Min Qty',
            'shortage' => 'Shortage',
            'severity' => 'Severity',
        ];

        $filename = sprintf('low_stock_alerts_%s.csv', now()->format('Y-m-d'));

        return $this->exportService->exportCsv($flatData, $columns, $filename);
    }
}
