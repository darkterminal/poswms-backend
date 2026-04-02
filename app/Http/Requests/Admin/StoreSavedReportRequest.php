<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreSavedReportRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->authorizeSoft('reports.saved.create', 'create saved report');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'string', Rule::in(['sales', 'inventory', 'customer', 'custom'])],
            'template_id' => ['nullable', 'exists:report_templates,id'],
            'filters' => ['required', 'array'],
            'data' => ['nullable', 'array'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];

        // Super admins can specify tenant_id
        if ($this->user() && $this->user()->is_super_admin) {
            $rules['tenant_id'] = ['nullable', 'exists:tenants,id'];
        }

        return $rules;
    }
}
