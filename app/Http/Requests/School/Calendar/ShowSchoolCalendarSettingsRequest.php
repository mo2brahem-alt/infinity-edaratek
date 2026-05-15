<?php

namespace App\Http\Requests\School\Calendar;

use Illuminate\Foundation\Http\FormRequest;

class ShowSchoolCalendarSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-school-calendar');
    }

    public function rules(): array
    {
        return [];
    }
}

