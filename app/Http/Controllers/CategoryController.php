<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');

        $query = Category::where('tenant_id', $tenantId)
            ->with('parent');

        // Filter by parent category
        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        // Search by name or description
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by active status
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        // Paginate results
        $perPage = $request->get('per_page', 15);
        $categories = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories->items(),
                'pagination' => [
                    'current_page' => $categories->currentPage(),
                    'per_page' => $categories->perPage(),
                    'total' => $categories->total(),
                    'last_page' => $categories->lastPage(),
                    'has_more' => $categories->hasMorePages(),
                ],
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['tenant_id'] = $request->route('tenant_id');
        $validated['active'] = $validated['active'] ?? true;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        // Auto-generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category = Category::create($validated);

        return response()->json([
            'success' => true,
            'data' => ['category' => $category],
            'message' => 'Category created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $categoryId = $request->route('categoryId');

        $category = Category::where('tenant_id', $tenantId)
            ->with(['parent', 'children', 'products'])
            ->findOrFail($categoryId);

        return response()->json([
            'success' => true,
            'data' => ['category' => $category],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $categoryId = $request->route('categoryId');

        $category = Category::where('tenant_id', $tenantId)
            ->findOrFail($categoryId);

        $validated = $request->validated();

        // Only update slug if explicitly provided in the request
        // Don't auto-generate slug on update to avoid conflicts
        if (! isset($validated['slug'])) {
            unset($validated['slug']);
        }

        $category->update($validated);

        return response()->json([
            'success' => true,
            'data' => ['category' => $category],
            'message' => 'Category updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $categoryId = $request->route('categoryId');

        $category = Category::where('tenant_id', $tenantId)
            ->findOrFail($categoryId);

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }
}
