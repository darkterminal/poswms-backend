<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreReportTemplateRequest;
use App\Http\Requests\Admin\UpdateReportTemplateRequest;
use App\Models\ReportTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportTemplateController extends Controller
{
    /**
     * Display a listing of report templates.
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type');
        $isGlobal = $request->query('is_global');
        $isActive = $request->query('is_active');
        $search = $request->query('search');
        $perPage = $request->query('per_page', 15);
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');

        $user = $request->user();
        
        $query = ReportTemplate::query()
            ->with(['createdBy', 'updatedBy', 'tenant']);
        
        // Super admins see all templates, tenant users see only their templates + global
        if (! $user->is_super_admin && $user->tenant_id) {
            $query->forTenant($user->tenant_id);
        }

        // Apply filters
        if ($type) {
            $query->where('type', $type);
        }

        if ($isGlobal !== null) {
            $query->where('is_global', filter_var($isGlobal, FILTER_VALIDATE_BOOLEAN));
        }

        if ($isActive !== null) {
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $validSortFields = ['name', 'type', 'created_at', 'updated_at', 'is_global', 'is_active'];
        $sortField = in_array($sortBy, $validSortFields) ? $sortBy : 'created_at';
        $sortDirection = $sortOrder === 'asc' ? 'asc' : 'desc';

        $templates = $query->orderBy($sortField, $sortDirection)->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'templates' => $templates->items(),
                'pagination' => [
                    'current_page' => $templates->currentPage(),
                    'per_page' => $templates->perPage(),
                    'total' => $templates->total(),
                    'total_pages' => $templates->lastPage(),
                    'has_more' => $templates->hasMorePages(),
                ],
            ],
            'message' => 'Report templates retrieved successfully',
        ], 200);
    }

    /**
     * Store a newly created report template.
     */
    public function store(StoreReportTemplateRequest $request): JsonResponse
    {
        $template = ReportTemplate::create([
            'tenant_id' => $request->user()->tenant_id,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'type' => $request->input('type'),
            'config' => $request->input('config'),
            'is_global' => $request->boolean('is_global', false),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $template->load(['createdBy', 'updatedBy', 'tenant']);

        return response()->json([
            'success' => true,
            'data' => ['template' => $template],
            'message' => 'Report template created successfully',
        ], 201);
    }

    /**
     * Display the specified report template.
     */
    public function show(int $id): JsonResponse
    {
        $template = ReportTemplate::with(['createdBy', 'updatedBy', 'tenant', 'savedReports', 'scheduledReports'])->findOrFail($id);

        // Check if user has access (tenant scope or global)
        $user = request()->user();
        $isAccessible = $template->is_global
            || ($template->tenant_id && $template->tenant_id === $user->tenant_id)
            || $user->is_super_admin;

        if (! $isAccessible) {
            return response()->json([
                'success' => false,
                'message' => 'Report template not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => ['template' => $template],
            'message' => 'Report template retrieved successfully',
        ], 200);
    }

    /**
     * Update the specified report template.
     */
    public function update(UpdateReportTemplateRequest $request, int $id): JsonResponse
    {
        $template = ReportTemplate::findOrFail($id);

        // Check if user has access (tenant scope or global)
        $user = $request->user();
        $isAccessible = $template->is_global
            || ($template->tenant_id && $template->tenant_id === $user->tenant_id)
            || $user->is_super_admin;

        if (! $isAccessible) {
            return response()->json([
                'success' => false,
                'message' => 'Report template not found',
            ], 404);
        }

        // Prevent non-super-admins from modifying global templates
        if ($template->is_global && ! $user->is_super_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Only super admins can modify global templates',
            ], 403);
        }

        $template->update([
            'name' => $request->input('name', $template->name),
            'description' => $request->input('description', $template->description),
            'type' => $request->input('type', $template->type),
            'config' => $request->input('config', $template->config),
            'is_global' => $request->boolean('is_global', $template->is_global),
            'is_active' => $request->boolean('is_active', $template->is_active),
            'updated_by' => $user->id,
        ]);

        $template->load(['createdBy', 'updatedBy', 'tenant']);

        return response()->json([
            'success' => true,
            'data' => ['template' => $template],
            'message' => 'Report template updated successfully',
        ], 200);
    }

    /**
     * Remove the specified report template.
     */
    public function destroy(int $id): JsonResponse
    {
        $template = ReportTemplate::findOrFail($id);

        // Check if user has access
        $user = request()->user();
        $isAccessible = $template->is_global
            || ($template->tenant_id && $template->tenant_id === $user->tenant_id)
            || $user->is_super_admin;

        if (! $isAccessible) {
            return response()->json([
                'success' => false,
                'message' => 'Report template not found',
            ], 404);
        }

        // Prevent non-super-admins from deleting global templates
        if ($template->is_global && ! $user->is_super_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Only super admins can delete global templates',
            ], 403);
        }

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Report template deleted successfully',
        ], 200);
    }

    /**
     * Duplicate an existing report template.
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        $template = ReportTemplate::findOrFail($id);

        // Check if user has access
        $user = $request->user();
        $isAccessible = $template->is_global
            || ($template->tenant_id && $template->tenant_id === $user->tenant_id)
            || $user->is_super_admin;

        if (! $isAccessible) {
            return response()->json([
                'success' => false,
                'message' => 'Report template not found',
            ], 404);
        }

        $newTemplate = ReportTemplate::create([
            'tenant_id' => $user->tenant_id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'name' => $template->name . ' (Copy)',
            'description' => $template->description,
            'type' => $template->type,
            'config' => $template->config,
            'is_global' => false, // Duplicates are never global by default
            'is_active' => $template->is_active,
        ]);

        $newTemplate->load(['createdBy', 'updatedBy', 'tenant']);

        return response()->json([
            'success' => true,
            'data' => ['template' => $newTemplate],
            'message' => 'Report template duplicated successfully',
        ], 201);
    }

    /**
     * Get available report types.
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'types' => [
                    [
                        'value' => 'sales',
                        'label' => 'Sales Reports',
                        'description' => 'Revenue, orders, and product performance analytics',
                    ],
                    [
                        'value' => 'inventory',
                        'label' => 'Inventory Reports',
                        'description' => 'Stock levels, movements, and low stock alerts',
                    ],
                    [
                        'value' => 'customer',
                        'label' => 'Customer Reports',
                        'description' => 'Customer analytics and segmentation',
                    ],
                    [
                        'value' => 'custom',
                        'label' => 'Custom Reports',
                        'description' => 'Custom configured reports',
                    ],
                ],
            ],
            'message' => 'Report types retrieved successfully',
        ], 200);
    }
}
