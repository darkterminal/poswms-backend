<?php

namespace App\Http\Requests;

class StockAdjustmentRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Soft enforcement: check for inventory.adjust permission
        return $this->authorizeSoft('inventory.adjust', 'adjust inventory');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'inventory_id' => 'required|exists:inventories,id',
            'quantity' => 'required|integer',
            'adjustment_type' => 'required|in:set,add,subtract',
            'unit_cost' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:255',
            'batch_number' => 'nullable|string|max:255',
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
            'inventory_id.required' => 'Inventory record is required.',
            'inventory_id.exists' => 'The selected inventory record does not exist.',
            'quantity.required' => 'Quantity is required.',
            'quantity.integer' => 'Quantity must be an integer.',
            'adjustment_type.required' => 'Adjustment type is required.',
            'adjustment_type.in' => 'Adjustment type must be one of: set, add, subtract.',
            'unit_cost.numeric' => 'Unit cost must be a valid number.',
            'unit_cost.min' => 'Unit cost cannot be negative.',
            'reason.required' => 'Reason for adjustment is required.',
            'reason.max' => 'Reason cannot exceed 255 characters.',
            'batch_number.max' => 'Batch number cannot exceed 255 characters.',
        ];
    }
}
