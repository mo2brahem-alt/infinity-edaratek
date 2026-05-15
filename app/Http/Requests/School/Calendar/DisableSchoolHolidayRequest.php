<?php

namespace App\Http\Requests\School\Calendar;

use Illuminate\Foundation\Http\FormRequest;

class DisableSchoolHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-school-holidays');
    }

    public function rules(): array
    {
        return [
            'confirm_impact' => ['nullable', 'boolean'],
        ];
    }
}
