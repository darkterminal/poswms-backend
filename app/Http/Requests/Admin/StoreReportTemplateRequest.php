<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreReportTemplateRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->authorizeSoft('reports.templates.create', 'create report template');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'string', Rule::in(['sales', 'inventory', 'customer', 'custom'])],
            'config' => ['required', 'array'],
            'config.filters' => ['nullable', 'array'],
            'config.columns' => ['nullable', 'array'],
            'config.grouping' => ['nullable', 'string', 'max:100'],
            'config.sorting' => ['nullable', 'array'],
            'is_global' => ['boolean'],
            'is_active' => ['boolean'],
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
            'name.required' => 'Template name is required',
            'type.required' => 'Report type is required',
            'type.in' => 'Report type must be one of: sales, inventory, customer, custom',
            'config.required' => 'Template configuration is required',
            'config.array' => 'Configuration must be a valid JSON object',
        ];
    }
}
