# Implementation Plan: Mobile Backend Readiness

> **قرار PM نافذ 2026-11-09:** WhatsApp خارج المشروع نهائياً. الإشعارات app/web فقط، وpush حيث يوجد مطلب معتمد قائم؛ لا delivery tests أو provider configuration. أي نص WhatsApp/OTP/control/kill-switch قديم أدناه HISTORICAL/SUPERSEDED وليس شرط قبول نشطاً. المرجع docs/notification-policy.md وevidence/phase-06/scope-decision.md. لا staging/production أو Phase9؛ actual aliases/CDN تبقى UNKNOWN.

> PM2026-11-09: Phase7 APPROVED; Phase8 T103-T112 authorized with feature-branch commits/pushes and isolated CI. STOP at Phase8 PM review. No Phase9, deployment, real data/provider or shared-environment action.

**Feature:** 001-mobile-backend-readiness | **Date:** 2026-11-07 | **Spec:** [spec.md](spec.md)

Historical planning snapshot2026-11-07: repository `D:\edaratek\public_html`, then main at `12b9d5326d942acda55309aac05c6cee1bf928b0`. Current Phase7 branch is `001-mobile-backend-readiness`, starting `d1810cc`; current execution is recorded in evidence/phase-07/.

## Summary

Prepare the existing Laravel backend for a later native client using installed Sanctum personal access tokens, current ACCOUNT_LEVEL approval, MIXED authorization, server-derived school scope, authorized private files, supported app/web notifications and permanent retirement of the removed channel and an additive /api/v1 contract. Vue/Inertia and integrations keep their existing contracts; shared services/policies remain authoritative. This plan does not implement readiness. Current backend verdict remains MOBILE_BACKEND_NOT_READY.

Authority: spec and its five accepted clarifications, requirements checklist markers21/21, Constitution v2.0.0 (PM2026-11-09 notification scope amendment), Phase0 audit at `C:\Users\HP\.codex\artifacts\edaratek-phase0-20261107\EDARATEK_MOBILE_BACKEND_AUDIT.md`. Historical checklist prose about pending A-04 is superseded by the accepted spec. Spec/checklist active scope follows the PM amendment; historical findings remain evidence.

## Technical Context

| Item | Evidence/decision |
|---|---|
| Language/framework | PHP^8.2, locked Laravel12.50.0; no dependency upgrade |
| Dependencies | Installed Sanctum4.3.0, Spatie Permission6.24.1, Inertia Laravel2.0.19 |
| Existing frontend | Vue3.5.28 / Inertia Vue2.3.13, current sessions/CSRF/routes retained |
| Storage | Existing MySQL-oriented schema, private local/school disks, intentionally public branding |
| PRIMARY_TEST_RUNTIME | Dedicated pinned PHP8.2 Linux test container with dev dependencies and disposable MySQL8.0 internal service |
| FALLBACK_TEST_RUNTIME | Ephemeral CI Linux runner using the identical image/services in a separate test workflow |
| Runtime availability | No usable PHP/Composer/Docker or installed WSL detected; provision only in later approved Phase1 |
| Testing | PHPUnit, actual database PATs, MySQL concurrency, HTTP/queue fakes; SQLite supplementary only |
| Platform/type | Existing Laravel backend/web application; no Flutter or second backend |
| Performance | Lists default25/max100; scoped totals and eager loading. Baseline query/latency measurement on synthetic large fixtures precedes budgets; no invented throughput claim |
| Scale/scope | 36FR,24SR, all H01-H05, twelve P0 groups, affected P1/P2 and web regressions |
| Constraints | Documents only now; later one approved phase then STOP; no production action, deployment or real provider sends |

Resolved choices and alternatives: [research.md](research.md). Runtime image digests/DB parity, exact inventory/static-cache topology and policy tuning are phase-owned validation items, not remaining business ambiguity.

## Constitution Check

| Principle | Pre-research | Post-design |
|---|---|---|
| I Security First | PASS | Current-state gates, IDOR/privilege tests, fail-closed defaults |
| II Laravel authority | PASS | Thin native adapters share existing services/policies |
| III Server tenant | PASS | Resolver, scoped query and parent policy |
| IV Compatibility | PASS | Existing web/CSRF/integrations retained, audited corrections tested |
| V Stable contract | PASS | Versioned DTOs, codes, pagination and date rules |
| VI Native auth | PASS | Finite PATs, bearer-only, owner revocation, no frozen privileges |
| VII Attachments | PASS | Private bytes, per-request policy, legacy URL revocation |
| VIII Supported notifications | PASS | App/web continuity; WhatsApp permanently retired; no delivery test dependency |
| IX Executed test gates | PASS | No tests claimed executed; readiness requires passing execution |
| X No production development | PASS | Disposable synthetic runtime; no production steps |
| XI Phase boundaries | PASS | Nine dependent phases, review STOP after each |
| XII Separate approvals | PASS | Plan review before tasks; each implementation phase separately authorized |
| XIII Secrets | PASS | Redacted evidence, minimal DTO/log fields, synthetic fixtures |
| XIV Audit-driven | PASS | All findings and P0 groups mapped below |

