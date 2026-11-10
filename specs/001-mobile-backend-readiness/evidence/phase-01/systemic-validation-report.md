# Phase 1 systemic DB validation / Phase 2 eligibility report

EDARATEK_PHASE_1_VALIDATION_AND_PHASE_2_EXECUTION_REPORT

Date: 2026-11-08 (Africa/Cairo).

PHASE_1_APPLICATION_MIGRATION_DEFECT_FOUND
PHASE_1_BLOCKED_SYSTEMIC_BASELINE_INVALID

## Scope and decision

Starting branch ci/mobile-backend-baseline; starting local/fetched remote HEAD fab8eea5ecb2da1188ba0d9fa34070b68e4818f5.
Read Phase 1 tasks/plan/quickstart/checklist and prior executed evidence; the prior execution-only acceptance has been withdrawn.
This request authorized infrastructure diagnosis/repair commits but explicitly required stopping before modification of a genuine repository migration defect.
That boundary is now reached. No migration/application/test edit, workaround migration, naming-grammar override, SQL-mode weakening, RefreshDatabase removal or expectation change was performed.
No Phase 2 writes/branch/task execution began.

## Infrastructure change and CI

Commit 40e580fdfb2ab49df2b24f14e86bf7c22c6c8313: test: diagnose isolated MySQL schema readiness.
Exact files:
- tools/testing/probe-database.php
- tools/testing/run-baseline.sh
- tools/testing/summarize-junit.php

Pushed only origin/ci/mobile-backend-baseline. CI run 42478658016, job 128725553146.
URL: https://github.com/mo2brahem-alt/infinity-edaratek/actions/runs/42478658016
Created 2026-11-08T12:06:06Z, completed/updated 12:08:32Z (2m26s), conclusion failure.
Only isolated baseline workflow returned for this SHA; no deploy workflow triggered.
Remote main stayed 12b9d5326d942acda55309aac05c6cee1bf928b0.
Runtime build/Composer/platform/Vite/internal MySQL/preflight/Laravel boot remain PASS.
The new probe executed after guard checks, with effective/actual DB identity constrained to the generated ephemeral test database.
Raw migration/SQL/exception output remains private. Probe captures errorInfo[0]/[1], never errorInfo[2].

## Clean MySQL migration proof

Initial migrations table absent; applied migrations 0; source migration files 112.
Character set utf8mb4; collation utf8mb4_0900_ai_ci.
SQL mode ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION.
Actual MySQL 8.0.46; generated database only, no host mount/DB port.

MIGRATION_GATE=FAIL
operation_stage=migrate_fresh
exception_class=Illuminate\Database\QueryException
SQLSTATE=42000
SQLSTATE_CLASS=42
DRIVER_ERROR_CODE=1059
APPLIED_MIGRATIONS_AFTER=85

Failing source migration:
database/migrations/2026_03_11_100200_create_school_teaching_assignment_classrooms_table.php
SHA-256 0452865f29dcb21bec0ae761ca6935f98fa04832fa2fa686ea6ff5c1641fb69d.
This matches the unmodified file in the starting reviewed commit and local source.

Laravel Blueprint::createIndexName builds table + column + foreign suffix when constrained() receives no explicit index name.
Source line 18 yields school_teaching_assignment_classrooms_school_teaching_assignment_id_foreign (75 ASCII characters).
Source line 19 also yields school_teaching_assignment_classrooms_school_classroom_id_foreign (65 ASCII characters).
MySQL's constraint/index identifier maximum is 64 characters. Error 1059 / ER_TOO_LONG_IDENT uses SQLSTATE 42000.
The first offending constraint at line 18 is sufficient to prevent a clean MySQL migration chain; the second also exceeds the limit.
This is a repository migration defect, not evidence of missing DB privileges, missing dependency, fixture data or remote host misrouting.
No raw SQL was needed for this diagnosis.

Official references:
- https://dev.mysql.com/doc/mysql-errors/8.0/en/server-error-reference.html (1059 / ER_TOO_LONG_IDENT / 42000)
- https://dev.mysql.com/doc/mysql-reslimits-excerpt/8.0/en/identifier-length.html (constraint/index limit 64)

Package discovery/boot before migrations passed. Post-migration boot/schema completeness/User factory insert could not be proven because migrations failed first; the probe correctly did not advance those stages.

## Unchanged representative tests

