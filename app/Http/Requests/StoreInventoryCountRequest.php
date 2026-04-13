<?php

namespace App\Http\Requests;

use Illuminate\Validation\Validator;

/**
 * Validation rules for creating an inventory count.
 */
class StoreInventoryCountRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return $user->hasPermission('inventory.counts.manage');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (empty($this->input('warehouse_id')) && empty($this->input('store_id'))) {
                $validator->errors()->add(
                    'warehouse_id',
                    'Either warehouse_id or store_id is required.'
                );
            }
        });
    }
}
