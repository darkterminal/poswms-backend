<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\SecurityAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private SecurityAuditLogger $securityLogger
    ) {}

    /**
     * Display a listing of roles across all tenants (Super Admin only).
     */
    public function globalIndex(Request $request): JsonResponse
    {
        $query = Role::query()
            ->with(['tenant', 'users'])
            ->withCount('users');

        // Filter by tenant_id if provided
        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by is_system
        if ($request->has('is_system')) {
            $query->where('is_system', $request->boolean('is_system'));
        }

        // Sorting
        $allowedSortFields = ['name', 'slug', 'created_at', 'updated_at', 'is_system'];
        $sortBy = $request->get('sort_by', 'created_at');
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $roles = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'roles' => $roles->getCollection()->map(fn($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'description' => $role->description,
                    'permissions' => $role->permissions,
                    'is_system' => $role->is_system,
                    'users_count' => $role->users_count,
                    'created_at' => $role->created_at->toIso8601String(),
                    'updated_at' => $role->updated_at->toIso8601String(),
                    'tenant' => $role->tenant ? [
                        'id' => $role->tenant->id,
                        'name' => $role->tenant->name,
                        'slug' => $role->tenant->slug,
                        'status' => $role->tenant->status,
                    ] : null,
                ]),
                'pagination' => [
                    'current_page' => $roles->currentPage(),
                    'last_page' => $roles->lastPage(),
                    'per_page' => $roles->perPage(),
                    'total' => $roles->total(),
                    'from' => $roles->firstItem(),
                    'to' => $roles->lastItem(),
                ],
            ],
            'meta' => [
                'filters_applied' => $request->only(['tenant_id', 'search', 'is_system']),
                'sorting' => ['by' => $sortBy, 'order' => $sortOrder],
            ],
            'message' => 'Roles retrieved successfully across all tenants',
        ], 200);
    }

    /**
     * Display a listing of roles for the tenant.
     */
    public function index(Request $request, int $tenant_id): JsonResponse
    {
        $roles = Role::where('tenant_id', $tenant_id)
            ->withCount('users')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'roles' => $roles,
            ],
            'message' => 'Roles retrieved successfully',
        ], 200);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request, int $tenant_id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:roles,slug,NULL,id,tenant_id,' . $tenant_id,
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
            'is_system' => 'boolean',
        ]);

        $role = Role::create([
            'tenant_id' => $tenant_id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'permissions' => $validated['permissions'] ?? [],
            'is_system' => $validated['is_system'] ?? false,
        ]);

        // Security audit logging for role creation
        $this->securityLogger->logAsync(
            eventType: 'role.created',
            description: "Role created: {$role->name}",
            context: [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'role_slug' => $role->slug,
                'permissions' => $role->permissions,
                'is_system' => $role->is_system,
            ],
            tenantId: $tenant_id,
            userId: $request->user()->id,
            request: $request,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role,
            ],
            'message' => 'Role created successfully',
        ], 201);
    }

    /**
     * Display the specified role.
     */
    public function show(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $roleId = $request->route('role');

        $role = Role::where('tenant_id', $tenantId)->findOrFail($roleId);

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role,
            ],
            'message' => 'Role retrieved successfully',
        ], 200);
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $roleId = $request->route('role');

        $role = Role::where('tenant_id', $tenantId)->findOrFail($roleId);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:roles,slug,' . $roleId . ',id,tenant_id,' . $tenantId,
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
        ]);

        // Capture old values for audit
        $oldValues = [
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'permissions' => $role->permissions,
        ];

        $role->update($validated);

        // Security audit logging for role update
        $this->securityLogger->logAsync(
            eventType: 'role.updated',
            description: "Role updated: {$role->name}",
            context: [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'old_values' => $oldValues,
                'new_values' => $validated,
                'changed_fields' => array_keys($validated),
            ],
            tenantId: $tenantId,
            userId: $request->user()->id,
            request: $request,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role->fresh(),
            ],
            'message' => 'Role updated successfully',
        ], 200);
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $roleId = $request->route('role');

        $role = Role::where('tenant_id', $tenantId)->findOrFail($roleId);

        if ($role->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete system role.',
            ], 403);
        }

        // Capture role data before deletion for audit
        $roleData = [
            'role_id' => $role->id,
            'role_name' => $role->name,
            'role_slug' => $role->slug,
            'permissions' => $role->permissions,
        ];

        $role->delete();

        // Security audit logging for role deletion
        $this->securityLogger->logAsync(
            eventType: 'role.deleted',
            description: "Role deleted: {$roleData['role_name']}",
            context: $roleData,
            tenantId: $tenantId,
            userId: $request->user()->id,
            request: $request,
        );

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully',
        ], 200);
    }

    /**
     * Assign a role to a user.
     */
    public function assignToUser(Request $request, int $tenant_id, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = Role::where('tenant_id', $tenant_id)->findOrFail($validated['role_id']);
        $user = User::where('tenant_id', $tenant_id)->findOrFail($userId);

        $user->assignRole($role);

        // Security audit logging for role assignment
        $this->securityLogger->logAsync(
            eventType: 'role.assigned',
            description: "Role '{$role->name}' assigned to user '{$user->name}'",
            context: [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_name' => $user->name,
                'role_id' => $role->id,
                'role_name' => $role->name,
                'role_slug' => $role->slug,
            ],
            tenantId: $tenant_id,
            userId: $request->user()->id,
            request: $request,
        );

        return response()->json([
            'success' => true,
            'message' => 'Role assigned successfully.',
        ], 200);
    }

    /**
     * Remove a role from a user.
     */
    public function removeFromUser(Request $request, int $tenant_id, int $userId, int $roleId): JsonResponse
    {
        $role = Role::where('tenant_id', $tenant_id)->findOrFail($roleId);
        $user = User::where('tenant_id', $tenant_id)->findOrFail($userId);

        $user->removeRole($role);

        // Security audit logging for role removal
        $this->securityLogger->logAsync(
            eventType: 'role.revoked',
            description: "Role '{$role->name}' removed from user '{$user->name}'",
            context: [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_name' => $user->name,
                'role_id' => $role->id,
                'role_name' => $role->name,
                'role_slug' => $role->slug,
            ],
            tenantId: $tenant_id,
            userId: $request->user()->id,
            request: $request,
        );

        return response()->json([
            'success' => true,
            'message' => 'Role removed successfully.',
        ], 200);
    }
}
