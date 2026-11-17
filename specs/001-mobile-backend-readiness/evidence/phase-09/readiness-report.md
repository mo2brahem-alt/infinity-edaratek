# T118 — Mobile Backend Readiness: مقبول من PM

المراجع Codex،2026-11-17. مراجعةأدلةمعاختبارتوافقPHP8.3معزولمصرحبه؛لاSSHأوHTTPإلىالخوادم. C=`d57cf9831c3c7123534aedfd34021c7b649968c7` deployed PM-approved؛B=`fbc63a4615f48ac0f832d91945d9652aeabef466` مرجع PHP8.2 full regression؛والاختبارالكامل PHP8.3 نُفذعلىC. التفويض الحالي [محفوظ](pm-phase09-authorization.txt).

```ini
T118=PASS
PM_PHASE_9_DECISION=ACCEPTED
CHK_RELEASE_SATISFIED=84/84
MOBILE_BACKEND_READINESS=PASS
PROPOSE_MOBILE_BACKEND_READY=YES
PHASE_9_TECHNICAL_REVIEW_COMPLETE=YES
T119=PASS
T120=PASS
MOBILE_BACKEND_READY=YES
PHASE_9=CLOSED
```

## النتيجة وحدودها

H01/H05/T112 PASS وPhase 8 CLOSED كما اعتمد PM. لا HIGH مطبق أو CRITICAL مؤكد غير مغلق ضمن النطاق. نتائج P0 وHIGH السابقة محفوظة: 12/12 dispositions، وأربعة HIGH PASS مع H04 محسوم النطاق. الدستور الآن 14/14 PASS.

البوابات: 84/84 مقيمة ومستوفاة؛ 75 PASS + 9 SUPERSEDED بعد قبول CHK084 الفعلي من PM. لم يُغيّر أي checkbox مملوك لمراجع readiness-security.md.

**R9-01=RESOLVED:** ينفذ [الدليل الجديد](php83-compatibility.md) PHP 8.3.36 على المرشح نفسه بالحارس المعتمد. نجحت جميع المجموعات المركزة؛ كل مسار كامل 724/725 مع Excel القديم نفسه، وصفر فشل أو خطأ أو تخطٍ جديد، والتنظيف PASS. أُغلقت CHK008/081/083 وتوابعها T116/T117. لم تُعد مراجعة P0/HIGH أو فتح H01/H05/T112.

**CHK084 / T119:** PASS بقرار PM النهائي الصريح بتاريخ 2026-11-17؛ T120=PASS وMOBILE_BACKEND_READY=YES. [القرار](pm-decision.md) و[القبول النهائي](acceptance.md) يسجلان الإغلاق؛ Flutter يحتاج موافقة مستقلة.

## FR requirements — كل36 بنداً

الحكم أدناه فني للنطاقالوظيفي علىB/source-identicalC،وقدأغلقتqualification8.3بالتشغيلالمعتمد. deliveryrequirements القديمةSUPERSEDED،والـsecretsالمشتركةتحكمهاSR017.

