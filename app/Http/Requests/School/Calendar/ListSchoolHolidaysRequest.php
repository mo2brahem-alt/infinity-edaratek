<?php

namespace App\Http\Requests\School\Calendar;

use Illuminate\Foundation\Http\FormRequest;

class ListSchoolHolidaysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-school-holidays');
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

