# نتائج Phase 4 المنفذة — 2026-11-08

`PHASE_4_PASS_AWAITING_PM_REVIEW`؛ T041–T054 مكتملة تقنياً للنطاق الحالي غير الخاص بالملفات. `MOBILE_BACKEND_NOT_READY`. لا T055 ولا Phase5.

المصدر المعتمد: [run42496584067/job128780124189](https://github.com/mo2brahem-alt/infinity-edaratek/actions/runs/42496584067/job/128780124189)، SHA `ff58fcdf0c71940d02e17905df1233691e0f0928`. [validated-runtime.json](validated-runtime.json) يحتوي سجلات JSON المنقاة الفعلية؛ [source-manifest.json](source-manifest.json) يربط34 ملفاً وSHA256 بالمصدر المختبر. لا سجلات طلبات أو JUnit خام أو أسرار مرفقة.

| التشغيل | TOTAL | PASSED | FAILED | ERRORS | SKIPPED | WARNINGS | exit |
|---|---:|---:|---:|---:|---:|---:|---:|
| Phase4 focused |65|65|0|0|0|65|0|
| Phase3 regression |128|128|0|0|0|128|0|
| Phase2 native regression |16|16|0|0|0|16|0|
| authoritative full |638|637|1|0|0|637|1|

هذه تشغيلات مستقلة ومتداخلة في التغطية؛ لا تجمع أعدادها. زادت المجموعة الكاملة من615 إلى638 بإضافة23 اختباراً جديداً. مجموعة Phase4 تشمل23 جديداً و42 اختبار ويب/حراس/عزل قائم. جميع tests داخل namespace Mobile/Phase4 مرت في focused وfull.

| صنف Phase4 focused | العدد |
|---|---:|
| CurrentTenantContextTest |8|
| ForeignRelationshipTest |8|
| StaffTaskIsolationTest |7|
| TicketWorkflowAuthorizationTest |1|
| HeavyEndpointsPaginationTest |4|
| RealtimeNotificationFlowTest |2|
| ManagerDashboardAnalyticsTest |6|
| ActiveSchoolAssociationMiddlewareTest |9|
| AcademicPlanningAccessTest |6|
| QuickSetupStatusTest |6|
| SchoolTenantIsolationTest |8|

الأمر التنفيذي المعتمد داخل CI: `./tools/testing/run-tests.ps1`، الذي يستدعي `tools/testing/run-baseline.sh` داخل scratch من المصدر المتتبع وصورة محصورة وشبكة MySQL داخلية. لا PHP/Docker محليين متاحين لتشغيل اختبارات التطبيق على هذا الجهاز؛ التنفيذ الفعلي أعلاه في CI. تحقق `git diff --check` و`bash -n tools/testing/run-baseline.sh` محلياً.

الأمر المركز هو phpunit مع `-c phpunit.mysql.xml` و512M و`--log-junit`/`--log-events-verbose-text` في tmpfs، على مجلد `tests/Feature/Mobile/Phase4` والملفات الثمانية القائمة في جدول الأصناف أعلاه. أمر Phase3: مجلد Mobile/Phase3 مع SafeAdminSeederTest وAuth/PasswordResetTest وAccountApprovalFlowTest وSchoolUserRoleAssignmentApiTest وSubscriptionBillingAndSeatsTest. أمر Phase2: NativeAccountStateTest وNativeAuthenticationTest وNativeDeviceLifecycleTest وNativeWebCompatibilityTest. بعدها full phpunit واحد دون filter؛ التفاصيل الحرفية في run-baseline.sh المختبر.

البيئة: PHP8.2.35، Composer2.11.1، Laravel12.50.0، Sanctum4.3.0 من lock، PHPUnit11.5.51، Node22.23.4، MySQL8.0.46، utf8mb4/utf8mb4_0900_ai_ci. Vite manifest بُني واختُبر، Laravel boot وComposer/platform PASS. full:4095 assertions،90.456 ثانية،152.50MB، لا زيادة cgroup OOM kill.

فشل full الوحيد: `Tests\Feature\StudentExcelImportTest::test_valid_excel_import_creates_students_inside_current_school`، test line53، SQLSTATE42S22/MySQL1054، المسار المعروف StudentImportService مع Query Builder whereKey. لم يُعدّل المصدر أو الاختبار ولم تُضف filters/skips لإخفائه. عدد الفشل كما قبل المرحلة1، وزاد النجاح من614 إلى637. لذلك workflow conclusion=failure وexit1 متوقعان ومكشوفان؛ probes الثلاثة PASS وفق معيار PM، ولا ادعاء full أخضر بالكامل.

تحذيرات PHP كلها بنفس signature السابق، من `vendor/vlucas/phpdotenv/src/Store/File/Reader.php:73` نتيجة غياب .env عمداً داخل البيئة المعزولة. أرقام WARNINGS هي أحداث PHPUnit وليست عدد اختبارات مصابة. full يعرض كذلك PHPUnit Deprecations:1 السابقة؛ لا أخطاء أو skips جديدة. لم تُصدّر رسائل التحذيرات الخام.

لم تُضف migrations. أعاد التشغيل gate المخطط113/113 وMySQL charset/collation وإدخال factory/package discovery، وأعاد gate PAT القائمة rollback ثم reapply ثم repeat مع حفظ المستخدم غير المتعلق؛ PASS. هذه قاعدة مؤقتة لا staging ولا production. T005 PASS:13 رفضاً سلبياً قبلDB، HTTP stray blocked، mail/notification fakes، sync job دون worker، TCP TESTNET ممنوع، وconnection-lock مشترك مثبت باتصالين. إثبات races المستقبلية للworkers يظل خارج هذه المرحلة.

تاريخ التصحيح:

- f21a42b / run42495790708: focused55، pass53/fail1/error1. الخطأ اسم جدول history داخل الاختبار الجديد؛ الفشل عقد رد assignee للويب. رُفضت البوابة ولم تُسجل نجاحاً.
- c9aa675 / run42496193234: focused58، pass57/fail1/error0. metadata مقيدة أثبتت test line113 expected422/actual302. لم تُخرَج أجسام الطلبات أو ردودها.
- ff58fcd / run42496584067: استُعيد422 JSON لإسناد الموظف غير المسموح، وأضيف اختبار عداد لوحة المدير وسياقها الحالي؛ focused65/65 وP2/P3 PASS وfull لا فشل جديد. انتهى التصحيح دون تغيير اختبار الويب القائم TicketWorkflowAuthorizationTest.

مراجعة الأمان النهائية: نطاق الخادم قبل filter/count/page؛ native lookup داخل scope؛ web policy قبل load/write؛ parent school للطفل والتذكرة؛ scoped eligible assignee؛ rejectAuthority/allowlists؛ فصل view عن manage/assignment؛ guard داخل خدمة انتقال الحالة؛ عدم الاعتماد على UI أو role/token/header وحده. لا concern عزل/مهمة مفتوح ضمن Phase4 المثبتة. التفاصيل في [isolation.md](isolation.md) و[current-context.md](current-context.md) و[task-traceability.md](task-traceability.md).

CHK023/024/025/026/027/029/030/031/032 تُغلق لهذا النطاق بالأدلة السابقة. CHK028 يبقى مفتوحاً لتنزيل الملفات Phase5، مع إثبات non-download الحالي فقط. CHK061 يبقى مفتوحاً لإثبات large-data/query budgets وcontract النهائي Phase7 رغم إثبات25/100 والبحث/الفرز هنا. CHK077 يبقى مراجعة PM للمرحلتين4/5؛ CHK006/074 وإثبات إعدادات البيئات الحقيقية وبقية متطلبات المراحل اللاحقة لا تُغلق بهذا التشغيل.

فُحصت inventory تشغيلات كل SHA مدفوع: workflow الوحيد هو Isolated mobile backend baseline؛ لم يظهر deployment. main المحلي وorigin/main وremote main عند `12b9d5326d942acda55309aac05c6cee1bf928b0`. فرع الميزة local/remote متطابق، index فارغ؛ README وحذف public السابق والمجلدات غير المتتبعة محفوظة. الأدلة/Spec Kit محلية غير متتبعة ولم تدخل commits التطبيق.
