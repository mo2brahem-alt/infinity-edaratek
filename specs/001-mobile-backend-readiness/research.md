# Research: Mobile Backend Readiness

> **قرار PM نافذ 2026-11-09:** WhatsApp خارج المشروع نهائياً. الإشعارات app/web فقط، وpush حيث يوجد مطلب معتمد قائم؛ لا delivery tests أو provider configuration. أي نص WhatsApp/OTP/control/kill-switch قديم أدناه HISTORICAL/SUPERSEDED وليس شرط قبول نشطاً. المرجع docs/notification-policy.md وevidence/phase-06/scope-decision.md. لا staging/production أو Phase9؛ actual aliases/CDN تبقى UNKNOWN.

> PM2026-11-09: Phase7 APPROVED; Phase8 T103-T112 authorized with feature-branch commits/pushes and isolated CI. STOP at Phase8 PM review. No Phase9, deployment, real data/provider or shared-environment action.

2026-11-07. Local source/lockfile reconnaissance plus three read-only research workstreams. No files changed by research agents, application boot, tests, installs or live service calls.

## Runtime

**Decision:** Primary pinned dedicated PHP8.2 test container with disposable MySQL8.0; fallback ephemeral CI executing identical image/services in a separate test workflow. SQLite supplementary.

**Rationale/evidence:** No php/composer/docker on PATH/common runtime locations; WSL reports not installed. Existing Dockerfile is production-oriented: COPY . ., composer --no-dev, explicitly pdo_mysql/zip only. phpunit.xml does not isolate config cache; phpunit.mysql.xml isolates cache paths but inherits host/credentials. AttendancePerformanceIndexesTest and SubscriptionHandshakeFlowTest require MySQL; Excel imports require zip, attendance image tests check GD. AppServiceProvider boots PublicStorageLinkMaintainer outside unit tests, so even package discovery must use isolated synthetic state.

**Alternatives:** Local runtime absent; Sail dependency without usable compose/runtime is insufficient; host installation drifts; current deploy.yml cannot validate safely; production/shared staging prohibited. MySQL8.0 is proposed test baseline, actual live parity unknown. Pin patches/digests at provisioning. Array stores/sync queue cover ordinary tests; DB connections/shared test state cover races; Redis not required by this plan.

## Authentication

**Decision:** Installed Sanctum4.3 PATs, HasApiTokens, finite token-specific expires_at, native-only bearer guard, coarse native:access marker, owner-scoped native revocation; proposed initial TTL24h configurable/validated.

**Rationale/evidence:** composer.lock Laravel12.50/Sanctum4.3; User lacks trait; no app PAT migration/config/native stack. Vendor HasApiTokens::createToken accepts expiry; Guard tries web first, requiring separate no-session native guard and real-PAT check. Standard PAT metadata suffices for device display.

**Alternatives:** Native cookies add browser coupling; custom tokens/JWT duplicate primitives and complicate revocation/current-state checks. No refresh subsystem or separate device table necessary. Existing web guard remains unchanged.

## ACCOUNT_LEVEL and MIXED

**Decision:** Central account evaluator unions current legacy+Spatie roles; any manager/supervisor requires approved for all business roles; current activity, permission, school and resource determine access.

**Rationale/evidence:** User::hasSystemRole unions roles; requiresSuperAdminApproval uses primaryRole. Admin/UserController::approvalScopedUsers already matches any sensitive role. Single users.approval_status/actor timestamps exist. SchoolRoleAssignmentService and SchoolUserRoleAssignmentApiTest support staff+teacher. Spatie teams=false; roles global. Permission-group aggregation needs current-school scope.

**Alternatives:** Per-role approvals contradict accepted A-04; role order/client navigation creates bypass. Existing Spatie/policies suffice; no new permission system. Legacy school_admin is not an assumed privileged alias.

## Tenant/task scope

**Decision:** Request-scoped SchoolContextResolver, shared SubtaskAccessQuery and parent policies.

**Rationale/evidence:** users.school_id, schools.manager_user_id and independent supervisor relationships supply authority. Staff/SubtaskController::index assigned_to lacks school, SubtaskPolicy::view checks school. EnsureAcademicPlanningAccess manager branch bypasses active check; quick-setup needs suspension gate. Scope precedes filters/counts/binding.