PASS describes design compliance, not completed remediation. No constitutional exception requested.

## Project Structure

Planning artifacts: plan.md, research.md, data-model.md, quickstart.md, contracts/mobile-api.md, contracts/security-protocols.md in this feature directory. No tasks.md generated.

Likely future source locations, not created now:

```text
app/Http/Controllers/Api/V1/     thin adapters
app/Http/Requests/Api/V1/        allowlisted validation
app/Http/Resources/Api/V1/       stable DTOs
app/Http/Middleware/            bearer/account/context wrappers
app/Services/                  shared account/context/task/download decisions
app/Services/WhatsApp/          live gate and existing transport/claim services
app/Models/                    User/PAT and minimal durable metadata
routes/api.php                 additive v1 routes
bootstrap/app.php              routing and v1-only error renderer
database/migrations/           minimal additive schema
tests/Feature/Mobile/           proposed native/security/contract tests
tests/Feature/                 existing web/domain regressions
```

Match actual namespaces during implementation; proposed responsibility names do not require redundant abstraction layers.

## Architecture

1. Add HasApiTokens to User and reuse Sanctum schema. Native token name `native:<device_uuid>:<label>`, ability `native:access`; label is bounded metadata, never authorization. Proposed configurable default lifetime24h, finite maximum, explicit expires_at, no sliding refresh or refresh-token subsystem. Validate policy configuration in Phase2. Secret returned once, hashed server-side; future native storage is platform secure storage over HTTPS. No integration credentials in clients.
2. Native stack is stateless and explicitly bearer-only. Sanctum tries web first by default: configure a native-only Sanctum guard with no session guards, require real PersonalAccessToken/native marker and explicit Authorization Bearer. Do not change the global web guard. Test cookie-only and conflicting cookie/bearer identities. Existing session/CSRF routes remain intact.
3. Shared AccountAccessEvaluator unions all current Spatie roles and legacy role; any school_manager/supervisor requires approved for all business access. is_active must also be true. Pending/rejected error precedence follows spec even when inactive. No primaryRole/frontend-role decision or per-role approval schema. Native login checks credentials then eligibility before token creation. Web middleware/service wrappers consume the same decision. Reload current state each business request; logout current token alone may cancel it despite blocked business access.
4. MIXED authorization combines account, existing Spatie permission, current school-scoped delegated groups, role/relation and resource policy. Invalidate/reload permission caches after changes and do not freeze capabilities in tokens. Allowlist requests; forbid privileged/tenant mass assignment. Legacy school_admin is not silently elevated.
5. Request-scoped SchoolContextResolver uses staff users.school_id; manager additionally proves schools.manager_user_id; supervisor selector is permitted only on explicitly declared operations and validated current relation every request. Incomplete context permits eligible /me with school=null, school operations409; suspended school403 including quick-setup. Account approval and supervision/association state remain distinct.
6. Shared SubtaskAccessQuery intersects assignment, current school and parent-ticket school before filters/counts/pagination. Web/native list/detail use it; scoped detail binding then policy; reply/submit and attachments enforce parent policy. Stale delegated school groups cannot grant access after transfer.
7. Private downloads reuse AttachmentService/private disks with ticket/institutional/leave parent authorization. Institutional controller currently denies ticket modules: explicitly add ticket policy handling rather than route reuse with blanket access. Leave download requires leave-operation permission, not broad calendar access. New affected uploads use centralized MIME/size hardening and private storage. Logos remain public. Migration protocol is in contracts/security-protocols.md.
8. WhatsApp transport admission reads a durable shared live flag named N8N_WHATSAPP_ENABLED, uncached and default false; serialize disable/admission. Boot config false is an extra deny, never sole authority. Supported disable persists live false before reporting success; editing .env alone cannot update a running worker. Bootstrap from false stays disabled; enable is outside this feature. Jobs have supplementary checks; transport boundary is mandatory. Preserve claims/uncertain/idempotency and do not replay cancelled messages.
9. Add /api/v1, retain existing /api/system and /api/school web/session/CSRF endpoints. Thin adapters reuse approval/subscription/association/calendar/notification services. Native GETs do not provision defaults. Resource DTOs and v1-only JSON errors avoid global changes. Breaking changes need future v2 with coexistence/consumer tests, not a copied backend.

