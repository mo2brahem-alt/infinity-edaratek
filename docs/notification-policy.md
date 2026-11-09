# سياسة قنوات إشعارات Edaratek — قرار PM بتاريخ2026-11-09

WhatsApp ملغى نهائياً من نطاق المشروع. WHATSAPP_ENABLED=NO، WHATSAPP_IN_SCOPE=NO، WHATSAPP_SENDS_ALLOWED=NO. لا n8n/Meta calls أو templates أو provider configuration أو delivery tests. القنوات المسموحة: in-app/application، web، وmobile push فقط حيث توجد متطلبات معتمدة وبنية قائمة. هذا القرار لا يضيف push provider أو native notification API أو قناة OTP بديلة.

NotificationService وسجل notifications ومسارات الويب الحالية هي مسار إشعارات الأعمال المشترك. لا fan-out إلى القناة الملغاة، ولا اعتماد على أسرارها أو وظائفها أو config قديمة true. حفظ الإشعار داخل التطبيق ليس ادعاء delivery خارج التطبيق. email/password-reset الأمنيان غير المتعلقين بالإشعارات التجارية لا يُحذفان بهذا القرار.

الـlegacy dispatch entry point متقاعد دون HTTP، والـserialized job classes باقية للتوافق لكن لا تعالج إرسالاً أو تعيد كتابة historical message/claim states. مسارات verification/webhooks القديمة تعيد410 WHATSAPP_REMOVED ولا تنشئ OTP أو claim ولا تقبل callbacks. بيانات الجوال والتحقق التاريخي محفوظة؛ تغيير الرقم يبطل التحقق كما قبل، ولا يستبدل إثبات ملكية الهاتف بإشعار داخل الحساب نفسه.

الحفاظ على النماذج/الجداول/ملفات الاختبارات التاريخية ليس إبقاءً للميزة نشطة. لا integration_controls migration، لا live-toggle أو enable/disable command أو OTP-budget work جديد. متطلبات US10 وFR027–029 وSR014–016 والأجزاء المرتبطة بـWhatsApp من SR021/EC19–22/T0-12/CHK043–050/063/Phase6 القديمة SUPERSEDED، وليست acceptance dependencies. سياسات السرية والعزل وأهلية الحساب العامة تبقى نافذة.

tests/Feature/WhatsAppIntegrationTest.php خارج suites النشطة وحده بسبب قرار المنتج، لا skip وقت التنفيذ. يُحفظ محتواه وبصمته، وتُنقل اختبارات الجوال العامة والتحقق عند تغييره إلى PhoneStateCompatibilityTest. يُبلغ عدد الحالات المستبعدة والمحفوظة والفارق عن670 في دليل Phase6؛ لا تستبعد مجموعات regression أخرى. لا تنفذ legacy delivery tests أو تتصل بمزود.

القرار ينسخ أي نص قديم متعارض في تعليمات المشروع أو Spec Kit. التاريخ يبقى evidence فقط. بعد نشر مستقبلي مصرح به يجب وقف/إعادة تشغيل أي workers محملة بالكود القديم؛ تغيير source هنا لا يبدل كود process حقيقي موجود ولا يسحب إرسالاً سبق أن بدأ. لا نشر أو staging/production/control/provider change ضمن Phase6 الحالية. actual aliases/CDN ما زالت UNKNOWN، وREAL_FILES_MIGRATED=0 وREAL_WHATSAPP_SENDS=0 وMOBILE_BACKEND_NOT_READY.
