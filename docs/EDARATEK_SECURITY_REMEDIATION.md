# EDARATEK_SECURITY_REMEDIATION_REPORT

Date: 2026-11-19. Authority: current PM **Security Remediation & Linux Gate**
instruction. Local dependencies, test infrastructure and workflow proposals only.
No server, Firebase, Git staging/history or production operation is authorized.

```text
BASELINE_PRESERVED=PASS; all 116 Flutter files unchanged; existing Google/application code preserved
PACKAGES_UPDATED=16 compatible locked updates; 0 added or removed packages
CRITICAL_BEFORE_AFTER=1 -> 0
HIGH_BEFORE_AFTER=16 -> 0 unique; 17 -> 0 raw records
MODERATE_BEFORE_AFTER=28 -> 0
LOW_BEFORE_AFTER=9 -> 0
COMPOSER_AUDIT=PASS; 55 raw / 54 unique before, 0 after; no suppression
PHP_82_COMPATIBILITY=PASS on PHP 8.2.35; requirement remains ^8.2
LARAVEL_TESTS=Focused 185/185 PASS; full practical existing selection 753/755 PASS, 1 failure and 1 Windows-only error
GOOGLE_SECURITY_TESTS=34/34 PASS within focused and broad runs
LINUX_CONCURRENCY_TESTS=NOT EXECUTED; Docker absent and WSL not installed
FROZEN_BASELINE_STATUS=UNCHANGED; explicit 5-file proposal PENDING_PM_REVIEW
DEPLOY_WORKFLOW_PROPOSAL=LOCAL ONLY; manual validated test-source handoff; no server executor
MIGRATION_REGRESSION=PASS on disposable local MySQL; Google up/down and selective token preservation
REMAINING_BLOCKERS=Linux execution; existing Excel import failure; PM baseline approval; operator/server readiness and separately authorized deployment/UAT
FILES_CHANGED=17 task-specific files; see security-remediation-source-files.txt
COMMIT=NO
PUSH=NO
MERGE=NO
DEPLOYMENT=NO
SERVER_MIGRATION=NO
PRODUCTION_TOUCHED=NO
FINAL_STATUS=SECURITY_REMEDIATION_AWAITING_PM_REVIEW
```

## Preservation and dependency changes

Preflight status, binary diffs, changed-file copies and full Git-visible file
hashes for both repositories are retained in a private local QA evidence folder
outside both repositories. The original Composer files were copied before any
change. The five previously existing files modified by this task are
`composer.json`, `composer.lock`, `tools/testing/Dockerfile`,
`tools/testing/run-tests.ps1` and `.github/workflows/deploy.yml`.
No application implementation, existing test assertion, Google API contract,
Flutter source/configuration or accepted localization work was changed.

The root constraint is now `laravel/framework:^12.68.0`, preserving Laravel 12.
An explicit `symfony/http-foundation:~7.4.23` security floor keeps that component
within Symfony 7.4 and includes the post-ReDoS whitespace-separator correction
and the remaining compatible security fixes. PHP remains `^8.2`. Other updates
were solved from the 12 affected packages using Composer's minimal-changes mode
with dependent updates; unrelated top-level requirements were retained.

| Package | Before | After |
| --- | --- | --- |
| guzzlehttp/guzzle | 7.10.0 | 7.16.3 |
| guzzlehttp/promises | 2.3.0 | 2.6.0 |
| guzzlehttp/psr7 | 2.8.0 | 2.13.4 |
| laravel/framework | 12.50.0 | 12.73.0 |
| league/commonmark | 2.8.0 | 2.10.7 |
| league/flysystem | 3.31.0 | 3.36.0 |
| nette/utils | 4.1.2 | 4.1.6 |
| symfony/http-foundation | 7.4.5 | 7.4.23 |
| symfony/http-kernel | 7.4.5 | 7.4.23 |
| symfony/mailer | 7.4.4 | 7.4.22 |
| symfony/mime | 7.4.5 | 7.4.22 |
| symfony/polyfill-intl-idn | 1.33.0 | 1.43.0 |
| symfony/polyfill-php84 | 1.33.0 | 1.43.0 |
| symfony/polyfill-php85 | 1.33.0 | 1.44.0 |
| symfony/routing | 7.4.4 | 7.4.23 |
| symfony/yaml | 7.4.1 | 7.4.23 |

No package was added or removed. The source lock matches the isolated
installation tested. Strict Composer validation and full platform requirements
pass. Fresh locked audits report no advisories or abandoned packages. There is
no advisory ignore list, security-policy bypass or disabled audit gate.

