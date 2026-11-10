# Phase1 — نتائج baseline

BASELINE_TEST_RESULTS_RECORDED=FAIL.

لم تُشغّل اختباراتLaravel أوPHPUnit. عدد الاختبارات المنفذة=0؛TOTAL/PASSED/FAILED/SKIPPED/WARNINGS/DURATION غيرمتاحة، وليست صفرنتائجنجاح/فشل. لا أسماءاختباراتفاشلة لأنrunnerلميبدأ. أعدادالاكتشافالساكن السابقة فيPhase0 ليستنتائجbaseline.

الأوامرالمخططة داخلحارس tools/testing/run-tests.ps1 بعدتوفيرالمحرك:

`php artisan test --configuration=phpunit.mysql.xml --log-junit=/tmp/artisan-junit.xml`

`vendor/bin/phpunit -c phpunit.mysql.xml --log-junit=/tmp/phpunit-junit.xml`

تُستخدم قاعدةمولدةومؤقتة فقط؛ لا credentials/قواعدمضيفحقيقية. rawstdout/JUnit يبقيان فيtmpfsالحاويةويُحذفان معها؛ ملخصمحددالحقول فقطيُصدر. لا تغييرassertions أوتخطيحالات للحصولعلىأخضر. يجبعندالتشغيلتسجيلwarningcounts/تصنيفاتالفشل، وأي بياناتغيرمتاحة تبقىفجوة.

فحوصPowerShellالبنيوية ليستbaseline؛ نتائجهافيstatic-checks.json. لا يمكن إعلانTestRuntimeReady أوBaselineRecorded منأسماءالملفات/الصيغ وحدها.


## Latest autonomous evidence - 2026-11-08

See [autonomous execution report](autonomous-execution-report.md) and [sanitized final baseline](autonomous-final-baseline.json). This later run supersedes earlier NOT_RUN/runtime-blocked statements for the tested CI profile only. TEST_RUNTIME_READY=PASS; BASELINE_TEST_RESULTS_RECORDED=PASS; execution acceptance=PASS_AWAITING_PM_REVIEW. Baseline 523/48/0 failures/475 errors/0 skipped, 522 warning events, exit 2. Original errors remain unresolved UNKNOWN and unwaived; mobile backend is NOT_READY. T005/stronger checklist proof and formal PM review remain explicit. No Phase 2.


## Superseding formal validation - 2026-11-08

Run42482335970 at debaff7f1c864b9c0d9d54a300092cd7a753261b: 112/112 migrations; schema/factory/boot PASS; T005 all executed negative/transport/shared-state proof PASS; Artisan5/5; Academic6/6; AccountApproval16/16; full523/522 passed/1 known pre-existing import failure/0errors/0skips/522 identified suppressed dotenv warning events, exit1. TEST_RUNTIME_READY, BASELINE_TEST_RESULTS_RECORDED, BASELINE_ENVIRONMENT_VALIDITY, MIGRATION_GATE and PHASE_1_ACCEPTANCE PASS. T005/T012 COMPLETE. See evidence/phase-01/formal-acceptance-after-repair.md (relative from feature root). Prior invalid-environment/partial acceptance entries are historical. No existing test expectations changed; no import-business fix. Latest PM instruction now permits Phase2 on a dedicated non-main feature branch; Phase3 remains out of scope.