## Implementation phases

Each phase requires explicit PM authorization before work and STOP for review afterward. Approval of this plan does not authorize all phases. Common gates: I/II/X/XII/XIII/XIV. Shared staging verification is a later separately authorized non-destructive smoke check; never destructive automated tests. Production actions are outside these phases.

### Phase1 — Test Runtime & Baseline

- Goal/components: dedicated test image/harness and separate future CI test workflow; assess Dockerfile/phpunit configurations without repurposing deployment image.
- Dependencies/approach: none; quickstart prerequisite validation, pinned image/DB, filtered disposable copy, synthetic env/cache paths before all bootstrap, dev dependencies, database guard and provider egress denial.
- Tests/acceptance: baseline MySQL suite plus optional SQLite; record versions/hash/commands, failures and skips. MySQL/zip/GD-required tests must execute. Prepare debug-false/config isolation checks for H05; no live environment claim.
- Rollback/gates: remove only verified task-owned disposable resources, never repository/shared data. IX/SR-018. PM reviews working runtime and baseline before Phase2.

### Phase2 — Mobile Authentication Foundation

- Goal/components: User trait, PAT migration/config, native guard/middleware, login/me/logout/devices requests/resources and login limiter.
- Dependencies/approach: Phase1. Implement shared ACCOUNT_LEVEL evaluator now as prerequisite to issuance/protected routes, rather than using the old primaryRole logic. Finite owner-scoped native tokens; keep web guard unchanged.
- Tests/acceptance: T0-01/02/03 plus initial09; real PATs, expired/revoked/deleted user, blocked account, cookie-only/conflicting identity, multiple devices, session isolation and minimal DTOs. No unlimited token issued.
- Rollback/gates: disable native routes if necessary, preserve data/schema until reviewed, revoke only native tokens if authorized. VI/IX. PM reviews foundation.

### Phase3 — Account Approval & Authorization Hardening

- Goal/components: User approval helpers, EnsureApprovedAccount, UserApprovalService callers, delegated permissions, auth/reset controllers, admin seeder and safe errors.
- Dependencies/approach: Phase2 evaluator; route shared/web decisions through it; preserve legitimate staff+teacher. Remove embedded admin secrets and implicit existing-password reset; explicit operator provisioning with no automatic change to existing admins. Normalize reset result/throttle. H05 prove synthetic debug-false/no trace and safe cache configuration, document live state unknown.
- Tests/acceptance: T0-08/09/11, every A-04 row and role-order permutation/legacy role; role removal, privilege injection, repeated seeder preservation, generic reset, no secret/error leakage.
- Rollback/gates: retain denial/credential preservation; never restore known bypass or fixed passwords. I/IV/VI/XIII. PM reviews changed shared semantics.

### Phase4 — Tenant Isolation & Staff Task Security

- Goal/components: context resolver, manager/association/academic middleware, scoped permission groups, SubtaskController/Policy/query, quick-setup and relationship validation.
- Dependencies/approach: Phase3; resolve context before binding/query filters, enforce current membership/parent school and preserve web shapes.
- Tests/acceptance: T0-04/05/06/07/08; method-by-method two-school matrix including direct/nested IDs, counts/filters/pages, transfers/stale groups, manager impersonation, suspended quick-setup and supervisor relation removal. Foreign404; missing context409.
- Rollback/gates: never restore unscoped query; disable new native exposure if needed while retaining security fixes. III/IV/IX. PM reviews route matrix.

### Phase5 — Private Attachment Security

- Goal/components: Attachment/AttachmentService, ticket and leave upload/download, public controllers/static-link safeguards, durable manifest tooling.
- Dependencies/approach: Phase4 parent authorization; protected reads/private writes first, synthetic inventory, verified copy, CAS metadata switch, revoke aliases/static copies/link/cache. Actual file inventory/move/deletion needs separate environment/data authorization.
- Tests/acceptance: T0-04/06/10; Laravel and representative static HTTP denial for old aliases, foreign/anonymous/missing/deleted denial; leave-specific permissions; correct bytes/MIME/disposition; traversal and upload hardening; crash/resume/conflict/rollback; public logo retained. Unclassified sensitive files/unverified static bypass block readiness.
- Rollback/gates: keep private bytes/protected metadata/public denial, resume cleanup; never republish. VII/IX/XII. PM reviews dry-run and operational boundaries.

### Phase6 - Supported app/web notifications; permanent channel retirement

