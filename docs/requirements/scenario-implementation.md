# Scenario Implementation Plan (Backward Compatible)

## 1) Gap Analysis vs Requested Scenario

### Already available
- Super Admin dashboard routes/pages for schools, users, roles, departments, settings, media.
- School and educational directorate entities exist.
- Role middleware exists (`role:*`) with Spatie support.
- User/role management is already in Super Admin UI.

### Missing (required by scenario)
- Explicit role lifecycle for: `super_admin`, `supervisor`, `school_manager`, `staff` (consistent behavior across auth/redirection/authorization).
- School activation workflow (`SUSPENDED` -> `ACTIVE`) and manager-school association handshake.
- Supervisor assignment policy for schools/regions in data model.
- Ticketing domain (tickets, subtasks, replies, attachments, status history).
- Notifications + lightweight audit logs.
- Dashboards for Supervisor, School Manager, Staff.
- APIs for handshake and ticket flows.
- Feature tests for handshake and ticket authorization chains.

### Compatibility constraints adopted
- Keep existing Super Admin routes and route names unchanged.
- Keep existing Inertia props for current Super Admin pages unchanged.
- Additive migrations only (no destructive edits).
- Keep legacy `users.role` behavior as fallback adapter while expanding role logic.

## 2) ERD (Additive)

### Existing tables extended
- `schools`
  - `status` string default `SUSPENDED` index
  - `supervisor_id` nullable FK -> `users.id`
  - `manager_user_id` nullable FK -> `users.id` (unique)
- `users`
  - `school_id` nullable FK -> `schools.id` index

### New tables
- `school_supervisor_assignments`
  - `id`, `supervisor_id` FK users
  - `directorate_id` nullable FK educational_directorates
  - `school_id` nullable FK schools
  - `is_active` bool default true
  - timestamps
  - indexes on (`supervisor_id`), (`directorate_id`), (`school_id`)

- `association_requests`
  - `id`, `school_id`, `manager_user_id`, `supervisor_user_id`
  - `title`, `status` (`PENDING|APPROVED|REJECTED`), `notes`
  - `approved_at`, `rejected_at`, `responded_by`
  - timestamps

- `tickets`
  - `id`, `school_id`, `created_by`, `assigned_to`
  - `title`, `description`, `priority` nullable, `due_date` nullable
  - `status` (`OPEN|IN_PROGRESS|WAITING_MANAGER_REVIEW|WAITING_SUPERVISOR_REVIEW|CLOSED`)
  - `manager_final_report` nullable
  - `closed_at` nullable
  - timestamps

- `subtasks`
  - `id`, `ticket_id`, `school_id`, `created_by`, `assigned_to`
  - `title`, `description` nullable, `due_date` nullable
  - `status` (`OPEN|IN_PROGRESS|SUBMITTED|APPROVED`)
  - timestamps

- `ticket_messages`
  - `id`, `ticket_id` nullable, `subtask_id` nullable
  - `user_id`, `message`, `message_type` default `reply`
  - timestamps

- `attachments`
  - `id`, `ticket_message_id` nullable
  - `uploaded_by`, `file_name`, `file_path`, `mime_type`, `file_size`
  - timestamps

- `notifications`
  - `id`, `user_id`, `type`, `title`, `body`, `data` json nullable
  - `read_at` nullable
  - timestamps

- `audit_logs`
  - `id`, `user_id` nullable
  - `action`, `entity_type`, `entity_id` nullable
  - `payload` json nullable, `ip_address` nullable, `user_agent` nullable
  - timestamps

- `status_history`
  - `id`, `entity_type`, `entity_id`, `from_status` nullable, `to_status`
  - `changed_by` nullable FK users
  - `meta` json nullable
  - timestamps

## 3) Endpoint Design (Additive)

### Registration / Handshake
- `GET /auth/register-manager`
- `POST /auth/register-manager`
- `GET /association-requests`
- `POST /association-requests/{id}/approve`
- `POST /association-requests/{id}/reject`

### Supervisor ticketing
- `GET /supervisor/tickets`
- `POST /supervisor/tickets`
- `GET /supervisor/tickets/{ticket}`
- `PUT /supervisor/tickets/{ticket}`
- `POST /supervisor/tickets/{ticket}/close`

### Manager ticketing/subtasks
- `GET /manager/tickets`
- `POST /manager/tickets/{ticket}/final-report`
- `POST /manager/subtasks`
- `PUT /manager/subtasks/{subtask}`
- `POST /manager/subtasks/{subtask}/approve`

### Staff subtask workflow
- `GET /staff/subtasks`
- `POST /staff/subtasks/{subtask}/reply`
- `POST /staff/subtasks/{subtask}/submit`

### Optional helper endpoints
- `GET /notifications`
- `POST /notifications/{id}/read`

## 4) Authorization Policies

- `SUPER_ADMIN`
  - Full access to existing admin + read/manage all new modules.

- `SUPERVISOR`
  - Can create/update/close tickets only for ACTIVE schools assigned to him.
  - Cannot create subtasks.

- `SCHOOL_MANAGER`
  - Can view own school tickets only.
  - Can create subtasks assigned only to staff in same school.
  - Can post final report on own school ticket.
  - Can approve/reject only own pending association requests.

- `STAFF`
  - Can view only subtasks assigned to self.
  - Can post replies/attachments and submit own subtasks.

## 5) Compatibility Layer Strategy
- Keep existing behavior; add centralized role helpers/services for new flows.
- Preserve old `users.role` checks while relying on Spatie for new policies.
- Extend, do not replace, existing controllers/pages used by Super Admin dashboard.

## 6) Execution Phases
- A) Migrations + Models + relations
- B) Policies + role helpers + services
- C) Handshake APIs + manager registration UI
- D) Ticket/Subtask APIs + status history + notifications/audit
- E) Supervisor/Manager/Staff dashboards
- F) Tests + build + smoke validation for Super Admin
