<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
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
    public function show(Request $request, int $tenant_id, int $id): JsonResponse
    {
        $role = Role::where('tenant_id', $tenant_id)->findOrFail($id);

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
    public function update(Request $request, int $tenant_id, int $id): JsonResponse
    {
        $role = Role::where('tenant_id', $tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:roles,slug,' . $id . ',id,tenant_id,' . $tenant_id,
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
        ]);

        $role->update($validated);

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
    public function destroy(Request $request, int $tenant_id, int $id): JsonResponse
    {
        $role = Role::where('tenant_id', $tenant_id)->findOrFail($id);

        if ($role->is_system) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete system role.',
            ], 403);
        }

        $role->delete();

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

        return response()->json([
            'success' => true,
            'message' => 'Role removed successfully.',
        ], 200);
    }
}
