# Quickstart: Isolated Test Runtime and Validation

> **قرار PM نافذ 2026-11-09:** WhatsApp خارج المشروع نهائياً. الإشعارات app/web فقط، وpush حيث يوجد مطلب معتمد قائم؛ لا delivery tests أو provider configuration. أي نص WhatsApp/OTP/control/kill-switch قديم أدناه HISTORICAL/SUPERSEDED وليس شرط قبول نشطاً. المرجع docs/notification-policy.md وevidence/phase-06/scope-decision.md. لا staging/production أو Phase9؛ actual aliases/CDN تبقى UNKNOWN.

> PM2026-11-09: Phase7 APPROVED; Phase8 T103-T112 authorized with feature-branch commits/pushes and isolated CI. STOP at Phase8 PM review. No Phase9, deployment, real data/provider or shared-environment action.

## Latest Phase8 result - 2026-11-09

T103-T111 complete; T112 BLOCKED. Final fbc63a4615f48ac0f832d91945d9652aeabef466, CI42622792050/job129178897906: focused462/462; both full runners724/725, known Excel only, errors0/skips0. New2 P2 scale cases explain the increase. PHASE_8_TEST_EXECUTION=PASS but FULL_SECURITY_REGRESSION_GATE_PASS=BLOCKED: authoritative H01 serving/cache and H05 actual-environment safety proof remain unverified. Detailed report: [Phase8](evidence/phase-08/EDARATEK_PHASE_8_EXECUTION_REPORT.md); [acceptance](evidence/phase-08/acceptance.md). Reproduce only with ./tools/testing/run-tests.ps1 in the guarded disposable image; no host Laravel or shared environment. T112 stays unchecked; STOP at Phase8 PM review; no Phase9. MOBILE_BACKEND_NOT_READY. Earlier status paragraphs below are historical checkpoints, superseded by this latest status.

## الحالة الأحدث — Phase7، 2026-11-09

اعتمد PM المرحلة السادسة وصرّح بالسابعةT089–T102. أُنجزت السابعة تقنياً على8020e8b9363e1d100a1012c9b0a68d245169f2c1، CI42614974604/job129152556298:76/76 مركزة، انحداراتP2–P6 ناجحة، الكامل722/723 بفشلExcel السابق فقط، errors0/skips0. MOBILE_API_CONTRACT_PASS=PASS؛ PHASE_7_PASS_AWAITING_PM_REVIEW. العقد الفعلي contracts/mobile-api.md والنسخة المحمولة docs/mobile-api-v1.md؛ دليل التنفيذ evidence/phase-07/EDARATEK_PHASE_7_EXECUTION_REPORT.md. لا host Laravel boot؛ التشغيل الفعلي عبر tools/testing/run-tests.ps1 داخل CI المعزول. aliases/CDN UNKNOWN، REAL_FILES_MIGRATED=0، REAL_WHATSAPP_SENDS=0، PHASE_8_STARTED=NO، MOBILE_BACKEND_NOT_READY. لاT103 قبل اعتماد PM.

## سجل Phase3 السابق — 2026-11-08

المصدر `7b8fd762bf7b61325fecf51f1dc5adba015d8221` على `001-mobile-backend-readiness`؛ workflow الاختبار المعزول يتتبع فرع الميزة فقط. التشغيل42493350045 اجتاز مركزة Phase3 بـ128/128 وnative16/16؛ الكامل615/614/1 failure سابق/0errors/0skips، exit1 محفوظ. البوابات ACCOUNT_APPROVAL_HARDENING وAUTHORIZATION_HARDENING وPHASE_3_ACCEPTANCE كلها PASS. انظر evidence/phase-03/acceptance.md. انتهى التفويض عند هذه المرحلة؛ لا Phase4، ولا main/deploy/بيئة حية/رسائل حقيقية. التعليمات والأعداد القديمة أدناه سجل تاريخي.

## Current verified Phase 1 execution - 2026-11-08

The dedicated CI branch ci/mobile-backend-baseline now builds and runs the isolated runtime. [Executed evidence](evidence/phase-01/autonomous-execution-report.md): run 42472192069, commit fab8eea5ecb2da1188ba0d9fa34070b68e4818f5. Full suite completed once: TOTAL 523, PASSED 48, FAILED 0, ERRORS 475, SKIPPED 0, WARNINGS 522, TEST_EXIT_CODE 2. Red tests were recorded without source/assertion changes. Runtime and baseline recording PASS; mobile backend remains NOT_READY and Phase 2 is not authorized.

PHPUnit exhausted the old 128M limit. Only the isolated test process/profile now uses bounded 512M; actual completed peak was 130.94 MB.

On an approved local Linux Docker engine, the existing harness command is:

