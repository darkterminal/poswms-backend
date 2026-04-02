<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSavedReportRequest;
use App\Models\SavedReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SavedReportController extends Controller
{
    /**
     * Display a listing of saved reports.
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type');
        $search = $request->query('search');
        $perPage = $request->query('per_page', 15);
        $sortBy = $request->query('sort_by', 'generated_at');
        $sortOrder = $request->query('sort_order', 'desc');
        $includeExpired = $request->query('include_expired', false);

        $user = $request->user();

        $query = SavedReport::query()
            ->with(['template', 'createdBy', 'tenant']);

        // Super admins see all reports, tenant users see only their reports
        if (! $user->is_super_admin && $user->tenant_id) {
            $query->forTenant($user->tenant_id);
        }

        // Include expired if requested
        if (! $includeExpired) {
            $query->notExpired();
        }

        // Apply filters
        if ($type) {
            $query->ofType($type);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $validSortFields = ['name', 'type', 'generated_at', 'created_at'];
        $sortField = in_array($sortBy, $validSortFields) ? $sortBy : 'generated_at';
        $sortDirection = $sortOrder === 'asc' ? 'asc' : 'desc';

        $reports = $query->orderBy($sortField, $sortDirection)->paginate($perPage);

        // Add file URL to each report
        $reportsData = collect($reports->items())->map(function ($report) {
            $item = $report->toArray();
            $item['file_url'] = $report->getFileUrl();
            $item['formatted_file_size'] = $report->getFormattedFileSize();
            $item['is_expired'] = $report->isExpired();
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'reports' => $reportsData,
                'pagination' => [
                    'current_page' => $reports->currentPage(),
                    'per_page' => $reports->perPage(),
                    'total' => $reports->total(),
                    'total_pages' => $reports->lastPage(),
                    'has_more' => $reports->hasMorePages(),
                ],
            ],
            'message' => 'Saved reports retrieved successfully',
        ], 200);
    }

    /**
     * Store a newly created saved report.
     */
    public function store(StoreSavedReportRequest $request): JsonResponse
    {
        $user = $request->user();
        
        // Super admins can specify tenant_id, tenant users use their own
        $tenantId = $user->is_super_admin 
            ? ($request->input('tenant_id') ?? $user->tenant_id)
            : $user->tenant_id;

        if (! $tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant ID is required',
            ], 422);
        }

        $report = SavedReport::create([
            'tenant_id' => $tenantId,
            'template_id' => $request->input('template_id'),
            'created_by' => $user->id,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'type' => $request->input('type'),
            'filters' => $request->input('filters'),
            'data' => $request->input('data'),
            'generated_at' => now(),
            'expires_at' => $request->input('expires_at'),
        ]);

        $report->load(['template', 'createdBy', 'tenant']);

        return response()->json([
            'success' => true,
            'data' => [
                'report' => $report,
                'file_url' => $report->getFileUrl(),
            ],
            'message' => 'Report saved successfully',
        ], 201);
    }

    /**
     * Display the specified saved report.
     */
    public function show(SavedReport $saved_report, Request $request): JsonResponse
    {
        $report = $saved_report->load(['template', 'createdBy', 'tenant']);

        // Check if user has access to this report
        if ($report->tenant_id !== $request->user()->tenant_id && ! $request->user()->is_super_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Saved report not found',
            ], 404);
        }

        $reportData = $report->toArray();
        $reportData['file_url'] = $report->getFileUrl();
        $reportData['formatted_file_size'] = $report->getFormattedFileSize();
        $reportData['is_expired'] = $report->isExpired();

        return response()->json([
            'success' => true,
            'data' => ['report' => $reportData],
            'message' => 'Saved report retrieved successfully',
        ], 200);
    }

    /**
     * Update the specified saved report.
     */
    public function update(Request $request, SavedReport $saved_report): JsonResponse
    {
        $report = $saved_report;

        // Check if user has access
        if ($report->tenant_id !== $request->user()->tenant_id && ! $request->user()->is_super_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Saved report not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $report->update($validated);
        $report->load(['template', 'createdBy', 'tenant']);

        return response()->json([
            'success' => true,
            'data' => ['report' => $report],
            'message' => 'Saved report updated successfully',
        ], 200);
    }

    /**
     * Remove the specified saved report.
     */
    public function destroy(Request $request, SavedReport $saved_report): JsonResponse
    {
        $report = $saved_report;

        // Check if user has access
        if ($report->tenant_id !== $request->user()->tenant_id && ! $request->user()->is_super_admin) {
            return response()->json([
                'success' => false,
                'message' => 'Saved report not found',
            ], 404);
        }

        // Delete associated file if exists
        if ($report->file_path && Storage::disk('public')->exists($report->file_path)) {
            Storage::disk('public')->delete($report->file_path);
        }

        $report->delete();

        return response()->json([
            'success' => true,
            'message' => 'Saved report deleted successfully',
        ], 200);
    }

    /**
     * Download the saved report file.
     */
    public function download(Request $request, SavedReport $saved_report): StreamedResponse
    {
        $report = $saved_report;

        // Check if user has access
        if ($report->tenant_id !== $request->user()->tenant_id && ! $request->user()->is_super_admin) {
            abort(404, 'Saved report not found');
        }

        // Check if report has a file
        if (! $report->file_path) {
            abort(404, 'No file available for download');
        }

        // Check if file exists
        if (! Storage::disk('public')->exists($report->file_path)) {
            abort(404, 'File not found');
        }

        // Increment download count (could be tracked in a separate column if needed)

        return Storage::disk('public')->download($report->file_path, $report->name . '.' . $report->file_format);
    }

    /**
     * Get saved report statistics for the tenant.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = SavedReport::query();

        // Super admins see all reports, tenant users see only their reports
        if (! $user->is_super_admin && $user->tenant_id) {
            $query->forTenant($user->tenant_id);
        }

        $total = $query->notExpired()->count();
        
        $byType = (clone $query)->notExpired()
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->get()
            ->pluck('count', 'type');

        $withFiles = (clone $query)->notExpired()
            ->whereNotNull('file_path')
            ->count();

        $expiringSoon = (clone $query)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(7)])
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'by_type' => [
                    'sales' => $byType->get('sales', 0),
                    'inventory' => $byType->get('inventory', 0),
                    'customer' => $byType->get('customer', 0),
                    'custom' => $byType->get('custom', 0),
                ],
                'with_files' => $withFiles,
                'expiring_soon' => $expiringSoon,
            ],
            'message' => 'Saved report statistics retrieved successfully',
        ], 200);
    }
}
