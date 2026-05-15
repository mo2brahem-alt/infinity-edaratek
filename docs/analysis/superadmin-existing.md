# Super Admin Existing Analysis

## Scope
This document maps what is already implemented for the Super Admin area and what the current UI depends on. It is the compatibility baseline before adding the supervision/handshake/tickets modules.

## Project Snapshot
- Stack: Laravel 12 + Inertia + Vue 3.
- Entry frontend app: `resources/js/app.js` (Vue page resolver for `Pages/**/*.vue`).
- Auth: Laravel Breeze-style auth with custom redirection logic.
- RBAC: Spatie Permission is installed and used, plus legacy `users.role` field still exists.

## Existing Super Admin Routes (must remain stable)
Under middleware `auth` + `role:super_admin`, prefix `/admin`:
- `GET /admin/dashboard` -> `admin.dashboard`
- `GET /admin/schools` -> `admin.schools.index`
- `POST /admin/directorates` -> `admin.directorates.store`
- `DELETE /admin/directorates/{id}` -> `admin.directorates.delete`
- `POST /admin/schools` -> `admin.schools.store`
- `PUT /admin/schools/{id}` -> `admin.schools.update`
- `DELETE /admin/schools/{id}` -> `admin.schools.delete`
- `GET /admin/settings` -> `admin.settings.index`
- `POST /admin/settings` -> `admin.settings.update`
- `POST /admin/components` -> `admin.components.store`
- `PUT /admin/components/{id}` -> `admin.components.update`
- `DELETE /admin/components/{id}` -> `admin.components.destroy`
- `POST /admin/pages` -> `admin.pages.store`
- `PUT /admin/pages/{id}` -> `admin.pages.update`
- `DELETE /admin/pages/{id}` -> `admin.pages.destroy`
- `GET /admin/media` -> `admin.media.index`
- `POST /admin/media` -> `admin.media.store`
- `DELETE /admin/media/{id}` -> `admin.media.destroy`
- `POST /admin/header/menu` -> `admin.header.menu.store`
- `DELETE /admin/header/menu/{id}` -> `admin.header.menu.delete`
- `POST /admin/header/item` -> `admin.header.item.store`
- `DELETE /admin/header/item/{id}` -> `admin.header.item.delete`
- `GET /admin/footer` -> `admin.footer.index`
- `POST /admin/footer/column` -> `admin.footer.column.store`
- `DELETE /admin/footer/column/{id}` -> `admin.footer.column.delete`
- `POST /admin/footer/item` -> `admin.footer.item.store`
- `DELETE /admin/footer/item/{id}` -> `admin.footer.item.delete`
- Resource routes (without create/show/edit) for:
  - `/admin/users` (`users.*` names)
  - `/admin/roles` (`roles.*` names)
  - `/admin/departments` (`departments.*` names)

## Existing Super Admin UI Pages
- `resources/js/Pages/Admin/Dashboard.vue`
- `resources/js/Pages/Admin/Schools/Index.vue`
- `resources/js/Pages/Admin/Settings/Index.vue`
- `resources/js/Pages/Admin/Users/Index.vue`
- `resources/js/Pages/Admin/Roles/Index.vue`
- `resources/js/Pages/Admin/Departments/Index.vue`
- `resources/js/Pages/Admin/Footer/Index.vue`
- Shared shell/navigation: `resources/js/Layouts/AdminLayout.vue`

## Models/Entities already present
Core:
- `User`, `Department`, `School`, `EducationalDirectorate`
- `Setting`, `Media`
- `HeaderMenu`, `HeaderItem`
- `FooterColumn`, `FooterItem`
- `Page`, `PageComponent`

RBAC:
- Spatie tables for roles/permissions exist (`roles`, `permissions`, `model_has_roles`, ...).

