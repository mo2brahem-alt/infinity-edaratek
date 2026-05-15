<?php

namespace App\Http\Requests\School;

use App\Rules\SaudiMobile;
use App\Support\SchoolPermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class StoreSchoolUserWithRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-school-users');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'min:3'],
            'email' => ['required', 'string', 'email:filter', 'max:255', 'unique:users,email'],
            'mobile' => ['required', 'string', 'max:20', new SaudiMobile, 'unique:users,mobile'],
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')->where(function ($query) {
                    $schoolId = (int) ($this->user()?->school_id ?? 0);

                    $query->where(function ($scopeQuery) use ($schoolId): void {
                        $scopeQuery->whereNull('school_id');

                        if ($schoolId > 0) {
                            $scopeQuery->orWhere('school_id', $schoolId);
                        }
                    });
                }),
            ],
            'department_role_id' => [
                'required',
                Rule::exists('department_roles', 'id')->where(fn ($query) => $query
                    ->where('department_id', (int) $this->input('department_id'))
                    ->where('is_active', true)),
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role_names' => ['required', 'array', 'min:1'],
            'role_names.*' => [
                'required',
                'string',
                'max:255',
            ],
            'permission_names' => ['nullable', 'array'],
            'permission_names.*' => ['required', 'string', 'max:255', Rule::in(SchoolPermissionCatalog::permissionNames())],
            'school_permission_group_ids' => ['nullable', 'array'],
            'school_permission_group_ids.*' => ['required', 'integer', 'min:1'],
            'can_manage_student_structure' => ['nullable', 'boolean'],
            'can_manage_student_attendance' => ['nullable', 'boolean'],
            'can_manage_academic_planning' => ['nullable', 'boolean'],
            'can_manage_student_leaves' => ['nullable', 'boolean'],
            'can_manage_leave_types' => ['nullable', 'boolean'],
            'can_manage_school_calendar' => ['nullable', 'boolean'],
            'can_manage_school_holidays' => ['nullable', 'boolean'],
        ];
    }
}
