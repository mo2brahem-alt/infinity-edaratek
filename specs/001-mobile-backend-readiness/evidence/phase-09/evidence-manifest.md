# T117 — manifest المصدر والأدلة

المراجع Codex،2026-11-17. Evidence/review مع تشغيل توافق PHP8.3 معزول واحد بتفويض مستقل؛لاSSH/HTTP إلىالخوادم. C=`d57cf9831c3c7123534aedfd34021c7b649968c7` deployed PM-approved؛B=`fbc63a4615f48ac0f832d91945d9652aeabef466` مرجعPHP8.2fullregression؛تلاهبينPHP8.3fullعلىC. التفويض الحالي [محفوظ](pm-phase09-authorization.txt).

## الربط غير القابل للخلط

| العنصر | القيمة |
|---|---|
| branch | 001-mobile-backend-readiness |
| HEAD/candidate/runtime operator SHA | d57cf9831c3c7123534aedfd34021c7b649968c7 |
| PHP 8.2 full regression SHA | fbc63a4615f48ac0f832d91945d9652aeabef466 |
| PHP 8.3 full regression SHA | d57cf9831c3c7123534aedfd34021c7b649968c7 |
| candidate tree | d2399073f24d94eeb5c4b48891cd1648482fc144 |
| reviewed source fingerprint (162 ordered path+SHA records) | cb04019688d322fe9794e011390325d1a376a0d608fd38e7376371fa3aaa5027 |
| initial tracked/untracked status | CLEAN/CLEAN |
| direct SHA256 matches to original B source manifest | 162/162 |
| B→C changed paths | 186 |
| changed application/tests/runtime/config/routes/schema/build/test-runner/dependency/CI files | 0 |

[source-guard.json](source-guard.json) يحتفظ بالأمر guard وinitial artifact hashes وكل مساراتdelta. الـ186 ملفاً تخص specs/docs/SpecKit/skills وREADME و.gitattributes. فحص Git objects يثبت غياب أي delta فيapp/bootstrap/config/routes/resources/database/tests/tools/public/Composer/npm/Docker/phpunit/workflows/.htaccess. فحص bytes مباشر للمصادر الـ162 أعطى162/162؛هذا يمنع افتراض أن تغيرSHA يعني تغيرالبرنامج،ويمنع ادعاءأنC=B.

لم يقع source change بعدfullB يبطل broad regression علىنفس runtime8.2. H01 remediation كانت موجودة بالفعل فيfeature sourceB/C؛اختلاف الخادم القديم12b9d53 عنC أُغلق بالنشر المأذون والـQA النهائي. Runtime .env/config-cache/ACL changes مستقلة عنGit؛نستهلك قبولPM لها،لا نستنتج hashes أسرار أو تطابقruntimeمنGit.

## تسلسل الأدلة المقبول

أعدادfull بالترتيب total/passed/fail/errors/skips؛full exit1 معروف لاzero. المراجع روابطمحليةمقبولة،لم نزُرGitHub أو نكررCI. قبولالمراحل الفني بتاريخها،ثم موافقاتالانتقال المسجلة فيtasks،ثم أذن PM بمراجعة Phase9؛ وبعد توافق PHP8.3 قبل Phase9 صراحة بتاريخ 2026-11-17، كما يسجل [القرار النهائي](pm-decision.md).

