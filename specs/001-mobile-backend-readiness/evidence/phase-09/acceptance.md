# T120 — قبول جاهزية Mobile Backend وإغلاق Phase 9

التاريخ: 2026-11-17. المرجع الحاكم: [قرار PM النهائي الفعلي](pm-decision.md)، «PM FINAL DECISION — APPROVED»، للمصدر `d57cf9831c3c7123534aedfd34021c7b649968c7`. أُجيز تسجيل T120 بعد استيفاء T113–T119؛ لا تنفيذ إضافياً في جلسة الإغلاق.

```ini
PM_PHASE_9_DECISION=ACCEPTED
CHK008=PASS
CHK081=PASS
CHK083=PASS
CHK084=PASS
CHK_RELEASE_SATISFIED=84/84
P0_PASS=12/12
HIGH_PASS=5/5
CONSTITUTION_PASS=14/14
FINAL_SOURCE_EVIDENCE_CONSISTENCY=PASS
H01=PASS
H02=PASS
H03=PASS
H04=SUPERSEDED_SCOPE_RESOLVED
H05=PASS
T113=PASS
T114=PASS
T115=PASS
T116=PASS
T117=PASS
T118=PASS
T119=PASS
T120=PASS
PHASE_9=CLOSED
MOBILE_BACKEND_READY=YES
MOBILE_BACKEND_READINESS=PASS
FINAL_SOURCE_SHA=d57cf9831c3c7123534aedfd34021c7b649968c7
```

## أساس القبول

| البوابة | الدليل المقبول | النتيجة |
|---|---|---|
| T113 / CHK | [84 بوابة](checklist-review.md) | 75 PASS + 9 SUPERSEDED؛ لا FAIL أو BLOCKED؛ 84/84 مستوفاة |
| T114 / P0 | [إغلاق P0](p0-closure.md) | 12/12 dispositions؛ T0-12 محسوم النطاق مع دليل app/web نشط |
| T115 / HIGH | [النتائج العليا](high-findings.md) | H01/H02/H03/H05 PASS؛ H04 محسوم النطاق؛ 5/5 dispositions |
| T116 / Constitution | [الدستور](constitution-review.md) | 14/14 PASS |
| T117 / المصدر | [ربط المصدر](evidence-manifest.md)، [حارس المصدر](source-guard.json) | اتساق الدليل النهائي PASS؛ مصدر C محدد؛ لا خلط بينه وبين مرجع 8.2 السابق B |
| T118 / المتطلبات | [تقرير الجاهزية](readiness-report.md) | FR/SR/US/SC مربوطة بالأدلة؛ لا CRITICAL/HIGH blocker مفتوح ضمن النطاق |
| PHP 8.3 | [التوافق](php83-compatibility.md)، [سجل التشغيل](php83-runtime.json) | 8.3.36؛ حراس العزل/Composer lock/platform/extensions/migration probes PASS؛ focused462/462؛ تنظيف PASS |
| T119 / PM | [القرار النهائي](pm-decision.md) | ACCEPTED؛ CHK084 PASS |

## الاستثناء المحفوظ وحدود القرار

كل من Artisan وMySQL سجل 724/725، failures=1، errors=0، skips=0، exit=1. فشل StudentExcelImportTest::test_valid_excel_import_creates_students_inside_current_school ما زال FAIL: SQLSTATE42S22/MySQL1054، استثناء تاريخي ثابت مقبول صراحة من PM وغير محجب للموبايل. لا full-green ولا إعادة تصنيفه PASS. NEW_PHP83_ONLY_FAILURES=0، NEW_PHP83_ONLY_ERRORS=0، NEW_PHP83_ONLY_MANDATORY_SKIPS=0.

هذه جاهزية backend للمصدر والنطاق المقبولين؛ ليست تفويضاً لبدء Flutter أو إنتاج أو نشر جديد. WhatsApp وOTP delivery وMeta وn8n وprovider delivery tests مستبعدة نهائياً. H04 محسوم بقرار النطاق وأدلة app/web النشطة، وليس نجاح تكامل ملغى. قيود الأدلة التشغيلية السابقة محفوظة في [التقرير](readiness-report.md)، ولا ادعاء سحب bytes قديمة من العملاء أو شهادة إنتاج/CDN شاملة.

السجلات التاريخية ونتائج التشغيل الأصلية لم تُعد كتابتها. الحالات القديمة المعلقة تعبر عن وقتها وتُستبدل للحالة الحالية بهذا القرار فقط. [فحص وثائق الإغلاق](closure-validation.json) يتحقق من الحالة والروابط والبصمات دون تشغيل اختبارات التطبيق.

```ini
APPLICATION_CODE_CHANGED=NO
DATABASE_CHANGED=NO
MIGRATIONS_EXECUTED=NO
DEPLOYMENT_EXECUTED=NO
PRODUCTION_TOUCHED=NO
ROOT_USED=NO
COMMIT=NO
PUSH=NO
MERGE=NO
FLUTTER_STARTED=NO
```

توقف بعد الإغلاق. تغييرات التوثيق غير ملتزمة؛ أي عملية Git لاحقة تحتاج موافقة PM منفصلة.
