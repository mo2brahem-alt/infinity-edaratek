# Org Structure Role Templates

## Summary

This change moves org-structure role creation to **Super Admin > User Roles** and stops creating structure roles from the departments page.

- Super Admin now manages **Org Structure Role Templates** centrally.
- Departments can only **select templates** and bind them.
- School structure pages continue assigning users to department roles without creating new roles.

## Data Model

### New table

- `org_structure_role_templates`
  - `id`
  - `name` (unique)
  - `code` (nullable, unique)
  - `is_active`
  - `created_by`, `updated_by`
  - timestamps

### Updated table

- `department_roles`
  - added `org_structure_role_template_id` (nullable FK to `org_structure_role_templates`)
  - added index `department_roles_department_template_active_idx` on:
    - `department_id`
    - `org_structure_role_template_id`
    - `is_active`

### Data migration

Existing `department_roles` records are mapped to templates by normalized role name and linked via `org_structure_role_template_id`.

## Backend Changes

- `Admin\RoleController`
  - keeps existing user-role CRUD.
  - adds org-structure template management:
    - `GET /admin/org-structure-roles`
    - `POST /admin/org-structure-roles`
    - `PUT|PATCH /admin/org-structure-roles/{id}`
    - `POST /admin/org-structure-roles/{id}/disable`
    - `storeOrgStructureRole`
    - `updateOrgStructureRole`
    - `disableOrgStructureRole`
  - writes audit logs for create/update/disable.

- `Admin\DepartmentController`
  - now requires `org_structure_roles[*].org_structure_role_template_id`.
  - blocks legacy role-name creation payloads.
  - creates/updates `department_roles` by template binding.
  - writes audit logs for create/update/delete.

- `Api\School\SchoolOrgStructureRoleController`
  - new read-only endpoint for school context:
    - `GET /api/school/org-structure-roles`

- Manager/school user assignment checks now only allow department roles that are:
  - active
  - and linked to an active template (or legacy role without template id).

## UI Changes

- `Admin/Roles/Index.vue`
  - keeps user-role section.
  - adds second section for org-structure role templates.

- `Admin/Departments/Index.vue`
  - removed free-text role creation.
  - uses template selection rows only.

## Compatibility Notes

- Existing departments and department roles continue to work after migration.
- Existing school staff assignments remain valid.
- Legacy roles without template linkage are still accepted in school assignment validation until fully migrated.
- If a template is disabled after being used:
  - it is blocked for **new assignments**.
  - existing users already linked to that department role can still be updated while keeping the same role.