**Alternatives:** Blanket global tenant scope risks administrator/system queries; scattered controller comparisons duplicate rules; frontend hiding does not protect IDs. Explicit supervisor selector must revalidate relation each request.

## Private files

**Decision:** Reuse AttachmentService/private disks with per-module authorization, resumable durable manifest and privacy-preserving rollback.

**Rationale/evidence:** Staff/SubtaskController::reply stores public ticketing/attachments; Attachment legacy URL is public. PublicStorageController/routes expose /storage and /media-files, public cached7days. PublicStorageLinkMaintainer can create symlink or copied public directory. Institutional SchoolAttachmentController module allowlist denies tickets. Leave download broad middleware/public fallback is insufficient.

**Alternatives:** Random names/signed URLs do not replace authorization; blanket privatization breaks logos; moving without DB compare-and-swap/manifest loses recovery. Static/CDN topology must be proven with authorized fixture verification; Laravel-only denial is insufficient. Already delivered browser cache cannot be recalled.

## WhatsApp

**Decision:** Live shared N8N_WHATSAPP_ENABLED control, uncached and disabled default, serialized transport admission/disable at client; config false also denies. Operational disable maps directly to live false, not an unrelated toggle. Editing .env alone cannot update existing workers.

**Rationale/evidence:** N8nWhatsAppClient::dispatch lacks enabled check; configured tests it at enqueue. Both jobs retry/direct-dispatch. Claim service atomically claims pending/unclaimed and preserves provider/uncertain evidence; cancelled/uncertain states already exist.

**Alternatives:** Enqueue/controller checks and restart-only controls cannot meet FR-027/SR-014. Reparsing all environment secrets per send adds coupling; explicit authoritative shared state is chosen. No enable workflow/integration activation is included. Detailed mapping/race/state contract in security-protocols.md is mandatory, not optional follow-up design.

## API and limits

**Superseding executed Phase7 decision2026-11-09:** actual frozen contract is contracts/mobile-api.md / docs/mobile-api-v1.md. Source8020e8b, CI42614974604:76/76 focused, full722/723 known Excel only. DTO/error/rate/pagination/date decisions are executed, not merely proposed. Login reserves canonical identifier5 + resolved account-ID5 + IP30 atomically before password check, preserving account budget across email/IP changes; reads120/300, sessions10/30, reset3/10. Shared independent-process fixtures prove coordination; source cache deployment settings remain unverified. OTP/WhatsApp portions below are permanently superseded. See evidence/phase-07/implementation.md and results.md. No Phase8 or MOBILE_BACKEND_READY claim.

**Decision:** Additive /api/v1, thin shared-service adapters, explicit Resources, deterministic codes, pagination25/max100 and side-effect-free new reads.

**Rationale/evidence:** Current /api routes are web/session/CSRF; no native Resources layer. Raw models and mixed pagination/prose are unstable. LoginRequest already uses5 attempts/email+IP; retain baseline and supplement account/IP budgets. OTP budget/cooldown intent retained with atomic user/current-phone key independent of IP changes.

**Alternatives:** Moving existing routes breaks consumers; global JSON rendering breaks redirects; copying controllers duplicates domain logic. Contract prepares foundation/task reads/private downloads, not every future mobile CRUD or OTP UI. Numeric tuning beyond existing baseline is configuration validation, not business ambiguity.

## Primary references and resolution

Local actual locked code is authoritative: composer.json/lock; vendor/laravel/sanctum/src/Guard.php and HasApiTokens.php; User; bootstrap/app.php; routes/web.php; relevant policies/middleware/controllers/services/migrations/tests; phpunit*.xml; Dockerfile; deploy.yml. Audit/spec contain detailed finding references. Secret values omitted.

Official docs verified2026-11-07: [Laravel12 Sanctum](https://laravel.com/docs/12.x/sanctum) for PAT issuance/expiry/revocation; [Docker Windows requirements](https://docs.docker.com/desktop/setup/install/windows-install/) for WSL/virtualization validation. Preserve locked versions; no upgrade implied.

All design choices resolved. Phase-owned remaining validations: runtime patches/digests/extensions/DB parity(P1); TTL/limiter tuning(P2/P7); fixture inventory/static/cache proof(P5); live flag/disable concurrency(P6). No remaining PM business decision; no production inspection inferred.
