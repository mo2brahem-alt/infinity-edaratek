# Phase6 measured verification results

Exact source d1810ccef7c8c64651098eecd33f0fbe017bfb62. [CI42607287769/job129126371236](https://github.com/mo2brahem-alt/infinity-edaratek/actions/runs/42607287769/job/129126371236). Only isolated baseline workflow ran. GitHub conclusion failure / process exit1 is retained: unchanged known Excel assertion only. All Phase2-6 acceptance probes PASS.

| Run | Total | Passed | Failed | Errors | Skipped | PHP warning events |
|---|---:|---:|---:|---:|---:|---:|
| phase6_focused | 20 | 20 | 0 | 0 | 0 | 20 |
| phase5_focused | 100 | 100 | 0 | 0 | 0 | 100 |
| phase4_focused | 65 | 65 | 0 | 0 | 0 | 65 |
| phase3_focused | 128 | 128 | 0 | 0 | 0 | 128 |
| phase2_regression | 16 | 16 | 0 | 0 | 0 | 16 |
| authoritative_full | 647 | 646 | 1 | 0 | 0 | 646 |

Focused20 tests/92 assertions; full647/4292 assertions. No delivery tests executed. Full reconciliation670-41+10+8=647:41 archived static cases,10 generic retained/executed,31 old product cases superseded,8 active cases added. Static scope reconciliation PASS and archived source hash unchanged. Generic two methods and data-provider bodies were also compared locally and unchanged. Exact inventory: retired-test-inventory.json.

Known failure: Tests\Feature\StudentExcelImportTest::test_valid_excel_import_creates_students_inside_current_school, testline53, SQLSTATE42S22/MySQL1054. Original source/test unchanged. Full646 PHP warning events match the prior dotenv Reader.php:73 signature29850f1c3c9f0a5f7f50bfc840a3d002d63b04b010f143ff2726246b87a883fd; one existing PHPUnit deprecation separately recorded. No errors, skips, fatal signatures or OOM event increase.

CI command (existing workflow):
```powershell
./tools/testing/run-tests.ps1
```

It builds the existing pinned filtered image, installs locked Composer/npm dependencies, runs npm run build, PHP-lints tools and changed PHP, checks sh -n, starts disposable guarded MySQL with runtime-only internal network, then executes tools/testing/run-baseline.sh. Exact relevant test/probe commands from that script (private output redirection/exit capture omitted for readability):
```sh
php tools/testing/probe-notification-scope.php

php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --log-junit=/tmp/baseline-junit.xml --log-events-verbose-text=/tmp/baseline-events.log tests/Feature/Mobile/Phase6 tests/Feature/PhoneStateCompatibilityTest.php tests/Feature/RealtimeNotificationFlowTest.php

php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --log-junit=/tmp/baseline-junit.xml --log-events-verbose-text=/tmp/baseline-events.log tests/Feature/Mobile/Phase5 tests/Feature/SchoolAttachmentManagementTest.php tests/Feature/StudentLeaveManagementTest.php tests/Feature/StudentAttendanceAccessTest.php tests/Feature/StudentCertificateFeatureTest.php tests/Feature/UploadValidationFeatureFlagTest.php tests/Feature/PublicStorageLinkStructureTest.php tests/Feature/ManagerOnboardingLogoUploadTest.php tests/Feature/MediaStorageUrlFallbackTest.php

php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --log-junit=/tmp/baseline-junit.xml --log-events-verbose-text=/tmp/baseline-events.log tests/Feature/Mobile/Phase4 tests/Feature/TicketWorkflowAuthorizationTest.php tests/Feature/HeavyEndpointsPaginationTest.php tests/Feature/RealtimeNotificationFlowTest.php tests/Feature/ManagerDashboardAnalyticsTest.php tests/Feature/ActiveSchoolAssociationMiddlewareTest.php tests/Feature/AcademicPlanningAccessTest.php tests/Feature/QuickSetupStatusTest.php tests/Feature/SchoolTenantIsolationTest.php

php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --log-junit=/tmp/baseline-junit.xml --log-events-verbose-text=/tmp/baseline-events.log tests/Feature/Mobile/Phase3 tests/Feature/SafeAdminSeederTest.php tests/Feature/Auth/PasswordResetTest.php tests/Feature/AccountApprovalFlowTest.php tests/Feature/SchoolUserRoleAssignmentApiTest.php tests/Feature/SubscriptionBillingAndSeatsTest.php

php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --stop-on-error --log-junit=/tmp/baseline-junit.xml --log-events-verbose-text=/tmp/baseline-events.log tests/Feature/Mobile/NativeAccountStateTest.php tests/Feature/Mobile/NativeAuthenticationTest.php tests/Feature/Mobile/NativeDeviceLifecycleTest.php tests/Feature/Mobile/NativeWebCompatibilityTest.php

php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --log-junit=/tmp/baseline-junit.xml --log-events-verbose-text=/tmp/baseline-events.log

php tools/testing/probe-phase6-gate.php "$test_exit"
```

The script also runs existing environment/safety, schema, scoped PAT and manifest migration probes, diagnostic authentication/academic/approval subsets, and earlier-phase acceptance probes. No thresholds are relaxed to ignore errors/skips or extra failures. Composer validation including strict and platform checks PASS; generated Vite manifest verified. Syntax gates pass by successful Docker build before test execution.

Local non-runtime validation:
```powershell
git diff --check
& 'C:\Program Files\Git\bin\bash.exe' -n tools/testing/run-baseline.sh
git diff ecb6b7f HEAD -- database/migrations app/Services/School/StudentImportService.php tests/Feature/StudentExcelImportTest.php tests/Feature/WhatsAppIntegrationTest.php .github/workflows/deploy.yml
```
Diff of preserved paths is empty. Local PHP/Composer/Docker are unavailable; there is no claim of host application execution.

Runtime PHP8.2.35/Composer2.11.1/Laravel12.50.0/PHPUnit11.5.51/Node22.23.4/MySQL8.0.46, utf8mb4_0900_ai_ci. All114 migration files apply only in disposable DB; PAT scoped rollback/reapply/repeat and manifest retained-data rollback/reapply/repeat PASS. Runtime guards prove no provider credentials, mail fakes, no worker processing and blocked external TCP.

Sanitized complete measurements, warning categories, migration probes, acceptance class counts and JUnit hashes: [validated-runtime.json](validated-runtime.json). Raw JUnit/logs/credentials/payloads are not exported.
