<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryRequest;
use App\Http\Requests\UpdateInventoryRequest;
use App\Models\Inventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');

        $query = Inventory::where('tenant_id', $tenantId)
            ->with(['product', 'warehouse', 'store']);

        // Filter by warehouse
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Filter by store
        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        // Filter by product
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Paginate results
        $perPage = $request->get('per_page', 15);
        $inventories = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'inventories' => $inventories->items(),
                'pagination' => [
                    'current_page' => $inventories->currentPage(),
                    'per_page' => $inventories->perPage(),
                    'total' => $inventories->total(),
                    'last_page' => $inventories->lastPage(),
                    'has_more' => $inventories->hasMorePages(),
                ],
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInventoryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['tenant_id'] = $request->route('tenant_id');
        $validated['reserved'] = $validated['reserved'] ?? 0;
        $validated['available'] = $validated['available'] ?? $validated['quantity'];

        $inventory = Inventory::create($validated);

        return response()->json([
            'success' => true,
            'data' => ['inventory' => $inventory],
            'message' => 'Inventory created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $inventoryId = $request->route('inventoryId');

        $inventory = Inventory::where('tenant_id', $tenantId)
            ->with(['product', 'warehouse', 'store'])
            ->findOrFail($inventoryId);

        // Build FIFO layers data
        $fifoLayers = null;
        $layers = $inventory->layers()->with('batch')->orderBy('id')->get();
        if ($layers->isNotEmpty()) {
            $totalQuantity = $layers->sum('quantity');
            $totalAvailable = $layers->sum('available');
            $totalReserved = $layers->sum('reserved');
            $totalValue = $layers->sum(fn ($l) => $l->quantity * $l->unit_cost);
            $weightedAvgCost = $totalQuantity > 0 ? $totalValue / $totalQuantity : 0;

            $fifoLayers = [
                'total_quantity' => $totalQuantity,
                'total_available' => $totalAvailable,
                'total_reserved' => $totalReserved,
                'total_value' => round($totalValue, 2),
                'weighted_average_cost' => round($weightedAvgCost, 2),
                'layers' => $layers->map(fn ($l) => [
                    'layer_id' => $l->id,
                    'quantity' => $l->quantity,
                    'available' => $l->available,
                    'reserved' => $l->reserved,
                    'unit_cost' => $l->unit_cost,
                    'total_cost' => round($l->quantity * $l->unit_cost, 2),
                    'batch_number' => $l->batch?->batch_number,
                    'received_date' => $l->batch?->received_date?->toDateString(),
                    'expiry_date' => $l->batch?->expiry_date?->toDateString(),
                ])->toArray(),
            ];
        }

        $result = $inventory->toArray();
        $result['fifo_layers'] = $fifoLayers;

        return response()->json([
            'success' => true,
            'data' => ['inventory' => $result],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInventoryRequest $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $inventoryId = $request->route('inventoryId');

        $inventory = Inventory::where('tenant_id', $tenantId)
            ->findOrFail($inventoryId);

        $validated = $request->validated();

        $inventory->update($validated);

        return response()->json([
            'success' => true,
            'data' => ['inventory' => $inventory],
            'message' => 'Inventory updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $inventoryId = $request->route('inventoryId');

        $inventory = Inventory::where('tenant_id', $tenantId)
            ->findOrFail($inventoryId);

        $inventory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Inventory deleted successfully',
        ]);
    }
}
