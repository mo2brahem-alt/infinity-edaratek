# Phase 1 approved CI push and run report

Status: EDARATEK_PHASE_1_CI_PUSH_AND_RUN_REPORT
Date: 2026-11-07 (Africa/Cairo)

- Starting main HEAD and origin/main before/after: 12b9d5326d942acda55309aac05c6cee1bf928b0.
- Created branch: ci/mobile-backend-baseline.
- Approved commit: 613c2bb945f69ed7098672db48e5c5c395bf5c52.
- Subject: test: add isolated mobile backend baseline CI.
- Commit parent equals reviewed main HEAD; committed file count 9; unrelated files committed 0.
- Push succeeded only to origin/ci/mobile-backend-baseline; remote CI branch equals approved commit.
- main did not move. No force push, tags, PR, merge, additional commit or second push.

Exact committed allowlist:

1. .github/workflows/mobile-backend-tests.yml
2. tools/testing/.dockerignore
3. tools/testing/Dockerfile
4. tools/testing/IsolatedTestCase.php
5. tools/testing/assert-environment.php
6. tools/testing/compose.yaml
7. tools/testing/run-baseline.sh
8. tools/testing/run-tests.ps1
9. tools/testing/summarize-junit.php

## Observed GitHub run

- URL: https://github.com/mo2brahem-alt/infinity-edaratek/actions/runs/42404403945
- Run ID: 42404403945; job ID: 128498880734; job: baseline.
- Event: push; branch: ci/mobile-backend-baseline; SHA: approved commit above.
- Run created: 2026-11-07T19:04:25Z; completed: 2026-11-07T19:04:43Z.
- Run and job status: completed; conclusion: failure.
- Checkout succeeded. Guarded disposable MySQL baseline step failed.
- Safe diagnostic: TEST_IMAGE_BUILD_FAILED.
- GitHub step process exit: 1. This is NOT TEST_EXIT_CODE.
- No deployment workflow run was returned for this exact SHA after completion.
- Static baseline trigger is push CI branch only; deploy trigger is push main only; overlap NO.

## Runtime and baseline evidence

Failure classification: TEST_INFRASTRUCTURE (image-build stage); underlying build cause UNKNOWN.
The wrapper captures build diagnostics in memory and emits only a generic failure marker.
The completed run has no exported Docker build trace, so it cannot establish which build substep failed.
No rerun, code changes, additional commit or second push were performed.
Read-only tree inspection confirms phpunit.mysql.xml and the four frontend config files are tracked.

Actual PHP version: UNAVAILABLE.
Composer validate/install/platform results: NOT_PROVEN; individual build substeps unavailable.
PHP extensions: NOT_PROVEN.
Node/Vite build: NOT_PROVEN; image build did not complete.
Ephemeral MySQL: NOT_STARTED (harness exits before compose up on build failure).
Environment PHP guard: NOT_RUN.
Laravel boot: NOT_RUN.
Tests executed: NO.
TOTAL/PASSED/FAILED/ERRORS/SKIPPED/WARNINGS/TEST_EXIT_CODE: UNAVAILABLE (not fabricated zeros).
Zero-test rejection: implemented statically, NOT_EXERCISED in this run.
TEST_RUNTIME_READY=FAIL.
BASELINE_TEST_RESULTS_RECORDED=FAIL.
Phase 1 acceptance=FAIL.

## Task and decision disposition

T003-T005: authored infrastructure/static proof only; container behavior unproven; remain unchecked.
T006: CI started, image build failed; remains unchecked.
T007: Composer/platform execution unproven; remains unchecked.
T008: Laravel/tests not executed; remains unchecked.
T009: build-stage blocker classified; no application failure/skip baseline to classify; remains unchecked.
T010-T011: no successful runtime/baseline evidence to reconcile; remain unchecked.
T012: acceptance FAIL; remains unchecked.
No new checklist completions. CHK006 is not fully closed; D2 preserved.
No D1/C1/V1 implementation. T013 and Phase 2 not started.
Historical evidence files describe earlier attempts; this report records the later CI attempt.
This local report is uncommitted and was not pushed.

APPLICATION_BUSINESS_SOURCE_MODIFIED=NO
UNRELATED_CHANGES_PRESERVED=YES
ADDITIONAL_COMMIT_CREATED=NO
PULL_REQUEST_CREATED=NO
MERGE_PERFORMED=NO
STAGING_MODIFIED=NO
PRODUCTION_TOUCHED=NO
REAL_WHATSAPP_SENDS=0
PHASE_2_STARTED=NO

PHASE_1_BLOCKED_AWAITING_PM_REVIEW
