#!/bin/sh
set -eu
# Original Linux concurrency assertions and isolation guard are unchanged.
fail() { printf '{"kind":"infrastructure","stage":"%s","status":"FAIL"}\n' "$1"; exit 29; }
php tools/testing/check-successor-baseline.php /app image || fail successor_sources
php tools/testing/test-successor-baseline.php || fail successor_guard_self_test
php tools/testing/test-security-candidate.php || fail source_guard_self_test
php tools/testing/test-security-case-coverage.php || fail case_coverage_self_test
php tools/testing/test-security-summaries.php || fail summary_guard_self_test
php tools/testing/assert-environment.php preflight >/tmp/security-guard.json 2>/tmp/security-guard.log || fail environment_preflight
php tools/testing/probe-linux-native.php || fail linux_native_prerequisites
cat /tmp/security-guard.json
composer validate --strict --no-check-publish --no-interaction >/tmp/security-validate.log 2>&1 || fail composer_validate
composer check-platform-reqs --no-interaction >/tmp/security-platform.log 2>&1 || fail composer_platform
php -r 'echo json_encode(["kind"=>"platform","status"=>"PASS","php_requirement"=>"^8.2","php_version"=>PHP_VERSION,"os_family"=>PHP_OS_FAMILY], JSON_THROW_ON_ERROR).PHP_EOL;'
# Dependency fetch/audit network belongs to the build/CI host, never test runtime.
php tools/testing/probe-database.php boot || fail disposable_migrations
php tools/testing/assert-environment.php boot >/tmp/security-guard.json 2>/tmp/security-guard.log || fail environment_boot
cat /tmp/security-guard.json
result=0
code=0
php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --fail-on-warning --fail-on-risky --fail-on-skipped --filter test_independent_processes_cannot_overbook_a_shared_budget tests/Feature/Mobile/Phase7/MobileRatePolicyTest.php --log-junit=/tmp/security-concurrency.xml >/tmp/security-concurrency.log 2>&1 || code=$?
php tools/testing/summarize-security-tests.php concurrency "$code" || result=29
code=0
php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --fail-on-warning --fail-on-risky --fail-on-skipped tests/Feature/Mobile/GoogleIdentityVerifierTest.php tests/Feature/Mobile/GoogleSessionContractTest.php tests/Feature/Mobile/GoogleIdentityConcurrencyTest.php --log-junit=/tmp/security-google.xml >/tmp/security-google.log 2>&1 || code=$?
php tools/testing/summarize-security-tests.php google "$code" || result=29
code=0
php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --fail-on-warning --fail-on-risky --fail-on-skipped tests/Feature/Mobile/NativeAuthenticationTest.php tests/Feature/Mobile/NativeDeviceLifecycleTest.php tests/Feature/Mobile/NativeAccountStateTest.php tests/Feature/Mobile/Phase3/AccountApprovalMatrixTest.php tests/Feature/Mobile/NativeWebCompatibilityTest.php tests/Feature/Mobile/Phase7 tests/Feature/Mobile/Phase6/NotificationChannelPolicyTest.php --log-junit=/tmp/security-native.xml >/tmp/security-native.log 2>&1 || code=$?
php tools/testing/summarize-security-tests.php native "$code" || result=29
code=0
php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --fail-on-warning --fail-on-risky --fail-on-skipped tests/Feature/StudentExcelImportTest.php --log-junit=/tmp/security-excel.xml >/tmp/security-excel.log 2>&1 || code=$?
php tools/testing/summarize-security-tests.php excel "$code" || result=29
code=0
php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --fail-on-warning --fail-on-risky --fail-on-skipped tests/Feature/Mobile/Phase5/StaticPrivateUrlTest.php --log-junit=/tmp/security-static.xml >/tmp/security-static.log 2>&1 || code=$?
php tools/testing/summarize-security-tests.php static "$code" || result=29
code=0
php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --fail-on-warning --fail-on-risky --fail-on-skipped tests/Feature/Mobile/NativeAuthenticationTest.php tests/Feature/Mobile/NativeDeviceLifecycleTest.php tests/Feature/Mobile/NativeAccountStateTest.php tests/Feature/Mobile/NativeWebCompatibilityTest.php tests/Feature/Mobile/Phase3/AccountApprovalMatrixTest.php tests/Feature/Mobile/Phase7 tests/Feature/Mobile/Phase6/NotificationChannelPolicyTest.php --log-junit=/tmp/security-native.xml >/tmp/security-native.log 2>&1 || code=$?
php tools/testing/summarize-security-tests.php native "$code" || result=29
code=0
php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --fail-on-warning --fail-on-risky --fail-on-skipped tests/Feature/StudentExcelImportTest.php --log-junit=/tmp/security-excel.xml >/tmp/security-excel.log 2>&1 || code=$?
php tools/testing/summarize-security-tests.php excel "$code" || result=29
code=0
php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --fail-on-warning --fail-on-risky --fail-on-skipped tests/Feature/Mobile/Phase5/StaticPrivateUrlTest.php --log-junit=/tmp/security-static.xml >/tmp/security-static.log 2>&1 || code=$?
php tools/testing/summarize-security-tests.php static "$code" || result=29
code=0
# Complete active suite; retains the previously authorized WhatsApp retirement.
# No Excel/Windows/concurrency failure is allowlisted or filtered here.
php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --fail-on-warning --fail-on-risky --fail-on-skipped --log-junit=/tmp/security-full.xml >/tmp/security-full.log 2>&1 || code=$?
php tools/testing/summarize-security-tests.php full "$code" || result=29
php tools/testing/check-successor-baseline.php /app image || result=29
exit "$result"
