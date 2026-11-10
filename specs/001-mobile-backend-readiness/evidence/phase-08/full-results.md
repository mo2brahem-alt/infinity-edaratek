# T108 — Executed results

Final source: `fbc63a4615f48ac0f832d91945d9652aeabef466`; [CI run42622792050](https://github.com/mo2brahem-alt/infinity-edaratek/actions/runs/42622792050), job129178897906. CI conclusion **failure**, raw final exit1 preserved for the known Excel assertion. PHASE_8_TEST_EXECUTION=PASS certifies code/synthetic evidence only; FULL_SECURITY_REGRESSION_GATE_PASS=BLOCKED.

| Lane | Pass | Total | Fail | Runtime errors | Skips | Exit |
|---|---:|---:|---:|---:|---:|---:|
| diagnostic_subset | 5 | 5 | 0 | 0 | 0 | 0 |
| diagnostic_subset | 6 | 6 | 0 | 0 | 0 | 0 |
| diagnostic_subset | 16 | 16 | 0 | 0 | 0 | 0 |
| phase8_focused | 462 | 462 | 0 | 0 | 0 | 0 |
| phase7_focused | 76 | 76 | 0 | 0 | 0 | 0 |
| phase6_focused | 20 | 20 | 0 | 0 | 0 | 0 |
| phase5_focused | 100 | 100 | 0 | 0 | 0 | 0 |
| phase4_focused | 65 | 65 | 0 | 0 | 0 | 0 |
| phase3_focused | 128 | 128 | 0 | 0 | 0 | 0 |
| phase2_regression | 16 | 16 | 0 | 0 | 0 | 0 |
| phase8_artisan_full | 724 | 725 | 1 | 0 | 0 | 1 |
| authoritative_full | 724 | 725 | 1 | 0 | 0 | 1 |

The 462-case focused selection is32 input paths expanding to58 classes, frozen in tools/testing/phase8-scope.json. It includes positive/negative authorization, real PAT lifecycle, account/role removal, tenant/foreign identifiers, validation, private byte access, fixture migration/static denial, app/web notifications, schema, safe seeding and API error/shape/rate regressions. Full runners both execute725 cases: original723 plus two mandatory P2 scale cases. These lane totals overlap and must not be added.

## Runtime / checks

PHP8.2.35, Composer2.11.1, Laravel12.50.0, PHPUnit11.5.51, Node22.23.4, MySQL8.0.46, utf8mb4/utf8mb4_0900_ai_ci, strict SQL modes. Dependencies/images are digest/lock pinned. External dependency network exists at image build only; runtime uses ephemeral internal MySQL, test disks/tmpfs, fake/array mail, sync queue without workers, no provider credentials. Host Laravel was not booted.

Composer validation/strict validation/platform checks exit0. Image build enforces npm ci + npm run build + nonempty Vite manifest, PHP lint of all app/routes/tests and testing probes, and sh syntax. Reaching test stages proves these mandatory image steps succeeded. Fresh schema114/114, factory insert and package discovery PASS; native-PAT rollback/reapply/repeat preserves unrelated synthetic user; private manifest rollback retains existing record and repeat is idempotent. No new migration/table/column/index/backfill/real record changes.

Image-only512M PHP default is verified before child processes, max_execution_time0; no cgroup OOM kill increase. This fixes the rejected second run, not application code. Local harness SelfTest rejected4 unsafe profiles; PrepareOnly verified4 passive reference hashes, forbidden entries0, no Docker/Laravel startup and task-owned scratch cleanup. PHP/Docker were unavailable locally; actual PHP/MySQL/build checks are CI evidence.

Focused direct PHPUnit summary:60.150seconds,128.50MB,462tests/5313assertions. Full direct PHPUnit:115.270seconds,166.50MB,725tests/7066assertions; one PHPUnit deprecation. Artisan full completed with valid XML/events, exit1; its wrapper emitted no duration summary, so no separate duration is invented.

Warnings are not zero: focused462 and each full runner724 PHP warning events, all same existing dotenv Reader.php:73 signature `29850f1c3c9f0a5f7f50bfc840a3d002d63b04b010f143ff2726246b87a883fd`. These are counted warning events, not runtime errors or extra tests. No new warning source is reported; see [failure register](failure-register.md).

## Integrity / completed-run identity

Pre/post source guards PASS162 immutable sources +2 known image overrides (tests/TestCase.php and phpunit.xml). Original host tests/contract remain unchanged. Both full runners have identical sorted class::method multiset SHA256:
`558917c93b1a30c27ea30857188fb1a7ca64dc85833737e58382a7c2c09aa290`.

```json
[
  {
    "lane": "phase8_focused",
    "junit_sha256": "af62a621bbad9edbc3d52dfda586b70c72723132821f3cef2bc9355fdd5f1a63",
    "completed": true,
    "execution_classification": "COMPLETED_REQUIRES_EXIT_CHECK",
    "exit": 0
  },
  {
    "lane": "phase8_artisan_full",
    "junit_sha256": "43031398f2f0e930d403060e966469f6069e67477bb4e3e771c4118e06ec481a",
    "completed": true,
    "execution_classification": "COMPLETED_REQUIRES_EXIT_CHECK",
    "exit": 1
  },
  {
    "lane": "authoritative_full",
    "junit_sha256": "c7cff6066c0227763a001b6749e19c8f8966e0c61ffe04b29fec7f05ca413193",
    "completed": true,
    "execution_classification": "COMPLETED_REQUIRES_EXIT_CHECK",
    "exit": 1
  }
]
```

## Scale proof

| Prepared list | Synthetic rows small → large | Queries small → large | Budget | Max returned | Large bytes | Timings ms |
|---|---|---|---:|---:|---:|---|
| Native devices |3 →137 |7 →7 |9 |100 |17517 |6.142 →19.003 |
| Task attachment metadata / multiple histories |3 →139 |26 →26 |28 |100 |15649 |17.077 →26.372 |

Two new LargePreparedListsTest cases assert default25/max100, exact DTO keys, subsequent/empty pages, foreign/expired/invalid-parent exclusion before totals and no history transcript. Timings are observations, not portable SLA claims. Existing Phase7 task1001 profile remains within its19-query budget; full profile is in validated-runtime.json.

## Reproduction

From a machine with PowerShell/Docker, use the repository wrapper; it creates only its validated disposable scratch/database:
```powershell
./tools/testing/run-tests.ps1 -SelfTest
./tools/testing/run-tests.ps1 -PrepareOnly
./tools/testing/run-tests.ps1
```

The final command intentionally returns1 for known Excel. Never run host Artisan against .env. Exact focused32 paths are in tools/testing/phase8-scope.json and the literal invocation in tools/testing/run-baseline.sh. Inside the guarded test image, after preflight:
```sh
php tools/testing/probe-phase8-source.php pre_tests
php -d memory_limit=512M artisan test --colors=never --log-junit=/tmp/baseline-junit.xml --log-events-verbose-text=/tmp/baseline-events.log
php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --log-junit=/tmp/baseline-junit.xml --log-events-verbose-text=/tmp/baseline-events.log
php tools/testing/probe-phase8-source.php post_tests
```

The wrapper serializes lanes, parses completion/exit/XML/events and preserves separate focused/Artisan/full reports before probe-phase8-gate.php. Do not invoke the final gate without those completed artifacts. [All four attempts](validation-history.json) are retained, including rejected incomplete proof; [validated runtime](validated-runtime.json) contains sanitized measured records. No raw private logs/JUnit were exported.

