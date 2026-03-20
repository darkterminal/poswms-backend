<?php

namespace App\Http\Controllers;

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
        $orders = Order::where('tenant_id', $request->route('tenant_id'))
            ->with(['customer', 'store', 'warehouse', 'items'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['orders' => $orders],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_number' => 'string|max:100',
            'customer_id' => 'nullable|exists:customers,id',
            'store_id' => 'nullable|exists:stores,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'status' => 'string|in:pending,confirmed,fulfilled,cancelled',
            'type' => 'string|max:50',
            'subtotal' => 'numeric|min:0',
            'tax' => 'numeric|min:0',
            'discount' => 'numeric|min:0',
            'shipping' => 'numeric|min:0',
            'payment_status' => 'string|max:50',
            'payment_method' => 'string|max:100',
            'notes' => 'string|nullable',
            'shipping_address' => 'string|nullable',
            'shipping_city' => 'string|max:255',
            'shipping_state' => 'string|max:255',
            'shipping_country' => 'string|max:255',
            'shipping_postal_code' => 'string|max:50',
            'items' => 'array|nullable',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
        ]);

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
    public function show(Request $request, int $order): JsonResponse
    {
        $order = Order::where('tenant_id', $request->route('tenant_id'))
            ->findOrFail($order);

        return response()->json([
            'success' => true,
            'data' => ['order' => $order->load(['customer', 'items'])],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $order): JsonResponse
    {
        $order = Order::where('tenant_id', $request->route('tenant_id'))
            ->findOrFail($order);

        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'store_id' => 'nullable|exists:stores,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'status' => 'string|in:pending,confirmed,fulfilled,cancelled',
            'type' => 'string|max:50',
            'subtotal' => 'numeric|min:0',
            'tax' => 'numeric|min:0',
            'discount' => 'numeric|min:0',
            'shipping' => 'numeric|min:0',
            'payment_status' => 'string|max:50',
            'payment_method' => 'string|max:100',
            'notes' => 'string|nullable',
            'shipping_address' => 'string|nullable',
            'shipping_city' => 'string|max:255',
            'shipping_state' => 'string|max:255',
            'shipping_country' => 'string|max:255',
            'shipping_postal_code' => 'string|max:50',
        ]);

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
    public function destroy(Request $request, int $order): JsonResponse
    {
        $order = Order::where('tenant_id', $request->route('tenant_id'))
            ->findOrFail($order);

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Order deleted successfully',
        ]);
    }

    /**
     * Confirm the order.
     */
    public function confirm(Request $request, int $order): JsonResponse
    {
        $order = Order::where('tenant_id', $request->route('tenant_id'))
            ->findOrFail($order);

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
    public function fulfill(Request $request, int $order): JsonResponse
    {
        $order = Order::where('tenant_id', $request->route('tenant_id'))
            ->findOrFail($order);

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
    public function cancel(Request $request, int $order): JsonResponse
    {
        $order = Order::where('tenant_id', $request->route('tenant_id'))
            ->findOrFail($order);

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
