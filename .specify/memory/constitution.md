<!--

Sync Impact Report (temporary amendment review material; remove before a future
separately approved commit; this comment does not authorize one).
Version: 1.0.0 -> 1.1.0 (MINOR: additive principles and expanded governance).
Modified principles:
- I. School isolation and authorization -> I. Security First and
  III. Server-Resolved Tenant Context.
- II. Active application stack -> preserved in Project Constraints and expanded
  by II. Laravel Is the Single Source of Truth and IV. Backward Compatibility.
- III. Arabic user experience -> preserved in Project Constraints.
- IV. Evidence-based specifications -> XIV. Audit-Driven Work and evidence workflow.
- V. Proportionate verification -> IX. Tests Are Release Gates; documentation-only
  proportionality is preserved in Development Workflow.
Added: 14-principle mobile-readiness framework; Phase 0 finding and P0/P1/P2
test-gap baseline; phase boundaries, independent approvals and readiness gates.
Removed: none of the prior project requirements.
Template resolution: constitution-template resolved successfully through the
installed PowerShell resolver. Dependent templates/commands read the constitution
at runtime; no template or skill source changes are required or authorized.
Deferred placeholders/TODOs: none.
Scope: constitution foundation only, not specifications, tasks or implementation.
-->

# Edaratek Constitution

> **قرار PM نافذ 2026-11-09:** WhatsApp خارج المشروع نهائياً. الإشعارات app/web فقط، وpush حيث يوجد مطلب معتمد قائم؛ لا delivery tests أو provider configuration. أي نص WhatsApp/OTP/control/kill-switch قديم أدناه HISTORICAL/SUPERSEDED وليس شرط قبول نشطاً. المرجع docs/notification-policy.md وevidence/phase-06/scope-decision.md. لا staging/production أو Phase9؛ actual aliases/CDN تبقى UNKNOWN.

> PM2026-11-09: Phase7 APPROVED; Phase8 T103-T112 authorized with feature-branch commits/pushes and isolated CI. STOP at Phase8 PM review. No Phase9, deployment, real data/provider or shared-environment action.

## Core Principles

### I. Security First

Authentication, authorization and tenant isolation MUST take priority over feature
delivery. Cross-tenant data exposure MUST block release. An unresolved authentication
or authorization bypass MUST NOT be accepted as a shortcut to readiness. Changes
affecting school-owned data MUST cover authorized and negative access cases for
lists, individual records, mutations, exports, relationships and downloads.

### II. Laravel Is the Single Source of Truth

Laravel services/domain logic MUST own business rules; Vue and Flutter are clients.
Clients MUST NOT duplicate authorization, tenant, subscription, pricing, approval,
academic-calendar or workflow rules. API controllers MUST reuse shared services
and policies, extracting rules from web controllers when needed while preserving
approved behavior. Client validation is convenience only; Laravel is authoritative.

### III. Server-Resolved Tenant Context

Laravel MUST derive and verify tenant context from the authenticated user and
authorized server-side relationships. Client-supplied school_id, route IDs, query
parameters, imported IDs or equivalent selectors MUST NOT grant access.
Legitimate multi-school selection, including supervisor selection, MUST undergo
server authorization on every request. Child IDs MUST belong to both the authorized
school and parent record. User assignment alone MUST NOT replace current school
scope, including after a staff transfer.

### IV. Backward Compatibility

Introducing mobile APIs MUST preserve existing Vue/Inertia behavior and integrations
unless an approved specification explicitly changes them. Shared-service changes
MUST retain relevant web regression coverage. Changes to flags, data formats,
migrations or rollout behavior MUST be documented. Mobile APIs MUST NOT silently
repurpose web routes, break consumers or duplicate business logic.

### V. Stable Mobile API Contract

Mobile-facing APIs MUST document predictable success, validation, authentication,
authorization, not-found, conflict, rate-limit, server-error and pagination
contracts. Specify HTTP codes, field types, nullability, date/time formats,
machine-readable status/error values and bounded list behavior. Clients MUST NOT
derive business state or authorization from UI-specific/localized strings.
Contract changes MUST include compatibility review and executable contract tests.
An API-looking path or JSON output alone does not establish readiness.

### VI. Mobile Authentication Security

Native authentication MUST use an appropriate server-controlled Laravel mechanism.
Secure issuance, expiry policy, revocation, current-device logout and all-device
invalidation MUST have documented semantics. Account activity, approval, roles,
permissions and school context MUST be enforced server-side on each protected
request, including previously issued tokens. Tokens MUST NOT retain access after
relevant rejection, disablement or authorization changes. Clients MUST store tokens
securely and MUST NOT retain passwords or OTP codes insecurely.

