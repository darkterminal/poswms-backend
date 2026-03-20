<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Services\PriceCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceCalculationController extends Controller
{
    public function __construct(
        protected PriceCalculationService $calculationService
    ) {}

    /**
     * Calculate price for a single product.
     *
     * @bodyParam product_id int required The product ID
     * @bodyParam quantity int The quantity (default: 1)
     * @bodyParam customer_id int|null The customer ID (for tier-based pricing)
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'integer|min:1',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $product = Product::where('tenant_id', $request->route('tenant_id'))
            ->findOrFail($validated['product_id']);

        $customer = null;
        if (isset($validated['customer_id'])) {
            $customer = Customer::where('tenant_id', $request->route('tenant_id'))
                ->findOrFail($validated['customer_id']);
        }

        $quantity = $validated['quantity'] ?? 1;
        $result = $this->calculationService->calculatePrice($product, $quantity, $customer);

        return response()->json([
            'success' => true,
            'data' => [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                ],
                'quantity' => $quantity,
                'customer' => $customer ? [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'pricing_tier' => $customer->pricingTier?->name,
                ] : null,
                'pricing' => $result,
            ],
        ]);
    }

    /**
     * Calculate price for multiple products (shopping cart).
     *
     * @bodyParam items array required Array of {product_id, quantity}
     * @bodyParam customer_id int|null The customer ID (for tier-based pricing)
     */
    public function calculateCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'integer|min:1',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $customer = null;
        if (isset($validated['customer_id'])) {
            $customer = Customer::where('tenant_id', $request->route('tenant_id'))
                ->findOrFail($validated['customer_id']);
        }

        $items = array_map(function ($item) {
            return [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'] ?? 1,
            ];
        }, $validated['items']);

        $result = $this->calculationService->calculateCartPrice($items, $customer);

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => $customer ? [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'pricing_tier' => $customer->pricingTier?->name,
                ] : null,
                'pricing' => $result,
            ],
        ]);
    }
}
