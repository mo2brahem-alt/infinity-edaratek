# T093–T098 — قرارات التنفيذ والتحقق

العقد portable في docs/mobile-api-v1.md؛ نسخة Spec Kit تُثبت بعد اجتياز T100. لا schema أو migration أو مزود أو Flutter أو عمليات ملفات حقيقية.

DTO allowlists منفصلة تحت Resources/Api/V1، وRequests/Api/V1 تقبل فقط الحقول المعلنة وتمنع حتى null/empty في حقول السلطة المحظورة. page1..2147483647/per_page1..100/default25، ومعرّفات canonical موجبة ضمن signed64؛ لا cast مشبع. تاريخ الاستحقاق date-only، timestamps UTC Z، status من constants المجال القائمة. لا حقول مالية في هذه النقاط؛ لا DTO مالي مخترع.

NativeCollection يوحد meta/links النسبية ذات validated filters. تفاصيل المهمة ترقم metadata المرفقات، والـAttachmentAccess يبني شروط المدرسة والأب/module/attachable consistency قبل العد. لا تحميل سجل رسائل أو تعليقات كامل. filename/MIME/size فقط دون disk/path أو uploader data. اختبارات read SQL تتيح تحديث last_used_at لرمز PAT فقط؛ لا إنشاء defaults/roles أو بيانات أعمال عبر GET.

ApiError يوحد خطأ v1، ويولّد UUID لكل request دون عكس X-Request-ID. الأخطاء لا تحمل exception text أو arbitrary field messages/headers؛ logger500 يسجل code+UUID نفسه. Http409 domain mapping يثبت بfixture فقط دون endpoint أعمال جديد. non-v1 renderer باقٍ.

NativeRateLimiter يحجز ميزانيات canonical identifier وuser ID المعروف وIP معاً قبل فحص كلمة المرور، تحت locks مرتبة من مخزن cache.limiter أو المخزن الافتراضي نفسه الذي يحمل counters. قراءة candidate من قاعدة البيانات تسبق الحجز لمنع إنشاء budget آخر للحساب نفسه عند تغيير البريد أو lookup alias؛ لا بيانات حساب في رد الرفض. shared-cache failure يمنع العمل؛ lock timeout يعيد429/retry1. الأرقام في config/mobile.php لم تتغير؛ unknown identifier وIP rotation محميان أيضاً. reset web يشارك نفس الخدمة مع middleware محدود. lock lease10s/wait2s؛ لا fallback غير مقفل أو مخزن array بديل عند فشل shared store. إعداد مخزن shared فعلي متعدد hosts لم يُفحص هنا.

إثبات concurrency يستعمل ست عمليات PHP مستقلة لكل من login/read/session/reset؛ كل واحدة تمر preflight+boot guards في isolated runtime ثم تستخدم file store fixture مشتركاً. parent يملأ budget إلى maximum-2، وتنتظر العمليات حاجز start بعد ready لكل الستة. المطلوب2 admissions و4 denials بالقيم الفعلية. لا fork لاتصال DB مفتوح، ولا queue أو provider أو مخرجات حساسة؛ cleanup محصور في root p7-rate-UUID داخل storage/framework/testing.

في الحالة النهائية login workers تشترك في user ID وتستعمل identifiers/IPs مختلفة، حتى يكون budget الحساب المشترك نفسه هو القيد المثبت ذرياً. اختبار HTTP مستقل يغير بريد الحساب وIP بين المحاولات ثم يثبت رفض المحاولة السادسة قبل credentials، وعدم إنشاء token واستعادة الدخول بعد61 ثانية. هذه الإضافة عالجت فجوة T096 التي ظهرت في المراجعة بعد اجتياز مجموعة الاختبارات الأولى؛ لم يُدّع تنفيذ exploit على بيئة حية. كما أصبح اختبار due_date يستخدم تاريخين مختلفين للاتجاهين، وsession IP30 مثبت عبر ثلاثة حسابات.

إثبات قابلية التوسع يقيس baseline10 مهام أولاً، ثم1001 مهمة على parent قائم؛ output100/query growth<=baseline+2 وresponse<50000 bytes. الزمن مقاس للمعلومية، لا threshold هش أو ادعاء throughput في بيئة حية. بوابة Phase7 تستهلك profile آمن وتتحقق من المصدر والعدد مع JUnit، وتعدّ كل حالة جديدة فوق647 دون استبعاد إضافي.

التشغيل الأول b555288/run42611731015/job129141570801: focused67، pass59/fail7/error1/skip0. سبعة failure من fixture staff بلا leave capability (المطبق403 قبل resource lookup صحيح)؛ صحّح fixture لمنح capability وأضيف اختبار مستقل يحفظ أسبقية403. Error واحد HY000/1364 من Ticket fixture بلا description الإلزامي؛ أضيف الوصف. لا تعدّل policies أو schema لتلبية fixture. بقية الاختبارات، ومنها concurrency، اجتازت ذلك التشغيل. التصحيحات وحدود null/empty/date/lock مدعمة باختبارات إضافية في e5ca33e؛ النتائج النهائية في results.md.
