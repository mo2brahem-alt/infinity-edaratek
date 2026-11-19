# EDARATEK_GOOGLE_SIGNIN_IMPLEMENTATION_REPORT

Date: 2026-11-19 (Africa/Cairo).
Authority: PM's attached **GOOGLE SIGN-IN LOCAL IMPLEMENTATION** authorization
and instruction to continue. Implementation and validation are local only.
Contract: [mobile-google-auth-v1.md](mobile-google-auth-v1.md), extending the
accepted native Mobile API without replacing password authentication.

```text
PHP_COMPATIBILITY=PASS; declared PHP ^8.2 unchanged; tested on portable PHP 8.2.35
FIREBASE_VERIFIER=IMPLEMENTED_AND_FAKE_TESTED; no live Firebase UAT
IDENTITY_MODEL=EXPLICIT_EXISTING_USER_LINK; unique ownership and transactional mutations
MIGRATIONS_CREATED=1; external_identities plus nullable Sanctum provenance
MIGRATIONS_EXECUTED_ON_SERVERS=NO
GOOGLE_LOGIN_API=POST /api/v1/auth/google/login; implemented locally
LINK_STATUS_API=GET /api/v1/auth/google/link; implemented locally
LINK_API=PUT /api/v1/auth/google/link; native session plus password and fresh Google proof
UNLINK_API=DELETE /api/v1/auth/google/link; selective Google-session revocation
SECURITY_TESTS=PASS; 34 new backend tests plus client security/regression coverage
FLUTTER_GOOGLE_BUTTON=IMPLEMENTED; Arabic/English, RTL/LTR, responsive and accessible
FLUTTER_ACCOUNT_LINKING=IMPLEMENTED; status, explicit selection/confirmation, password, unlink
LARAVEL_TESTS=183/183 supported selected cases PASS; 3050 assertions; four existing Linux worker cases excluded
FLUTTER_TESTS=196/196 PASS; 172 existing plus 24 new
FLUTTER_ANALYZE=PASS; no issues
ANDROID_DEBUG_BUILD=PASS; APK built; no live Google testing or emulator installation
REGRESSION_TESTS=149 existing backend cases and 172 existing Flutter cases PASS within the selected suites
FILES_CHANGED=29 backend and 30 Flutter files relative to task preflight; see source inventory
DEPENDENCY_CHANGES=2 direct Composer requirements, 21 added locked packages, zero existing package version changes; no Flutter dependency changes
REMAINING_BLOCKERS=Baseline dependency advisories; Linux worker validation; separately approved deployment/configuration and dedicated-account live UAT
COMMIT=NO
PUSH=NO
MERGE=NO
DEPLOYMENT=NO
PRODUCTION_TOUCHED=NO
FINAL_STATUS=GOOGLE_SIGNIN_AWAITING_PM_REVIEW
```

## Implemented security boundary

- RS256 signature verification uses `firebase/php-jwt`, pinned Google public key
  retrieval, bounded caching and serialized/rate-limited key refresh. No JWT key
  URL is trusted. Cryptographic tests generate local RSA fixtures; HTTP is faked.
- Audience, issuer, subject, times, maximum 300-second authentication age,
  verified email, Google provider UID and unexpected tenant are checked. Kreait
  Auth metadata verifies current user/provider state and Firebase revocation
  cutoff. Deleted/disabled users and verification outages fail closed.
- Google email is never an account lookup or linking key. No Laravel user is
  created. Current Laravel eligibility and school/permission policies remain
  authoritative. Native session marker, ability, device and expiry are reused.
- Explicit linking requires an eligible native session, current password and
  fresh Google proof. Owner-first transactions, binary unique indexes and retry
  handling enforce single ownership/idempotency across concurrent requests.
- Unlink deletes only Sanctum sessions attributed to that identity. Password
  sessions remain. Migration rollback removes Google-attributed sessions before
  removing provenance. Real two-process tests exercise ownership, issue/unlink
  races and a shared rate-limit budget against disposable local MySQL.
- Firebase revocation blocks new exchanges. Previously issued Sanctum sessions
  retain their independent Laravel lifecycle; no continuous revocation bridge
  is claimed or implemented.
- Flutter shares authentication single-flight, secure native-token persistence,
  restoration, expiry and stale-response protection. Google/Firebase proofs
  remain transient and never become Laravel credentials. SDK cleanup is
  attempted after selection and logout. Link errors do not invalidate a valid
  native session; unlink success or uncertain transport revalidates `/me`.
- The Google button uses official unmodified artwork and a scoped Google Sans
  font, with bundled license/provenance. Existing login layout tests cover
  Arabic/English, 320/411 dp, keyboard insets, 2x text and semantics.

## Validation evidence and limits

Evidence is retained under the ignored Flutter path
`build/google-auth-implementation/`; it is not a commit candidate.

