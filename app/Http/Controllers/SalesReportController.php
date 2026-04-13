<?php

namespace App\Http\Controllers;

use App\ExportService;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(protected ExportService $exportService) {}

    /**
     * Get sales report with revenue analytics.
     * Note: Authorization enforced by role:admin middleware.
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

        // Get summary stats in a single query
        $summary = (clone $query)
            ->selectRaw('
                COUNT(*) as total_orders,
                SUM(subtotal) as total_revenue,
                SUM(tax) as total_tax,
                SUM(discount) as total_discount,
                SUM(shipping) as total_shipping,
                AVG(subtotal) as avg_order_value
            ')
            ->first();

        // Get revenue grouped by period using database aggregation
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $dateExpr = match ($period) {
                'yearly' => "strftime('%Y', created_at)",
                'monthly' => "strftime('%Y-%m', created_at)",
                'weekly' => "strftime('%Y-%W', created_at)",
                default => "strftime('%Y-%m-%d', created_at)",
            };
        } else {
            $dateExpr = match ($period) {
                'yearly' => "DATE_FORMAT(created_at, '%Y')",
                'monthly' => "DATE_FORMAT(created_at, '%Y-%m')",
                'weekly' => "DATE_FORMAT(created_at, '%Y-%u')",
                default => "DATE_FORMAT(created_at, '%Y-%m-%d')",
            };
        }

        $revenueData = (clone $query)
            ->selectRaw("{$dateExpr} as period,
                        COUNT(*) as order_count,
                        SUM(subtotal) as total_revenue,
                        SUM(tax) as total_tax,
                        SUM(discount) as total_discount,
                        SUM(shipping) as total_shipping,
                        AVG(subtotal) as avg_order_value")
            ->groupByRaw($dateExpr)
            ->orderBy('period')
            ->get()
            ->map(fn($item) => [
                'period' => $item->period,
                'order_count' => (int) $item->order_count,
                'total_revenue' => round($item->total_revenue, 2),
                'total_tax' => round($item->total_tax, 2),
                'total_discount' => round($item->total_discount, 2),
                'total_shipping' => round($item->total_shipping, 2),
                'avg_order_value' => round($item->avg_order_value, 2),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'revenue_by_period' => $revenueData,
                'summary' => [
                    'total_revenue' => round($summary->total_revenue ?? 0, 2),
                    'total_orders' => (int) ($summary->total_orders ?? 0),
                    'average_order_value' => round($summary->avg_order_value ?? 0, 2),
                    'total_tax' => round($summary->total_tax ?? 0, 2),
                    'total_discount' => round($summary->total_discount ?? 0, 2),
                    'total_shipping' => round($summary->total_shipping ?? 0, 2),
                ],
            ],
        ]);
    }

    /**
     * Get orders by period.
     * Note: Authorization enforced by role:admin middleware.
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
     * Get top products report.
     * Note: Authorization enforced by role:admin middleware.
     */
    public function topProducts(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $limit = min($request->query('limit', 10), 100);
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $sortBy = $request->query('sort_by', 'quantity'); // quantity, revenue

        $orderByField = $sortBy === 'quantity' ? 'total_quantity' : 'total_revenue';

        $query = OrderItem::where('order_items.tenant_id', $tenantId)
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereIn('orders.status', ['confirmed', 'fulfilled']);

        if ($startDate) {
            $query->whereDate('orders.created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('orders.created_at', '<=', $endDate);
        }

        // Use database-level aggregation instead of loading all into memory
        $topProducts = $query
            ->selectRaw('
                products.id,
                products.name,
                products.sku,
                SUM(order_items.quantity) as total_quantity,
                SUM(order_items.unit_price * order_items.quantity) as total_revenue,
                COUNT(DISTINCT orders.id) as order_count,
                AVG(order_items.unit_price) as avg_price
            ')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc($orderByField)
            ->limit($limit)
            ->get()
            ->map(fn($item) => [
                'product_id' => $item->id,
                'product' => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                ],
                'total_quantity' => (int) $item->total_quantity,
                'total_revenue' => round($item->total_revenue, 2),
                'order_count' => (int) $item->order_count,
                'avg_price' => round($item->avg_price, 2),
            ]);

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
     * Get dashboard metrics (KPIs for tenant admin).
     * Note: Authorization enforced by role:admin middleware.
     */
    public function dashboardMetrics(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $period = $request->query('period', 'today'); // today, week, month, year, all

        $dateRange = $this->getDateRange($period);
        $previousDateRange = $this->getPreviousDateRange($period, $dateRange);

        // Query 1: Current period stats (single query)
        $currentStats = Order::where('tenant_id', $tenantId)
            ->whereIn('status', ['confirmed', 'fulfilled'])
            ->when($dateRange['start'], fn($q) => $q->whereDate('created_at', '>=', $dateRange['start']))
            ->when($dateRange['end'], fn($q) => $q->whereDate('created_at', '<=', $dateRange['end']))
            ->selectRaw('COUNT(*) as orders, SUM(subtotal) as revenue')
            ->first();

        $currentRevenue = $currentStats->revenue ?? 0;
        $currentOrders = $currentStats->orders ?? 0;

        // Query 2: Previous period stats (single query)
        $previousStats = Order::where('tenant_id', $tenantId)
            ->whereIn('status', ['confirmed', 'fulfilled'])
            ->when($previousDateRange['start'], fn($q) => $q->whereDate('created_at', '>=', $previousDateRange['start']))
            ->when($previousDateRange['end'], fn($q) => $q->whereDate('created_at', '<=', $previousDateRange['end']))
            ->selectRaw('COUNT(*) as orders, SUM(subtotal) as revenue')
            ->first();

        $previousRevenue = $previousStats->revenue ?? 0;
        $previousOrders = $previousStats->orders ?? 0;

        // Calculate growth percentages
        $revenueGrowth = $previousRevenue > 0 ? (($currentRevenue - $previousRevenue) / $previousRevenue) * 100 : 0;
        $ordersGrowth = $previousOrders > 0 ? (($currentOrders - $previousOrders) / $previousOrders) * 100 : 0;

        // Query 3: All-time status counts (single GROUP BY query instead of loading all orders)
        $statusCounts = Order::where('tenant_id', $tenantId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $statusCounts = [
            'total' => $statusCounts->sum(),
            'pending' => $statusCounts->get('pending', 0),
            'confirmed' => $statusCounts->get('confirmed', 0),
            'fulfilled' => $statusCounts->get('fulfilled', 0),
            'cancelled' => $statusCounts->get('cancelled', 0),
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
     * Format a date based on period.
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
     * Get date range for dashboard metrics.
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
     * Get previous date range for comparison.
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
     * Get product details.
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

    /**
     * Export revenue report to CSV.
     */
    public function exportRevenue(Request $request): StreamedResponse
    {
        $tenantId = $request->route('tenant_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $period = $request->query('period', 'daily');
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

        // Group by period using database aggregation
        $driver = DB::connection()->getDriverName();
        $dateExpr = match ($period) {
            'yearly' => $driver === 'sqlite' ? "strftime('%Y', created_at)" : "DATE_FORMAT(created_at, '%Y')",
            'monthly' => $driver === 'sqlite' ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')",
            'weekly' => $driver === 'sqlite' ? "strftime('%Y-%W', created_at)" : "DATE_FORMAT(created_at, '%Y-%u')",
            default => $driver === 'sqlite' ? "strftime('%Y-%m-%d', created_at)" : "DATE_FORMAT(created_at, '%Y-%m-%d')",
        };

        $query->selectRaw("{$dateExpr} as period,
                    COUNT(*) as order_count,
                    SUM(subtotal) as total_revenue,
                    SUM(tax) as total_tax,
                    SUM(discount) as total_discount,
                    SUM(shipping) as total_shipping,
                    AVG(subtotal) as avg_order_value")
            ->groupByRaw($dateExpr)
            ->orderBy('period');

        $columns = [
            'period' => 'Period',
            'order_count' => 'Orders',
            'total_revenue' => 'Revenue',
            'total_tax' => 'Tax',
            'total_discount' => 'Discount',
            'total_shipping' => 'Shipping',
            'avg_order_value' => 'Avg Order Value',
        ];

        $filename = sprintf('sales_revenue_%s_%s.csv', $period, now()->format('Y-m-d'));

        return $this->exportService->exportCsvFromQuery($query, $columns, $filename);
    }

    /**
     * Export orders by period report to CSV.
     */
    public function exportOrdersByPeriod(Request $request): StreamedResponse
    {
        $tenantId = $request->route('tenant_id');
        $period = $request->query('period', 'daily');
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

        // Group by period using database aggregation
        $driver = DB::connection()->getDriverName();
        $dateExpr = match ($period) {
            'yearly' => $driver === 'sqlite' ? "strftime('%Y', created_at)" : "DATE_FORMAT(created_at, '%Y')",
            'monthly' => $driver === 'sqlite' ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')",
            'weekly' => $driver === 'sqlite' ? "strftime('%Y-%W', created_at)" : "DATE_FORMAT(created_at, '%Y-%u')",
            default => $driver === 'sqlite' ? "strftime('%Y-%m-%d', created_at)" : "DATE_FORMAT(created_at, '%Y-%m-%d')",
        };

        $query->selectRaw("{$dateExpr} as period,
                    COUNT(*) as order_count,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count,
                    SUM(CASE WHEN status = 'fulfilled' THEN 1 ELSE 0 END) as fulfilled_count,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
                    SUM(subtotal) as total_revenue")
            ->groupByRaw($dateExpr)
            ->orderBy('period');

        $columns = [
            'period' => 'Period',
            'order_count' => 'Total Orders',
            'pending_count' => 'Pending',
            'confirmed_count' => 'Confirmed',
            'fulfilled_count' => 'Fulfilled',
            'cancelled_count' => 'Cancelled',
            'total_revenue' => 'Revenue',
        ];

        $filename = sprintf('orders_by_period_%s.csv', now()->format('Y-m-d'));

        return $this->exportService->exportCsvFromQuery($query, $columns, $filename);
    }

    /**
     * Export top products report to CSV.
     */
    public function exportTopProducts(Request $request): StreamedResponse
    {
        $tenantId = $request->route('tenant_id');
        $limit = min($request->query('limit', 10), 100);
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $sortBy = $request->query('sort_by', 'quantity');

        $orderByField = $sortBy === 'quantity' ? 'total_quantity' : 'total_revenue';

        $query = OrderItem::where('order_items.tenant_id', $tenantId)
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereIn('orders.status', ['confirmed', 'fulfilled']);

        if ($startDate) {
            $query->whereDate('orders.created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('orders.created_at', '<=', $endDate);
        }

        $query->selectRaw('
                products.id as product_id,
                products.name as product_name,
                products.sku as product_sku,
                SUM(order_items.quantity) as total_quantity,
                SUM(order_items.unit_price * order_items.quantity) as total_revenue,
                COUNT(DISTINCT orders.id) as order_count,
                AVG(order_items.unit_price) as avg_price
            ')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc($orderByField)
            ->limit($limit);

        $columns = [
            'product_id' => 'Product ID',
            'product_name' => 'Product Name',
            'product_sku' => 'SKU',
            'total_quantity' => 'Qty Sold',
            'total_revenue' => 'Revenue',
            'order_count' => 'Orders',
            'avg_price' => 'Avg Price',
        ];

        $filename = sprintf('top_products_%s.csv', now()->format('Y-m-d'));

        return $this->exportService->exportCsvFromQuery($query, $columns, $filename);
    }
}