### VII. Attachment Security

Private or tenant-scoped attachments MUST NOT be accessible through public,
predictable or merely unguessable URLs. Every sensitive download MUST authenticate
the caller and authorize the school, record and operation. Upload content types,
sizes and permitted formats MUST be validated server-side. Legacy public files,
fallback paths, previews, exports and caches MUST be included in a file-security
review; a protected new download route alone does not secure existing public files.

### VIII. Supported Notification Channels

WhatsApp is permanently removed from active scope by PM decision2026-11-09. No WhatsApp/n8n/Meta sends, provider configuration, templates or delivery tests are permitted. Supported product channels are in-app/application and web; push only where an existing approved requirement applies. Keep Laravel authoritative and current authorization; do not invent alternative phone-verification behavior. Historical code/data may remain inert for compatibility. Old WhatsApp/control/OTP/kill-switch requirements are superseded with explicit test accounting and unrelated generic coverage retained. No broad destructive cleanup or live environment action follows.

### IX. Tests Are Release Gates

A backend feature MUST NOT be declared mobile-ready until relevant automated tests
execute and pass. P0 authentication, authorization and tenant-isolation behavior
MUST have positive and negative automated coverage. Tests MUST use isolated local
configuration/data and MUST NOT target production or real messaging providers.
Missing runtimes, failed tests, skipped mandatory checks and static-only review
MUST be reported as verification gaps, never passing evidence. Distinguish static
test discovery from executed case counts and outcomes.

### X. No Production Changes During Development

Development MUST target local code and explicitly approved staging only.
edaratek.com and its production database, environment, files, queues, integrations,
DNS and SSL MUST NOT be modified during development. Local/staging approval MUST
NOT imply production authorization. A later production release requires its own
approved release phase and separate explicit production approval; none is granted
by this constitution update.

### XI. Phase-Level Implementation

After explicit phase approval, tasks inside the CURRENT approved phase may execute
in dependency order without repeated permission for routine authorized tasks.
Execution MUST stop at every phase boundary for PM review. Subsequent phases,
scope expansion and out-of-phase dependencies MUST NOT be assumed approved.
Independent action gates remain applicable inside an approved phase.

### XII. Explicit Approval Gates

Separate explicit user approval MUST precede each commit, push, merge, deployment,
production change, destructive database operation and production integration
activation. Real WhatsApp/OTP sends also require explicit approval.
Approval for one action MUST NOT imply another or override a current prohibition.
Constitution, readiness and phase gates MUST be satisfied before dependent work.
Prepare reviewable work within scope before requesting gated actions; do not ask
for permission for every reversible task already authorized.

### XIII. No Secret Exposure

Passwords, API/bearer tokens, Meta tokens, HMAC secrets, private keys and environment
secrets MUST NOT be printed, committed or copied into specs, logs, screenshots,
fixtures or client bundles. Report only secret type, location and risk, suppressing
values. Personal school/student data MUST NOT enter specifications or development
fixtures. Errors MUST NOT reveal credentials, stack traces or provider internals.
Cached configuration MUST be treated as potentially secret-bearing.

### XIV. Audit-Driven Work

The Phase 0 Mobile Backend Audit is an evidence baseline. Future specifications,
plans and implementation records MUST trace relevant findings/test gaps to code,
decisions and executed verification. Mentioning a finding here does not fix it.
All CRITICAL findings and P0 test gaps MUST be resolved before mobile readiness.
In-scope HIGH security findings MUST be fixed and tested or disproved by evidence
reviewed by the PM. Cross-tenant exposure or authentication bypass cannot be waived
into readiness. Unverified behavior MUST remain UNCONFIRMED. New evidence MUST
refine the baseline without silently erasing its original findings or assumptions.

## Project Constraints and Audit Evidence

### Existing Project Constraints

- The authoritative repository is D:\edaratek\public_html. Do not inspect or use
  E:\edaratek-gethup\infinity-edaratek without explicit instruction.
- Preserve Laravel 12 / PHP 8.2+, Vue 3, Inertia, Vite and existing Tailwind.
  React/JSX files are legacy references, not the default for new web UI.
  Reuse layouts, components and services; justify dependencies and migrations.
- Preserve Arabic, UTF-8, RTL, mobile layouts and light/dark support. Review
  validation, empty states and permission failures. Write feature documentation
  in Arabic unless requested otherwise.
- Reuse calendar, planning, exam-validation, subscription, approval, delegation,
  notification and attachment services. Prefer server aggregates for reports and
  dashboards over transferring large raw datasets.
