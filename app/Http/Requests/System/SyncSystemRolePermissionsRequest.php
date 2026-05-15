<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class SyncSystemRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-system-roles');
    }

    public function rules(): array
    {
        /** @var Role|int|string|null $boundRole */
        $boundRole = $this->route('role');
        $guardName = $boundRole instanceof Role ? $boundRole->guard_name : (string) $this->input('guard_name', 'web');

        return [
            'permission_names' => ['required', 'array'],
            'permission_names.*' => [
                'required',
                'string',
                'max:255',
                Rule::exists('permissions', 'name')->where(fn ($query) => $query->where('guard_name', $guardName)),
            ],
        ];
    }
}

