# Specification Quality Checklist: Mobile Backend Readiness

> PM2026-11-09 supersedes every historical WhatsApp/OTP delivery/control/kill-switch requirement in this feature. Active notification scope is app/web only (push only where an already approved requirement exists); see docs/notification-policy.md and evidence/phase-06/scope-decision.md. The quality checks below are historical review evidence, not execution or permission to restore the removed integration. General authorization, redaction and phone-change compatibility remain required.

**Purpose**: مراجعة اكتمال وجودة المواصفة قبل أي اعتماد للتخطيط أو التنفيذ.
**Created**: 2026-11-07
**Feature**: [spec.md](../spec.md)
**Review ownership**: مراجعة ذاتية ضمن speckit-specify؛ تظل موافقة PM مستقلة.
**Marker semantics**: [x] يعني استيفاء جودة المتطلب فقط، وليس تنفيذًا أو نجاح اختبار Backend.

## Content Quality

- [x] لا تفاصيل تنفيذ داخلية غير لازمة؛ Laravel/Vue وHTTP/versioning وdisabled flag قيود طلبها المستخدم، دون اختيار framework عميل أو مخطط DB أو وسيلة تخزين tokens أو runtime بعينه.
- [x] التركيز على قيمة المستخدم وحدود بيانات المدرسة ونتائج القبول.
- [x] الوصف بالعربية للمعنيين مع رموز العقود والمعرفات اللازمة للتتبع.
- [x] جميع أقسام القالب الإلزامية موجودة وبالترتيب المطلوب.

## Requirement Completeness

- [x] لا علامات NEEDS CLARIFICATION متبقية: توجد علامة واحدة مقصودة في A-04.
- [x] كل المتطلبات غير ملتبسة وقابلة للاختبار بالكامل: FR-009/SR-006 وT0-09 تنتظر قرار سياسة الأدوار المركبة؛ بقية الشروط لها نتائج ظاهرة.
- [x] معايير النجاح قابلة للقياس: SC-001–010 تحدد100% من المصفوفة، وصفر تسريب/إرسال، وعدم تخطي الاختبارات الإلزامية.
- [x] معايير النجاح تصف نتائج مستخدم/سلامة/تحقق دون فرض طريقة تنفيذ أو packages أو performance targets غير مؤيدة.
- [x] سيناريوهات القبول محددة:14 قصة و28 حالة Given/When/Then؛ لا ادعاء تنفيذها.
- [x] الحالات الطرفية محددة: EC-01–22 تشمل revocation/transfer/files/queue/pagination/rate/duplicates.
- [x] النطاق محدود: native readiness؛ لا Flutter أو تحويل كل مسارات الويب إلى REST.
- [x] الافتراضات والتبعيات والقرارات والتأجيلات معلنة في Assumptions والتتبع وجدول فجوات الاختبارات.

## Feature Readiness

- [x] جميع المتطلبات لها قبول نهائي غير معلق: خريطة التغطية أدناه موجودة، لكن A-04 يمنع اعتماد قبول FR-009/SR-006/T0-09 حتى القرار.
- [x] قصص المستخدم تغطي الدخول والرفض والموافقة والمدرسة والمهام والملفات والأصول العامة والتعطيل والعقد والويب والإلغاء والاختبارات.
- [x] النتائج المعرفة تحقق أهداف الميزة عند تنفيذها؛ SC-001–010 مرتبطة بالبوابة ولا تعلن اكتمالها اليوم.
- [x] لا تسرب إلى Flutter implementation أو إصلاح تلقائي أو provisioning/deploy أثناء specification.

## Audit, Constitution and Safety Review

- [x] Critical: لا واحدة مؤكدة بحسب التقرير؛ كل H01–H05 ممثل كblocker، دون إسقاط الشروط أو افتراض إصلاح.
- [x] جميع M01–M07 وL01–L04 لها متطلبات أو تأجيل صريح معلل؛ L02 مؤجل فقط لعدم إضافة feed موبايل هنا.
- [x] جميع12 مجموعةP0 موجودة T0-01–12؛ فجوات P1/P2 مفصلة بين المطلوب للمتأثر والمؤجل لوحداته.
- [x] مبادئ الدستورI–XIV ممثلة؛ فرق المواصفة مقابل الجاهزية النهائية ظاهر، وحدود المرحلة/الموافقات مستقلة.
- [x] الإنتاج وstaging والintegrations وreal sends خارج العمل الحالي؛ المصدر والأعمال السابقة لا تتغير؛ لا مراحل لاحقة تُشغّل.

## Functional Requirement Acceptance Map

الرموز التالية إحالات قبول وليس جدول تنفيذ أو tasks.

