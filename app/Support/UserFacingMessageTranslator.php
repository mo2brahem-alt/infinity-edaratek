<?php

namespace App\Support;

class UserFacingMessageTranslator
{
    /**
     * @var array<string, string>
     */
    private const EXACT_MESSAGES = [
        'The given data was invalid.' => 'messages.invalid_input',
        'This action is unauthorized.' => 'messages.unauthorized_action',
        'You are not authorized to access this page.' => 'messages.page_access_denied',
        'School context is required.' => 'messages.school_context_required',
        'School context is invalid.' => 'messages.school_context_invalid',
        'Only the assigned manager can access the student structure.' => 'messages.student_structure_manager_only',
        'You do not have permission to manage the student structure.' => 'messages.student_structure_permission_denied',
        'Only the assigned manager can access student leaves.' => 'messages.student_leaves_manager_only',
        'You do not have permission to manage student leaves.' => 'messages.student_leaves_permission_denied',
        'Only the assigned manager can access student attendance.' => 'messages.student_attendance_manager_only',
        'You do not have permission to manage student attendance.' => 'messages.student_attendance_permission_denied',
        'Only the assigned manager can access academic planning.' => 'messages.academic_planning_manager_only',
        'You do not have permission to manage academic planning.' => 'messages.academic_planning_permission_denied',
        'Only the assigned manager can access school reports.' => 'messages.school_reports_manager_only',
        'You do not have permission to access school reports.' => 'messages.school_reports_permission_denied',
        'You do not have permission to export school reports.' => 'messages.school_reports_export_denied',
        'Manager access is required.' => 'messages.manager_access_required',
        'Manager account must be linked to a school.' => 'messages.manager_school_required',
        'Manager account must be linked to a school first.' => 'messages.manager_school_required',
        'You are not the assigned manager for this school.' => 'messages.assigned_manager_required',
        'Departments and structure roles are managed by super admin only.' => 'messages.departments_super_admin_only',
        'No supervisor assignment found for selected school.' => 'messages.supervisor_assignment_missing',
        'Selected school already has a manager account.' => 'messages.school_has_manager',
        'Selected school is already linked to another manager.' => 'messages.school_linked_to_another_manager',
        'Manager is already linked to another school.' => 'messages.manager_linked_to_another_school',
        'No supervisor is assigned for this school yet.' => 'messages.school_supervisor_missing',
        'A school with this name already exists.' => 'messages.school_name_taken',
        'You are not allowed to access this leave request.' => 'messages.leave_request_access_denied',
        'You are not allowed to access this leave type.' => 'messages.leave_type_access_denied',
        'You are not allowed to access this holiday.' => 'messages.holiday_access_denied',
        'You do not have permission to view leave types.' => 'messages.leave_types_view_denied',
        'You do not have permission to view leave type impact.' => 'messages.leave_type_impact_denied',
        'You do not have permission to view holiday impact.' => 'messages.holiday_impact_denied',
        'Attachment file is missing.' => 'messages.attachment_missing',
        'You are not allowed to manage this user.' => 'messages.user_manage_denied',
        'Only school staff users can be managed from this page.' => 'messages.staff_only_management',
        'You cannot modify your own manager account roles from this endpoint.' => 'messages.manager_self_role_change_denied',
        'System accounts cannot be managed from this endpoint.' => 'messages.system_accounts_management_denied',
        'You are not allowed to manage this permission group.' => 'messages.school_permission_group_manage_denied',
        'Selected role cannot be assigned by school manager.' => 'messages.school_role_assignment_denied',
        'At least one assignable role is required.' => 'messages.school_role_assignment_required',
        'You are not allowed to access this school stage.' => 'messages.school_stage_access_denied',
        'You are not allowed to access this school stage grade.' => 'messages.school_grade_access_denied',
        'You are not allowed to access this classroom.' => 'messages.classroom_access_denied',
        'You are not allowed to access this student.' => 'messages.student_access_denied',
    ];

    public static function translate(?string $message, ?int $statusCode = null): string
    {
        $message = trim((string) $message);

        if ($message === '') {
            return self::fallbackForStatus($statusCode);
        }

        if (preg_match('/[\p{Arabic}]/u', $message) === 1) {
            return $message;
        }

        if (isset(self::EXACT_MESSAGES[$message])) {
            return __(self::EXACT_MESSAGES[$message]);
        }

        if (preg_match('/^Too many login attempts\. Please try again in (\d+) seconds\.$/', $message, $matches) === 1) {
            return __('auth.throttle', ['seconds' => $matches[1]]);
        }

        return $message;
    }

    /**
     * @param  array<int, string>  $errors
     * @return array<int, string>
     */
    public static function translateValidationErrors(array $errors): array
    {
        return array_values(array_map(fn ($message) => self::translate((string) $message, 422), $errors));
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     * @return array<string, array<int, string>>
     */
    public static function translateValidationErrorBag(array $errors): array
    {
        $translated = [];

        foreach ($errors as $field => $messages) {
            $translated[$field] = self::translateValidationErrors($messages);
        }

        return $translated;
    }

    private static function fallbackForStatus(?int $statusCode): string
    {
        return match ($statusCode) {
            403 => __('messages.forbidden'),
            404 => __('messages.not_found'),
            422 => __('messages.invalid_input'),
            default => __('messages.unexpected_error'),
        };
    }
}
