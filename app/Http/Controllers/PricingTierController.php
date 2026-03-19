<?php

namespace App\Http\Controllers;

use App\Models\PricingTier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingTierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $tiers = PricingTier::where('tenant_id', $request->route('tenant_id'))
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['pricing_tiers' => $tiers],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100',
            'description' => 'string|nullable',
            'priority' => 'integer|min:0',
            'active' => 'boolean',
        ]);

        $validated['tenant_id'] = $request->route('tenant_id');
        $validated['active'] = $validated['active'] ?? true;

        $tier = PricingTier::create($validated);

        return response()->json([
            'success' => true,
            'data' => ['pricing_tier' => $tier],
            'message' => 'Pricing tier created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, int $pricingTier): JsonResponse
    {
        $pricingTier = PricingTier::where('tenant_id', $request->route('tenant_id'))
            ->findOrFail($pricingTier);

        return response()->json([
            'success' => true,
            'data' => ['pricing_tier' => $pricingTier],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $pricingTier): JsonResponse
    {
        $pricingTier = PricingTier::where('tenant_id', $request->route('tenant_id'))
            ->findOrFail($pricingTier);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:100',
            'description' => 'string|nullable',
            'priority' => 'integer|min:0',
            'active' => 'boolean',
        ]);

        $pricingTier->update($validated);

        return response()->json([
            'success' => true,
            'data' => ['pricing_tier' => $pricingTier],
            'message' => 'Pricing tier updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, int $pricingTier): JsonResponse
    {
        $pricingTier = PricingTier::where('tenant_id', $request->route('tenant_id'))
            ->findOrFail($pricingTier);

        $pricingTier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pricing tier deleted successfully',
        ]);
    }
}
