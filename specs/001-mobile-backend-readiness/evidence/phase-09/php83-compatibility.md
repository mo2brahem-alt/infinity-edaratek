# PHP 8.3 — دليل التوافق الآلي للمرشح

مراجعة 2026-11-17 وفق [التفويض المحدد](pm-php83-authorization.txt). تشغيل واحد للحارس الأصلي؛ لا SSH أو HTTP إلى test/production، ولا إعادة بوابات H01/H05/T112.

| الهوية | القيمة |
|---|---|
| candidate / git archive | d57cf9831c3c7123534aedfd34021c7b649968c7 |
| image tag | php:8.3-cli-bookworm |
| immutable RepoDigest | sha256:232331112d3a7c5b98edeeddc3b85f610172f99352c0635fe6b3e166e47062cb |
| actual PHP | 8.3.36 |
| architecture | linux/amd64 (PHP reports x86_64) |
| MySQL | 8.0.46 |
| Composer / Laravel / PHPUnit / Node | 2.11.1 / v12.50.0 / 11.5.51 / 22.23.4 |
| source archive SHA256 | 6f57c2b6bc500e0b5ecd4df74ce0e1f8d97a1fb141e51737d879a446e866bf7d |
| export source fingerprint | 1576630283f45c51564ef59996327f7abddd5045afdbcfdc5f7582355005fe03 |
| disposable Dockerfile before / after SHA256 | 6c92d7dd66bbdd237d202b7fd92c4d40e9e4b6e113d93c1b730a54040cd07a3f / cb0aa538ec49077afcd92a5805d5c917e27c85ef48154922b45ca957c7da9d35 |
| execution started / ended UTC | 2026-11-17T09:52:34.6206043Z / 2026-11-17T10:31:47.1788687Z |

## مصدر التشغيل وحدود التغيير

أنشئ التصدير بـgit archive من المرشح المحدد، دون نسخ العمل غير الملتزم أو .env أو .git أو vendor أو بيانات خادم. تحقق محتوى جميع 1,580 Git blob؛ منها 1567 ملفاً عادياً و13 مدخل symlink قديم للأيقونات. احتفظ ZIP على Windows بنص أهدافها فقط، ولم تُتبع أو تدخل أي public input ينسخه الحارس. لا اختلاف في شيفرة التشغيل المختارة.

تستبعد .gitattributes ملفَي workflow من archive؛ استُعيدا من git cat-file blob عند SHA نفسه لأن الحارس يبصمهما كمرجع سلبي، ولم يُنفذ أي workflow. لم تنسخ ملفات من working tree. التغيير الوحيد في Dockerfile المؤقت: مرجع FROM من PHP8.2 pinned إلى PHP8.3 pinned. بقي Composer lock، extensions، Node، MySQL، الذاكرة512M، الحراس، اختيار الاختبارات وأوامرها كما هي. البصمات الأصلية للمستودع وملف الحارس محفوظة ولم تتغير.

## الأوامر المنفذة

```powershell
docker pull php:8.3-cli-bookworm
docker image inspect php:8.3-cli-bookworm --format "{{json .RepoDigests}} {{.Architecture}} {{.Os}}"
git archive --format=zip --output=<task-temp>/candidate.zip d57cf9831c3c7123534aedfd34021c7b649968c7
Expand-Archive -LiteralPath <task-temp>/candidate.zip -DestinationPath <task-temp>/source
# Resolve every exported blob against the candidate; restore two export-ignore references from that candidate.
# Replace exactly one PHP FROM reference in the disposable Dockerfile only.
pwsh -NoProfile -File <task-temp>/source/tools/testing/run-tests.ps1
```

التحقق المباشر من قاعدة الصورة استخدم docker run --rm --network none --read-only مع PHP_VERSION/MAJOR/MINOR فقط؛أكد8.3.36 قبل تشغيل الحارس. لم يُستدعَ phpunit مبسط خارج الحارس. نتج exit1 النهائي المتوقع من فشل Excel القديم. بنيت صورة الاختبار بنجاح عبر بوابات Composer validate/install-from-lock/platform/extensions وnpm build/lint. وصلت package discovery وschema114/PAT/private-manifest وtransport/source guards إلى PASS.

