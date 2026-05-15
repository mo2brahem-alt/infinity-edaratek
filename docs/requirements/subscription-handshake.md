# Subscription + Onboarding + Two-Step Handshake

## Goal
Add pricing plans, user subscriptions, onboarding wizards, and a two-step school-supervisor handshake while preserving all existing Super Admin dashboard contracts.

## 1) New Additive Data Model

### plans
- `id`
- `name`
- `role_type` (`SUPERVISOR`, `SCHOOL_MANAGER`)
- `price`
- `billing_cycle` (`MONTHLY`, `YEARLY`)
- `is_active`
- `limits` (json, nullable)
- `description` (nullable)
- `created_at`, `updated_at`

### subscriptions
- `id`
- `user_id` FK
- `plan_id` FK nullable
- `status` (`PENDING`, `ACTIVE`, `CANCELED`, `EXPIRED`)
- `starts_at`, `ends_at`
- `meta` (json, nullable)
- `created_at`, `updated_at`

### school_supervision_requests
- `id`
- `school_id` FK
- `region_id` FK nullable (`educational_directorates`)
- `supervisor_id` FK
- `manager_id` FK nullable
- `status`:
  - `SUPERVISOR_REQUESTED`
  - `MANAGER_APPROVED`
  - `ACTIVE_ASSOCIATION`
  - `MANAGER_REJECTED`
  - `SUPERVISOR_REJECTED`
  - `CANCELED`
- `requested_at`
- `manager_action_at`
- `supervisor_confirmed_at`
- `notes` nullable
- `created_at`, `updated_at`

### Additive columns
- `users.onboarding_region_id` nullable FK
- `users.onboarding_completed_at` nullable datetime
- `schools.supervision_status` nullable/default `SUSPENDED`

## 2) Handshake State Machine

1. Supervisor selects region + schools (onboarding):
   - create request status `SUPERVISOR_REQUESTED`.
2. School manager approves:
   - status -> `MANAGER_APPROVED`.
3. Supervisor final confirm:
   - status -> `ACTIVE_ASSOCIATION`.
   - finalize school binding:
     - `schools.supervisor_id = supervisor_id`
     - `schools.status = ACTIVE`
     - `schools.supervision_status = ACTIVE_ASSOCIATION`

Alternative endings:
- Manager rejects -> `MANAGER_REJECTED`
- Supervisor cancels before final confirm -> `CANCELED`

## 3) New Endpoints

### Public / Auth
- `GET /pricing` (Inertia pricing page)
- `GET /plans?role_type=SUPERVISOR|SCHOOL_MANAGER`
- `GET /auth/register/supervisor`
- `POST /auth/register/supervisor`
- `GET /auth/register/manager`
- `POST /auth/register/manager`

### Admin
- `GET /admin/plans`
- `POST /admin/plans`
- `PUT /admin/plans/{plan}`
- `DELETE /admin/plans/{plan}`
- `POST /admin/subscriptions/{subscription}/activate`
- `POST /admin/subscriptions/{subscription}/cancel`

### Supervisor onboarding + requests
- `GET /supervisor/onboarding`
- `GET /supervisor/onboarding/regions`
- `GET /supervisor/onboarding/regions/{region}/schools`
- `POST /supervisor/onboarding/select`
- `GET /supervisor/requests/inbox` (Inertia page)
- `GET /supervisor/requests` (JSON list)
- `POST /supervisor/requests/{request}/confirm`
- `POST /supervisor/requests/{request}/cancel`

### Manager onboarding + requests
- `GET /manager/onboarding`
- `GET /manager/onboarding/regions`
- `GET /manager/onboarding/regions/{region}/schools`
- `POST /manager/onboarding/select`
- `GET /manager/requests/inbox` (Inertia page)
- `GET /manager/requests` (JSON list)
- `POST /manager/requests/{request}/approve`
- `POST /manager/requests/{request}/reject`

## 4) RBAC Rules Enforced
- Supervisor onboarding selection validates every selected school belongs to selected region.
- Duplicate active request for same `(school_id, supervisor_id)` is prevented.
- Manager can only act on requests tied to his own selected school.
- Manager cannot select a school already linked to another manager.
- Final active association is only possible from `MANAGER_APPROVED` state by same supervisor.

## 5) Compatibility Notes
- Existing Super Admin routes/components are untouched.
- Existing legacy manager registration flow (`/auth/register-manager`) remains available.
- Existing ticketing and legacy association modules remain intact.
- New modules are additive and isolated.

## 6) Tests Added
- `tests/Feature/SubscriptionHandshakeFlowTest.php`
  - covers plans API
  - supervisor registration with plan + subscription creation
  - supervisor onboarding request creation and duplicate prevention
  - manager registration with plan + subscription creation
  - manager onboarding school binding
  - manager approve + supervisor final confirm workflow
  - manager forbidden from selecting school linked to another manager
