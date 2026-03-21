<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    /**
     * Display a listing of tenants.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::query();

        // Search filter
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Sort
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = $request->query('per_page', 15);
        $tenants = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'tenants' => $tenants->items(),
                'pagination' => [
                    'current_page' => $tenants->currentPage(),
                    'per_page' => $tenants->perPage(),
                    'total' => $tenants->total(),
                    'last_page' => $tenants->lastPage(),
                    'has_more' => $tenants->hasMorePages(),
                ],
            ],
            'message' => 'Tenants retrieved successfully',
        ], 200);
    }

    /**
     * Store a newly created tenant.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:tenants,slug'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:tenants,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'timezone' => ['nullable', 'string', 'max:50', 'timezone'],
            'currency' => ['nullable', 'string', 'max:3', 'size:3'],
            'status' => ['nullable', Rule::in(['active', 'suspended', 'inactive'])],
            'settings' => ['nullable', 'array'],
            'trial_ends_at' => ['nullable', 'date'],
            'subscription_ends_at' => ['nullable', 'date'],
        ]);

        $tenant = Tenant::create($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => $tenant,
            ],
            'message' => 'Tenant created successfully',
        ], 201);
    }

    /**
     * Display the specified tenant.
     */
    public function show(Tenant $tenant): JsonResponse
    {
        $tenant->load(['users', 'stores', 'warehouses', 'products', 'customers']);

        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => $tenant,
            ],
            'message' => 'Tenant details retrieved successfully',
        ], 200);
    }

    /**
     * Update the specified tenant.
     */
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('tenants')->ignore($tenant->id)],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', Rule::unique('tenants')->ignore($tenant->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'timezone' => ['nullable', 'string', 'max:50', 'timezone'],
            'currency' => ['nullable', 'string', 'max:3', 'size:3'],
            'status' => ['nullable', Rule::in(['active', 'suspended', 'inactive'])],
            'settings' => ['nullable', 'array'],
            'trial_ends_at' => ['nullable', 'date'],
            'subscription_ends_at' => ['nullable', 'date'],
        ]);

        $tenant->update($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => $tenant,
            ],
            'message' => 'Tenant updated successfully',
        ], 200);
    }

    /**
     * Remove the specified tenant (soft delete).
     */
    public function destroy(Tenant $tenant): JsonResponse
    {
        $tenant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tenant deleted successfully',
        ], 200);
    }

    /**
     * Activate the specified tenant.
     */
    public function activate(Tenant $tenant): JsonResponse
    {
        $tenant->update(['status' => 'active']);

        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => $tenant,
            ],
            'message' => 'Tenant activated successfully',
        ], 200);
    }

    /**
     * Suspend the specified tenant.
     */
    public function suspend(Tenant $tenant): JsonResponse
    {
        $tenant->update(['status' => 'suspended']);

        return response()->json([
            'success' => true,
            'data' => [
                'tenant' => $tenant,
            ],
            'message' => 'Tenant suspended successfully',
        ], 200);
    }

    /**
     * Get statistics for the specified tenant.
     */
    public function stats(Tenant $tenant): JsonResponse
    {
        $stats = [
            'users' => $tenant->users()->count(),
            'stores' => $tenant->stores()->count(),
            'warehouses' => $tenant->warehouses()->count(),
            'products' => $tenant->products()->count(),
            'customers' => $tenant->customers()->count(),
            'inventory_items' => $tenant->inventories()->count(),
            'orders' => $tenant->orders()->count(),
            'is_on_trial' => $tenant->isOnTrial(),
            'has_active_subscription' => $tenant->hasActiveSubscription(),
            'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
            'subscription_ends_at' => $tenant->subscription_ends_at?->toIso8601String(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'stats' => $stats,
            ],
            'message' => 'Tenant statistics retrieved successfully',
        ], 200);
    }
}
