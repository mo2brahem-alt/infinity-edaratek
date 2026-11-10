# T042/T044/T047–T050/T052 — مصفوفة العزل

حالة التنفيذ والأعداد في [results.md](results.md). أسماء الاختبارات أدناه كلها ضمن `tests/Feature/Mobile/Phase4/` إلا حيث يذكر غير ذلك. الملفات المقترحة في tasks.md نُفذت داخل namespace Phase4 المتسق مع Phase3، وnative controller داخل Mobile المتسق مع مكونات المصادقة القائمة.

| endpoint/method | الاختبار السلبي والنتيجة المطلوبة | الحالة المسموحة |
|---|---|---|
| GET /api/v1/staff/tasks | A/B مع إسناد B إلى staffA، زميل A، أم B مع subtaskA؛ القائمة/total تستبعد الثلاثة | مهمته الحالية فقط، 25 افتراضياً،100 أقصى حد |
| GET /api/v1/staff/tasks/{id} | guessed B/زميل/أم مختلفة أو missing/invalid:404 NOT_FOUND | own200 مع DTO مصغر وdescription |
| native list filters | school_id/tenant_id/organization_id/assigned_to/department_id/role، sort/status/page/per_page غير صالح:422؛ لا أثر على scope | search title فقط وبـLIKE escaped/bound، status enum، id/-id/due_date/-due_date، null-last |
| native list pages | total لا يتضمن B؛ صفحة2 وآخر الصفحة وبعدها لا تسرب | page99:200 data=[] وfrom/to=null؛ links نسبية بفلاتر allowlist |
| GET staff.subtasks.index/show | transfer وinconsistent parent وforeign ID محجوبة؛ تفاصيل الويب403 قبل تحميل البيانات | الشكل القديم array أو data+pagination محفوظ |
| POST staff.subtasks.reply/submit | foreign/transfer/inconsistent parent403، حقول سلطة ونested IDs/body/query422؛ لا message/status writes | reply200 وsubmit200، SUBMITTED |
| POST manager.subtasks.store | ticketB403، assigneeB422، مدرسة الإسناد والأم قبل الإنشاء؛ supervisor view وحده لا يمنح assignment | TicketWorkflowAuthorizationTest:201 في المدرسة الحالية |
| PUT manager.subtasks.update | foreign/mismatched parent403، assigneeB422، ticket_id injection422؛ لا تعديل | title/status الحاليان200 |
| POST manager.subtasks.approve | foreign403، forged school_id422؛ لا status/history write | manager current owner200 APPROVED |
| GET manager.tickets.index/show | school_id=B لا يوسع index/count؛ foreign show403؛ nested subtask ذو مدرسة مختلفة مخفي | قائمة/تفاصيل المدرسة المملوكة المسندة للمدير |
| GET manager.dashboard | المدرسة الحالية المملوكة هي السياق حتى عند وجود مدرسة مملوكة أخرى أقدم؛ teachersOpenTickets لا يعد طفلاً من مدرسة أم مختلفة | ManagerDashboardAnalyticsTest وStaffTaskIsolationTest |
| POST manager.tickets.store | assigneeB أو forged school_id422؛ لا ticket/subtask إضافية | إنشاء internal ticket مع subtask في اختبار الويب201 |
| POST manager.tickets.final_report/close | foreign403، والمدير+المشرف لا يغلق B من مدخل المدير | final report/supervisor close وinternal manager close في اختبار الويب |
| GET supervisor.tickets.index/show | current relationship removal/legacy unconfirmed يخفي total والقائمة ويحجب show403؛ nested child غير متسق مخفي | منشئ التذكرة مع إشراف حالي واعتماد صالح |
| POST supervisor.tickets.store | foreign school403، current school مع managerB422، removal403 | selector معلن يمر فقط بعد إثبات علاقة الخادم201 |
| PUT supervisor.tickets.update; POST close | foreign/removed relationship403؛ school authority422 قبل الكتابة | flow الويب الحالي واختبار notification |
| service markSubtaskStatus | actorA على taskB: AuthorizationException ولا status_history أو status writes | policy العملية المطلوبة قبل كتابة الحالة والسجل |
| GET me/quick-setup | null/409، suspension403، forged manager context403، header لا يغير المدرسة | هوية مؤهلة ومدرسة فعلية؛ native بلا provision |
| affected school report/certificate methods | SchoolTenantIsolationTest: foreign stage/classroom/student/teacher/template/signature/certificate reject، بما فيها delete/cancel حيث هي موجودة | لا إضافة CRUD للمهام |

`SubtaskAccessQuery::forStaff` هو أساس index/detail/count/search/pages وpolicy staff actions: current active school ∩ assigned_to ∩ parent school. `consistentParent` يستبعد الطفل غير المتسق داخل تذاكر المدير/المشرف أيضاً. manager assignee helper يقيد الاستعلام بالمدرسة ثم staff وأهلية الحساب قبل اعتماد foreign key.

لا delete/accept/reassign جديدة للموظف. update/reassignment القائمة للمدير مغطاة. لا يُستحدث endpoint لغرض ملء صف غير موجود في المصفوفة.

قرار التوافق: native lookup داخل scope يعطي404 للمخفي والمفقود دون كشف وجوده. web route binding القائم يتبعه policy فوراً قبل أي load/serialization/write، ويحتفظ403 الذي تثبته اختبارات الويب؛ لم يحوَّل كل رفض صلاحية إلى404. حقول السلطة غير المعلنة422؛ headers ليست مصدراً للسلطة. selector مدرسة المشرف الوحيد المعلن يظل مدخل اختيار، لا إثبات صلاحية.

رفض assignee غير المؤهل يحافظ على422 JSON حتى للـplain web POST؛ استبدال هذا الرفض بـValidationException وحدها أدى إلى302 في اختبار الويب، ولذلك أعيد عقد الاستجابة القديم في helper المركزي دون إزالة predicate المدرسة أو أهلية الموظف.

قرار transcript: native list بلا messages، وdetail الحالي مصغر. قائمة Vue القديمة تستخدم messages والمرفقات فعلاً؛ بقي شكلها للتوافق داخل scope الآمن. الملفات ومساراتها والرفع والتنزيل والتحويل إلى تخزين خاص وmetadata المحمية تخص Phase5 ولم تُنفذ هنا. CHK028 ينتهي الآن إلى إثبات الطرق غير الخاصة بالملفات فقط، وdownload rows مؤجلة صراحة إلى T056/T068. لا شهادة PRIVATE_ATTACHMENT_SECURITY_PASS.
