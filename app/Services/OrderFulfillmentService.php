<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class OrderFulfillmentService
{
    /**
     * Fulfill an order by deducting inventory.
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
            $firstProductId = null;

            // Deduct inventory for each order item
            foreach ($order->items as $item) {
                $result = $this->deductInventory($order, $item);
                $totalQuantity += $item->quantity;
                if (! $firstProductId) {
                    $firstProductId = $item->product_id;
                }
            }

            // Update order status
            $order->fulfill();

            // Record stock movement for the order (if items were processed)
            if ($totalQuantity > 0 && $firstProductId) {
                StockMovement::create([
                    'tenant_id' => $order->tenant_id,
                    'product_id' => $firstProductId,
                    'store_id' => $order->store_id,
                    'warehouse_id' => $order->warehouse_id,
                    'order_id' => $order->id,
                    'quantity' => -$totalQuantity,
                    'quantity_before' => 0,
                    'quantity_after' => -$totalQuantity,
                    'type' => 'order_fulfillment',
                    'reference' => "Order #{$order->order_number}",
                    'reason' => 'Order fulfillment',
                ]);
            }
        });
    }

    /**
     * Deduct inventory for a single order item.
     */
    private function deductInventory(Order $order, $item): bool
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

        if ($inventory->available < $item->quantity) {
            throw new \Exception(
                "Insufficient inventory for product {$item->product_id}. ".
                "Available: {$inventory->available}, Required: {$item->quantity}"
            );
        }

        // Deduct the quantity
        $quantityBefore = $inventory->quantity;
        $inventory->updateQuantity(-$item->quantity);
        $quantityAfter = $inventory->quantity;

        // Record stock movement
        StockMovement::create([
            'tenant_id' => $order->tenant_id,
            'product_id' => $item->product_id,
            'store_id' => $order->store_id,
            'warehouse_id' => $order->warehouse_id,
            'inventory_id' => $inventory->id,
            'order_id' => $order->id,
            'quantity' => -$item->quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'type' => 'sale',
            'reference' => "Order #{$order->order_number} - Item {$item->id}",
            'reason' => 'Order fulfillment',
        ]);

        return true;
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
}
