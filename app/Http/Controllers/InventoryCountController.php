<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListInventoryCountsRequest;
use App\Http\Requests\RecordInventoryCountItemRequest;
use App\Http\Requests\StoreInventoryCountRequest;
use App\Models\Inventory;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryCountController extends Controller
{
    /**
     * List all inventory counts with filtering and pagination.
     */
    public function index(ListInventoryCountsRequest $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');

        $validated = $request->validated();

        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 20;

        $query = InventoryCount::with(['warehouse', 'store', 'startedBy', 'completedBy', 'approvedBy']);

        // Filter by tenant
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        } elseif (! empty($validated['tenant_id'])) {
            $query->where('tenant_id', $validated['tenant_id']);
        }

        // Filter by warehouse
        if (! empty($validated['warehouse_id'])) {
            $query->where('warehouse_id', $validated['warehouse_id']);
        }

        // Filter by store
        if (! empty($validated['store_id'])) {
            $query->where('store_id', $validated['store_id']);
        }

        // Filter by status
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        // Search
        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where('name', 'like', "%{$search}%");
        }

        // Sorting
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDirection = $validated['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        $counts = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => [
                'counts' => $counts->getCollection()->map(fn($count) => [
                    'id' => $count->id,
                    'tenant_id' => $count->tenant_id,
                    'name' => $count->name,
                    'description' => $count->description,
                    'status' => $count->status,
                    'warehouse' => $count->warehouse ? [
                        'id' => $count->warehouse->id,
                        'name' => $count->warehouse->name,
                    ] : null,
                    'store' => $count->store ? [
                        'id' => $count->store->id,
                        'name' => $count->store->name,
                    ] : null,
                    'started_by' => $count->startedBy?->name,
                    'completed_by' => $count->completedBy?->name,
                    'approved_by' => $count->approvedBy?->name,
                    'started_at' => $count->started_at?->toIso8601String(),
                    'completed_at' => $count->completed_at?->toIso8601String(),
                    'approved_at' => $count->approved_at?->toIso8601String(),
                    'summary' => $count->getSummary(),
                    'created_at' => $count->created_at->toIso8601String(),
                ]),
                'pagination' => [
                    'current_page' => $counts->currentPage(),
                    'per_page' => $counts->perPage(),
                    'total' => $counts->total(),
                    'last_page' => $counts->lastPage(),
                    'has_more' => $counts->hasMorePages(),
                ],
            ],
            'message' => 'Inventory counts retrieved successfully',
        ], 200);
    }

    /**
     * Create a new inventory count.
     */
    public function store(StoreInventoryCountRequest $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $validated = $request->validated();

        $count = DB::transaction(function () use ($tenantId, $validated) {
            $count = InventoryCount::create([
                'tenant_id' => $tenantId,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'store_id' => $validated['store_id'] ?? null,
                'status' => 'draft',
            ]);

            // Add products to count
            $productIds = $validated['product_ids'] ?? [];
            if (empty($productIds)) {
                // Get all products for the location
                $query = Product::where('tenant_id', $tenantId)
                    ->where('track_inventory', true);

                $warehouseId = $validated['warehouse_id'] ?? null;
                $storeId = $validated['store_id'] ?? null;

                if ($warehouseId) {
                    $query->whereHas('inventories', function ($q) use ($warehouseId) {
                        $q->where('warehouse_id', $warehouseId);
                    });
                }

                if ($storeId) {
                    $query->whereHas('inventories', function ($q) use ($storeId) {
                        $q->where('store_id', $storeId);
                    });
                }

                $productIds = $query->pluck('id')->toArray();
            }

            foreach ($productIds as $productId) {
                $warehouseId = $validated['warehouse_id'] ?? null;
                $storeId = $validated['store_id'] ?? null;

                $inventory = Inventory::where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->where(function ($q) use ($warehouseId, $storeId) {
                        if ($warehouseId) {
                            $q->where('warehouse_id', $warehouseId);
                        }
                        if ($storeId) {
                            $q->where('store_id', $storeId);
                        }
                    })
                    ->first();

                InventoryCountItem::create([
                    'count_id' => $count->id,
                    'product_id' => $productId,
                    'inventory_id' => $inventory?->id,
                    'expected_quantity' => $inventory?->available ?? 0,
                ]);
            }

            return $count;
        });

        return response()->json([
            'success' => true,
            'data' => ['count' => $count->load(['warehouse', 'store'])],
            'message' => 'Inventory count created successfully',
        ], 201);
    }

    /**
     * Get count details with items.
     */
    public function show(Request $request, int $countId): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasPermission('inventory.counts.manage') && ! $user->hasPermission('inventory.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You do not have permission to view inventory counts.',
            ], 403);
        }

        $tenantId = $request->route('tenant_id');

        $query = InventoryCount::with([
            'warehouse',
            'store',
            'startedBy',
            'completedBy',
            'approvedBy',
            'items.product',
            'items.inventory',
        ]);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $count = $query->findOrFail($countId);

        return response()->json([
            'success' => true,
            'data' => [
                'count' => [
                    'id' => $count->id,
                    'name' => $count->name,
                    'description' => $count->description,
                    'status' => $count->status,
                    'warehouse' => $count->warehouse,
                    'store' => $count->store,
                    'started_by' => $count->startedBy,
                    'completed_by' => $count->completedBy,
                    'approved_by' => $count->approvedBy,
                    'started_at' => $count->started_at?->toIso8601String(),
                    'completed_at' => $count->completed_at?->toIso8601String(),
                    'approved_at' => $count->approved_at?->toIso8601String(),
                    'notes' => $count->notes,
                    'summary' => $count->getSummary(),
                    'items' => $count->items->map(fn($item) => [
                        'id' => $item->id,
                        'product' => $item->product ? [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'sku' => $item->product->sku,
                        ] : null,
                        'expected_quantity' => $item->expected_quantity,
                        'counted_quantity' => $item->counted_quantity,
                        'variance' => $item->variance,
                        'notes' => $item->notes,
                    ]),
                ],
            ],
            'message' => 'Inventory count details retrieved successfully',
        ], 200);
    }

    /**
     * Start a count.
     */
    public function start(Request $request, int $countId): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasPermission('inventory.counts.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You do not have permission to start inventory counts.',
            ], 403);
        }

        $tenantId = $request->route('tenant_id');

        $count = InventoryCount::where('tenant_id', $tenantId)
            ->where('status', 'draft')
            ->findOrFail($countId);

        $count->start($user->id);

        return response()->json([
            'success' => true,
            'data' => ['count' => $count],
            'message' => 'Inventory count started',
        ], 200);
    }

    /**
     * Record count for a specific item.
     */
    public function recordItem(RecordInventoryCountItemRequest $request, int $countId, int $itemId): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $validated = $request->validated();

        $item = InventoryCountItem::whereHas('count', function ($q) use ($tenantId, $countId) {
            $q->where('tenant_id', $tenantId)->where('id', $countId);
        })->findOrFail($itemId);

        if ($item->count->status !== 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'Can only record counts when the inventory count is in progress.',
            ], 422);
        }

        $item->recordCount($validated['counted_quantity'], $validated['notes'] ?? null);

        return response()->json([
            'success' => true,
            'data' => ['item' => $item],
            'message' => 'Count recorded',
        ], 200);
    }

    /**
     * Complete a count.
     */
    public function complete(Request $request, int $countId): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasPermission('inventory.counts.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You do not have permission to complete inventory counts.',
            ], 403);
        }

        $tenantId = $request->route('tenant_id');

        $count = InventoryCount::where('tenant_id', $tenantId)
            ->where('status', 'in_progress')
            ->findOrFail($countId);

        // Verify all items have been counted
        $uncountedItems = $count->items()->whereNull('counted_quantity')->count();
        if ($uncountedItems > 0) {
            return response()->json([
                'success' => false,
                'message' => "{$uncountedItems} items have not been counted yet",
            ], 422);
        }

        $count->complete($user->id);

        return response()->json([
            'success' => true,
            'data' => ['count' => $count],
            'message' => 'Inventory count completed',
        ], 200);
    }

    /**
     * Approve a count and apply adjustments.
     */
    public function approve(Request $request, int $countId): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasPermission('inventory.counts.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You do not have permission to approve inventory counts.',
            ], 403);
        }

        $tenantId = $request->route('tenant_id');

        $count = InventoryCount::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->findOrFail($countId);

        $count->approve($user->id);

        return response()->json([
            'success' => true,
            'data' => ['count' => $count],
            'message' => 'Inventory count approved and adjustments applied',
        ], 200);
    }

    /**
     * Cancel a count.
     */
    public function cancel(Request $request, int $countId): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasPermission('inventory.counts.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You do not have permission to cancel inventory counts.',
            ], 403);
        }

        $tenantId = $request->route('tenant_id');

        $count = InventoryCount::where('tenant_id', $tenantId)
            ->whereIn('status', ['draft', 'in_progress'])
            ->findOrFail($countId);

        $count->cancel();

        return response()->json([
            'success' => true,
            'data' => ['count' => $count],
            'message' => 'Inventory count cancelled',
        ], 200);
    }

    /**
     * Delete a count (draft only).
     */
    public function destroy(Request $request, int $countId): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasPermission('inventory.counts.manage')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You do not have permission to delete inventory counts.',
            ], 403);
        }

        $tenantId = $request->route('tenant_id');

        $count = InventoryCount::where('tenant_id', $tenantId)
            ->where('status', 'draft')
            ->findOrFail($countId);

        $count->delete();

        return response()->json([
            'success' => true,
            'message' => 'Inventory count deleted',
        ], 200);
    }
}
