<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryReportRequest;
use App\ExportService;
use App\Models\Inventory;
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
    public function stockLevels(InventoryReportRequest $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $warehouseId = $request->validated('warehouse_id');
        $storeId = $request->validated('store_id');

        $query = Inventory::forTenantReport($tenantId, $warehouseId, $storeId)
            ->with(['product:id,name,sku', 'warehouse:id,name', 'store:id,name']);

        $paginator = $query->paginate(50);

        // Calculate summary using a fresh aggregate query to avoid memory issues
        $summaryQuery = Inventory::forTenantReport($tenantId, $warehouseId, $storeId);
        $summary = [
            'total_items' => $summaryQuery->count(),
            'total_quantity' => $summaryQuery->sum('quantity'),
            'total_available' => $summaryQuery->sum('available'),
            'total_reserved' => $summaryQuery->sum('reserved'),
            'total_value' => round($summaryQuery->sum(\DB::raw('quantity * cost')), 2),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'inventories' => $paginator->items(),
                'summary' => $summary,
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                ],
            ],
        ]);
    }

    /**
     * Export stock levels report to CSV.
     */
    public function exportStockLevels(InventoryReportRequest $request): StreamedResponse
    {
        $tenantId = $request->route('tenant_id');
        $warehouseId = $request->validated('warehouse_id');
        $storeId = $request->validated('store_id');

        $query = Inventory::forTenantReport($tenantId, $warehouseId, $storeId)
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
