<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderNumberGenerator
{
    /**
     * Generate a sequential order number for a tenant.
     * Format: ORD-YYYYMM-XXXX (e.g., ORD-202603-0001).
     */
    public function generate(Order $order): string
    {
        $prefix = config('app.name', 'ORD');
        $datePrefix = now()->format('Ym');

        // Get the count of orders for this tenant in this month
        $count = Order::where('tenant_id', $order->tenant_id)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        // Increment and format with leading zeros
        $sequence = str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);

        return "{$prefix}-{$datePrefix}-{$sequence}";
    }

    /**
     * Generate order number with database lock to prevent duplicates.
     */
    public function generateWithLock(int $tenantId): string
    {
        $prefix = config('app.name', 'ORD');
        $datePrefix = now()->format('Ym');

        return DB::transaction(function () use ($prefix, $datePrefix, $tenantId) {
            // Lock the orders table for this tenant
            $count = Order::where('tenant_id', $tenantId)
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->lockForUpdate()
                ->count();

            $sequence = str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);

            return "{$prefix}-{$datePrefix}-{$sequence}";
        });
    }
}
