# QA + Security Audit Report

Date: 2026-02-24  
Scope: Student Leaves, School Calendar, Student Attendance, RBAC, School Isolation (Multi-Tenancy)

## Executive Summary
- Reviewed tenant scoping, RBAC enforcement, attachment handling, and report/export paths.
- Core backend scoping is already strong in `StudentLeaveService`, `SchoolCalendarService`, and attendance flows.
- Found and fixed security hardening gaps in student leave attachments:
1. Added strict MIME validation for leave attachments by default (configurable).
2. Added school-scoped storage path for leave attachments to strengthen tenant isolation.
3. Added regression tests for cross-tenant attachment IDOR and tenant-scoped leave-type actions.

## Findings (Risk Rated)

### 1) Leave attachment validation was optional by global flag
- Severity: High
- Type: Insecure File Upload
- Location: `app/Http/Requests/School/Leaves/UploadStudentLeaveAttachmentRequest.php`
- Issue: If global strict upload validation flag was disabled, leave attachments accepted any MIME.
- Fix:
  - Added dedicated flag `features.uploads.strict_student_leave_attachment_validation` (default `true`).
  - Effective check now: strict leave flag OR global strict flag.

### 2) Leave attachments were stored in a shared public path
- Severity: High
- Type: Tenant isolation hardening / Data exposure risk
- Location: `app/Services/School/StudentLeaveService.php`
- Issue: Attachments were stored under `student-leaves/attachments` for all schools.
- Fix:
  - Store path is now tenant-scoped:
    - `schools/{school_id}/student-leaves/{leave_request_id}/attachments`
  - Original filename is normalized with `basename(...)` before persistence.

### 3) Missing regression coverage for attachment IDOR and leave-type tenant actions
- Severity: Medium
- Type: Test coverage gap
- Fix:
  - Added cross-tenant upload denial test.
  - Added school-scoped storage assertion test.
  - Added strict attachment MIME default behavior test.
  - Added leave-type tenant-scope update/disable regression test.

## Permission Matrix (Implemented / Verified)

| Endpoint Group | Required Permission | Enforcement Layer |
|---|---|---|
| `/api/school/leaves*` | `manage-student-leaves` | FormRequest + `student_leave_access` middleware |
| `/api/school/leave-types*` | `manage-leave-types` | FormRequest + service school ownership checks |
| `/api/school/school-calendar-settings*` | `manage-school-calendar` | FormRequest + service school ownership checks |
| `/api/school/holidays*` | `manage-school-holidays` | FormRequest + service school ownership checks |
| `/school/student-attendance*` | attendance access | `student_attendance_access` middleware + scoped validation |

## Isolation Coverage Added
- `tests/Feature/StudentLeaveManagementTest.php`
  - `test_leave_attachment_upload_is_tenant_scoped_and_path_is_school_scoped`
  - `test_leave_attachment_upload_rejects_disallowed_mime_types_by_default`
- `tests/Feature/SchoolCalendarManagementApiTest.php`
  - `test_leave_type_actions_are_tenant_scoped`

## Files Changed
- `config/features.php`
- `app/Http/Requests/School/Leaves/UploadStudentLeaveAttachmentRequest.php`
- `app/Services/School/StudentLeaveService.php`
- `tests/Feature/StudentLeaveManagementTest.php`
- `tests/Feature/SchoolCalendarManagementApiTest.php`
- `docs/qa-security-audit.md`

## Operational Verification (Run in cloud Ubuntu environment)
Use these commands on the server environment where PHP is available:

```bash
php artisan optimize:clear
php vendor/bin/phpunit -c phpunit.mysql.xml --filter StudentLeaveManagementTest
php vendor/bin/phpunit -c phpunit.mysql.xml --filter SchoolCalendarManagementApiTest
```

Optional full regression:

```bash
php vendor/bin/phpunit -c phpunit.mysql.xml
```

## Residual Risk / Follow-up
- Existing old attachments remain in previous path; new uploads are isolated.  
  Recommendation: optional migration/maintenance task to relocate historical files per school path.
- If business needs broader file types for leave attachments, configure:
  - `FEATURE_STRICT_STUDENT_LEAVE_ATTACHMENT_VALIDATION=false`
  with explicit risk acceptance.
