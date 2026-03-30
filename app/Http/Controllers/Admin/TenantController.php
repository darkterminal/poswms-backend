<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ValidatesSorting;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListTenantsRequest;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    use ValidatesSorting;

    /**
     * Display a listing of tenants.
     */
    public function index(ListTenantsRequest $request): JsonResponse
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

        // Plan filter
        if ($plan = $request->query('plan')) {
            $query->where('subscription_plan', $plan);
        }

        // Trial expiring filter
        if ($trialExpiring = $request->query('trial_expiring')) {
            $this->applyTrialExpiringFilter($query, $trialExpiring);
        }

        // Subscription status filter
        if ($subscriptionStatus = $request->query('subscription_status')) {
            $this->applySubscriptionStatusFilter($query, $subscriptionStatus);
        }

        // Date from filter
        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        // Date to filter
        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Sort - validated against whitelist to prevent SQL injection
        $allowedSortFields = ['name', 'slug', 'company_name', 'email', 'status', 'subscription_plan', 'trial_ends_at', 'subscription_ends_at', 'created_at', 'updated_at'];
        $sortParams = $this->getValidatedSortParams(
            $request,
            'created_at',
            $allowedSortFields,
            'desc'
        );
        $query->orderBy($sortParams['sort_by'], $sortParams['sort_order']);

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
            'meta' => [
                'sorting' => [
                    'field' => $sortParams['sort_by'],
                    'order' => $sortParams['sort_order'],
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
            'subscription_plan' => ['nullable', 'string', 'max:50'],
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
            'subscription_plan' => ['nullable', 'string', 'max:50'],
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

    /**
     * Update tenant trial period.
     */
    public function updateTrial(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'trial_ends_at' => ['required', 'date'],
        ]);

        $tenant->update(['trial_ends_at' => $validated['trial_ends_at']]);

        return response()->json([
            'success' => true,
            'data' => [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'trial_ends_at' => $tenant->trial_ends_at->toIso8601String(),
            ],
            'message' => 'Tenant trial period updated successfully',
        ], 200);
    }

    /**
     * Extend tenant trial period.
     */
    public function extendTrial(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['required', 'integer', 'min:1'],
        ]);

        $currentTrialEndsAt = $tenant->trial_ends_at ?? now();
        $newTrialEndsAt = $currentTrialEndsAt->addDays($validated['days']);

        $tenant->update(['trial_ends_at' => $newTrialEndsAt]);

        return response()->json([
            'success' => true,
            'data' => [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'trial_ends_at' => $tenant->trial_ends_at->toIso8601String(),
                'days_added' => $validated['days'],
            ],
            'message' => 'Tenant trial period extended successfully',
        ], 200);
    }

    /**
     * Update tenant subscription.
     */
    public function updateSubscription(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'subscription_ends_at' => ['required', 'date'],
        ]);

        $tenant->update(['subscription_ends_at' => $validated['subscription_ends_at']]);

        return response()->json([
            'success' => true,
            'data' => [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'subscription_ends_at' => $tenant->subscription_ends_at->toIso8601String(),
            ],
            'message' => 'Tenant subscription updated successfully',
        ], 200);
    }

    /**
     * Extend tenant subscription.
     */
    public function extendSubscription(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['required', 'integer', 'min:1'],
        ]);

        $currentSubscriptionEndsAt = $tenant->subscription_ends_at ?? now();
        $newSubscriptionEndsAt = $currentSubscriptionEndsAt->addDays($validated['days']);

        $tenant->update(['subscription_ends_at' => $newSubscriptionEndsAt]);

        return response()->json([
            'success' => true,
            'data' => [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'subscription_ends_at' => $tenant->subscription_ends_at->toIso8601String(),
                'days_added' => $validated['days'],
            ],
            'message' => 'Tenant subscription extended successfully',
        ], 200);
    }

    /**
     * Cancel tenant subscription (set to end of current period).
     */
    public function cancelSubscription(Tenant $tenant): JsonResponse
    {
        if (! $tenant->hasActiveSubscription()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_ACTIVE_SUBSCRIPTION',
                    'message' => 'Tenant does not have an active subscription to cancel',
                ],
            ], 400);
        }

        // Set subscription to end at the end of current billing period
        $tenant->update(['subscription_ends_at' => $tenant->subscription_ends_at]);

        return response()->json([
            'success' => true,
            'data' => [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'subscription_ends_at' => $tenant->subscription_ends_at->toIso8601String(),
                'cancelled_at' => now()->toIso8601String(),
            ],
            'message' => 'Tenant subscription cancelled successfully (access until end of period)',
        ], 200);
    }

    /**
     * Convert tenant from trial to paid subscription.
     */
    public function convertToPaid(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'subscription_ends_at' => ['required', 'date'],
        ]);

        $tenant->update([
            'trial_ends_at' => null,
            'subscription_ends_at' => $validated['subscription_ends_at'],
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'subscription_ends_at' => $tenant->subscription_ends_at->toIso8601String(),
            ],
            'message' => 'Tenant converted from trial to paid subscription successfully',
        ], 200);
    }

    /**
     * Apply trial expiring filter to query.
     */
    private function applyTrialExpiringFilter($query, string $timeframe): void
    {
        match ($timeframe) {
            '24hours' => $query->whereNotNull('trial_ends_at')
                ->whereBetween('trial_ends_at', [now(), now()->addHours(24)]),
            '7days' => $query->whereNotNull('trial_ends_at')
                ->whereBetween('trial_ends_at', [now(), now()->addDays(7)]),
            '30days' => $query->whereNotNull('trial_ends_at')
                ->whereBetween('trial_ends_at', [now(), now()->addDays(30)]),
            'expired' => $query->whereNotNull('trial_ends_at')
                ->where('trial_ends_at', '<', now()),
            default => null,
        };
    }

    /**
     * Apply subscription status filter to query.
     */
    private function applySubscriptionStatusFilter($query, string $status): void
    {
        match ($status) {
            'active' => $query->whereNotNull('subscription_ends_at')
                ->where('subscription_ends_at', '>', now())
                ->whereNotNull('subscription_plan'),
            'expiring' => $query->whereNotNull('subscription_ends_at')
                ->whereBetween('subscription_ends_at', [now(), now()->addDays(30)]),
            'expired' => $query->whereNotNull('subscription_ends_at')
                ->where('subscription_ends_at', '<', now()),
            'none' => $query->where(function ($q) {
                $q->whereNull('subscription_ends_at')
                    ->orWhereNull('subscription_plan');
            })->where(function ($q) {
                $q->whereNull('trial_ends_at')
                    ->orWhere('trial_ends_at', '>', now());
            }),
            default => null,
        };
    }
}
