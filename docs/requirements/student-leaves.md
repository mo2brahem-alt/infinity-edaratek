# Student Leaves Module

## Scope
- Adds school-scoped student leave management.
- Supports:
  - `PRE_APPROVED` leave: applied during attendance period.
  - `RETROACTIVE` leave: converts existing `ABSENT` attendance to `LEAVE` after approval.

## School Isolation
- All leave data is scoped by `school_id`.
- Access is protected by `student_leave_access` middleware.
- Cross-school access is blocked at middleware + query + model binding checks.

## Permission Model
- New staff capability:
  - `users.can_manage_student_leaves` (nullable boolean)
  - `users.can_manage_leave_types` (nullable boolean)
  - `users.can_manage_school_calendar` (nullable boolean)
  - `users.can_manage_school_holidays` (nullable boolean)
- `department_roles.can_manage_student_leaves` (boolean fallback)
  - `department_roles.can_manage_leave_types` (boolean fallback)
  - `department_roles.can_manage_school_calendar` (boolean fallback)
  - `department_roles.can_manage_school_holidays` (boolean fallback)
- Effective permission method: `User::canManageStudentLeaves()`.
- Managers keep full access inside their school.

## Feature Flags
- `FEATURE_STUDENT_LEAVES` (default `true`)
- `FEATURE_STUDENT_LEAVES_RETROACTIVE_MAX_DAYS` (default `30`)
- `FEATURE_STUDENT_LEAVES_RETROACTIVE_GRACE_DAYS` (default `30`)
- `FEATURE_STUDENT_LEAVES_ATTACHMENT_GRACE_DAYS` (default `7`)
- `FEATURE_STUDENT_LEAVES_ENFORCE_ATTENDANCE` (default `true`)

## New Routes
- UI:
  - `GET /school/student-leaves` (`school.student_leaves.index`)
- API:
  - `GET /api/school/leave-types`
  - `POST /api/school/leave-types`
  - `PATCH /api/school/leave-types/{schoolLeaveType}`
  - `POST /api/school/leave-types/{schoolLeaveType}/disable`
  - `GET /api/school/school-calendar-settings`
  - `PUT /api/school/school-calendar-settings`
  - `GET /api/school/holidays`
  - `POST /api/school/holidays`
  - `PATCH /api/school/holidays/{schoolHoliday}`
  - `POST /api/school/holidays/{schoolHoliday}/disable`
  - `GET /api/school/leaves`
  - `POST /api/school/leaves`
  - `PATCH /api/school/leaves/{schoolStudentLeaveRequest}`
  - `POST /api/school/leaves/{schoolStudentLeaveRequest}/approve`
  - `POST /api/school/leaves/{schoolStudentLeaveRequest}/reject`
  - `POST /api/school/leaves/{schoolStudentLeaveRequest}/cancel`
  - `POST /api/school/leaves/{schoolStudentLeaveRequest}/attachments`

## Attendance Report Export
- New web route:
  - `GET /school/student-attendance/report/export` (`school.student_attendance.report.export`)
- Exports CSV for the selected school classroom and date range.
- Export is tenant-scoped by `school_id` and validates classroom ownership.
- Supports optional report filters:
  - `report_day_type` (`SCHOOL_DAY|HOLIDAY|WEEKLY_OFF`)
  - `report_holiday_name` (partial holiday name match)
  - `report_leave_type_id` (school-scoped leave type)
- CSV columns:
  - `student_name`
  - `student_code`
  - `leave_days`
  - `unexcused_absence_days`
  - `present_days`
  - `excused_days`
  - `recorded_days`

## Attendance Integration
- New attendance status: `LEAVE`.
- During attendance save:
  - If a student has approved active leave and input status is `ABSENT`, status is auto-converted to `LEAVE`.
  - Conversion stores `school_student_leave_request_id`.
- Retroactive approval converts matching attendance records:
  - `ABSENT -> LEAVE`
  - Records audit and status history.
- Attendance page report now separates:
  - `leave_days`
  - `unexcused_absence_days` (from `ABSENT` only)
  so approved leave does not inflate unexcused absence totals.
- Report filters now support:
  - `day_type` filtering
  - `holiday_name` filtering
  - `leave_type` filtering
- Response includes `day_type_summary`:
  - `school_days`
  - `holiday_days`
  - `weekly_off_days`
- Attendance report aggregation is optimized at DB level using grouped counts by:
  - `school_student_id`
  - `status`
  to reduce memory usage and improve performance on large classrooms.

## Audit and Traceability
- Leave lifecycle actions are written to `audit_logs`.
- Status transitions are written to `status_history`:
  - Leave request transitions.
  - Attendance conversion transitions.

## Migrations Added
- `2026_02_22_130000_add_student_leave_permission_to_users_and_department_roles.php`
- `2026_02_22_130100_create_school_leave_types_table.php`
- `2026_02_22_130200_create_school_student_leave_requests_table.php`
- `2026_02_22_130300_create_school_student_leave_attachments_table.php`
- `2026_02_22_130400_add_leave_reference_to_school_student_attendances_table.php`
- `2026_02_22_210000_add_performance_indexes_for_attendance_reports.php`
- `2026_02_23_090000_add_management_and_code_fields_to_school_leave_types_table.php`
- `2026_02_23_090100_create_school_calendar_settings_table.php`
- `2026_02_23_090200_create_school_holidays_table.php`
- `2026_02_23_090300_add_leave_calendar_holiday_permissions_to_users_and_department_roles.php`