الحارس أنشأ قاعدة edaratek_test_<random> في MySQL tmpfs على شبكة داخلية، APP_ENV=testing وAPP_DEBUG=false، APP_KEY مصطنع مؤقت، cache/session=array، queue=sync دونworkers، mail=array/fakes، وcache paths منفصلة. فحصت الاختبارات منع HTTP غير المزيف وegress إلىTEST-NET، وغياب provider credentials. بصمات المصدر نجحت قبل الاختبارات وبعدها.

## النتائج الفعلية — المسارات تبقى منفصلة

| lane | passed/total | failed | errors | skipped | warning events | exit | safe timing/summary |
|---|---|---|---|---|---|---|---|
| phase8_focused | 462/462 | 0 | 0 | 0 | 462 | 0 | Time: 03:39.236, Memory: 128.50 MB; OK (462 tests, 5313 assertions) |
| phase7_focused | 76/76 | 0 | 0 | 0 | 76 | 0 | Time: 01:07.725, Memory: 64.50 MB; OK (76 tests, 2519 assertions) |
| phase6_focused | 20/20 | 0 | 0 | 0 | 20 | 0 | Time: 00:47.373, Memory: 56.50 MB; OK (20 tests, 92 assertions) |
| phase5_focused | 100/100 | 0 | 0 | 0 | 100 | 0 | Time: 01:23.560, Memory: 82.50 MB; OK (100 tests, 614 assertions) |
| phase4_focused | 65/65 | 0 | 0 | 0 | 65 | 0 | Time: 01:17.401, Memory: 74.50 MB; OK (65 tests, 544 assertions) |
| phase3_focused | 128/128 | 0 | 0 | 0 | 128 | 0 | Time: 01:10.464, Memory: 80.50 MB; OK (128 tests, 886 assertions) |
| phase2_regression | 16/16 | 0 | 0 | 0 | 16 | 0 | Time: 00:41.409, Memory: 62.50 MB; OK (16 tests, 113 assertions) |
| phase8_artisan_full | 724/725 | 1 | 0 | 0 | 724 | 1 | NOT_REPORTED_BY_EXISTING_RUNNER |
| authoritative_full | 724/725 | 1 | 0 | 0 | 724 | 1 | Time: 06:49.707, Memory: 164.50 MB; FAILURES!; Tests: 725, Assertions: 7066, Failures: 1, PHPUnit Deprecations: 1. |

المجاميع متداخلة ولا تُجمع. مرحلة Phase8 focused هي462/462، وبقية المجموعات المطلوبة أعلاه نجحت دونفشل/خطأ/skip. لا اختبارات WhatsApp متقاعدة أو provider delivery أعيدت. مدة Artisan المنفصلة إن لم يخرجها الحارس تبقى NOT_REPORTED، ولا تُخترع. يمكن مراجعة أوقات metadata وبصمات JUnit ضمن [الدليل المنقح](php83-runtime.json).

## المقارنة بـPHP8.2.35

المصدر المرجعي fbc63a4615f48ac0f832d91945d9652aeabef466، ومصدرالتوافق C مختلفان فيGit لكن ملفات التطبيق والاختبارات والحارس متطابقة؛[source guard](source-guard.json) يثبت162/162. ملفات التصدير المنفذ مرتبطة مباشرة بـC. كلlane أعاد العدد نفسه والـexit والـfailure identity نفسها، مع مقارنةdiagnostics الآمنة وcase-symbol fingerprint للـfull.

| المقارنة | PHP8.2.35 | PHP8.3.36 | فرق failures/errors/mandatory skips |
|---|---|---|---|
| focused security | 462/462 | 462/462 | 0/0/0 |
| full Artisan | 724/725,exit1 | 724/725,exit1 | 0/0/0 |
| full MySQL PHPUnit | 724/725,exit1 | 724/725,exit1 | 0/0/0 |

