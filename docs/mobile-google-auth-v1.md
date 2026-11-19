# Native Google authentication extension (local implementation)

Authority: the PM's Google Sign-In integration proposal and local implementation
authorization in the current conversation, 2026-11-19 (Africa/Cairo). This extends
`mobile-api-v1.md`; existing password/session/resource behavior is preserved.
This document does not authorize deployment, server migrations or live UAT.

## Operations

All paths start with `/api/v1`. JSON requests, existing native error envelope,
generated request IDs, sanitized messages and no-store responses apply.

| Method/path | Input and authority | Success |
| --- | --- | --- |
| POST `/auth/google/login` | `firebase_id_token`, `device_uuid`, `device_name`; no email/user/role/school selector | 200 existing TokenIssuance |
| GET `/auth/google/link` | Eligible native Sanctum session; no request fields | 200 link status |
| PUT `/auth/google/link` | Eligible native session, `current_password`, `firebase_id_token` | 200 link status; no new session |
| DELETE `/auth/google/link` | Eligible native session, JSON `current_password` | 204 empty |

TokenIssuance retains `access_token`, `token_type:Bearer`, `expires_at`, and
`user:CurrentUser`. Link status is `data:{linked:bool,provider:"google.com",
linked_at:UTC-instant|null}`; the instant is present exactly when linked.
External identity IDs and provider payloads are not returned. Unknown fields
are rejected. Firebase proof is bounded to 16 KiB; mutation bodies to 20 KiB.
Device rules remain UUID and nonempty device name up to 80 characters.

## Verification and linking

The expected project is `my-project-79313-1716224806958`; the issuer is
`https://securetoken.google.com/my-project-79313-1716224806958`.
Maintained `firebase/php-jwt` verifies RS256 signatures against public keys from
the fixed Google secure-token certificate URL. Algorithm and key ID are
allowlisted; token-supplied key URLs are ignored. Public keys are cached using
bounded Google cache lifetime, with serialized refresh and a 60-second refresh
budget against unknown-key abuse. Key/cache/network failures deny verification.

The strict verifier checks audience, issuer, subject, numeric times, expiration,
issued-at, authentication age at most 300 seconds, verified email, Google-only
sign-in provider and a single Google provider UID. Unexpected Firebase tenant
claims are rejected. Kreait's server Auth lookup supplies current user metadata;
deleted/disabled users, provider UID mismatch, unverified current user and
authentication older than `tokensValidAfterTime` are rejected. Firebase emulator
mode is rejected. Refreshing a token alone cannot reset authentication recency.

Only an existing explicit mapping of project + Firebase UID + Google UID can
resolve a Laravel user. Google email is never a lookup/linking key. The server
never creates Laravel users or copies Google roles/tenant claims. Current
Laravel eligibility, actual roles and school/resource policies remain authority.

Linking requires both the current native session and current password proof,
plus fresh Google proof and explicit client confirmation. A different Google
email is allowed through that dual-proof operation. Identical links are
idempotent; a different existing mapping or foreign ownership returns conflict.
Changing identities requires explicit unlink and fresh linking.

Transactions lock the Laravel owner before the identity record. Binary unique
indexes enforce Firebase ownership, Google ownership and one Google link per
user/project. Login rechecks the mapping under the same owner lock, serializing
issuance against unlink. Audit payloads contain only server-controlled action,
provider and internal entity/user references; no proof, password or provider
profile is copied to logs/audit payloads.

## Session and revocation policy

Google login uses the existing NativeTokenService, native marker/ability,
installation UUID and configured finite absolute expiry (default 1440 minutes).
There is no refresh token or implicit renewal. `/me` remains the restoration
authority and still supplies no expiry. Existing DTOs remain unchanged.

Google-issued Sanctum rows reference `external_identities`. Unlink cascades only
that link's sessions; password and legacy sessions with null provenance remain.
User deletion cascades linked identities/Google sessions without blocking
existing user deletion. Migration rollback first deletes attributed Google
sessions before removing provenance; it preserves password sessions.

Firebase revocation/deletion/disablement blocks new exchanges. Already-issued
Sanctum sessions retain their independent Laravel expiry/eligibility/revocation
rules. No Firebase-to-Sanctum continuous revocation bridge is implemented.
Logout removes the current Sanctum session and clears client identity SDK state;
it does not unlink the account, delete Firebase users or revoke Google consent.

## Admission and errors

- Google login shares the existing 30/IP/minute login budget, reserved before
  validation and external verification.
- After cryptographic claim validation, 5 verified-proof attempts per identity
  per minute precede expensive user lookup.
- Completed verification also reserves the verified identity and shared Laravel
  account login budgets (5/minute). Password/Google switching cannot bypass the
  account budget. Limiter identities are HMAC keys.
- Link/unlink retain native session mutation admission and add password
  reauthentication limits of 5/user/minute and 30/IP/minute. Status uses native
  read admission. Cache/lock failures do not allow admission.

| Code | HTTP and meaning |
| --- | --- |
| GOOGLE_IDENTITY_INVALID | 401 on login; 403 on link, preserving a valid native session |
| GOOGLE_ACCOUNT_NOT_LINKED | 403; valid Google proof without an explicit mapping |
| REAUTHENTICATION_FAILED | 403; current password proof rejected |
| IDENTITY_REAUTHENTICATION_REQUIRED | 403; Google authentication is too old |
| IDENTITY_LINK_CONFLICT | 409; no foreign account details |
| AUTH_PROVIDER_UNAVAILABLE | 503; verification/configuration unavailable |

Existing UNAUTHENTICATED, account restriction, 422, 429 and internal error
conventions remain. RATE_LIMITED retains matching positive `Retry-After` and
`retry_after`; mutations are not automatically retried.

## Configuration and activation boundary

`FIREBASE_GOOGLE_ENABLED` defaults to false. The operator must configure
`FIREBASE_GOOGLE_CREDENTIALS_FILE` as an absolute private service-account file
outside checkout/document roots and every Git checkout. The implementation
rejects paths in the application checkout and detected Git ancestors, including
resolved/lexical application paths. Server aliases, filesystem permissions, IAM
and deployment runtime still require operator validation. No credential file is
included or downloaded by this implementation.

Schema deployment, dependency security review, credential configuration and
feature activation require separate PM approval. Link status requires the new
schema; unlink remains possible without contacting Firebase once deployed.
Do not enable clients against an unprepared backend.
