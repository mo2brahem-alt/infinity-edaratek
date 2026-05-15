<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSystemRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-system-roles');
    }

    public function rules(): array
    {
        $guardName = (string) $this->input('guard_name', 'web');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9._-]+$/',
                Rule::unique('roles', 'name')->where(fn ($query) => $query->where('guard_name', $guardName)),
            ],
            'guard_name' => ['nullable', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'assignable_by_school_admin' => ['nullable', 'boolean'],
            'is_system' => ['nullable', 'boolean'],
            'permission_names' => ['nullable', 'array'],
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
        $this->merge([
            'guard_name' => (string) ($this->input('guard_name') ?: 'web'),
        ]);
    }
}

