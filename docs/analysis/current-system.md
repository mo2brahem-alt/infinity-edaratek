# Current System Analysis

## 1) Stack And Runtime
- Backend: Laravel 12 (PHP 8.2+), Eloquent, Inertia Laravel.
- Frontend: Vue 3 + Inertia Vue 3 + Vite.
- Auth: Laravel Breeze style auth controllers with custom role-based redirect service.
- RBAC: Spatie `laravel-permission` + legacy fallback column `users.role`.

## 2) Current Core Data Model

### Existing core tables (already in use)
- `users`
- `departments`
- `educational_directorates` (used as Regions)
- `schools`
- `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`
- CMS/settings tables: `settings`, `pages`, `page_components`, `media`, `header_menus`, `header_items`, `footer_columns`, `footer_items`

### Existing supervision/ticketing extensions
- `school_supervisor_assignments`
- `association_requests`
- `tickets`
- `subtasks`
- `ticket_messages`
- `attachments`
- `notifications`
- `audit_logs`
- `status_history`

### Existing key columns relevant to this scope
- `schools.status` (`SUSPENDED`/`ACTIVE`)
- `schools.supervisor_id` (nullable)
- `schools.manager_user_id` (nullable, unique)
- `users.school_id` (nullable)
- `association_requests.status` currently (`PENDING`, `APPROVED`, `REJECTED`)

## 3) Current RBAC And Guards
- Middleware alias: `role` -> `App\Http\Middleware\CheckRole`.
- Role check logic is backward compatible:
  - Spatie role check (`hasRole`)
  - OR legacy check (`users.role`).
- Known seeded roles: `super_admin`, `supervisor`, `school_manager`, `staff` (plus extra legacy roles).

## 4) Existing Super Admin Dashboard Contracts (Sensitive)

All of these are active dependencies and must remain unchanged.

### Super Admin route group
Under: middleware `auth` + `role:super_admin`, prefix `/admin`

- `GET /admin/dashboard` (`admin.dashboard`)
- `GET /admin/schools` (`admin.schools.index`)
- `POST /admin/directorates` (`admin.directorates.store`)
- `DELETE /admin/directorates/{id}` (`admin.directorates.delete`)
- `POST /admin/schools` (`admin.schools.store`)
- `PUT /admin/schools/{id}` (`admin.schools.update`)
- `DELETE /admin/schools/{id}` (`admin.schools.delete`)
- `resource /admin/users` (route names `users.*`)
- `resource /admin/roles` (route names `roles.*`)
- `resource /admin/departments` (route names `departments.*`)
- Existing settings/media/header/footer/pages/components routes under `/admin/*`
- Existing extra route:
  - `GET /admin/supervisor-assignments`
  - `POST /admin/supervisor-assignments`
  - `DELETE /admin/supervisor-assignments/{supervisorAssignment}`

### Super Admin UI dependencies
- `resources/js/Layouts/AdminLayout.vue`
- `resources/js/Pages/Admin/*` existing pages
- Inertia props shape expected by current pages:
  - schools page: `directorates`, `filters`, `filterOptions`
  - users page: `users`, `roles`, `departments`
  - roles page: `roles`
  - departments page: `departments`

## 5) Existing Role-Specific Flows (Current State)
- `RegisterManagerController` (legacy path `/auth/register-manager`) currently binds manager to school directly.
- Existing `association_requests` flow is manager-centric and single-step approve -> activate school.
- Existing supervisor/manager/staff ticket endpoints are active and tested.

## 6) Current Gaps Against New Scenario

### Missing subscription domain
- No `plans` table.
- No `subscriptions` table.
- No public plans API.
- No registration routes that require plan selection for supervisor/manager.

### Missing onboarding domain
- No supervisor onboarding APIs for region + multi-school selection.
- No manager onboarding APIs for region + school selection.
- No onboarding UI wizards.

### Handshake gap (required two-step)
- Current association model does not implement:
  - `SUPERVISOR_REQUESTED` -> `MANAGER_APPROVED` -> `ACTIVE_ASSOCIATION`
- No supervisor-side final confirmation endpoint.
- Current flow activates school immediately after manager approval.

### Admin pricing management gap
- Admin has no real CRUD for plans/subscriptions yet (only placeholder link in sidebar).

## 7) Sensitive Non-Breaking Areas
- Do not rename/remove any existing Super Admin route names.
- Do not change existing Inertia prop keys consumed by current Admin pages.
- Keep existing `role` middleware behavior (Spatie + legacy fallback).
- Keep old registration and association endpoints available (compatibility), even when adding new flows.
- Apply additive DB migrations only.

## 8) Compatibility Approach For New Work
- Add new modules (`Plan`, `Subscription`, `SchoolSupervisionRequest`) and services.
- Keep existing Super Admin routes/pages untouched; only add new admin plans page/route.
- Keep legacy manager registration route in place, and add new role-based registration routes for subscription flow.
- Implement new two-step handshake through new endpoints and statuses without destructive changes.