```powershell
./tools/testing/run-tests.ps1 -SelfTest
./tools/testing/run-tests.ps1 -PrepareOnly
./tools/testing/run-tests.ps1 -ValidateOnly
./tools/testing/run-tests.ps1
```

Local PHP/Docker remain unavailable here; CI execution is the actual runtime proof. The wrapper copies a filtered OS-temp context, rejects environment/auth/key files and reparse points, generates Vite assets using pinned Node and npm ci, and provisions unique MySQL tmpfs on an internal network without DB ports or host data mounts. Dependency download uses build networking only. Exact Compose project and validated task-owned temporary directory are removed in finally. Never use host/shared/staging databases or invoke deploy.yml.

The one authoritative in-container baseline command is:

```sh
php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --log-junit=/tmp/baseline-junit.xml --log-events-verbose-text=/tmp/baseline-events.log
```

The actual PHPUnit process exit is preserved. A valid red baseline still makes the CI job red; recording PASS does not force exit zero. Only sanitized JSON records leave the container. Raw logs/XML stay in ephemeral tmpfs. Completion event and process exit must agree; XML must parse with safe settings and contain more than zero testcase records. No byte repair or regex-count substitution is accepted. Fatal diagnostics expose static categories and source test symbols only.

A push to the dedicated CI branch triggers baseline only; deployment listens to main. New future pushes/changes remain subject to the active PM scope; no Phase 2, deployment or real integration sends. The current authorized execution has stopped at the Phase 1 PM boundary. Do not infer permission from these instructions.

T005 and stronger checklist proof remain explicitly open as described in the execution report. CHK006/074 error-redaction evidence is not certified by Phase 1. Unknown staging/production versions remain unknown.

## Historical planning/first-attempt notes (superseded by executed evidence above)


Planning guide, not an executed setup. No PHP/Composer/Docker runtime currently detected; WSL is not installed. No application tests ran during planning. Commands below are future validation in an approved isolated runtime, never the existing working directory with real .env/cache/storage.

## Phase1 execution update — 2026-11-07

Test-only infrastructure now exists under tools/testing/; it is NOT container-validated or operational on this host. No Laravel/PHP/Composer/database baseline ran. Current Phase1 gate FAIL: Docker/Podman absent, WSL not installed, current session nonadministrator, Windows CIM does not report enabled virtualization. No host installation/reboot/firmware change performed. Manual-only .github/workflows/mobile-backend-tests.yml is prepared but not published or executed; fallback was not silently selected. Never invoke deploy.yml.

Safe checks performed here: PowerShell parser, run-tests.ps1 -SelfTest (four unsafe profiles rejected, zero DB operations), -PrepareOnly (filtered temporary context with no .env/cache/secrets/reparse points, then verified task-owned cleanup), and -ValidateOnly (fails closed before boot when Docker absent). These are infrastructure checks, not application baseline tests.

After local prerequisites are made available, run from repository root:

```powershell
./tools/testing/run-tests.ps1 -SelfTest
./tools/testing/run-tests.ps1 -PrepareOnly
./tools/testing/run-tests.ps1 -ValidateOnly
./tools/testing/run-tests.ps1
```

The last command remains UNVERIFIED. It builds from a filtered disposable context, uses a local Linux Docker endpoint only, creates unique disposable MySQL on an internal network without host mounts/DB port, then runs Composer/platform/isolated Laravel and both Artisan/MySQL PHPUnit lanes. Source tests/TestCase.php stays untouched: a dedicated image-only superclass adds mail/notification fakes and HTTP stray-request prevention. Other original tests/assertions remain unchanged. Existing compiled public build assets are copied; private uploads/storage are excluded. No baseline failure is fixed by assertion changes.

Image metadata (not executed versions): PHP8.2.35, Composer2.11.1 binary copied from its separate image, MySQL8.0.46; linux/amd64 manifests pinned by digest in test Dockerfile/compose. Apt packages still resolve at image build; record actual image ID/package/platform results before reproducibility acceptance. Raw test diagnostics/JUnit remain ephemeral inside the container; only restricted class/method/type/count/time summaries are exported, with no failure bodies/dataset labels. Missing reports/warning details remain explicit gaps. Runtime cleanup is restricted to generated Compose project and validated direct OS-temp child.

See [Phase1 evidence](evidence/phase-01/acceptance.md). CHK006/074 application debug-true/500 sanitization remains later work; Phase1 does not prove it. D1/C1/V1 remain preconditions for their later phases. Stop after Phase1 regardless of outcome.

## Runtime choice and prerequisites

PRIMARY_TEST_RUNTIME: dedicated pinned PHP8.2 Linux test image + Composer2/dev dependencies + disposable MySQL8.0 on internal network. FALLBACK_TEST_RUNTIME: ephemeral Linux CI runner with identical pinned image/DB and separate test workflow; never invoke existing deploy.yml. SQLite memory is supplemental because MySQL-specific tests otherwise skip.

