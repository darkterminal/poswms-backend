<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $customers = Customer::where('tenant_id', $request->route('tenant_id'))
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['customers' => $customers],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'email|max:255',
            'phone' => 'string|max:50',
            'company' => 'string|max:255',
            'tax_id' => 'string|max:100',
            'address' => 'string|nullable',
            'city' => 'string|max:255',
            'state' => 'string|max:255',
            'country' => 'string|max:255',
            'postal_code' => 'string|max:50',
            'pricing_tier_id' => 'nullable|exists:pricing_tiers,id',
            'credit_limit' => 'numeric|min:0',
            'balance' => 'numeric|min:0',
            'settings' => 'array|nullable',
            'active' => 'boolean',
        ]);

        $validated['tenant_id'] = $request->route('tenant_id');
        $validated['active'] = $validated['active'] ?? true;
        $validated['credit_limit'] = $validated['credit_limit'] ?? 0;
        $validated['balance'] = $validated['balance'] ?? 0;

        $customer = Customer::create($validated);

        return response()->json([
            'success' => true,
            'data' => ['customer' => $customer],
            'message' => 'Customer created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $customerId = $request->route('customerId');

        $customer = Customer::where('tenant_id', $tenantId)
            ->findOrFail($customerId);

        return response()->json([
            'success' => true,
            'data' => ['customer' => $customer],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $customerId = $request->route('customerId');

        $customer = Customer::where('tenant_id', $tenantId)
            ->findOrFail($customerId);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'email|max:255',
            'phone' => 'string|max:50',
            'company' => 'string|max:255',
            'tax_id' => 'string|max:100',
            'address' => 'string|nullable',
            'city' => 'string|max:255',
            'state' => 'string|max:255',
            'country' => 'string|max:255',
            'postal_code' => 'string|max:50',
            'pricing_tier_id' => 'nullable|exists:pricing_tiers,id',
            'credit_limit' => 'numeric|min:0',
            'balance' => 'numeric|min:0',
            'settings' => 'array|nullable',
            'active' => 'boolean',
        ]);

        $customer->update($validated);

        return response()->json([
            'success' => true,
            'data' => ['customer' => $customer],
            'message' => 'Customer updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $customerId = $request->route('customerId');

        $customer = Customer::where('tenant_id', $tenantId)
            ->findOrFail($customerId);

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully',
        ]);
    }
}
