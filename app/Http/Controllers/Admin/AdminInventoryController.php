<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminInventoryController extends Controller
{
    /**
     * List inventory across all tenants with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'stock_status' => ['nullable', 'string', 'in:in_stock,low_stock,out_of_stock'],
            'sort_by' => ['nullable', 'string', 'in:product_name,quantity,available,cost,created_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 20;

        $query = Inventory::with(['tenant', 'product', 'warehouse', 'store'])
            ->select('inventories.*');

        // Filter by tenant
        if (! empty($validated['tenant_id'])) {
            $query->where('inventories.tenant_id', $validated['tenant_id']);
        }

        // Filter by warehouse
        if (! empty($validated['warehouse_id'])) {
            $query->where('inventories.warehouse_id', $validated['warehouse_id']);
        }

        // Filter by store
        if (! empty($validated['store_id'])) {
            $query->where('inventories.store_id', $validated['store_id']);
        }

        // Search by product name or SKU
        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Filter by stock status
        if (! empty($validated['stock_status'])) {
            if ($validated['stock_status'] === 'in_stock') {
                $query->where('inventories.available', '>', 0);
            } elseif ($validated['stock_status'] === 'low_stock') {
                $query->join('products', 'inventories.product_id', '=', 'products.id')
                    ->whereColumn('inventories.available', '<=', 'products.min_stock')
                    ->where('inventories.available', '>', 0);
            } elseif ($validated['stock_status'] === 'out_of_stock') {
                $query->where('inventories.available', 0);
            }
        }

        // Sorting
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDirection = $validated['sort_direction'] ?? 'desc';

        if ($sortBy === 'product_name') {
            $query->join('products', 'inventories.product_id', '=', 'products.id')
                ->orderBy('products.name', $sortDirection);
        } else {
            $query->orderBy("inventories.{$sortBy}", $sortDirection);
        }

        $inventories = $query->paginate($perPage, ['inventories.*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => [
                'inventories' => $inventories->getCollection()->map(fn($inv) => [
                    'id' => $inv->id,
                    'tenant_id' => $inv->tenant_id,
                    'tenant_name' => $inv->tenant?->name,
                    'product_id' => $inv->product_id,
                    'product_name' => $inv->product?->name,
                    'product_sku' => $inv->product?->sku,
                    'warehouse_id' => $inv->warehouse_id,
                    'warehouse_name' => $inv->warehouse?->name,
                    'store_id' => $inv->store_id,
                    'store_name' => $inv->store?->name,
                    'quantity' => $inv->quantity,
                    'reserved' => $inv->reserved,
                    'available' => $inv->available,
                    'cost' => $inv->cost,
                    'location' => $inv->location,
                    'notes' => $inv->notes,
                    'is_low_stock' => $inv->product ? ($inv->available <= $inv->product->min_stock) : false,
                    'created_at' => $inv->created_at->toIso8601String(),
                    'updated_at' => $inv->updated_at->toIso8601String(),
                ]),
                'pagination' => [
                    'current_page' => $inventories->currentPage(),
                    'per_page' => $inventories->perPage(),
                    'total' => $inventories->total(),
                    'last_page' => $inventories->lastPage(),
                    'has_more' => $inventories->hasMorePages(),
                ],
            ],
            'message' => 'Inventory retrieved successfully',
        ], 200);
    }

    /**
     * Get inventory statistics across all tenants.
     */
    public function stats(): JsonResponse
    {
        $totalInventoryRecords = Inventory::count();
        $totalQuantity = Inventory::sum('quantity');
        $totalAvailable = Inventory::sum('available');
        $totalReserved = Inventory::sum('reserved');
        $totalValue = Inventory::sum(DB::raw('quantity * COALESCE(cost, 0)'));

        $inStockCount = Inventory::where('available', '>', 0)->count();
        $lowStockCount = Inventory::join('products', 'inventories.product_id', '=', 'products.id')
            ->whereColumn('inventories.available', '<=', 'products.min_stock')
            ->where('inventories.available', '>', 0)
            ->count();
        $outOfStockCount = Inventory::where('available', 0)->count();

        $tenantsWithInventory = Inventory::distinct('tenant_id')->count('tenant_id');
        $warehousesWithStock = Inventory::whereNotNull('warehouse_id')->distinct('warehouse_id')->count('warehouse_id');
        $storesWithStock = Inventory::whereNotNull('store_id')->distinct('store_id')->count('store_id');

        return response()->json([
            'success' => true,
            'data' => [
                'total_inventory_records' => $totalInventoryRecords,
                'total_quantity' => $totalQuantity,
                'total_available' => $totalAvailable,
                'total_reserved' => $totalReserved,
                'total_value' => round($totalValue, 2),
                'in_stock_count' => $inStockCount,
                'low_stock_count' => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
                'tenants_with_inventory' => $tenantsWithInventory,
                'warehouses_with_stock' => $warehousesWithStock,
                'stores_with_stock' => $storesWithStock,
            ],
            'message' => 'Inventory statistics retrieved successfully',
        ], 200);
    }

    /**
     * Get a single inventory record details with FIFO layers.
     */
    public function show($inventoryId): JsonResponse
    {
        // Bypass tenant scope for super admin
        $inventory = Inventory::withoutGlobalScopes()
            ->with(['tenant', 'product', 'warehouse', 'store', 'layers.batch'])
            ->findOrFail($inventoryId);

        return response()->json([
            'success' => true,
            'data' => [
                'inventory' => [
                    'id' => $inventory->id,
                    'tenant_id' => $inventory->tenant_id,
                    'tenant_name' => $inventory->tenant?->name,
                    'product_id' => $inventory->product_id,
                    'product_name' => $inventory->product?->name,
                    'product_sku' => $inventory->product?->sku,
                    'warehouse_id' => $inventory->warehouse_id,
                    'warehouse_name' => $inventory->warehouse?->name,
                    'store_id' => $inventory->store_id,
                    'store_name' => $inventory->store?->name,
                    'quantity' => $inventory->quantity,
                    'reserved' => $inventory->reserved,
                    'available' => $inventory->available,
                    'cost' => $inventory->cost,
                    'location' => $inventory->location,
                    'notes' => $inventory->notes,
                    'is_low_stock' => $inventory->product ? ($inventory->available <= $inventory->product->min_stock) : false,
                    'fifo_layers' => $inventory->getFifoSummary(),
                    'created_at' => $inventory->created_at->toIso8601String(),
                    'updated_at' => $inventory->updated_at->toIso8601String(),
                ],
            ],
            'message' => 'Inventory retrieved successfully',
        ], 200);
    }

    /**
     * Export inventory to CSV.
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'stock_status' => ['nullable', 'string', 'in:in_stock,low_stock,out_of_stock'],
            'sort_by' => ['nullable', 'string', 'in:product_name,quantity,available,cost,created_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $query = Inventory::with(['tenant', 'product', 'warehouse', 'store'])
            ->select('inventories.*');

        // Apply same filters as index
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
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['stock_status'])) {
            if ($validated['stock_status'] === 'in_stock') {
                $query->where('inventories.available', '>', 0);
            } elseif ($validated['stock_status'] === 'low_stock') {
                $query->join('products', 'inventories.product_id', '=', 'products.id')
                    ->whereColumn('inventories.available', '<=', 'products.min_stock')
                    ->where('inventories.available', '>', 0);
            } elseif ($validated['stock_status'] === 'out_of_stock') {
                $query->where('inventories.available', 0);
            }
        }

        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDirection = $validated['sort_direction'] ?? 'desc';

        if ($sortBy === 'product_name') {
            $query->join('products', 'inventories.product_id', '=', 'products.id')
                ->orderBy('products.name', $sortDirection);
        } else {
            $query->orderBy("inventories.{$sortBy}", $sortDirection);
        }

        $inventories = $query->get();

        // Generate CSV
        $filename = 'pos-inventory-' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($inventories) {
            $file = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($file, [
                'ID',
                'Tenant Name',
                'Product Name',
                'SKU',
                'Warehouse',
                'Store',
                'Quantity',
                'Reserved',
                'Available',
                'Unit Cost',
                'Total Value',
                'Location',
                'Notes',
                'Created At',
            ]);

            // CSV Data
            foreach ($inventories as $inv) {
                fputcsv($file, [
                    $inv->id,
                    $inv->tenant?->name,
                    $inv->product?->name,
                    $inv->product?->sku,
                    $inv->warehouse?->name,
                    $inv->store?->name,
                    $inv->quantity,
                    $inv->reserved,
                    $inv->available,
                    $inv->cost,
                    round($inv->quantity * $inv->cost, 2),
                    $inv->location,
                    $inv->notes,
                    $inv->created_at->toIso8601String(),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
