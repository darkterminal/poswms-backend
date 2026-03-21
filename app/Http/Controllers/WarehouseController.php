<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $warehouses = Warehouse::where('tenant_id', $request->route('tenant_id'))
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['warehouses' => $warehouses],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100',
            'address' => 'string|nullable',
            'city' => 'string|max:255',
            'state' => 'string|max:255',
            'country' => 'string|max:255',
            'postal_code' => 'string|max:50',
            'phone' => 'string|max:50',
            'email' => 'email|max:255',
            'latitude' => 'numeric|nullable',
            'longitude' => 'numeric|nullable',
            'settings' => 'array|nullable',
            'active' => 'boolean',
        ]);

        $validated['tenant_id'] = $request->route('tenant_id');
        $validated['active'] = $validated['active'] ?? true;

        $warehouse = Warehouse::create($validated);

        return response()->json([
            'success' => true,
            'data' => ['warehouse' => $warehouse],
            'message' => 'Warehouse created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $warehouseId = $request->route('warehouseId');

        $warehouse = Warehouse::where('tenant_id', $tenantId)->findOrFail($warehouseId);

        return response()->json([
            'success' => true,
            'data' => ['warehouse' => $warehouse],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $warehouseId = $request->route('warehouseId');

        $warehouse = Warehouse::where('tenant_id', $tenantId)->findOrFail($warehouseId);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:100',
            'address' => 'string|nullable',
            'city' => 'string|max:255',
            'state' => 'string|max:255',
            'country' => 'string|max:255',
            'postal_code' => 'string|max:50',
            'phone' => 'string|max:50',
            'email' => 'email|max:255',
            'latitude' => 'numeric|nullable',
            'longitude' => 'numeric|nullable',
            'settings' => 'array|nullable',
            'active' => 'boolean',
        ]);

        $warehouse->update($validated);

        return response()->json([
            'success' => true,
            'data' => ['warehouse' => $warehouse],
            'message' => 'Warehouse updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $warehouseId = $request->route('warehouseId');

        $warehouse = Warehouse::where('tenant_id', $tenantId)->findOrFail($warehouseId);

        $warehouse->delete();

        return response()->json([
            'success' => true,
            'message' => 'Warehouse deleted successfully',
        ]);
    }
}
