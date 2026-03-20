<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get unified dashboard metrics for tenant admin
     */
    public function index(Request $request, int $tenantId): JsonResponse
    {
        $period = $request->query('period', 'today'); // today, week, month, year, all
        $dateRange = $this->getDateRange($period);

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'sales' => $this->getSalesMetrics($tenantId, $dateRange),
                'inventory' => $this->getInventoryMetrics($tenantId),
                'orders' => $this->getOrderMetrics($tenantId, $dateRange),
            ],
        ]);
    }

    /**
     * Get sales metrics
     */
    private function getSalesMetrics(int $tenantId, array $dateRange): array
    {
        $revenueQuery = Order::where('tenant_id', $tenantId)
            ->whereIn('status', ['confirmed', 'fulfilled']);

        if ($dateRange['start']) {
            $revenueQuery->whereDate('created_at', '>=', $dateRange['start']);
        }

        if ($dateRange['end']) {
            $revenueQuery->whereDate('created_at', '<=', $dateRange['end']);
        }

        $currentRevenue = $revenueQuery->sum('subtotal');
        $currentOrders = $revenueQuery->count();

        // Previous period comparison
        $previousDateRange = $this->getPreviousDateRange($dateRange);
        $previousRevenue = Order::where('tenant_id', $tenantId)
            ->whereIn('status', ['confirmed', 'fulfilled'])
            ->when($previousDateRange['start'], fn ($q) => $q->whereDate('created_at', '>=', $previousDateRange['start']))
            ->when($previousDateRange['end'], fn ($q) => $q->whereDate('created_at', '<=', $previousDateRange['end']))
            ->sum('subtotal');

        $previousOrders = Order::where('tenant_id', $tenantId)
            ->whereIn('status', ['confirmed', 'fulfilled'])
            ->when($previousDateRange['start'], fn ($q) => $q->whereDate('created_at', '>=', $previousDateRange['start']))
            ->when($previousDateRange['end'], fn ($q) => $q->whereDate('created_at', '<=', $previousDateRange['end']))
            ->count();

        $revenueGrowth = $previousRevenue > 0 ? (($currentRevenue - $previousRevenue) / $previousRevenue) * 100 : 0;
        $ordersGrowth = $previousOrders > 0 ? (($currentOrders - $previousOrders) / $previousOrders) * 100 : 0;

        // Average order value
        $avgOrderValue = $currentOrders > 0 ? $currentRevenue / $currentOrders : 0;

        return [
            'revenue' => [
                'current' => round($currentRevenue, 2),
                'previous' => round($previousRevenue, 2),
                'growth_percentage' => round($revenueGrowth, 2),
            ],
            'orders_count' => [
                'current' => $currentOrders,
                'previous' => $previousOrders,
                'growth_percentage' => round($ordersGrowth, 2),
            ],
            'average_order_value' => round($avgOrderValue, 2),
        ];
    }

    /**
     * Get inventory metrics
     */
    private function getInventoryMetrics(int $tenantId): array
    {
        $inventories = Inventory::where('tenant_id', $tenantId)
            ->with(['product'])
            ->get();

        $totalProducts = $inventories->count();
        $totalQuantity = $inventories->sum('quantity');
        $totalAvailable = $inventories->sum('available');
        $totalReserved = $inventories->sum('reserved');
        $totalValue = $inventories->sum(fn ($i) => $i->quantity * ($i->cost ?? 0));

        // Low stock and out of stock counts
        $lowStockCount = $inventories->filter(function ($inventory) {
            return $inventory->product && $inventory->available <= $inventory->product->min_stock;
        })->count();

        $outOfStockCount = $inventories->filter(fn ($i) => $i->available === 0)->count();

        // Inventory health percentage
        $healthPercentage = $totalProducts > 0
            ? round((($totalProducts - $lowStockCount) / $totalProducts) * 100, 2)
            : 100;

        return [
            'total_products' => $totalProducts,
            'total_quantity' => $totalQuantity,
            'total_available' => $totalAvailable,
            'total_reserved' => $totalReserved,
            'total_value' => round($totalValue, 2),
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
            'health_percentage' => $healthPercentage,
        ];
    }

    /**
     * Get order metrics
     */
    private function getOrderMetrics(int $tenantId, array $dateRange): array
    {
        // Order status counts (all time for the tenant)
        $allOrders = Order::where('tenant_id', $tenantId)->get();

        $statusCounts = [
            'total' => $allOrders->count(),
            'pending' => $allOrders->where('status', 'pending')->count(),
            'confirmed' => $allOrders->where('status', 'confirmed')->count(),
            'fulfilled' => $allOrders->where('status', 'fulfilled')->count(),
            'cancelled' => $allOrders->where('status', 'cancelled')->count(),
        ];

        // Today's orders
        $todaysOrders = Order::where('tenant_id', $tenantId)
            ->whereDate('created_at', '>=', now()->startOfDay())
            ->count();

        // Pending fulfillment
        $pendingFulfillment = Order::where('tenant_id', $tenantId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();

        return [
            'status_counts' => $statusCounts,
            'todays_orders' => $todaysOrders,
            'pending_fulfillment' => $pendingFulfillment,
        ];
    }

    /**
     * Get date range for metrics
     */
    private function getDateRange(string $period): array
    {
        return match ($period) {
            'today' => ['start' => now()->startOfDay(), 'end' => now()->endOfDay()],
            'week' => ['start' => now()->startOfWeek(), 'end' => now()->endOfWeek()],
            'month' => ['start' => now()->startOfMonth(), 'end' => now()->endOfMonth()],
            'year' => ['start' => now()->startOfYear(), 'end' => now()->endOfYear()],
            'all' => ['start' => null, 'end' => null],
            default => ['start' => now()->startOfDay(), 'end' => now()->endOfDay()],
        };
    }

    /**
     * Get previous date range for comparison
     */
    private function getPreviousDateRange(array $currentRange): array
    {
        if (! $currentRange['start'] || ! $currentRange['end']) {
            return ['start' => null, 'end' => null];
        }

        $start = $currentRange['start'];
        $end = $currentRange['end'];

        $daysDiff = $start->diffInDays($end) + 1;

        return [
            'start' => $start->copy()->subDays($daysDiff),
            'end' => $end->copy()->subDays($daysDiff)->endOfDay(),
        ];
    }
}
