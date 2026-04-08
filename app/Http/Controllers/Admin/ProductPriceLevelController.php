<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPriceLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductPriceLevelController extends Controller
{
    /**
     * List price levels for a product.
     */
    public function index($productId): JsonResponse
    {
        // Bypass tenant scope for super admin
        $product = Product::withoutGlobalScopes()->findOrFail($productId);

        $priceLevels = ProductPriceLevel::withoutGlobalScopes()
            ->where('product_id', $productId)
            ->orderBy('level_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'price_levels' => $priceLevels->map(fn($level) => [
                    'id' => $level->id,
                    'product_id' => $level->product_id,
                    'level_name' => $level->level_name,
                    'level_order' => $level->level_order,
                    'unit_size' => $level->unit_size,
                    'price' => $level->price,
                    'cost' => $level->cost,
                    'barcode' => $level->barcode,
                    'active' => $level->active,
                    'price_per_base_unit' => $level->getPricePerBaseUnit(),
                    'created_at' => $level->created_at->toIso8601String(),
                    'updated_at' => $level->updated_at->toIso8601String(),
                ]),
            ],
            'message' => 'Price levels retrieved successfully',
        ], 200);
    }

    /**
     * Create a new price level.
     */
    public function store(Request $request, $productId): JsonResponse
    {
        // Bypass tenant scope for super admin
        $product = Product::withoutGlobalScopes()->findOrFail($productId);

        $validated = $request->validate([
            'level_name' => 'required|string|max:100',
            'level_order' => 'required|integer|min:1',
            'unit_size' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'barcode' => 'nullable|string|max:100',
            'active' => 'nullable|boolean',
        ]);

        $validated['product_id'] = $productId;
        $validated['tenant_id'] = $product->tenant_id;
        $validated['active'] = $validated['active'] ?? true;

        $priceLevel = ProductPriceLevel::create($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'price_level' => [
                    'id' => $priceLevel->id,
                    'product_id' => $priceLevel->product_id,
                    'level_name' => $priceLevel->level_name,
                    'level_order' => $priceLevel->level_order,
                    'unit_size' => $priceLevel->unit_size,
                    'price' => $priceLevel->price,
                    'cost' => $priceLevel->cost,
                    'barcode' => $priceLevel->barcode,
                    'active' => $priceLevel->active,
                    'price_per_base_unit' => $priceLevel->getPricePerBaseUnit(),
                    'created_at' => $priceLevel->created_at->toIso8601String(),
                    'updated_at' => $priceLevel->updated_at->toIso8601String(),
                ],
            ],
            'message' => 'Price level created successfully',
        ], 201);
    }

    /**
     * Update a price level.
     */
    public function update(Request $request, $productId, $levelId): JsonResponse
    {
        // Bypass tenant scope for super admin
        $priceLevel = ProductPriceLevel::withoutGlobalScopes()
            ->where('product_id', $productId)
            ->findOrFail($levelId);

        $validated = $request->validate([
            'level_name' => 'sometimes|string|max:100',
            'level_order' => 'sometimes|integer|min:1',
            'unit_size' => 'sometimes|integer|min:1',
            'price' => 'sometimes|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'barcode' => 'nullable|string|max:100',
            'active' => 'nullable|boolean',
        ]);

        $priceLevel->update($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'price_level' => [
                    'id' => $priceLevel->id,
                    'product_id' => $priceLevel->product_id,
                    'level_name' => $priceLevel->level_name,
                    'level_order' => $priceLevel->level_order,
                    'unit_size' => $priceLevel->unit_size,
                    'price' => $priceLevel->price,
                    'cost' => $priceLevel->cost,
                    'barcode' => $priceLevel->barcode,
                    'active' => $priceLevel->active,
                    'price_per_base_unit' => $priceLevel->getPricePerBaseUnit(),
                    'created_at' => $priceLevel->created_at->toIso8601String(),
                    'updated_at' => $priceLevel->updated_at->toIso8601String(),
                ],
            ],
            'message' => 'Price level updated successfully',
        ], 200);
    }

    /**
     * Delete a price level.
     */
    public function destroy($productId, $levelId): JsonResponse
    {
        // Bypass tenant scope for super admin
        $priceLevel = ProductPriceLevel::withoutGlobalScopes()
            ->where('product_id', $productId)
            ->findOrFail($levelId);

        $priceLevel->delete();

        return response()->json([
            'success' => true,
            'message' => 'Price level deleted successfully',
        ], 200);
    }

    /**
     * Bulk update price levels (reorder).
     */
    public function bulkUpdate(Request $request, $productId): JsonResponse
    {
        // Bypass tenant scope for super admin
        $product = Product::withoutGlobalScopes()->findOrFail($productId);

        $validated = $request->validate([
            'price_levels' => 'required|array',
            'price_levels.*.id' => 'required|integer',
            'price_levels.*.level_order' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            foreach ($validated['price_levels'] as $levelData) {
                ProductPriceLevel::withoutGlobalScopes()
                    ->where('id', $levelData['id'])
                    ->where('product_id', $productId)
                    ->update(['level_order' => $levelData['level_order']]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Price levels reordered successfully',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder price levels: ' . $e->getMessage(),
            ], 500);
        }
    }
}
