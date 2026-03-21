<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Display a listing of audit logs.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');

        $query = AuditLog::query()
            ->where('tenant_id', $tenantId)
            ->with(['user', 'tenant'])
            ->orderBy('created_at', 'desc');

        // Filter by event type
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by auditable model
        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->auditable_type);
            if ($request->filled('auditable_id')) {
                $query->where('auditable_id', $request->auditable_id);
            }
        }

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $perPage = $request->get('per_page', 20);
        $auditLogs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $auditLogs->items(),
            'meta' => [
                'current_page' => $auditLogs->currentPage(),
                'last_page' => $auditLogs->lastPage(),
                'per_page' => $auditLogs->perPage(),
                'total' => $auditLogs->total(),
            ],
        ]);
    }

    /**
     * Display the specified audit log.
     */
    public function show(Request $request): JsonResponse
    {
        $auditLogId = $request->route('audit_log');

        $auditLog = AuditLog::with(['user', 'tenant'])->findOrFail($auditLogId);

        return response()->json([
            'success' => true,
            'data' => $auditLog,
        ]);
    }

    /**
     * Get audit logs for a specific user.
     */
    public function byUser(Request $request): JsonResponse
    {
        $userId = $request->route('userId');

        $user = User::findOrFail($userId);

        $auditLogs = AuditLog::query()
            ->where('user_id', $userId)
            ->with(['tenant'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $auditLogs->items(),
            'meta' => [
                'current_page' => $auditLogs->currentPage(),
                'last_page' => $auditLogs->lastPage(),
                'per_page' => $auditLogs->perPage(),
                'total' => $auditLogs->total(),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
        ]);
    }

    /**
     * Get audit summary statistics.
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');

        $query = AuditLog::query()
            ->where('tenant_id', $tenantId);

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $totalEvents = $query->count();
        $eventsByType = clone $query;
        $eventsByUser = clone $query;

        return response()->json([
            'success' => true,
            'data' => [
                'total_events' => $totalEvents,
                'by_event_type' => $eventsByType->selectRaw('event_type, COUNT(*) as count')
                    ->groupBy('event_type')
                    ->get()
                    ->pluck('count', 'event_type'),
                'by_user' => $eventsByUser->selectRaw('user_id, COUNT(*) as count')
                    ->groupBy('user_id')
                    ->with('user:id,name,email')
                    ->get()
                    ->map(fn($item) => [
                        'user' => $item->user?->name ?? 'Unknown',
                        'count' => $item->count,
                    ]),
            ],
        ]);
    }
}
