<?php

namespace App\Http\Requests\Store;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'settings' => ['nullable', 'array'],
            'active' => ['boolean'],
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
            'name.required' => 'Store name is required.',
            'name.max' => 'Store name may not be greater than 255 characters.',
            'code.required' => 'Store code is required.',
            'code.max' => 'Store code may not be greater than 100 characters.',
            'city.max' => 'City may not be greater than 255 characters.',
            'state.max' => 'State may not be greater than 255 characters.',
            'country.max' => 'Country may not be greater than 255 characters.',
            'postal_code.max' => 'Postal code may not be greater than 50 characters.',
            'phone.max' => 'Phone may not be greater than 50 characters.',
            'email.email' => 'Please provide a valid email address.',
            'email.max' => 'Email may not be greater than 255 characters.',
        ];
    }
}
