<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class SyncSchoolUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-school-users');
    }

    public function rules(): array
    {
        return [
            'role_names' => ['required', 'array', 'min:1'],
            'role_names.*' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }
}
