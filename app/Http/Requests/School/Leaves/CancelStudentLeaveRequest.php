<?php

namespace App\Http\Requests\School\Leaves;

use Illuminate\Foundation\Http\FormRequest;

class CancelStudentLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-student-leaves');
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

