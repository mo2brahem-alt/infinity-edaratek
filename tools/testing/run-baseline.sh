#!/bin/sh
set -eu
# Arbitrary diagnostics stay private in runtime tmpfs.
fail() { printf '{"kind":"infrastructure","status":"FAIL","stage":"%s"}\n' "$1"; exit "$2"; }
guard() {
  if php tools/testing/assert-environment.php "$1" >/tmp/guard.json 2>/tmp/guard.log; then
    cat /tmp/guard.json
  else
    fail "environment_$1" 20
  fi
}
guard preflight
composer validate --no-check-publish --no-interaction --no-ansi >/tmp/validate.log 2>&1 || fail composer_validate 40
strict_exit=0
composer validate --strict --no-check-publish --no-interaction --no-ansi >/tmp/strict.log 2>&1 || strict_exit=$?
# Composer's warning-only strict status is 1; other errors remain hard failures.
[ "$strict_exit" -le 1 ] || fail composer_strict_error 40
platform_exit=0
composer check-platform-reqs --no-interaction --format=json >/tmp/platform.json 2>/tmp/platform.log || platform_exit=$?
composer --version --no-ansi >/tmp/composer-version.txt 2>/tmp/composer-version.log || fail composer_version 42
php tools/testing/summarize-junit.php platform "$strict_exit" "$platform_exit" || fail platform_evidence 42
[ "$platform_exit" -eq 0 ] || fail composer_platform 42
php artisan package:discover --no-ansi >/tmp/discovery.log 2>&1 || fail package_discovery 22
guard boot
schema_exit=0
php -d memory_limit=512M tools/testing/probe-database.php || schema_exit=$?
native_pat_migration_exit=0
php -d memory_limit=512M tools/testing/probe-native-pat-migration.php || native_pat_migration_exit=$?
php -d memory_limit=512M tools/testing/probe-private-manifest-migration.php || fail private_manifest_migration_probe 29
# Small unchanged representative tests diagnose RefreshDatabase independently;
# they do not replace the authoritative full suite below.
diagnostic_exit=0
for file in tests/Feature/Auth/AuthenticationTest.php tests/Feature/AcademicPlanningAccessTest.php tests/Feature/AccountApprovalFlowTest.php; do
  test_exit=0
  if [ "$file" = tests/Feature/Auth/AuthenticationTest.php ]; then
    # Dedicated image default is the unchanged repository MySQL configuration;
    # avoid Collision adding a duplicate --configuration option.
    php -d memory_limit=512M artisan test --colors=never --stop-on-error --log-junit=/tmp/baseline-junit.xml --log-events-verbose-text=/tmp/baseline-events.log "$file" >/tmp/baseline.log 2>&1 || test_exit=$?
  else
    php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --stop-on-error --log-junit=/tmp/baseline-junit.xml --log-events-verbose-text=/tmp/baseline-events.log "$file" >/tmp/baseline.log 2>&1 || test_exit=$?
  fi
  php tools/testing/summarize-junit.php baseline "$test_exit" diagnostic || fail diagnostic_evidence 24
  # Assertion failures can be a legitimate red application baseline. Execution
  # errors must be reviewed before spending time on an authoritative full run.
  if [ "$test_exit" -gt 1 ]; then diagnostic_exit=1; fi
