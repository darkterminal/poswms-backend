<?php

namespace App\Http\Requests;

class InventoryTransferRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Soft enforcement: check for inventory.transfer permission
        return $this->authorizeSoft('inventory.transfer', 'transfer inventory');
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
            'quantity' => 'required|integer|min:1',
            'from_warehouse_id' => 'nullable|exists:warehouses,id',
            'from_store_id' => 'nullable|exists:stores,id',
            'to_warehouse_id' => 'nullable|exists:warehouses,id',
            'to_store_id' => 'nullable|exists:stores,id',
            'reason' => 'nullable|string|max:255',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Ensure at least one source location is provided
            if (! $this->filled('from_warehouse_id') && ! $this->filled('from_store_id')) {
                $validator->errors()->add(
                    'from_warehouse_id',
                    'Either source warehouse or source store must be provided.'
                );
            }

            // Ensure at least one destination location is provided
            if (! $this->filled('to_warehouse_id') && ! $this->filled('to_store_id')) {
                $validator->errors()->add(
                    'to_warehouse_id',
                    'Either destination warehouse or destination store must be provided.'
                );
            }

            // Prevent transfer from and to the same location
            if (
                $this->filled('from_warehouse_id') &&
                $this->filled('to_warehouse_id') &&
                $this->input('from_warehouse_id') === $this->input('to_warehouse_id') &&
                ! $this->filled('from_store_id') &&
                ! $this->filled('to_store_id')
            ) {
                $validator->errors()->add(
                    'to_warehouse_id',
                    'Source and destination warehouse cannot be the same.'
                );
            }

            if (
                $this->filled('from_store_id') &&
                $this->filled('to_store_id') &&
                $this->input('from_store_id') === $this->input('to_store_id') &&
                ! $this->filled('from_warehouse_id') &&
                ! $this->filled('to_warehouse_id')
            ) {
                $validator->errors()->add(
                    'to_store_id',
                    'Source and destination store cannot be the same.'
                );
            }
        });
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
            'quantity.required' => 'Quantity to transfer is required.',
            'quantity.integer' => 'Quantity must be an integer.',
            'quantity.min' => 'Quantity must be at least 1.',
            'from_warehouse_id.exists' => 'The source warehouse does not exist.',
            'from_store_id.exists' => 'The source store does not exist.',
            'to_warehouse_id.exists' => 'The destination warehouse does not exist.',
            'to_store_id.exists' => 'The destination store does not exist.',
            'reason.max' => 'Reason cannot exceed 255 characters.',
        ];
    }
}
