# T099 — مراجعة التوافق وحدود المرحلة

مسارات routes/api.php وroutes/web.php ونسخ endpoint القائمة لم تتغير في Phase7. التسجيل في bootstrap/app.php قائم؛ فروع renderer الجديدة مقيدة بـapi/v1، بما فيها malformed/method/routing errors. المسار العام web و/api/system و/api/school يحتفظ بفروعه/CSRF/envelopes/redirects. لا controller أعمال موازٍ أو قواعد subscription/approval/tenant جديدة.

الموارد/الطلبات انتقلت من Mobile إلى Api/V1 وأضيفت الموارد الناقصة؛ controllers تستخدم AccountAccessEvaluator وSchoolContextResolver وSubtaskAccessQuery وAttachmentAccess وNativeTokenService نفسها. قائمة المهام ما زالت تقيد الإسناد والمدرسة والأب قبل الفلاتر/العد، وتفاصيلها لا تكشف transcript. أضيفت attachments_meta/links داخل المسار نفسه لصفحات مرفقات مأذونة محدودة، دون حذف حقول DTO أو نقطة أعمال جديدة. إصلاح device IDs يمنع overflow coercion. جلسة native ذات created_at مفقود لا تصنف active؛ لا حذف لسجل تاريخي أو migration.

التغيير المقصود في web reset: استبدال throttle غير الذري بمصدر الحجز الذري المشترك، بالأرقام نفسها3/identifier/hour و10/IP/hour. redirects/validation/outcome العام للموجود وغير الموجود باقية؛ tests/Feature/Auth/PasswordResetTest تثبتها. الدخول native يحتسب كل المحاولات في5/canonical-identifier/min و30/IP/min قبل credentials لمنع تجاوز الحد بتبديلIP أو حساب مجهول. حدود read120/300 وsession10/30 مثبتة. لا تغيير login web/session/CSRF أو token lifetime أو اشتراك/تسعير.

اكتمال T096 يضيف أيضاً5/resolved-user-ID/min في الحجز نفسه قبل password check، كي لا يصبح تغيير email أو اختلاف lookup alias ميزانية جديدة للحساب. المراجعة أظهرت أن identifier وحده لا يكفي لشرط account-global؛ لذلك أُبطل اعتماد المصدر السابق لهذه النقطة حتى اجتياز الاختبار الإضافي على8020e8b. هذا تصحيح ضمن نطاق الأمن المعتمد، لا صلاحية أعمال جديدة أو تغيير أرقام الحدود.

تغيير renderer مقصود: machine codes عربية العرض وآمنة، request_id مولد يربط500 بسجل code+request_id فقط، وعدم إرجاع arbitrary exception/field messages أو رؤوس خاصة. اختبار Phase3 للـlog عُدّل فقط ليتطلب حقل request_id UUID إضافياً مع نفس الرسالة والكود ومرة واحدة، دون إسقاط اختبارات debug/trace/secret. لا تغيير في عدد حالات Phase2–6.

NotificationService وجدول notifications وقنوات app/web ومسارات410 للميزة الملغاة لم تُغيّر. لا event جديد أو push أو استعادة WhatsApp/OTP. tests/Feature/Mobile/Phase6 وPhoneStateCompatibilityTest وRealtimeNotificationFlowTest ضمن الانحدار، ولا تنفذ الاختبارات التاريخية للميزة الملغاة. source hash/archive/targeted suite exclusion محفوظة.

أدلة التنفيذ، بما فيها auth/role/foreign-school/private download وschool association وموافقة/تسعير/اشتراك وواجهات web، تسجل في results.md وvalidated-runtime.json بعد التشغيل النهائي. مخرجات CI لا تحوّل فشل Excel المعروف إلى نجاح ولا تخفيه؛ المصدر والاختبار الأصليان محفوظان. لا شهادة لتوافق deployed infrastructure غير المفحوصة؛ aliases/CDN UNKNOWN.
