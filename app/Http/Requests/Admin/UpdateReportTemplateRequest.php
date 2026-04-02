<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateReportTemplateRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->authorizeSoft('reports.templates.update', 'update report template');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['sometimes', 'required', 'string', Rule::in(['sales', 'inventory', 'customer', 'custom'])],
            'config' => ['sometimes', 'required', 'array'],
            'config.filters' => ['nullable', 'array'],
            'config.columns' => ['nullable', 'array'],
            'config.grouping' => ['nullable', 'string', 'max:100'],
            'config.sorting' => ['nullable', 'array'],
            'is_global' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