done
[ "$schema_exit" -eq 0 ] || fail migration_schema_probe 26
[ "$native_pat_migration_exit" -eq 0 ] || fail native_pat_migration_probe 29
[ "$diagnostic_exit" -eq 0 ] || fail representative_database_tests 27
guard boot
php -d memory_limit=512M tools/testing/probe-safety.php || fail phase1_safety_probe 28
phase5_exit=0
php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --log-junit=/tmp/baseline-junit.xml --log-events-verbose-text=/tmp/baseline-events.log tests/Feature/Mobile/Phase5 tests/Feature/SchoolAttachmentManagementTest.php tests/Feature/StudentLeaveManagementTest.php tests/Feature/StudentAttendanceAccessTest.php tests/Feature/StudentCertificateFeatureTest.php tests/Feature/UploadValidationFeatureFlagTest.php tests/Feature/PublicStorageLinkStructureTest.php tests/Feature/ManagerOnboardingLogoUploadTest.php tests/Feature/MediaStorageUrlFallbackTest.php >/tmp/baseline.log 2>&1 || phase5_exit=$?
php tools/testing/summarize-junit.php baseline "$phase5_exit" phase5 || fail phase5_focused_evidence 29
[ "$phase5_exit" -eq 0 ] || fail phase5_focused_tests 29
cp /tmp/baseline-junit.xml /tmp/phase5-focused-junit.xml
phase4_exit=0
php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --log-junit=/tmp/baseline-junit.xml --log-events-verbose-text=/tmp/baseline-events.log tests/Feature/Mobile/Phase4 tests/Feature/TicketWorkflowAuthorizationTest.php tests/Feature/HeavyEndpointsPaginationTest.php tests/Feature/RealtimeNotificationFlowTest.php tests/Feature/ManagerDashboardAnalyticsTest.php tests/Feature/ActiveSchoolAssociationMiddlewareTest.php tests/Feature/AcademicPlanningAccessTest.php tests/Feature/QuickSetupStatusTest.php tests/Feature/SchoolTenantIsolationTest.php >/tmp/baseline.log 2>&1 || phase4_exit=$?
php tools/testing/summarize-junit.php baseline "$phase4_exit" phase4 || fail phase4_focused_evidence 29
[ "$phase4_exit" -eq 0 ] || fail phase4_focused_tests 29
cp /tmp/baseline-junit.xml /tmp/phase4-focused-junit.xml
phase3_exit=0
php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --log-junit=/tmp/baseline-junit.xml --log-events-verbose-text=/tmp/baseline-events.log tests/Feature/Mobile/Phase3 tests/Feature/SafeAdminSeederTest.php tests/Feature/Auth/PasswordResetTest.php tests/Feature/AccountApprovalFlowTest.php tests/Feature/SchoolUserRoleAssignmentApiTest.php tests/Feature/SubscriptionBillingAndSeatsTest.php >/tmp/baseline.log 2>&1 || phase3_exit=$?
php tools/testing/summarize-junit.php baseline "$phase3_exit" phase3 || fail phase3_focused_evidence 29
[ "$phase3_exit" -eq 0 ] || fail phase3_focused_tests 29
cp /tmp/baseline-junit.xml /tmp/phase3-focused-junit.xml
# Native auth tests must be clean on their own. The later authoritative suite
# still preserves the historical unrelated StudentExcelImport failure evidence.
native_tests_exit=0
php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --stop-on-error --log-junit=/tmp/baseline-junit.xml --log-events-verbose-text=/tmp/baseline-events.log tests/Feature/Mobile/NativeAccountStateTest.php tests/Feature/Mobile/NativeAuthenticationTest.php tests/Feature/Mobile/NativeDeviceLifecycleTest.php tests/Feature/Mobile/NativeWebCompatibilityTest.php >/tmp/baseline.log 2>&1 || native_tests_exit=$?
php tools/testing/summarize-junit.php baseline "$native_tests_exit" native || fail native_mobile_evidence 29
[ "$native_tests_exit" -eq 0 ] || fail native_mobile_tests 29
# CI run 42469219953 proved PHP memory exhaustion at the base 128M limit.
# Bound ONLY the isolated test process/profile; no application/production ini.
php -d memory_limit=512M tools/testing/summarize-junit.php process-profile || fail process_profile 24
# ONE suite. Warnings are counted from version-checked verbose event records.
test_exit=0
php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --log-junit=/tmp/baseline-junit.xml --log-events-verbose-text=/tmp/baseline-events.log >/tmp/baseline.log 2>&1 || test_exit=$?
php tools/testing/summarize-junit.php baseline "$test_exit" || fail baseline_evidence 24
php tools/testing/probe-mobile-auth-gate.php "$test_exit" || fail mobile_auth_foundation_gate 29
php tools/testing/probe-phase3-gate.php "$test_exit" || fail phase3_acceptance_gate 29
php tools/testing/probe-phase4-gate.php "$test_exit" || fail phase4_acceptance_gate 29
php tools/testing/probe-phase5-gate.php "$test_exit" || fail phase5_acceptance_gate 29
exit "$test_exit"
