<?php

namespace App\Http\Controllers;

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
        $categories = Category::where('tenant_id', $request->route('tenant_id'))
            ->with('parent')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['categories' => $categories],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:categories,id',
            'name' => 'required|string|max:255',
            'slug' => 'string|max:255',
            'description' => 'string|nullable',
            'image' => 'string|nullable',
            'sort_order' => 'integer|min:0',
            'active' => 'boolean',
        ]);

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
    public function update(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $categoryId = $request->route('categoryId');

        $category = Category::where('tenant_id', $tenantId)
            ->findOrFail($categoryId);

        $validated = $request->validate([
            'parent_id' => 'nullable|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255',
            'description' => 'string|nullable',
            'image' => 'string|nullable',
            'sort_order' => 'integer|min:0',
            'active' => 'boolean',
        ]);

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
