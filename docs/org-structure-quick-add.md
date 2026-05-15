# Org Structure Quick Add

## Scope
- Applies to Super Admin org-structure role templates screen (`Admin/Roles/Index`).
- Keeps all existing routes, permissions, and backward-compatible behavior.

## Backend
- `POST /admin/org-structure-roles`:
  - `code` remains optional.
  - when omitted, code is generated server-side from `name`.
- `PUT/PATCH /admin/org-structure-roles/{id}`:
  - preserves existing code when present.
  - generates code for legacy records that have no code.
- uniqueness and concurrency safety:
  - generation and save run inside a DB transaction.
  - uniqueness is re-checked under lock before write.
- audit logging remains enabled for create/update/disable.

## Frontend UX
- Org-template create/update/disable now use JSON async requests (no full page reload).
- On successful create:
  - list updates immediately.
  - form resets for next entry.
  - focus returns to template name field.
  - success message shows generated code.
- On error:
  - fields remain unchanged.
  - validation errors are shown inline.

## Notes
- Super Admin RBAC remains unchanged.
- No school data scope is modified in this flow.
