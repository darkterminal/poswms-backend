<?php

namespace App\Http\Controllers;

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
        $inventories = Inventory::where('tenant_id', $request->route('tenant_id'))
            ->with(['product', 'warehouse', 'store'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['inventories' => $inventories],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'store_id' => 'nullable|exists:stores,id',
            'quantity' => 'required|integer|min:0',
            'reserved' => 'integer|min:0',
            'available' => 'integer|min:0',
            'cost' => 'numeric|min:0',
            'location' => 'string|max:255',
            'notes' => 'string|nullable',
        ]);

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
            ->findOrFail($inventoryId);

        return response()->json([
            'success' => true,
            'data' => ['inventory' => $inventory],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $inventoryId = $request->route('inventoryId');

        $inventory = Inventory::where('tenant_id', $tenantId)
            ->findOrFail($inventoryId);

        $validated = $request->validate([
            'product_id' => 'sometimes|exists:products,id',
            'warehouse_id' => 'sometimes|exists:warehouses,id',
            'store_id' => 'sometimes|exists:stores,id',
            'quantity' => 'sometimes|integer|min:0',
            'reserved' => 'sometimes|integer|min:0',
            'available' => 'sometimes|integer|min:0',
            'cost' => 'sometimes|numeric|min:0',
            'location' => 'sometimes|string|max:255',
            'notes' => 'string|nullable',
        ]);

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
