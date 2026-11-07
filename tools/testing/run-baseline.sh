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
# ONE suite. Warnings are counted from version-checked verbose event records.
test_exit=0
vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --log-junit=/tmp/baseline-junit.xml --log-events-verbose-text=/tmp/baseline-events.log >/tmp/baseline.log 2>&1 || test_exit=$?
php tools/testing/summarize-junit.php baseline "$test_exit" || fail baseline_evidence 24
exit "$test_exit"