- PM2026-11-09 supersedes the former WhatsApp kill-switch phase. Original requirements: evidence/phase-06/original-requirements.md; T071-T088 dispositions: scope-decision.md.
- Preserve NotificationService/database/web consumers and recipient authorization. Remove retired fan-out and transport admission; old routes return fixed410 and serialized jobs remain inert. No provider/OTP/control schema or replacement verification feature.
- Prove active generation, ownership/read-all, stale dependency independence, history preservation and generic phone compatibility. Exact obsolete test-file exclusion with static reconciliation; prior regressions and complete active suite.
- No migration, external transport, shared queue, environment, real file operation or Phase7. End at Phase6 PM review; MOBILE_BACKEND_NOT_READY.

### Phase7 — Versioned Mobile API Contract

- Goal/components: finalize minimally introduced v1 routes/resources/requests/error renderer, task/download/current-user schemas, pagination/rate/logging conventions.
- Dependencies/approach: Phases2–6; freeze contracts against implementation, reuse services, no broad new business CRUD or native OTP UI; legacy consumers keep shapes.
- Tests/acceptance: types/nullability/codes, seven error statuses, date-only/UTC/Arabic/decimal, stable pagination including empty/overrun/invalid pages, allowed filters, rate reset/Retry-After, no GET provisioning, large fixture/query counts, no list transcript.
- Rollback/gates: native routes may disable; do not globally alter web renderer. IV/V/IX. PM reviews v1 stability.

### Phase8 — Full Security / Regression Test Gate

- Goal/components: complete automated evidence matrix and affected Vue/Inertia/domain regressions.
- Dependencies/approach: all prior phases; full isolated MySQL, optional SQLite, concurrency and static-file server inside test network. Compare baseline; intentional security corrections documented.
- Tests/acceptance: all12 P0 implemented/executed/passing, mandatory affected P1/P2; minimal DTO/log/error outputs; legitimate sessions/multi-role/delegation/subscription/association/download/notification/WhatsApp flows preserved; no unresolved CRITICAL/HIGH mobile blocker. No P0 waiver as preexisting.
- Rollback/gates: repair within approved scope or disable native exposure, never weaken controls. IX/XIV. PM signs evidence.

### Phase9 — Mobile Backend Readiness Verification

- Goal/components: readiness report/blocker register, no client implementation/deployment.
- Dependencies/approach: Phase8 and PM review; reconcile spec/audit/contract snapshots/runtime manifest/migration proof and remaining environment validations.
- Tests/acceptance: final gate below; repeat checks only when changes invalidate evidence; MOBILE_BACKEND_READY only with passing evidence, otherwise exact blockers and MOBILE_BACKEND_NOT_READY.
- Rollback/gates: withdraw invalidated readiness; no data rollback/Flutter action. IX/XI/XII. PM accepts backend readiness; Flutter remains a separately approved feature.

## P0 traceability

Proposed test names under tests/Feature/Mobile may extend equivalent existing suites. Use real PATs, two-school/mixed-role fixtures and HTTP fakes. Enumerate every prepared and affected method, not just a representative route.

| Group | Spec requirements | Phases/components | Test evidence |
|---|---|---|---|
| T0-01 | FR-002/009; SR-002/006 | 2–3 login/evaluator | NativeLoginTest: eligible success, pending/rejected/inactive no token |
| T0-02 | FR-005–008; SR-002/024 | 2 tokens/sessions | NativeTokenLifecycleTest: finite expiry/current/target/all revoke, repeat logout, owner isolation, browser/nonnative token retained |
| T0-03 | FR-007–009; SR-002 | 2–3 current state | NativeCurrentStateTest: disabled/deleted/compromised tokens denied, blocked current-token cancellation |
| T0-04 | FR-012–018/020–023; SR-003/004 | 4–5 scope/policy | TenantEndpointMatrixTest: list/show/create/update/delete/download for prepared/affected routes, foreign counts/query/body IDs |
| T0-05 | FR-015/016; SR-007 | 4 task query | StaffTaskIsolationTest: transfer/list/detail/reply/submit, parent mismatch, filters/pages/counts |
| T0-06 | FR-014; SR-004 | 4–5 parent validation | ForeignRelationshipTest: valid foreign IDs/nested parents, no unauthorized writes |
| T0-07 | FR-013; SR-003/023 | 4 resolver | TenantInjectionTest: school_id/header spoof, manager impersonation, supervisor selection/removal |
| T0-08 | FR-004/034; SR-005 | 3–4 permission/requests | PrivilegeEscalationTest: nonadmin role/approval/permission injection, stale delegated groups |
| T0-09 | FR-009; SR-006 | 2–3 account union | AccountApprovalMatrixTest: all A-04 rows/order/legacy, adding staff cannot bypass; staff+teacher retained |
| T0-10 | FR-019–026; SR-008–011 | 5 private migration | PrivateAttachmentMigrationTest/StaticPrivateUrlTest: aliases/static/link/cache, leave gate, missing/deleted, crash/resume/rollback, public branding |
| T0-11 | SR-012/017 | 3 seeder | SafeAdminSeederTest: no fixed default/unsafe reset, repeat preserves credentials, synthetic fixtures |
| T0-12 | FR-027–029; SR-014–016 | 6 live gate/jobs | WhatsAppExecutionGateTest: direct/queue/stale/race/reenable, zero HTTP and no false sent state |

