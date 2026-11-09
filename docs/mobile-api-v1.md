# عقد Edaratek Mobile API v1

تعريف العقد للمرحلة السابعة، على المسارات الموجودة تحت `/api/v1`. المرجع التنفيذي: `routes/api.php` وموارد/طلبات `app/Http/{Resources,Requests}/Api/V1`. نتائج الاعتماد مرتبطة بالمصدر في تقرير Phase7، وليست هذه الوثيقة إعلان جاهزية أو تصريح نشر. WhatsApp خارج النطاق نهائياً؛ لا OTP أو push provider أو عمليات أعمال جديدة هنا.

## النقل والهوية

HTTPS خارج الاختبارات المعزولة، JSON UTF-8 وAccept:application/json. المسارات المحمية تحتاج Authorization:Bearer ورمز Sanctum أصلي محدود. لا cookie/session/query-token fallback. الدخول لا يعدّل جلسة الويب. العمر الافتراضي1440 دقيقة من MOBILE_NATIVE_TOKEN_EXPIRATION_MINUTES في إعدادات native الحالية؛ expires_at مطلق وملزم، لا refresh_token ولا تجديد ضمني.

الترتيب: رمز مفقود/مشوه/منتهٍ/ملغى أو مستخدم محذوف401؛ أهلية الحساب الحالية403؛ صلاحية العملية403؛ سياق المدرسة409 أو إيقاف403؛ المورد المخفي/الأجنبي404؛ عملية غير مأذونة على مورد مرئي403. قد يسبقها429. استثناء logout فقط يسمح لصاحب رمز صالح بإنهائه بعد منع الحساب من الأعمال، دون بيانات تشغيلية.

## نقاط النهاية

| الطريقة والمسار النسبي | المدخلات/التفويض | النجاح |
|---|---|---|
| POST /auth/login | email صالح حتى255، password غير فارغ حتى255، device_uuid UUID، device_name1..80؛ allowlist صارمة، أي حقل صلاحيات/tenant إضافي مرفوض حتى null/empty | 200 data:TokenIssuance، Cache-Control:no-store |
| GET /me | حساب أصلي مؤهل؛ لا provisioning؛ school قد تكون null | 200 data:CurrentUser |
| POST /auth/logout | الهوية الأصلية الحالية، حذف رمزها فقط | 204 فارغ |
| GET /auth/devices | حساب مؤهل، رموزه الأصلية غير المنتهية، page/per_page فقط | 200 Device[]/meta/links |
| DELETE /auth/devices/{token} | مالك رمز أصلي؛ foreign/missing404؛ يجوز الحالي | 204 فارغ |
| DELETE /auth/devices | حذف جميع رموز المالك الأصلية؛ لا browser/nonnative | 204 فارغ |
| GET /staff/tasks | staff، مدرسة وعلاقة نشطة، assignment+school+parent؛ الفلاتر أدناه | 200 TaskSummary[]/meta/links |
| GET /staff/tasks/{subtask} | نفس scope وpolicy؛ page/per_page لبيانات المرفقات | 200 data:TaskDetail |
| GET /attachments/{attachment}/download | parent/school/operation policy، لا معرفة URL وحدها | 200 binary خاص |
| GET /student-leaves/{leave}/attachments/{attachment}/download | إذن leave وتطابق المدرسة والأب والمرفق | 200 binary خاص |

لا201 في هذه النقاط؛ لا عملية إنشاء أعمال هنا. رموز malformed/nonpositive/noncanonical/out-of-range404 قبل cast أو lookup. الحد الأعلى للمعرّف9223372036854775807 على PHP64؛ صيغ +1 و01 والكسور غير مقبولة. لا يتحول المعرف الكبير إلى معرف آخر.

## الموارد والأنواع

IDs أعداد JSON صحيحة موجبة؛ booleans فعلية؛ arrays تبقى[] عند الفراغ؛ nullability صريحة. instants بصيغة ISO8601 UTC مع Z ودقة الأجزاء العشرية المسموحة، date-only بصيغة YYYY-MM-DD دون تحويل timezone/DST. العربية للعرض، وmachine codes/statuses مستقلة عنها. لا مبلغ مالي في هذه DTOs؛ أي إضافة مستقبلية للمبالغ تحتاج decimal string مع currency واختبار عقد، لا binary float.

