# عقد ما قبل تثبيت Phase7 — سجل تاريخي فقط

Snapshot of the preceding planned contract, after its Phase7 administrative authorization banner was updated but before T101 freeze. Not active acceptance requirements; WhatsApp portions remain superseded. Active contract: ../../contracts/mobile-api.md.

# Mobile API v1 Contract

> **قرار PM نافذ 2026-11-09:** WhatsApp خارج المشروع نهائياً. الإشعارات app/web فقط، وpush حيث يوجد مطلب معتمد قائم؛ لا delivery tests أو provider configuration. أي نص WhatsApp/OTP/control/kill-switch قديم أدناه HISTORICAL/SUPERSEDED وليس شرط قبول نشطاً. المرجع docs/notification-policy.md وevidence/phase-06/scope-decision.md. لا staging/production أو Phase8؛ actual aliases/CDN تبقى UNKNOWN.

> PM2026-11-09: Phase6 APPROVED; Phase7 T089-T102 authorized on the existing feature branch with isolated CI commits/pushes. STOP at Phase7 PM review. No Phase8 or environment/deployment action.

Planned contract, not currently implemented. Base path `/api/v1`; no production hostname or real credential examples. Existing web and /api/system,/api/school contracts remain unchanged. These foundation/task/download routes do not claim every future mobile feature is ready.

## Transport and authentication

HTTPS outside isolated local tests. JSON requests/responses UTF-8, including Arabic display strings; Accept application/json. Authenticated native routes require Authorization Bearer with valid unexpired native Sanctum PAT; no cookie/session/CSRF-cookie fallback, query token or URL secret. A simultaneous web cookie cannot override bearer identity. Token marker is authentication classification, never business permission. Login has no session mutation.

Protected request order: invalid/missing token or deleted user401; current account eligibility403; endpoint capability403; school context409 or suspended403; invisible/foreign/missing resource404; visible resource operation denied403. Limiting can preempt429. Validate malformed body/query422, malformed route ID404. Current-token logout is the sole blocked-account exception and returns no business data. Capability checks must not leak whether a foreign record exists.

## Endpoint inventory

| Method/path | Input and policy | Success |
|---|---|---|
| POST /auth/login | email:string valid email, password:string nonempty, device_uuid:UUID, device_name:string1..80. Additional privileged/tenant fields rejected. Credentials then current account eligibility | 200 data:token issuance |
| GET /me | eligible current account; school context may be null; read has no provisioning side effects | 200 data:CurrentUser |
| POST /auth/logout | valid current native token identity, even if account now business-blocked; delete only this token | 204 empty |
| GET /auth/devices | current eligible account; owner/native token scope; paginated common list parameters | 200 data:Device[] + meta/links |
| DELETE /auth/devices/{id} | eligible owner; native token lookup within owner; foreign/missing404; current may be selected | 204 empty |
| DELETE /auth/devices | eligible owner; revoke all own native tokens including current; preserve other token classes/web session | 204 empty |
| GET /staff/tasks | current staff capability, active resolved school and assignment/parent scope | 200 data:TaskSummary[] + meta/links |
| GET /staff/tasks/{subtask} | same scope then SubtaskPolicy; no global model binding before tenant scope | 200 data:TaskDetail |
| GET /attachments/{attachment}/download | resolve allowed ticket/institutional parent, current school/operation policy | 200 binary |
| GET /student-leaves/{leave}/attachments/{attachment}/download | parent-school and attachment parent consistency, leave-specific download permission | 200 binary |

No new task mutations, broad business CRUD, supervisor-selection API or native OTP UI added by this foundation. Existing affected reply/submit/upload/OTP endpoints retain their web contracts while shared security is fixed and tested. Future explicitly declared supervisor selectors must validate current authorized relation and never grant manager authority.

## Types and DTO allowlists

IDs: positive JSON integers consistent with current schema, with contract tests for actual database range; reject noninteger/out-of-range path forms. Booleans are JSON booleans, roles/capabilities are string arrays, nullable fields explicit. Arrays never change to objects when empty. Instants ISO8601 UTC with `Z`; date-only YYYY-MM-DD never timezone-converted. Any future monetary field decimal string plus currency, not binary float. Database status/priority values must be explicitly mapped from current allowed domain values in implementation contract fixtures; no translation-based machine logic.

**Token issuance data:** access_token:string (one-time plaintext), token_type:"Bearer", expires_at:instant, user:CurrentUser. Cache-Control:no-store. No refresh_token. Invalid credentials returns same401 for unknown email/wrong password, no account data.

**CurrentUser:** id:integer, name:string, email:string, roles:string[], capabilities:string[] for current authorized context, is_active:boolean, approval_required:boolean, approval_status:pending_approval|approved|rejected|null (null only where existing legitimate non-sensitive data is null), school:{id:integer,name:string}|null, phone_verified:boolean. Return only current server-owned state; no primaryRole shortcut, approval notes/actors, private school fields, OTP records, role pivots or internal permission groups. A blocked account receives error, not this payload. Phone state describes canonical current phone without exposing OTP.

**Device:** id:integer, device_name:string, created_at:instant, last_used_at:instant|null, expires_at:instant, is_current:boolean. No token/hash/abilities/claim secrets. Exclude expired sessions from active-device list; expiry remains401 even if pruning has not run. New login leaves other sessions valid. Device UUID is not an authorization key.

