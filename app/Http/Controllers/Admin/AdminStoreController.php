<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStoreController extends Controller
{
    /**
     * List stores across all tenants with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'sort_by' => ['nullable', 'string', 'in:name,code,city,state,country,created_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 20;

        $query = Store::with(['tenant'])
            ->select('stores.*');

        // Filter by tenant
        if (! empty($validated['tenant_id'])) {
            $query->where('tenant_id', $validated['tenant_id']);
        }

        // Search by name, code, city, state, or country
        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('state', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%");
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

        $stores = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => [
                'stores' => $stores->getCollection()->map(fn($store) => [
                    'id' => $store->id,
                    'tenant_id' => $store->tenant_id,
                    'tenant_name' => $store->tenant?->name,
                    'name' => $store->name,
                    'code' => $store->code,
                    'address' => $store->address,
                    'city' => $store->city,
                    'state' => $store->state,
                    'country' => $store->country,
                    'postal_code' => $store->postal_code,
                    'phone' => $store->phone,
                    'email' => $store->email,
                    'active' => $store->active,
                    'created_at' => $store->created_at?->toISOString(),
                    'updated_at' => $store->updated_at?->toISOString(),
                ]),
                'pagination' => [
                    'current_page' => $stores->currentPage(),
                    'per_page' => $stores->perPage(),
                    'total' => $stores->total(),
                    'last_page' => $stores->lastPage(),
                    'has_more' => $stores->hasMorePages(),
                ],
            ],
        ]);
    }
}
