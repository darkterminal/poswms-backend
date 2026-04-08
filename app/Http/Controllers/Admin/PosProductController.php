<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $query = Product::with(['tenant', 'category', 'priceLevels', 'inventories'])
            ->select('products.*');

        // Filter by tenant
        if (! empty($validated['tenant_id'])) {
            $query->where('tenant_id', $validated['tenant_id']);
        }

        // Search by name, SKU, or barcode
        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if (! empty($validated['category'])) {
            $query->whereHas('category', function ($q) use ($validated) {
                $q->where('name', $validated['category']);
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
                    'price_levels' => $product->getAllPriceLevels(),
                    'inventory_count' => $product->inventories->sum('quantity'),
                    'min_stock' => $product->min_stock,
                    'is_low_stock' => $product->isLowStock(),
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
    public function show($productId): JsonResponse
    {
        // Bypass tenant scope for super admin
        $product = Product::withoutGlobalScopes()
            ->with(['tenant', 'category', 'inventories.warehouse', 'inventories.store', 'priceLevels'])
            ->findOrFail($productId);

        return response()->json([
            'success' => true,
            'data' => [
                'product' => [
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
                    'min_stock' => $product->min_stock,
                    'is_low_stock' => $product->isLowStock(),
                    'inventory_count' => $product->inventories->sum('quantity'),
                    'price_levels' => $product->getAllPriceLevels(),
                    'inventories' => $product->inventories->map(fn($inv) => [
                        'id' => $inv->id,
                        'warehouse_id' => $inv->warehouse_id,
                        'warehouse_name' => $inv->warehouse?->name,
                        'store_id' => $inv->store_id,
                        'store_name' => $inv->store?->name,
                        'quantity' => $inv->quantity,
                        'reserved' => $inv->reserved,
                        'available' => $inv->available,
                        'cost' => $inv->cost,
                        'location' => $inv->location,
                        'notes' => $inv->notes,
                    ]),
                    'created_at' => $product->created_at->toIso8601String(),
                    'updated_at' => $product->updated_at->toIso8601String(),
                ],
            ],
            'message' => 'Product retrieved successfully',
        ], 200);
    }

    /**
     * Create a new product for a tenant.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['tenant_id'] = $request->input('tenant_id');
        $validated['active'] = $validated['active'] ?? true;
        $validated['track_inventory'] = $validated['track_inventory'] ?? true;
        
        // Convert empty strings to null for optional fields
        foreach (['barcode', 'description', 'unit', 'image'] as $field) {
            if (isset($validated[$field]) && $validated[$field] === '') {
                $validated[$field] = null;
            }
        }

        $product = Product::create($validated);

        return response()->json([
            'success' => true,
            'data' => ['product' => $product],
            'message' => 'Product created successfully',
        ], 201);
    }

    /**
     * Update an existing product.
     */
    public function update(UpdateProductRequest $request, $productId): JsonResponse
    {
        // Bypass tenant scope for super admin
        $product = Product::withoutGlobalScopes()->findOrFail($productId);

        $validated = $request->validated();
        
        // Convert empty strings to null for optional fields
        foreach (['barcode', 'description', 'unit', 'image'] as $field) {
            if (isset($validated[$field]) && $validated[$field] === '') {
                $validated[$field] = null;
            }
        }

        $product->update($validated);

        return response()->json([
            'success' => true,
            'data' => ['product' => $product],
            'message' => 'Product updated successfully',
        ]);
    }

    /**
     * Delete a product (soft delete).
     */
    public function destroy($productId): JsonResponse
    {
        // Bypass tenant scope for super admin
        $product = Product::withoutGlobalScopes()->findOrFail($productId);

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }

    /**
     * Toggle product active/inactive status.
     */
    public function toggleStatus($productId): JsonResponse
    {
        // Bypass tenant scope for super admin
        $product = Product::withoutGlobalScopes()->findOrFail($productId);

        $product->active = ! $product->active;
        $product->save();

        return response()->json([
            'success' => true,
            'data' => ['product' => $product],
            'message' => $product->active ? 'Product activated successfully' : 'Product deactivated successfully',
        ]);
    }

    /**
     * Get stock movements for a product.
     */
    public function stockMovements($productId, Request $request): JsonResponse
    {
        // Bypass tenant scope for super admin
        $product = Product::withoutGlobalScopes()->findOrFail($productId);

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'type' => ['nullable', 'string', 'in:in,out,adjustment'],
        ]);

        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 20;

        $query = StockMovement::where('product_id', $productId)
            ->with(['warehouse', 'store', 'order', 'layer.batch']);

        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        $query->orderByDesc('created_at');

        $movements = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => [
                'movements' => $movements->getCollection()->map(fn($m) => [
                    'id' => $m->id,
                    'type' => $m->type,
                    'quantity' => $m->quantity,
                    'unit_cost' => $m->unit_cost,
                    'total_cost' => $m->total_cost,
                    'quantity_before' => $m->quantity_before,
                    'quantity_after' => $m->quantity_after,
                    'warehouse_name' => $m->warehouse?->name,
                    'store_name' => $m->store?->name,
                    'order_number' => $m->order?->order_number,
                    'batch_number' => $m->layer?->batch?->batch_number,
                    'reason' => $m->reason,
                    'reference' => $m->reference,
                    'created_at' => $m->created_at->toIso8601String(),
                ]),
                'pagination' => [
                    'current_page' => $movements->currentPage(),
                    'per_page' => $movements->perPage(),
                    'total' => $movements->total(),
                    'last_page' => $movements->lastPage(),
                    'has_more' => $movements->hasMorePages(),
                ],
            ],
            'message' => 'Stock movements retrieved successfully',
        ], 200);
    }

    /**
     * Get orders containing a specific product.
     */
    public function orders($productId, Request $request): JsonResponse
    {
        // Bypass tenant scope for super admin
        $product = Product::withoutGlobalScopes()->findOrFail($productId);

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'string', 'in:pending,confirmed,fulfilled,cancelled'],
        ]);

        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 20;

        $query = OrderItem::where('product_id', $productId)
            ->with(['order.tenant', 'order.customer', 'order.store', 'order.warehouse']);

        if (! empty($validated['status'])) {
            $query->whereHas('order', function ($q) use ($validated) {
                $q->where('status', $validated['status']);
            });
        }

        $query->orderByDesc('created_at');

        $orderItems = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => [
                'order_items' => $orderItems->getCollection()->map(fn($item) => [
                    'id' => $item->id,
                    'order_id' => $item->order_id,
                    'order_number' => $item->order?->order_number,
                    'tenant_name' => $item->order?->tenant?->name,
                    'customer_name' => $item->order?->customer?->name,
                    'store_name' => $item->order?->store?->name,
                    'warehouse_name' => $item->order?->warehouse?->name,
                    'order_status' => $item->order?->status,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax' => $item->tax,
                    'discount' => $item->discount,
                    'total' => $item->total,
                    'created_at' => $item->created_at->toIso8601String(),
                ]),
                'pagination' => [
                    'current_page' => $orderItems->currentPage(),
                    'per_page' => $orderItems->perPage(),
                    'total' => $orderItems->total(),
                    'last_page' => $orderItems->lastPage(),
                    'has_more' => $orderItems->hasMorePages(),
                ],
            ],
            'message' => 'Order history retrieved successfully',
        ], 200);
    }

    /**
     * Get all categories for filtering products.
     */
    public function categories(): JsonResponse
    {
        $categories = Category::select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories,
            ],
            'message' => 'Categories retrieved successfully',
        ], 200);
    }

    /**
     * Export products to CSV.
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'category' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'sort_by' => ['nullable', 'string', 'in:name,sku,price,created_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $query = Product::with(['tenant', 'category', 'priceLevels'])
            ->select('products.*');

        // Apply same filters as index
        if (! empty($validated['tenant_id'])) {
            $query->where('tenant_id', $validated['tenant_id']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['category'])) {
            $query->whereHas('category', function ($q) use ($validated) {
                $q->where('name', $validated['category']);
            });
        }

        if (! empty($validated['status'])) {
            $query->where('active', $validated['status'] === 'active');
        }

        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDirection = $validated['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        $products = $query->get();

        // Generate CSV
        $filename = 'pos-products-' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($file, [
                'ID',
                'Tenant Name',
                'Product Name',
                'SKU',
                'Category',
                'Base Price',
                'Base Cost',
                'Status',
                'Price Levels Count',
                'Created At',
                'Updated At',
            ]);

            // CSV Data
            foreach ($products as $product) {
                fputcsv($file, [
                    $product->id,
                    $product->tenant?->name,
                    $product->name,
                    $product->sku,
                    $product->category?->name,
                    $product->price,
                    $product->cost,
                    $product->active ? 'Active' : 'Inactive',
                    $product->priceLevels->count(),
                    $product->created_at->toIso8601String(),
                    $product->updated_at->toIso8601String(),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
