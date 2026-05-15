# Academic Planning: Stage/Classroom Management Relocation

## Scope
- Moved stage and classroom management UI from `School/StudentStructure` to `School/AcademicPlanning`.
- Kept all stage/classroom CRUD endpoints unchanged for backward compatibility.

## UI Changes
- Added a new section in `School/AcademicPlanning`:
  - `0) ??????? ???????`
  - `A) ????? ??????? ????????`
  - `B) ????? ?????? ????????`
- Removed stage/classroom panels from `School/StudentStructure`.
- Added a clear redirect link in `School/StudentStructure` to:
  - `school.academic_planning.index#stages-classrooms`

## Backend/Data Contract
- `GET /school/academic-planning` now returns extra prop:
  - `structureStages`
- Existing prop `stages` is unchanged (still used by timetable forms).
- `structureStages` is only populated for users with `can_manage_student_structure`; others receive an empty array.
- Existing stage/classroom CRUD routes are unchanged:
  - `/school/student-structure/stages/*`
  - `/school/student-structure/classrooms/*`

## Security and Isolation
- Stage/classroom mutations remain protected by `student_structure_access` middleware.
- All stage/classroom operations remain school-scoped (`school_id`) and tenant-isolated.
- Existing audit log behavior remains unchanged because mutations still go through `StudentStructureController`.

## Regression Notes
- Student management remains on `School/StudentStructure`.
- Attendance/leaves/timetable flows that depend on stages/classrooms remain compatible.
