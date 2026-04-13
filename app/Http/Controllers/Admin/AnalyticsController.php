<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Tenant;
use App\Support\SqlDateFormatter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Get sales trend data for charts.
     */
    public function salesTrend(Request $request): JsonResponse
    {
        $period = $request->query('period', '30d');
        $tenantId = $request->query('tenant_id');
        $startDate = $this->getStartDate($period);
        $user = $request->user();

        $query = DB::table('orders')
            ->whereIn('status', ['confirmed', 'fulfilled'])
            ->where('created_at', '>=', $startDate);

        // Super admins can optionally filter by tenant; tenant users always scoped
        if ($user->is_super_admin && $tenantId) {
            $query->where('orders.tenant_id', $tenantId);
        } elseif (! $user->is_super_admin && $user->tenant_id) {
            $query->where('orders.tenant_id', $user->tenant_id);
        }

        $dateExpr = SqlDateFormatter::dateColumn('created_at', 'date');

        $trendData = $query
            ->selectRaw("{$dateExpr} as date,
                        COUNT(*) as orders,
                        SUM(subtotal) as revenue,
                        AVG(subtotal) as avg_order_value")
            ->groupByRaw($dateExpr)
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'trend' => $trendData->map(fn($item) => [
                    'date' => $item->date,
                    'orders' => (int) $item->orders,
                    'revenue' => round($item->revenue, 2),
                    'avg_order_value' => round($item->avg_order_value, 2),
                ]),
            ],
            'message' => 'Sales trend retrieved successfully',
        ], 200);
    }

    /**
     * Get order status distribution.
     */
    public function orderStatusDistribution(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant_id');
        $user = $request->user();

        $query = DB::table('orders');

        // Super admins can optionally filter by tenant; tenant users always scoped
        if ($user->is_super_admin && $tenantId) {
            $query->where('tenant_id', $tenantId);
        } elseif (! $user->is_super_admin && $user->tenant_id) {
            $query->where('tenant_id', $user->tenant_id);
        }

        $statusData = $query
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get();

        $total = $statusData->sum('count');

        return response()->json([
            'success' => true,
            'data' => [
                'distribution' => $statusData->map(fn($item) => [
                    'status' => $item->status,
                    'count' => (int) $item->count,
                    'percentage' => $total > 0 ? round(($item->count / $total) * 100, 2) : 0,
                ]),
                'total' => (int) $total,
            ],
            'message' => 'Order status distribution retrieved successfully',
        ], 200);
    }

    /**
     * Get inventory level distribution.
     */
    public function inventoryLevelDistribution(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant_id');
        $user = $request->user();

        $query = DB::table('inventories')
            ->join('products', 'inventories.product_id', '=', 'products.id');

        // Super admins can optionally filter by tenant; tenant users always scoped
        if ($user->is_super_admin && $tenantId) {
            $query->where('inventories.tenant_id', $tenantId);
        } elseif (! $user->is_super_admin && $user->tenant_id) {
            $query->where('inventories.tenant_id', $user->tenant_id);
        }

        $distribution = $query
            ->selectRaw('
                CASE 
                    WHEN inventories.quantity = 0 THEN "out_of_stock"
                    WHEN inventories.quantity < 10 THEN "low_stock"
                    WHEN inventories.quantity < 50 THEN "medium_stock"
                    ELSE "high_stock"
                END as stock_level,
                COUNT(*) as count,
                SUM(inventories.quantity) as total_quantity
            ')
            ->groupByRaw('
                CASE 
                    WHEN inventories.quantity = 0 THEN "out_of_stock"
                    WHEN inventories.quantity < 10 THEN "low_stock"
                    WHEN inventories.quantity < 50 THEN "medium_stock"
                    ELSE "high_stock"
                END
            ')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'distribution' => $distribution->map(fn($item) => [
                    'level' => $item->stock_level,
                    'count' => (int) $item->count,
                    'total_quantity' => (int) $item->total_quantity,
                ]),
            ],
            'message' => 'Inventory level distribution retrieved successfully',
        ], 200);
    }

    /**
     * Get top products by revenue or quantity.
     */
    public function topProducts(Request $request): JsonResponse
    {
        $limit = min($request->query('limit', 10), 100);
        $sortBy = $request->query('sort_by', 'revenue');
        $period = $request->query('period', '30d');
        $tenantId = $request->query('tenant_id');
        $startDate = $this->getStartDate($period);
        $user = $request->user();

        $orderByField = $sortBy === 'quantity' ? 'total_quantity' : 'total_revenue';

        $query = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereIn('orders.status', ['confirmed', 'fulfilled'])
            ->where('orders.created_at', '>=', $startDate);

        // Super admins can optionally filter by tenant; tenant users always scoped
        if ($user->is_super_admin && $tenantId) {
            $query->where('orders.tenant_id', $tenantId);
        } elseif (! $user->is_super_admin && $user->tenant_id) {
            $query->where('orders.tenant_id', $user->tenant_id);
        }

        $topProducts = $query
            ->selectRaw('
                products.id,
                products.name,
                products.sku,
                SUM(order_items.quantity) as total_quantity,
                SUM(order_items.unit_price * order_items.quantity) as total_revenue,
                COUNT(DISTINCT orders.id) as order_count
            ')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc($orderByField)
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'sort_by' => $sortBy,
                'period' => $period,
                'products' => $topProducts->map(fn($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'total_quantity' => (int) $item->total_quantity,
                    'total_revenue' => round($item->total_revenue, 2),
                    'order_count' => (int) $item->order_count,
                ]),
            ],
            'message' => 'Top products retrieved successfully',
        ], 200);
    }

    /**
     * Get customer segmentation data.
     */
    public function customerSegments(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant_id');
        $user = $request->user();

        $query = DB::table('customers')
            ->leftJoin('orders', 'customers.id', '=', 'orders.customer_id')
            ->whereIn('orders.status', ['confirmed', 'fulfilled']);

        // Super admins can optionally filter by tenant; tenant users always scoped
        if ($user->is_super_admin && $tenantId) {
            $query->where('customers.tenant_id', $tenantId);
        } elseif (! $user->is_super_admin && $user->tenant_id) {
            $query->where('customers.tenant_id', $user->tenant_id);
        }

        $segments = $query
            ->selectRaw("
                customers.id,
                customers.name,
                COUNT(orders.id) as total_orders,
                COALESCE(SUM(orders.total), 0) as total_spent,
                MAX(orders.created_at) as last_order_date,
                CASE
                    WHEN COUNT(orders.id) = 0 THEN 'inactive'
                    WHEN COUNT(orders.id) >= 10 OR COALESCE(SUM(orders.total), 0) >= 1000 THEN 'vip'
                    WHEN COUNT(orders.id) >= 5 OR COALESCE(SUM(orders.total), 0) >= 500 THEN 'loyal'
                    WHEN COUNT(orders.id) >= 2 OR COALESCE(SUM(orders.total), 0) >= 100 THEN 'regular'
                    ELSE 'new'
                END as segment
            ")
            ->groupBy('customers.id', 'customers.name')
            ->get();

        // Segment customers
        $segmented = $segments->map(fn($customer) => [
            'id' => $customer->id,
            'name' => $customer->name,
            'total_orders' => (int) $customer->total_orders,
            'total_spent' => round($customer->total_spent, 2),
            'last_order_date' => $customer->last_order_date,
            'segment' => $customer->segment,
        ]);

        // Group by segment
        $bySegment = $segmented->groupBy('segment')->map(fn($group) => [
            'count' => $group->count(),
            'total_revenue' => round($group->sum('total_spent'), 2),
            'avg_orders' => round($group->avg('total_orders'), 2),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'by_segment' => $bySegment,
                'customers' => $segmented,
            ],
            'message' => 'Customer segments retrieved successfully',
        ], 200);
    }

    /**
     * Get revenue by tenant comparison.
     */
    public function tenantComparison(Request $request): JsonResponse
    {
        $limit = $request->query('limit', 10);
        $period = $request->query('period', '30d');
        $startDate = $this->getStartDate($period);

        $comparison = DB::table('orders')
            ->join('tenants', 'orders.tenant_id', '=', 'tenants.id')
            ->whereIn('orders.status', ['confirmed', 'fulfilled'])
            ->where('orders.created_at', '>=', $startDate)
            ->selectRaw('
                tenants.id,
                tenants.name,
                tenants.subscription_plan,
                COUNT(orders.id) as order_count,
                SUM(orders.total) as total_revenue,
                AVG(orders.total) as avg_order_value
            ')
            ->groupBy('tenants.id', 'tenants.name', 'tenants.subscription_plan')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'tenants' => $comparison->map(fn($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'subscription_plan' => $item->subscription_plan,
                    'order_count' => (int) $item->order_count,
                    'total_revenue' => round($item->total_revenue, 2),
                    'avg_order_value' => round($item->avg_order_value, 2),
                ]),
            ],
            'message' => 'Tenant comparison retrieved successfully',
        ], 200);
    }

    /**
     * Get daily activity heatmap data.
     */
    public function activityHeatmap(Request $request): JsonResponse
    {
        $period = $request->query('period', '30d');
        $tenantId = $request->query('tenant_id');
        $startDate = $this->getStartDate($period);
        $user = $request->user();

        $query = DB::table('orders')
            ->whereIn('status', ['confirmed', 'fulfilled'])
            ->where('created_at', '>=', $startDate);

        // Super admins can optionally filter by tenant; tenant users always scoped
        if ($user->is_super_admin && $tenantId) {
            $query->where('orders.tenant_id', $tenantId);
        } elseif (! $user->is_super_admin && $user->tenant_id) {
            $query->where('orders.tenant_id', $user->tenant_id);
        }

        $dayOfWeekExpr = SqlDateFormatter::dateColumn('created_at', 'day_of_week');
        $hourExpr = SqlDateFormatter::dateColumn('created_at', 'hour');

        $heatmapData = $query
            ->selectRaw("{$dayOfWeekExpr} as day_of_week,
                        {$hourExpr} as hour,
                        COUNT(*) as order_count")
            ->groupByRaw("{$dayOfWeekExpr}, {$hourExpr}")
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'heatmap' => $heatmapData->map(fn($item) => [
                    'day' => (int) $item->day_of_week,
                    'hour' => (int) $item->hour,
                    'count' => (int) $item->order_count,
                ]),
            ],
            'message' => 'Activity heatmap retrieved successfully',
        ], 200);
    }

    /**
     * Get inventory value by warehouse.
     */
    public function inventoryByWarehouse(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant_id');
        $user = $request->user();

        $query = DB::table('inventories')
            ->join('warehouses', 'inventories.warehouse_id', '=', 'warehouses.id')
            ->selectRaw('
                warehouses.id,
                warehouses.name,
                SUM(inventories.quantity) as total_quantity,
                SUM(inventories.quantity * COALESCE(inventories.cost, 0)) as total_value,
                COUNT(DISTINCT inventories.product_id) as product_count
            ');

        // Super admins can optionally filter by tenant; tenant users always scoped
        if ($user->is_super_admin && $tenantId) {
            $query->where('inventories.tenant_id', $tenantId);
        } elseif (! $user->is_super_admin && $user->tenant_id) {
            $query->where('inventories.tenant_id', $user->tenant_id);
        }

        $warehouseData = $query
            ->groupBy('warehouses.id', 'warehouses.name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'warehouses' => $warehouseData->map(fn($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'total_quantity' => (int) $item->total_quantity,
                    'total_value' => round($item->total_value, 2),
                    'product_count' => (int) $item->product_count,
                ]),
            ],
            'message' => 'Inventory by warehouse retrieved successfully',
        ], 200);
    }

    /**
     * Get monthly recurring revenue (MRR) for subscription plans.
     */
    public function recurringRevenue(Request $request): JsonResponse
    {
        $planPrices = config('pricing.subscription_plans', [
            'free' => 0,
            'starter' => 29,
            'professional' => 99,
            'enterprise' => 299,
        ]);

        $tenantCounts = Tenant::selectRaw('subscription_plan, COUNT(*) as count')
            ->where('status', 'active')
            ->groupBy('subscription_plan')
            ->get();

        $mrr = $tenantCounts->sum(fn($plan) => $plan->count * ($planPrices[$plan->subscription_plan] ?? 0));

        return response()->json([
            'success' => true,
            'data' => [
                'mrr' => round($mrr, 2),
                'by_plan' => $tenantCounts->map(fn($plan) => [
                    'plan' => $plan->subscription_plan,
                    'count' => (int) $plan->count,
                    'revenue' => $plan->count * ($planPrices[$plan->subscription_plan] ?? 0),
                ]),
            ],
            'message' => 'Recurring revenue retrieved successfully',
        ], 200);
    }

    /**
     * Get the grouping period based on date range.
     */
    private function getGrouping(string $period): string
    {
        return match ($period) {
            '7d', '14d' => 'daily',
            '30d', '60d' => 'daily',
            '90d', '180d' => 'weekly',
            '1y', 'all' => 'monthly',
            default => 'daily',
        };
    }

    /**
     * Get the start date based on period.
     */
    private function getStartDate(string $period): Carbon
    {
        return match ($period) {
            '7d' => now()->subDays(7),
            '14d' => now()->subDays(14),
            '30d' => now()->subDays(30),
            '60d' => now()->subDays(60),
            '90d' => now()->subDays(90),
            '180d' => now()->subDays(180),
            '1y' => now()->subYear(),
            'all' => now()->subYears(5), // Return 5 years for "all" instead of 1 year
            default => now()->subDays(30),
        };
    }

    /**
     * Determine customer segment based on orders and spending.
     */
    private function getCustomerSegment(int $orders, float $spent): string
    {
        if ($orders === 0) {
            return 'inactive';
        }

        if ($orders >= 10 || $spent >= 1000) {
            return 'vip';
        }

        if ($orders >= 5 || $spent >= 500) {
            return 'loyal';
        }

        if ($orders >= 2 || $spent >= 100) {
            return 'regular';
        }

        return 'new';
    }
}