12/12 planned groups, zero executed during planning. Additional contract/security tests assert all allowlisted fields, no traces/secrets, upload/path checks and every error semantic.

## Audit closure and P1/P2

| Finding | Planned closure |
|---|---|
| H01 | P5 private ticket writes/download policy, public/static/cache revocation |
| H02 | P4 current-school task scope |
| H03 | P3 fixed-secret/unsafe-reset removal |
| H04 | P6 live execution gate and durable unsent cancellation |
| H05 | P1/P3/P8 synthetic debug-false/cache safeguards; live state unknown, no production inspection/remediation planned |
| M01/M07 | P5 leave-specific gate, no public fallback, upload hardening |
| M02 | P4 suspended school quick-setup gate |
| M03/M04 | P2/P3 native current-state ACCOUNT_LEVEL |
| M05 | P3/P7 request/DTO allowlists |
| M06 | P3 reset enumeration/rate controls |
| L01 | P4/P7 read-only native GETs; explicit provisioning remains separate |
| L02 | P8 notification ownership/unread count regressions; native push deferred |
| L03 | P7 contract/date/error/pagination |
| L04 | P6 atomic OTP global budget/current-phone invalidation |

Affected P1 includes leave/suspended-school gates, schema/codes, multipart, business conflicts/impact confirmation, OTP cooldown/unavailable/races, notification ownership and subscription/dashboard regressions. P2 includes large synthetic fixtures/query budgets, Arabic/date-only/timezone/DST, additive compatibility and affected exports. Future push/UI accessibility/polling implementation remains out of scope.

## Migration, observability and compatibility

See data-model.md for minimal additive schemas and rollback invariants. Verify disposable actual schema before PAT creation; no per-role approval/device table or tenant redesign. Expand first, preserve old compatible code until verified, resumable private-file batches and retained claim history. Rehearse forward/rollback on isolated fixtures; shared staging smoke requires separate later authorization, never destructive tests.

Use bounded validated/generated request IDs in v1 errors/logs. Log safe event/actor/resource identifiers, denial code, gate version, duration and counters. Exclude auth headers, bodies, passwords/tokens/OTP/HMAC/Meta credentials, private paths/filenames/contents. Rate-limit denial logging. Metrics: auth failure codes, authorization denials, kill skips, uncertain counts, migration conflicts, API errors/query latency; automate redaction assertions.

Regression inventory: web login/logout/session/CSRF; approval redirects; supported multi-role/delegation; association/subscription/pricing; tasks list/show/reply/submit; ticket/institutional/leave downloads and logos; existing /api/system and /api/school consumers; callbacks/HMAC/replay/claims/OTP; notification ownership/unread semantics. Security defect corrections are intentional, not compatibility obligations to preserve vulnerabilities.

## MOBILE_BACKEND_READY gate

Require operational isolated runtime; all P0 implemented/executed/passing; tested finite native auth/current-state ACCOUNT_LEVEL/MIXED authorization; current tenant/task scope; authorized private bytes and proven old URL rejection; supported app/web notification continuity and no retired-channel dependency; stable v1 contracts; affected P1/P2 and regressions passing; no unresolved CRITICAL/HIGH mobile blocker; PM evidence acceptance. Missing static/cache proof or applicable H05 safety evidence blocks the gate. Local readiness does not certify an uninspected production environment or authorize deployment. Flutter remains blocked until this gate and separate feature approval.

## Complexity Tracking and remaining validations

No constitutional violations. Shared evaluators/resolver/query/gate avoid duplicated rules; durable control/manifest are justified by stale workers and resumable migration. No remaining PM business decision. P1 pins runtime/DB/extensions; P2/P7 validates finite TTL/limit configuration; P5 verifies fixture inventory/static/cache topology; P6 proves flag mapping/admission concurrency. No unresolved architecture question.

Stop after planning. PM review precedes speckit-tasks; no checklist/tasks/analyze/implement, runtime installation, tests, migrations, deployment or Flutter in this step.
