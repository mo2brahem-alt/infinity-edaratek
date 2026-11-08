<?php

namespace App\Support;

final class UserAuthorityFields
{
    /** Fields a self-service client can never set, even to null or an empty array. */
    public const FIELDS = [
        'role', 'role_name', 'roles', 'role_names', 'permissions', 'permission_names',
        'abilities', 'is_active', 'approval_status', 'approved_at', 'approved_by',
        'rejected_at', 'rejected_by', 'approval_notes', 'school_id', 'tenant_id',
        'department_id', 'department_role_id', 'school_staff_type',
        'school_permission_group_ids', 'phone_verified_at', 'email_verified_at',
        'can_manage_student_structure', 'can_manage_student_attendance',
        'can_manage_academic_planning', 'can_manage_student_leaves',
        'can_manage_leave_types', 'can_manage_school_calendar', 'can_manage_school_holidays',
    ];

    public static function reject(\Illuminate\Http\Request $request, array $allowed = []): void
    {
        $errors = [];
        foreach (array_diff(self::FIELDS, $allowed) as $field) {
            if (array_key_exists($field, $request->all())) {
                $errors[$field] = ['لا يمكن تعديل هذا الحقل من هذه العملية.'];
            }
        }
        if ($errors !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
    }
}
