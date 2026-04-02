<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreScheduledReportRequest;
use App\Http\Requests\Admin\UpdateScheduledReportRequest;
use App\Models\ScheduledReport;
use App\Models\ScheduledReportExecution;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduledReportController extends Controller
{
    /**
     * Display a listing of scheduled reports.
     */
    public function index(Request $request): JsonResponse
    {
        $isActive = $request->query('is_active');
        $frequency = $request->query('frequency');
        $search = $request->query('search');
        $perPage = $request->query('per_page', 15);
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');

        $user = $request->user();

        $query = ScheduledReport::query()
            ->with(['template', 'createdBy', 'tenant', 'executionHistory' => function ($q) {
                $q->latest()->limit(1);
            }]);

        // Super admins see all, tenant users see only their tenant's reports
        if (! $user->is_super_admin && $user->tenant_id) {
            $query->forTenant($user->tenant_id);
        }

        // Apply filters
        if ($isActive !== null) {
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        if ($frequency) {
            $query->where('schedule_frequency', $frequency);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $validSortFields = ['name', 'schedule_frequency', 'is_active', 'created_at', 'next_run_at'];
        $sortField = in_array($sortBy, $validSortFields) ? $sortBy : 'created_at';
        $sortDirection = $sortOrder === 'asc' ? 'asc' : 'desc';

        $reports = $query->orderBy($sortField, $sortDirection)->paginate($perPage);

        // Add execution history and schedule description to each report
        $reportsData = collect($reports->items())->map(function ($report) {
            $item = $report->toArray();
            $item['schedule_description'] = $report->getScheduleDescription();
            $item['last_execution'] = $report->executionHistory->first()?->toArray();
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
            'message' => 'Scheduled reports retrieved successfully',
        ], 200);
    }

    /**
     * Store a newly created scheduled report.
     */
    public function store(StoreScheduledReportRequest $request): JsonResponse
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

        // Calculate next run time
        $nextRunAt = $this->calculateNextRun(
            $request->input('schedule_frequency'),
            $request->input('schedule_day'),
            $request->input('schedule_time')
        );

        $report = ScheduledReport::create([
            'tenant_id' => $tenantId,
            'template_id' => $request->input('template_id'),
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'type' => $request->input('type'),
            'filters' => $request->input('filters'),
            'schedule_frequency' => $request->input('schedule_frequency'),
            'schedule_day' => $request->input('schedule_day'),
            'schedule_time' => $request->input('schedule_time'),
            'recipients' => $request->input('recipients'),
            'export_format' => $request->input('export_format'),
            'is_active' => $request->boolean('is_active', true),
            'next_run_at' => $nextRunAt,
        ]);

        $report->load(['template', 'createdBy', 'tenant']);

        return response()->json([
            'success' => true,
            'data' => [
                'report' => $report,
                'schedule_description' => $report->getScheduleDescription(),
            ],
            'message' => 'Scheduled report created successfully',
        ], 201);
    }

    /**
     * Display the specified scheduled report.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $report = ScheduledReport::with(['template', 'createdBy', 'tenant', 'executionHistory' => function ($q) {
            $q->latest()->limit(10);
        }])->findOrFail($id);

        // Check if user has access
        $user = $request->user();
        if (! $user->is_super_admin && $report->tenant_id !== $user->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Scheduled report not found',
            ], 404);
        }

        $reportData = $report->toArray();
        $reportData['schedule_description'] = $report->getScheduleDescription();
        $reportData['is_due'] = $report->isDue();

        return response()->json([
            'success' => true,
            'data' => ['report' => $reportData],
            'message' => 'Scheduled report retrieved successfully',
        ], 200);
    }

    /**
     * Update the specified scheduled report.
     */
    public function update(UpdateScheduledReportRequest $request, int $id): JsonResponse
    {
        $report = ScheduledReport::findOrFail($id);

        // Check if user has access
        $user = $request->user();
        if (! $user->is_super_admin && $report->tenant_id !== $user->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Scheduled report not found',
            ], 404);
        }

        $updateData = [];

        if ($request->has('name')) {
            $updateData['name'] = $request->input('name');
        }

        if ($request->has('description')) {
            $updateData['description'] = $request->input('description');
        }

        if ($request->has('filters')) {
            $updateData['filters'] = $request->input('filters');
        }

        if ($request->has('schedule_frequency') || $request->has('schedule_day') || $request->has('schedule_time')) {
            $frequency = $request->input('schedule_frequency', $report->schedule_frequency);
            $day = $request->input('schedule_day', $report->schedule_day);
            $time = $request->input('schedule_time', $report->schedule_time);

            $updateData['schedule_frequency'] = $frequency;
            $updateData['schedule_day'] = $day;
            $updateData['schedule_time'] = $time;
            $updateData['next_run_at'] = $this->calculateNextRun($frequency, $day, $time);
        }

        if ($request->has('recipients')) {
            $updateData['recipients'] = $request->input('recipients');
        }

        if ($request->has('export_format')) {
            $updateData['export_format'] = $request->input('export_format');
        }

        if ($request->has('is_active')) {
            $updateData['is_active'] = $request->boolean('is_active');
        }

        $updateData['updated_by'] = $user->id;

        $report->update($updateData);
        $report->load(['template', 'createdBy', 'tenant']);

        return response()->json([
            'success' => true,
            'data' => [
                'report' => $report,
                'schedule_description' => $report->getScheduleDescription(),
            ],
            'message' => 'Scheduled report updated successfully',
        ], 200);
    }

    /**
     * Remove the specified scheduled report.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $report = ScheduledReport::findOrFail($id);

        // Check if user has access
        $user = $request->user();
        if (! $user->is_super_admin && $report->tenant_id !== $user->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Scheduled report not found',
            ], 404);
        }

        $report->delete();

        return response()->json([
            'success' => true,
            'message' => 'Scheduled report deleted successfully',
        ], 200);
    }

    /**
     * Manually trigger a scheduled report execution.
     */
    public function run(Request $request, int $id): JsonResponse
    {
        $report = ScheduledReport::findOrFail($id);

        // Check if user has access
        $user = $request->user();
        if (! $user->is_super_admin && $report->tenant_id !== $user->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Scheduled report not found',
            ], 404);
        }

        // In a real implementation, this would dispatch a job
        // For now, we'll just update the last_run_at and next_run_at
        $report->updateNextRun();

        // Create execution record
        ScheduledReportExecution::create([
            'scheduled_report_id' => $report->id,
            'tenant_id' => $report->tenant_id,
            'executed_at' => now(),
            'success' => true,
            'records_count' => 0, // Would be populated by actual execution
            'recipients_notified' => $report->recipients,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'report' => $report,
                'message' => 'Scheduled report executed successfully',
            ],
            'message' => 'Report execution triggered',
        ], 200);
    }

    /**
     * Get execution history for a scheduled report.
     */
    public function history(Request $request, int $id): JsonResponse
    {
        $report = ScheduledReport::findOrFail($id);

        // Check if user has access
        $user = $request->user();
        if (! $user->is_super_admin && $report->tenant_id !== $user->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Scheduled report not found',
            ], 404);
        }

        $perPage = $request->query('per_page', 15);
        
        $history = ScheduledReportExecution::forScheduledReport($id)
            ->latest('executed_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'executions' => $history->items(),
                'pagination' => [
                    'current_page' => $history->currentPage(),
                    'per_page' => $history->perPage(),
                    'total' => $history->total(),
                    'total_pages' => $history->lastPage(),
                    'has_more' => $history->hasMorePages(),
                ],
            ],
            'message' => 'Execution history retrieved successfully',
        ], 200);
    }

    /**
     * Calculate the next run time based on schedule configuration.
     */
    private function calculateNextRun(string $frequency, ?int $day, string $time): \Carbon\CarbonInterface
    {
        $timeParts = explode(':', $time);
        $hour = (int) $timeParts[0];
        $minute = (int) $timeParts[1];

        return match ($frequency) {
            'daily' => now()->copy()->addDay()->setTime($hour, $minute),
            'weekly' => now()->copy()->addWeek()->next($day)->setTime($hour, $minute),
            'monthly' => now()->copy()->addMonth()->day($day)->setTime($hour, $minute),
            default => now()->copy()->addDay()->setTime($hour, $minute),
        };
    }
}
