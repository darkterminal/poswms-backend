<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosProductController extends Controller
{
    /**
     * List products across all tenants with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'category' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'sort_by' => ['nullable', 'string', 'in:name,sku,price,created_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 20;

        $query = Product::with(['tenant', 'category'])
            ->select('products.*');

        // Filter by tenant
        if (!empty($validated['tenant_id'])) {
            $query->where('tenant_id', $validated['tenant_id']);
        }

        // Search by name or SKU
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if (!empty($validated['category'])) {
            $query->whereHas('category', function ($q) use ($validated) {
                $q->where('name', $validated['category']);
            });
        }

        // Filter by status
        if (!empty($validated['status'])) {
            $query->where('active', $validated['status'] === 'active');
        }

        // Sorting
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDirection = $validated['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        $products = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $products->getCollection()->map(fn($product) => [
                    'id' => $product->id,
                    'tenant_id' => $product->tenant_id,
                    'tenant_name' => $product->tenant?->name,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'category' => $product->category?->name,
                    'price' => $product->price,
                    'cost' => $product->cost,
                    'is_active' => $product->active,
                    'created_at' => $product->created_at->toIso8601String(),
                    'updated_at' => $product->updated_at->toIso8601String(),
                ]),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'last_page' => $products->lastPage(),
                    'has_more' => $products->hasMorePages(),
                ],
            ],
            'message' => 'Products retrieved successfully',
        ], 200);
    }

    /**
     * Get product statistics across all tenants.
     */
    public function stats(): JsonResponse
    {
        $totalProducts = Product::count();
        $activeProducts = Product::where('active', true)->count();
        $inactiveProducts = Product::where('active', false)->count();
        $tenantsWithProducts = Product::distinct('tenant_id')->count('tenant_id');

        $topCategories = Product::selectRaw('category_id, COUNT(*) as count')
            ->with('category')
            ->groupBy('category_id')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn($item) => [
                'category' => $item->category?->name ?? 'Uncategorized',
                'count' => $item->count,
            ]);

        $priceStats = Product::selectRaw('AVG(price) as avg_price, MIN(price) as min_price, MAX(price) as max_price')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'total_products' => $totalProducts,
                'active_products' => $activeProducts,
                'inactive_products' => $inactiveProducts,
                'tenants_with_products' => $tenantsWithProducts,
                'top_categories' => $topCategories,
                'price_stats' => [
                    'avg_price' => round($priceStats->avg_price ?? 0, 2),
                    'min_price' => round($priceStats->min_price ?? 0, 2),
                    'max_price' => round($priceStats->max_price ?? 0, 2),
                ],
            ],
            'message' => 'Product statistics retrieved successfully',
        ], 200);
    }

    /**
     * Get a single product details.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load(['tenant', 'category', 'inventories']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $product->id,
                'tenant_id' => $product->tenant_id,
                'tenant_name' => $product->tenant?->name,
                'name' => $product->name,
                'sku' => $product->sku,
                'description' => $product->description,
                'category' => $product->category?->name,
                'price' => $product->price,
                'cost' => $product->cost,
                'is_active' => $product->active,
                'inventory_count' => $product->inventories->sum('quantity'),
                'created_at' => $product->created_at->toIso8601String(),
                'updated_at' => $product->updated_at->toIso8601String(),
            ],
            'message' => 'Product retrieved successfully',
        ], 200);
    }
}