| FR | معيار القبول كمافيspec | الدليل/الحكم |
|---|---|---|
| FR-001 | MUST يبقى Laravel الحالي مصدر هوية المستخدم وقواعد الأعمال والبيانات؛ لا تسجيل دخول مباشر لقاعدة البيانات أو backend ثانٍ للعميل الأصلي. | PASS: [P2 realPAT16/16](../phase-02/acceptance.md),[P3 currentstate128/128](../phase-03/results.md),[P7 rates/contracts76/76](../phase-07/results.md),[B8](../phase-08/full-results.md) |
| FR-002 | MUST يتيح دخولًا أصليًا للمستخدم المستوفي للشروط ببيانات الدخول الحالية، ويعيد رمز جلسة وصاحبها وexpires_at صريحًا؛ لا عمر غير محدود ولا تمديد ضمني بمجرد الاستخدام. | PASS: [P2 realPAT16/16](../phase-02/acceptance.md),[P3 currentstate128/128](../phase-03/results.md),[P7 rates/contracts76/76](../phase-07/results.md),[B8](../phase-08/full-results.md) |
| FR-003 | MUST يعيد محاولة الدخول غير الصحيحة 401/INVALID_CREDENTIALS دون تمييز بريد غير موجود من كلمة مرور خاطئة، ودون بيانات حساب أو أسرار. | PASS: [P2 realPAT16/16](../phase-02/acceptance.md),[P3 currentstate128/128](../phase-03/results.md),[P7 rates/contracts76/76](../phase-07/results.md),[B8](../phase-08/full-results.md) |
| FR-004 | MUST يتيح معرفة المستخدم الحالي المؤهل عبر جلسة أصلية صالحة، مع هوية allowlisted وroles[] الفعلية وcapabilities الفعلية للسياق وis_active وapproval_required وapproval_status الحالي وschool context أو غيابه وphone_verified للرقم المرجعي الحالي. هذه قيم آلية من الخادم وليست primaryRole منفردًا أو اختيار العميل؛ لا يلزم كشف permission internals أو approval_notes أو أسرار/سجلات OTP. | PASS: [P2 realPAT16/16](../phase-02/acceptance.md),[P3 currentstate128/128](../phase-03/results.md),[P7 rates/contracts76/76](../phase-07/results.md),[B8](../phase-08/full-results.md) |
| FR-005 | MUST تكون جلسة كل جهاز قابلة للتمييز والإلغاء المستقل؛ لا يلغي دخول جديد تلقائيًا جلسات أصلية أخرى أو جلسة ويب. | PASS: [P2 realPAT16/16](../phase-02/acceptance.md),[P3 currentstate128/128](../phase-03/results.md),[P7 rates/contracts76/76](../phase-07/results.md),[B8](../phase-08/full-results.md) |
| FR-006 | MUST يلغي logout الرمز الأصلي المستخدم بحيث يرفض الطلب التالي به؛ تكرار logout لا يعيد الرمز ولا ينفذ عملية أخرى، ويمكن إعادة 401 عند كونه ملغى مسبقًا. إلغاء الرمز الحالي فقط مسموح لصاحبه ذي هوية token مثبتة حتى إذا مُنع الحساب من الأعمال؛ لا يعيد بيانات ولا يتجاوز gate الأعمال، أما سرد/إلغاء أجهزة أخرى فيحتاج أهلية الحساب الحالية. | PASS: [P2 realPAT16/16](../phase-02/acceptance.md),[P3 currentstate128/128](../phase-03/results.md),[P7 rates/contracts76/76](../phase-07/results.md),[B8](../phase-08/full-results.md) |
| FR-007 | MUST تتاح لصاحب الحساب المصادَق عليه معرفة جلساته الأصلية وإلغاء جلسة محددة أو جميعها بما فيها الحالية؛ لا كشف رموزها السرية أو إدارة جلسات مستخدم آخر. | PASS: [P2 realPAT16/16](../phase-02/acceptance.md),[P3 currentstate128/128](../phase-03/results.md),[P7 rates/contracts76/76](../phase-07/results.md),[B8](../phase-08/full-results.md) |
| FR-008 | MUST يرفض الرمز المفقود/المشوه/المنتهي/الملغى أو التابع لمستخدم محذوف برد 401 موحد دون fallback إلى وصول عام أو هوية مدرسة من الطلب. | PASS: [P2 realPAT16/16](../phase-02/acceptance.md),[P3 currentstate128/128](../phase-03/results.md),[P7 rates/contracts76/76](../phase-07/results.md),[B8](../phase-08/full-results.md) |
| FR-009 | MUST يحترم is_active وحالات pending_approval وapproved وrejected الحالية وفق ACCOUNT_LEVEL: غير النشط مرفوض، ووجود أي school_manager أو supervisor في legacy أو Spatie يلزم approved قبل أي وصول تشغيلي لكل أدوار الحساب. غير المعتمد لا يحصل على رمز تشغيلي؛ تُفحص الأهلية الحالية في كل طلب، ولا يجعل ترتيب role IDs أو دور staff إضافي موافقة جزئية موجودة. تنطبق تفاصيل A-04 وجداولها دون إنشاء statuses جديدة. | PASS: [P2 realPAT16/16](../phase-02/acceptance.md),[P3 currentstate128/128](../phase-03/results.md),[P7 rates/contracts76/76](../phase-07/results.md),[B8](../phase-08/full-results.md) |
| FR-010 | MUST تُوثّق وتُفرض حدود محاولات الدخول بحسب الحساب ومصدر الطلب، مع حد انتظار معلن و429 قابل للفهم عند تجاوزه؛ لا يحتسبها العميل وحده. | PASS: [P2 realPAT16/16](../phase-02/acceptance.md),[P3 currentstate128/128](../phase-03/results.md),[P7 rates/contracts76/76](../phase-07/results.md),[B8](../phase-08/full-results.md) |
| FR-011 | MUST لا يكشف forgot-password/reset المعرض للعميل وجود بريد من اختلاف النتيجة العامة، مع حدود تمنع الإساءة ودون تعطيل مسار إعادة تعيين كلمة المرور الحالي للمستخدم الصحيح. | PASS: PasswordResetTest7cases+MobileRatePolicyTest;[P3](../phase-03/results.md)/[B8](../phase-08/full-results.md) |
| FR-012 | MUST يمنع سياق المدرسة غير الصالح أو الموقوف العمليات المدرسية وقراءة quick-setup؛ الحساب النشط دون ارتباط مكتمل يحصل على SCHOOL_CONTEXT_REQUIRED دون اختيار مدرسة تلقائي. | PASS: [P4 scope65/65](../phase-04/results.md),[P7 pages/rates76/76](../phase-07/results.md),[scale2/2](../phase-08/full-results.md) |
| FR-013 | MUST يحل الخادم school context ويمنع school_id أو مكافئه من تغيير السياق؛ حقول tenant غير المقبولة في عملية school-bound تُرفض 422. الاستثناء selector ضروري ومصرح به صراحةً مع تحقق علاقة على كل طلب. | PASS: [P4 scope65/65](../phase-04/results.md),[P7 pages/rates76/76](../phase-07/results.md),[scale2/2](../phase-08/full-results.md) |
| FR-014 | MUST يُتحقق من انتماء relationship IDs للمدرسة والأب المأذونين قبل القراءة أو التغيير؛ وجود ID صحيح في قاعدة البيانات لا يثبت حق الوصول. | PASS: [P4 scope65/65](../phase-04/results.md),[P7 pages/rates76/76](../phase-07/results.md),[scale2/2](../phase-08/full-results.md) |
| FR-015 | MUST تقتصر قائمة الموظف على المهام المسندة إليه والمسموحة في مدرسته الحالية النشطة؛ لا تتجاوزها الفلاتر أو الصفحات أو counts؛ لا تُضمّن transcripts كاملة ضمن list. | PASS: [P4 scope65/65](../phase-04/results.md),[P7 pages/rates76/76](../phase-07/results.md),[scale2/2](../phase-08/full-results.md) |
| FR-016 | MUST تتبع تفاصيل المهمة والعمليات الحالية عليها قيود الدور والمدرسة والإسناد والسياسة المرجعية؛ لا يتيح نجاح list تخطي تفويض show/reply/submit أو استخدام معرف آخر. | PASS: [P4 scope65/65](../phase-04/results.md),[P7 pages/rates76/76](../phase-07/results.md),[scale2/2](../phase-08/full-results.md) |
| FR-017 | MUST تكون القوائم الأصلية page-based ببنية ثابتة: default per_page=25 وحد أقصى100 وpage>=1؛ الصفحة الصحيحة الخالية/بعد الأخيرة تعيد data=[] و200 مع metadata ثابتة، والقيم غير الصالحة422؛ لا get-all ضمني. | PASS: [P4 scope65/65](../phase-04/results.md),[P7 pages/rates76/76](../phase-07/results.md),[scale2/2](../phase-08/full-results.md) |
| FR-018 | MUST تقبل القوائم فلاتر وترتيبًا موثقين فقط، وتُرفض القيم غير الصالحة دون تغيير tenant scope؛ عدد/تفاصيل نتائج المدرسة الأخرى لا يظهران في totals أو metadata. | PASS: [P4 scope65/65](../phase-04/results.md),[P7 pages/rates76/76](../phase-07/results.md),[scale2/2](../phase-08/full-results.md) |
| FR-019 | MUST يميز الخادم PUBLIC ASSET من AUTHORIZED PRIVATE ATTACHMENT وفق غرض السجل وسياسة معتمدة؛ موقع public disk القديم أو حقل يرسله العميل لا يجعل ملف مهمة/إجازة/طالب عامًا. | PASS: [P5 private/static/migration100/100](../phase-05/results.md),[H01 accepted V2–V6/history](accepted-operator-summary.json),P7 DTO |
| FR-020 | MUST يعيد التنزيل المأذون للملف الخاص bytes الملف الصحيح واسمًا ونوعًا مناسبين، فقط بعد تحقق صلاحية المدرسة والسجل والعملية عند طلب التنزيل. | PASS: [P5 private/static/migration100/100](../phase-05/results.md),[H01 accepted V2–V6/history](accepted-operator-summary.json),P7 DTO |
| FR-021 | MUST يمنع تنزيل الملف الخاص دون جلسة أو حق عملية أو مدرسة؛ تعاد401 لغياب المصادقة، و403 لنقص حق عملية على سياق مأذون، و404 لسجل أجنبي/غير مرئي، دون كشف وجوده أو محتواه. | PASS: [P5 private/static/migration100/100](../phase-05/results.md),[H01 accepted V2–V6/history](accepted-operator-summary.json),P7 DTO |
| FR-022 | MUST تكون metadata للملفات allowlisted: ما يلزم للاسم/النوع/الحجم والوصول المأذون؛ لا storage paths أو provider secrets أو معلومات طالب/رافع غير مطلوبة في list/error/response. | PASS: [P5 private/static/migration100/100](../phase-05/results.md),[H01 accepted V2–V6/history](accepted-operator-summary.json),P7 DTO |
| FR-023 | MUST لا يعيد ملف مفقود أو محذوف أو رابط خاص منتهي أي محتوى؛404 مناسب وثابت، دون إحياء soft-deleted record أو bypass عبر fallback عام. | PASS: [P5 private/static/migration100/100](../phase-05/results.md),[H01 accepted V2–V6/history](accepted-operator-summary.json),P7 DTO |
| FR-024 | MUST تبقى الشعارات وصور العلامة المصنفة عامة عمدًا متاحة وفق عقدها الحالي؛ لا تتطلب هذه الميزة نقلها إلى خاصة بلا دليل خصوصية. | PASS: [P5 private/static/migration100/100](../phase-05/results.md),[H01 accepted V2–V6/history](accepted-operator-summary.json),P7 DTO |
| FR-025 | MUST تتوقف كل public URL/cache/preview/fallback المعروفة للمرفقات الخاصة القديمة عن منح الوصول غير المأذون قبل الجاهزية؛ تُحفظ الملفات اللازمة دون حذف بيانات غير مأذون أو استبدال خصوصيتها بعشوائية الرابط. | PASS: [P5 private/static/migration100/100](../phase-05/results.md),[H01 accepted V2–V6/history](accepted-operator-summary.json),P7 DTO |
| FR-026 | MUST تبقى عمليات الرفع المتأثرة محكومة بقيود MIME/format/size موثقة ومتسقة أمنيًا وتمنع المحتوى المحظور؛ لا يكفي امتداد يرسله العميل؛ تدعم الملفات عبر العقد الأصلي دون إعادة قواعد التحقق داخل Flutter. | PASS: [P5 private/static/migration100/100](../phase-05/results.md),[H01 accepted V2–V6/history](accepted-operator-summary.json),P7 DTO |
| FR-027 | **SUPERSEDED_BY_PM2026-11-09 / NOT IMPLEMENTED**. Historical: MUST تُفرض القيمة الحالية N8N_WHATSAPP_ENABLED=false وقت تنفيذ الإرسال قرب حد outbound؛ تمنع كل job قديم/جديد/مؤجل/retry أو مستأنف يدويًا عبر الطابور العادي وdispatch مباشر من بدء إرسال حقيقي بعد التعطيل، دون تسجيل sent/delivered زائف؛ لا يعتمد job/worker على قرار enabled قديم محفوظ وقت enqueue أو قبل تغير التعطيل. | SUPERSEDED_BY_PM_SCOPE_DECISION;[Phase6](../phase-06/scope-decision.md) |
| FR-028 | **SUPERSEDED_BY_PM2026-11-09 / NOT IMPLEMENTED**. Historical: MUST يحافظ التكامل على التوقيع وreplay protection وclaim/idempotency ومنع التكرار وعدم إعادة إرسال حالة uncertain آليًا؛ نجاح queue/enqueue ليس دليل إرسال أو تسليم. | SUPERSEDED_BY_PM_SCOPE_DECISION;[Phase6](../phase-06/scope-decision.md) |
| FR-029 | **SUPERSEDED_BY_PM2026-11-09 / NOT IMPLEMENTED**. Historical: MUST يبقى تسلسل التكامل Flutter→Laravel→n8n→Meta؛ لا عنوان تكامل أو credentials أو claim tokens تقدم للعميل، ولا اتصال Flutter مباشر بـn8n/Meta. | SUPERSEDED_BY_PM_SCOPE_DECISION;[Phase6](../phase-06/scope-decision.md) |
| FR-030 | MUST يملك العقد الأصلي أشكالًا مستقرة للنجاح: مورد تحت data، collection تحت data[] وmeta/links للصفحات؛ تُوثق types/nullability/status/role/state values وتُحفظ العربية كعرض دون الاعتماد عليها كآلة حالات. | PASS: [frozenv1](../../contracts/mobile-api.md),[P7 76/76](../phase-07/results.md),[B8 regression](../phase-08/full-results.md) |
| FR-031 | MUST تلتزم الاستجابات الأصلية بجدول الأخطاء أدناه، مع code وmessage وerrors للحقول عند الحاجة وrequest_id غير حساس؛ لا redirects/HTML/stack trace في المسارات الأصلية. | PASS: [frozenv1](../../contracts/mobile-api.md),[P7 76/76](../phase-07/results.md),[B8 regression](../phase-08/full-results.md) |
| FR-032 | MUST تُوثق التواريخ date-only كـYYYY-MM-DD دون تحويل timezone، والأوقات المطلقة ISO8601 مع offset، والمبالغ decimal غير ملتبسة مع عملتها؛ لا تتغير الأنواع بتغير شكل pagination أو العلاقات المحمّلة. | PASS: [frozenv1](../../contracts/mobile-api.md),[P7 76/76](../phase-07/results.md),[B8 regression](../phase-08/full-results.md) |
| FR-033 | MUST يستقر العقد الأول المعد للموبايل تحت /api/v1، ويحافظ التغيير على clients المثبتة وweb/integration routes الحالية؛ التغييرات غير المتوافقة تحتاج نسخة/انتقال معلنين واختبارات توافق، دون نسخ منطق الأعمال. | PASS: [frozenv1](../../contracts/mobile-api.md),[P7 76/76](../phase-07/results.md),[B8 regression](../phase-08/full-results.md) |
| FR-034 | MUST تستخدم العمليات المعدة القواعد المرجعية الحالية للموافقة والاشتراك والtenant والإشعارات والارتباط؛ لا اختلاف ناتج عن controller موازٍ أو حساب عميل. لا يُعامل إصلاح تجاوز مثبت كتراجع مطلوب حفظه. | PASS: [frozenv1](../../contracts/mobile-api.md),[P7 76/76](../phase-07/results.md),[B8 regression](../phase-08/full-results.md) |
| FR-035 | MUST تُحدد الآثار الجانبية للعمليات الحالية ولا يُفترض أن GET آمن للمراجعة لمجرد اسمه؛ read-only contract جديد لسرد/هوية/metadata لا ينشئ defaults أو يعدل أدوارًا ضمنيًا، وتبقى provisioning عملية مصرحًا بها منفصلة. | PASS: [frozenv1](../../contracts/mobile-api.md),[P7 76/76](../phase-07/results.md),[B8 regression](../phase-08/full-results.md) |
| FR-036 | MUST لا تُعلن MOBILE_BACKEND_READY حتى تحقق بوابة الجاهزية أدناه بأدلة اختبارات منفذة ومراجعة PM؛ وجود endpoint أو spec/checklist مكتملة لا يجيز بدء Flutter. | PASS;finalPMacceptanceACCEPTED;[PHP8.3](php83-compatibility.md),[PM](pm-decision.md) |

