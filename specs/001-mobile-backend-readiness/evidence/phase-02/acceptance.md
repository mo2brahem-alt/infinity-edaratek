# Phase 2 technical acceptance gate

Date: 2026-11-08 (Africa/Cairo). Technical reviewer: Codex, under the explicit PM delegation recorded for this workflow. This is not a human PM acceptance of Phase 3 and is not a mobile-backend readiness decision.

Authoritative source: `e448618bffe70e10352b52bf20d3ffc6f43b14f5`.
Authoritative CI run/job: 42486583770 / 128749676130.

## Gate evidence

- T013: Sanctum 4.3.0 guard, token model, `HasApiTokens`, exact expiry support, and stock-cookie behavior were inspected. The custom native Bearer path is documented in `auth-design.md`.
- T014–T016 and T025: 16 real-PAT/MySQL feature cases pass, including login, finite expiry boundary, invalid credentials, fake-clock limit recovery, cookie/query/conflict rejection, account-state changes, deletion, revocation, repeated logout denial, and web-session preservation.
- T017/T026: standard missing PAT migration, User trait, separate native expiry configuration, and forward/rollback/reapply/repeat rehearsal pass in disposable MySQL only.
- T018–T024: account role union/evaluation, exact Bearer authentication, finite native issuance, request validation/limits, minimal resources, owner-native device lifecycle, additive `/api/v1` routes, and JSON error envelopes are implemented and covered.
- T027: focused subset is 16/16. Full suite is 539/538/1/0 errors/0 skips. The sole failure is the pre-existing Student Excel import failure; it is not hidden, waived, or changed.
- T028: CI emitted `MOBILE_AUTH_FOUNDATION_PASS=PASS`, `mobile_test_cases=16`, `mobile_test_failures=0`, `full_suite_failure_count=1`, and `known_preexisting_regression_only=true`.

CHK011–CHK018 are accepted for the Phase 2 scope by the documented source and executed results. The later account-matrix, tenant, file, WhatsApp, debug/error-contract, and final-readiness checks remain open.

## Decision

`MOBILE_AUTH_FOUNDATION_PASS=PASS`

`MOBILE_BACKEND_NOT_READY`

STOP: no Phase 3 implementation, main merge, deployment, shared database operation, provider activation, or real message/send action is authorized or performed.
