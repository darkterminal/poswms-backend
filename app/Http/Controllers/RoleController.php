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
     * Display the specified role (Super Admin - Global View).
     */
    public function globalShow(int $roleId): JsonResponse
    {
        $role = Role::with(['tenant', 'users'])
            ->withCount('users')
            ->findOrFail($roleId);

        return response()->json([
            'success' => true,
            'data' => [
                'role' => [
                    'id' => $role->id,
                    'tenant_id' => $role->tenant_id,
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
                ],
            ],
            'message' => 'Role retrieved successfully',
        ], 200);
    }

    /**
     * Store a new role (Super Admin - Global View).
     */
    public function globalStore(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'slug' => 'required|string|max:255|unique:roles,slug',
                'description' => 'nullable|string',
                'permissions' => 'nullable|array',
                'permissions.*' => 'string',
                'tenant_id' => 'required|exists:tenants,id',
                'is_system' => 'boolean',
            ]);

            $role = Role::create([
                'tenant_id' => $validated['tenant_id'],
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'description' => $validated['description'] ?? null,
                'permissions' => $validated['permissions'] ?? [],
                'is_system' => $validated['is_system'] ?? false,
            ]);

            if ($this->securityLogger) {
                $this->securityLogger->log(
                    eventType: 'role.created',
                    description: 'Role created: ' . $role->name,
                    context: [
                        'role_id' => $role->id,
                        'role_name' => $role->name,
                        'tenant_id' => $role->tenant_id,
                    ],
                    userId: auth()->id()
                );
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'role' => [
                        'id' => $role->id,
                        'tenant_id' => $role->tenant_id,
                        'name' => $role->name,
                        'slug' => $role->slug,
                        'description' => $role->description,
                        'permissions' => $role->permissions,
                        'is_system' => $role->is_system,
                        'users_count' => 0,
                        'created_at' => $role->created_at->toIso8601String(),
                        'updated_at' => $role->updated_at->toIso8601String(),
                        'tenant' => null,
                    ],
                ],
                'message' => 'Role created successfully',
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'The given data was invalid.',
                    'details' => $e->errors(),
                ],
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('Role creation failed - Database error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'database_error',
                    'message' => 'Failed to create role. Database operation failed.',
                ],
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Role creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'internal_server_error',
                    'message' => 'Failed to create role. Please try again.',
                ],
            ], 500);
        }
    }

    /**
     * Update the specified role (Super Admin - Global View).
     */
    public function globalUpdate(Request $request, int $roleId): JsonResponse
    {
        try {
            $role = Role::findOrFail($roleId);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'slug' => 'sometimes|required|string|max:255|unique:roles,slug,' . $roleId . ',id',
                'description' => 'nullable|string',
                'permissions' => 'nullable|array',
                'permissions.*' => 'string',
                'is_system' => 'boolean',
            ]);

            $role->update($validated);

            if ($this->securityLogger) {
                $this->securityLogger->log(
                    eventType: 'role.updated',
                    description: 'Role updated: ' . $role->name,
                    context: [
                        'role_id' => $role->id,
                        'role_name' => $role->name,
                        'changes' => $validated,
                    ],
                    userId: auth()->id()
                );
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'role' => [
                        'id' => $role->id,
                        'tenant_id' => $role->tenant_id,
                        'name' => $role->name,
                        'slug' => $role->slug,
                        'description' => $role->description,
                        'permissions' => $role->permissions,
                        'is_system' => $role->is_system,
                        'users_count' => $role->users_count ?? 0,
                        'created_at' => $role->created_at->toIso8601String(),
                        'updated_at' => $role->updated_at->toIso8601String(),
                        'tenant' => $role->tenant ? [
                            'id' => $role->tenant->id,
                            'name' => $role->tenant->name,
                            'slug' => $role->tenant->slug,
                            'status' => $role->tenant->status,
                        ] : null,
                    ],
                ],
                'message' => 'Role updated successfully',
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'validation_error',
                    'message' => 'The given data was invalid.',
                    'details' => $e->errors(),
                ],
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Role not found',
                ],
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Role update failed', [
                'role_id' => $roleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'internal_server_error',
                    'message' => 'Failed to update role. Please try again.',
                ],
            ], 500);
        }
    }

    /**
     * Remove the specified role (Super Admin - Global View).
     */
    public function globalDestroy(int $roleId): JsonResponse
    {
        try {
            $role = Role::findOrFail($roleId);

            if ($role->is_system) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'forbidden',
                        'message' => 'System roles cannot be deleted',
                    ],
                ], 403);
            }

            // Check if role has users assigned
            $usersCount = $role->users()->count();
            if ($usersCount > 0) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'forbidden',
                        'message' => 'Cannot delete role with assigned users. Please reassign or remove users first.',
                        'details' => [
                            'users_count' => $usersCount,
                        ],
                    ],
                ], 403);
            }

            $roleName = $role->name;
            $role->delete();

            if ($this->securityLogger) {
                $this->securityLogger->log(
                    eventType: 'role.deleted',
                    description: 'Role deleted: ' . $roleName,
                    context: [
                        'role_id' => $roleId,
                        'role_name' => $roleName,
                    ],
                    userId: auth()->id()
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Role not found',
                ],
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('Role deletion failed - Database error', [
                'role_id' => $roleId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'database_error',
                    'message' => 'Failed to delete role. Database operation failed.',
                ],
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Role deletion failed', [
                'role_id' => $roleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'internal_server_error',
                    'message' => 'Failed to delete role. Please try again.',
                ],
            ], 500);
        }
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
