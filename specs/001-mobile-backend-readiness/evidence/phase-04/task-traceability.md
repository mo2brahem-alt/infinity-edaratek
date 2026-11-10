# T041–T054 — ربط المتطلبات بالتنفيذ والإثبات

الأعداد والنتيجة النهائية وحالة كل مهمة في [EDARATEK_PHASE_4_EXECUTION_REPORT.md](EDARATEK_PHASE_4_EXECUTION_REPORT.md). يشترط إغلاق المهام التنفيذ الفعلي لا مجرد كتابة المصدر. لا صف خاص بالتنزيل محسوب ناجحاً هنا.

| ID | المتطلب المحدد | التنفيذ | الاختبار/الإثبات |
|---|---|---|---|
| T041 | حصر الطرق والعلاقات وfixtures لمدرستين والنقل والمدير والمشرف | endpoint-matrix.md، tests/Support/MobileSchoolFixtures.php | حصر طرق Staff/Manager/Supervisor/native، عداد لوحة المدير، وعدم اختراع delete/accept للموظف |
| T042 | مصفوفة foreign resources/body/query/route/nested/no-write | ForeignRelationshipTest، StaffTaskIsolationTest، SchoolTenantIsolationTest القائم | manager/staff/supervisor methods؛ native404؛ web policy403 الموثق؛ downloads مؤجلة T056/T068 |
| T043 | spoof/context/current relationships/null409/suspension | CurrentTenantContextTest | headers وforged school، manager ownership، supervisor current relation وlegacy confirmation، لا توفير من GET |
| T044 | assignment/current/parent scope وtransfer/count/filter/pages/actions | StaffTaskIsolationTest | B assignment إلى staffA، زميل A، أم غير متسقة، نقل مع token قائم، reply/submit، native بلا transcript |
| T045 | resolver واحد والحراس وme وquick-setup | SchoolContextResolver/Exception، EnsureManagerOwnsSchool/EnsureActiveSchoolAssociation/EnsureAcademicPlanningAccess/EnsureNativeStaffContext، QuickSetupStatusController وCurrentUserResource | current-context.md؛ CurrentTenantContextTest وActiveSchoolAssociationMiddlewareTest وAcademicPlanningAccessTest وQuickSetupStatusTest |
| T046 | current-school delegated groups وإعادة التحميل | User::groupedSchoolPermissionNames، AccountAccessEvaluator::capabilities | loaded collection ثم permission removal وtransfer؛ Phase3 PrivilegeEscalationTest لإزالة Spatie permissions/roles |
| T047 | tenant-aware parent/assignee validation وسياسات العمليات | SubtaskAccessQuery::assignee/rejectAuthority/consistentParent، TicketPolicy::manage/assignSubtask، manager/supervisor controllers | ForeignRelationshipTest؛ no writes وforeign assignee422 وmixed manager+supervisor؛ nested child مخفي |
| T048 | استعلام مهام مشترك قبل العد والفلاتر والصفحات | SubtaskAccessQuery::forStaff، Staff/SubtaskController، SubtaskPolicy؛ consistentParent داخل analytics | StaffTaskIsolationTest، HeavyEndpointsPaginationTest، ManagerDashboardAnalyticsTest |
| T049 | reply/submit/service يثبتون المورد والعملية قبل الكتابة | Staff/SubtaskController وTicketingService::markSubtaskStatus | own200/foreign403، service actorA-taskB يرفض دون status/history؛ TicketWorkflowAuthorizationTest وRealtimeNotificationFlowTest |
| T050 | native list/detail25/100 بقاعدة النطاق نفسها | Mobile/StaffTaskController، StaffTaskListRequest، routes/api.php وnative.staff_context | StaffTaskIsolationTest: count/pages/filter/sort/foreign404/bearer-only؛ DTO النهائي والملفات لاحقاً |
| T051 | تنفيذ سحب current context/permission وإعادة اختبار الحراس | CurrentTenantContextTest + اختبارات الحراس القائمة | current-context.md، أعداد CI في results.md؛ حساب غير مرتبط school=null/409، تعليق403، no provisioning |
| T052 | تنفيذ مصفوفة الطرق الحالية والتصفية والأدوار | isolation.md ومجموعة Phase4 المركزة | جميع الطرق الحالية غير الخاصة بالملفات مع مثبتات إيجابية وسلبية؛ no new task CRUD؛ ملفات Phase5 pending |
| T053 | Phase2/3 وweb regressions وCHK023–032 | run-baseline.sh وsummarize-junit.php وprobe-mobile-auth/phase3-gate | runs منفصلة للمرحلتين ثم full؛ لا إخفاء StudentExcelImportTest ولا تعديل مصدره |
| T054 | بوابة TENANT_ISOLATION_PASS وSTAFF_TASK_ISOLATION_PASS | probe-phase4-gate.php، acceptance.md، validated-runtime.json | focused لا failures/errors/skips، حضور الأصناف المطلوبة، full لا فشل سوى الاستيراد المعروف؛ STOP قبل T055 |

الاختبارات الجديدة داخل `tests/Feature/Mobile/Phase4/`؛ استخدام اسم CurrentTenantContextTest يجمع متطلبات TenantInjectionTest المقترح، وForeignRelationshipTest مع اختبارات المدرسة القائمة يحقق متطلبات TenantEndpointMatrixTest المقترح. المسارات المقترحة ليست إطاراً موازياً للمصادقة. التحكم في Bearer وحالة الحساب والاستجابات يعيد استخدام Phase2/3.
