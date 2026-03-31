<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ValidatesSorting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SearchUsersRequest;
use App\Models\User;
use App\SecurityAuditLogger;
use App\Services\ImpersonationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use ValidatesSorting;

    public function __construct(
        private ImpersonationService $impersonationService,
        private SecurityAuditLogger $securityLogger
    ) {}

    /**
     * Display a listing of users across all tenants.
     */
    public function index(SearchUsersRequest $request): JsonResponse
    {
        $query = User::query()
            ->with(['tenant', 'store', 'warehouse', 'roles']);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('email')) {
            $query->where('email', $request->email);
        }

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('is_super_admin')) {
            $query->where('is_super_admin', $request->boolean('is_super_admin'));
        }

        // Sorting - validated against whitelist to prevent SQL injection
        $allowedSortFields = ['name', 'email', 'created_at', 'updated_at', 'is_active'];
        $sortParams = $this->getValidatedSortParams(
            $request,
            'created_at',
            $allowedSortFields,
            'desc'
        );
        $query->orderBy($sortParams['sort_by'], $sortParams['sort_order']);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'users' => $users->getCollection()->map(fn(User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_super_admin' => $user->is_super_admin,
                    'is_active' => $user->is_active ?? true,
                    'tenant' => $user->tenant ? [
                        'id' => $user->tenant->id,
                        'name' => $user->tenant->name,
                        'slug' => $user->tenant->slug,
                    ] : null,
                    'store' => $user->store ? [
                        'id' => $user->store->id,
                        'name' => $user->store->name,
                    ] : null,
                    'warehouse' => $user->warehouse ? [
                        'id' => $user->warehouse->id,
                        'name' => $user->warehouse->name,
                    ] : null,
                    'roles' => $user->roles->map(fn($role) => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'slug' => $role->slug,
                    ]),
                    'created_at' => $user->created_at->toIso8601String(),
                    'updated_at' => $user->updated_at->toIso8601String(),
                ]),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
            ],
            'meta' => [
                'filters_applied' => $request->only(['search', 'email', 'tenant_id', 'status', 'is_super_admin']),
                'sorting' => ['by' => $sortParams['sort_by'], 'order' => $sortParams['sort_order']],
            ],
        ]);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): JsonResponse
    {
        $user->load(['tenant', 'store', 'warehouse', 'roles']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_super_admin' => $user->is_super_admin,
                'is_active' => $user->is_active ?? true,
                'tenant' => $user->tenant ? [
                    'id' => $user->tenant->id,
                    'name' => $user->tenant->name,
                    'slug' => $user->tenant->slug,
                    'status' => $user->tenant->status,
                ] : null,
                'store' => $user->store ? [
                    'id' => $user->store->id,
                    'name' => $user->store->name,
                ] : null,
                'warehouse' => $user->warehouse ? [
                    'id' => $user->warehouse->id,
                    'name' => $user->warehouse->name,
                ] : null,
                'roles' => $user->roles->map(fn($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ]),
                'created_at' => $user->created_at->toIso8601String(),
                'updated_at' => $user->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'tenant_id' => ['nullable', 'exists:tenants,id'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,id'],
            'is_super_admin' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'tenant_id' => $validated['tenant_id'] ?? null,
            'store_id' => $validated['store_id'] ?? null,
            'warehouse_id' => $validated['warehouse_id'] ?? null,
            'is_super_admin' => $validated['is_super_admin'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Assign roles if provided
        if (! empty($validated['roles'])) {
            $user->roles()->attach($validated['roles']);
        }

        $user->load(['tenant', 'store', 'warehouse', 'roles']);

        // Security audit logging
        $this->securityLogger->logAsync(
            eventType: 'user.created',
            description: "User '{$user->name}' was created",
            context: [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_name' => $user->name,
                'is_super_admin' => $user->is_super_admin,
            ],
            tenantId: $user->tenant_id,
            userId: $request->user()?->id,
            request: $request,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_super_admin' => $user->is_super_admin,
                'is_active' => $user->is_active,
                'tenant' => $user->tenant ? [
                    'id' => $user->tenant->id,
                    'name' => $user->tenant->name,
                    'slug' => $user->tenant->slug,
                    'status' => $user->tenant->status,
                ] : null,
                'store' => $user->store ? [
                    'id' => $user->store->id,
                    'name' => $user->store->name,
                ] : null,
                'warehouse' => $user->warehouse ? [
                    'id' => $user->warehouse->id,
                    'name' => $user->warehouse->name,
                ] : null,
                'roles' => $user->roles->map(fn($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ]),
                'created_at' => $user->created_at->toIso8601String(),
                'updated_at' => $user->updated_at->toIso8601String(),
            ],
            'message' => 'User created successfully',
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['sometimes', 'string', 'min:8'],
            'tenant_id' => ['nullable', 'exists:tenants,id'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,id'],
            'is_super_admin' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        // Update user attributes
        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }

        if (isset($validated['password'])) {
            $user->password = bcrypt($validated['password']);
        }

        if (isset($validated['tenant_id'])) {
            $user->tenant_id = $validated['tenant_id'];
        }

        if (isset($validated['store_id'])) {
            $user->store_id = $validated['store_id'];
        }

        if (isset($validated['warehouse_id'])) {
            $user->warehouse_id = $validated['warehouse_id'];
        }

        if (isset($validated['is_super_admin'])) {
            $user->is_super_admin = $validated['is_super_admin'];
        }

        if (isset($validated['is_active'])) {
            $user->is_active = $validated['is_active'];
        }

        $user->save();

        // Sync roles if provided
        if (isset($validated['roles'])) {
            $user->roles()->sync($validated['roles']);
        }

        $user->load(['tenant', 'store', 'warehouse', 'roles']);

        // Security audit logging
        $this->securityLogger->logAsync(
            eventType: 'user.updated',
            description: "User '{$user->name}' was updated",
            context: [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_name' => $user->name,
                'changes' => array_keys($validated),
            ],
            tenantId: $user->tenant_id,
            userId: $request->user()?->id,
            request: $request,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_super_admin' => $user->is_super_admin,
                'is_active' => $user->is_active,
                'tenant' => $user->tenant ? [
                    'id' => $user->tenant->id,
                    'name' => $user->tenant->name,
                    'slug' => $user->tenant->slug,
                    'status' => $user->tenant->status,
                ] : null,
                'store' => $user->store ? [
                    'id' => $user->store->id,
                    'name' => $user->store->name,
                ] : null,
                'warehouse' => $user->warehouse ? [
                    'id' => $user->warehouse->id,
                    'name' => $user->warehouse->name,
                ] : null,
                'roles' => $user->roles->map(fn($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                ]),
                'created_at' => $user->created_at->toIso8601String(),
                'updated_at' => $user->updated_at->toIso8601String(),
            ],
            'message' => 'User updated successfully',
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $userName = $user->name;
        $userEmail = $user->email;

        // Prevent deleting yourself
        if ($request->user()?->id === $user->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_REQUEST',
                    'message' => 'Cannot delete your own account',
                ],
            ], 422);
        }

        // Delete the user
        $user->delete();

        // Security audit logging
        $this->securityLogger->logAsync(
            eventType: 'user.deleted',
            description: "User '{$userName}' was deleted",
            context: [
                'user_id' => $user->id,
                'user_email' => $userEmail,
                'user_name' => $userName,
            ],
            tenantId: $user->tenant_id,
            userId: $request->user()?->id,
            request: $request,
        );

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Generate an impersonation token for the specified user.
     */
    public function impersonate(Request $request, User $user): JsonResponse
    {
        $impersonator = $request->user();

        // Validate that impersonator is a super admin
        if (! $impersonator || ! $impersonator->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Only super admins can impersonate users',
                ],
            ], 403);
        }

        // Cannot impersonate yourself
        if ($user->id === $impersonator->id) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_REQUEST',
                    'message' => 'Cannot impersonate yourself',
                ],
            ], 422);
        }

        // Generate impersonation token
        $tokenData = $this->impersonationService->generateImpersonationToken($user, $impersonator);

        // Security audit logging for impersonation start
        $this->securityLogger->logAsync(
            eventType: 'auth.impersonation_started',
            description: "Super admin '{$impersonator->name}' started impersonating '{$user->name}'",
            context: [
                'impersonator_id' => $impersonator->id,
                'impersonator_email' => $impersonator->email,
                'impersonator_name' => $impersonator->name,
                'target_user_id' => $user->id,
                'target_user_email' => $user->email,
                'target_user_name' => $user->name,
                'expires_at' => $tokenData['expiresAt']->toIso8601String(),
            ],
            tenantId: $user->tenant_id,
            userId: $impersonator->id,
            request: $request,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $tokenData['token'],
                'expires_at' => $tokenData['expiresAt']->toIso8601String(),
                'impersonated_user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'impersonated_by' => [
                    'id' => $impersonator->id,
                    'name' => $impersonator->name,
                    'email' => $impersonator->email,
                ],
            ],
            'message' => 'Impersonation token generated successfully. Token expires in 15 minutes.',
            'warning' => 'You are now acting as this user. All actions will be logged.',
        ]);
    }

    /**
     * Stop impersonating a user (revoke current impersonation token).
     */
    public function stopImpersonating(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user->currentAccessToken();

        if (! $token) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'No active token found',
                ],
            ], 404);
        }

        // Check if this is an impersonation token by checking all user tokens
        $isImpersonationToken = false;
        $impersonatorId = null;
        foreach ($user->tokens as $userToken) {
            if ($userToken->id === $token->id && str_starts_with($userToken->name, 'impersonation_')) {
                $isImpersonationToken = true;
                // Extract impersonator ID from token name
                $impersonatorId = (int) substr($userToken->name, strlen('impersonation_'));
                break;
            }
        }

        if (! $isImpersonationToken) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_REQUEST',
                    'message' => 'Current token is not an impersonation token',
                ],
            ], 422);
        }

        // Revoke the token
        $token->delete();

        // Security audit logging for impersonation end
        $this->securityLogger->logAsync(
            eventType: 'auth.impersonation_ended',
            description: "Impersonation session ended for user '{$user->name}'",
            context: [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_name' => $user->name,
                'impersonator_id' => $impersonatorId,
            ],
            tenantId: $user->tenant_id,
            userId: $user->id,
            request: $request,
        );

        return response()->json([
            'success' => true,
            'message' => 'Impersonation session ended successfully',
        ]);
    }

    /**
     * Get active impersonation sessions for a user.
     */
    public function impersonationSessions(User $user): JsonResponse
    {
        $sessions = $this->impersonationService->getActiveImpersonations($user);

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'active_sessions' => count($sessions),
                'sessions' => $sessions,
            ],
        ]);
    }

    /**
     * Revoke all impersonation tokens for a user.
     */
    public function revokeImpersonationTokens(User $user): JsonResponse
    {
        $revoked = $this->impersonationService->revokeImpersonationTokens($user);

        return response()->json([
            'success' => true,
            'data' => [
                'tokens_revoked' => $revoked,
            ],
            'message' => "Successfully revoked {$revoked} impersonation token(s)",
        ]);
    }
}