- Use CLAUDE.md and PROJECT_DETAILS.md for orientation, verifying claims in code.
  Do not invent business rules or treat seeded roles as completed product modules.

### Authoritative Phase 0 Baseline

Report: EDARATEK_MOBILE_BACKEND_AUDIT, dated 2026-11-07.
Evidence locator:
C:\Users\HP\.codex\artifacts\edaratek-phase0-20261107\EDARATEK_MOBILE_BACKEND_AUDIT.md

This local path is not a portable tracked specification. If unavailable to a later
contributor, obtain the approved report; do not reconstruct evidence by guessing.
Sections 32 and 35 contain the findings/test gaps below; Appendices A-E contain the
route, page, service and test inventories. The verdict is MOBILE_BACKEND_NOT_READY.
The audit is static: 97 test files and 508 test methods were discovered, but zero
tests executed because PHP was unavailable. Live production state was not verified.
This amendment does not resolve those gaps or change that verdict.

### Extracted Security Findings

These are evidence records for later specifications, not tasks or authorization
to implement fixes in the foundation phase.

CRITICAL: none confirmed in the audit; this is not proof of absence of vulnerabilities.

| ID | Severity | Baseline observation and qualification |
|---|---|---|
| H01 | HIGH | Staff reply attachments use the public disk; known URLs can bypass school/record authorization. Actual private contents were not inspected. |
| H02 | HIGH | Staff subtask list filters by assigned user, not current school. Transfers with retained historical tasks can expose the previous school's data. |
| H03 | HIGH | SuperAdminSeeder contains fixed privileged credentials; updateOrCreate can create/reset admins. Live use was not confirmed; values remain suppressed. |
| H04 | HIGH | N8nWhatsAppClient::dispatch and existing jobs do not enforce enabled=false at dispatch; prior queued jobs are a conditional outbound-send risk. |
| H05 | HIGH | Local env/cached config combine production mode with debug=true. Exposure depends on deployment; live production settings are UNCONFIRMED. |
| M01 | MEDIUM | Leave download lacks the action-specific leave permission; broad middleware can admit a calendar-only caller in the same school. |
| M02 | MEDIUM | Quick-setup lacks active-school middleware and its manager branch can pass before checking school activity. |
| M03 | MEDIUM | Approval middleware redirects/logs out the web guard; native tokens need equivalent per-request account-state enforcement. |
| M04 | MEDIUM | primaryRole, legacy/Spatie roles and redirect precedence can disagree; multi-role approval needs tests, not an assumed exploit. |
| M05 | MEDIUM | Privileged fillable fields, guarded=[] domain models and raw JSON create extraction/contract risks. No full-request mass-assignment exploit was confirmed. |
| M06 | MEDIUM | Forgot-password distinguishes outcomes without an explicit route throttle, creating enumeration/abuse risk. |
| M07 | MEDIUM | Legacy public leave-file fallback and differing upload-hardening paths need a uniform private-file policy. |
| L01 | LOW | Some read helpers repair roles, seed leave defaults or create calendar settings; GET is not automatically side-effect-free. |
| L02 | LOW | Unread count covers the loaded bounded list; notification actions reference web routes. |
| L03 | LOW | Pagination, types, dates/statuses and request identifiers lack a uniform stable contract. |
| L04 | LOW | OTP send keys include IP and cooldown lacks user-level locking; account-global budgets/concurrency need tests. |

### Extracted Missing Tests: P0

The following behavior MUST receive executed automated coverage before its relevant
mobile-readiness gate can pass:

- Token issuance rejects pending, rejected and inactive accounts.
- Token expiry, current-device revocation and all-device revocation work.
- Compromised/disabled-account tokens cannot retain access.
- Every v1 route enforces cross-school list/show/create/update/delete/attachment
  boundaries.
- Staff-transfer lists cannot expose another school's historical tasks.
- Foreign related-record IDs cannot bypass school or parent ownership.
- Manager impersonation and client school-context injection are rejected.
- Non-admin callers cannot escalate roles/permissions.
- Mixed/multiple-role approval semantics enforce the documented account policy.
- Private files cannot be retrieved through public or legacy URLs.
- Admin seeding cannot introduce fixed privileged credentials or unsafe resets.
- Supported app/web notifications preserve authorization and data; the permanently
  retired channel has no executable outbound path. Old delivery gates are superseded.

### Extracted Missing Tests: P1

