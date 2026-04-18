<?php

namespace App\Http\Controllers\Admin;

use App\ExportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminInventoryReportRequest;
use App\Models\Inventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminInventoryReportController extends Controller
{
    public function __construct(
        private ExportService $exportService
    ) {}

    /**
     * Get stock levels report across all tenants.
     */
    public function stockLevels(AdminInventoryReportRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = $validated['per_page'] ?? 50;

        $query = Inventory::with(['tenant', 'product:id,name,sku', 'warehouse:id,name', 'store:id,name']);

        if (! empty($validated['tenant_id'])) {
            $query->where('tenant_id', $validated['tenant_id']);
        }

        if (! empty($validated['warehouse_id'])) {
            $query->where('warehouse_id', $validated['warehouse_id']);
        }

        if (! empty($validated['store_id'])) {
            $query->where('store_id', $validated['store_id']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage);

        $formattedInventories = collect($paginator->items())->map(function ($item) {
            return [
                'id' => $item->id,
                'tenant_id' => $item->tenant_id,
                'tenant_name' => $item->tenant?->name,
                'product' => $item->product ? [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'sku' => $item->product->sku,
                ] : null,
                'location' => [
                    'warehouse' => $item->warehouse?->name,
                    'store' => $item->store?->name,
                    'aisle' => $item->location,
                ],
                'quantity' => (int) $item->quantity,
                'reserved' => (int) $item->reserved,
                'available' => (int) $item->available,
                'cost' => (float) $item->cost,
                'total_value' => round($item->quantity * $item->cost, 2),
            ];
        });

        // Summary calculations
        $summaryQuery = Inventory::query();
        if (! empty($validated['tenant_id'])) {
            $summaryQuery->where('tenant_id', $validated['tenant_id']);
        }
        if (! empty($validated['warehouse_id'])) {
            $summaryQuery->where('warehouse_id', $validated['warehouse_id']);
        }
        if (! empty($validated['store_id'])) {
            $summaryQuery->where('store_id', $validated['store_id']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $summaryQuery->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $summary = [
            'total_items' => $summaryQuery->count(),
            'total_quantity' => (int) $summaryQuery->sum('quantity'),
            'total_available' => (int) $summaryQuery->sum('available'),
            'total_reserved' => (int) $summaryQuery->sum('reserved'),
            'total_value' => round($summaryQuery->sum(DB::raw('quantity * cost')), 2),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'inventories' => $formattedInventories,
                'summary' => $summary,
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Export stock levels report to CSV.
     */
    public function exportStockLevels(AdminInventoryReportRequest $request): StreamedResponse
    {
        $validated = $request->validated();

        $query = Inventory::query()
            ->join('tenants', 'inventories.tenant_id', '=', 'tenants.id')
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->leftJoin('warehouses', 'inventories.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('stores', 'inventories.store_id', '=', 'stores.id')
            ->selectRaw('
                inventories.id,
                tenants.name as tenant_name,
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

        if (! empty($validated['tenant_id'])) {
            $query->where('inventories.tenant_id', $validated['tenant_id']);
        }

        if (! empty($validated['warehouse_id'])) {
            $query->where('inventories.warehouse_id', $validated['warehouse_id']);
        }

        if (! empty($validated['store_id'])) {
            $query->where('inventories.store_id', $validated['store_id']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                    ->orWhere('products.sku', 'like', "%{$search}%");
            });
        }

        $columns = [
            'id' => 'ID',
            'tenant_name' => 'Tenant',
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

        $filename = 'admin-stock-levels-' . date('Y-m-d') . '.csv';

        return $this->exportService->exportCsvFromQuery($query, $columns, $filename);
    }
}
