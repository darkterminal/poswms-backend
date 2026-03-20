<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PricingRule;
use App\Models\Product;

class PriceCalculationService
{
    /**
     * Calculate the final price for a product considering all applicable rules.
     *
     * @param  Product  $product  The product to calculate price for
     * @param  int  $quantity  The quantity being purchased
     * @param  Customer|null  $customer  The customer (for tier-based pricing)
     * @return array{base_price: float, final_price: float, discount: float, rules_applied: array}
     */
    public function calculatePrice(
        Product $product,
        int $quantity = 1,
        ?Customer $customer = null
    ): array {
        $basePrice = (float) $product->price;
        $currentPrice = $basePrice;
        $rulesApplied = [];

        // Get customer's pricing tier rules if customer has a tier
        if ($customer && $customer->pricing_tier_id) {
            $tierRules = $this->getApplicableRules($product, $customer->pricing_tier_id, $quantity);

            foreach ($tierRules as $rule) {
                $newPrice = $rule->calculatePrice($currentPrice, $quantity);
                if ($newPrice !== $currentPrice) {
                    $rulesApplied[] = [
                        'rule_id' => $rule->id,
                        'rule_name' => $rule->name ?? "Rule #{$rule->id}",
                        'type' => $rule->type,
                        'operation' => $rule->operation,
                        'value' => (float) $rule->value,
                        'price_before' => $currentPrice,
                        'price_after' => $newPrice,
                    ];
                    $currentPrice = $newPrice;
                }
            }
        }

        // Get general rules (not tied to a specific tier) that apply to this product
        $generalRules = $this->getGeneralApplicableRules($product, $quantity);

        foreach ($generalRules as $rule) {
            $newPrice = $rule->calculatePrice($currentPrice, $quantity);
            if ($newPrice !== $currentPrice) {
                $rulesApplied[] = [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name ?? "Rule #{$rule->id}",
                    'type' => $rule->type,
                    'operation' => $rule->operation,
                    'value' => (float) $rule->value,
                    'price_before' => $currentPrice,
                    'price_after' => $newPrice,
                ];
                $currentPrice = $newPrice;
            }
        }

        // Ensure price doesn't go below zero
        $finalPrice = max(0, $currentPrice);

        return [
            'base_price' => $basePrice,
            'final_price' => round($finalPrice, 2),
            'discount' => round($basePrice - $finalPrice, 2),
            'rules_applied' => $rulesApplied,
        ];
    }

    /**
     * Get all applicable pricing rules for a product and tier.
     */
    private function getApplicableRules(Product $product, int $tierId, int $quantity): array
    {
        return PricingRule::where('tenant_id', $product->tenant_id)
            ->where('pricing_tier_id', $tierId)
            ->where('active', true)
            ->where(function ($query) use ($product) {
                $query->whereNull('product_id')
                    ->orWhere('product_id', $product->id);
            })
            ->where(function ($query) use ($product) {
                $query->whereNull('category_id')
                    ->orWhere('category_id', $product->category_id);
            })
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->orderBy('id', 'desc')
            ->get()
            ->filter(fn ($rule) => $rule->appliesToQuantity($quantity))
            ->values()
            ->all();
    }

    /**
     * Get general pricing rules (not tied to a specific tier).
     */
    private function getGeneralApplicableRules(Product $product, int $quantity): array
    {
        return PricingRule::where('tenant_id', $product->tenant_id)
            ->whereNull('pricing_tier_id')
            ->where('active', true)
            ->where(function ($query) use ($product) {
                $query->whereNull('product_id')
                    ->orWhere('product_id', $product->id);
            })
            ->where(function ($query) use ($product) {
                $query->whereNull('category_id')
                    ->orWhere('category_id', $product->category_id);
            })
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->orderBy('id', 'desc')
            ->get()
            ->filter(fn ($rule) => $rule->appliesToQuantity($quantity))
            ->values()
            ->all();
    }

    /**
     * Calculate price for multiple products (e.g., shopping cart).
     *
     * @param  array  $items  Array of ['product_id' => int, 'quantity' => int]
     * @param  Customer|null  $customer  The customer
     * @return array{subtotal: float, discount: float, total: float, items: array}
     */
    public function calculateCartPrice(array $items, ?Customer $customer = null): array
    {
        $subtotal = 0;
        $total = 0;
        $totalDiscount = 0;
        $calculatedItems = [];

        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            if (! $product) {
                continue;
            }

            $quantity = $item['quantity'] ?? 1;
            $calculation = $this->calculatePrice($product, $quantity, $customer);

            $itemSubtotal = $calculation['base_price'] * $quantity;
            $itemTotal = $calculation['final_price'] * $quantity;
            $itemDiscount = $itemSubtotal - $itemTotal;

            $subtotal += $itemSubtotal;
            $total += $itemTotal;
            $totalDiscount += $itemDiscount;

            $calculatedItems[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'base_price' => $calculation['base_price'],
                'final_price' => $calculation['final_price'],
                'subtotal' => round($itemSubtotal, 2),
                'discount' => round($itemDiscount, 2),
                'total' => round($itemTotal, 2),
                'rules_applied' => $calculation['rules_applied'],
            ];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($totalDiscount, 2),
            'total' => round($total, 2),
            'items' => $calculatedItems,
        ];
    }
}
