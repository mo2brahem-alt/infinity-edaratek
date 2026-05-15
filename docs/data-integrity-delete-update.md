# Data Integrity: Delete/Update Guard

## Overview
This project enforces delete/update integrity in backend services (not UI-only) using `App\Services\Integrity\IntegrityImpactService`.

Goals:
- Prevent unsafe hard deletes when operational/history data exists.
- Return clear impact metadata before sensitive mutations.
- Enforce tenant scope (`school_id`) and RBAC on all related endpoints.
- Keep behavior backward-compatible with minimal safe changes.

## Central Guard
- Service: `app/Services/Integrity/IntegrityImpactService.php`
- APIs:
  - `checkDeleteImpact(entityType, entityId, schoolId): ImpactResult`
  - `checkUpdateImpact(entityType, entityId, patch, schoolId): ImpactResult`

`ImpactResult` shape:
- `allowed`: bool
- `severity`: `INFO | WARNING | BLOCK`
- `message_code`: string
- `message`: string
- `affected`: list of `{ entity, count }`
- `suggested_action`: nullable string
- `requires_confirmation`: bool

## Policies by Entity
| Entity | Operation | Dependencies checked | Policy | Typical action |
|---|---|---|---|---|
| `school_stage` | delete | classrooms, students, attendance | `BLOCK` if any dependencies | Prevent delete; keep record |
| `school_classroom` | delete | students, attendance, schedules | `BLOCK` if any dependencies | Prevent delete; keep record |
| `school_student` | delete | attendance, leave requests | `BLOCK` if any dependencies | Prevent delete; keep record |
| `school_leave_type` | disable/delete-flow | leave requests, pending requests | `WARNING` for pending, info for history | Require explicit confirm for pending |
| `school_leave_type` | update | historical leave requests + semantic fields | `BLOCK` on semantic changes | Allow name-only safe update |
| `school_holiday` | disable | attendance in holiday range | `WARNING` if attendance exists | Require explicit confirm |
| `school_holiday` | update | attendance impacted by date range change | `WARNING` if attendance exists | Require explicit confirm |

## Backend Enforcement
- Student leave and calendar flows call guard before update/disable:
  - `app/Services/School/StudentLeaveService.php`
  - `app/Services/School/SchoolCalendarService.php`
- Student structure destructive endpoints are protected by guard:
  - `app/Http/Controllers/School/StudentStructureController.php`

When blocked:
- Request is rejected from backend (validation/forbidden), not only UI.
- Operation is audited with impact summary.

## Impact Preview Endpoints
Added preview routes:
- `GET /api/school/leave-types/{schoolLeaveType}/delete-impact`
- `GET /api/school/holidays/{schoolHoliday}/delete-impact`
- `GET /api/school/holidays/{schoolHoliday}/update-impact`

Used by UI to show warning/confirm before action.

## Audit Logging
Added/extended audit logs for update/delete and blocked delete:
- Stage/classroom/student update/delete + delete blocked.
- Leave type update/disable with impact summary.
- Holiday update/disable with impact summary.

## Multi-Tenancy & RBAC
- Guard always resolves by `school_id`; cross-tenant entity access is rejected.
- API actions remain protected by current permission middleware/request authorization.
- New preview endpoints reuse same tenant context and permission checks.

## Regression Coverage Added
- `tests/Feature/SchoolCalendarManagementApiTest.php`
  - leave type disable confirmation behavior
  - holiday update/disable confirmation behavior
  - preview endpoints tenant scoping
- `tests/Feature/StudentStructureAccessTest.php`
  - delete blocked when dependencies exist
  - delete tenant scoping
