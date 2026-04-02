<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display a listing of notifications (super admin view).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Notification::query()
            ->with(['user', 'tenant'])
            ->orderBy('created_at', 'desc');

        // Filter by tenant
        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by priority
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filter by read status
        if ($request->filled('read_status')) {
            if ($request->read_status === 'read') {
                $query->whereNotNull('read_at');
            } elseif ($request->read_status === 'unread') {
                $query->whereNull('read_at');
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
        $notifications = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * Display the specified notification.
     */
    public function show($notificationId): JsonResponse
    {
        $notification = Notification::with(['user', 'tenant'])->findOrFail($notificationId);

        return response()->json([
            'success' => true,
            'data' => $notification,
        ]);
    }

    /**
     * Get unread notification count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $query = Notification::query()->unread();

        // Filter by tenant
        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $count = $query->count();

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead($notificationId): JsonResponse
    {
        $notification = Notification::findOrFail($notificationId);
        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'data' => $notification,
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $query = Notification::query()->unread();

        // Filter by tenant
        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $updated = $query->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
            'data' => [
                'marked_count' => $updated,
            ],
        ]);
    }

    /**
     * Delete a notification.
     */
    public function destroy($notificationId): JsonResponse
    {
        $notification = Notification::findOrFail($notificationId);
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully',
        ]);
    }

    /**
     * Delete multiple notifications.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'notification_ids' => 'required|array|min:1',
            'notification_ids.*' => 'required|integer|exists:notifications,id',
        ]);

        $deleted = Notification::whereIn('id', $request->notification_ids)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notifications deleted successfully',
            'data' => [
                'deleted_count' => $deleted,
            ],
        ]);
    }

    /**
     * Get notification statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $query = Notification::query();

        // Filter by tenant
        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $total = $query->count();
        $unread = (clone $query)->unread()->count();
        $read = $total - $unread;

        // Count by priority
        $lowPriority = (clone $query)->where('priority', 'low')->count();
        $mediumPriority = (clone $query)->where('priority', 'medium')->count();
        $highPriority = (clone $query)->where('priority', 'high')->count();
        $urgentPriority = (clone $query)->where('priority', 'urgent')->count();

        // Count by type
        $byType = (clone $query)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->get()
            ->pluck('count', 'type');

        // Recent notifications (last 7 days)
        $recentCount = (clone $query)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'unread' => $unread,
                'read' => $read,
                'by_priority' => [
                    'low' => $lowPriority,
                    'medium' => $mediumPriority,
                    'high' => $highPriority,
                    'urgent' => $urgentPriority,
                ],
                'by_type' => $byType,
                'recent_7_days' => $recentCount,
            ],
        ]);
    }
}
