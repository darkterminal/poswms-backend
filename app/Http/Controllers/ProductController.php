<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $products = Product::where('tenant_id', $request->route('tenant_id'))
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['products' => $products],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100',
            'barcode' => 'string|max:100',
            'description' => 'string|nullable',
            'price' => 'required|numeric|min:0',
            'cost' => 'numeric|min:0',
            'tax_rate' => 'numeric|min:0',
            'unit' => 'string|max:50',
            'min_stock' => 'integer|min:0',
            'max_stock' => 'integer|min:0',
            'image' => 'string|nullable',
            'images' => 'array|nullable',
            'attributes' => 'array|nullable',
            'track_inventory' => 'boolean',
            'active' => 'boolean',
        ]);

        $validated['tenant_id'] = $request->route('tenant_id');
        $validated['active'] = $validated['active'] ?? true;
        $validated['track_inventory'] = $validated['track_inventory'] ?? true;

        $product = Product::create($validated);

        return response()->json([
            'success' => true,
            'data' => ['product' => $product],
            'message' => 'Product created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, int $product): JsonResponse
    {
        $product = Product::where('tenant_id', $request->route('tenant_id'))
            ->findOrFail($product);

        return response()->json([
            'success' => true,
            'data' => ['product' => $product],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $product): JsonResponse
    {
        $product = Product::where('tenant_id', $request->route('tenant_id'))
            ->findOrFail($product);

        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'sku' => 'sometimes|string|max:100',
            'barcode' => 'string|max:100',
            'description' => 'string|nullable',
            'price' => 'sometimes|numeric|min:0',
            'cost' => 'numeric|min:0',
            'tax_rate' => 'numeric|min:0',
            'unit' => 'string|max:50',
            'min_stock' => 'integer|min:0',
            'max_stock' => 'integer|min:0',
            'image' => 'string|nullable',
            'images' => 'array|nullable',
            'attributes' => 'array|nullable',
            'track_inventory' => 'boolean',
            'active' => 'boolean',
        ]);

        $product->update($validated);

        return response()->json([
            'success' => true,
            'data' => ['product' => $product],
            'message' => 'Product updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, int $product): JsonResponse
    {
        $product = Product::where('tenant_id', $request->route('tenant_id'))
            ->findOrFail($product);

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }
}
