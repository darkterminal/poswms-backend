<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    /**
     * Get subscription statistics across all tenants.
     */
    public function stats(): JsonResponse
    {
        $now = now();
        $thirtyDaysFromNow = now()->addDays(30);

        // Get all active tenants with their subscription data
        $tenants = Tenant::select('id', 'subscription_plan', 'subscription_ends_at', 'status')
            ->where('status', 'active')
            ->get();

        $totalSubscriptions = $tenants->count();
        $activeSubscriptions = 0;
        $expiringSubscriptions = 0;
        $expiredSubscriptions = 0;
        $noSubscription = 0;

        $planDistribution = [
            'starter' => 0,
            'professional' => 0,
            'enterprise' => 0,
        ];

        foreach ($tenants as $tenant) {
            if (!$tenant->subscription_plan || !$tenant->subscription_ends_at) {
                $noSubscription++;
                continue;
            }

            $endsAt = $tenant->subscription_ends_at;

            if ($endsAt->isPast()) {
                $expiredSubscriptions++;
            } elseif ($endsAt->between($now, $thirtyDaysFromNow)) {
                $expiringSubscriptions++;
            } else {
                $activeSubscriptions++;
            }

            if (isset($planDistribution[$tenant->subscription_plan])) {
                $planDistribution[$tenant->subscription_plan]++;
            }
        }

        // Calculate MRR
        $planPrices = [
            'starter' => 29,
            'professional' => 99,
            'enterprise' => 299,
        ];

        $mrr = $tenants->sum(function ($tenant) use ($planPrices) {
            if (!$tenant->subscription_plan) {
                return 0;
            }
            return $planPrices[$tenant->subscription_plan] ?? 0;
        });

        $arr = $mrr * 12;

        // Calculate average subscription length
        $tenantsWithSubscription = $tenants->filter(fn($t) => $t->subscription_ends_at !== null && $t->created_at !== null);
        $avgSubscriptionLength = 0;

        if ($tenantsWithSubscription->count() > 0) {
            $totalDays = $tenantsWithSubscription->sum(function ($tenant) {
                if (!$tenant->created_at || !$tenant->subscription_ends_at) {
                    return 0;
                }
                return max(0, $tenant->created_at->diffInDays($tenant->subscription_ends_at));
            });
            $avgSubscriptionLength = round($totalDays / $tenantsWithSubscription->count());
        }

        // Calculate churn rate (simplified - expired / total)
        $churnRate = $totalSubscriptions > 0
            ? round(($expiredSubscriptions / $totalSubscriptions) * 100, 2)
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_subscriptions' => $totalSubscriptions,
                'active_subscriptions' => $activeSubscriptions,
                'expiring_subscriptions' => $expiringSubscriptions,
                'expired_subscriptions' => $expiredSubscriptions,
                'no_subscription' => $noSubscription,
                'monthly_recurring_revenue' => round($mrr, 2),
                'annual_recurring_revenue' => round($arr, 2),
                'plan_distribution' => $planDistribution,
                'churn_rate' => $churnRate,
                'avg_subscription_length_days' => $avgSubscriptionLength,
            ],
            'message' => 'Subscription statistics retrieved successfully',
        ], 200);
    }

    /**
     * Get subscriptions expiring within specified days.
     */
    public function expiringSoon(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);
        $now = now();
        $futureDate = now()->addDays($days);

        $expiringSubscriptions = Tenant::whereNotNull('subscription_ends_at')
            ->where('status', 'active')
            ->whereBetween('subscription_ends_at', [$now, $futureDate])
            ->orderBy('subscription_ends_at', 'asc')
            ->get(['id', 'name', 'subscription_plan', 'subscription_ends_at'])
            ->map(function ($tenant) use ($now) {
                $daysRemaining = max(0, $now->diffInDays($tenant->subscription_ends_at, false));

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'subscription_plan' => $tenant->subscription_plan,
                    'subscription_ends_at' => $tenant->subscription_ends_at->toIso8601String(),
                    'days_remaining' => $daysRemaining,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'tenants' => $expiringSubscriptions,
                'count' => $expiringSubscriptions->count(),
                'days' => $days,
            ],
            'message' => 'Expiring subscriptions retrieved successfully',
        ], 200);
    }

    /**
     * Get subscription history/changelog for a tenant.
     */
    public function history(Tenant $tenant): JsonResponse
    {
        $history = AuditLog::where('tenant_id', $tenant->id)
            ->where('auditable_type', Tenant::class)
            ->where('auditable_id', $tenant->id)
            ->whereIn('event_type', ['created', 'updated'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($auditLog) {
                return [
                    'id' => $auditLog->id,
                    'event' => $auditLog->event_type,
                    'description' => "Tenant {$auditLog->event_type}",
                    'changes' => array_merge(
                        $auditLog->old_values ?? [],
                        $auditLog->new_values ?? []
                    ),
                    'user_id' => $auditLog->user_id,
                    'user_name' => $auditLog->user?->name ?? 'System',
                    'created_at' => $auditLog->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'history' => $history,
                'total_changes' => $history->count(),
            ],
            'message' => 'Subscription history retrieved successfully',
        ], 200);
    }

    /**
     * Get revenue metrics for subscriptions.
     */
    public function revenue(Request $request): JsonResponse
    {
        $period = $request->input('period', '30d');

        $planPrices = [
            'free' => 0,
            'starter' => 29,
            'professional' => 99,
            'enterprise' => 299,
        ];

        // Get current MRR
        $tenantCounts = Tenant::selectRaw('subscription_plan, COUNT(*) as count')
            ->where('status', 'active')
            ->whereNotNull('subscription_plan')
            ->groupBy('subscription_plan')
            ->get();

        $mrr = $tenantCounts->sum(fn($plan) => $plan->count * ($planPrices[$plan->subscription_plan] ?? 0));

        // Get historical data based on period
        $historicalData = $this->getHistoricalRevenue($period, $planPrices);

        return response()->json([
            'success' => true,
            'data' => [
                'mrr' => round($mrr, 2),
                'arr' => round($mrr * 12, 2),
                'by_plan' => $tenantCounts->map(fn($plan) => [
                    'plan' => $plan->subscription_plan,
                    'count' => (int) $plan->count,
                    'revenue' => $plan->count * ($planPrices[$plan->subscription_plan] ?? 0),
                ]),
                'historical' => $historicalData,
                'period' => $period,
            ],
            'message' => 'Revenue metrics retrieved successfully',
        ], 200);
    }

    /**
     * Get historical revenue data.
     */
    private function getHistoricalRevenue(string $period, array $planPrices): array
    {
        $months = match ($period) {
            '30d' => 1,
            '90d' => 3,
            '180d' => 6,
            '365d' => 12,
            default => 1,
        };

        $historical = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();

            // Get tenant counts at end of month
            $tenantCounts = Tenant::selectRaw('subscription_plan, COUNT(*) as count')
                ->where('status', 'active')
                ->whereNotNull('subscription_plan')
                ->where('created_at', '<=', $monthEnd)
                ->groupBy('subscription_plan')
                ->get();

            $monthMrr = $tenantCounts->sum(fn($plan) => $plan->count * ($planPrices[$plan->subscription_plan] ?? 0));

            $historical[] = [
                'month' => $date->format('Y-m'),
                'label' => $date->format('M Y'),
                'mrr' => round($monthMrr, 2),
                'arr' => round($monthMrr * 12, 2),
            ];
        }

        return $historical;
    }
}