## SR requirements — كل24 بنداً

| SR | المعيار | الدليل/الحكم |
|---|---|---|
| SR-001 | MUST يتحقق الخادم من المصادقة والتفويض لكل عملية محمية، دون الاعتماد على إخفاء وجهة أو تفضيل دور بالعميل. (I, II, VI) | PASS: [P0 mapping](p0-closure.md),[B8 security462/462](../phase-08/full-results.md),[P7 contracts](../phase-07/results.md) |
| SR-002 | MUST لا تحتفظ الجلسة الأصلية بحق الوصول بعد تعطيل الحساب/تغير موافقته/انتهاء الرمز أو إلغائه؛ تُختبر الحالات كلٌ وفق كود الرفض الموثق. (M03; VI) | PASS: [P0 mapping](p0-closure.md),[B8 security462/462](../phase-08/full-results.md),[P7 contracts](../phase-07/results.md) |
| SR-003 | MUST يمنع عزل المدرسة تسريب records/counts/metadata من list/show/mutations/downloads في كل endpoint معد وفي المسارات القديمة المتأثرة. (H02; I, III) | PASS: [P0 mapping](p0-closure.md),[B8 security462/462](../phase-08/full-results.md),[P7 contracts](../phase-07/results.md) |
| SR-004 | MUST لا تمنح IDs الأجنبية أو route binding أو query/filter/pagination أو nested IDs حق مدرسة أو أب غير مأذون. (III) | PASS: [P0 mapping](p0-closure.md),[B8 security462/462](../phase-08/full-results.md),[P7 contracts](../phase-07/results.md) |
| SR-005 | MUST يقتصر قبول/إرجاع حقول المستخدم على allowlist لكل عملية؛ لا تعديل role/is_active/approval/school/delegation من مسار لا يجيز ذلك، ولا privilege escalation لغير المسؤول. (M05; I, VI, XIII) | PASS: [P0 mapping](p0-closure.md),[B8 security462/462](../phase-08/full-results.md),[P7 contracts](../phase-07/results.md) |
| SR-006 | MUST يطبق قرار ACCOUNT_LEVEL المحسوم في A-04 على اتحاد أدوار Spatie وlegacy، لا primaryRole/ترتيبIDs؛ pending/rejected للحساب ذي دور حساس يمنع كل وصوله التشغيلي مهما أضيف دور غير حساس. لا موافقة لكل role أو علاقة مستحدثة، ويختبر القرار نفسه في الويب والموبايل دون نسخ للعميل. (M04; II, VI, XIV) | PASS: [P0 mapping](p0-closure.md),[B8 security462/462](../phase-08/full-results.md),[P7 contracts](../phase-07/results.md) |
| SR-007 | MUST يزول الوصول المدرسي التاريخي غير المأذون بعد staff transfer/permission change في list وباقي العمليات، دون الاعتماد على assigned_to وحده. (H02; III) | PASS: [P0 mapping](p0-closure.md),[B8 security462/462](../phase-08/full-results.md),[P7 contracts](../phase-07/results.md) |
| SR-008 | MUST لا يمنح URL معلوم/مخمن أو public alias/cache حق ملف خاص؛ كل طلب تنزيل خاص يحتاج تفويضًا. (H01; VII) | PASS: [P5 tests100/100](../phase-05/results.md);[H01 accepted physical/HTTP/history](accepted-operator-summary.json) |
| SR-009 | MUST يتطلب تنزيل مرفق إجازة حق العملية المحدد للإجازات؛ صلاحية calendar/holiday/leave-types وحدها لا تكفي. (M01; I, VII) | PASS: [P5 tests100/100](../phase-05/results.md);[H01 accepted physical/HTTP/history](accepted-operator-summary.json) |
| SR-010 | MUST تشمل حماية الملفات legacy/public fallback والpreview والروابط القديمة، مع إبقاء branding العام خارج النقل غير المبرر. (H01, M07; IV, VII) | PASS: [P5 tests100/100](../phase-05/results.md);[H01 accepted physical/HTTP/history](accepted-operator-summary.json) |
| SR-011 | MUST توحد سلامة رفع الملفات المتأثرة وفق قواعد موثقة مانعة للمحتوى الخطر وتختبر MIME/size/private-download؛ لا mode غير آمن في بيئة مماثلة للإطلاق. (M07; VII) | PASS: [P5 tests100/100](../phase-05/results.md);[H01 accepted physical/HTTP/history](accepted-operator-summary.json) |
| SR-012 | MUST لا يخلق seed/bootstrap حساب admin بكلمة مرور ثابتة داخل المصدر ولا يعيد ضبط admin موجود ضمنيًا؛ يُفحص H03 دون طباعة القيم أو تشغيله على بيانات حقيقية، وأي rotation/deletion لاحقة تحتاج الموافقات الخاصة بها. (H03; I, XIII) | PASS: [SafeAdminSeeder4/4](../phase-03/seeding.md);[P0T0-11](p0-closure.md) |
| SR-013 | MUST لا تعتمد الاختبارات/إعداد الإطلاق على cached production credentials، ولا تكشف الأخطاء تفاصيل debug؛ debug المتاح للمستخدم في إعداد الإطلاق false أو ما يكافئ منع الإفشاء، ويثبت ذلك غير إنتاجيًا. حالة live لا تُفترض. (H05; IX, X, XIII) | PASS: guards+EnvironmentSafetyTest;[H05 final debugOFF/CGI non-disclosure](accepted-operator-summary.json);not rawINI measurement |
| SR-014 | **SUPERSEDED_BY_PM2026-11-09 / NOT IMPLEMENTED**. Historical: MUST يفحص كل حد فعلي لبدء outbound قرار التعطيل الحالي، بما يمنع stale job/worker decision من تجاوز false حتى عند الاستئناف اليدوي للطابور؛ يُثبت صفر outbound في المصفوفة. (H04; VIII) | SUPERSEDED_BY_PM_SCOPE_DECISION (WA/OTP);[scope](../phase-06/scope-decision.md);genericphone compatibility10 retained |
| SR-015 | **SUPERSEDED_BY_PM2026-11-09 / NOT IMPLEMENTED**. Historical: MUST لا تُجرى رسائل WhatsApp/OTP حقيقية أو تغيير credentials/workflow/provider أثناء specification أو اختبارات هذه الميزة؛ تستخدم fakes فقط. (VIII, X, XII) | SUPERSEDED_BY_PM_SCOPE_DECISION (WA/OTP);[scope](../phase-06/scope-decision.md);genericphone compatibility10 retained |
| SR-016 | **SUPERSEDED_BY_PM2026-11-09 / NOT IMPLEMENTED**. Historical: MUST لا تضعف تغييرات kill-switch التحقق من HMAC/replay/claims أو تكرر الإرسال أو تحول uncertain إلى retry تلقائي. (VIII) | SUPERSEDED_BY_PM_SCOPE_DECISION (WA/OTP);[scope](../phase-06/scope-decision.md);genericphone compatibility10 retained |
| SR-017 | MUST لا تظهر passwords/OTP/HMAC/Meta/private keys في responses، ولا أسرار فعلية في logs/specs/fixtures. سر الجلسة يُعاد فقط لصاحبه في استجابة الإصدار المخصصة ولا يظهر في قوائم الجلسات أو query URLs أو أي استجابة أخرى؛ نقل الجلسات المحمية خارج الاختبار المحلي المعزول يكون عبر HTTPS. (VI, XIII) | PASS: EnvironmentSafetyTest/MobileErrorContractTest/MobileSerializationTest;[P7](../phase-07/results.md);[H05](accepted-operator-summary.json) |
| SR-018 | MUST تعمل security/regression tests ببيانات مصطنعة وDB وcache وqueue وmail معزولة عن الإنتاج، مع منع real-provider network أثناء الاختبارات. (IX, X) | PASS: isolated8.2+PHP8.3guards/transport;[PHP8.3](php83-compatibility.md) |
| SR-019 | MUST تُحفظ الأعمال السابقة والموافقات المستقلة؛ لا commit/push/merge/deploy/destructive DB/production integration activation بلا إذن منفصل، ولا تعديل staging/production خلال specification. (X, XI, XII) | PASS: [authorization](pm-phase09-authorization.txt);sourceguard clean;local docs-only;no production/noGitmutation |
| SR-020 | MUST تسمح error/request identifiers بتتبع العطل دون كشف وجود مورد أجنبي أو traces أو أسرار؛ النص المترجم لا يغير machine code أو الصلاحية. (L03; V, XIII) | PASS: EnvironmentSafetyTest/MobileErrorContractTest/MobileSerializationTest;[P7](../phase-07/results.md);[H05](accepted-operator-summary.json) |
| SR-021 | MUST لا تسمح عمليات OTP الحالية المتأثرة بتجاوز budget/cooldown عبر تغيير IP أو طلبين متزامنين؛ يُتحقق الرقم المرجعي الحالي بالخادم ويُبطل التحقق القديم عند تغييره، مع حفظ الرسائل disabled وفحص الرفض آليًا. (L04; I, VIII, IX) | SUPERSEDED_BY_PM_SCOPE_DECISION (WA/OTP);[scope](../phase-06/scope-decision.md);genericphone compatibility10 retained |
| SR-022 | MUST لا تمكن فروق نتائج invalid-login/forgot-password أو غياب rate limit من كشف حسابات أو تخمين غير محدود؛ يجرب الحد والتوقف والعودة بعد الانتظار باختبارات مصطنعة. (M06; I, VI) | PASS: [P0 mapping](p0-closure.md),[B8 security462/462](../phase-08/full-results.md),[P7 contracts](../phase-07/results.md) |
| SR-023 | MUST يحقق مشرف متعدد المدارس حق الاختيار والعلاقة والعملية لكل طلب؛ لا يعطى صلاحيات مدير مدرسة لمجرد إشرافه أو علمه بمعرفها. (III, VI) | PASS: [P0 mapping](p0-closure.md),[B8 security462/462](../phase-08/full-results.md),[P7 contracts](../phase-07/results.md) |
| SR-024 | MUST لا يملك مستخدم سرد/إلغاء جلسات مستخدم آخر أو إحياء رمز جهاز مفقود/ملغى؛ الإلغاء لا يعتمد على ثقة بمعرف جلسة من العميل. (VI) | PASS: [P0 mapping](p0-closure.md),[B8 security462/462](../phase-08/full-results.md),[P7 contracts](../phase-07/results.md) |