| Requirements | دليل القبول/التحقق في المواصفة |
|---|---|
| FR-001 | US12؛ FR-034؛ SC-009؛ لا قاعدة بيانات مباشرة أو backend موازٍ |
| FR-002 | US01-A؛ T0-01/02؛ EC-01؛ SC-001 |
| FR-003 | US02-A؛ SR-022؛ SC-001 |
| FR-004 | US01-A/04-A؛ T0-07/08؛ SR-005 |
| FR-005 | US01-B/13-A؛ T0-02؛ SC-007 |
| FR-006 | US13-A؛ EC-02؛ T0-02 |
| FR-007 | US13-B؛ SR-024؛ T0-02/03 |
| FR-008 | EC-01–03؛ T0-02/03؛ جدول401 |
| FR-009 | US03-A/B؛ T0-01/03/09؛ قبول multi-role النهائي معلق A-04 |
| FR-010 | US02-B؛ EC-19؛ جدول429 |
| FR-011 | SR-022 يفرض اختبار تساوي نتائج forgot-password وحدوده؛ إعادة التعيين الصحيحة محفوظة؛ regression US12 |
| FR-012 | US04؛ EC-07/08؛ P1 suspended quick-setup؛ جدول403/409 |
| FR-013 | US04-B/05-B؛ T0-07؛ EC-09 |
| FR-014 | US05-A/B؛ T0-06؛ EC-09 |
| FR-015 | US06-A/B؛ T0-05؛ SC-003 |
| FR-016 | US06/05-A؛ T0-04/05؛ SC-002/003 |
| FR-017 | US11-B؛ EC-18؛ P1 defaults/caps؛ SC-006 |
| FR-018 | US05-B/06-B؛ EC-09/18؛ SC-002/003 |
| FR-019 | US09-A/B؛ EC-14؛ T0-10 |
| FR-020 | US07-A؛ P1 private download؛ SC-004 |
| FR-021 | US08-A/B؛ T0-10؛ جدول401/403/404 |
| FR-022 | US07-A/08-A؛ EC-21؛ SR-017/020 |
| FR-023 | EC-11/12؛ T0-10؛ SC-004 |
| FR-024 | US09-A؛ EC-14؛ SC-004 |
| FR-025 | US08-A/09-B؛ EC-13؛ T0-10 |
| FR-026 | P1 multipart/MIME/size/private-download؛ SR-011؛ T0-10 |
| FR-027 | US10-A/B؛ EC-15/16/22؛ T0-12؛ SC-005 |
| FR-028 | EC-17/22؛ SR-016؛ T0-12؛ SC-005 |
| FR-029 | US10؛ SR-015/017؛ صفر external real sends والcredentials غير معروضة |
| FR-030 | US11-A/B؛ P1 schema/types؛ SC-006 |
| FR-031 | US02/03/08/11؛ جدول كل الفئات؛ critical error contract ضمن T0 |
| FR-032 | P1 types وP2 Arabic/timezone/date-only؛ EC-21؛ SC-006 |
| FR-033 | US12؛ P2 additive compatibility؛ SC-009 |
| FR-034 | US12-B؛ regression approval/subscription/association/notification؛ SC-009 |
| FR-035 | L01 trace؛ read-only contract لا يكتب؛ سلامة المراجعة وفق SR-019 |
| FR-036 | US14-A/B؛ بوابة الجاهزية؛ SC-008/010 |

## Notes

**النتيجة**:18 بند جودة مستوفى من21، وثلاثة بنود معلقة بسبب قرار واحد A-04.
هذه ليست checklist اكتمال implementation ولا نتيجة اختبارات أمنية.

**القرار المتبقي لمراجعة PM ثم clarify عند التصريح**:

السياق من المواصفة: «requiresSuperAdminApproval يعتمد primaryRole بينما hasSystemRole
يقبل legacy أو أي Spatie role».

| الخيار | السياسة | أثرها المطلوب مراجعته |
|---|---|---|
| A | وجود أي school_manager/supervisor يُخضع الحساب كله للموافقة | قاعدة أكثر تشددًا قد تقيد حسابًا مركبًا كان يستطيع العمل كstaff؛ يلزم توثيق فرق الويب المقصود |
| B | primaryRole يحدد gate الحساب مع منع قدرات manager/supervisor غير المعتمدين مستقلًا | يحفظ فصل قدرات محتملًا لكنه يحتاج قرارًا موحدًا لكل مسار واختبارات عدم تجاوز واضحة |
| Custom | سياسة موثقة أخرى مع منع منح manager/supervisor غير معتمد وصولًا محظورًا | يجب أن تحسم الحالات المركبة دون inventing approval statuses أو نقل القرار للعميل |

لا اختيار تلقائي في specification، ولا تشغيل speckit-clarify هنا. التخطيط/التنفيذ
وMOBILE_BACKEND_READY ممنوعة حتى حسم السياسة وتحديث المتطلبات/معايير قبول T0-09.

**المراجعة الذاتية والتصحيحات**:

1. راجعت النطاق والتتبّع ومعايير الاختبار؛ حدّدت A-04 بدل إخفاء اختلاف الأدوار،
   وحددت تأجيل L02/Push/client lifecycle دون إسقاط اختبارات P0 أو regression المتأثر.
2. راجعت تناقض الأسرار مع issuance؛ SR-017 يجيز الرمز في استجابة إصداره لصاحبه فقط،
   ويحظر logs/session lists/query URLs. حددت fixtures تتجاوز100 سجل لفحص pagination.
3. راجعت التغطية والأعداد وروابط الملفات وJSON selector وحدود Git safety. لا plan/tasks
   أو migrations أو runtime/config/backend edits ضمن هذه المخرجات.

**ملاحظة توافق القالب**: شروط منع «implementation details» تطبق على الاختيارات الداخلية
غير اللازمة. ذكر Laravel/Vue/HTTP و/api/v1 وN8N_WHATSAPP_ENABLED قيود عقد/معمارية طلبها
المستخدم صراحةً، وليس اختيار مكتبات أو code structure أو تنفيذًا.

**التأجيلات المعلنة**: L02 unread-count/mobile destinations إلى notification feature؛
P2 push/polling/mobile-accessibility إلى features العميل؛ APIs بقية المجالات/tuning العام
إلى وحداتها. لا تُفسر التأجيلات كحل finding أو إعلان جاهزية تلك الوحدات.

**الخطوة التالية**: مراجعة PM لهذه المواصفة والقرار؛ لا تنفيذ أي command للمرحلة التالية
دون طلب جديد. الامتثال للدستور PASS لنطاق كتابة المواصفة، لا لادعاء readiness تنفيذية.
