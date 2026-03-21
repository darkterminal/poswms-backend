<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SearchUsersRequest;
use App\Models\User;
use App\Services\ImpersonationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private ImpersonationService $impersonationService
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

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

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
                'sorting' => ['by' => $sortBy, 'order' => $sortOrder],
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
        foreach ($user->tokens as $userToken) {
            if ($userToken->id === $token->id && str_starts_with($userToken->name, 'impersonation_')) {
                $isImpersonationToken = true;
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
