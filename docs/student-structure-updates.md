# Student Structure Updates

## Scope
- Stage form now supports:
  - `school_day_start_time`
  - `school_day_end_time`
- Stage/Classroom/Student codes are auto-generated server-side when code is not provided.
- Create/Update form submits preserve page state and scroll; successful submits reset form fields for next entry.
- Sequential data-entry UX:
  - stage/classroom/student forms reset after success
  - focus returns to the first primary input
  - classroom/student keep selected stage/classroom where possible to speed repeated entry

## Validation Rules
- Stage time rules:
  - `school_day_start_time` and `school_day_end_time` must be `H:i`.
  - `school_day_start_time < school_day_end_time`.
- Code uniqueness is enforced within each school context.

## Auto Code Patterns
- Stage: `STG-001`
- Classroom: `CLS-001`
- Student: `STU-001`

Codes are generated inside database transactions with school scoping to avoid cross-tenant collisions.
Generation locks the school row and re-checks scoped uniqueness inside the transaction.

## Audit Log
New create events:
- `student_structure.stage.created`
- `student_structure.classroom.created`
- `student_structure.student.created`

Existing update/delete events remain unchanged.
