# Security Protocols: Private Files and Outbound Admission

> **قرار PM نافذ 2026-11-09:** WhatsApp خارج المشروع نهائياً. الإشعارات app/web فقط، وpush حيث يوجد مطلب معتمد قائم؛ لا delivery tests أو provider configuration. أي نص WhatsApp/OTP/control/kill-switch قديم أدناه HISTORICAL/SUPERSEDED وليس شرط قبول نشطاً. المرجع docs/notification-policy.md وevidence/phase-06/scope-decision.md. لا staging/production أو Phase9؛ actual aliases/CDN تبقى UNKNOWN.

> PM2026-11-09: Phase7 APPROVED; Phase8 T103-T112 authorized with feature-branch commits/pushes and isolated CI. STOP at Phase8 PM review. No Phase9, deployment, real data/provider or shared-environment action.

Design-only contracts; no actual files/messages moved or processed.

## Private-file protocol

Classify INTENTIONALLY_PUBLIC_ASSET versus AUTHORIZED_PRIVATE_ATTACHMENT by purpose/parent policy, not old disk/is_private alone. Include tickets, leave, institutional/student/user/admin documents, soft-deleted references and sensitive orphan paths. Public logos/branding stay public. Ambiguous ownership blocks completion and is quarantined for review.

1. Prepare authorized parent-aware protected reads and private new writes; preserve legacy web serializers by pointing them to authorized endpoints. Institutional module handler must explicitly support ticket policy; no blanket module bypass. Leave download requires operation permission.
2. Inventory only authorized environment/fixtures. Durable manifest identity includes source metadata, size/hash and schema version. Bound batches; record ownership/race conflicts without leaking paths in logs.
3. Copy to private destination, verify checksum/size, reuse already verified bytes on retry. Do not mark copied if incomplete. Coordinate concurrent uploads/deletes with record locking or a controlled migration window.
4. CAS metadata switch under short DB transaction only if source matches inventoried record. Preserve bytes and manifest on failure, quarantine conflicts. New protected endpoint remains authoritative.
5. Revoke every anonymous source: /storage, /media-files, previews/fallbacks, physical public copies, storage symlink targets, direct static server aliases and known proxy/CDN caches. Application route denial alone is insufficient. Preserve original bytes in a private archive when necessary; deletion/move of actual data needs separate authorization. No sensitive public compatibility fallback. Public legacy URL may return404; authentication is performed at the new endpoint, not by guessing URL.
6. Verify anonymous denial at Laravel AND representative static HTTP serving layer, current authorized download and cross-tenant denial; then mark verified. Cached public response already delivered to a browser cannot be recalled; document historical exposure and prevent new delivery rather than claiming erasure.

States: inventoried→copied_verified→metadata_switched→public_revoked→verified. Interruption resumes from last proven state. Completion requires all records and aliases proved, no ambiguous paths. Rollback never restores public sensitive files/fallback: retain private metadata/bytes and denial, resume repair. Rehearse duplicate batches, conflicting paths, missing bytes, large fixture counts, checksum mismatch, mid-switch crash, static copied-directory case, symlink case, cache headers and intended logo access. No signed/public URL is accepted as authorization.

## Live N8N_WHATSAPP_ENABLED authority

The operational flag is mapped to one durable shared integration_controls row with that exact key, version and disabled default. This row is the current execution authority for all workers and direct dispatch; boot configuration false is additionally a hard deny. Do not model a separate unrelated enable switch. Existing .env false initializes/reconciles disabled and must never auto-enable the row. No enabling/activation is implemented or performed in this feature.

Supported disable operation obtains the same shared coordination lock used by transport admission, persists enabled=false and increments version before acknowledging completion. A future environment/config management change to N8N_WHATSAPP_ENABLED=false must invoke this live disable first (or keep gate already false), then update environment/restart as defense in depth. Merely editing .env is not the supported live operation and cannot be reported as completed disable. Test this mapping explicitly; if a future environment only permits .env editing, implementation must supply a fresh flag-read adapter or block rollout rather than claim stale config satisfies FR-027.

Transport admission under shared DB synchronization reads current row uncached. Missing/unavailable/malformed state denies; process-local cached true cannot grant admission. Client also checks config false and credentials readiness, but no credential values enter logs. Serialize final admission and disable with consistent lock order against message claims; hold critical section through bounded transport initiation (may hold until bounded HTTP return to prove ordering), so disable success is a clear barrier. Do not acquire a local-memory-only lock or perform an unlocked read followed by a delayed send. Concurrent stale enabled worker must see disabled or complete its admission before the disable barrier.

Already admitted in-flight traffic cannot be recalled; disable operation waits for earlier admission critical sections and guarantees no subsequent admission. Never claim an already initiated external request was cancelled. Bound timeouts, lock waits and safe failure behavior; verify absence of lock/DB failure bypass and avoid holding unrelated business transactions around network calls.

## Message state on disabled admission

| Existing evidence | Durable result | Queue/provider behavior |
|---|---|---|
| pending, unclaimed, no provider ID | cancelled, safe reason whatsapp_disabled, preserve idempotency key | acknowledge without retry; no send-attempt increment; zero HTTP |
| claim/processing with ambiguous outcome | uncertain, preserve claim/provider evidence | no resend; no conversion to clean unsent |
| accepted/sent/delivered/read | preserve confirmed/monotonic state | no new transport |
| failed/cancelled/uncertain | preserve terminal state | no automatic revival |

If claim is acquired by the current job before final disabled check, distinguish a proven no-transport claim owned by this execution from an ambiguous pre-existing claim using claim token/current execution evidence; either safely cancel with proof of zero transport or retain uncertain. Never discard a pre-existing claim to force resend. Gate must return a typed disabled/unknown decision, not a sent-looking success object. Callback HMAC/replay protection and claim-token validation remain unchanged; callbacks reconcile actual provider evidence without resurrecting cancelled send attempts.

Reenable does not revive cancelled/uncertain messages. A separately authorized new business event uses normal new idempotency identity. Enqueued/acknowledged jobs are never proof of sent/delivered. No feature phase runs real queues/providers in shared environments.

## Required fake-only matrix

Both OTP and notification jobs, plus direct client dispatch: queued before disable, newly queued, delayed, retries, failed-job retry, manually resumed processing, stale boot-enabled worker, false at client after true job check, missing live record, DB/lock failure, duplicate worker/claim race, disable/admission race, already accepted and ambiguous claim, reenable after cancellation. Assert HTTP::fake + preventStrayRequests, zero HTTP for all denied admissions, correct durable state/attempt counter/idempotency, no secrets in logs. Use separate connections/processes and shared disposable test control state for concurrency; array-only locks cannot prove cross-worker behavior.
