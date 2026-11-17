# T119 — قرار PM النهائي: ACCEPTED

بتاريخ 2026-11-17، أصدر PM في الرسالة المباشرة «PM FINAL DECISION — APPROVED» قبول الحزمة الفنية الخاصة بالمصدر `d57cf9831c3c7123534aedfd34021c7b649968c7`، بما يشمل التنفيذ والأمن وruntime والمهاجرات والاختبارات والأدلة. هذا قبول فعلي، وليس استنتاجاً من تفويض التشغيل السابق.

```ini
PM_PHASE_9_DECISION=ACCEPTED
PM_DECISION=ACCEPTED
CHK008=PASS
CHK081=PASS
CHK083=PASS
CHK084=PASS
CHK_RELEASE_SATISFIED=84/84
P0_PASS=12/12
HIGH_PASS=5/5
CONSTITUTION_PASS=14/14
FINAL_SOURCE_EVIDENCE_CONSISTENCY=PASS
T119=PASS
T120=PASS
PHASE_9=CLOSED
MOBILE_BACKEND_READY=YES
MOBILE_BACKEND_READINESS=PASS
FINAL_SOURCE_SHA=d57cf9831c3c7123534aedfd34021c7b649968c7
```

اعتمد PM نتائج PHP 8.3.36 على التصدير نفسه: focused 462/462؛ Artisan 724/725 وMySQL 724/725، فشل واحد وصفر errors/skips لكل مسار. الفشل الوحيد Excel تاريخي ثابت، مسجل كفشل حقيقي واستثناء مقبول غير محجب لجاهزية الموبايل؛ لم يُحوّل إلى PASS. لا إخفاقات أو أخطاء أو mandatory skips جديدة خاصة بـPHP8.3، والتنظيف PASS. [التنفيذ الأصلي](php83-runtime.json) و[تقرير التوافق](php83-compatibility.md) محفوظان دون تعديل.

العدّ النهائي 75 PASS + 9 SUPERSEDED = 84/84؛ P0 يشمل 12 disposition مع حسم T0-12 بالنطاق، وHIGH يشمل أربعة PASS وH04=SUPERSEDED_SCOPE_RESOLVED. لا ادعاء نجاح تسليم WhatsApp أو تنفيذ kill-switch ملغى. WhatsApp وOTP delivery وMeta وn8n واختبارات provider delivery خارج النطاق نهائياً؛ الإشعارات المسموحة in-app/web وmobile push حيث ينطبق مطلب معتمد قائم.

تسجيل [T120](acceptance.md) مصرح به صراحة في القرار نفسه. التفويض الحالي توثيق/حالة فقط: لا كود أو اختبار جديد أو migrations أو DB أو نشر أو production أو restart أو بيئة أو Flutter أو commit/push/merge. الأدلة التاريخية باقية بتاريخها؛ حالات انتظار PM أدناه تمثل الحزمة قبل هذا القرار فقط.

## الحزمة السابقة قبل قرار PM — محفوظة تاريخياً

# T119 — حزمة قرار PM بعد إغلاق توافق PHP 8.3

المرشح `d57cf9831c3c7123534aedfd34021c7b649968c7`. أذن [التفويض](pm-php83-authorization.txt) بتشغيل الحارس المعزول فقط؛ لا يتضمن قبول الجاهزية أو T120.

```ini
PM_DECISION=AWAITING_PM_REVIEW
PROPOSE_MOBILE_BACKEND_READY=YES
CHK_RELEASE_SATISFIED=83/84
CHK084=AWAITING_PM_ACCEPTANCE
T119=AWAITING_PM_REVIEW
T120=NOT_STARTED
MOBILE_BACKEND_READY=AWAITING_PM_DECISION
PHASE_9=CURRENTLY_OPEN
```

| الدليل | النتيجة | المرجع |
|---|---|---|
| توافق PHP 8.3 | PASS؛ PHP 8.3.36؛ focused462/462؛ كل full724/725؛ لا إخفاق/خطأ/تخطٍ جديد؛ التنظيف PASS | [التقرير](php83-compatibility.md)، [السجل](php83-runtime.json) |
| CHK | 84 مقيمة؛ 74 PASS + 9 SUPERSEDED؛ CHK084 وحدها معلقة | [T113](checklist-review.md) |
| P0 | 12/12 dispositions PASS؛ النتيجة السابقة محفوظة دون إعادة تقييم | [T114](p0-closure.md) |
| HIGH | H01/H02/H03/H05 PASS؛ H04 محسوم النطاق؛ دون إعادة فتح | [T115](high-findings.md) |
| Constitution | 14/14 PASS؛ أُغلق IX بدليل 8.3 | [T116](constitution-review.md) |
| المصدر والأدلة | PASS؛ تصدير C؛ lock والحارس ثابتان؛ تغيير PHP base في نسخة مؤقتة فقط | [T117](evidence-manifest.md) |
| التوصية | YES؛ لا عائق فني متبقٍ ضمن النطاق | [T118](readiness-report.md) |

بقي Excel فشلاً تاريخياً بنفس class/method/SQLSTATE42S22/MySQL1054، وفق استثناء PM السابق. كل full يحتفظ بـexit1؛ لا ادعاء full green، ولا تغيير لتوقعات اختبار أو نطاق للحصول على النجاح.

المطلوب من PM: قبول أو رفض الحزمة. لا 84/84 قبل CHK084، ولا تنفيذ T120 أو Flutter أو Git/deployment/integration من هذه التوصية. لم تُفترض موافقة PM.
