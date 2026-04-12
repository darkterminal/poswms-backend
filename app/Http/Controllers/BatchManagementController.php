<?php

namespace App\Http\Controllers;

use App\Models\InventoryBatch;
use App\Models\Scopes\TenantScope;
use App\Services\FifoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BatchManagementController extends Controller
{
    public function __construct(
        private FifoService $fifoService
    ) {}

    /**
     * List all inventory batches with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $isAdmin = !$tenantId;

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'status' => ['nullable', 'string', 'in:active,consumed,expired,cancelled'],
            'expiry_status' => ['nullable', 'string', 'in:expiring_soon,expired,all'],
            'days_until_expiry' => ['nullable', 'integer', 'min:1', 'max:365'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'string', 'in:received_date,expiry_date,remaining_quantity,unit_cost,created_at,product_name'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 20;

        $query = InventoryBatch::withoutGlobalScope(TenantScope::class)
            ->with(['product', 'warehouse', 'supplier', 'layers']);

        // Filter by tenant
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

        // Filter by status
        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        // Filter by expiry status
        if (!empty($validated['expiry_status'])) {
            if ($validated['expiry_status'] === 'expiring_soon') {
                $days = $validated['days_until_expiry'] ?? 30;
                $query->expiringSoon($days);
            } elseif ($validated['expiry_status'] === 'expired') {
                $query->expired();
            }
        }

        // Search by batch/lot number
        if (!empty($validated['search'])) {
            $search = str_replace(['%', '_'], ['\%', '\_'], $validated['search']);
            $query->where(function ($q) use ($search) {
                $q->where('batch_number', 'like', "%{$search}%")
                    ->orWhere('lot_number', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($productQuery) use ($search) {
                        $productQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        // Sorting
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDirection = $validated['sort_direction'] ?? 'desc';

        if ($sortBy === 'product_name') {
            $query->orderBy(
                DB::raw('(SELECT name FROM products WHERE products.id = inventory_batches.product_id)'),
                $sortDirection
            );
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        $batches = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => [
                'batches' => $batches->getCollection()->map(fn($batch) => [
                    'id' => $batch->id,
                    'tenant_id' => $batch->tenant_id,
                    'tenant_name' => $batch->tenant?->name ?? null,
                    'product' => $batch->product ? [
                        'id' => $batch->product->id,
                        'name' => $batch->product->name,
                        'sku' => $batch->product->sku,
                    ] : null,
                    'warehouse' => $batch->warehouse ? [
                        'id' => $batch->warehouse->id,
                        'name' => $batch->warehouse->name,
                        'code' => $batch->warehouse->code,
                    ] : null,
                    'batch_number' => $batch->batch_number,
                    'lot_number' => $batch->lot_number,
                    'received_date' => $batch->received_date?->toDateString(),
                    'expiry_date' => $batch->expiry_date?->toDateString(),
                    'unit_cost' => $batch->unit_cost,
                    'initial_quantity' => $batch->initial_quantity,
                    'remaining_quantity' => $batch->remaining_quantity,
                    'status' => $batch->status,
                    'is_expired' => $batch->isExpired(),
                    'is_expiring_soon' => $batch->isExpiringSoon(),
                    'days_until_expiry' => $batch->daysUntilExpiry(),
                    'total_value' => round($batch->remaining_quantity * $batch->unit_cost, 2),
                    'notes' => $batch->notes,
                    'created_at' => $batch->created_at->toIso8601String(),
                    'updated_at' => $batch->updated_at->toIso8601String(),
                ]),
                'pagination' => [
                    'current_page' => $batches->currentPage(),
                    'per_page' => $batches->perPage(),
                    'total' => $batches->total(),
                    'last_page' => $batches->lastPage(),
                    'has_more' => $batches->hasMorePages(),
                ],
            ],
            'message' => 'Inventory batches retrieved successfully',
        ], 200);
    }

    /**
     * Get batch statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');

        $query = InventoryBatch::withoutGlobalScope(TenantScope::class);
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $totalBatches = (clone $query)->count();
        $activeBatches = (clone $query)->where('status', 'active')->count();
        $expiredBatches = (clone $query)->where('status', 'expired')->count();
        $expiringSoon = (clone $query)->expiringSoon(30)->count();
        $totalValue = (clone $query)->where('status', 'active')
            ->selectRaw('SUM(remaining_quantity * unit_cost) as total')
            ->value('total') ?? 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_batches' => $totalBatches,
                'active_batches' => $activeBatches,
                'expired_batches' => $expiredBatches,
                'expiring_soon_30_days' => $expiringSoon,
                'total_value' => round($totalValue, 2),
            ],
            'message' => 'Batch statistics retrieved successfully',
        ], 200);
    }

    /**
     * Get batch details with layers.
     */
    public function show(Request $request, int $batchId): JsonResponse
    {
        $tenantId = $request->route('tenant_id');

        $query = InventoryBatch::withoutGlobalScope(TenantScope::class)
            ->with(['product', 'warehouse', 'supplier', 'layers']);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $batch = $query->findOrFail($batchId);

        return response()->json([
            'success' => true,
            'data' => [
                'batch' => [
                    'id' => $batch->id,
                    'tenant_id' => $batch->tenant_id,
                    'product' => $batch->product ? [
                        'id' => $batch->product->id,
                        'name' => $batch->product->name,
                        'sku' => $batch->product->sku,
                    ] : null,
                    'warehouse' => $batch->warehouse ? [
                        'id' => $batch->warehouse->id,
                        'name' => $batch->warehouse->name,
                        'code' => $batch->warehouse->code,
                    ] : null,
                    'batch_number' => $batch->batch_number,
                    'lot_number' => $batch->lot_number,
                    'received_date' => $batch->received_date?->toDateString(),
                    'expiry_date' => $batch->expiry_date?->toDateString(),
                    'unit_cost' => $batch->unit_cost,
                    'initial_quantity' => $batch->initial_quantity,
                    'remaining_quantity' => $batch->remaining_quantity,
                    'status' => $batch->status,
                    'is_expired' => $batch->isExpired(),
                    'is_expiring_soon' => $batch->isExpiringSoon(),
                    'days_until_expiry' => $batch->daysUntilExpiry(),
                    'total_value' => round($batch->remaining_quantity * $batch->unit_cost, 2),
                    'notes' => $batch->notes,
                    'metadata' => $batch->metadata,
                    'layers' => $batch->layers->map(fn($layer) => [
                        'id' => $layer->id,
                        'quantity' => $layer->quantity,
                        'available' => $layer->available,
                        'reserved' => $layer->reserved,
                        'unit_cost' => $layer->unit_cost,
                        'total_cost' => $layer->total_cost,
                        'layer_order' => $layer->layer_order,
                    ]),
                    'created_at' => $batch->created_at->toIso8601String(),
                    'updated_at' => $batch->updated_at->toIso8601String(),
                ],
            ],
            'message' => 'Batch details retrieved successfully',
        ], 200);
    }

    /**
     * Get expiring batches alert.
     */
    public function expiringBatches(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $days = $request->query('days', 30);

        // For super admin, get expiring batches across all tenants or a specific tenant
        if (!$tenantId) {
            // Super admin: get expiring batches for a specific tenant if provided
            $filterTenantId = $request->query('tenant_id');
            if ($filterTenantId) {
                $expiring = $this->fifoService->getExpiringBatches($filterTenantId, $days);
            } else {
                // Return summary across all tenants
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant ID is required for expiring batches',
                ], 400);
            }
        } else {
            $expiring = $this->fifoService->getExpiringBatches($tenantId, $days);
        }

        return response()->json([
            'success' => true,
            'data' => $expiring,
            'message' => 'Expiring batches retrieved successfully',
        ], 200);
    }

    /**
     * Expire a batch manually.
     */
    public function expire(Request $request, int $batchId): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // For super admin, don't filter by tenant_id
        $query = InventoryBatch::withoutGlobalScope(TenantScope::class);
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $batch = $query->lockForUpdate()->findOrFail($batchId);

        if ($batch->status === 'expired') {
            return response()->json([
                'success' => false,
                'message' => 'Batch is already expired',
            ], 422);
        }

        try {
            $this->fifoService->expireBatch($batch, $validated['reason'] ?? 'Manual expiry');

            return response()->json([
                'success' => true,
                'message' => 'Batch expired successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Export batches to CSV.
     */
    public function export(Request $request)
    {
        $tenantId = $request->route('tenant_id');

        $validated = $request->validate([
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'status' => ['nullable', 'string', 'in:active,consumed,expired,cancelled'],
            'expiry_status' => ['nullable', 'string', 'in:expiring_soon,expired,all'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $query = InventoryBatch::withoutGlobalScope(TenantScope::class)
            ->with(['product', 'warehouse']);

        // Filter by tenant: route param (tenant-scoped) or query param (super admin)
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } elseif (!empty($validated['tenant_id'])) {
            $query->where('tenant_id', $validated['tenant_id']);
        }

        if (!empty($validated['product_id'])) {
            $query->where('product_id', $validated['product_id']);
        }

        if (!empty($validated['warehouse_id'])) {
            $query->where('warehouse_id', $validated['warehouse_id']);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['search'])) {
            $search = str_replace(['%', '_'], ['\%', '\_'], $validated['search']);
            $query->where(function ($q) use ($search) {
                $q->where('batch_number', 'like', "%{$search}%")
                    ->orWhere('lot_number', 'like', "%{$search}%");
            });
        }

        $batches = $query->orderBy('created_at', 'desc')->limit(10000)->get();

        $filename = 'inventory-batches-' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($batches) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'ID',
                'Batch Number',
                'Lot Number',
                'Product',
                'SKU',
                'Warehouse',
                'Received Date',
                'Expiry Date',
                'Days Until Expiry',
                'Initial Qty',
                'Remaining Qty',
                'Unit Cost',
                'Total Value',
                'Status',
                'Notes',
            ]);

            foreach ($batches as $batch) {
                fputcsv($file, [
                    $batch->id,
                    $batch->batch_number,
                    $batch->lot_number,
                    $batch->product?->name,
                    $batch->product?->sku,
                    $batch->warehouse?->name,
                    $batch->received_date?->toDateString(),
                    $batch->expiry_date?->toDateString(),
                    $batch->daysUntilExpiry() ?? 'N/A',
                    $batch->initial_quantity,
                    $batch->remaining_quantity,
                    $batch->unit_cost,
                    round($batch->remaining_quantity * $batch->unit_cost, 2),
                    $batch->status,
                    $batch->notes,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
