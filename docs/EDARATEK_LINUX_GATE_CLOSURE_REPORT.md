# EDARATEK_LINUX_GATE_CLOSURE_REPORT

Date: 2026-11-19. Authority: current PM **Linux Release Gate Closure** instruction.
Scope: local Excel correction, isolated validation infrastructure and proposed
fingerprint evidence. No server or Git history/index operation is authorized.

```text
EXCEL_ROOT_CAUSE=CONFIRMED; Query Builder whereKey generated schools.key, not schools.id
EXCEL_FIX=APPLIED; explicit bound id predicate; original transaction, lock and tests retained
DEV_NULL_ROOT_CAUSE=WINDOWS_ENVIRONMENT; original native Linux subprocess descriptor
LINUX_RUNNER_PREPARED=YES; Ubuntu 24.04 plus isolated digest-pinned PHP/MySQL containers
LINUX_WORKFLOW_VALIDATED_LOCALLY=PASS_STATIC_AND_GUARD_CHECKS; no Linux execution claim
LINUX_TESTS_EXECUTED=NO
CONCURRENCY_TESTS=FOUR_ORIGINAL_LINUX_CASES_NOT_EXECUTED; mandatory in prepared runner
FULL_LARAVEL_TESTS=756/756 Windows-applicable cases PASS; 7000 assertions; complete Linux suite NOT EXECUTED
GOOGLE_SECURITY_TESTS=34/34 PASS in current Windows run; real Firebase authentication NOT EXECUTED
COMPOSER_AUDIT=PASS; zero advisories and abandoned packages
FROZEN_BASELINE_REVIEW=FIVE_INHERITED_RECORDS_REVIEWED; sixth Excel proposal added; all pending PM
FROZEN_BASELINE_UPDATED=NO
DEPLOYMENT_WORKFLOW_ISOLATION=PASS_LOCAL_REVIEW; validation only, feature/PR path, no server credentials or deployment bridge
REMAINING_BLOCKERS=Real Ubuntu execution of full suite, four concurrency datasets and static-file case; separate PM baseline approval
COMMIT=NO
PUSH=NO
MERGE=NO
DEPLOYMENT=NO
SERVER_MIGRATION=NO
PRODUCTION_TOUCHED=NO
FINAL_STATUS=LINUX_GATE_AWAITING_PM_REVIEW
```

## Exact failures and Excel correction

The prior broad run recorded two different issues:

1. `Tests\Feature\StudentExcelImportTest::test_valid_excel_import_creates_students_inside_current_school`:
   `assertRedirect(...)` at test line 53 expected a redirect status
   `[201,301,302,303,307,308]` but received 500. The underlying database error was
   SQLSTATE `42S22` / MySQL 1054, unknown column `key` in a school lookup.
   `tools/testing/probe-mobile-auth-gate.php` and `probe-phase8-gate.php` already
   name this exact pre-existing failure. It is not an outdated assertion or a
   Windows-specific SQL issue.
2. `Tests\Feature\Mobile\Phase5\StaticPrivateUrlTest::test_http_layer_denies_sensitive_physical_copies_and_symlinks_on_both_aliases`:
   `proc_open(/dev/null)` failed on Windows before the subprocess-resource and
   HTTP assertions could execute. It was an environment error, not an observed
   failure of the private-file authorization assertions.

The Excel failure was reproduced again before editing, on the current patched
Laravel 12.73.0/PHP 8.2.35 stack in a disposable database: one case, one failure.
The original service called `DB::table('schools')->whereKey($schoolId)`.
`DB::table()` returns Query Builder; its dynamic `where*` dispatch interprets that
as column `key`. Eloquent's model-aware `whereKey()` behavior does not apply.

The smallest correction in `app/Services/School/StudentImportService.php:255` is:

```php
DB::table('schools')->where('id', $schoolId)->lockForUpdate()->value('id');
```

