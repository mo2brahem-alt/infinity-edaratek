# Data Model: Mobile Backend Readiness

> **قرار PM نافذ 2026-11-09:** WhatsApp خارج المشروع نهائياً. الإشعارات app/web فقط، وpush حيث يوجد مطلب معتمد قائم؛ لا delivery tests أو provider configuration. أي نص WhatsApp/OTP/control/kill-switch قديم أدناه HISTORICAL/SUPERSEDED وليس شرط قبول نشطاً. المرجع docs/notification-policy.md وevidence/phase-06/scope-decision.md. لا staging/production أو Phase9؛ actual aliases/CDN تبقى UNKNOWN.

> PM2026-11-09: Phase7 APPROVED; Phase8 T103-T112 authorized with feature-branch commits/pushes and isolated CI. STOP at Phase8 PM review. No Phase9, deployment, real data/provider or shared-environment action.

Design only, 2026-11-07. Existing Laravel schema remains authoritative; inspect actual isolated schema before creating tables. No migrations executed or application schema files created.

## Existing User / approval

Keep id, name, email, legacy role, school_id, is_active, approval_status, approval actors/timestamps and current canonical phone/verification fields. Approval states remain pending_approval/approved/rejected. Spatie role pivots permit multiple global roles; no role-level approval columns/table. Effective approval_required is computed from the union of legacy+Spatie roles intersecting school_manager/supervisor. All business roles blocked unless account active and required approval is approved. Existing authorized admin approval transitions remain in UserApprovalService; adding/removing a role never silently changes is_active/approval_status.

Approved account becoming disabled/pending/rejected: next protected request denied, no capability retained in PAT. Role/permission/membership removal: next request recomputes. Deleted user:401. Eligible unlinked account: /me school=null, school operations409. Changing canonical phone invalidates old verification/OTP context. No new approval state or account migration required.

## Existing role / permission / school relationships

Spatie roles/permissions and model_has_roles remain global (teams=false). School delegated-group membership must be scoped to resolved school before aggregation. User.school_id establishes ordinary current staff school; School.manager_user_id adds manager ownership. Supervisor assignment/request/association state is separately validated; requested/approved/active/rejected/canceled association states do not replace account approval. No tenant schema redesign.

## Native session: Sanctum personal_access_tokens

Expected additive application migration if table absent: standard id, tokenable_type/id, name, unique token hash, abilities, last_used_at nullable, expires_at nullable framework column, timestamps. Native issuance requires non-null finite expires_at even if schema allows null for other consumers. Verify vendor migration/version before adapting; never run duplicate create or overwrite existing token data.

- tokenable belongs to existing User; name uses native prefix, device UUID and bounded label.
- abilities contains native:access only; no embedded roles/tenant claims.
- id is management identifier, not secret; token plaintext returned only at issuance, never persisted/logged.
- device UUID is display/grouping metadata, cannot select another user's token or revive a session.
- all list/revoke queries constrain owner AND native marker; do not revoke browser sessions or unrelated integration tokens.
- states: issued→valid until expiry; valid→revoked by deletion; elapsed expiry→invalid; no auto-renew. Prune expiry later only as safe maintenance, not authorization prerequisite.
- no device table, refresh table or custom token columns required initially. Reject malformed/oversize name/device fields.

Forward: add compatible PAT schema first, then native routes. Rollback: disable routes first; avoid table drop with live/unknown token data. Revoke only native tokens if separately authorized; preserve nonnative records.

## Existing tasks and attachments

Subtask assignment and school_id plus parent-ticket school must agree with resolved current school. Assignment alone is insufficient. Filter/count queries use the same scope as detail authorization. No task schema change required initially; index changes only after explain/query evidence, not speculative migration.

Attachment retains stable id, module/parent reference, school ownership, disk/path, filename/MIME/size and privacy metadata where already present. Internal path fields never enter public DTOs. Classification is derived from purpose/parent policy, not is_private or caller input alone. Ticket/leave/institutional parent authorization differs explicitly. Private object storage path is not a credential. Soft-deleted/unlinked/missing bytes remain inaccessible. Existing metadata suffices unless implementation inventory proves a specific missing field; do not add redundant columns.

## New durable private-file migration manifest

Minimal justified table `private_file_migrations`: id, schema_version, attachment_type/id (nullable for orphan inventory), source_disk/path, destination_disk/path, source_size/hash, observed_source_metadata/version, state, safe_failure_code, timestamps. Internal-only service/console access, no public API. Unique stable inventory key (source identity+schema version) prevents duplicate copies. Orphans/ambiguous owners quarantine and block completion. Paths/hashes are internal operational data, never response/log payloads.

States: inventoried→copied_verified→metadata_switched→public_revoked→verified. Conflict/failure is resumable with safe reason and retained prior state; do not fake verified. CAS compares observed original metadata before switch. Each batch bounded, checksummed and retry-safe; source/destination changes become conflicts. Physical copies/symlink paths/cache aliases recorded or separately evidenced so final state cannot precede anonymous URL denial.

Forward: additive manifest first; private download compatibility before copies; copy verification before short per-record metadata transaction; revocation before completion. Rollback preserves private bytes/metadata and public denial; never restore sensitive public fallback. Retain manifest for reconciliation; no automatic destructive down migration or file deletion. Real inventory/moves/deletion require separate authorization.

## New live integration control

Minimal table `integration_controls`: key unique (`N8N_WHATSAPP_ENABLED`), enabled boolean NOT NULL default false, version monotonic integer, updated_at, authorized actor reference nullable. Seed disabled only without overwriting existing state; this feature never enables integration. Restrict writes to explicit server-side operator control; no mobile endpoint.

Read at every transport admission, uncached, across all workers. Missing/unavailable/invalid row denies. Serialize disable and admission under one shared DB coordination mechanism, using consistent lock ordering with message claim. Hold the admission critical section through bounded transport initiation (implementation may hold until HTTP call returns for an unambiguous tested boundary); disable completes after earlier admitted calls, and admits none afterward. Never silently use boot-true on DB error. Config false additionally denies. See security-protocols.md for operational flag mapping.

Forward: create disabled control before gate use; absence already fails closed. Rollback keeps disabled durable record/gate; do not remove the safety mechanism while workers can dispatch. No unrelated API-state table.

## Existing WhatsApp message / claim / OTP

Keep existing pending/accepted/sent/delivered/read/failed/cancelled/uncertain states, provider identifiers, unique idempotency key, atomic claim token/timestamp and callback reconciliation. No new enum needed. Pending unclaimed disabled→cancelled(reason whatsapp_disabled), no send attempt. Claimed ambiguous→uncertain, preserving claim. Accepted/provider-confirmed states unchanged. Reenable never resurrects cancelled/uncertain. Acknowledging a job is not sent/delivered evidence.

Use existing safe error/reason metadata if sufficient; only add a nullable reason column if current schema cannot durably encode disabled reason. Never put OTP plaintext/claim secrets in manifests or DTOs. OTP issuance/verification uses current canonical phone and per-user/phone atomic budget, independent IP signals supplementary; changing phone invalidates prior verification. No provider/live schema access during planning.

## Migration validation

Run forward, repeat, interrupted resume and rollback rehearsal only in disposable synthetic databases/storage. Verify old code compatibility with additive schema, nonnative token preservation, concurrent copies/claims, no deadlocks under prescribed lock order, zero data loss and no public re-exposure. Shared staging non-destructive verification needs later independent permission. Actual production schema/topology is unknown; this feature plans no production migration/deployment.
