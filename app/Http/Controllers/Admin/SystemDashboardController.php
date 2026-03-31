<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemDashboardController extends Controller
{
    /**
     * Get system overview metrics for super admin.
     */
    public function overview(Request $request): JsonResponse
    {
        $metrics = [
            'tenants' => $this->getTenantMetrics(),
            'users' => $this->getUserMetrics(),
            'business' => $this->getBusinessMetrics(),
            'system_health' => $this->getSystemHealthMetrics(),
        ];

        return response()->json([
            'success' => true,
            'data' => $metrics,
            'message' => 'System overview metrics retrieved successfully',
        ], 200);
    }

    /**
     * Get revenue-specific metrics.
     */
    public function revenue(Request $request): JsonResponse
    {
        $period = $request->query('period', 'all');
        $dateRange = $this->getDateRange($period);

        $metrics = [
            'total_revenue' => $this->calculateTotalRevenue($dateRange),
            'revenue_by_tenant' => $this->getRevenueByTenant($dateRange),
            'revenue_trends' => $this->getRevenueTrends($dateRange),
            'top_performing_tenants' => $this->getTopPerformingTenants($dateRange),
        ];

        return response()->json([
            'success' => true,
            'data' => $metrics,
            'message' => 'Revenue metrics retrieved successfully',
        ], 200);
    }

    /**
     * Get usage statistics.
     */
    public function usage(Request $request): JsonResponse
    {
        $period = $request->query('period', 'all');
        $dateRange = $this->getDateRange($period);

        $metrics = [
            'tenant_activity' => $this->getTenantActivity($dateRange),
            'user_activity' => $this->getUserActivity($dateRange),
            'resource_usage' => $this->getResourceUsage(),
            'api_usage' => $this->getApiUsage(),
        ];

        return response()->json([
            'success' => true,
            'data' => $metrics,
            'message' => 'Usage statistics retrieved successfully',
        ], 200);
    }

    /**
     * Get system alerts.
     */
    public function alerts(): JsonResponse
    {
        $alerts = [
            'tenant_alerts' => $this->getTenantAlerts(),
            'system_alerts' => $this->getSystemAlerts(),
            'recent_issues' => $this->getRecentIssues(),
        ];

        return response()->json([
            'success' => true,
            'data' => $alerts,
            'message' => 'System alerts retrieved successfully',
        ], 200);
    }

    /**
     * Get tenant trends over time.
     */
    public function tenantTrends(Request $request): JsonResponse
    {
        $period = $request->query('period', '30d');
        $days = match ($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            'all' => 365,
            default => 30,
        };

        $trends = Tenant::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($item) => [
                'date' => $item->date,
                'count' => $item->count,
            ])
            ->toArray();

        // Calculate growth percentage
        if (count($trends) >= 2) {
            $firstHalf = array_slice($trends, 0, floor(count($trends) / 2));
            $secondHalf = array_slice($trends, floor(count($trends) / 2));

            $firstTotal = array_sum(array_column($firstHalf, 'count'));
            $secondTotal = array_sum(array_column($secondHalf, 'count'));

            $growthPercentage = $firstTotal > 0
                ? (($secondTotal - $firstTotal) / $firstTotal) * 100
                : 0;
        } else {
            $growthPercentage = 0;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'trends' => $trends,
                'growth_percentage' => round($growthPercentage, 2),
                'period' => $period,
            ],
            'message' => 'Tenant trends retrieved successfully',
        ], 200);
    }

    /**
     * Get top products across all tenants.
     */
    public function topProducts(Request $request): JsonResponse
    {
        $limit = $request->query('limit', 10);
        $period = $request->query('period', '30d');
        $sortBy = $request->query('sort_by', 'quantity');
        $dateRange = $this->getDateRange($period);

        $query = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('tenants', 'orders.tenant_id', '=', 'tenants.id')
            ->whereIn('orders.status', ['fulfilled', 'confirmed'])
            ->selectRaw('
                products.id,
                products.name,
                products.sku,
                tenants.id as tenant_id,
                tenants.name as tenant_name,
                SUM(order_items.quantity) as total_sold,
                SUM(order_items.total) as total_revenue
            ');

        if ($dateRange['start']) {
            $query->where('orders.created_at', '>=', $dateRange['start']);
        }

        if ($dateRange['end']) {
            $query->where('orders.created_at', '<=', $dateRange['end']);
        }

        $orderByField = $sortBy === 'revenue' ? 'total_revenue' : 'total_sold';

        $topProducts = $query
            ->groupBy('products.id', 'products.name', 'products.sku', 'tenants.id', 'tenants.name')
            ->orderByDesc($orderByField)
            ->limit($limit)
            ->get()
            ->map(fn($item) => [
                'productId' => $item->id,
                'productName' => $item->name,
                'productSku' => $item->sku,
                'tenantId' => $item->tenant_id,
                'tenantName' => $item->tenant_name,
                'totalSold' => $item->total_sold,
                'totalRevenue' => round($item->total_revenue, 2),
            ])
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'products' => $topProducts,
                'period' => $period,
                'sort_by' => $sortBy,
            ],
            'message' => 'Top products retrieved successfully',
        ], 200);
    }

    /**
     * Get customer analytics across all tenants.
     */
    public function customerAnalytics(Request $request): JsonResponse
    {
        $period = $request->query('period', '30d');
        $limit = $request->query('limit', 10);
        $dateRange = $this->getDateRange($period);

        // Total customers
        $totalCustomers = DB::table('customers')->count();

        // New customers in period
        $newCustomersQuery = DB::table('customers');
        if ($dateRange['start']) {
            $newCustomersQuery->where('created_at', '>=', $dateRange['start']);
        }
        if ($dateRange['end']) {
            $newCustomersQuery->where('created_at', '<=', $dateRange['end']);
        }
        $newCustomers = $newCustomersQuery->count();

        // Active customers (customers with orders in period)
        $activeCustomersQuery = DB::table('customers')
            ->join('orders', 'customers.id', '=', 'orders.customer_id')
            ->whereIn('orders.status', ['fulfilled', 'confirmed']);

        if ($dateRange['start']) {
            $activeCustomersQuery->where('orders.created_at', '>=', $dateRange['start']);
        }
        if ($dateRange['end']) {
            $activeCustomersQuery->where('orders.created_at', '<=', $dateRange['end']);
        }

        $activeCustomers = $activeCustomersQuery->distinct('customers.id')->count('customers.id');

        // Top customers by revenue
        $topCustomersQuery = DB::table('customers')
            ->join('orders', 'customers.id', '=', 'orders.customer_id')
            ->join('tenants', 'orders.tenant_id', '=', 'tenants.id')
            ->whereIn('orders.status', ['fulfilled', 'confirmed'])
            ->selectRaw('
                customers.id,
                customers.name,
                customers.email,
                COUNT(orders.id) as total_orders,
                SUM(orders.total) as total_revenue
            ');

        if ($dateRange['start']) {
            $topCustomersQuery->where('orders.created_at', '>=', $dateRange['start']);
        }
        if ($dateRange['end']) {
            $topCustomersQuery->where('orders.created_at', '<=', $dateRange['end']);
        }

        $topCustomers = $topCustomersQuery
            ->groupBy('customers.id', 'customers.name', 'customers.email')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get()
            ->map(fn($item) => [
                'customerId' => $item->id,
                'customerName' => $item->name,
                'customerEmail' => null, // Email is encrypted, skip for now
                'totalOrders' => $item->total_orders,
                'totalRevenue' => round($item->total_revenue, 2),
            ])
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'totalCustomers' => $totalCustomers,
                'newCustomers' => $newCustomers,
                'activeCustomers' => $activeCustomers,
                'topCustomers' => $topCustomers,
                'period' => $period,
            ],
            'message' => 'Customer analytics retrieved successfully',
        ], 200);
    }

    /**
     * Get tenant metrics.
     */
    private function getTenantMetrics(): array
    {
        $total = Tenant::count();
        $active = Tenant::where('status', 'active')->count();
        $suspended = Tenant::where('status', 'suspended')->count();
        $inactive = Tenant::where('status', 'inactive')->count();
        $onTrial = Tenant::whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->count();
        $expiringTrials = Tenant::whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [now(), now()->addDays(7)])
            ->count();

        return [
            'total' => $total,
            'active' => $active,
            'suspended' => $suspended,
            'inactive' => $inactive,
            'on_trial' => $onTrial,
            'expiring_trials' => $expiringTrials,
        ];
    }

    /**
     * Get user metrics.
     */
    private function getUserMetrics(): array
    {
        $total = User::count();
        $superAdmins = User::where('is_super_admin', true)->count();
        $tenantUsers = User::whereNotNull('tenant_id')->count();
        $usersWithoutTenant = User::whereNull('tenant_id')->count();

        return [
            'total' => $total,
            'super_admins' => $superAdmins,
            'tenant_users' => $tenantUsers,
            'without_tenant' => $usersWithoutTenant,
        ];
    }

    /**
     * Get business metrics.
     */
    private function getBusinessMetrics(): array
    {
        $totalStores = DB::table('stores')->count();
        $totalWarehouses = DB::table('warehouses')->count();
        $totalProducts = DB::table('products')->count();
        $totalCustomers = DB::table('customers')->count();
        $totalOrders = DB::table('orders')->count();
        $totalInventory = DB::table('inventories')->count();

        // Total inventory value
        $totalInventoryValue = DB::table('inventories')
            ->selectRaw('SUM(quantity * COALESCE(cost, 0)) as value')
            ->value('value') ?? 0;

        // Total revenue (from fulfilled orders)
        $totalRevenue = DB::table('orders')
            ->whereIn('status', ['fulfilled'])
            ->sum('total');

        return [
            'total_stores' => $totalStores,
            'total_warehouses' => $totalWarehouses,
            'total_products' => $totalProducts,
            'total_customers' => $totalCustomers,
            'total_orders' => $totalOrders,
            'total_inventory_items' => $totalInventory,
            'total_inventory_value' => round($totalInventoryValue, 2),
            'total_revenue' => round($totalRevenue, 2),
        ];
    }

    /**
     * Get system health metrics.
     */
    private function getSystemHealthMetrics(): array
    {
        // Check failed jobs
        $failedJobs = DB::table('failed_jobs')->count();

        // Check recent webhook failures
        $recentWebhookFailures = DB::table('webhook_delivery_attempts')
            ->where('success', false)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        // Check active webhooks
        $activeWebhooks = Webhook::where('active', true)->count();
        $inactiveWebhooks = Webhook::where('active', false)->count();

        // Cache health
        $cacheSize = DB::table('cache')->count();

        // Session count
        $activeSessions = DB::table('sessions')
            ->where('last_activity', '>=', now()->subMinutes(30))
            ->count();

        return [
            'failed_jobs' => $failedJobs,
            'recent_webhook_failures' => $recentWebhookFailures,
            'active_webhooks' => $activeWebhooks,
            'inactive_webhooks' => $inactiveWebhooks,
            'cache_entries' => $cacheSize,
            'active_sessions' => $activeSessions,
            'health_score' => $this->calculateHealthScore($failedJobs, $recentWebhookFailures),
        ];
    }

    /**
     * Calculate total revenue.
     */
    private function calculateTotalRevenue(array $dateRange): array
    {
        $query = DB::table('orders')
            ->whereIn('status', ['fulfilled', 'confirmed']);

        if ($dateRange['start']) {
            $query->where('created_at', '>=', $dateRange['start']);
        }

        if ($dateRange['end']) {
            $query->where('created_at', '<=', $dateRange['end']);
        }

        $total = $query->sum('total');
        $count = $query->count();
        $avgOrderValue = $count > 0 ? $total / $count : 0;

        return [
            'total' => round($total, 2),
            'order_count' => $count,
            'average_order_value' => round($avgOrderValue, 2),
        ];
    }

    /**
     * Get revenue by tenant.
     */
    private function getRevenueByTenant(array $dateRange): array
    {
        $query = DB::table('orders')
            ->join('tenants', 'orders.tenant_id', '=', 'tenants.id')
            ->whereIn('orders.status', ['fulfilled', 'confirmed'])
            ->selectRaw('tenants.id, tenants.name, SUM(orders.total) as revenue, COUNT(orders.id) as order_count');

        if ($dateRange['start']) {
            $query->where('orders.created_at', '>=', $dateRange['start']);
        }

        if ($dateRange['end']) {
            $query->where('orders.created_at', '<=', $dateRange['end']);
        }

        $results = $query->groupBy('tenants.id', 'tenants.name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        return $results->map(fn($item) => [
            'tenant_id' => $item->id,
            'tenant_name' => $item->name,
            'revenue' => round($item->revenue, 2),
            'order_count' => $item->order_count,
        ])->toArray();
    }

    /**
     * Get revenue trends.
     */
    private function getRevenueTrends(array $dateRange): array
    {
        $query = DB::table('orders')
            ->whereIn('status', ['fulfilled', 'confirmed']);

        if ($dateRange['start']) {
            $query->where('created_at', '>=', $dateRange['start']);
        }

        if ($dateRange['end']) {
            $query->where('created_at', '<=', $dateRange['end']);
        }

        // Group by date
        $trends = $query->selectRaw('DATE(created_at) as date, SUM(total) as revenue, COUNT(id) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->limit(30)
            ->get();

        return $trends->map(fn($item) => [
            'date' => $item->date,
            'revenue' => round($item->revenue, 2),
            'orders' => $item->orders,
        ])->toArray();
    }

    /**
     * Get top performing tenants.
     */
    private function getTopPerformingTenants(array $dateRange): array
    {
        $query = DB::table('orders')
            ->join('tenants', 'orders.tenant_id', '=', 'tenants.id')
            ->whereIn('orders.status', ['fulfilled', 'confirmed'])
            ->selectRaw('tenants.id, tenants.name, 
                       SUM(orders.total) as revenue, 
                       COUNT(orders.id) as orders,
                       AVG(orders.total) as avg_order_value');

        if ($dateRange['start']) {
            $query->where('orders.created_at', '>=', $dateRange['start']);
        }

        if ($dateRange['end']) {
            $query->where('orders.created_at', '<=', $dateRange['end']);
        }

        $results = $query->groupBy('tenants.id', 'tenants.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        return $results->map(fn($item) => [
            'tenant_id' => $item->id,
            'tenant_name' => $item->name,
            'total_revenue' => round($item->revenue, 2),
            'total_orders' => $item->orders,
            'avg_order_value' => round($item->avg_order_value, 2),
        ])->toArray();
    }

    /**
     * Get tenant activity.
     */
    private function getTenantActivity(array $dateRange): array
    {
        $newTenants = Tenant::when($dateRange['start'], fn($q) => $q->where('created_at', '>=', $dateRange['start']))
            ->when($dateRange['end'], fn($q) => $q->where('created_at', '<=', $dateRange['end']))
            ->count();

        $activatedTenants = Tenant::where('status', 'active')
            ->when($dateRange['start'], fn($q) => $q->where('updated_at', '>=', $dateRange['start']))
            ->when($dateRange['end'], fn($q) => $q->where('updated_at', '<=', $dateRange['end']))
            ->count();

        return [
            'new_tenants' => $newTenants,
            'activated_tenants' => $activatedTenants,
        ];
    }

    /**
     * Get user activity.
     */
    private function getUserActivity(array $dateRange): array
    {
        $newUsers = User::when($dateRange['start'], fn($q) => $q->where('created_at', '>=', $dateRange['start']))
            ->when($dateRange['end'], fn($q) => $q->where('created_at', '<=', $dateRange['end']))
            ->count();

        return [
            'new_users' => $newUsers,
        ];
    }

    /**
     * Get resource usage.
     */
    private function getResourceUsage(): array
    {
        return [
            'total_stores' => DB::table('stores')->count(),
            'total_warehouses' => DB::table('warehouses')->count(),
            'total_products' => DB::table('products')->count(),
            'total_inventory_items' => DB::table('inventories')->count(),
        ];
    }

    /**
     * Get API usage.
     */
    private function getApiUsage(): array
    {
        // Get API token usage
        $totalTokens = DB::table('personal_access_tokens')->count();
        $tokensUsedToday = DB::table('personal_access_tokens')
            ->whereDate('last_used_at', '>=', now()->startOfDay())
            ->count();

        return [
            'total_api_tokens' => $totalTokens,
            'tokens_used_today' => $tokensUsedToday,
        ];
    }

    /**
     * Get tenant alerts.
     */
    private function getTenantAlerts(): array
    {
        $alerts = [];

        // Trials expiring soon
        $expiringTrials = Tenant::whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [now(), now()->addDays(7)])
            ->get(['id', 'name', 'trial_ends_at'])
            ->map(fn($t) => [
                'type' => 'trial_expiring',
                'tenant_id' => $t->id,
                'tenant_name' => $t->name,
                'trial_ends_at' => $t->trial_ends_at->toIso8601String(),
                'message' => "Trial for {$t->name} expires in {$t->trial_ends_at->diffForHumans()}",
            ]);

        $alerts = array_merge($alerts, $expiringTrials->toArray());

        // Subscriptions expiring soon
        $expiringSubscriptions = Tenant::whereNotNull('subscription_ends_at')
            ->whereBetween('subscription_ends_at', [now(), now()->addDays(7)])
            ->get(['id', 'name', 'subscription_ends_at'])
            ->map(fn($t) => [
                'type' => 'subscription_expiring',
                'tenant_id' => $t->id,
                'tenant_name' => $t->name,
                'subscription_ends_at' => $t->subscription_ends_at->toIso8601String(),
                'message' => "Subscription for {$t->name} expires in {$t->subscription_ends_at->diffForHumans()}",
            ]);

        $alerts = array_merge($alerts, $expiringSubscriptions->toArray());

        // Suspended tenants
        $suspendedTenants = Tenant::where('status', 'suspended')
            ->get(['id', 'name'])
            ->map(fn($t) => [
                'type' => 'tenant_suspended',
                'tenant_id' => $t->id,
                'tenant_name' => $t->name,
                'message' => "Tenant {$t->name} is suspended",
            ]);

        $alerts = array_merge($alerts, $suspendedTenants->toArray());

        return $alerts;
    }

    /**
     * Get system alerts.
     */
    private function getSystemAlerts(): array
    {
        $alerts = [];

        // Failed jobs
        $failedJobs = DB::table('failed_jobs')->count();
        if ($failedJobs > 0) {
            $alerts[] = [
                'type' => 'failed_jobs',
                'severity' => $failedJobs > 10 ? 'high' : 'medium',
                'count' => $failedJobs,
                'message' => "{$failedJobs} failed job(s) require attention",
            ];
        }

        // Recent webhook failures
        $webhookFailures = DB::table('webhook_delivery_attempts')
            ->where('success', false)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        if ($webhookFailures > 5) {
            $alerts[] = [
                'type' => 'webhook_failures',
                'severity' => $webhookFailures > 20 ? 'high' : 'medium',
                'count' => $webhookFailures,
                'message' => "{$webhookFailures} webhook delivery failure(s) in the last 24 hours",
            ];
        }

        return $alerts;
    }

    /**
     * Get recent issues.
     */
    private function getRecentIssues(): array
    {
        // Recent failed jobs
        $recentFailedJobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(5)
            ->get(['id', 'uuid', 'connection', 'failed_at'])
            ->map(fn($job) => [
                'type' => 'failed_job',
                'id' => $job->id,
                'uuid' => $job->uuid,
                'connection' => $job->connection,
                'failed_at' => $job->failed_at,
            ]);

        return $recentFailedJobs->toArray();
    }

    /**
     * Calculate system health score.
     */
    private function calculateHealthScore(int $failedJobs, int $webhookFailures): float
    {
        $score = 100;

        // Deduct points for failed jobs
        $score -= min($failedJobs * 2, 30);

        // Deduct points for webhook failures
        $score -= min($webhookFailures, 20);

        return max($score, 0);
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
            default => ['start' => null, 'end' => null],
        };
    }
}
