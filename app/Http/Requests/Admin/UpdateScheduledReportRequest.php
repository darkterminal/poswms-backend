<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateScheduledReportRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->authorizeSoft('reports.schedules.update', 'update scheduled report');
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
            'filters' => ['sometimes', 'required', 'array'],
            'schedule_frequency' => ['sometimes', 'required', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
            'schedule_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'schedule_time' => ['sometimes', 'required', 'date_format:H:i'],
            'recipients' => ['sometimes', 'required', 'array', 'min:1'],
            'recipients.*' => ['required', 'email'],
            'export_format' => ['sometimes', 'required', 'string', Rule::in(['csv', 'pdf', 'xlsx'])],
            'is_active' => ['boolean'],
        ];
    }
}
