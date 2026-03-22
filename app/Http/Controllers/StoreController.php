<?php

namespace App\Http\Controllers;

use App\Http\Requests\Store\CreateStoreRequest;
use App\Http\Requests\Store\UpdateStoreRequest;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    /**
     * Display a listing of stores for the tenant.
     *
     * @param  Request  $request  The HTTP request containing tenant_id route parameter
     * @return JsonResponse JSON response containing array of stores
     *
     * @security Bearer token required
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "stores": [Store...]
     *   }
     * }
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
     * Store a newly created store in storage.
     *
     * @param  CreateStoreRequest  $request  Form request with validated store data
     * @return JsonResponse JSON response containing the created store
     *
     * @security Bearer token required
     *
     * @requestBody {
     *   "name": "string (required, max: 255)",
     *   "code": "string (required, max: 100)",
     *   "address": "string (optional)",
     *   "city": "string (optional, max: 255)",
     *   "state": "string (optional, max: 255)",
     *   "country": "string (optional, max: 255)",
     *   "postal_code": "string (optional, max: 50)",
     *   "phone": "string (optional, max: 50)",
     *   "email": "string (optional, email, max: 255)",
     *   "settings": "object (optional)",
     *   "active": "boolean (optional, default: true)"
     * }
     *
     * @response 201 {
     *   "success": true,
     *   "data": {
     *     "store": Store
     *   },
     *   "message": "Store created successfully"
     * }
     * @response 422 {
     *   "message": "Validation errors",
     *   "errors": {
     *     "field": ["error message"]
     *   }
     * }
     */
    public function store(CreateStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
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
     * Display the specified store.
     *
     * @param  Request  $request  The HTTP request containing tenant_id and storeId route parameters
     * @return JsonResponse JSON response containing the store
     *
     * @security Bearer token required
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "store": Store
     *   }
     * }
     * @response 404 {
     *   "message": "Store not found"
     * }
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
     * Update the specified store in storage.
     *
     * @param  UpdateStoreRequest  $request  Form request with validated store data
     * @return JsonResponse JSON response containing the updated store
     *
     * @security Bearer token required
     *
     * @requestBody {
     *   "name": "string (optional, max: 255)",
     *   "code": "string (optional, max: 100)",
     *   "address": "string (optional)",
     *   "city": "string (optional, max: 255)",
     *   "state": "string (optional, max: 255)",
     *   "country": "string (optional, max: 255)",
     *   "postal_code": "string (optional, max: 50)",
     *   "phone": "string (optional, max: 50)",
     *   "email": "string (optional, email, max: 255)",
     *   "settings": "object (optional)",
     *   "active": "boolean (optional)"
     * }
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "store": Store
     *   },
     *   "message": "Store updated successfully"
     * }
     * @response 404 {
     *   "message": "Store not found"
     * }
     * @response 422 {
     *   "message": "Validation errors",
     *   "errors": {
     *     "field": ["error message"]
     *   }
     * }
     */
    public function update(UpdateStoreRequest $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $storeId = $request->route('storeId');

        $store = Store::where('tenant_id', $tenantId)->findOrFail($storeId);

        $store->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => ['store' => $store],
            'message' => 'Store updated successfully',
        ]);
    }

    /**
     * Remove the specified store from storage (soft delete).
     *
     * @param  Request  $request  The HTTP request containing tenant_id and storeId route parameters
     * @return JsonResponse JSON response confirming deletion
     *
     * @security Bearer token required
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Store deleted successfully"
     * }
     * @response 404 {
     *   "message": "Store not found"
     * }
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