- Capability-specific leave-attachment download denial/allowance.
- Suspended-school quick-setup behavior.
- Exact success/error schemas, field types and nullability.
- Authentication/authorization/not-found/conflict/validation/rate-limit/server
  responses, including 401/403/404/409/422/429/500.
- Pagination defaults, invalid values and caps.
- Bearer-authenticated multipart upload, MIME/size checks and private downloads.
- OTP unavailable state, cooldown, global budgets, concurrency and phone replacement.
- Notification ownership, unread count, read-all and authorized deep links.
- Subscription entitlements and pricing/proration round trips.
- Dashboard aggregate parity with existing Laravel results.

### Extracted Missing Tests: P2

- Large synthetic fixtures, query budgets and N+1 checks.
- Arabic output, date-only fields and timezone/DST boundaries.
- Foreground/background notification polling behavior.
- Additive contract compatibility across clients/releases.
- Report exports and certificate rendering.
- Push-token reassignment and logout cleanup when push is implemented.
- Accessibility and mobile retry/cancellation behavior.

The audit also records P1 unbounded optional pagination, nested message/attachment
payload and export aggregation risks; and P2 catalog, capability-query, dashboard
and quick-setup query-budget risks. Measurements were not performed. Later plans
MUST assess these risks when the affected features enter scope.

## Development Workflow and Readiness Gates

1. Confirm repository identity, branch, HEAD and existing work before mutations.
   Preserve unrelated work; do not clean/reset/stash it implicitly.
2. Read the relevant audit evidence, implementation and tests. Distinguish existing
   behavior, requested changes and unresolved questions. Use bounded acceptance
   criteria and evidence references.
3. Use Constitution -> Specify -> Clarify -> Plan -> Checklist -> Tasks -> Analyze
   -> Implement -> Converge/Review only as authorized by the user/current phase.
   Describing the workflow is not permission to run subsequent commands.
4. Every later plan's Constitution Check MUST cover the 14 principles, audit/test
   traceability, tenant/record scope, account/role state, API compatibility, file
   privacy, outbound-send safety, secrets, Arabic/RTL, test isolation, phase scope
   and separate action approvals. Recheck after design and before phase completion.
5. Execute only the approved phase in dependency order. Record changed files,
   command results, tests run/passed/failed/skipped and verification gaps.
   Stop for PM review at its boundary; do not invoke the next phase automatically.
6. Run focused PHP tests and relevant regression/P0 coverage for changed behavior.
   Run npm run build for frontend changes. Documentation-only changes require
   document/integration validation, not an application build or a claim that
   backend release tests passed.
7. A readiness declaration MUST cite relevant/P0 passing executed tests, blocker
   resolutions and PM gate review. Until then retain the audit's not-ready status.
   Backend readiness and Flutter implementation are separate scopes.

Historical foundation-only scope was superseded by later explicit PM approvals.
Current approval2026-11-09 accepts Phase7 and authorizes Phase8 T103-T112 with permanent WhatsApp removal,
and feature-branch commits/pushes using the established isolated CI workflow.
Stop at Phase8 PM review. No Phase9, Flutter, main push/merge, staging/production,
real file migration, provider configuration or real sends are authorized.

## Governance

This constitution governs Edaratek specifications, plans, execution and readiness
reviews. Apply explicit user authorization and current scope when interpreting
gates. Amendments MUST record the reason, affected principles, version, amendment
date and downstream impact; preserve applicable prior rules; and receive PM review
before dependent work proceeds. An amendment request does not authorize a commit
or any other independently gated action.

Use semantic versioning: MAJOR for incompatible removals/redefinitions, MINOR for
new principles/sections or materially expanded guidance, PATCH for non-semantic
clarifications. Preserve the original ratification date. Review dependent
templates/commands for compatibility; they read this constitution at runtime and
MUST NOT be edited by the constitution command. Unrelated feature work is deferred
to its separately authorized workflow.

Version 1.1.0 adds the requested mobile-readiness principles, extracted finding/test
baseline and phase/approval gates to 1.0.0. It preserves the stack, Arabic experience,
evidence and proportionate-verification requirements. The temporary Sync Impact
Report above MUST be removed before a future separately approved commit.

**Version**: 2.0.0 | **Ratified**: 2026-10-31 | **Last Amended**: 2026-11-09

Amendment2.0.0: explicit PM removal redefines PrincipleVIII (MAJOR). Prior WhatsApp findings remain historical evidence, with active scope superseded by docs/notification-policy.md. All unrelated security/privacy/environment gates remain. Current authorized execution is Phase8 only after explicit Phase7 approval, stopping at Phase8 PM review. No approval activates the removed channel within this scope.
