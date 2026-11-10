# T041 — حصر نقاط المرحلة الرابعة

إضافة مراجعة العدادات: GET manager.dashboard يستخدم SchoolContextResolver بدلاً من تفضيل managedSchool التاريخية على المدرسة الحالية؛ SchoolDashboardAnalyticsService يطبق consistentParent على teachersOpenTickets قبل التجميع. لا يوجد عداد مهام مستقل في Staff/Dashboard: الصفحة تستهلك staff.subtasks.index. اختبارات StaffTaskIsolationTest وManagerDashboardAnalyticsTest تغطي اختيار المدرسة الحالية والطفل غير المتسق وتوافق الصفحة.

البداية: `7b8fd762bf7b61325fecf51f1dc5adba015d8221`، فرع `001-mobile-backend-readiness`. التفويض الحالي T041–T054 فقط، مع CI/MySQL مؤقت وpush لفرع الميزة. لا تغيير لمسار StudentExcelImport ولا بدء Phase5.

| المجموعة | طرق التطبيق الفعلية | قواعد النطاق والاختبار |
|---|---|---|
| Staff/SubtaskController | GET index/show؛ POST reply/submit | دور staff الحالي + المدرسة النشطة الحالية + assignment + تطابق مدرسة ticket الأم قبل العد/البحث/الصفحات وكل إجراء |
| Manager/SubtaskController | POST store؛ PUT update؛ POST approve | ملكية المدرسة الحالية، ticket مسند للمدير، subtask في نفس المدرسة والأم، assignee موظف مؤهل في المدرسة نفسها |
| Manager/TicketController | GET index/show؛ POST store/finalReport/close | المدير المالك الحالي، المدرسة الحالية، ticket مسند إليه؛ مدرسة/منشئ ومالك مشتقون من الخادم |
| Supervisor/TicketController | GET index/show؛ POST store/close؛ PUT update | منشئ التذكرة ودور supervisor وعلاقة إشراف المدرسة الحالية وحالتها، selector فقط في إنشاء المهمة المعلن؛ لا سلطة مدير من الإشراف |
| native staff | GET /api/v1/staff/tasks و/{id} | Bearer وأهلية الحساب ودور staff ثم resolver واستعلام النطاق نفسه؛ DTO دنيا، 25/100، totals بعد scope |
| native me | GET /api/v1/me | مدرسة مأذونة أو null، دون اختيار عميل/توفير بيانات افتراضية؛ capabilities المدرسة لا تجمع مجموعات مدرسة سابقة |
| middleware | EnsureManagerOwnsSchool؛ EnsureActiveSchoolAssociation؛ EnsureAcademicPlanningAccess | مسار قرار مدرسي واحد؛ الطلبات التمهيدية للمدير تحت owned-school لا تشترط نشاط العمليات؛ العمليات وquick-setup ترفض التعليق |
| QuickSetupStatusController | GET /quick-setup/status | السياق والنشاط قبل أي counts؛ لا fallback إلى مدرسة يختارها العميل |
| مجموعات التفويض | User::groupedSchoolPermissionNames | شرط مدرسة حالية مأذونة حتى مع علاقة eager قديمة؛ إزالة المجموعة/الصلاحية وإعادة النقل تغير الطلب التالي |

لا توجد طرق delete أو accept أو reassign أصلية للموظف في المصدر؛ لا تضاف وظائف أعمال جديدة. الإسناد متاح في تعديل المدير الحالي فقط. التفاصيل/الأفعال في الويب تحت policy مباشرة قبل البيانات مع إبقاء عقد403 القائم؛ native يستخدم lookup داخل scope ويخفي المورد بـ404. كلتا القناتين تشتركان في invariant البيانات.

ملفات/تنزيلات reply الموجودة تُذكر هنا للحصر فقط؛ تصحيح القرص/public URLs/metadata وطرق download ومصفوفة bytes تؤجل صراحة إلى T055–T070. لا native download ولا نقل ملفات في Phase4. قائمة native لا تعرض transcript أو paths أو attachment URLs؛ تفاصيل native تؤجل بيانات المرفقات إلى Phase5.

الاختبارات الجديدة في tests/Feature/Mobile/Phase4، والـfixtures في tests/Support/MobileSchoolFixtures.php: مدرستان، مدير ومشرف وموظف لكل مدرسة، موظف آخر من المدرسة نفسها، transfer مع task تاريخية، parent مخالف، مجموعة صلاحيات باقية من المدرسة القديمة. لا بيانات فعلية.