SR018: isolation وruntimequalification الآنPASS؛لاوصوللقواعدخادم. SR021القديمضمنscopeWhatsAppOTPsuperseded؛لا قناةتحققمخترعة.

## قصصالمستخدم14 ومعاييرGiven/When/Then

| Story | acceptance cases | verdict | source/tests/executed outcome |
|---|---|---|---|
| US01 native token/current identity/device independence | US01-A, US01-B | PASS on reviewed8.2/C-source scope | [P2](../phase-02/acceptance.md);NativeAuthenticationTest/NativeDeviceLifecycleTest |
| US02 invalidcredentials/noenumeration/rate429 | US02-A, US02-B | PASS on reviewed8.2/C-source scope | [P2](../phase-02/acceptance.md);MobileRatePolicyTest |
| US03 approval matrix/currentstate token denial | US03-A, US03-B | PASS on reviewed8.2/C-source scope | [P3](../phase-03/results.md);AccountApprovalMatrixTest/NativeAccountStateTest |
| US04 current school/supervisor relationship | US04-A, US04-B | PASS on reviewed8.2/C-source scope | [P4](../phase-04/results.md);CurrentTenantContextTest |
| US05 cross-school/foreign IDs/no counts or writes | US05-A, US05-B | PASS on reviewed8.2/C-source scope | [P4](../phase-04/results.md);ForeignRelationshipTest;[P5](../phase-05/results.md) |
| US06 assignment+school+parent/transfer | US06-A, US06-B | PASS on reviewed8.2/C-source scope | [P4](../phase-04/results.md);StaffTaskIsolationTest;MobilePaginationTest |
| US07 authorized exactbytes/currentaccess removal | US07-A, US07-B | PASS on reviewed8.2/C-source scope | [P5](../phase-05/results.md);PrivateAttachmentAuthorizationTest;[H01V3](accepted-operator-summary.json) |
| US08 anonymous/cross-school/leave-specificpermission | US08-A, US08-B | PASS on reviewed8.2/C-source scope | [P5](../phase-05/results.md);PrivateAttachmentAuthorizationTest;[H01V4/V5/history](accepted-operator-summary.json) |
| US09 public logo/privateclassification | US09-A, US09-B | PASS on reviewed8.2/C-source scope | PublicAssetCompatibilityTest/StaticPrivateUrlTest;[P5](../phase-05/results.md);[H01V6](accepted-operator-summary.json) |
| US10 historical WhatsApp scope removed | US10-A, US10-B | SUPERSEDED_BY_PM_SCOPE_DECISION | [scope decision](../phase-06/scope-decision.md);active app/web20/20 only |
| US11 errors/types/pages/date/rates/large lists | US11-A, US11-B | PASS on reviewed8.2/C-source scope | [P7](../phase-07/results.md);contract/error/pagination/rate/serialization76/76;[B8scale](../phase-08/full-results.md) |
| US12 websessions/CSRF/shared business regressions | US12-A, US12-B | PASS on reviewed8.2/C-source scope | NativeWebCompatibilityTest/WebSessionSafetyTest;[B8full](../phase-08/full-results.md);knownExcelexception preserved |
| US13 current/target/all revoke/foreign token denied | US13-A, US13-B | PASS on reviewed8.2/C-source scope | NativeDeviceLifecycleTest/NativeAccountStateTest;[P2](../phase-02/acceptance.md) |
| US14 executed evidence and refuse premature readiness | US14-A, US14-B | PASS;finalPMdecisionACCEPTED | [baseline](../phase-01/formal-acceptance-after-repair.md);[B8](../phase-08/full-results.md);[PM accepted](pm-decision.md) |

