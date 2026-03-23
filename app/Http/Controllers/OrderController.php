<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderFulfillmentService;
use App\Services\OrderNumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderFulfillmentService $fulfillmentService,
        protected OrderNumberGenerator $numberGenerator
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');

        $query = Order::where('tenant_id', $tenantId)
            ->with(['customer', 'store', 'warehouse', 'items']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by store
        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        // Filter by customer
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Paginate results
        $perPage = $request->get('per_page', 15);
        $orders = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => $orders->items(),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'last_page' => $orders->lastPage(),
                    'has_more' => $orders->hasMorePages(),
                ],
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['tenant_id'] = $request->route('tenant_id');
        $validated['status'] = $validated['status'] ?? 'pending';
        $validated['type'] = $validated['type'] ?? 'sale';
        $validated['payment_status'] = $validated['payment_status'] ?? 'pending';
        $validated['user_id'] = auth()->id();

        // Generate sequential order number if not provided
        if (empty($validated['order_number'])) {
            $validated['order_number'] = $this->numberGenerator->generateWithLock($validated['tenant_id']);
        }

        // Calculate totals if not provided
        if (isset($validated['items']) && count($validated['items']) > 0) {
            $validated['subtotal'] = 0;
            foreach ($validated['items'] as $item) {
                $validated['subtotal'] += $item['unit_price'] * $item['quantity'];
            }
            $validated['total'] = $validated['subtotal'] + ($validated['tax'] ?? 0) + ($validated['shipping'] ?? 0) - ($validated['discount'] ?? 0);
        } else {
            $validated['subtotal'] = $validated['subtotal'] ?? 0;
            $validated['total'] = $validated['total'] ?? 0;
        }

        $order = Order::create($validated);

        // Create order items
        if (isset($validated['items']) && count($validated['items']) > 0) {
            foreach ($validated['items'] as $item) {
                OrderItem::create([
                    'tenant_id' => $validated['tenant_id'],
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $item['unit_price'] * $item['quantity'],
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => ['order' => $order],
            'message' => 'Order created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $orderId = $request->route('orderId');

        $order = Order::where('tenant_id', $tenantId)
            ->findOrFail($orderId);

        return response()->json([
            'success' => true,
            'data' => ['order' => $order->load(['customer', 'items'])],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOrderRequest $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $orderId = $request->route('orderId');

        $order = Order::where('tenant_id', $tenantId)
            ->findOrFail($orderId);

        $validated = $request->validated();

        $order->update($validated);

        return response()->json([
            'success' => true,
            'data' => ['order' => $order],
            'message' => 'Order updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $orderId = $request->route('orderId');

        $order = Order::where('tenant_id', $tenantId)
            ->findOrFail($orderId);

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Order deleted successfully',
        ]);
    }

    /**
     * Confirm the order.
     */
    public function confirm(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $orderId = $request->route('orderId');

        $order = Order::where('tenant_id', $tenantId)
            ->findOrFail($orderId);

        $order->confirm();

        return response()->json([
            'success' => true,
            'data' => ['order' => $order],
            'message' => 'Order confirmed successfully',
        ]);
    }

    /**
     * Fulfill the order.
     */
    public function fulfill(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $orderId = $request->route('orderId');

        $order = Order::where('tenant_id', $tenantId)
            ->findOrFail($orderId);

        try {
            $this->fulfillmentService->fulfill($order);

            return response()->json([
                'success' => true,
                'data' => ['order' => $order->fresh()],
                'message' => 'Order fulfilled successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancel the order.
     */
    public function cancel(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenant_id');
        $orderId = $request->route('orderId');

        $order = Order::where('tenant_id', $tenantId)
            ->findOrFail($orderId);

        try {
            $this->fulfillmentService->cancel($order);

            return response()->json([
                'success' => true,
                'data' => ['order' => $order->fresh()],
                'message' => 'Order cancelled successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
