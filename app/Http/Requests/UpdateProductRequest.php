<?php

namespace App\Http\Requests;

class UpdateProductRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Soft enforcement: check for products.update permission
        return $this->authorizeSoft('products.update', 'update product');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $productId = $this->route('productId');

        return [
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'sku' => 'sometimes|string|max:100|unique:products,sku,' . $productId,
            'barcode' => 'nullable|string|max:100|unique:products,barcode,' . $productId,
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string|max:50',
            'min_stock' => 'nullable|integer|min:0',
            'max_stock' => 'nullable|integer|min:0',
            'image' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'attributes' => 'nullable|array',
            'track_inventory' => 'nullable|boolean',
            'active' => 'nullable|boolean',
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
            'category_id.exists' => 'The selected category does not exist.',
            'name.max' => 'Product name cannot exceed 255 characters.',
            'sku.unique' => 'This SKU is already in use.',
            'sku.max' => 'SKU cannot exceed 100 characters.',
            'barcode.unique' => 'This barcode is already in use.',
            'price.numeric' => 'Price must be a valid number.',
            'price.min' => 'Price cannot be negative.',
            'cost.numeric' => 'Cost must be a valid number.',
            'cost.min' => 'Cost cannot be negative.',
            'tax_rate.numeric' => 'Tax rate must be a valid number.',
            'tax_rate.min' => 'Tax rate cannot be negative.',
            'unit.max' => 'Unit cannot exceed 50 characters.',
            'min_stock.integer' => 'Minimum stock must be an integer.',
            'min_stock.min' => 'Minimum stock cannot be negative.',
            'max_stock.integer' => 'Maximum stock must be an integer.',
            'max_stock.min' => 'Maximum stock cannot be negative.',
        ];
    }
}