[spec](../../spec.md) نصكلscenarioالأصلي. معاييرUS01–09/11–13 كلمنهاA/BمربوطةبـclassesالمذكورةونتائجإعادةB8؛لا nativefeaturesجديدة. US14-Aيجتازbaseline8.2ومصفوفة8.3المعزولة؛US14-BيبقيالإعلانالنهائيمرتبطاًبقبولPM.

## معاييرالنجاحSC001–010

| SC | الحكم والدليل |
|---|---|
| SC-001 | PASS scoped:auth/eligibility/errors,P2/P3/P7 |
| SC-002 | PASS scoped:tenant/foreign IDs/counts/no writes,P4/P5 |
| SC-003 | PASS scoped:transfer/assignment/school/parent,P4 |
| SC-004 | PASS:H01final+historical/P5 exactbytes/denial/branding |
| SC-005 | SUPERSEDED:originalWhatsAppdelivery;active app/webscopePASS |
| SC-006 | PASS scoped:P7contract76/76+B8scale |
| SC-007 | PASS scoped:expiry/revoke/P2P3 |
| SC-008 | PASS for requiredcompatibility;knownExcelstillFAIL/acceptedexception,not greenfull |
| SC-009 | PASS scoped:affected webregressions;intentional bypass fixes documented |
| SC-010 | PASS;finalPMacceptanceACCEPTED;applicableHIGH=0 |

