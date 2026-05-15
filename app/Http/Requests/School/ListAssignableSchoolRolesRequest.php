<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class ListAssignableSchoolRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-school-users');
    }

    public function rules(): array
    {
        return [];
    }
}