| Check | Result / evidence |
| --- | --- |
| Backend supported regression/security selection | `backend-supported-tests.log`, `backend-supported-junit.xml`: 183 tests, 3050 assertions, no failures/errors/skips in this selection |
| New backend cases | Verifier 21, API/session contract 10, concurrency/rollback 3 |
| Existing backend regressions | Native authentication/devices/account state, approval matrix, web compatibility, Phase7 and notification channel policy: 149 cases |
| Flutter | `flutter-tests.log`: 196 pass; `analyze.log`: no issues |
| Android | `android-build.log`: debug APK built successfully |
| Composer | `composer-validate.log`: strict validation; `composer-platform.log`: production platform requirements pass |
| Change protection | `flutter-before.json`, backup ZIP and `final-flutter-scope.json` |
| Security review | `final-security-review.json`: paths/counts only; no credential values |

The four pre-existing `MobileRatePolicyTest` worker datasets for independent
process shared login/read/session/reset budgets could not pass their original
Linux-only worker environment guard on Windows. They are excluded from the
183-case result and must be rerun in the approved Linux lane. Their guards,
fixtures and existing tests were not weakened or rewritten. New Google tests
independently exercise real subprocess concurrency on Windows/MySQL; this does
not substitute for those four original regressions or the entire Laravel suite.

Tests ran in an ignored backend copy without the source `.env` or server
credentials. A task-owned MySQL instance was loopback-only with a randomly named
disposable database. Its database was removed and its server shut down after
validation. The task-created legacy metrics scratch file/directory was removed;
safe query metrics remain in ignored evidence. Portable QA tools were retained
locally; no global runtime, server PHP or installed database service was changed.

No real Google sign-in, real-account login/link/unlink, Firebase Console change,
test-server request/mutation, server migration, deployment or production action
was performed. The debug APK is a build result, not live integration acceptance.

## Dependency review and deployment gates

The PHP requirement remains `^8.2`. Direct additions are
`kreait/firebase-php:^7.24.1` (locked 7.24.1) and
`firebase/php-jwt:^7.2.2` (locked v7.2.2). The selected tree adds 21 packages and
changes no previously locked version. The source lock exactly matches the
isolated installation used for tests. The newer PHP-8.3-only SDK line was not
used. Flutter dependency versions and `pubspec.lock` are unchanged from preflight;
`pubspec.yaml` adds only branding assets/font declarations.

Composer audit reports **55 advisories across 12 unchanged baseline packages**:
Guzzle HTTP/PSR7, Laravel Framework, League CommonMark/Flysystem and Symfony
HTTP Foundation/Kernel, Mailer, MIME, Intl IDN polyfill, Routing and YAML.
No added package was flagged in this audit. This is an unresolved security gate,
not a clean dependency-security result. No unrelated upgrades were performed.
The machine-readable advisory details are retained in `composer-audit.json`.

Before enabling on any server, obtain separate approval for baseline advisory
remediation/review, the original Linux regression lane, deployment/runtime
validation, dependency installation, schema migration and private credentials.
`FIREBASE_GOOGLE_ENABLED` defaults to false. Credentials must be operator-managed
outside repositories and public document roots; none was downloaded or bundled.
Then authorize dedicated linked/unlinked/blocked-account UAT, real SDK selection,
proof exchange, linking, unlinking, session restoration/revocation and Arabic/
English visual review on Android. Do not point the enabled client at an
unprepared backend. No deployment or UAT is authorized by this report.

## Change scope and preservation

[google-signin-source-files.txt](google-signin-source-files.txt) lists this task's
29 backend and 30 Flutter files. Backend preflight was clean; Flutter had existing
uncommitted Phase3/Firebase/UI work. Against its 103-file hash snapshot, this task
modified 17 existing Flutter files, added 13 files and deleted none. Existing
Android configuration, Firebase options/bootstrap, FCM and debug FID components,
shell/theme and native secure-storage code remain byte-for-byte preserved.
The identity adapter changes are limited to display-only selected account label;
the original Firebase SDK flow is retained.

Both indexes remain empty and branches remain `main`. Backend HEAD remains
`bcd59be7c132f8b1a4be5bd7dd80751161204a3d`; Flutter HEAD remains
`7c7faf22d72898110e08268eb2db576b040024b2`. Working trees intentionally contain
uncommitted work. Required new source/tests/localization/branding files are
Git-visible; builds, logs, portable test copies and APKs are ignored. A targeted
changed-file secret scan found no private keys, service-account documents,
literal JWTs or GitHub/AWS credential patterns; source/configuration review also
found no added live credentials. Pattern scanning is not a credential audit of
unrelated historical files.

Notification inbox and server FCM sending were not implemented. Existing
password/session behavior, web routes and notification workflows were preserved.
Stop here for PM review: **GOOGLE_SIGNIN_AWAITING_PM_REVIEW**.