## البوابات وP0 وHIGH والدستور

- [T113](checklist-review.md): جميع البوابات الـ84 مسجلة فردياً، دون تعديل checkbox المراجع الأصلي.
- [T114](p0-closure.md): ‏12 disposition مستوفاة. T0-12 التاريخي مستبدل؛ اختبارات app/web البديلة منفذة.
- [T115](high-findings.md): ‏H01/H02/H03/H05 PASS؛ H04 SUPERSEDED_SCOPE_RESOLVED.
- [T116](constitution-review.md): مراجعة 14 مبدأ؛ 14 PASS بعدPHP8.3.
- [T117](evidence-manifest.md): تطابق المصدر PASS؛ ربطإصدارالتشغيلبالدليلPASSبعدPHP8.3.

لا استثناء أمني جديد أو إعفاء لمجموعة P0 نشطة.

## مراحل القبول والاختبارات المنفذة

| Phase | source | CI | executed result | acceptance |
|---|---|---|---|---|
| 1 | debaff7f1c864b9c0d9d54a300092cd7a753261b | 42482335970/128736780096 | 523/522/1/0/0 | [record](../phase-01/formal-acceptance-after-repair.md) |
| 2 | e448618bffe70e10352b52bf20d3ffc6f43b14f5 | 42486583770/128749676130 | focused16/16;full539/538/1/0/0 | [record](../phase-02/acceptance.md) |
| 3 | 7b8fd762bf7b61325fecf51f1dc5adba015d8221 | 42493350045/128770255571 | focused128/128;full615/614/1/0/0 | [record](../phase-03/acceptance.md) |
| 4 | ff58fcdf0c71940d02e17905df1233691e0f0928 | 42496584067/128780124189 | focused65/65;full638/637/1/0/0 | [record](../phase-04/acceptance.md) |
| 5 | ecb6b7fd7d1b06bf7d4575f1b194ce77fa76fb54 | 42598238825/129096063283 | focused100/100;full670/669/1/0/0 | [record](../phase-05/acceptance.md) |
| 6 | d1810ccef7c8c64651098eecd33f0fbe017bfb62 | 42607287769/129126371236 | active20/20;full647/646/1/0/0 | [record](../phase-06/acceptance.md) |
| 7 | 8020e8b9363e1d100a1012c9b0a68d245169f2c1 | 42614974604/129152556298 | focused76/76;full723/722/1/0/0 | [record](../phase-07/acceptance.md) |
| 8 | fbc63a4615f48ac0f832d91945d9652aeabef466 | 42622792050/129178897906 | focused462/462;bothfull725/724/1/0/0 | [record](../phase-08/acceptance.md) |