| Phase | executed source | CI run/job | actual result | evidence/acceptance | scope |
|---|---|---|---|---|---|
| 1 | debaff7f1c864b9c0d9d54a300092cd7a753261b | 42482335970/128736780096 | 523/522/1/0/0 | [record](../phase-01/formal-acceptance-after-repair.md) | runtime/schema112/guards13/shared-lock;invalid prior baseline superseded |
| 2 | e448618bffe70e10352b52bf20d3ffc6f43b14f5 | 42486583770/128749676130 | focused16/16;full539/538/1/0/0 | [record](../phase-02/acceptance.md) | PAT migration113;native real token/current-state/device/web isolation |
| 3 | 7b8fd762bf7b61325fecf51f1dc5adba015d8221 | 42493350045/128770255571 | focused128/128;full615/614/1/0/0 | [record](../phase-03/acceptance.md) | ACCOUNT_LEVEL/privilege/seeder/reset/error-redaction |
| 4 | ff58fcdf0c71940d02e17905df1233691e0f0928 | 42496584067/128780124189 | focused65/65;full638/637/1/0/0 | [record](../phase-04/acceptance.md) | tenant/parent/transfer/staff operation isolation |
| 5 | ecb6b7fd7d1b06bf7d4575f1b194ce77fa76fb54 | 42598238825/129096063283 | focused100/100;full670/669/1/0/0 | [record](../phase-05/acceptance.md) | private/migration/static representative fixtures;environment limits later closed |
| 6 | d1810ccef7c8c64651098eecd33f0fbe017bfb62 | 42607287769/129126371236 | active20/20;full647/646/1/0/0 | [record](../phase-06/acceptance.md) | WA permanentlyremoved;670-41+10+8=647;31 retiredcases notpass/skip |
| 7 | 8020e8b9363e1d100a1012c9b0a68d245169f2c1 | 42614974604/129152556298 | focused76/76;full723/722/1/0/0 | [record](../phase-07/acceptance.md) | frozen10endpoints/types/errors/rates/pages;priorregressionspass |
| 8 | fbc63a4615f48ac0f832d91945d9652aeabef466 | 42622792050/129178897906 | focused462/462;bothfull725/724/1/0/0 | [record](../phase-08/acceptance.md) | two scale tests723→725;T112 laterclosed byPM operationalproof atC |

بعد Phase 8، استُخدم المرشح C في H01 QA النهائي (14 طلباً)، والمصالحة التاريخية (طلبا HEAD)، ودليل H05 الفعلي (طلب CGI واحد): جميعها مقبولة من PM. يحفظ [ملخص النتائج](accepted-operator-summary.json) بصمات ملفات النتائج الأصلية وحقولاً منقحة فقط. سلاسل Perl/B0/B1/Stage R المهجورة لا تضيف شروط قبول بديلة تتجاوز الدليل التشغيلي النهائي المقبول.

## المصدر وإصدار التشغيل: R9-01 مغلق

أكمل [تشغيل التوافق](php83-compatibility.md) مصفوفة الحارس نفسها على المرشح C باستخدام PHP 8.3.36، بالصورة الرسمية المثبتة بالبصمة sha256:232331112d3a7c5b98edeeddc3b85f610172f99352c0635fe6b3e166e47062cb.

نجحت بوابات Composer والمنصة والإضافات والعزل وschema114 وPAT وprivate manifest. المجموعة المركزة 462/462، وبقية المجموعات المطلوبة كلها ناجحة. كل من full Artisan وMySQL PHPUnit أعاد 724/725 مع فشل Excel التاريخي نفسه، errors0/skips0.

لم يتغير الكود أو الحارس أو الاختيار أو lock. التصدير مرتبط بكل Git blob عند C، والتغيير الوحيد في Dockerfile المؤقت هو قاعدة PHP. الفروق الجديدة في failures/errors/mandatory skips صفر. التنظيف PASS، والمقارنة والبصمات في [php83-runtime.json](php83-runtime.json).

SOURCE_CODE_EVIDENCE_BINDING=PASS؛ FINAL_SOURCE_EVIDENCE_CONSISTENCY=PASS. لا إعادة فتح Phase 8، وتبقى CHK084 ملك PM.

## الاستثناء القديم: Excel

يبقى StudentExcelImportTest::test_valid_excel_import_creates_students_inside_current_school فاشلاً بـSQLSTATE42S22/MySQL1054 منذ Phase 1. يستعمل StudentImportService استدعاء Query Builder whereKey الذي يُفسر كعمود key غير موجود. المالك: backend import maintainer. لا يوجد endpoint استيراد أصلي ضمن هذه الميزة؛ التصنيف السابق غير محجب للموبايل ومقبول من PM، كما يثبت [سجل الفشل](../phase-08/failure-register.md).

لم يتغير المصدر أو الاختبار بين B وC. كل من full Artisan وMySQL PHPUnit يسجل 724/725 وexit1، مع errors0/skips0. **هذا ليس PASS للمجموعة الكاملة.** لم تُعدل التوقعات ولم يُستبعد الاختبار. تحذيرات dotenv Reader.php:73 باقية: 462 حدثاً في focused و724 في كل full، مع deprecation واحدة؛ لا تُسمى runtime errors أو warnings0.

