# Frozen Phase 8 fingerprint review: proposal only

The authoritative `tools/testing/phase8-scope.json` is unchanged. Approval status is **PENDING_PM_REVIEW**, with no approver recorded.

The first five records retain their prior candidate hashes. The authorized Excel correction adds a separately documented sixth record. Candidate execution is not frozen-baseline acceptance.

Original frozen manifest SHA-256: `5862f8395099ac4546ec5a4252443fb5b40ec1c76b978d0233d556702ac9df3d`.

## .github/workflows/deploy.yml

Replaces automatic main-push SSH deployment with workflow_dispatch-only test-source review handoff. Validation is a dependency; exact revision/artifact and protected reviewers are required. No server executor, service secret, migration or production target is configured.

The old hash describes the earlier Phase8 bytes. The legitimate source change above changes those bytes while retaining the stated invariants.

```text
FROZEN_SHA256=349264dca87ac571ae508f130a9d79267e67ef18a0c1e52f640633d488e2c317
PROPOSED_SHA256=2f3bbc1e7acfb1420847a78cbaec618d8c3b422c7362d059065c690d41b99a2f
```

## app/Providers/AppServiceProvider.php

Adds constructor-injected Firebase gateway/verifier bindings. Existing boot logic and providers remain. The Google proof is verified server-side; no email authorization or user provisioning is introduced.

The old hash describes the earlier Phase8 bytes. The legitimate source change above changes those bytes while retaining the stated invariants.

```text
FROZEN_SHA256=3242e8d5fd52a47da33e1a2f6ff761e47e35422aec114ecf439d1f5b0d74823f
PROPOSED_SHA256=4eb4407ca4b2b8f8963ca6f1b9f304b3a43b65575d4bf7237c9125b7fd72c49f
```

## app/Services/Mobile/NativeRateLimiter.php

Adds Google IP, verified-proof, identity and password-reauthentication admission. Password/Google share account budgets; HMAC keys, deterministic shared locks and fail-closed behavior remain.

The old hash describes the earlier Phase8 bytes. The legitimate source change above changes those bytes while retaining the stated invariants.

```text
FROZEN_SHA256=7fb53c630169d05f476b1ecca39e4715bb4c3a4e26b8872c4d03ca53dd89a62d
PROPOSED_SHA256=5843307ad0825ee5bc497655261c8841e04edaf7521b71e03a86172f15df1f17
```

## app/Support/ApiError.php

Adds six allowlisted Google errors and formatting. Existing request IDs, safe messages, field filtering, no-store and Retry-After conventions remain; no provider exception or raw token is exposed.

The old hash describes the earlier Phase8 bytes. The legitimate source change above changes those bytes while retaining the stated invariants.

```text
FROZEN_SHA256=a537e33e7195c34255aaf8a6c248c38f55b31a8dd57ff9429dd4900644a4f9f3
PROPOSED_SHA256=33ab57126a97cc3ea60a9175c080fe9250b28635ec4e3819347624bf9c50c5f8
```

## routes/api.php

Adds only the four approved Google operations with native bearer, account and throttle middleware where required. Existing native routes are retained; linking additionally requires password and fresh provider proof.

The old hash describes the earlier Phase8 bytes. The legitimate source change above changes those bytes while retaining the stated invariants.

```text
FROZEN_SHA256=5a8d29999684115f3f45042c87318dab5bf9793a430ddbc351dcc83256e0223a
PROPOSED_SHA256=c989d7aefe23829b5e5948017ada7bad0263c5f560f130cf23c1b36a33ef82c4
```

## app/Services/School/StudentImportService.php

Additional sixth proposal under current Step1 authority: changes only Query Builder whereKey(schoolId) to where(id, schoolId). The old call generated schools.key; explicit id preserves the transaction and parent-school FOR UPDATE lock. Four unchanged Excel tests now pass.

The old hash describes the earlier Phase8 bytes. The legitimate source change above changes those bytes while retaining the stated invariants.

```text
FROZEN_SHA256=fad0e59a9b87fde756bbb5b8d6caef5f8bdb7505cecb1d08f87a3272fbb69e36
PROPOSED_SHA256=08860e81c936f9d3b64b5ed7849c2437dd916ab24db5f713192ef7276a0e75fe
```

## Evidence and approval boundary

- Candidate source verification passes; release verification rejects pending PM approval with exit 29.
- The 756-case Windows selection passes, including all 34 Google and four unchanged Excel cases.
- Original concurrency/static tests and frozen acceptance files are unchanged; Linux cases remain NOT EXECUTED.
- Historical Phase8 acceptance expects the old known Excel failure and old totals; it is not relabeled as new candidate acceptance.
- The Linux candidate requires zero failures/errors/skips plus explicit mandatory case identities and the complete applicable suite.
- Exact executable hashes and reasons are in `tools/testing/security-baseline-proposal.json`. Review Ubuntu results and these diffs before any separately authorized baseline update.
