<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreScheduledReportRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->authorizeSoft('reports.schedules.create', 'create scheduled report');
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
            'template_id' => ['nullable', 'exists:report_templates,id'],
            'filters' => ['required', 'array'],
            'schedule_frequency' => ['required', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
            'schedule_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'schedule_time' => ['required', 'date_format:H:i'],
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*' => ['required', 'email'],
            'export_format' => ['required', 'string', Rule::in(['csv', 'pdf', 'xlsx'])],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $frequency = $this->input('schedule_frequency');
            $day = $this->input('schedule_day');

            if ($frequency === 'weekly' && ! $day) {
                $validator->errors()->add('schedule_day', 'Schedule day is required for weekly frequency.');
            }

            if ($frequency === 'monthly' && (! $day || $day < 1 || $day > 31)) {
                $validator->errors()->add('schedule_day', 'Schedule day (1-31) is required for monthly frequency.');
            }
        });
    }
}
