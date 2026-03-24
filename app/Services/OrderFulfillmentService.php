<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class OrderFulfillmentService
{
    public function __construct(
        private readonly FifoService $fifoService
    ) {}

    /**
     * Fulfill an order by deducting inventory using FIFO.
     *
     * @throws \Exception
     */
    public function fulfill(Order $order): void
    {
        if (! $order->isConfirmed()) {
            throw new \Exception('Order must be confirmed before fulfillment');
        }

        DB::transaction(function () use ($order) {
            $totalQuantity = 0;
            $totalCost = 0.0;
            $firstProductId = null;
            $fifoDetails = [];

            // Deduct inventory for each order item using FIFO
            foreach ($order->items as $item) {
                $result = $this->deductInventory($order, $item);
                $totalQuantity += $item->quantity;
                $totalCost += $result['total_cost'] ?? 0;

                if (! $firstProductId) {
                    $firstProductId = $item->product_id;
                }

                $fifoDetails[$item->product_id] = $result;
            }

            // Update order status
            $order->fulfill();

            // Record summary stock movement for the order
            if ($totalQuantity > 0 && $firstProductId) {
                $inventory = Inventory::where('tenant_id', $order->tenant_id)
                    ->where('product_id', $firstProductId)
                    ->first();

                StockMovement::create([
                    'tenant_id' => $order->tenant_id,
                    'product_id' => $firstProductId,
                    'store_id' => $order->store_id,
                    'warehouse_id' => $order->warehouse_id,
                    'order_id' => $order->id,
                    'inventory_id' => $inventory?->id,
                    'quantity' => $totalQuantity,
                    'unit_cost' => $totalQuantity > 0 ? $totalCost / $totalQuantity : 0,
                    'total_cost' => $totalCost,
                    'quantity_before' => 0,
                    'quantity_after' => -$totalQuantity,
                    'type' => 'order_fulfillment',
                    'reference' => "Order #{$order->order_number}",
                    'reason' => 'Order fulfillment',
                ]);
            }

            // Attach FIFO details to order for reporting
            $order->update([
                'meta' => array_merge($order->meta ?? [], [
                    'fulfillment_fifo' => $fifoDetails,
                    'fulfillment_cost' => $totalCost,
                ]),
            ]);
        });
    }

    /**
     * Deduct inventory for a single order item using FIFO.
     *
     * @return array{consumed: int, total_cost: float, layers: array}
     */
    private function deductInventory(Order $order, $item): array
    {
        $inventory = Inventory::where('tenant_id', $order->tenant_id)
            ->where('product_id', $item->product_id)
            ->where(function ($query) use ($order) {
                if ($order->warehouse_id) {
                    $query->where('warehouse_id', $order->warehouse_id);
                }
                if ($order->store_id) {
                    $query->where('store_id', $order->store_id);
                }
            })
            ->first();

        if (! $inventory) {
            throw new \Exception(
                "Inventory not found for product {$item->product_id} in specified location"
            );
        }

        // Check available stock (FIFO-aware)
        $availableStock = $inventory->hasFifoLayers()
            ? $inventory->getLayerAvailableQuantity()
            : $inventory->available;

        if ($availableStock < $item->quantity) {
            throw new \Exception(
                "Insufficient inventory for product {$item->product_id}. " .
                "Available: {$availableStock}, Required: {$item->quantity}"
            );
        }

        // Use FIFO consumption if layers exist
        if ($inventory->hasFifoLayers()) {
            return $inventory->consumeQuantity(
                quantity: $item->quantity,
                type: 'sale',
                orderId: $order->id
            );
        }

        // Fallback to legacy deduction
        return $this->legacyDeductInventory($inventory, $item, $order);
    }

    /**
     * Legacy inventory deduction (backward compatible).
     */
    private function legacyDeductInventory(Inventory $inventory, $item, Order $order): array
    {
        $quantityBefore = $inventory->quantity;
        $inventory->updateQuantity(-$item->quantity);
        $quantityAfter = $inventory->quantity;

        $cost = $item->quantity * $inventory->cost;

        // Record stock movement
        StockMovement::create([
            'tenant_id' => $order->tenant_id,
            'product_id' => $item->product_id,
            'store_id' => $order->store_id,
            'warehouse_id' => $order->warehouse_id,
            'inventory_id' => $inventory->id,
            'order_id' => $order->id,
            'quantity' => $item->quantity,
            'unit_cost' => $inventory->cost,
            'total_cost' => $cost,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'type' => 'sale',
            'reference' => "Order #{$order->order_number} - Item {$item->id}",
            'reason' => 'Order fulfillment',
        ]);

        return [
            'consumed' => $item->quantity,
            'total_cost' => $cost,
            'layers' => [],
        ];
    }

    /**
     * Cancel an order and restore inventory.
     */
    public function cancel(Order $order): void
    {
        if ($order->isFulfilled()) {
            throw new \Exception('Cannot cancel a fulfilled order');
        }

        DB::transaction(function () use ($order) {
            // Restore reserved quantities if order was confirmed
            if ($order->isConfirmed()) {
                foreach ($order->items as $item) {
                    $inventory = Inventory::where('tenant_id', $order->tenant_id)
                        ->where('product_id', $item->product_id)
                        ->first();

                    if ($inventory) {
                        $inventory->releaseQuantity($item->quantity);
                    }
                }
            }

            // Update order status
            $order->cancel();
        });
    }

    /**
     * Get estimated fulfillment cost using FIFO.
     */
    public function getEstimatedFulfillmentCost(Order $order): array
    {
        $totalCost = 0.0;
        $productCosts = [];

        foreach ($order->items as $item) {
            $inventory = Inventory::where('tenant_id', $order->tenant_id)
                ->where('product_id', $item->product_id)
                ->where(function ($query) use ($order) {
                    if ($order->warehouse_id) {
                        $query->where('warehouse_id', $order->warehouse_id);
                    }
                    if ($order->store_id) {
                        $query->where('store_id', $order->store_id);
                    }
                })
                ->first();

            if ($inventory) {
                if ($inventory->hasFifoLayers()) {
                    // Calculate cost based on FIFO layers
                    $layers = $inventory->layers()
                        ->fifoLayers()
                        ->fifoOrder()
                        ->get();

                    $remainingQty = $item->quantity;
                    $itemCost = 0.0;

                    foreach ($layers as $layer) {
                        if ($remainingQty <= 0) {
                            break;
                        }

                        $consumeFromLayer = min($remainingQty, $layer->available);
                        $itemCost += $consumeFromLayer * $layer->unit_cost;
                        $remainingQty -= $consumeFromLayer;
                    }

                    $productCosts[$item->product_id] = [
                        'quantity' => $item->quantity,
                        'cost' => $itemCost,
                        'average_cost' => $item->quantity > 0 ? $itemCost / $item->quantity : 0,
                    ];

                    $totalCost += $itemCost;
                } else {
                    // Use standard cost
                    $itemCost = $item->quantity * $inventory->cost;
                    $productCosts[$item->product_id] = [
                        'quantity' => $item->quantity,
                        'cost' => $itemCost,
                        'average_cost' => $inventory->cost,
                    ];
                    $totalCost += $itemCost;
                }
            }
        }

        return [
            'total_cost' => $totalCost,
            'product_costs' => $productCosts,
        ];
    }
}