المجاميع متداخلة ولا تُجمع. البيئة المنفذة: PHP 8.2.35، Composer 2.11.1، Laravel 12.50.0، PHPUnit 11.5.51، Node 22.23.4، MySQL 8.0.46. فحوص extensions/platform/build/lint نجحت؛ قاعدة البيانات والشبكة معزولتان، mail array/fakes وqueue sync دون workers، ولا host .env.

B8 focused: ‏462/462، ‏5313 assertions، ‏60.150 ثانية، ‏128.50 MB. Direct full: ‏725 اختباراً، ‏724 نجاحاً وفشل Excel واحد، ‏7066 assertions، ‏115.270 ثانية، ‏166.50 MB. Errors0/skips0؛ لا مدة منفصلة معلنة لـArtisan full. الأوامر وexit codes وبصمات JUnit الأصلية في [full-results](../phase-08/full-results.md) و[R8](../phase-08/validated-runtime.json). هذهنتائج8.2المحفوظة؛تلاها[تشغيلPHP8.3](php83-compatibility.md)بنفسالعددوالـexitsونطاقالأمان،علىC.

## المهاجرات والاستعادة والعقد وبيئة الاختبار

الإضافتان هما PAT القياسي وprivate-file manifest. نجحت 114 migration معزولة، وPAT rollback/reapply/repeat؛ احتفظ manifest بالسجلات والخصوصية. تغطي 18 حالة CAS/checksum/crash/recovery، مع مصالحة جرد 504 ملفات مصطنعة. لا schema جديد في Phases 6–8 ولا استعادة إنتاجية مزعومة. [دليل الاستعادة](../phase-05/migration-results.md).

