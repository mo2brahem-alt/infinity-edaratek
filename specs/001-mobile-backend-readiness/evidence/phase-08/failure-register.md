# T109 — Failure / skip register

Final run42622792050, commit fbc63a4615f48ac0f832d91945d9652aeabef466. Full Artisan and explicit MySQL PHPUnit independently agree:725total /724pass /1failure /0errors /0skips. Focused462/462.

| Category | Item / root cause | Introduced Phase8? | Disposition / reproduction |
|---|---|---|---|
| A Phase8 regression | None in final completed run | No | Zero new failing cases; API76 and P2–6 groups pass. |
| B pre-approved Excel | Tests\\Feature\\StudentExcelImportTest::test_valid_excel_import_creates_students_inside_current_school, tests/Feature/StudentExcelImportTest.php:53; assertion with SQLSTATE42S22/MySQL1054 | No | Same P7/earlier failure. Source/test untouched. Both full lanes retain failure and exit1. In guarded image: php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --filter StudentExcelImportTest. Not a Phase8 acceptance blocker under explicit PM exception. |
| C harness/context, resolved | Run42619442662 missing4 passive docs/workflow references before tests | Yes, test harness evidence path | Guard failed closed, no accepted counts. Explicit reference-copy fix3a0fea7; repeat pre/post hashes PASS. |
| C harness/runtime, resolved | Run42620017956 Artisan child memory exhausted,255/empty XML/no completion | Yes, newly required full Artisan lane | Never accepted as a full run; direct full did not execute afterward. Dedicated image512M default fixed48c34c4; two later dual runs complete. No application memory regression claimed. |
| C authoritative evidence gap, OPEN | H01 actual roots/serving aliases/proxy/CDN cache denial proof and H05 actual environment debug/config/cache safety are UNKNOWN/UNVERIFIED | No, retained from P5/P7 | Blocks T112 even though synthetic tests PASS. Not a test skip or a verified live defect. Closing requires separate authorized operations evidence; do not infer from fixtures or inspect/change shared environments now. |
| D superseded requirement | Old T106/T0-12/H04 WhatsApp worker/OTP/HMAC/provider/delivery matrices and archived WhatsAppIntegrationTest | No, permanent PM decision | Not restored/loaded/executed, not counted as PHPUnit skips or a defect. Supported app/web and generic phone compatibility execute. |

Skipped tests=0, mandatory active P0 skips=0, runtime errors=0, unexplained failures=0. No failure expectation or old test was weakened. Warning events and one PHPUnit deprecation are reported separately: existing dotenv Reader.php:73 warning signature is unchanged; absent host .env in isolated runtime is intentional. They are not reclassified as successes or silently removed. See exact counts/source in full-results.md and validated-runtime.json.

T103 additionally identified absent >100-device/attachment scale evidence. The gap was closed with two new valid cases in fbc63a4, raising723→725, not by lowering a requirement. Final H01/H05 blockers remain independent of Excel and removed WhatsApp. [Validation history](validation-history.json) preserves every attempt.

