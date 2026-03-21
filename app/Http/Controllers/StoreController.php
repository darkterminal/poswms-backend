<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $stores = Store::where('tenant_id', $request->route('tenant_id'))
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['stores' => $stores],
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
            'settings' => 'array|nullable',
            'active' => 'boolean',
        ]);

        $validated['tenant_id'] = $request->route('tenant_id');
        $validated['active'] = $validated['active'] ?? true;

        $store = Store::create($validated);

        return response()->json([
            'success' => true,
            'data' => ['store' => $store],
            'message' => 'Store created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $storeId = $request->route('storeId');

        $store = Store::where('tenant_id', $tenantId)->findOrFail($storeId);

        return response()->json([
            'success' => true,
            'data' => ['store' => $store],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $storeId = $request->route('storeId');

        $store = Store::where('tenant_id', $tenantId)->findOrFail($storeId);

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
            'settings' => 'array|nullable',
            'active' => 'boolean',
        ]);

        $store->update($validated);

        return response()->json([
            'success' => true,
            'data' => ['store' => $store],
            'message' => 'Store updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $storeId = $request->route('storeId');

        $store = Store::where('tenant_id', $tenantId)->findOrFail($storeId);

        $store->delete();

        return response()->json([
            'success' => true,
            'message' => 'Store deleted successfully',
        ]);
    }
}
