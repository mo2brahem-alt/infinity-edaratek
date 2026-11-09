# Phase8 — نطاق بوابة الأمن والانحدار

PM2026-11-09 اعتمد Phase7 وصرح بـT103–T112 على001-mobile-backend-readiness، مع نفس workflow الاختبار المعزول والدفع إلى فرع الميزة فقط. Phase9 وما بعدها غير مصرح بها. لا application/API contract/schema/event/provider/deployment changes مطلوبة لمجرد كونها مرحلة اختبار.

يُحفظ API v1 وapp/routes/resources/policies/models والخدمات المرجعية. phase8-scope.json يثبت بصمات الاختبارات والعقد ومصادر التطبيق المحمية عند8020e8b، باستثناء overrides المعروفة داخل image فقط لـtests/TestCase.php وphpunit.xml. لا تعديل host لهما. لا broad exclusion؛ WhatsAppIntegrationTest مؤرشف ومستبعد باسمه فقط منذ قرار المنتج، مع بقاء generic phone cases10 منفذة.

run-tests.ps1 يشغل filtered scratch + pinned PHP/MySQL/Vite في CI دون .env أو بيانات مستخدم أو secret أو شبكة خارجية أثناء الاختبارات. Phase8 focused ثم انحداراتPhase2–7 ثم guarded Artisan full وexplicit MySQL PHPUnit full، مع مقارنة case/class counts وsymbols وexit status. مطلوب إثبات الاختبارات فعلياً لا static count. known Excel failure فقط pre-approved ويظهر exit1 كما هو؛ أي failure/error/skip آخر يفشل technical evidence.

WhatsApp خارج النطاق نهائياً: لا delivery/OTP/job/callback execution أو provider credentials/templates/n8n/Meta. T106 القديم وT0-12/H04 integration requirements SUPERSEDED، لا يعاد تصنيف غيابها defect ولا يقال إن delivery tests نجحت. underlying app/web notification regression فقط؛ لا push provider جديد.

نجاح PHASE_8_TEST_EXECUTION لا يكفي FULL_SECURITY_REGRESSION_GATE_PASS. T110/T112 يفرضان إبقاء مخاطرHIGH/نقص أدلتها دون افتراض الإغلاق. الحالة المرجعية منPhase5/7: actual storage roots/aliases/CDN/cache proof وlive debug/config safety UNVERIFIED/UNKNOWN. لم يُقدَّم فحص operations موثوق يغلق هذه الجوانب؛ synthetic static HTTP وdebug-true tests يثبتان المصدر/fixture فقط. لا يجوز إعلان صفرHIGH عالمي أو اعتمادT112 من ذلك وحده.

لذلك يفصل تقريرCI بين technical execution PASS وبين T112 BLOCKED بسبب evidence غير مكتمل لـH01 وH05. لا يدّعي وجود عطل مؤكد جديد على خادم لم يُفحص؛ العائق نقص دليل إغلاق المخاطر المشروطة المطلوبة للبوابة. لا يرخص أي artifact للوكيل قراءة أسرار أو SSH أو تعديل test.edaratek.com/edaratek.com أو migration حقيقية. يتطلب الإغلاق تقرير عمليات موثوقاً ضمن تفويض بيئي مستقل أو أدلة مكافئة راجعها PM، ثم إعادة مراجعة نفس البوابة دون إضعافها.

REAL_FILES_MIGRATED=0، REAL_WHATSAPP_SENDS=0، PHASE_9_STARTED=NO، MOBILE_BACKEND_NOT_READY. الأدلة المحلية فيspecs/001-mobile-backend-readiness/evidence/phase-08/. لا تكتب هذه السياسة موافقة PM أو نتيجة اختبار لم تنفذ؛ النتائج المقاسة تسجل هناك بعد اكتمالCI.
