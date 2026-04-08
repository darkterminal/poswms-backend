<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    /**
     * List orders across all tenants with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'status' => ['nullable', 'string', 'in:pending,confirmed,fulfilled,cancelled'],
            'type' => ['nullable', 'string', 'in:sale,purchase,transfer'],
            'payment_status' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort_by' => ['nullable', 'string', 'in:order_number,total,created_at,status'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 20;

        $query = Order::with(['tenant', 'customer', 'store', 'warehouse', 'items.product'])
            ->select('orders.*');

        // Filter by tenant
        if (! empty($validated['tenant_id'])) {
            $query->where('orders.tenant_id', $validated['tenant_id']);
        }

        // Search by order number or customer name
        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('orders.order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by status
        if (! empty($validated['status'])) {
            $query->where('orders.status', $validated['status']);
        }

        // Filter by type
        if (! empty($validated['type'])) {
            $query->where('orders.type', $validated['type']);
        }

        // Filter by payment status
        if (! empty($validated['payment_status'])) {
            $query->where('orders.payment_status', $validated['payment_status']);
        }

        // Filter by date range
        if (! empty($validated['date_from'])) {
            $query->where('orders.created_at', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->where('orders.created_at', '<=', $validated['date_to'] . ' 23:59:59');
        }

        // Sorting
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDirection = $validated['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        $orders = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => $orders->getCollection()->map(fn($order) => [
                    'id' => $order->id,
                    'tenant_id' => $order->tenant_id,
                    'tenant_name' => $order->tenant?->name,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer?->name,
                    'store_name' => $order->store?->name,
                    'warehouse_name' => $order->warehouse?->name,
                    'status' => $order->status,
                    'type' => $order->type,
                    'subtotal' => $order->subtotal,
                    'tax' => $order->tax,
                    'discount' => $order->discount,
                    'shipping' => $order->shipping,
                    'total' => $order->total,
                    'payment_status' => $order->payment_status,
                    'payment_method' => $order->payment_method,
                    'items_count' => $order->items->count(),
                    'created_at' => $order->created_at->toIso8601String(),
                    'updated_at' => $order->updated_at->toIso8601String(),
                    'confirmed_at' => $order->confirmed_at?->toIso8601String(),
                    'fulfilled_at' => $order->fulfilled_at?->toIso8601String(),
                    'cancelled_at' => $order->cancelled_at?->toIso8601String(),
                ]),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'last_page' => $orders->lastPage(),
                    'has_more' => $orders->hasMorePages(),
                ],
            ],
            'message' => 'Orders retrieved successfully',
        ], 200);
    }

    /**
     * Get order statistics across all tenants.
     */
    public function stats(): JsonResponse
    {
        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $confirmedOrders = Order::where('status', 'confirmed')->count();
        $fulfilledOrders = Order::where('status', 'fulfilled')->count();
        $cancelledOrders = Order::where('status', 'cancelled')->count();

        $totalRevenue = Order::whereIn('status', ['confirmed', 'fulfilled'])
            ->sum('total');

        $avgOrderValue = $totalOrders > 0
            ? Order::avg('total')
            : 0;

        $tenantsWithOrders = Order::distinct('tenant_id')->count('tenant_id');

        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'confirmed_orders' => $confirmedOrders,
                'fulfilled_orders' => $fulfilledOrders,
                'cancelled_orders' => $cancelledOrders,
                'total_revenue' => round($totalRevenue, 2),
                'avg_order_value' => round($avgOrderValue, 2),
                'tenants_with_orders' => $tenantsWithOrders,
            ],
            'message' => 'Order statistics retrieved successfully',
        ], 200);
    }

    /**
     * Get a single order details.
     */
    public function show($orderId): JsonResponse
    {
        // Bypass tenant scope for super admin
        $order = Order::withoutGlobalScopes()
            ->with(['tenant', 'customer', 'store', 'warehouse', 'items.product'])
            ->findOrFail($orderId);

        return response()->json([
            'success' => true,
            'data' => [
                'order' => [
                    'id' => $order->id,
                    'tenant_id' => $order->tenant_id,
                    'tenant_name' => $order->tenant?->name,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer?->name,
                    'store_name' => $order->store?->name,
                    'warehouse_name' => $order->warehouse?->name,
                    'status' => $order->status,
                    'type' => $order->type,
                    'subtotal' => $order->subtotal,
                    'tax' => $order->tax,
                    'discount' => $order->discount,
                    'shipping' => $order->shipping,
                    'total' => $order->total,
                    'payment_status' => $order->payment_status,
                    'payment_method' => $order->payment_method,
                    'notes' => $order->notes,
                    'shipping_address' => $order->shipping_address,
                    'shipping_city' => $order->shipping_city,
                    'shipping_state' => $order->shipping_state,
                    'shipping_country' => $order->shipping_country,
                    'shipping_postal_code' => $order->shipping_postal_code,
                    'items_count' => $order->items->count(),
                    'items' => $order->items->map(fn($item) => [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product?->name,
                        'product_sku' => $item->product?->sku,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'tax' => $item->tax,
                        'discount' => $item->discount,
                        'total' => $item->total,
                    ]),
                    'created_at' => $order->created_at->toIso8601String(),
                    'updated_at' => $order->updated_at->toIso8601String(),
                    'confirmed_at' => $order->confirmed_at?->toIso8601String(),
                    'fulfilled_at' => $order->fulfilled_at?->toIso8601String(),
                    'cancelled_at' => $order->cancelled_at?->toIso8601String(),
                ],
            ],
            'message' => 'Order retrieved successfully',
        ], 200);
    }

    /**
     * Confirm an order.
     */
    public function confirm($orderId): JsonResponse
    {
        // Bypass tenant scope for super admin
        $order = Order::withoutGlobalScopes()
            ->with(['tenant', 'customer', 'store', 'warehouse', 'items.product'])
            ->findOrFail($orderId);

        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending orders can be confirmed',
            ], 422);
        }

        $order->status = 'confirmed';
        $order->confirmed_at = now();
        $order->save();

        return response()->json([
            'success' => true,
            'data' => [
                'order' => $this->formatOrder($order),
            ],
            'message' => 'Order confirmed successfully',
        ], 200);
    }

    /**
     * Fulfill an order.
     */
    public function fulfill($orderId): JsonResponse
    {
        // Bypass tenant scope for super admin
        $order = Order::withoutGlobalScopes()
            ->with(['tenant', 'customer', 'store', 'warehouse', 'items.product'])
            ->findOrFail($orderId);

        if ($order->status !== 'confirmed') {
            return response()->json([
                'success' => false,
                'message' => 'Only confirmed orders can be fulfilled',
            ], 422);
        }

        $order->status = 'fulfilled';
        $order->fulfilled_at = now();
        $order->save();

        return response()->json([
            'success' => true,
            'data' => [
                'order' => $this->formatOrder($order),
            ],
            'message' => 'Order fulfilled successfully',
        ], 200);
    }

    /**
     * Cancel an order.
     */
    public function cancel($orderId, Request $request): JsonResponse
    {
        // Bypass tenant scope for super admin
        $order = Order::withoutGlobalScopes()
            ->with(['tenant', 'customer', 'store', 'warehouse', 'items.product'])
            ->findOrFail($orderId);

        if ($order->status === 'fulfilled') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel a fulfilled order',
            ], 422);
        }

        $order->status = 'cancelled';
        $order->cancelled_at = now();
        if ($request->has('reason')) {
            $order->notes = ($order->notes ? $order->notes . "\n\n" : '') . 'Cancellation reason: ' . $request->input('reason');
        }
        $order->save();

        return response()->json([
            'success' => true,
            'data' => [
                'order' => $this->formatOrder($order),
            ],
            'message' => 'Order cancelled successfully',
        ], 200);
    }

    /**
     * Export orders to CSV.
     */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'status' => ['nullable', 'string', 'in:pending,confirmed,fulfilled,cancelled'],
            'type' => ['nullable', 'string', 'in:sale,purchase,transfer'],
            'payment_status' => ['nullable', 'string', 'max:50'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort_by' => ['nullable', 'string', 'in:order_number,total,created_at,status'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $query = Order::with(['tenant', 'customer', 'store', 'warehouse'])
            ->select('orders.*');

        // Apply same filters as index
        if (! empty($validated['tenant_id'])) {
            $query->where('orders.tenant_id', $validated['tenant_id']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('orders.order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($validated['status'])) {
            $query->where('orders.status', $validated['status']);
        }

        if (! empty($validated['type'])) {
            $query->where('orders.type', $validated['type']);
        }

        if (! empty($validated['payment_status'])) {
            $query->where('orders.payment_status', $validated['payment_status']);
        }

        if (! empty($validated['date_from'])) {
            $query->where('orders.created_at', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->where('orders.created_at', '<=', $validated['date_to'] . ' 23:59:59');
        }

        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortDirection = $validated['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);

        $orders = $query->get();

        // Generate CSV
        $filename = 'pos-orders-' . date('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($orders) {
            $file = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($file, [
                'ID',
                'Order Number',
                'Tenant Name',
                'Customer Name',
                'Store Name',
                'Status',
                'Type',
                'Subtotal',
                'Tax',
                'Discount',
                'Shipping',
                'Total',
                'Payment Status',
                'Payment Method',
                'Items Count',
                'Created At',
            ]);

            // CSV Data
            foreach ($orders as $order) {
                fputcsv($file, [
                    $order->id,
                    $order->order_number,
                    $order->tenant?->name,
                    $order->customer?->name,
                    $order->store?->name,
                    $order->status,
                    $order->type,
                    $order->subtotal,
                    $order->tax,
                    $order->discount,
                    $order->shipping,
                    $order->total,
                    $order->payment_status,
                    $order->payment_method,
                    $order->items()->count(),
                    $order->created_at->toIso8601String(),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Format order object for response.
     */
    private function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'tenant_id' => $order->tenant_id,
            'tenant_name' => $order->tenant?->name,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer?->name,
            'store_name' => $order->store?->name,
            'warehouse_name' => $order->warehouse?->name,
            'status' => $order->status,
            'type' => $order->type,
            'subtotal' => $order->subtotal,
            'tax' => $order->tax,
            'discount' => $order->discount,
            'shipping' => $order->shipping,
            'total' => $order->total,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
            'notes' => $order->notes,
            'shipping_address' => $order->shipping_address,
            'shipping_city' => $order->shipping_city,
            'shipping_state' => $order->shipping_state,
            'shipping_country' => $order->shipping_country,
            'shipping_postal_code' => $order->shipping_postal_code,
            'items_count' => $order->items->count(),
            'items' => $order->items->map(fn($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'product_sku' => $item->product?->sku,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'tax' => $item->tax,
                'discount' => $item->discount,
                'total' => $item->total,
            ]),
            'created_at' => $order->created_at->toIso8601String(),
            'updated_at' => $order->updated_at->toIso8601String(),
            'confirmed_at' => $order->confirmed_at?->toIso8601String(),
            'fulfilled_at' => $order->fulfilled_at?->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
        ];
    }
}
