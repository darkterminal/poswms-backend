<?php

namespace App\Http\Requests;

class StoreInventoryRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Soft enforcement: check for inventory.create permission
        return $this->authorizeSoft('inventory.create', 'create inventory record');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'store_id' => 'nullable|exists:stores,id',
            'quantity' => 'required|integer|min:0',
            'reserved' => 'nullable|integer|min:0',
            'available' => 'nullable|integer|min:0',
            'cost' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
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
            'product_id.required' => 'Product is required.',
            'product_id.exists' => 'The selected product does not exist.',
            'warehouse_id.exists' => 'The selected warehouse does not exist.',
            'store_id.exists' => 'The selected store does not exist.',
            'quantity.required' => 'Quantity is required.',
            'quantity.integer' => 'Quantity must be an integer.',
            'quantity.min' => 'Quantity cannot be negative.',
            'reserved.integer' => 'Reserved must be an integer.',
            'reserved.min' => 'Reserved cannot be negative.',
            'available.integer' => 'Available must be an integer.',
            'available.min' => 'Available cannot be negative.',
            'cost.numeric' => 'Cost must be a valid number.',
            'cost.min' => 'Cost cannot be negative.',
            'location.max' => 'Location cannot exceed 255 characters.',
        ];
    }
}
