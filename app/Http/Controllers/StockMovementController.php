<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockMovementController extends Controller
{
    /**
     * Display a listing of stock movements with filtering and pagination.
     * For tenant-scoped access, use tenant_id from route.
     * For super admin access, allow filtering by tenant_id.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $isAdmin = !$tenantId; // Super admin if no tenant_id in route

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'type' => ['nullable', 'string', 'in:in,out,adjustment,transfer_in,transfer_out,transfer,sale,return'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'string', 'in:created_at,quantity,total_cost,product_name'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 20;

        $query = StockMovement::with(['tenant', 'product', 'warehouse', 'store', 'user', 'layer', 'order']);

        // Filter by tenant (required for tenant-scoped, optional for super admin)
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } elseif (!empty($validated['tenant_id'])) {
            $query->where('tenant_id', $validated['tenant_id']);
        }

        // Filter by product
        if (!empty($validated['product_id'])) {
            $query->where('product_id', $validated['product_id']);
        }

        // Filter by warehouse
        if (!empty($validated['warehouse_id'])) {
            $query->where('warehouse_id', $validated['warehouse_id']);
        }

        // Filter by store
        if (!empty($validated['store_id'])) {
            $query->where('store_id', $validated['store_id']);
        }

        // Filter by type
        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        // Filter by user
        if (!empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        // Filter by date range
        if (!empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (!empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        // Search by product name, SKU, or reference
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', function ($productQuery) use ($search) {
                    $productQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                })
                ->orWhere('reference', 'like', "%{$search}%")
                ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDirection = $validated['sort_direction'] ?? 'desc';

        if ($sortBy === 'product_name') {
            $query->join('products', 'stock_movements.product_id', '=', 'products.id')
                ->orderBy('products.name', $sortDirection)
                ->select('stock_movements.*');
        } else {
            $query->orderBy("stock_movements.{$sortBy}", $sortDirection);
        }

        $movements = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => [
                'movements' => $movements->getCollection()->map(fn($movement) => [
                    'id' => $movement->id,
                    'tenant_id' => $movement->tenant_id,
                    'tenant_name' => $movement->tenant?->name ?? null,
                    'product' => $movement->product ? [
                        'id' => $movement->product->id,
                        'name' => $movement->product->name,
                        'sku' => $movement->product->sku,
                    ] : null,
                    'warehouse' => $movement->warehouse ? [
                        'id' => $movement->warehouse->id,
                        'name' => $movement->warehouse->name,
                        'code' => $movement->warehouse->code,
                    ] : null,
                    'store' => $movement->store ? [
                        'id' => $movement->store->id,
                        'name' => $movement->store->name,
                        'code' => $movement->store->code,
                    ] : null,
                    'user' => $movement->user ? [
                        'id' => $movement->user->id,
                        'name' => $movement->user->name,
                        'email' => $movement->user->email,
                    ] : null,
                    'type' => $movement->type,
                    'quantity' => $movement->quantity,
                    'quantity_before' => $movement->quantity_before,
                    'quantity_after' => $movement->quantity_after,
                    'unit_cost' => $movement->unit_cost,
                    'total_cost' => $movement->total_cost,
                    'reason' => $movement->reason,
                    'reference' => $movement->reference,
                    'created_at' => $movement->created_at->toIso8601String(),
                ]),
                'pagination' => [
                    'current_page' => $movements->currentPage(),
                    'per_page' => $movements->perPage(),
                    'total' => $movements->total(),
                    'last_page' => $movements->lastPage(),
                    'has_more' => $movements->hasMorePages(),
                ],
            ],
            'message' => 'Stock movements retrieved successfully',
        ], 200);
    }

    /**
     * Get stock movement statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $isAdmin = !$tenantId; // Super admin if no tenant_id in route

        // For super admin, allow filtering by tenant_id from query params
        $validated = $request->validate([
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
        ]);

        $effectiveTenantId = $tenantId ?? $validated['tenant_id'] ?? null;

        $query = DB::table('stock_movements');
        if ($effectiveTenantId !== null) {
            $query->where('tenant_id', $effectiveTenantId);
        }

        $stats = $query->selectRaw('
            COUNT(*) as total_movements,
            SUM(CASE WHEN type IN ("in", "sale", "return") AND quantity > 0 THEN quantity 
                     WHEN type IN ("out", "sale", "transfer") AND quantity < 0 THEN ABS(quantity)
                     ELSE 0 END) as total_in,
            SUM(CASE WHEN type IN ("out", "sale", "transfer") AND quantity < 0 THEN ABS(quantity)
                     WHEN type IN ("in", "return") AND quantity > 0 THEN 0
                     ELSE 0 END) as total_out,
            SUM(CASE WHEN type = "adjustment" THEN ABS(quantity) ELSE 0 END) as total_adjustments,
            SUM(CASE WHEN type LIKE "transfer%" THEN ABS(quantity) ELSE 0 END) as total_transfers,
            SUM(COALESCE(total_cost, 0)) as total_value
        ')->first();

        // Get movements by type
        $typeQuery = DB::table('stock_movements');
        if ($effectiveTenantId !== null) {
            $typeQuery->where('tenant_id', $effectiveTenantId);
        }

        $movementsByType = $typeQuery
            ->selectRaw('type, COUNT(*) as count, SUM(ABS(quantity)) as total_quantity')
            ->groupBy('type')
            ->get()
            ->pluck('count', 'type');

        // Get recent activity (last 7 days)
        $activityQuery = DB::table('stock_movements');
        if ($effectiveTenantId !== null) {
            $activityQuery->where('tenant_id', $effectiveTenantId);
        }

        $recentActivity = $activityQuery
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_movements' => $stats->total_movements ?? 0,
                'total_in' => $stats->total_in ?? 0,
                'total_out' => $stats->total_out ?? 0,
                'total_adjustments' => $stats->total_adjustments ?? 0,
                'total_transfers' => $stats->total_transfers ?? 0,
                'total_value' => round($stats->total_value ?? 0, 2),
                'movements_by_type' => $movementsByType,
                'recent_activity' => $recentActivity,
            ],
            'message' => 'Stock movement statistics retrieved successfully',
        ], 200);
    }

    /**
     * Display the specified stock movement.
     */
    public function show(Request $request, int $movementId): JsonResponse
    {
        $tenantId = $request->route('tenant_id');

        // For admin routes, no tenant filter; for tenant routes, filter by tenant_id
        $query = StockMovement::with(['product', 'warehouse', 'store', 'user', 'layer.batch', 'order']);
        
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $movement = $query->findOrFail($movementId);

        return response()->json([
            'success' => true,
            'data' => [
                'movement' => [
                    'id' => $movement->id,
                    'product' => $movement->product ? [
                        'id' => $movement->product->id,
                        'name' => $movement->product->name,
                        'sku' => $movement->product->sku,
                    ] : null,
                    'warehouse' => $movement->warehouse ? [
                        'id' => $movement->warehouse->id,
                        'name' => $movement->warehouse->name,
                        'code' => $movement->warehouse->code,
                    ] : null,
                    'store' => $movement->store ? [
                        'id' => $movement->store->id,
                        'name' => $movement->store->name,
                        'code' => $movement->store->code,
                    ] : null,
                    'user' => $movement->user ? [
                        'id' => $movement->user->id,
                        'name' => $movement->user->name,
                        'email' => $movement->user->email,
                    ] : null,
                    'layer' => $movement->layer ? [
                        'id' => $movement->layer->id,
                        'batch_number' => $movement->layer->batch?->batch_number,
                        'unit_cost' => $movement->layer->unit_cost,
                    ] : null,
                    'type' => $movement->type,
                    'quantity' => $movement->quantity,
                    'quantity_before' => $movement->quantity_before,
                    'quantity_after' => $movement->quantity_after,
                    'unit_cost' => $movement->unit_cost,
                    'total_cost' => $movement->total_cost,
                    'reason' => $movement->reason,
                    'reference' => $movement->reference,
                    'created_at' => $movement->created_at->toIso8601String(),
                    'updated_at' => $movement->updated_at->toIso8601String(),
                ],
            ],
            'message' => 'Stock movement retrieved successfully',
        ], 200);
    }

    /**
     * Export stock movements to CSV.
     */
    public function export(Request $request)
    {
        $tenantId = $request->route('tenant_id');

        $validated = $request->validate([
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'type' => ['nullable', 'string', 'in:in,out,adjustment,transfer_in,transfer_out,transfer,sale,return'],
            'date_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = StockMovement::with(['tenant', 'product', 'warehouse', 'store', 'user']);

        // Apply tenant filter
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } elseif (!empty($validated['tenant_id'])) {
            $query->where('tenant_id', $validated['tenant_id']);
        }

        // Apply same filters as index
        if (!empty($validated['product_id'])) {
            $query->where('product_id', $validated['product_id']);
        }

        if (!empty($validated['warehouse_id'])) {
            $query->where('warehouse_id', $validated['warehouse_id']);
        }

        if (!empty($validated['store_id'])) {
            $query->where('store_id', $validated['store_id']);
        }

        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (!empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (!empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', function ($productQuery) use ($search) {
                    $productQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                })
                ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        $movements = $query->orderBy('created_at', 'desc')->get();

        // Generate CSV
        $filename = 'stock-movements-' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($movements) {
            $file = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($file, [
                'ID',
                'Tenant',
                'Product Name',
                'SKU',
                'Warehouse',
                'Store',
                'Type',
                'Quantity',
                'Qty Before',
                'Qty After',
                'Unit Cost',
                'Total Cost',
                'Reason',
                'Reference',
                'User',
                'Date',
            ]);

            // CSV Data
            foreach ($movements as $movement) {
                fputcsv($file, [
                    $movement->id,
                    $movement->tenant?->name,
                    $movement->product?->name,
                    $movement->product?->sku,
                    $movement->warehouse?->name,
                    $movement->store?->name,
                    $movement->type,
                    $movement->quantity,
                    $movement->quantity_before,
                    $movement->quantity_after,
                    $movement->unit_cost,
                    $movement->total_cost,
                    $movement->reason,
                    $movement->reference,
                    $movement->user?->name,
                    $movement->created_at->toIso8601String(),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
