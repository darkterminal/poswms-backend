<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListTenantsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,suspended,archived'],
            'plan' => ['nullable', 'string', 'in:starter,professional,enterprise'],
            'trial_expiring' => ['nullable', 'string', 'in:24hours,7days,30days,expired'],
            'subscription_status' => ['nullable', 'string', 'in:active,expiring,expired,none'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            // sort_by and sort_direction are handled by ValidatesSorting trait
            // Allowed sort fields: name, slug, company_name, email, status, subscription_plan, trial_ends_at, subscription_ends_at, created_at, updated_at
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
            'plan.in' => 'The selected plan is invalid. Must be one of: starter, professional, enterprise',
            'trial_expiring.in' => 'The selected trial expiring filter is invalid. Must be one of: 24hours, 7days, 30days, expired',
            'subscription_status.in' => 'The selected subscription status is invalid. Must be one of: active, expiring, expired, none',
            'date_from.date' => 'The date from must be a valid date in YYYY-MM-DD format.',
            'date_to.date' => 'The date to must be a valid date in YYYY-MM-DD format.',
        ];
    }
}
