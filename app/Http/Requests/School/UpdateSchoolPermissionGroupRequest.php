<?php

namespace App\Http\Requests\School;

use App\Models\SchoolPermissionGroup;
use App\Support\SchoolPermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchoolPermissionGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-school-users');
    }

    public function rules(): array
    {
        /** @var SchoolPermissionGroup|int|string|null $group */
        $group = $this->route('schoolPermissionGroup');
        $groupId = $group instanceof SchoolPermissionGroup ? $group->id : (int) $group;
        $schoolId = (int) ($this->user()?->school_id ?? 0);

        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                Rule::unique('school_permission_groups', 'name')
                    ->ignore($groupId)
                    ->where(fn ($query) => $query->where('school_id', $schoolId)),
            ],
            'group_type' => ['required', 'string', Rule::in(SchoolPermissionCatalog::groupTypes())],
            'permission_names' => ['required', 'array', 'min:1'],
            'permission_names.*' => ['required', 'string', 'max:255', Rule::in(SchoolPermissionCatalog::permissionNames())],
        ];
    }
}