عقد v1 يثبت عشر نقاط: login، me، logout، devices list/target/all revoke، tasks list/detail، وattachment/leave downloads. النجاحات 200/204؛ الأخطاء 401/403/404/409/422/429/500 مع safe UUID؛ pagination ‏25/100؛ UTC/date-only وعرض عربي؛ لا provisioning في GET. تثبت الاختبارات حجز الحدود بين عمليتين مستقلتين، وثبات queries: الأجهزة 7→7، المرفقات 26→26، المهام 17→17. [العقد](../../contracts/mobile-api.md)، [بصمات المصدر](source-guard.json).

الدليل التشغيلي المقبول قبل Phase 9 يخص test.edaratek.com فقط: QA خاص بـ14 طلباً، ومصالحة تاريخية بطلبَي HEAD، وH05 بطلب CGI واحد. Laravel debug OFF؛ لم يكشف CGI500 sentinel أوclass أوtrace أوpath أوfatal details؛ التنظيف وresource provenance PASS. لا شهادة إنتاج، أو قياس مفترض لكل PHP INI، أو اختيار يدوي لـCGI cwd.

## القيود والفجوات

- فشل Excel القديم SQLSTATE42S22/1054 ما زال ظاهراً؛ مصدره ثابت، وتصنيفه السابق غير محجب للموبايل ومقبول من PM. التحذيرات وdeprecation محفوظة أيضاً.
- إغلاق H01 خادمي؛ لا يسحب بايتات سبق تنزيلها لدى العميل، ولا يدعي purge عالمي أو شهادة إنتاج. تنظيف fixture الحالي PASS. بقيت جلسات قديمة غير منسوبة UNKNOWN في سجل QA؛ لا يُدعى حذفها، ولا تُعاد بوابة H01 المقبولة بسببها.
- لا native push أو OTP أو feed/deep links أو CRUD جديد أو تحويل كل323 route. تأجيلات UI/polling/accessibility/FCM في spec باقية؛ generic phone coverage ليست delivery proof.
- سجلات BLOCKED/NO_PHASE9 التاريخية محفوظة بتاريخها؛ تفويض PM الحالي وملحق قبول Phase 8 حاكمان للحالة الحالية. التحقيقات القديمة في cached callable وApache لا تتحول إلى PASS ضمني، ولا تضاف كبوابات جديدة بعد قبول H05 النهائي.
- R9-01 مغلق بدليل توافق PHP8.3؛ قبول PM النهائي ورد صراحةً في 2026-11-17، وليس قبولاً ذاتياً.

## الخطوة الآمنة التالية

أُغلق T119/T120 بقرار PM النهائي وسُجلت الجاهزية. التوقف الآن؛ تبقى تغييرات الوثائق لموافقة Git مستقلة. لا اختبار آخر أو تنفيذ Flutter أو نشر أو تغيير بيئة من هذا القبول.

PROPOSE_MOBILE_BACKEND_READY=YES؛ MOBILE_BACKEND_READY=YES.

```ini
APPLICATION_CODE_CHANGED=NO
DATABASE_CHANGED=NO
MIGRATIONS_EXECUTED=NO
PRODUCTION_TOUCHED=NO
DEPLOYMENT_EXECUTED=NO
ROOT_USED=NO
COMMIT=NO
PUSH=NO
MERGE=NO
FLUTTER_STARTED=NO
```

## تحديث توافق PHP8.3

[php83-compatibility.md](php83-compatibility.md) و[php83-runtime.json](php83-runtime.json) يسجلانimage digest،PHP/MySQL،كلfocused/fullcountsوالأزمنةالمعلنةوالتحذيرات،مطابقةالفشلالقديم،ومقارنة8.2/8.3والتنظيف. أعيدتالبنودCHK008/081/083وتوابعهافقط؛بقية80بنداًوP0/HIGHلمتُراجعمنجديد.

## إغلاق PM النهائي — 2026-11-17

[PM_PHASE_9_DECISION=ACCEPTED](pm-decision.md)؛ [T120=PASS](acceptance.md)؛ PHASE_9=CLOSED؛ MOBILE_BACKEND_READY=YES؛ MOBILE_BACKEND_READINESS=PASS. هذا تحديث توثيق فقط، دون إعادة تشغيل الاختبارات أو تعديل نتائجها التاريخية.
