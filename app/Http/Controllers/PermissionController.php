<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * Display a listing of permissions for the tenant.
     */
    public function index(Request $request, int $tenant_id): JsonResponse
    {
        $query = Permission::where('tenant_id', $tenant_id);

        // Filter by group if provided
        if ($request->has('group')) {
            $query->where('group', $request->group);
        }

        // Group by permission group
        $permissions = $query->orderBy('group')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'permissions' => $permissions,
            ],
            'message' => 'Permissions retrieved successfully',
        ], 200);
    }

    /**
     * Store a newly created permission.
     */
    public function store(Request $request, int $tenant_id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:permissions,slug,NULL,id,tenant_id,'.$tenant_id,
            'group' => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        $permission = Permission::create([
            'tenant_id' => $tenant_id,
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'group' => $validated['group'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'permission' => $permission,
            ],
            'message' => 'Permission created successfully',
        ], 201);
    }

    /**
     * Display the specified permission.
     */
    public function show(Request $request, int $tenant_id, int $id): JsonResponse
    {
        $permission = Permission::where('tenant_id', $tenant_id)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'permission' => $permission,
            ],
            'message' => 'Permission retrieved successfully',
        ], 200);
    }

    /**
     * Update the specified permission.
     */
    public function update(Request $request, int $tenant_id, int $id): JsonResponse
    {
        $permission = Permission::where('tenant_id', $tenant_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:permissions,slug,'.$id.',id,tenant_id,'.$tenant_id,
            'group' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string',
        ]);

        $permission->update($validated);

        return response()->json([
            'success' => true,
            'data' => [
                'permission' => $permission->fresh(),
            ],
            'message' => 'Permission updated successfully',
        ], 200);
    }

    /**
     * Remove the specified permission.
     */
    public function destroy(Request $request, int $tenant_id, int $id): JsonResponse
    {
        $permission = Permission::where('tenant_id', $tenant_id)->findOrFail($id);

        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully.',
        ], 200);
    }
}
