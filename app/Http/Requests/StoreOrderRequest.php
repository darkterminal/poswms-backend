<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_number' => 'nullable|string|max:100',
            'customer_id' => 'nullable|exists:customers,id',
            'store_id' => 'nullable|exists:stores,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'status' => 'nullable|string|in:pending,confirmed,fulfilled,cancelled',
            'type' => 'nullable|string|max:50',
            'subtotal' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'shipping' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
            'payment_status' => 'nullable|string|max:50',
            'payment_method' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'shipping_address' => 'nullable|string',
            'shipping_city' => 'nullable|string|max:255',
            'shipping_state' => 'nullable|string|max:255',
            'shipping_country' => 'nullable|string|max:255',
            'shipping_postal_code' => 'nullable|string|max:50',
            'items' => 'nullable|array',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.exists' => 'The selected customer does not exist.',
            'store_id.exists' => 'The selected store does not exist.',
            'warehouse_id.exists' => 'The selected warehouse does not exist.',
            'status.in' => 'Status must be one of: pending, confirmed, fulfilled, cancelled.',
            'subtotal.numeric' => 'Subtotal must be a valid number.',
            'subtotal.min' => 'Subtotal cannot be negative.',
            'tax.numeric' => 'Tax must be a valid number.',
            'tax.min' => 'Tax cannot be negative.',
            'discount.numeric' => 'Discount must be a valid number.',
            'discount.min' => 'Discount cannot be negative.',
            'shipping.numeric' => 'Shipping must be a valid number.',
            'shipping.min' => 'Shipping cannot be negative.',
            'total.numeric' => 'Total must be a valid number.',
            'total.min' => 'Total cannot be negative.',
            'items.*.product_id.exists' => 'One or more products do not exist.',
            'items.*.quantity.integer' => 'Quantity must be an integer.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'items.*.unit_price.numeric' => 'Unit price must be a valid number.',
            'items.*.unit_price.min' => 'Unit price cannot be negative.',
        ];
    }
}