| DTO | الحقول الوحيدة |
|---|---|
| TokenIssuance | access_token:string يظهر مرة عند الإصدار، token_type:"Bearer"، expires_at:instant، user:CurrentUser |
| CurrentUser | id:int، name:string، email:string، roles:string[] الفعلية، capabilities:string[] المأذونة للسياق، is_active:bool، approval_required:bool، approval_status:pending_approval/approved/rejected/null، school:{id:int,name:string}/null، phone_verified:bool |
| Device | id:int، device_name:string، created_at:instant، last_used_at:instant/null، expires_at:instant، is_current:bool |
| TaskSummary | id:int، title:string، status:OPEN/IN_PROGRESS/SUBMITTED/APPROVED، due_date:date-only/null |
| TaskDetail | TaskSummary + description:string/null، attachments:AttachmentMetadata[]، attachments_meta:PaginationMeta، attachments_links:PaginationLinks |
| AttachmentMetadata | id:int، name:string، mime_type:string، size_bytes:int>=0، download_url:string لمسار أصلي محمي نسبي |

capabilities من shared evaluator/relations الحالية، ولا تحل محل تفويض كل عملية. السياق غير المكتمل يتيح /me مع school=null، والسياق المحظور لا يمنح بيانات تشغيلية. phone_verified يستلزم تاريخ تحقق ورقم canonical حالي صالح؛ تغيير mobile/phone يبطل التحقق. يعاد استخدام utility الهاتف العامة دون تشغيل قناة إرسال أو OTP. لا notes/actors/role pivots/permission groups/password/hash/paths/disk/OTP/provider fields في الموارد.

الجلسات المعروضة لها marker/abilities أصلية صحيحة وcreated_at/expiry صالحان. لا يُعرض token/hash/abilities أو UUID كسلطة تفويض؛ تسجيل الدخول لا يلغي أجهزة أخرى. المرفقات في تفاصيل المهمة محدودة بصفحة مع عد/روابط ثابتة؛ لا تحميل messages أو transcripts. التنزيل يعيد البايتات الصحيحة مع server MIME وContent-Disposition آمن وnosniff وCache-Control:private,no-store؛ missing/deleted bytes404 دون public fallback.

## الصفحات والفلاتر

النجاح المفرد `{"data":{...}}`. المجموعة `{"data":[],"meta":{"current_page":1,"per_page":25,"total":0,"last_page":1,"from":null,"to":null},"links":{"first":"...","last":"...","prev":null,"next":null}}`. نفس المفاتيح والأنواع مع/دون per_page. page عدد صحيح1..2147483647 (حد تقني يمنع overflow)، per_page1..100/default25. null/empty/array/noninteger/zero/negative/out-of-range422. صفحة صحيحة بعد النهاية200/data=[] وfrom/to=null؛ last_page>=1 وcurrent_page يحفظ طلب العميل.

links نسبية إلى المسار نفسه، تحفظ فقط validated query، لا credentials/host أو tenant authority. prev موجود عند page>1 وnext عند page<last_page. scope والتطابق المأذون يسبقان العد والصفحات.

tasks: search اختياري غير فارغ حتى100 حرف، title فقط وLIKE حرفي escaped/bound؛ status من enum أعلاه؛ sort=id/-id/due_date/-due_date، default=-id. ترتيب due_date يجعل null أخيراً في الاتجاهين، وعند التعادل id تنازلياً. devices: page/per_page فقط، created_at ثمid تنازلياً. تفاصيل المهمة: page/per_page للمرفقات فقط، id تنازلياً؛ attachments_meta/links يصفان هذه المجموعة وحدها. لا فلاتر ضمنية أو get-all.

## الأخطاء والتتبع

`{"code":"VALIDATION_FAILED","message":"نص عرض عربي","errors":{"field":["نص آمن"]},"request_id":"UUID"}`. errors فقط عند وجود أخطاء حقول؛ أسماء حقول محدودة وآمنة، ورسائل ثابتة دون قيم المدخلات أو نص الاستثناء. request_id مولد لكل طلب، لا يعكس X-Request-ID. الخطأ الداخلي يسجل فقط code وrequest_id المولّد نفسه؛ لا bodies/SQL/token/secret/trace حتى debug=true. استجابات الخطأ no-store.