StudentExcelImportTest::test_valid_excel_import_creates_students_inside_current_school بقي failure واحداً، SQLSTATE42S22/MySQL1054، source line53، بلا اختلاف فيtype/class/method أوdiagnostics المنقحة. هذا **UNCHANGED_PREEXISTING_ACCEPTED_EXCEPTION**، وليس PASS للمجموعة الكاملة. لا تغير لتوقعات أوlock أوsource من أجل نتيجة. تحذيرات dotenv وdeprecation تبقى فيrecords؛ليست أخطاءruntime.

## التنظيف وحفظ المستودع

انتهى compose down --volumes --remove-orphans داخلfinally للحارس. تحقق مستقل بالـproject label أثبت0 containers و0 networks و0 volumes، وزوالscratch الحارس. بعد التحقق من المسارات المطلقة داخلمجلد المهمة المؤقت فقط، حذفexport وcandidate.zip ونسخةDockerfile المؤقتة. أزيلت متغيراتالاعتماد المصطنعة منprocess environment؛لم تحذف أي صورةDocker مشتركة. المصدر الأصلي وtracked Dockerfile وlock والحارس لمتتغير؛التعديلاتالموجودةمسبقاًفيتوثيقPhase9محفوظة.

DATABASE_CHANGED=NO يعني قواعدالتطبيق الأصلية/المشتركة؛كانت migration/fixture writes فيقاعدةMySQL المؤقتةالمصرحبها فقط،ودُمرت معالتنظيف. ROOT_USED=NO يعني لاroot/sudoعلىالمضيف أوأيخادم؛هويةDockerالافتراضيةللحارس لمتُغيّر.

### تحقق البيئة المؤقتة على Windows

أظهر flag التنظيف الأول قيمة false لأن SetEnvironmentVariable(name,null) يترك مدخلاً فارغاً في إصدار Windows/.NET المستخدم. أثبت اختبار محلي بقيمة مصطنعة غير سرية هذا السلوك. انتهت جميع عمليات الحارس الحاملة للاعتمادات؛ ثم تحقق مستقل عبر Env provider وGetEnvironmentVariable أكد غياب أسماء المتغيرات الأربعة تماماً. يحفظ cleanup في php83-runtime.json الملاحظة والـflag الأول ونتيجة التحقق النهائية؛ لم تُقرأ أو تُعرض قيم اعتماد.

أثناء التشغيل، اقتصرت المتابعة على Docker metadata والخرج JSON المنقح وعدّ Test Finished في سجل مؤقت داخل حاوية الاختبار؛ لم تُعرض سطور الأحداث أو raw failures، ولم تُشغّل اختبارات إضافية.

## قرار التوافق فقط

```ini
PHP83_COMPATIBILITY_RUN_RESULT=PASS
PHP83_SOURCE_EXPORT_SHA=d57cf9831c3c7123534aedfd34021c7b649968c7
PHP83_RUNTIME_VERSION=8.3.36
PHP83_IMAGE_DIGEST=sha256:232331112d3a7c5b98edeeddc3b85f610172f99352c0635fe6b3e166e47062cb
PHP83_MATRIX_ONLY_RUNTIME_CHANGED=YES
PHP83_ISOLATION_GUARDS=PASS
PHP83_COMPOSER_VALIDATE=PASS
PHP83_COMPOSER_LOCK_INSTALL=PASS
PHP83_PLATFORM_REQUIREMENTS=PASS
PHP83_REQUIRED_EXTENSIONS=PASS
PHP83_MIGRATION_PROBES=PASS
NEW_PHP83_ONLY_FAILURES=0
NEW_PHP83_ONLY_ERRORS=0
NEW_PHP83_ONLY_MANDATORY_SKIPS=0
PHP83_DISPOSABLE_RUNTIME_CLEANUP=PASS
CHK008=PASS
CHK084=AWAITING_PM_ACCEPTANCE
T119=AWAITING_PM_REVIEW
T120=NOT_STARTED
```

الـBLOCKED وPHASE_9_STARTED=false الثابتان داخلخرجprobe-phase8-gate.php يعودان إلىقالبحارسPhase8التاريخيغيرالمعدّل. لايعيدانفتحH01/H05/T112 المقبولة،ولايدلانعلىنتيجةتوافقالـruntime؛حكمالتوافق هنايراجع الاختباراتالمكتملةوالعزلومقارنةالنسختين فقط.
