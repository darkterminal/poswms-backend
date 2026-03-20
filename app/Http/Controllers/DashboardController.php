<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Order;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private CacheService $cacheService,
    ) {}

    /**
     * Get unified dashboard metrics for tenant admin.
     */
    public function index(Request $request, int $tenantId): JsonResponse
    {
        $period = $request->query('period', 'today');

        $data = $this->cacheService->rememberDashboardMetrics($tenantId, $period, function () use ($tenantId, $period) {
            $dateRange = $this->getDateRange($period);

            return [
                'period' => $period,
                'sales' => $this->getSalesMetrics($tenantId, $dateRange),
                'inventory' => $this->getInventoryMetrics($tenantId),
                'orders' => $this->getOrderMetrics($tenantId, $dateRange),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get sales metrics.
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
            ->when($previousDateRange['start'], fn($q) => $q->whereDate('created_at', '>=', $previousDateRange['start']))
            ->when($previousDateRange['end'], fn($q) => $q->whereDate('created_at', '<=', $previousDateRange['end']))
            ->sum('subtotal');

        $previousOrders = Order::where('tenant_id', $tenantId)
            ->whereIn('status', ['confirmed', 'fulfilled'])
            ->when($previousDateRange['start'], fn($q) => $q->whereDate('created_at', '>=', $previousDateRange['start']))
            ->when($previousDateRange['end'], fn($q) => $q->whereDate('created_at', '<=', $previousDateRange['end']))
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
     * Get inventory metrics.
     */
    private function getInventoryMetrics(int $tenantId): array
    {
        return $this->cacheService->rememberInventoryMetrics($tenantId, function () use ($tenantId) {
            // Use optimized query instead of loading all inventories
            $totalProducts = Inventory::forTenant($tenantId)->count();
            $totalQuantity = Inventory::forTenant($tenantId)->sum('quantity');
            $totalAvailable = Inventory::forTenant($tenantId)->sum('available');
            $totalReserved = Inventory::forTenant($tenantId)->sum('reserved');
            $totalValue = Inventory::forTenant($tenantId)
                ->selectRaw('SUM(quantity * COALESCE(cost, 0)) as value')
                ->value('value') ?? 0;

            // Low stock and out of stock counts using optimized queries
            $lowStockCount = Inventory::forTenant($tenantId)
                ->join('products', 'inventories.product_id', '=', 'products.id')
                ->whereColumn('inventories.available', '<=', 'products.min_stock')
                ->count();

            $outOfStockCount = Inventory::forTenant($tenantId)
                ->where('available', 0)
                ->count();

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
        });
    }

    /**
     * Get order metrics.
     */
    private function getOrderMetrics(int $tenantId, array $dateRange): array
    {
        // Use optimized query instead of loading all orders
        $statusCounts = Order::getSummaryForTenant($tenantId);

        // Today's orders
        $todaysOrders = Order::forTenant($tenantId)
            ->whereDate('created_at', '>=', now()->startOfDay())
            ->count();

        // Pending fulfillment
        $pendingFulfillment = Order::forTenant($tenantId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();

        return [
            'status_counts' => $statusCounts,
            'todays_orders' => $todaysOrders,
            'pending_fulfillment' => $pendingFulfillment,
        ];
    }

    /**
     * Get date range for metrics.
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
     * Get previous date range for comparison.
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
