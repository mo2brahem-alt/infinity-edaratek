# Phase 2 PAT migration rehearsal

Executed source: `e448618bffe70e10352b52bf20d3ffc6f43b14f5`.
CI run/job: 42486583770 / 128749676130.

The project had Sanctum installed but no tracked `personal_access_tokens` migration. Phase 2 adds only the standard Sanctum table and `HasApiTokens` on `User`; no separate device table, data backfill, or existing-token rewrite was introduced.

`tools/testing/probe-native-pat-migration.php` runs only after the existing fail-closed disposable MySQL guard. It verified 113 clean migrations, required PAT columns, and an unrelated synthetic user row. It then performed the following on the generated `edaratek_test_<random>` database only:

1. rollback one PAT migration step: exit 0, PAT table absent, unrelated row count unchanged;
2. migrate forward: exit 0, table and required columns restored;
3. repeat migrate: exit 0, migration record remains present.

`NATIVE_PAT_MIGRATION_GATE=PASS`. No host, shared, staging, or production database was read or modified.
