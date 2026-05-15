# Academic Planning: Subject Code + Teacher Assignment + Quick Add UX

## Scope
- `School/AcademicPlanning` page (terms, subjects, timetable versions, schedules).
- Subject code generation and subject-teacher assignment behavior.

## Discovery (Current Implementation)
- Subject entity/table:
  - Model: `App\Models\SchoolSubject`
  - Table: `school_subjects`
  - Core fields used in this flow: `id`, `school_id`, `name`, `code`, `is_active`
- Teacher-subject assignment entity/table:
  - Model: `App\Models\SchoolSubjectTeacherAssignment`
  - Table: `school_subject_teacher_assignments`
  - Core fields: `id`, `school_id`, `school_subject_id`, `teacher_user_id`, timestamps
- Existing relevant routes (School context):
  - `POST /school/academic-planning/subjects` (`school.academic_planning.subjects.store`)
  - `PUT /school/academic-planning/subjects/{schoolSubject}` (`school.academic_planning.subjects.update`)
  - `POST /school/academic-planning/subjects/{schoolSubject}/teachers` (`school.academic_planning.subjects.teachers.sync`)
  - `GET /school/academic-planning` (`school.academic_planning.index`) returns page props including `teachers` and `subjects`.
- School isolation and RBAC entrypoint:
  - `academic_planning_access` middleware sets/validates school context before controller actions.
- Timetable dependency:
  - Schedule validation enforces teacher-subject assignment via `ensureTeacherSubjectAssignment(...)`.

## What changed
- Subject code is now auto-generated on create when `code` is empty.
  - Format: `SUB-0001`, `SUB-0002`, ...
  - Sequence is isolated per `school_id`.
  - Manual code entry is still supported (backward-compatible).
- Subject create/update now supports direct teacher assignment in the same request:
  - Request field: `teacher_user_ids[]`
  - Validation enforces same-school + active + educational staff type.
  - Existing dedicated sync endpoint remains supported for backward compatibility.
- Existing subjects with empty code are backfilled by migration:
  - `database/migrations/2026_02_24_180000_backfill_school_subject_codes.php`
- Teacher assignment query/validation now relies on:
  - `school_id`
  - `is_active = true`
  - teacher eligibility supports both:
    - `school_staff_type = EDUCATIONAL` (current model)
    - legacy `role = teacher` / `spatie role = teacher` (backward compatibility)
    - school structure role naming patterns (`department_role.name` / template / department name containing `teacher` or `معلم`) for legacy/misconfigured staff type data
  - This removes dependency on legacy/spatie `staff` role naming for teacher assignment.

## API notes
- `POST /school/academic-planning/subjects`
  - accepts optional `teacher_user_ids` array.
- `PUT /school/academic-planning/subjects/{schoolSubject}`
  - accepts optional `teacher_user_ids` array.
  - if omitted, existing subject-teacher links are preserved.

## UX improvements in `AcademicPlanning.vue`
- After successful create (year/term/version/subject/schedule):
  - form is reset for the next entry,
  - scroll context is preserved on the same section,
  - focus is returned to the primary input.
- Submissions use Inertia options to avoid disruptive reload behavior:
  - `preserveScroll: true`
  - `preserveState: true`

## Backward compatibility
- Existing routes and payloads are unchanged.
- `subject.code` remains optional in requests.
- Existing manual codes continue to work.
