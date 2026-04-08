<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminWarehouseController extends Controller
{
    /**
     * List warehouses across all tenants with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'sort_by' => ['nullable', 'string', 'in:name,code,city,state,country,created_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 20;

        $query = Warehouse::with(['tenant'])
            ->select('warehouses.*');

        // Filter by tenant
        if (! empty($validated['tenant_id'])) {
            $query->where('tenant_id', $validated['tenant_id']);
        }

        // Search by name, code, city, state, or country
        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('state', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if (! empty($validated['status'])) {
            $query->where('active', $validated['status'] === 'active');
        }

        // Sorting
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDirection = $validated['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        $warehouses = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => [
                'warehouses' => $warehouses->getCollection()->map(fn($warehouse) => [
                    'id' => $warehouse->id,
                    'tenant_id' => $warehouse->tenant_id,
                    'tenant_name' => $warehouse->tenant?->name,
                    'name' => $warehouse->name,
                    'code' => $warehouse->code,
                    'address' => $warehouse->address,
                    'city' => $warehouse->city,
                    'state' => $warehouse->state,
                    'country' => $warehouse->country,
                    'postal_code' => $warehouse->postal_code,
                    'phone' => $warehouse->phone,
                    'email' => $warehouse->email,
                    'active' => $warehouse->active,
                    'created_at' => $warehouse->created_at?->toISOString(),
                    'updated_at' => $warehouse->updated_at?->toISOString(),
                ]),
                'pagination' => [
                    'current_page' => $warehouses->currentPage(),
                    'per_page' => $warehouses->perPage(),
                    'total' => $warehouses->total(),
                    'last_page' => $warehouses->lastPage(),
                    'has_more' => $warehouses->hasMorePages(),
                ],
            ],
        ]);
    }

    /**
     * Get warehouse statistics across all tenants.
     */
    public function stats(): JsonResponse
    {
        $totalWarehouses = Warehouse::count();
        $activeWarehouses = Warehouse::where('active', true)->count();
        $inactiveWarehouses = $totalWarehouses - $activeWarehouses;
        $tenantsWithWarehouses = Warehouse::distinct()->count('tenant_id');

        $topLocations = Warehouse::selectRaw('country, state, city, COUNT(*) as count')
            ->groupBy('country', 'state', 'city')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn($location) => [
                'location' => trim(implode(', ', array_filter([$location->city, $location->state, $location->country]))),
                'count' => $location->count,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'total_warehouses' => $totalWarehouses,
                'active_warehouses' => $activeWarehouses,
                'inactive_warehouses' => $inactiveWarehouses,
                'tenants_with_warehouses' => $tenantsWithWarehouses,
                'top_locations' => $topLocations,
            ],
        ]);
    }

    /**
     * Create a new warehouse.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', 'unique:warehouses,code'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'settings' => ['nullable', 'array'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $validated['active'] = $validated['active'] ?? true;

        $warehouse = Warehouse::create($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'warehouse' => [
                    'id' => $warehouse->id,
                    'tenant_id' => $warehouse->tenant_id,
                    'tenant_name' => $warehouse->tenant?->name,
                    'name' => $warehouse->name,
                    'code' => $warehouse->code,
                    'address' => $warehouse->address,
                    'city' => $warehouse->city,
                    'state' => $warehouse->state,
                    'country' => $warehouse->country,
                    'postal_code' => $warehouse->postal_code,
                    'phone' => $warehouse->phone,
                    'email' => $warehouse->email,
                    'active' => $warehouse->active,
                    'created_at' => $warehouse->created_at?->toISOString(),
                    'updated_at' => $warehouse->updated_at?->toISOString(),
                ],
            ],
            'message' => 'Warehouse created successfully',
        ], 201);
    }

    /**
     * Get a single warehouse details with inventory summary.
     */
    public function show(int $warehouseId): JsonResponse
    {
        $warehouse = Warehouse::with(['tenant'])->findOrFail($warehouseId);

        $inventorySummary = Warehouse::where('warehouses.id', $warehouseId)
            ->leftJoin('inventories', 'warehouses.id', '=', 'inventories.warehouse_id')
            ->selectRaw(
                'SUM(inventories.quantity) as total_quantity, SUM(inventories.reserved) as total_reserved, SUM(inventories.quantity - inventories.reserved) as total_available'
            )
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'warehouse' => [
                    'id' => $warehouse->id,
                    'tenant_id' => $warehouse->tenant_id,
                    'tenant_name' => $warehouse->tenant?->name,
                    'name' => $warehouse->name,
                    'code' => $warehouse->code,
                    'address' => $warehouse->address,
                    'city' => $warehouse->city,
                    'state' => $warehouse->state,
                    'country' => $warehouse->country,
                    'postal_code' => $warehouse->postal_code,
                    'phone' => $warehouse->phone,
                    'email' => $warehouse->email,
                    'latitude' => $warehouse->latitude,
                    'longitude' => $warehouse->longitude,
                    'settings' => $warehouse->settings,
                    'active' => $warehouse->active,
                    'total_quantity' => (int) ($inventorySummary->total_quantity ?? 0),
                    'total_reserved' => (int) ($inventorySummary->total_reserved ?? 0),
                    'total_available' => (int) ($inventorySummary->total_available ?? 0),
                    'created_at' => $warehouse->created_at?->toISOString(),
                    'updated_at' => $warehouse->updated_at?->toISOString(),
                ],
            ],
        ]);
    }

    /**
     * Update warehouse details.
     */
    public function update(Request $request, int $warehouseId): JsonResponse
    {
        $warehouse = Warehouse::findOrFail($warehouseId);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:100', 'unique:warehouses,code,' . $warehouseId],
            'address' => ['nullable', 'string'],
            'city' => ['sometimes', 'string', 'max:255'],
            'state' => ['sometimes', 'string', 'max:255'],
            'country' => ['sometimes', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'string', 'max:50'],
            'phone' => ['sometimes', 'string', 'max:50'],
            'email' => ['sometimes', 'email', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'settings' => ['nullable', 'array'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $warehouse->update($validated);
        $warehouse->load('tenant');

        return response()->json([
            'success' => true,
            'data' => [
                'warehouse' => [
                    'id' => $warehouse->id,
                    'tenant_id' => $warehouse->tenant_id,
                    'tenant_name' => $warehouse->tenant?->name,
                    'name' => $warehouse->name,
                    'code' => $warehouse->code,
                    'address' => $warehouse->address,
                    'city' => $warehouse->city,
                    'state' => $warehouse->state,
                    'country' => $warehouse->country,
                    'postal_code' => $warehouse->postal_code,
                    'phone' => $warehouse->phone,
                    'email' => $warehouse->email,
                    'active' => $warehouse->active,
                    'created_at' => $warehouse->created_at?->toISOString(),
                    'updated_at' => $warehouse->updated_at?->toISOString(),
                ],
            ],
            'message' => 'Warehouse updated successfully',
        ]);
    }

    /**
     * Toggle warehouse active/inactive status.
     */
    public function toggleStatus(int $warehouseId): JsonResponse
    {
        $warehouse = Warehouse::findOrFail($warehouseId);
        $warehouse->update(['active' => ! $warehouse->active]);
        $warehouse->load('tenant');

        return response()->json([
            'success' => true,
            'data' => [
                'warehouse' => [
                    'id' => $warehouse->id,
                    'tenant_id' => $warehouse->tenant_id,
                    'tenant_name' => $warehouse->tenant?->name,
                    'name' => $warehouse->name,
                    'code' => $warehouse->code,
                    'address' => $warehouse->address,
                    'city' => $warehouse->city,
                    'state' => $warehouse->state,
                    'country' => $warehouse->country,
                    'postal_code' => $warehouse->postal_code,
                    'phone' => $warehouse->phone,
                    'email' => $warehouse->email,
                    'active' => $warehouse->active,
                    'created_at' => $warehouse->created_at?->toISOString(),
                    'updated_at' => $warehouse->updated_at?->toISOString(),
                ],
            ],
            'message' => 'Warehouse status updated successfully',
        ]);
    }

    /**
     * Delete a warehouse (soft delete).
     */
    public function destroy(int $warehouseId): JsonResponse
    {
        $warehouse = Warehouse::findOrFail($warehouseId);

        // Check if warehouse has inventory
        if ($warehouse->inventories()->where('quantity', '>', 0)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete warehouse with existing inventory. Transfer or remove inventory first.',
            ], 422);
        }

        // Check if warehouse has assigned users
        if (User::where('warehouse_id', $warehouseId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete warehouse with assigned users. Reassign users first.',
            ], 422);
        }

        // Check if warehouse has associated orders
        if (Order::where('warehouse_id', $warehouseId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete warehouse with associated orders. Archive or reassign orders first.',
            ], 422);
        }

        $warehouse->delete();

        return response()->json([
            'success' => true,
            'message' => 'Warehouse deleted successfully',
        ]);
    }

    /**
     * Export warehouses to CSV.
     */
    public function export(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'sort_by' => ['nullable', 'string', 'in:name,code,city,state,country,created_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $query = Warehouse::with(['tenant'])
            ->select('warehouses.*');

        // Apply same filters as index
        if (! empty($validated['tenant_id'])) {
            $query->where('tenant_id', $validated['tenant_id']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('state', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['status'])) {
            $query->where('active', $validated['status'] === 'active');
        }

        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDirection = $validated['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        $warehouses = $query->get();

        return response()->json([
            'success' => true,
            'data' => [
                'warehouses' => $warehouses->map(fn($warehouse) => [
                    'id' => $warehouse->id,
                    'tenant_id' => $warehouse->tenant_id,
                    'tenant_name' => $warehouse->tenant?->name,
                    'name' => $warehouse->name,
                    'code' => $warehouse->code,
                    'address' => $warehouse->address,
                    'city' => $warehouse->city,
                    'state' => $warehouse->state,
                    'country' => $warehouse->country,
                    'postal_code' => $warehouse->postal_code,
                    'phone' => $warehouse->phone,
                    'email' => $warehouse->email,
                    'active' => $warehouse->active,
                    'created_at' => $warehouse->created_at?->toISOString(),
                    'updated_at' => $warehouse->updated_at?->toISOString(),
                ]),
            ],
        ]);
    }
}