| HTTP | code |
|---|---|
| 401 | INVALID_CREDENTIALS للدخول (unknown/wrong متماثلان)، UNAUTHENTICATED للهوية الأصلية |
| 403 | ACCOUNT_PENDING_APPROVAL / ACCOUNT_REJECTED / ACCOUNT_DISABLED وفق أسبقية حالة الحساب، FORBIDDEN، SCHOOL_SUSPENDED |
| 404 | NOT_FOUND دون كشف الوجود |
| 409 | SCHOOL_CONTEXT_REQUIRED، BUSINESS_CONFLICT، IMPACT_CONFIRMATION_REQUIRED عند تعارض domain فعلي فقط |
| 422 | VALIDATION_FAILED وerrors آمنة |
| 429 | RATE_LIMITED، Retry-After كرأس ثوانٍ صحيح موجب وretry_after:int مطابق |
| 500 | INTERNAL_ERROR دون تفاصيل داخلية |
| 400 / 405 / 413 | BAD_REQUEST / METHOD_NOT_ALLOWED / PAYLOAD_TOO_LARGE لأخطاء النقل/routing |

409 domain renderer مثبت عبر fixtures ولا يضيف workflow أعمال. أخطاء الخادم الأخرى تستخدم INTERNAL_ERROR. يمر فقط Retry-After المصفّى وAllow الصحيح عند405؛ لا يعكس renderer رؤوس استثناء خاصة. لا تعديل عالمي لأخطاء/redirects web أو /api/system أو /api/school.

## الحدود الفعلية

| السياسة | هوية/حساب | IP | النافذة |
|---|---:|---:|---:|
| native login | 5 لكل email canonical، تشمل المحاولات الصحيحة والخاطئة والمجهولة | 30 | 60 ثانية |
| native reads | 120 لكل user | 300 | 60 ثانية |
| native session mutations، ومنها logout | 10 لكل user | 30 | 60 ثانية |
| forgotten password web | 3 لكل email canonical | 10 | 3600 ثانية |

القيم في config/mobile.php؛ لم تتغير الأرقام المعتمدة سابقاً، واكتملت عملية الحجز الذري. حد5 لمحاولات email+IP الفاشلة محفوظ كنتيجة للحد الأقوى5 لكل identifier مستقل عنIP؛ لا يتيح نجاح الدخول أو تبديلIP تجاوز نافذة الحجز. مفاتيح HMAC لا تسجل الهوية أوIP الخام. تُحجز ميزانيتا الهوية/IP معاً تحت أقفال مرتبة في مخزن limiter نفسه؛ انتظار القفل2 ثانية وlease10 ثوانٍ، timeout429/retry1، فشل المخزن يمنع العملية بخطأ آمن ولا يتحول إلى allow. القراءة فقط داخل budget لا تعني استحقاق أعمال؛ أهلية الحساب والسياسات منفصلة.

الافتراضي database cache؛ concurrency يُثبت بمخزن file مشترك بين عمليات مستقلة داخل CI وبالقيم الفعلية، مع بقاء default array لاختبارات العزل الأخرى. تشغيل متعدد الخوادم يحتاج مخزناً مشتركاً يدعم locks؛ لا تَشهد اختبارات fixture على إعداد بيئة حية غير مفحوصة. استعادة الحدود تختبر بالساعة الوهمية. reset يحافظ على الرد العام للحساب المعروف/المجهول وإشعارات البريد الوهمية؛ لا تغييرات بريد حقيقي. OTP خارج النطاق، لا حدود واتساب نشطة.

## التوافق والتطور

هذا هو عقد v1 الذي يثبت في Phase7 على الكود المنفذ، وليس إعادة تسمية لمسارات web القائمة. إضافة field اختياري تحتاج تحديث fixture/contract؛ تغيير الاسم/النوع/المعنى أو جعل optional إلزامياً يتطلب v2 مع coexistence وانتقال معلن. أثناء التطوير قبل freeze أضيفت attachments_meta/links لإزالة استجابة غير محدودة؛ لا حذف لحقول الأعمال القائمة. لا native UI أو Flutter أو إشعارات جديدة أو نشر. يبقى MOBILE_BACKEND_NOT_READY حتى المراحل اللاحقة وموافقة PM.

الاختبارات: MobileApiContractTest، MobileErrorContractTest، MobilePaginationTest، MobileRatePolicyTest، MobileSerializationTest مع انحدارات auth/context/files/notifications/web. مرجع النتائج والمصدر: specs/001-mobile-backend-readiness/evidence/phase-07/.
