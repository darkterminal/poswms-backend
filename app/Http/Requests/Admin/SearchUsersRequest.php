<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SearchUsersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only super admins can search all users
        return $this->user() && $this->user()->isSuperAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'role' => ['nullable', 'string', 'max:100'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'status' => ['nullable', 'in:active,inactive'],
            'is_super_admin' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort_by' => ['nullable', 'in:name,email,created_at'],
            // Case-insensitive validation for sort_order (OWASP A03 - Input Validation)
            'sort_order' => ['nullable', 'in:asc,desc,ASC,DESC,Asc,Desc'],
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
            'search.max' => 'Search query cannot exceed 255 characters.',
            'email.email' => 'Please provide a valid email address.',
            'per_page.min' => 'Items per page must be at least 1.',
            'per_page.max' => 'Items per page cannot exceed 100.',
            'sort_by.in' => 'Invalid sort field. Allowed: name, email, created_at.',
            'sort_order.in' => 'Invalid sort order. Allowed: asc, desc.',
        ];
    }
}
