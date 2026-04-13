<?php

namespace App\Http\Requests;

/**
 * Validation rules for listing inventory counts.
 */
class ListInventoryCountsRequest extends BaseFormRequest
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

        return $user->hasPermission('inventory.counts.manage')
            || $user->hasPermission('inventory.view');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'status' => ['nullable', 'string', 'in:draft,in_progress,completed,approved,cancelled'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'string', 'in:name,created_at,started_at,completed_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}
