<?php

namespace App\Http\Controllers;

use App\Models\PricingRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingRuleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $rules = PricingRule::where('tenant_id', $request->route('tenant_id'))
            ->with(['pricingTier', 'product', 'category'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['pricing_rules' => $rules],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pricing_tier_id' => 'nullable|exists:pricing_tiers,id',
            'product_id' => 'nullable|exists:products,id',
            'category_id' => 'nullable|exists:categories,id',
            'type' => 'required|string|in:percentage,fixed',
            'operation' => 'required|string|in:add,subtract,replace',
            'value' => 'required|numeric|min:0',
            'min_quantity' => 'integer|min:0',
            'max_quantity' => 'integer|min:0',
            'starts_at' => 'date|nullable',
            'ends_at' => 'date|nullable',
            'active' => 'boolean',
        ]);

        $validated['tenant_id'] = $request->route('tenant_id');
        $validated['active'] = $validated['active'] ?? true;
        $validated['min_quantity'] = $validated['min_quantity'] ?? 0;

        $rule = PricingRule::create($validated);

        return response()->json([
            'success' => true,
            'data' => ['pricing_rule' => $rule],
            'message' => 'Pricing rule created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $pricingRuleId = $request->route('pricing_rule');

        $pricingRule = PricingRule::where('tenant_id', $tenantId)
            ->findOrFail($pricingRuleId);

        return response()->json([
            'success' => true,
            'data' => ['pricing_rule' => $pricingRule],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $pricingRuleId = $request->route('pricing_rule');

        $pricingRule = PricingRule::where('tenant_id', $tenantId)
            ->findOrFail($pricingRuleId);

        $validated = $request->validate([
            'pricing_tier_id' => 'nullable|exists:pricing_tiers,id',
            'product_id' => 'nullable|exists:products,id',
            'category_id' => 'nullable|exists:categories,id',
            'type' => 'sometimes|string|in:percentage,fixed',
            'operation' => 'sometimes|string|in:add,subtract,replace',
            'value' => 'sometimes|numeric|min:0',
            'min_quantity' => 'integer|min:0',
            'max_quantity' => 'integer|min:0',
            'starts_at' => 'date|nullable',
            'ends_at' => 'date|nullable',
            'active' => 'boolean',
        ]);

        $pricingRule->update($validated);

        return response()->json([
            'success' => true,
            'data' => ['pricing_rule' => $pricingRule],
            'message' => 'Pricing rule updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $pricingRuleId = $request->route('pricing_rule');

        $pricingRule = PricingRule::where('tenant_id', $tenantId)
            ->findOrFail($pricingRuleId);

        $pricingRule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pricing rule deleted successfully',
        ]);
    }
}
