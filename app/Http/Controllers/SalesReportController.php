<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesReportController extends Controller
{
    /**
     * Get sales report with revenue analytics
     */
    public function revenue(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $period = $request->query('period', 'daily'); // daily, weekly, monthly, yearly
        $storeId = $request->query('store_id');
        $warehouseId = $request->query('warehouse_id');

        $query = Order::where('tenant_id', $tenantId)
            ->whereIn('status', ['confirmed', 'fulfilled']);

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $orders = $query->get();

        // Group by period in PHP for database agnosticism
        $groupedData = $orders->groupBy(function ($order) use ($period) {
            return $this->formatPeriod($order->created_at, $period);
        });

        $revenueData = $groupedData->map(function ($group, $period) {
            return [
                'period' => $period,
                'order_count' => $group->count(),
                'total_revenue' => round($group->sum('subtotal'), 2),
                'total_tax' => round($group->sum('tax'), 2),
                'total_discount' => round($group->sum('discount'), 2),
                'total_shipping' => round($group->sum('shipping'), 2),
                'avg_order_value' => round($group->avg('subtotal'), 2),
            ];
        })->values();

        $totalRevenue = $orders->sum('subtotal');
        $totalOrders = $orders->count();
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'revenue_by_period' => $revenueData,
                'summary' => [
                    'total_revenue' => round($totalRevenue, 2),
                    'total_orders' => $totalOrders,
                    'average_order_value' => round($avgOrderValue, 2),
                    'total_tax' => round($orders->sum('tax'), 2),
                    'total_discount' => round($orders->sum('discount'), 2),
                    'total_shipping' => round($orders->sum('shipping'), 2),
                ],
            ],
        ]);
    }

    /**
     * Get orders by period
     */
    public function ordersByPeriod(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $period = $request->query('period', 'daily'); // daily, weekly, monthly, yearly
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $status = $request->query('status');

        $query = Order::where('tenant_id', $tenantId);

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $orders = $query->get();

        // Group by period in PHP
        $groupedData = $orders->groupBy(function ($order) use ($period) {
            return $this->formatPeriod($order->created_at, $period);
        });

        $ordersData = $groupedData->map(function ($group, $period) {
            return [
                'period' => $period,
                'order_count' => $group->count(),
                'pending_count' => $group->where('status', 'pending')->count(),
                'confirmed_count' => $group->where('status', 'confirmed')->count(),
                'fulfilled_count' => $group->where('status', 'fulfilled')->count(),
                'cancelled_count' => $group->where('status', 'cancelled')->count(),
                'total_revenue' => round($group->sum('subtotal'), 2),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'orders_by_period' => $ordersData,
                'summary' => [
                    'total_orders' => $ordersData->sum('order_count'),
                    'pending' => $ordersData->sum('pending_count'),
                    'confirmed' => $ordersData->sum('confirmed_count'),
                    'fulfilled' => $ordersData->sum('fulfilled_count'),
                    'cancelled' => $ordersData->sum('cancelled_count'),
                ],
            ],
        ]);
    }

    /**
     * Get top products report
     */
    public function topProducts(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $limit = $request->query('limit', 10);
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $sortBy = $request->query('sort_by', 'quantity'); // quantity, revenue

        $query = OrderItem::where('order_items.tenant_id', $tenantId)
            ->with(['product', 'order'])
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereIn('orders.status', ['confirmed', 'fulfilled']);

        if ($startDate) {
            $query->whereDate('orders.created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('orders.created_at', '<=', $endDate);
        }

        $orderItems = $query->get();

        // Group by product and calculate totals
        $productStats = $orderItems->groupBy('product_id')->map(function ($items) {
            return [
                'product_id' => $items->first()->product_id,
                'total_quantity' => $items->sum('quantity'),
                'total_revenue' => round($items->sum(function ($item) {
                    return $item->unit_price * $item->quantity;
                }), 2),
                'order_count' => $items->pluck('order_id')->unique()->count(),
                'avg_price' => round($items->avg('unit_price'), 2),
            ];
        });

        // Sort by specified field
        $sortedProducts = $sortBy === 'revenue'
            ? $productStats->sortByDesc('total_revenue')
            : $productStats->sortByDesc('total_quantity');

        $topProducts = $sortedProducts->take($limit)->values()->map(function ($item) {
            return [
                'product_id' => $item['product_id'],
                'product' => $this->getProductDetails($item['product_id']),
                'total_quantity' => $item['total_quantity'],
                'total_revenue' => $item['total_revenue'],
                'order_count' => $item['order_count'],
                'avg_price' => $item['avg_price'],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'sort_by' => $sortBy,
                'limit' => $limit,
                'top_products' => $topProducts,
            ],
        ]);
    }

    /**
     * Get dashboard metrics (KPIs for tenant admin)
     */
    public function dashboardMetrics(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $period = $request->query('period', 'today'); // today, week, month, year, all

        $dateRange = $this->getDateRange($period);

        // Revenue metrics
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
        $previousDateRange = $this->getPreviousDateRange($period, $dateRange);
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

        // Order status counts (all time for the tenant)
        $allOrders = Order::where('tenant_id', $tenantId)->get();
        $statusCounts = [
            'total' => $allOrders->count(),
            'pending' => $allOrders->where('status', 'pending')->count(),
            'confirmed' => $allOrders->where('status', 'confirmed')->count(),
            'fulfilled' => $allOrders->where('status', 'fulfilled')->count(),
            'cancelled' => $allOrders->where('status', 'cancelled')->count(),
        ];

        // Average order value
        $avgOrderValue = $currentOrders > 0 ? $currentRevenue / $currentOrders : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'revenue' => [
                    'current' => round($currentRevenue, 2),
                    'previous' => round($previousRevenue, 2),
                    'growth_percentage' => round($revenueGrowth, 2),
                ],
                'orders' => [
                    'current' => $currentOrders,
                    'previous' => $previousOrders,
                    'growth_percentage' => round($ordersGrowth, 2),
                ],
                'average_order_value' => round($avgOrderValue, 2),
                'order_statuses' => $statusCounts,
            ],
        ]);
    }

    /**
     * Format a date based on period
     */
    private function formatPeriod($date, string $period): string
    {
        $dt = $date instanceof CarbonInterface ? $date : Carbon::parse($date);

        return match ($period) {
            'yearly' => $dt->format('Y'),
            'monthly' => $dt->format('Y-m'),
            'weekly' => $dt->format('Y-W'),
            'daily' => $dt->format('Y-m-d'),
            default => $dt->format('Y-m-d'),
        };
    }

    /**
     * Get date range for dashboard metrics
     */
    private function getDateRange(string $period): array
    {
        return match ($period) {
            'today' => ['start' => now()->startOfDay(), 'end' => now()->endOfDay()],
            'week' => ['start' => now()->startOfWeek(), 'end' => now()->endOfWeek()],
            'month' => ['start' => now()->startOfMonth(), 'end' => now()->endOfMonth()],
            'year' => ['start' => now()->startOfYear(), 'end' => now()->endOfYear()],
            'all' => ['start' => null, 'end' => null],
            default => ['start' => null, 'end' => null],
        };
    }

    /**
     * Get previous date range for comparison
     */
    private function getPreviousDateRange(string $period, array $currentRange): array
    {
        if (! $currentRange['start'] || ! $currentRange['end']) {
            return ['start' => null, 'end' => null];
        }

        return match ($period) {
            'today' => [
                'start' => now()->copy()->subDay()->startOfDay(),
                'end' => now()->copy()->subDay()->endOfDay(),
            ],
            'week' => [
                'start' => now()->copy()->subWeek()->startOfWeek(),
                'end' => now()->copy()->subWeek()->endOfWeek(),
            ],
            'month' => [
                'start' => now()->copy()->subMonth()->startOfMonth(),
                'end' => now()->copy()->subMonth()->endOfMonth(),
            ],
            'year' => [
                'start' => now()->copy()->subYear()->startOfYear(),
                'end' => now()->copy()->subYear()->endOfYear(),
            ],
            default => ['start' => null, 'end' => null],
        };
    }

    /**
     * Get product details
     */
    private function getProductDetails(int $productId): array
    {
        $product = Product::find($productId);

        return $product ? [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
        ] : [
            'id' => $productId,
            'name' => 'Deleted Product',
            'sku' => 'N/A',
        ];
    }
}