## Existing DB Schema Relevant to Super Admin
- `users`: name, email, password, role, is_active, mobile, phone, profile_photo_path, department_id, ...
- `departments`
- `educational_directorates`: name, governorate
- `schools`: directorate_id, name, school_id(unique), phone, email, address, notes
- `settings`: key(unique), value, type, group
- `media`: file metadata
- `pages`, `page_components`
- `header_menus`, `header_items`, `footer_columns`, `footer_items`

Notable migration state:
- duplicate migration files for same button settings seed logic:
  - `2026_02_11_110931_add_button_settings_to_settings_table.php`
  - `2026_02_11_145836_add_button_settings_to_settings_table.php`

## Auth + Role/Permission behavior currently
- Login redirect checks legacy `users.role === 'super_admin'` for admin dashboard redirection.
- Route guard uses custom middleware alias `role` (`CheckRole`) and accepts either:
  - Spatie `hasRole($role)` OR
  - legacy `users.role` equality.
- Admin user CRUD assigns/syncs Spatie roles (`assignRole`, `syncRoles`).

## Existing Super Admin API contracts used by UI
### Users
- Create payload:
  - `name`, `email`, `mobile`, `password`, `password_confirmation`, `role_name`, `department_id`
- Update payload:
  - same fields, with optional password
- List response used by UI includes:
  - `users[]` with `roles[]` and `department`
  - `roles[]`, `departments[]`

### Roles
- Payload: `name`
- List: `roles[]` (excluding super_admin)

### Departments
- Payload: `name`
- List: `departments[]` with `users_count`

### Schools / Directorates
- Directorate create payload: `name`, `governorate`
- School create/update payload:
  - `directorate_id`, `name`, `phone`, `email`, `address`, `notes`
- Schools page list response:
  - `directorates[]` (each with nested `schools[]`)
  - `filters` (`governorate`, `directorate_id`)
  - `filterOptions` (`governorates`, `directorates`)

### Settings / Pages / Components / Header / Footer
- Settings update is dynamic key-value POST to `admin.settings.update`.
- Page payloads:
  - create: `title`, `content`
  - update: `title`, `slug`, `content`
- Page component payloads:
  - `name`, `shortcode`, `content` (JSON/text depending component type)
- Footer item payload: `footer_column_id`, `label`, `url`
- Header item payload: `header_menu_id`, `label`, `url`

### Media
- Upload payload: multipart `file`.
- List returns `Media::latest()->get()` including computed `url`.

## What the current Super Admin UI depends on
- Exact route names currently referenced in Vue via Ziggy (`route('...')`).
- Existing response prop shapes returned by Inertia for Admin pages.
- Existing school fields and validation expectations (phone format `05xxxxxxxx`).
- Existing dynamic settings key/value behavior in `SettingsController@update`.
- Admin layout and sidebar links (`admin.dashboard`, `admin.schools.index`, `admin.settings.index`, and users submenu routes).

## Sensitive Areas (Do Not Break)
1. Do not rename/remove existing admin routes or route names.
2. Do not change existing Inertia prop names expected by Admin Vue pages.
3. Do not remove legacy role fallback (`users.role`) until a safe adapter exists.
4. Do not alter existing table columns used by current dashboard; only additive migrations.
5. Keep `AdminLayout.vue` navigation behavior unchanged for current super admin pages.
6. Keep dynamic settings write behavior backward-compatible.

## Known Stability Risks to consider during extension
- Mixed role strategy (legacy `users.role` + Spatie roles) can cause drift.
- `DatabaseSeeder` currently does not call Role/SuperAdmin/Settings seeders.
- Duplicate settings migration files should be handled carefully to avoid duplicate logic side effects.
- Page slug creation/update can conflict after `Str::slug` normalization.

## Compatibility Rule for Next Phases
All new scenario features (handshake, tickets, subtasks, dashboards for other roles) must be additive and modular. Existing Super Admin dashboard contracts above are treated as immutable interfaces unless fixing a confirmed bug with compatibility fallback.