## المهاجرات والاستعادة والعقد

نفذت 114 migration في قاعدة الاختبار المؤقتة على B. أثبت PAT rollback/reapply/repeat حفظ المستخدم غير المرتبط؛ واحتفظ manifest بسجلاته وبالبايتات الخاصة بعد rollback. تغطي 18 حالة private migration الفحص والمقارنة والتوقف وإعادة التشغيل، ويتضمن الدليل مصالحة 504 ملفات مصطنعة. المراجع: [PAT](../phase-02/migration.md)، [private migration](../phase-05/migration-results.md). لا schema جديد في Phases 6–8.

لا ادعاء بنسخ أو استعادة إنتاجية، ولا migration في Phase 9. النشر السابق على test وQA لهما موافقة منفصلة. عقد v1 والنسخة المحمولة لم يتغيرا B→C: [العقد](../../contracts/mobile-api.md)، [جرد Phase 7](../phase-07/contract-inventory.md).

## سجل بصمات الأدلة

يحفظ [artifact-manifest.json](artifact-manifest.json) SHA256 والحجم لكل ملفات Phases 1–8 عند البداية، وبصماتها الحالية بعد إضافة قبول PM، ومدخلات ومخرجات Phase 9. يستثني نفسه وvalidation.json المتولد لاحقاً لتجنب اعتماد دائري. يحفظ source-guard البصمات السابقة للتعديل. هذه snapshot مرتبطة بالمحتوى وليست توقيعاً رقمياً أو commit.

لم يُعدل أي runtime JSON تاريخي أو عدد نتائج. ترتبط ملفات النتائج الأصلية المؤقتة ببصماتها؛ وحُفظت تصنيفاتها الآمنة في accepted-operator-summary.json ليبقى الدليل قابلاً للمراجعة بعد انتهاء ملفات Temp. لم تُقرأ أو تُبصم ملفات أسرار أو .env أو cached configuration.

## تنفيذ Phase 9 الفعلي

- أوامر Git للقراءة فقط: branch --show-current، rev-parse HEAD، status --short/--porcelain=v1، rev-parse 'HEAD^{tree}'، وdiff --stat/--name-only B C.
- rg --files وGet-Content محدد للمواصفة والخطة والدستور وchecklist والمصدر وأدلة القبول.
- Node fs/crypto لمقارنة 162 بصمة، وكتابة التوثيق، وفحص العدّ والروابط والبصمات. النتائج في [validation.json](validation.json).
- فشل أمر tree غير المقتبس أولاً بسبب تحليل PowerShell، ثم نجح مقتبساً. توقف مولد التوثيق عند assertion لتحليل عنوان P0، ثم صُحح التحليل المحلي. لم تتغير اختبارات التطبيق أو توقعاتها.

التوثيق الأول لم يشغّل التطبيق. لاحقاًنفذالتفويض[PHP8.3](pm-php83-authorization.txt) حارسDockerالمعزولبنفسمساريArtisan/MySQL؛لمتُستخدمSSHأوقواعدخادمأوroot. سجلاتالفحصالمحليللعملالجديدفيphp83-validation.json.

```ini
T117=PASS
SOURCE_CODE_EVIDENCE_BINDING=PASS
POST_REGRESSION_EXECUTABLE_DELTA=NONE
FINAL_SOURCE_EVIDENCE_CONSISTENCY=PASS
TECHNICAL_BLOCKERS=NONE
CHK084=PASS
PM_PHASE_9_DECISION=ACCEPTED
T119=PASS
T120=PASS
PHASE_9=CLOSED
MOBILE_BACKEND_READY=YES
```

## لقطة الإغلاق النهائي

[acceptance.md](acceptance.md) يسجل T120 و84/84 بعد قبول PM الفعلي. [closure-validation.json](closure-validation.json) فحص وثائق محلي فقط؛ لا تشغيل تطبيق أو اختبار جديد. حُفظت بصمة artifact-manifest قبل القرار في pre_pm_acceptance_manifest_sha256، مع إبقاء تاريخ المراحل ونتائج PHP8.3 الأصلية ثابتة. تستثني قائمة self_exclusions جميع سجلات validation وartifact-manifest نفسه لتجنب الاعتماد الدائري.
