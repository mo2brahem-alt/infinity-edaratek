# Phase 2 executed results

Executed source: `e448618bffe70e10352b52bf20d3ffc6f43b14f5`.
CI run/job: 42486583770 / 128749676130. Runtime: PHP 8.2.35, Laravel 12.50.0, Sanctum 4.3.0, PHPUnit 11.5.51, MySQL 8.0.46, utf8mb4 / utf8mb4_0900_ai_ci. The runner used a generated internal-only MySQL service and process-only synthetic credentials.

- Existing Phase 1 safety probe: PASS.
- PAT migration forward/rollback/reapply/repeat: PASS.
- Phase 2 Mobile subset: 16 total, 16 passed, 0 failed, 0 errors, 0 skipped, exit 0.
- Full authoritative suite: 539 total, 538 passed, 1 failed, 0 errors, 0 skipped, 538 warning events, exit 1.

The only full-suite failure is unchanged `Tests\\Feature\\StudentExcelImportTest::test_valid_excel_import_creates_students_inside_current_school`, classified as the established pre-feature `SQLSTATE 42S22 / MySQL 1054` import defect. The Phase 2 gate parsed the private JUnit report without exporting test values or logs and confirmed no Mobile test failure.

The 538 warning events retain the known optional absent-env-file warning source at `vendor/vlucas/phpdotenv/src/Store/File/Reader.php:73`; no raw warning text, token, password, OTP, or provider data was exported.
