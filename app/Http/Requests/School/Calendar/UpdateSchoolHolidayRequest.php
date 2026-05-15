<?php

namespace App\Http\Requests\School\Calendar;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-school-holidays');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'days_count' => ['nullable', 'integer', 'min:1', 'max:365'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'return_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'confirm_impact' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $hasDaysCount = $this->filled('days_count');
            $hasEndDate = $this->filled('end_date');

            if ($hasDaysCount || $hasEndDate) {
                return;
            }

            $validator->errors()->add('days_count', 'Either days_count or end_date is required.');
        });
    }
}
