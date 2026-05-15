<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UpdateSystemRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-system-roles');
    }

    public function rules(): array
    {
        /** @var Role|int|string|null $boundRole */
        $boundRole = $this->route('role');
        $roleId = $boundRole instanceof Role ? $boundRole->id : (int) $boundRole;
        $guardName = (string) $this->input('guard_name', $boundRole instanceof Role ? $boundRole->guard_name : 'web');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('roles', 'name')
                    ->ignore($roleId)
                    ->where(fn ($query) => $query->where('guard_name', $guardName)),
            ],
            'guard_name' => ['sometimes', 'required', 'string', 'max:255'],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'assignable_by_school_admin' => ['sometimes', 'boolean'],
            'is_system' => ['sometimes', 'boolean'],
            'permission_names' => ['sometimes', 'array'],
            'permission_names.*' => [
                'required_with:permission_names',
                'string',
                'max:255',
                Rule::exists('permissions', 'name')->where(fn ($query) => $query->where('guard_name', $guardName)),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        /** @var Role|int|string|null $boundRole */
        $boundRole = $this->route('role');
        $defaultGuard = $boundRole instanceof Role ? $boundRole->guard_name : 'web';

        $this->merge([
            'guard_name' => (string) ($this->input('guard_name') ?: $defaultGuard),
        ]);
    }
}