Before provisioning, authorized Phase1 validates Windows build, architecture, RAM/virtualization, supported Docker Desktop Linux backend and WSL2 prerequisites using current official [Docker requirements](https://docs.docker.com/desktop/setup/install/windows-install/). Read-only checks: `where.exe php`, `where.exe composer`, `where.exe docker`, `wsl --status`, `wsl --list --quiet`; execute version commands only when binaries exist. Nothing is installed by this document. If host prerequisites cannot be met, use separately authorized ephemeral CI access, not production/staging.

After provision, validate `docker version`, `docker compose version`, image digests, `php -v`, `composer --version`, `php -m`, DB version/charset/collation and `composer check-platform-reqs`. Pin exact patches/digests after compatibility checks; MySQL8.0 is test baseline, live version unknown. Use project PHP^8.2/locked dependencies, do not upgrade framework. PHP extensions must cover lock requirements: json/ctype/filter/hash/mbstring/openssl/session/tokenizer/fileinfo/iconv/dom/libxml/pcre/phar/xml/xmlwriter, PDO/pdo_mysql, pdo_sqlite/sqlite3, zip and GD. Explicitly verify zip/GD to prevent import/image tests silently skipping. No Redis requirement initially; concurrency uses actual shared test DB/locks.

## Isolation before any Laravel boot

The Phase1 harness is now authored under tools/testing/ but remains container-unverified. Its operational acceptance still requires proof of the following safeguards:

1. Create a task-owned scratch copy from an explicit source allowlist, including current approved dirty source as needed. Do not use git checkout/reset/stash. Exclude .env*, .git, vendor, node_modules, real storage data, bootstrap/cache manifests and symlinks; preserve host source unchanged. Provide writable scratch storage/cache/vendor only. Existing Dockerfile COPY . . is unsafe for this purpose.
2. Supply process environment BEFORE Composer scripts/package discovery/Artisan: APP_ENV=testing, APP_DEBUG=false for security contracts, synthetic APP_KEY, DB_HOST internal test service, DB_DATABASE uniquely named edaratek_test_<run>, dedicated disposable credentials, array cache/session/mail, sync queue, null broadcast, N8N_WHATSAPP_ENABLED=false and no integration credentials. Set APP_CONFIG_CACHE, APP_EVENTS_CACHE, APP_PACKAGES_CACHE, APP_ROUTES_CACHE, APP_SERVICES_CACHE to scratch-only paths. Host phpunit.mysql.xml alone is insufficient because it can inherit connection credentials.
3. Pre-bootstrap assert testing env, DB host/name allowlist, scratch paths, absent host .env/config cache, fake providers and network restrictions. Abort before RefreshDatabase/migrate on mismatch. AppServiceProvider maintains public storage links outside unit tests, so safe package discovery also needs this isolation. Do not run artisan config:clear/route:list in real repository as a setup shortcut.
4. Install locked dev dependencies with Composer --no-scripts first in build/scratch space, then permit isolated package discovery after guards. Dependency-fetch network is build-only; execution network allows internal test DB/server only, no provider egress. No deploy/SSH/cloud secrets. Never use composer update or --no-dev for tests.
5. Start fresh disposable MySQL storage with no host DB mounts/published DB port. Internal test network only. Every test uses synthetic identities/files/credentials. HTTP fakes plus preventStrayRequests block unexpected transport; queue fakes or controlled direct job execution, no real provider. Concurrency tests use separate connections/processes, not only array locks.

## Commands inside validated scratch runtime

These commands are genuine tool commands, but the surrounding harness/image/services must first pass Phase1 runtime validation. They are NOT commands to execute directly in D:\edaratek\public_html with host .env/cache.

```sh
php -v
composer --version
php -m
composer install --no-interaction --prefer-dist --no-scripts
composer check-platform-reqs
php artisan package:discover --ansi
vendor/bin/phpunit -c phpunit.mysql.xml
vendor/bin/phpunit -c phpunit.xml
```

mysql config's DB_DATABASE must be overridden to task-owned scratch DB by validated harness, never inherited/default shared database. Both lanes use externally enforced cache/env isolation; phpunit.xml is not safe alone. No `migrate:fresh` instruction targets a shared database. PHPUnit RefreshDatabase may destroy only validated disposable data. CI fallback executes the same commands/image/guards, with no permissions or linkage to deployment workflow. Optional frontend regression build uses `npm ci`/`npm run build` only in scratch after explicit Phase1/8 scope; this planning step runs neither.

## Validation scenarios and expected results

| Stage | Execution after implementation | Expected evidence |
|---|---|---|
| Baseline | Full MySQL suite, optional SQLite | Exact commands/version/digests, pass/fail/skip counts; MySQL-required tests execute; no real providers |
| Native auth | Proposed NativeLoginTest/NativeTokenLifecycleTest/NativeCurrentStateTest | Real PAT issue, expiry/revoke, cookie fallback denied, role/account changes reflected, browser session retained |
| Approval | AccountApprovalMatrixTest | All accepted A-04 rows/order/legacy cases; no pending/rejected token/bypass; staff+teacher retained |
| Tenant | TenantEndpointMatrixTest/StaffTaskIsolationTest/ForeignRelationshipTest/TenantInjectionTest | Two schools across every affected method; counts/filters/IDs/transfer/quick-setup protected |
| Files | PrivateAttachmentMigrationTest/StaticPrivateUrlTest | Authorized bytes, leave permission, foreign404, both old aliases/static copies/link denied, logo public, crash/retry safe |
| Queue | WhatsAppExecutionGateTest with HTTP fake | All stale/old/delayed/retry/direct/race cases denied with zero HTTP; terminal/uncertain states correct |
| Contract | Proposed MobileApiContractTest | Every endpoint/error schema, pagination/type/date/Arabic/code, Retry-After, no secrets/trace/GET side effect |
| Regression | Full existing suite plus affected web paths | Login/session/CSRF/approval/task/download/notification/subscription/association and callback regressions pass |

Use `vendor/bin/phpunit -c phpunit.mysql.xml --filter <implemented-test-class>` for targeted checks once those classes exist; do not claim tests exist today. Follow with full suite at Phase8, not repetitive full runs after unchanged documents. Capture rate limits/time transitions with fake clock and concurrency with independent DB connections. Static file test server uses only synthetic files, internal network and representative alias/symlink/copy topology.

## Evidence and readiness

Record source fingerprint (including approved preexisting dirty files), PHP/extensions/Composer/package lock/image/DB versions, synthetic env profile without secrets, exact commands, exit codes, pass/fail/skips, P0 mapping, regression differences, fake-provider assertions and blockers. No skipped mandatory P0 counts as pass. Unknown live config/production file topology cannot be represented as verified. If environment-specific proof is required and unavailable, keep blocker open without touching that environment.

MOBILE_BACKEND_READY only after plan.md final gate and PM evidence acceptance. Runtime provisioned alone is insufficient. Cleanup validates absolute task-owned paths/volumes before removal, affects disposable fixtures only, never host source/storage or shared DB. STOP after each approved phase; no Flutter, deployment, real send or production action follows this guide automatically.


## Superseding PM correction - 2026-11-08

Prior execution-only Phase 1 PASS is not formal acceptance. BASELINE_ENVIRONMENT_VALIDITY=NEEDS_VERIFICATION; PHASE_1_ACCEPTANCE=PENDING. The systemic 475 database errors and 522 PHP warnings require migration/schema/RefreshDatabase validation, and T005/T012 remain unfulfilled. Do not use older PASS statements to start Phase 2. Follow the current migration/schema diagnostics; preserve memory_limit=512M.


## Systemic baseline invalid - 2026-11-08

[Sanitized diagnosis](evidence/phase-01/systemic-validation-report.md): isolated clean MySQL migration probe failed at migration 2026_03_11_100200_create_school_teaching_assignment_classrooms_table.php, SQLSTATE 42000 / driver 1059. Static implicit foreign constraint names are 75 and 65 characters (MySQL maximum 64). Three unrelated RefreshDatabase test groups reproduce the same error before assertions. This is a genuine unchanged repository migration defect; no migration/application/test fix is authorized by the current Phase 1 repair path. BASELINE_ENVIRONMENT_VALIDITY=FAIL; PHASE_1_ACCEPTANCE=FAIL; T005/T012 incomplete. New full baseline and Phase 2 NOT_STARTED. Prior execution-only PASS statements are superseded.


## Superseding formal validation - 2026-11-08

Run42482335970 at debaff7f1c864b9c0d9d54a300092cd7a753261b: 112/112 migrations; schema/factory/boot PASS; T005 all executed negative/transport/shared-state proof PASS; Artisan5/5; Academic6/6; AccountApproval16/16; full523/522 passed/1 known pre-existing import failure/0errors/0skips/522 identified suppressed dotenv warning events, exit1. TEST_RUNTIME_READY, BASELINE_TEST_RESULTS_RECORDED, BASELINE_ENVIRONMENT_VALIDITY, MIGRATION_GATE and PHASE_1_ACCEPTANCE PASS. T005/T012 COMPLETE. See evidence/phase-01/formal-acceptance-after-repair.md (relative from feature root). Prior invalid-environment/partial acceptance entries are historical. No existing test expectations changed; no import-business fix. Latest PM instruction now permits Phase2 on a dedicated non-main feature branch; Phase3 remains out of scope.