This uses the actual primary key and retains parameter binding, the same
transaction and the same parent-school lock for per-school student code
generation. File validation, import limits, row transformation, authorization,
school scoping and response contracts are unchanged. No test assertion changed.

After the correction, the four original Excel cases pass with 12 assertions:
template download, successful school-local import with `STU-001`, rejection of
a foreign-school classroom, and rejection of an unauthorized staff member.
The later broad run confirms the same four cases pass in combination with the
other application tests. The earlier remediation report remains historical;
this evidence closes its Excel blocker locally.

## Native Linux behavior and prepared execution

The original static-file test remains byte-for-byte unchanged. It must verify
both physical copies and symlinks under both public aliases: sensitive files
return 404 without their contents and with `no-store`, while permitted logos
return 200 with the expected contents. These assertions were not replaced with
Windows substitutes or skips.

The Linux kernel interface specifies that `/dev/null` is a character device and
reads return EOF. The new `probe-linux-native.php` checks that actual device,
native PHP subprocess stdin/exit behavior, required extensions and process/
symlink function availability before database test execution. On Windows it
rejects the environment with exit 29; that negative check passed locally. Its
positive Linux result and the original HTTP/symlink assertions are **NOT
EXECUTED** here. Reference: [Linux null(4)](https://man7.org/linux/man-pages/man4/null.4.html).

The validation workflow is `.github/workflows/security-validation.yml`. The host
is Ubuntu 24.04; the digest-pinned PHP 8.2 Linux image runs at `/app`, preserving
the original worker assumptions. The existing Compose topology uses a private
internal network, random disposable MySQL database credentials, MySQL data in
tmpfs, no published ports, no real `.env`, array cache and per-fixture disposable
file locks. Redis is not needed. Existing environment guards and isolated test
superclass remain unchanged. Test runtime cannot reach application services or
Firebase. Public dependency downloads/audit occur during image build only.

The runner executes explicit lanes and then the complete active suite:

| Lane | Required evidence |
| --- | --- |
| Composer | Strict validation, platform requirements and fresh locked audit |
| Original concurrency | Four `MobileRatePolicyTest::test_independent_processes_cannot_overbook_a_shared_budget` datasets #0-#3: login/read/session/reset |
| Google | At least 34 cases from the three existing Google security classes |
| Native API | At least 153 native/account/approval/web/Phase7/notification-policy cases, including original concurrency datasets |
| Excel | All four original `StudentExcelImportTest` methods |
| Static files | The exact original HTTP/copy/symlink method above |
| Full | At least 761 active cases, including mandatory concurrency, Google, Excel and static case identities |

Failures/errors/skips and nonzero runner exit codes fail the gate. The result
summarizer now verifies case identities, not merely totals. No known Excel
failure is allowlisted. Existing PM-authorized retirement of the WhatsApp suite
is preserved; no new Linux exclusion is added. A per-run nonsecret audit nonce
invalidates Docker's audit layer so a cached image cannot reuse stale advisory
results indefinitely. No dependency version or PHP requirement changed here.

On a machine with local Linux Docker/Compose and PowerShell Core:

```powershell
pwsh -NoProfile -File ./tools/testing/run-tests.ps1 -SelfTest -SecurityGate
pwsh -NoProfile -File ./tools/testing/run-tests.ps1 -PrepareOnly -SecurityGate
pwsh -NoProfile -File ./tools/testing/run-tests.ps1 -SecurityGate
```

Docker is unavailable and WSL is not installed on the current workstation.
Availability validation returned exit 30 without booting Laravel or a container.
No GitHub Actions run was triggered. The commands and workflow are prepared,
not evidence of completed Linux execution.

## Results actually obtained locally

- Full Windows-applicable selection: **756 tests, 7000 assertions, zero
  failures/errors/skips, exit 0**. It excludes only the four original Linux
  worker datasets and the one native static-file case, all explicitly pending
  Linux execution. The 34 Google cases and four Excel cases pass within it.
- One existing PHPUnit metadata deprecation remains in
  `NativeDialogRegressionTest`; no failure or assertion was suppressed to remove
  it. PHPUnit remains on the existing version.
- Composer strict validation, platform requirements and fresh locked audit pass;
  zero advisories/abandoned packages. The security-patched lock is unchanged.
- actionlint 1.7.12 passes for validation and manual-review workflows. Its
  official release checksum was verified before execution. Optional shellcheck/
  pyflakes integrations were unavailable; shell grammar was checked separately
  with `bash -n`. PHP syntax checks pass.
- Case-coverage self-tests: **11 pass**. Result-summary acceptance/rejection and
  redaction fixtures: **17 pass**. Existing source-guard self-tests: **8 pass**.
  Synthetic fixtures report infrastructure checks only, never application-test
  acceptance. Missing mandatory cases, duplicate datasets, failures, errors,
  skips and nonzero exits are rejected.
- Workflow/isolation policy checks: **15 pass**. Filtered context preparation
  passes with no forbidden environment/credential files and no Laravel/DB boot.
- Candidate fingerprint validation passes; release validation deliberately
  rejects pending PM approval with exit 29.

Preflight hashes/status/diffs and QA logs remain outside both repositories in a
private task-owned evidence directory. Evidence files include `excel-before.xml`
and `.log`, `excel-after.xml` and `.log`, `windows-practical.xml` and `.log`,
`windows-events.log`, `composer-audit.json`, `composer-validate.log` and
`composer-platform.log`. Raw request/SQL diagnostics are not uploaded to CI.
The disposable loopback MySQL database was removed and its server stopped;
task-owned compatibility scratch files were also removed. No server migration
or real-account operation occurred.

## Frozen baseline and deployment separation

[EDARATEK_LINUX_FROZEN_BASELINE_REVIEW.md](EDARATEK_LINUX_FROZEN_BASELINE_REVIEW.md)
records the exact old/new SHA-256 values, reasons and security invariants for all
five inherited files. Their candidate hashes did not change in this task. The
Excel service is also frozen and adds a separately justified sixth proposal.
The authoritative `phase8-scope.json` and original acceptance scripts remain
unchanged. Historical gates expect the old known Excel failure and old counts;
they have not been relabeled as acceptance of this candidate.

`security-baseline-proposal.json` remains **PENDING_PM_REVIEW**, with no approver.
Its candidate-only hashes now cover the authorized Excel and runner changes.
No authoritative fingerprint or approval status was updated. Actual Ubuntu
results and explicit PM review are required before a separate baseline update.

Proposed feature path, **not performed**: after separate Git authorization,
review a branch named `feature/linux-release-gate` (or a child of that prefix),
push only that branch, and open a PR targeting `main` without merging. Feature
push and PR events run validation; neither pushes/merges to main. The workflow
uses a read-only token, pinned checkout with no persisted credentials, no
environment secrets, no artifact publishing, no SSH and no deployment calls.
It has no `workflow_run`/`pull_request_target` deployment bridge. GitHub event
reference: [workflow events](https://docs.github.com/en/actions/reference/workflows-and-actions/events-that-trigger-workflows).

The prior manual `deploy.yml` review-handoff proposal is unchanged and accepts
`workflow_dispatch` only. This validation workflow does not invoke it. Actual
server readiness, Firebase credential setup, deployment and live UAT remain
separate approval scopes. Production was not contacted.

Current task file scope is recorded in
[linux-gate-closure-source-files.txt](linux-gate-closure-source-files.txt).
All pre-existing Google/Firebase/Flutter/security work is preserved; only the
single authorized application line and validation infrastructure/proposal/docs
changed. No branch, commit, tag, push, merge or deployment was created.

Stop at **LINUX_GATE_AWAITING_PM_REVIEW**.
