<?php

namespace App\Http\Requests\School\Leaves;

use Illuminate\Foundation\Http\FormRequest;

class DisableSchoolLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-leave-types');
    }

    public function rules(): array
    {
        return [
            'confirm_impact' => ['nullable', 'boolean'],
        ];
    }
}