Executed one authoritative diagnostic subset per group, --stop-on-error, same MySQL runtime, memory_limit 512M. Existing tests/RefreshDatabase/assertions untouched.

| Group / first selected test | Total | Passed | Errors | Assertions | Warnings | Exit | SQLSTATE/driver |
|---|---:|---:|---:|---:|---:|---:|---|
| Auth AuthenticationTest / test_login_screen_can_be_rendered |1|0|1|0|1|2|42000/1059|
| AcademicPlanningAccessTest / test_manager_can_access_academic_planning_and_create_term_for_his_school |1|0|1|0|1|2|42000/1059|
| AccountApprovalFlowTest / test_supervisor_registration_creates_pending_account_redirects_home_and_sends_notifications |1|0|1|0|1|2|42000/1059|

Each subset completed with valid JUnit and matching terminal/process exit 2; each failed in database setup before assertions.
The independent clean migrate:fresh probe and three unrelated RefreshDatabase groups reproduce the same structural condition.
This proves the baseline is not representative of normal application execution. It does not individually attribute all 475 old errors without a new repaired full run.

## PHP warning source

All three diagnostic subsets observed one PHP warning each, same signature:
29850f1c3c9f0a5f7f50bfc840a3d002d63b04b010f143ff2726246b87a883fd.
Static source vendor/vlucas/phpdotenv/src/Store/File/Reader.php:73.
That source invokes @file_get_contents while Laravel LoadEnvironmentVariables calls Dotenv safeLoad; default application environment filename is .env.
The isolated context intentionally has no .env or .env.*. The source/category is dotenv file-reading during test bootstrap, not deprecated PHP behavior or provider execution.
No warning message or private path/value was exported. The @ call suppresses native warning output, while PHPUnit's verbose event stream records PHP warning events.
The older 522 warnings were not location-instrumented; the new sample proves 3/3 share this bootstrap source, not that every older warning has been individually attributed.
No warning-storm repair was attempted after the migration-defect boundary was established.

## Full baseline before/after

Prior full run 42472192069: TOTAL 523, PASSED 48, FAILED 0, ERRORS 475, SKIPPED 0, WARNINGS 522, TEST_EXIT_CODE 2.
Completion/valid JUnit are trustworthy recording facts, but environment validity is FAIL due to reproduced migration defect.
New full baseline NOT_RUN: schema gate intentionally stopped it after short diagnostic runs.
No after-repair full counts exist; diagnostic subset counts are not substituted for a full run or compared as an error reduction.
The prior PHP memory exhaustion remains resolved. Keep the isolated 512M limit; no additional increase or timeout change.

## T005 and T012

T005 remains INCOMPLETE: existing fakes/network/profile guards do not constitute the full required executed negative-transport/shared-state proof. Those probes were not falsely inferred from the PowerShell self-test or the DB-failing sample.
T012 remains INCOMPLETE/FAIL: original requirements are not met; systemic DB setup failure invalidates representative baseline, T005 is unfinished, and formal review cannot certify this environment.
T008/T009 remain reopened for validated baseline and causally classified error register. T006/T007 retain actual runtime/platform objective evidence but do not close the DB schema gate.
CHK006/074 remain open; no live debug/error-redaction claim. T013-T028 NOT_STARTED.

TEST_RUNTIME_READY=PASS
BASELINE_TEST_RESULTS_RECORDED=PASS (prior completed run only)
BASELINE_ENVIRONMENT_VALIDITY=FAIL
T005=INCOMPLETE
T012=INCOMPLETE
PHASE_1_ACCEPTANCE=FAIL
MOBILE_BACKEND_NOT_READY

## Safety

PUSH_TO_MAIN=NO
MERGE_PERFORMED=NO
DEPLOYMENT_PERFORMED=NO
DEPLOY_WORKFLOW_TRIGGERED=NO
STAGING_MODIFIED=NO
PRODUCTION_TOUCHED=NO
REAL_WHATSAPP_SENDS=0
APPLICATION_SOURCE_MODIFIED=NO
APPLICATION_TESTS_MODIFIED=NO
PRODUCTION_MIGRATION_MODIFIED=NO
UNRELATED_CHANGES_PRESERVED=YES
PHASE_2_STARTED=NO

Only local evidence/tasks/checklist updates follow this diagnosis. No further implementation commit/push after the migration-defect boundary.

PHASE_1_APPLICATION_MIGRATION_DEFECT_FOUND
PHASE_1_BLOCKED_SYSTEMIC_BASELINE_INVALID