[security-advisory-resolution.json](security-advisory-resolution.json) preserves
all 55 original advisory IDs, canonical identities, classifications, source
links and before/after package versions. One High Laravel email-rule advisory
was duplicated; eight originally unclassified rows were classified from the
maintainers' notices during the preceding audit. Unique totals were Critical 1,
High 16, Moderate 28 and Low 9; all are absent from the refreshed audit. There
are **no residual advisory IDs** in this audit. This is a current lock assessment,
not a claim that future advisories or unrelated runtime risks cannot exist.

Primary patch references:
[Symfony CVE-2026-108082](https://symfony.com/cve-2026-108082) and
[Laravel CVE-2026-114509](https://github.com/laravel/framework/security/advisories/GHSA-f9x3-7vq9-cph6).

## Local regression evidence

Tests used a separate source copy without `.env`, a task-owned loopback MySQL
8.4.13 database with a randomly named schema, array cache/mail/session and the
existing hermetic DNS fixture. No real identity, server credential or live
Firebase authentication was used. The Windows-only test superclass/configuration
is isolated QA infrastructure, not a source test change. Source Google PHP files
remain identical to the tested copy. The disposable database was removed and
its task-owned server shut down after testing.

- **185 focused cases / 3062 assertions pass**: all 34 Google cases, 149 existing
  native authentication/device/account/approval/web/API/notification regressions,
  and two new dependency security regression cases. These test rejection of
  non-string TokenGuard credentials without querying a user provider, and the
  corrected whitespace/header behavior.
- Google migration up/down, uniqueness/concurrency and selective unlink/rollback
  token preservation pass on the disposable database. No server migration ran.
- The broad existing practical suite ran **755 cases / 6986 assertions**:
  **753 pass, one failure, one error**, plus one PHPUnit deprecation. The four
  original Linux concurrency datasets were excluded from this Windows run only.
  The two new dependency unit cases were subsequently included in the focused
  run. This is not a completely passing full Laravel suite.
- The failure is the already documented
  `StudentExcelImportTest::test_valid_excel_import_creates_students_inside_current_school`
  (unknown `schools.key` column). The frozen Phase8 gate already identifies this
  same baseline failure. No application fix or failure allowance was introduced.
- The error is
  `StaticPrivateUrlTest::test_http_layer_denies_sensitive_physical_copies_and_symlinks_on_both_aliases`:
  its original `/dev/null` subprocess descriptor is unavailable on Windows.
  Its assertions and fixture were retained for Linux execution.
- Source guard self-tests: 8 pass. Result-summary rejection fixtures: 6 pass.
  Review-bundle tests: 8 pass. PHP syntax, shell syntax, workflow YAML structure
  and action SHA pinning checks pass. These do not claim GitHub/Ubuntu execution.

Private QA evidence filenames include `composer-update.log`,
`composer-validate.log`, `composer-platform.log`, `audit-before.json`,
`audit-after.json`, `windows-full-junit.xml`, `windows-full-tests.log`,
`focused-junit.xml` and `focused-tests.log`. Raw diagnostics stay outside Git and
are not uploaded by the workflow. The earlier Google implementation report is
historical; this report supersedes its dependency audit counts.

## Linux candidate gate and frozen evidence

Docker CLI is absent and WSL reports not installed. Linux tests are **NOT
EXECUTED**, including the four original
`MobileRatePolicyTest::test_independent_processes_cannot_overbook_a_shared_budget`
datasets: login (5), read (120), session (10), reset (3). No host OS feature or
remote runtime was installed/used to bypass this limitation.

On Ubuntu with local Docker Engine/Compose and PowerShell Core, from the project
root run:

```powershell
pwsh -NoProfile -File ./tools/testing/run-tests.ps1 -SelfTest -SecurityGate
pwsh -NoProfile -File ./tools/testing/run-tests.ps1 -PrepareOnly -SecurityGate
pwsh -NoProfile -File ./tools/testing/run-tests.ps1 -SecurityGate
```

The first two checks passed locally: the filtered context includes all candidate
files, contains no environment/private credential files, never boots Laravel,
and cleans only task-owned scratch space. Availability validation returned exit
30 (Docker unavailable); no Linux execution result is inferred from it.

The existing digest-pinned Linux image and disposable MySQL/internal network are
reused. Dependency fetch and locked audit happen at image build time. Test
runtime has no external network, server account, real user or provider credential.
Array cache is retained; concurrency fixtures use disposable file cache/locks,
so Redis is not required. The new runner executes the **unmodified four cases**,
then Google cases, then the complete active suite (expected at least 761 cases).
It fails on failures/errors/skips/warnings; no Excel exception is allowlisted.
The pre-existing PM-authorized WhatsApp retirement remains the only historical
suite exclusion. Existing migration probes and the original environment guard
remain in use. Raw failures never become successful evidence.

`phase8-scope.json`, its original assertions and acceptance scripts remain
unchanged. The four inherited legitimate Google changes were reviewed:

| Frozen file | Reason for candidate change |
| --- | --- |
| app/Providers/AppServiceProvider.php | Google verifier dependency injection |
| app/Services/Mobile/NativeRateLimiter.php | Google admission and shared login budgets |
| app/Support/ApiError.php | Six safe Google error codes; same error envelope |
| routes/api.php | Four approved Google operations; existing routes retained |

The current task adds a fifth reviewed change: `.github/workflows/deploy.yml`
removes automatic deployment and supplies a manual test-only review handoff.
`security-baseline-proposal.json` records the original manifest SHA, each old/new
file SHA, reasons and candidate file hashes. Its status is explicitly
**PENDING_PM_REVIEW**, with no approver recorded. Candidate tests may run against
this proposal; that is not acceptance of a revised frozen baseline. Unlisted
drift is rejected. Release-mode verification currently rejects it with exit 29,
as tested. No frozen baseline was silently regenerated or accepted.

## Local deployment-safety proposal and rollback

The replacement `deploy.yml` has **workflow_dispatch only**. Ordinary `main`
pushes run validation through `security-validation.yml` and never deploy. A
requested test handoff requires a full commit SHA, an explicit checkbox, Linux
test success, a protected `edaratek-test` environment with configured reviewers,
and a separately approved frozen-baseline proposal. API inability to verify
reviewers fails closed. All actions are pinned to full commit SHAs.

The review bundle is a **source-only review artifact**, not an installable server
release. Its creator requires a clean checkout of the exact requested commit,
rejects sensitive paths/content and symlinks, and records the archive and lock
hashes. Verification compares the archive to `git archive` of that exact commit,
not just to a self-declared manifest. Handoff downloads the same run's immutable
artifact ID and rechecks revision, hashes and target. No server credentials,
SSH, broad migration, production target or server executor is configured.
Nothing was pushed or activated; no actual release bundle for the uncommitted
work was claimed. Environment protection settings require later operator/PM
configuration; they were not changed by this task.

After all blockers are resolved, a separately authorized operator should:

1. Verify test-host identity, current release SHA, PHP CLI/FPM and database
   compatibility; prepare a scoped database/configuration backup and known-good
   code/dependency/frontend artifacts outside the document root.
2. Approve the reviewed immutable revision/artifact, Linux results and baseline
   proposal. Keep Google disabled while preparing only the test environment.
3. In an approved maintenance window, install the exact lock/artifact and run
   **only** the approved Google migration after reviewing migration status. Do
   not use an unattended broad migration command or apply unrelated pending work.
4. Verify password login/session/permissions, then configure private Firebase
   credentials and perform explicitly authorized dedicated-account Google UAT.

Rollback planning: disable new Google exchanges, stop linking traffic, and revoke
only Google-attributed Sanctum sessions when withdrawing the feature. Disabling
Firebase alone does not revoke existing Sanctum tokens. Restore known-good code,
lock and assets; prefer leaving compatible additive schema in place. A schema
rollback requires separate approval and the exact migration/batch review; it
deletes identity links and attributed sessions while preserving password
sessions. It is structurally reversible, not data-restoring. Never blindly
restore revoked sessions or overwrite unrelated test data from a full backup.

## Remaining approval boundary

Security advisories are resolved for this lock, but the release is **not accepted
for deployment**: Linux execution is outstanding, the known Excel application
failure still blocks a fully green gate, frozen proposal approval is pending,
and actual Ubuntu/Firebase/server readiness and live UAT remain unverified.
Resolving the unrelated Excel application defect requires separately scoped
application authorization; its test was neither changed nor waived here.

No Flutter files changed. No application API contract, session authorization,
Firebase project setting or server database changed. Both indexes are empty,
HEADs/branches are preserved, and no commit/push/merge/deployment occurred.
Stop at **SECURITY_REMEDIATION_AWAITING_PM_REVIEW**.