**TaskSummary:** id:integer, title:string, status:OPEN|IN_PROGRESS|SUBMITTED|APPROVED (existing Subtask constants), due_date:date-only|null. No transcript or foreign-school/user metadata. **TaskDetail:** same fields plus description:string|null and attachments:AttachmentMetadata[]; paginate any future message-history endpoint separately rather than embedding unbounded history. Existing state transitions remain shared Laravel service/policy decisions.

**AttachmentMetadata:** id:integer, name:string, mime_type:string, size_bytes:integer>=0, download_url:string referencing protected relative endpoint. No physical path/disk, provider data, uploader/student details unnecessary to operation or public sensitive URL. URL knowledge is never permission. Resolve filename safely for Content-Disposition; stream with server-derived MIME, nosniff, Cache-Control:private,no-store. Missing/deleted bytes404 without public fallback; interrupted unauthorized responses never contain file bytes.

## Envelopes / pagination / filters

Single success `{ "data": { ... } }`; no redundant success=true wrapper. Optional translated message only where operation merits it;204 has no content. Collection `{ "data": [], "meta": { "current_page":1,"per_page":25,"total":0,"last_page":1,"from":null,"to":null }, "links": { "first":"...","last":"...","prev":null,"next":null } }`. Stable same keys/types with or without explicit per_page.

page integer>=1; per_page integer1..100 default25; invalid/noninteger/zero/negative/overmax422. Valid page beyond last200 empty, current_page preserves requested page, from/to null, last_page max(1,ceil(total/per_page)); first/last valid page links, prev previous page when page>1, next only when page<last_page. Links preserve only validated filters and relative native path, never credentials. Scope precedes total/count/pagination.

Task filters: search optional string max100 matching title only with bound escaped LIKE semantics; status optional current allowed enum; sort one of id,-id,due_date,-due_date default -id with id tie-break and documented null-last due dates. Reject other fields/directions/invalid status422. Device list supports page/per_page only, newest created_at then id. No arbitrary school_id, assigned_to, role or raw SQL sort filter. Future filter additions must be documented/tested, no implicit get-all.

## Errors

Error object `{ "code":"VALIDATION_FAILED", "message":"translated display text", "errors":{"field":["display text"]}, "request_id":"bounded-opaque-id" }`. errors appears only when needed; clients branch on code/status, not Arabic/English prose. No data/trace/provider secret on error. request_id is generated/validated, not unrestricted reflected input.

| HTTP | Stable code | Notes |
|---|---|---|
| 401 | INVALID_CREDENTIALS | login unknown email/wrong password same result |
| 401 | UNAUTHENTICATED | missing/malformed/expired/revoked PAT/deleted user |
| 403 | ACCOUNT_PENDING_APPROVAL / ACCOUNT_REJECTED / ACCOUNT_DISABLED | spec account-state precedence; no operational user payload |
| 403 | FORBIDDEN | missing operation capability on otherwise authorized context |
| 403 | SCHOOL_SUSPENDED | school business/quick-setup denied |
| 404 | NOT_FOUND | missing/deleted/invisible/foreign record or bytes; no existence leak |
| 409 | SCHOOL_CONTEXT_REQUIRED | eligible account lacks completed school context |
| 409 | BUSINESS_CONFLICT / IMPACT_CONFIRMATION_REQUIRED | current shared domain conflict only; no new invented conflict workflow |
| 422 | VALIDATION_FAILED | field errors including invalid pagination/filter/tenant injection |
| 429 | RATE_LIMITED | Retry-After integer seconds and safe retry_after integer field |
| 500 | INTERNAL_ERROR | generic display text and request_id, no stack even debug true |

## Rate policies

Existing login baseline5 failed attempts/email+IP per60s retained; proposed additional5/account per60s and30/IP per60s to cover rotating IP/account attacks, HMAC keys rather than logging raw identity. Generic forgotten-password response independent of existence; proposed3/email per60min plus10/IP per60min. Native general read budget proposed120/min/account and300/min/IP, sensitive session mutations10/min/account. Validate legitimate concurrent devices/load and configure server-side, no immutable product promise for these proposed values.

OTP existing baseline from WhatsAppOtpService:5 sends/hour,60-second resend cooldown,10 verification requests/300seconds, and5 failed attempts per code; code is exactly6 digits. Retain these numeric ceilings while enforcing per-user/current-phone global budget atomically independent of IP, with source-IP budget supplementary. OTP business cooldown may retain documented422 with field error and retry metadata; actual network throttle429/Retry-After. Disabled integration errors retain current safe behavior; do not issue/send fake-success OTP. All limiter tests check boundary, cooldown, concurrency, IP rotation and recovery with fake clock/shared test state. Numeric tuning is a Phase2/6/7 configuration decision, not unresolved PM ambiguity.

## Compatibility and contract verification

Freeze DTO enum/nullability fixtures against actual domain code in Phase7; contract test every response/error, not raw models. Retain old web response envelopes/redirects and integration routes. Additive optional fields require tests; breaking rename/type/meaning requires v2 coexistence and announced migration. Contract current-user capabilities are advisory UI state only; every action reauthorizes. Secure native storage expectations do not create Flutter code here.
