<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Models\WarehouseZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseZoneController extends Controller
{
    /**
     * List zones for a warehouse.
     */
    public function index(Request $request, int $warehouseId): JsonResponse
    {
        $validated = $request->validate([
            'active' => 'nullable|boolean',
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = WarehouseZone::forWarehouse($warehouseId)
            ->with('warehouse:id,name,code');

        if (isset($validated['active'])) {
            $query->where('active', $validated['active']);
        }

        if (isset($validated['search'])) {
            $query->search($validated['search']);
        }

        $perPage = $validated['per_page'] ?? 15;
        $zones = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $zones->map(fn($zone) => $this->formatZone($zone)),
            'meta' => [
                'pagination' => [
                    'current_page' => $zones->currentPage(),
                    'last_page' => $zones->lastPage(),
                    'per_page' => $zones->perPage(),
                    'total' => $zones->total(),
                ],
            ],
        ]);
    }

    /**
     * Create a new warehouse zone.
     */
    public function store(Request $request, int $warehouseId): JsonResponse
    {
        // Verify warehouse exists
        $warehouse = Warehouse::findOrFail($warehouseId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'capacity' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'active' => 'boolean',
        ]);

        // Check for duplicate code
        $existing = WarehouseZone::where('warehouse_id', $warehouseId)
            ->where('code', $validated['code'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Zone with this code already exists in the warehouse',
            ], 422);
        }

        $zone = WarehouseZone::create([
            'warehouse_id' => $warehouseId,
            'name' => $validated['name'],
            'code' => $validated['code'],
            'capacity' => $validated['capacity'] ?? null,
            'description' => $validated['description'] ?? null,
            'active' => $validated['active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatZone($zone),
            'message' => 'Warehouse zone created successfully',
        ], 201);
    }

    /**
     * Get a single warehouse zone.
     */
    public function show(Request $request, int $warehouseId, int $zoneId): JsonResponse
    {
        $zone = WarehouseZone::forWarehouse($warehouseId)
            ->with('warehouse:id,name,code')
            ->findOrFail($zoneId);

        return response()->json([
            'success' => true,
            'data' => array_merge($this->formatZone($zone), [
                'usage_stats' => $zone->getUsageStats(),
            ]),
        ]);
    }

    /**
     * Update a warehouse zone.
     */
    public function update(Request $request, int $warehouseId, int $zoneId): JsonResponse
    {
        $zone = WarehouseZone::forWarehouse($warehouseId)->findOrFail($zoneId);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('warehouse_zones')->where('warehouse_id', $warehouseId)->ignore($zoneId)],
            'capacity' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'active' => 'sometimes|boolean',
        ]);

        $zone->update($validated);

        return response()->json([
            'success' => true,
            'data' => $this->formatZone($zone->fresh()),
            'message' => 'Warehouse zone updated successfully',
        ]);
    }

    /**
     * Delete a warehouse zone.
     */
    public function destroy(Request $request, int $warehouseId, int $zoneId): JsonResponse
    {
        $zone = WarehouseZone::forWarehouse($warehouseId)->findOrFail($zoneId);
        $zone->delete();

        return response()->json([
            'success' => true,
            'message' => 'Warehouse zone deleted successfully',
        ]);
    }

    /**
     * Get zone usage statistics.
     */
    public function stats(Request $request, int $warehouseId): JsonResponse
    {
        $zones = WarehouseZone::forWarehouse($warehouseId)->get();

        $stats = [
            'total_zones' => $zones->count(),
            'active_zones' => $zones->where('active', true)->count(),
            'inactive_zones' => $zones->where('active', false)->count(),
            'zones_at_capacity' => $zones->filter(fn($z) => $z->isAtCapacity())->count(),
            'zones' => $zones->map(fn($zone) => $zone->getUsageStats()),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Format zone for JSON response.
     */
    protected function formatZone(WarehouseZone $zone): array
    {
        return [
            'id' => $zone->id,
            'warehouse_id' => $zone->warehouse_id,
            'warehouse' => $zone->warehouse ? [
                'id' => $zone->warehouse->id,
                'name' => $zone->warehouse->name,
                'code' => $zone->warehouse->code,
            ] : null,
            'name' => $zone->name,
            'code' => $zone->code,
            'capacity' => $zone->capacity,
            'description' => $zone->description,
            'active' => $zone->active,
            'created_at' => $zone->created_at->toIso8601String(),
            'updated_at' => $zone->updated_at->toIso8601String(),
        ];
    }
}
