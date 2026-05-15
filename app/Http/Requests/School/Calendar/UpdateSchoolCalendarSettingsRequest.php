<?php

namespace App\Http\Requests\School\Calendar;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolCalendarSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-school-calendar');
    }

    public function rules(): array
    {
        return [
            'week_start_day' => ['required', 'integer', 'min:0', 'max:6'],
            'weekly_off_days' => ['required', 'array', 'min:1'],
            'weekly_off_days.*' => ['required', 'integer', 'distinct', 'min:0', 'max:6'],
        ];
    }
}

