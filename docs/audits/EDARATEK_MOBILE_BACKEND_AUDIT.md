# EDARATEK_MOBILE_BACKEND_AUDIT

تاريخ المراجعة: 2026-11-07 — Africa/Cairo. النطاق: Phase 0، مراجعة محلية للقراءة فقط. الحكم التنفيذي: يلزم تجهيز API وأمان المصادقة والملفات قبل ربط تطبيق Flutter إنتاجي. توجد خدمات أعمال كثيرة قابلة لإعادة الاستخدام؛ لا حاجة إلى Backend منفصل.

هذا تقرير تدقيق ساكن مدعوم بالكود، وليس شهادة خلو من الثغرات أو نتيجة اختبار اختراق. تغطي الملاحق جميع تعريفات مسارات التطبيق المكتشفة، بما فيها JSON خارج `/api`. تشغيل المسارات والاختبارات، مخطط قاعدة البيانات الفعلي، إعدادات الخوادم وصلاحية بيانات الإنتاج: UNCONFIRMED. لم يتم الاتصال بـ staging أو production أو n8n. كُتبت وثيقة التدقيق وأداة استخراج أدلتها فقط خارج Git، ولم يتغير التطبيق أو Spec Kit.

## 1. REPOSITORY CONFIRMATION

| البند | النتيجة |
|---|---|
| Local path / pwd | `D:\edaratek\public_html` |
| Git root | `D:/edaratek/public_html` |
| Branch | `main` |
| HEAD | `12b9d5326d942acda55309aac05c6cee1bf928b0` |
| الحالة الأولية | DIRTY؛ تغييرات سابقة محفوظة، لا تغييرات تدقيق داخل المستودع |
| الهوية | Laravel + Vue/Inertia، Edaratek متعدد المدارس؛ لا يوجد repository mismatch |

آخر خمسة commits: `12b9d53` Meta verification؛ `294d923` staging env shell compatibility؛ `f9cf145` إيقاف تتبع env؛ `ee7a3a6` WhatsApp/n8n؛ `f4ace2a` ملف المشروع.

كل المجلدات المطلوبة موجودة: artisan، composer.json، package.json، app، bootstrap، config، database، public، resources، routes، storage، tests. الحالة السابقة تتضمن README معدلًا، حذوفات awstats وبعض أيقونات MIME، ومجلدات/ملفات غير متتبعة `.agents/`، `.specify/`، `.well-known/`، `docs/spec-kit.md`. لم يتم فحص المسار القديم المحظور.

## 2. TECHNOLOGY STACK

| التقنية | المتطلب / النسخة المقفلة | الدليل |
|---|---|---|
| PHP | `^8.2`؛ لا runtime متاح في PATH أو XAMPP المتوقع | composer.json |
| Laravel | `^12.0` / `v12.50.0` | composer.json، composer.lock |
| Sanctum | `^4.0` / `v4.3.0`؛ مثبت وليس mobile token implementation | composer.lock، User.php، routes |
| Inertia Laravel | `^2.0` / `v2.0.19` | composer.lock |
| Vue | `^3.5.28` / `3.5.28` | package-lock.json |
| Inertia Vue | `^2.3.13` / `2.3.13` | package-lock.json |
| Vite | `^7.0.7` / `7.3.1` | package-lock.json |
| Node | package.json بلا engines؛ Vite المثبت يتطلب `^20.19.0 || >=22.12.0` | node_modules/vite/package.json |
| Permissions | Spatie `6.24.1` + legacy role + delegation flags/groups | User.php، SchoolDelegationService |
| UI | Tailwind 3، lucide-vue-next، ApexCharts؛ React/JSX قديم موجود | vite.config.js، resources/js/app.js |
| Auth | Breeze dev dependency، session web guard، Laravel password broker | config/auth.php، routes/auth.php |
| Database | إعداد محلي وcached config موجهان إلى MySQL؛ phpunit إلى SQLite memory | config/database.php، phpunit.xml؛ لا اتصال DB |
| Notifications | جدول تطبيق مخصص، HTTP/Queue/HMAC لـ n8n؛ لا حزمة Firebase/SMS/Socialite مكتشفة | NotificationService، app/Jobs، composer.json |

لا تثبيت أو تحديث حزم في هذه المرحلة.

## 3. BACKEND ARCHITECTURE SUMMARY

`bootstrap/app.php` يحمّل web.php وconsole.php وhealth `/up`؛ web.php يضم auth.php. لا routes/api.php. تم اكتشاف 76 ملف Controller و43 ملفًا في app/Services. بيانات المدرسة تُحل غالبًا من user.school_id أو request attribute ينشئه middleware، والمشرف يعمل عبر علاقات الإشراف. طبقة العرض Vue/Inertia تعتمد بيانات props وredirect/flash، مع جزر JSON ضمن نفس web middleware.

العمارة المستهدفة سليمة: Flutter → HTTPS JSON → Laravel authentication/context/authorization → الخدمات الحالية → DB. يجب نقل قواعد داخل Controllers إلى خدمات مشتركة عند استخراج APIs، خصوصًا workflows المهام والحضور والتخطيط والتسجيل؛ وجود Service لا يعني أنه يحتوي كل قواعد الوحدة بالفعل.

## 4. FUNCTIONAL MODULE INVENTORY

الحالة هنا لوجود وظيفة Backend/Web، وليست حكم جاهزية Flutter.

| Module | Status | دليل وحدود |
|---|---|---|
| Auth/login/register/reset | IMPLEMENTED | Auth controllers؛ مسارات session |
| Profile/photo/account deletion | IMPLEMENTED | ProfileController؛ redirects |
| Users/managers/staff/roles | IMPLEMENTED | Admin/UserController، SchoolUserManagementController |
| Schools/location taxonomy/default templates | IMPLEMENTED | Admin/SchoolManagementController، SchoolDefaultDataController |
| Supervisors/school supervision | IMPLEMENTED | Onboarding، assignments، SchoolSupervisionRequestService |
| Teachers | PARTIALLY_IMPLEMENTED | staff EDUCATIONAL + teacher role، assignments، availability؛ لا teacher-only app flow |
| Students | IMPLEMENTED | SchoolStudent + StudentStructureController؛ ليس حساب طالب self-service |
| Parent accounts/portal | PARTIALLY_IMPLEMENTED | role seeded؛ لا ParentController/portal مكتشف |
| Student-parent authenticated relationship | NOT_FOUND | لا guardian/parent relation في SchoolStudent/User أو migrations المكتشفة |
| Associations/approval/onboarding | IMPLEMENTED | AssociationService، UserApprovalService، request policies |
| Plans/subscriptions/seats/pricing | IMPLEMENTED | SubscriptionService، SubscriptionPricingService |
| Payment gateway/receipts/reconciliation | NOT_FOUND | لا SDK/routes/controller دفع مكتشفة؛ التفعيل الإداري ليس إثبات دفع |
| Automated renewal | UNCONFIRMED | دورة زمنية ومدة موجودتان؛ لا تجديد دفع تلقائي مؤكد |
| Role dashboards | IMPLEMENTED | Admin/Manager/Supervisor/Staff DashboardControllers |
| In-app notifications | IMPLEMENTED | custom Notification table + polling |
| WhatsApp/OTP | IMPLEMENTED | كود Laravel موجود؛ إرسال حقيقي معطل، التشغيل الخارجي UNCONFIRMED |
| Push/SMS/WebSocket realtime | NOT_FOUND | لا device token registry/FCM/broadcast flow مكتشف |
| Settings/CMS/media/pages/header/footer | IMPLEMENTED | Admin controllers + PageViewer/shortcodes |
| Files/images/documents | IMPLEMENTED | public assets + private institutional attachments + legacy public ticket files |
| Reports/search/filter/export | IMPLEMENTED | ReportsController؛ JSON export ليس list API ثابتًا |
| Attendance/leaves/calendar/holidays | IMPLEMENTED | School controllers/services + leave/calendar JSON APIs |
| Academic years/terms/subjects/course offerings | IMPLEMENTED | AcademicPlanningController + validation services |
| Scheduling/version publishing/teacher availability | IMPLEMENTED | AcademicPlanningController، SchoolCalendarService |
| Exams/question bank/scores | IMPLEMENTED | SchoolExamController، ExamSchedulingValidationService |
| Tasks/tickets/subtasks/replies | IMPLEMENTED | Manager/Supervisor/Staff controllers، TicketingService |
| General chat / direct messaging | NOT_FOUND | الردود مرتبطة بمهام؛ ليست محادثات عامة |
| Certificates/templates/signatures/verification | IMPLEMENTED | Certificates services + public verification page |
| Audit/status history/default imports | IMPLEMENTED | AuditLogger، StatusHistoryService، provisioning services |

## 5. AUTHENTICATION READINESS

CHANGES_REQUIRED. `AuthenticatedSessionController::store` يستدعي LoginRequest ثم يجدد session ويعيد redirect حسب RoleRedirectService؛ destroy يلغي web session فقط. تسجيل المدير والمشرف يحدد initial approval state ويعيد صفحات/رسائل ويب. لا endpoints لإصدار bearer token أو `/me` أو device sessions أو إلغاء token مكتشفة. لا social authentication مكتشف.

LoginRequest: email/password، remember، خمس محاولات قبل lockout بمفتاح email+IP؛ inactive/pending/rejected يمنعون بعد فحص كلمة المرور. password broker: expiry 60 دقيقة وthrottle 60 ثانية. forgot-password يعيد اختلافًا بين sent وfailure؛ يلزم contract يمنع enumeration وحد مستقل للمحاولات. email verification routes موقعة ومحددة المعدل، لكن User لا يطبق MustVerifyEmail؛ لذلك وجود route وverified middleware لا يثبت فرض تحقق البريد. phone verification غير مفروض كشرط دخول عام.

## 6. SANCTUM MOBILE READINESS

**CHANGES_REQUIRED — SANCTUM_MOBILE_CHANGES_REQUIRED.**

الدليل: User يستخدم HasFactory/Notifiable/HasRoles دون HasApiTokens؛ لا createToken أو auth:sanctum ضمن app/routes؛ guard الوحيد web/session؛ لا config/sanctum.php محلي. vendor config يضع expiration=null. وجود جدول personal_access_tokens في قائمة purge لا يثبت توفير إصدار/إلغاء tokens أو حتى وجود الجدول فعليًا في قاعدة البيانات الحالية.

التوصية: personal access token مستقل لكل جهاز، routes versioned محمية بـ auth:sanctum مع فحص account approval/activity لكل طلب. تعريف انتهاء صلاحية وإلغاء current/all-device tokens وإدارة أجهزة. لا تفترض وجود refresh token تلقائي في Sanctum. يمكن بدء re-login عند expiry وتحديد rotation لاحقًا. يدعم Sanctum رسميًا mobile tokens؛ يلزم wiring وتنفيذ واختبارات المشروع: [Laravel Sanctum mobile authentication](https://laravel.com/docs/12.x/sanctum#mobile-application-authentication).

## 7. ROLE MATRIX

| Role | القدرات المثبتة | الحدود |
|---|---|---|
| super_admin | users/approvals، schools، global settings/templates، plans/subscriptions، system RBAC | school operational middleware لا يعطيه سياقًا تلقائيًا؛ ليس bypass عامًا لكل route |
| supervisor | onboarding، اختيار مدارس وفق القيود، supervision requests، تذاكر ينشئها، dashboard | ticket school.supervisor_id وownership policies |
| school_manager | مدرسة يملك إدارتها، مستخدمون/تفويض/هيكل، طلاب وحضور وإجازات وتقويم وتخطيط واختبارات وتقارير وشهادات | requires approval + managed school + مدرسة نشطة حيث active_school_association |
| staff | subtasks المسندة والرد/الإرسال؛ وحدات مدرسية حسب delegation أو legacy flags/department role | school active + module-specific gate؛ ADMINISTRATIVE/EDUCATIONAL ليسا system roles |
| teacher | دور assignable، تدريس/availability/question bank بشروط | المسار الفعلي غالبًا staff+teacher؛ teacher وحده لا يمنح staff.dashboard |
| student | seeded role فقط، fallback Dashboard | لا self-service APIs مثبتة؛ SchoolStudent ليس بالضرورة User |
| parent | seeded role فقط، fallback Dashboard | لا علاقات children أو APIs self-service مثبتة |
| custom roles | system role API يسمح بإدارتها؛ assignable non-system عبر مدير المدرسة | القائمة الحالية في DB UNCONFIRMED؛ لا إضافات ثابتة أخرى مؤكدة |

الدليل: database/seeders/RoleSeeder.php؛ User::hasSystemRole/primaryRole/canManage*؛ CheckRole؛ SchoolPermissionCatalog؛ RoleRedirectService؛ السياسات الأربع. الاعتماد على `primaryRole()` وحده في Flutter غير كافٍ: Spatie متعدد الأدوار وlegacy role يعملان معًا. effective capabilities يجب أن يحسبها الخادم.

## 8. APPROVAL FLOW

User::APPROVAL_REQUIRED_ROLES = school_manager، supervisor. الحالات: pending_approval، approved، rejected؛ is_active شرط مستقل. initialStateForRole يجعل طلبات هذين الدورين pending/inactive. super_admin عبر Admin/UserController وUserApprovalService يوافق/يرفض. الموافقة تضبط actor/time/note، تفعل أحدث pending subscription، وتربط/تنشط مدرسة المدير إذا تحقق الاشتراك الحالي؛ الرفض مسموح للـpending ويلغي pending subscription. خدمات الأعمال تحفظ أجزاء مهمة داخل transactions وتصدر إشعارات.

قبل الموافقة: تسجيل الدخول يرفض؛ existing web session يخرجها EnsureApprovedAccount ويعيد login. بعد الموافقة: تستمر متطلبات onboarding/ownership/school state؛ لا تعني الموافقة حق كل وحدة. APIs الحالية تحت web تحصل على هذا middleware، لكن رد الرفض redirect وليس JSON. mobile API الجديدة يجب أن تحافظ على القاعدة بنفسها وتمنع tokens القديمة بعد الرفض/التعطيل. `requiresSuperAdminApproval()` يعتمد primaryRole بترتيب role IDs؛ multi-role edge cases تحتاج P0 tests. دليل اختبارات: AccountApprovalFlowTest.

## 9. MULTI-TENANT ARCHITECTURE

User belongsTo School؛ School.manager_user_id وsupervisor_id؛ SchoolSupervisorAssignment يدعم school/directorate؛ معظم السجلات التشغيلية school_id. middleware يحل school_context_id بعد التحقق؛ controllers/services تقارن IDs وتستخدم scoped queries. لا global tenant scope مكتشف، فالعزل مسؤولية صريحة لكل query/action. `schools.school_id` حقل تعريفي تجاري مختلف عن PK `schools.id` المشار إليه من users.school_id: يجب تسميتهما بوضوح في contract.

SchoolAssociationState يفرق ACTIVE عن ACTIVE_ASSOCIATION. staff/teacher operational access يعتمد المدرسة النشطة، والمدير في EnsureActiveSchoolAssociation يمر على نشاط المدرسة وملكيته، ولا يشترط دائمًا اكتمال الارتباط المتبادل. هذه قواعد الكود الفعلية؛ لا تفرض Flutter handshakes أوسع من Laravel.

## 10. MULTI-TENANT SECURITY ASSESSMENT

العزل مدعوم بوضوح في RBAC/users/groups، leaves، calendar، reports، certificates والسياسات، لكنه غير مثبت كقاعدة شاملة ولا يجوز اعتماد جاهزية أمنية كاملة. أمثلة إيجابية: SchoolUserManagementController::ensureManagedSchoolUser يمنع مدرسة أخرى/system accounts/self-role edit؛ SchoolPermissionGroupController::ensureManagedGroup؛ StudentLeaveService::ensureLeaveBelongsToSchool؛ scoped exists في ListStudentLeavesRequest؛ checks في IntegrityImpactService؛ SchoolTenantIsolationTest.

الـschool_id القادم من العميل ليس ثغرة بمجرده: Supervisor/TicketController يتحقق من supervisor_id وmanager_user_id؛ Manager/OnboardingController::select يقفل المستخدم والمدرسة ويتحقق من المنطقة ومالك المدرسة ووجود مدير آخر. هذه endpoints لا يجوز تحويلها إلى سياق مدرسة موثوق بلا نفس الشروط. نقاط ضعف/حواف مؤكدة أو مشروطة في القسم 32: ملفات عامة، staff list بلا school filter، صلاحية تنزيل الإجازات، quick setup على مدرسة موقوفة. لم تتم محاولة استغلال أو فحص بيانات حقيقية.

## 11. COMPLETE API INVENTORY

تم استخراج **323 سجل مسار مصرح به** من web/auth، مع تجميع PUT/PATCH المتطابقين وتوسيع resource actions المفعلة؛ **40 route تحت /api**؛ **140 route مرشح API/JSON/integration** تشمل paths الداخلية وغير المسماة api. هذه أعداد static declarations وليست عدد runtime routes. `/up` من bootstrap ومسارات vendor مثل Sanctum csrf-cookie ليست ضمن العدد وتحتاج route:list لاحقًا.

Appendix A يفصل كل JSON/API/integration route: METHOD، PATH، NAME، controller/action، auth، middleware/role، tenant rule، request/FormRequest، response، codes، pagination، errors، mobile status، evidence. Appendix B يشمل كل مسارات الويب أيضًا؛ فلا تختفي routes التي ترد redirects بدل JSON. Appendix C يربط كل Inertia render بالـcontroller. استخراج المدخلات ساكن؛ قواعد helper/service والفروع المشروطة يجب تثبيتها باختبارات contract، ولا يُعامل الملحق كـOpenAPI نهائي.

تقسيم /api: system permissions/roles ثمانية؛ school users/delegation عشرة؛ leave types خمسة؛ calendar settings اثنان؛ holidays ستة؛ leaves/actions/attachments ثمانية؛ quick setup واحد. كلها تستخدم web session، وليست native bearer endpoints. endpoint عام قابل للاستهلاك من حيث النقل الآن: GET /plans JSON (role_type optional)؛ يحتاج تثبيت PlanResource قبل الاعتماد الإنتاجي. باقي محميّات JSON PARTIAL. webhooks ليست APIs للعميل مطلقًا. لا protected API مؤكد YES لموبايل bearer اليوم.

## 12. WEB / INERTIA VS API GAP MATRIX

| Feature | Web implemented | API implemented | Mobile ready | Backend required | Priority |
|---|---|---|---|---|---|
| Login/register/reset/profile | Yes | Web routes/redirects | NO | token auth، me، JSON registration/reset/profile | P0 |
| Approval/onboarding | Yes | بعض JSON onboarding/requests | PARTIAL | state contract، bearer middleware، deep links | P0/P1 |
| School users/delegation | Yes | /api/school JSON | PARTIAL | auth/resource/default pagination/search | P1 |
| System roles/permissions | Yes | /api/system JSON | PARTIAL | auth/contracts؛ mobile admin scope قرار PM | P2 |
| Student/class structure/import | Yes | web redirects/downloads | NO | scoped list/detail/CRUD/import contract | P1 |
| Attendance | Yes | web write/CSV/binary | NO | date/class lists، batch upsert result، uploads | P1 |
| Leaves/calendar/holidays | Yes | JSON + download | PARTIAL | auth، specific gate، pagination/error contract | P1 |
| Academic planning/timetables | Yes | web mutation/props | NO | resource APIs تعيد استخدام services | P1/P2 |
| Exams/question bank/scores | Yes | web mutation/props | NO | scoped APIs+teacher capabilities+score validation | P1/P2 |
| Reports/certificates | Yes | export JSON/binary جزئي | PARTIAL/NO | paginated read APIs+download contract | P2 |
| Dashboards | Yes | Inertia props فقط | NO | role summaries/analytics APIs | P1 |
| Tasks/tickets/subtasks | Yes | JSON خارج /api | PARTIAL | school list fix، private files، resources | P1 |
| Notifications | Yes | /notifications JSON | PARTIAL | paginated feed+unread count+mobile targets | P1 |
| WhatsApp OTP | Yes | session JSON | PARTIAL/disabled | token auth+phone identity/state+safe dispatch | P1 |
| Plans | Yes | public /plans | PARTIAL | stable resource/currency metadata | P1 |
| Subscription self-service/payment | Admin/web | لا mobile contract | NO | subscription summary/action rules؛ payment scope | P1/P2 |
| Parent/student portal | Role placeholders | No | NO | product scope+authorized relationships أولًا | blocked |
| CMS/global settings | Yes | admin/internal JSON جزئي | NO | keep web-admin initially، public mobile config allowlist | P2 |

## 13. MOBILE FEATURE MAP

| Web feature/page | Business source | Existing API | Missing mobile API | Proposed screen | Roles | Priority |
|---|---|---|---|---|---|---|
| Auth/*، Profile/Edit | LoginRequest/UserApproval/ProfileController | session routes | auth/me/profile/reset | دخول/حالة حساب/ملف | جميع المعتمدين | P0 |
| Manager/Onboarding | SubscriptionService+provisioning | manager/onboarding JSON | stable authenticated DTOs | إعداد المدرسة | manager | P1 |
| Supervisor/Onboarding، Requests | Supervision service | onboarding/requests JSON | token adapters/state | المدارس وطلبات الإشراف | supervisor/manager | P1 |
| Manager/SchoolStructure | Delegation/RoleAssignment/Pricing | /api/school/users/groups | departments/details/search | فريق المدرسة والصلاحيات | manager | P1 |
| School/StudentStructure | StudentImport+controller | web CRUD | students/classes/stages | الطلاب والفصول | manager/delegated staff | P1 |
| School/StudentAttendance | calendar/leaves+controller | web writes | read/batch API | الحضور اليومي | authorized staff | P1 |
| School/StudentLeaves | StudentLeaveService | /api/school/leaves | authenticated adapters | الإجازات | authorized staff | P1 |
| School/AcademicPlanning | Calendar/AcademicValidation | quick setup فقط | years/terms/subjects/offerings/schedules | الجدول والتخطيط | manager/delegated | P2 |
| School/Exams | ExamValidation+controller | web writes | exams/questions/scores | الاختبارات والدرجات | manager/authorized teachers | P2 |
| Role dashboards | AnalyticsService+role controllers | No standalone | summaries | الرئيسية | admin/supervisor/manager/staff | P1 |
| Task components within dashboards | TicketingService+policies | tickets/subtasks JSON | normalized DTO/file APIs | المهام وتفاصيلها | supervisor/manager/staff | P1 |
| School/Reports | ReportsController/Export service | export formats | paginated datasets | التقارير | delegated capabilities | P2 |
| School/Certificates/Index | Certificate services | web/download/verify HTML | list/issue/template APIs | الشهادات | delegated capabilities | P2 |
| Notification component | NotificationService | list/read/read-all | count/devices/deep-link codes | الإشعارات | authenticated | P1 |
| Pricing/Index، Admin/Plans | SubscriptionPricing/Subscription | /plans | current subscription/seats | الاشتراك | manager/supervisor | P1/P2 |

الأسماء تصور وظيفي فقط؛ لا تصميم واجهات نهائي ولا إنشاء screens/folders. التطبيق لا يعيد حساب KPI أو المقاعد أو تقويم العمل أو تعارض الجداول أو انتقالات الحالات.

## 14. EXISTING BUSINESS SERVICES

Appendix D يسرد **كل 43 ملف خدمة** مع public functions وcaller files ووجود DB::transaction. الجدول التالي يحدد مصادر الحقيقة الأهم:

| Service | Purpose/rules | Main callers | Transaction/auth assumptions | Mobile reuse |
|---|---|---|---|---|
| SubscriptionService | creation/activation/freeze/cancel/delete/context، pricing snapshots | registrations، approval، admin subscriptions | activation transaction؛ أخرى تعتمد caller؛ actor ID ليس authorization | mandatory |
| SubscriptionPricingService | monthly/yearly seats/addons/proration/currency amounts | users/onboarding/register/dashboard | reserveSeats transaction+subscription locks؛ manager/school مفترضان | mandatory |
| UserApprovalService | initial state+approve/reject+subscription/school sync | registration/Admin User | approve/reject transactions؛ caller يضمن super_admin | mandatory |
| SchoolRoleAssignmentService | assignable role catalog+normalization | school user APIs | no transaction boundary؛ flags/system-role restrictions؛ GET may repair staff role | mandatory |
| SchoolDelegationService | direct/group permissions+type normalization+audit | school users/groups | server school ID وauthorized manager من caller | mandatory |
| AssociationService | manager-school association+legacy compatibility | onboarding/association controller | approve transaction/locks؛ policy in caller | mandatory |
| SchoolSupervisionRequestService | supervisor request→manager approve→supervisor confirm/cancel | onboarding/request controllers | transactions/locks في transitions؛ caller policies required | mandatory |
| SchoolCalendarService | settings، weekly off، holidays، academic bounds | calendar/leaves/planning/exams | بعض writes transactions؛ authoritative school ID required | mandatory |
| StudentLeaveService | overlap، attachment policy، retrospective absence conversion/cancel | leave API، attendance | transactions for mutations؛ action gate in FormRequest/controller | mandatory |
| AcademicPlanningValidationService | central date/assignment/schedule constraints | AcademicPlanningController | validation service؛ does not replace authorization | mandatory |
| ExamSchedulingValidationService | exam times/academic constraints | SchoolExamController | validation؛ caller scopes teacher/school | mandatory |
| SchoolDashboardAnalyticsService | aggregate students/attendance/leaves/exams/staff/subscription KPI | Manager Dashboard | read aggregate؛ authorized School supplied by caller | mandatory |
| TicketingService | status history+notifications | task controllers | not complete workflow/auth layer؛ validation still in controllers | reuse + extract remaining rules |
| AttachmentService | private institutional storage/allowlist/audit/cleanup/download DTO | school user/planning/structure/etc. | does not grant authorization; download caller checks scope/module | mandatory |
| Certificates services | templates/issue/render/verification | StudentCertificateController/public verification | issuing transaction+school relationship resolution؛ caller capabilities | mandatory |
| IntegrityImpactService | affected counts، BLOCK/WARNING/confirmation codes | leave/calendar/structure | read checks+school comparisons | mandatory |
| NotificationService | custom database messages+WhatsApp enqueue | business services | caller transaction context؛ catches outbound failure | mandatory |
| WhatsAppOtp/DispatchClaim/N8n client | OTP/security/idempotency | verification/jobs/webhooks | hashed OTP+transaction/lock؛ HMAC; see kill-switch finding | backend only |
| Provisioning/default import/calendar presets | school bootstrapping/reference data | onboarding/default-data/admin | mutation can occur on read helpers؛ inspect boundary before reuse | backend only |
| StudentImport/ExportDocument/StatusHistory/AuditLogger | imports/rendered exports/history/audit | module controllers/services | scoped caller/auth assumptions; no direct Flutter rules | reuse |

لا يُعد تمرير actorId أو schoolId لخدمة إثبات تفويض. APIs المستقبلية تستدعي policies/FormRequests/context قبل service.

## 15. API CONTRACT CONSISTENCY

غير موحد. school/system APIs كثيرًا ما تعيد `{data}`، optional `{pagination}`؛ tickets/subtasks تعيد raw model/array عند عدم طلب per_page، ثم `{data,pagination}` عند طلبه. notifications raw array/model وmarked_count؛ OTP message/timers/verified؛ onboarding school أو collections؛ export قد يكون JSON download؛ web forms redirect/flash؛ calendar impact يعيد 409 مع message_code/impact. إذن Flutter parser واحد لكل endpoints الحالية لن يكون موثوقًا.

المفاتيح snake_case غالبًا، dashboard يتضمن camelCase مثل generatedAt/filterOptions، timestamps أحيانًا ISO وأحيانًا formatted local-naive. raw models قد يغيرون shape عند تحميل علاقات. nullable/decimal/int/boolean يجب تعريفها صراحة. Laravel User يخفي password وremember_token فقط؛ array serialization ليس allowlist للعرض العام.

## 16. API VERSIONING ASSESSMENT

لا `/api/v1` في routes المكتشفة. أوصي بإضافة namespace مستقل `/api/v1` في Phase 1، دون تغيير routes Vue. endpoints mobile تكون adapters رفيعة فوق shared services/policies، لا نسخ لقواعد الأعمال. لا تحرك webhooks أو public certificate links أو integrations إلى v1 تلقائيًا. نسخة API مرتبطة بعقود mobile لا نسخة التطبيق. احتفظ بفترة توافق وسياسة deprecation واختبارات regression للويب؛ لا تحتاج نسخ route لكل الشاشة إذا service/DTO مشترك يكفي.

## 17. API RESOURCE ASSESSMENT

لا app/Http/Resources مكتشف. توجد serializers خاصة في RBAC/leave/calendar، وraw Eloquent في notifications/plans/tickets/onboarding. أدخل لاحقًا UserSessionResource، SchoolResource، SchoolUserResource، Student/Classroom، Ticket/Subtask/Message، Notification، Plan/Subscription، Leave/Calendar، Dashboard DTOs. استخدم allowlists وconditional relationships دون exposing approval_notes/internal storage paths/OTP hash/dispatch tokens. لا دليل على إرجاع OTP model أو bearer secrets حاليًا؛ خطر overexposure من توسيع raw models مستقبلًا، وليس تسريبًا مؤكدًا لكل الحقول.

## 18. VALIDATION ASSESSMENT

FormRequests جيدة في system/school RBAC، leaves/calendar، certificates وبعض onboarding؛ inline validation في tickets/profile/planning والعديد من admin controllers؛ manual payload parsing في webhooks. scoped exists مستخدم بكثرة. uploads تختلف حقولها: `photo`، `logo`، `attachment`، `attachments[]`، `file`؛ limits ليست واحدة. SaudiMobile وnormalizers يجب إعادة استخدامهما؛ WhatsApp normalizer يدعم Saudi/Egypt صراحة ويختار mobile قبل phone. لا توصية بتوسيع البلدان بلا متطلب.

ثغرات consistency: leave upload/ticket reply لا يستخدمان كل hardening الخاص بـUploadSecurity؛ date filters لا تعرض دائمًا ترتيب from<=to؛ validation/business states مختلطة بين422/409؛ store/update school user يطبّقان normalization بعد FormRequest validation؛ يجب اختبار كل صيغ الأرقام بدل افتراض ترتيب التحويل. nested school IDs وteacher/course/subject IDs تحتاج P0/P1 tests على bearer endpoints الجديدة. Appendix A يربط كل FormRequest/input evidence.

## 19. DATABASE / MODEL MOBILE CONCERNS

PK غالبًا Eloquent numeric id؛ notifications جدول مخصص numeric/user_id وليس Laravel UUID DatabaseNotification contract. School business school_id مختلف عن PK؛ user_id، school_id، manager_user_id، supervisor_id وdepartment/role/class/student keys يجب ألا تُفسر كلها كهوية مستخدم. SchoolStudent لا يربط حساب طالب/ولي أمر مؤكدًا. Spatie role pivots + legacy role + permission groups تشكل نموذج صلاحيات مركبًا.

User fillable يشمل role/is_active/approval/school/permission flags: requests يجب أن تبني allowlist منفصلًا؛ ProfileController يحدد حقوله بالفعل. معظم domain models guarded=[]؛ ليس استغلالًا بذاته، لكنه يجعل mass assignment من API عام خطرًا. لا tenant global scope؛ لا تفترض automatic binding scope. SoftDeletes موجود في Subscription/Attachment/CertificateTemplate/SchoolCertificateSignature/StudentCertificate؛ ليست قاعدة عامة لكل models.

casts: booleans في User/Student/LeaveType، timestamps عبر Carbon، Subscription decimal:2 يُنتج strings؛ لا تحول مبالغ العملة إلى floating-point حسابي في Flutter. حالات domain constants تتنوع uppercase وlowercase. OTP model لا hidden للـhash؛ ليس معروضًا حاليًا ويجب ألا يصبح resource عامًا. foreign keys/uniques/indexes موجودة في migrations، بما فيها WhatsApp idempotency/claim token unique؛ تطبيق migrations الفعلي UNCONFIRMED دون اتصال DB.

## 20. FILE / MEDIA READINESS

**MOBILE_UPLOAD_CHANGES_REQUIRED.** المؤسسي: school_attachments داخل storage/app/private، checks school+attachable+module في SchoolAttachmentController، 10 ملفات ×10MB، MIME allowlist وامتدادات خطرة محظورة في AttachmentService. profile: jpg/jpeg/png، 1MB؛ school logo صورة 2MB وفق FormRequest؛ leave attachment ملف 10MB على local private. certificate rendering يستخدم school_attachments.

استثناء مهم: Staff/SubtaskController::reply يخزن ticketing/attachments على public؛ PublicStorageController يسمح قراءة أي public path معلوم بلا session، cache أسبوع. leave download لديه fallback لملفات public قديمة؛ وجود ملفات قديمة فعلًا UNCONFIRMED، لا تفحص محتويات الطلاب. PublicStorageController يمنع traversal segments لكنه لا يجعل ملفات public خاصة. يجب ترحيل الملفات الحساسة قبل فتح mobile uploads.

يمكن Laravel استقبال multipart، لكن bearer auth وJSON validation وprivate download streaming لم تُجهز بعد. على Flutter إرسال POST multipart مع `_method=PUT/PATCH` حيث يلزم للتوافق واختباره، وعدم افتراض parsing multipart PUT على كل بيئة. فصل public logos من sensitive attachments؛ authenticated downloads، MIME/content-disposition، temp cleanup، عدم disk-cache الخاص؛ storage symlink/runtime URL host تبقى مراجعة بيئة لاحقة.

## 21. NOTIFICATION READINESS

custom app/Models/Notification مرتبط user_id، type/title/body/data/read_at. NotificationController: list limit default100/max200 بلا pages، mark read مع owner check، mark all scoped. لا count endpoint؛ RealtimeNotifications.vue يحسب unread من القائمة المحملة ويعمل polling كل8 ثوانٍ؛ الاسم Realtime لا يعني WebSocket أو push. body/data يحملان web route_name/params/action_route_name.

المطلوب للموبايل: paginated feed، true unread count، stable entity/action codes قابلة للتحويل إلى routes، lifecycle polling فقط أثناء foreground إذا لزم، وعدم الثقة notification deep link للتفويض. email reset/verification موجود كقدرة Laravel؛ business notification email channel غير مؤكد، SMS/FCM/broadcast غير مكتشفة.

## 22. WHATSAPP INTEGRATION ASSESSMENT

محليًا `.env` وbootstrap/cache/config.php كلاهما WhatsApp enabled=false. لا تعديل لهذه القيمة ولا تفعيل workflow ولا إرسال. الكود: Laravel custom notification → WhatsAppNotificationService → afterCommit queued job → signed N8nWhatsAppClient → n8n/Meta خارج نطاق الاتصال. OTP job implements ShouldBeEncrypted ويحمل code runtime، والـDB يحفظ hash.

حماية مثبتة: HMAC SHA256 على timestamp.raw-body، constant-time comparison، رفض timestamp مستقبل/أقدم300s، Cache::add لمنع replay، separate replay prefixes، atomic dispatch claim+unique idempotency، claim-token result matching، uncertain state يمنع auto resend بعد ambiguity، monotonic provider status. webhooks لا تتطلب user auth عمدًا؛ تتطلب signature، Meta GET يطابق verification token ويعيد plain challenge. الكاش المشترك بين instances وn8n workflow الفعلي UNCONFIRMED.

ملاحظة high: N8nWhatsAppClient::dispatch لا يتحقق enabled، فقط URL/secret؛ Jobs تستدعيه مباشرة. تعطيل flag يمنع إنشاء طلبات جديدة عبر configured لكنه لا يضمن منع إرسال job موجود مسبقًا. لا شُغلت queue أثناء التدقيق. يجب معالجة kill-switch في Phase 1 قبل أي تجارب OTP/notification. Flutter لا يحتاج URL/token/HMAC/claim token الخاصة بهذا التكامل؛ يتعامل فقط مع Laravel state.

## 23. PHONE VERIFICATION / OTP READINESS

session JSON: POST `/phone/verification/whatsapp/send` و`/verify`؛ resend نفس send؛ verify يقبل code فقط ولا يثق phone من العميل. send يختار user.mobile ثم user.phone ويطبّع إلى digits international. ست خانات random_int، expiry5min، cooldown60s، خمس sends/hour بمفتاح user+phone+IP، عشر verify requests/5min بنفس النوع من المفتاح وخمس أخطاء لكل OTP. resend يستهلك السابق؛ verify transaction+lock يستهلك الرمز ويضبط phone_verified_at؛ تغيير mobile/phone يمسح التحقق في User::updating.

ليس جاهزًا للاستخدام المباشر في Flutter: لا bearer endpoints، لا verification status resource، الخدمة disabled، queue success ليس delivery confirmed، controller يقول «تم الإرسال» قبل نتيجة provider. phone وmobile مصدران متعارضان: تعديل profile.phone قد لا يغير الرقم الذي يرسل إليه OTP إذا mobile موجود. المفاتيح per-IP لا تساوي limit عالميًا للحساب؛ send concurrency/cooldown تحتاج tests. يجب إرجاع queued/unavailable/retry_after/canonical verified number دون كشف أكواد أو provider internals. الأدلة والاختبارات في WhatsAppOtpService وWhatsAppIntegrationTest؛ لا OTP فعلي أرسل.

## 24. SUBSCRIPTION MOBILE READINESS

Plan يدعم role type، monthly/yearly، included users، extra seat pricing؛ Subscription حالات PENDING/ACTIVE/FROZEN/CANCELED/EXPIRED وtimestamps وpricing snapshot وschool/user ownership. SubscriptionPricingService يحسب seats وproration ويقفل الاشتراك عند الحجز. UserApprovalService قد يفعل pending subscription، وadmin يستطيع activate/freeze/cancel/delete بقيود. هذا نظام اشتراك إداري، لا gateway payment proof أو store billing.

Mobile يحتاج current entitlement+plan+currency/billing cycle+seat summary+status/expiry من Laravel. لا يقرر صلاحيات الاشتراك أو يحسب رسوم المقاعد محليًا. self-service renew/pay وApple/Google digital purchase handling قرارات scope لاحقة؛ لا تغيير billing مفترض في هذا التدقيق. existing tests SubscriptionBillingAndSeatsTest/HandshakeFlow موجودة دون تشغيل.

## 25. DASHBOARD MOBILE READINESS

SchoolDashboardAnalyticsService::build يحسب students/attendance/leaves/exams/teachers/schedules/subscription/staff/alerts/KPIs/charts مع filters؛ Manager/DashboardController يضيف staff≤200 وassociation requests≤50 ثم Inertia. admin/supervisor/staff dashboards أيضًا web props؛ لا standalone JSON dashboard مثبت.

المطلوب: `/api/v1/dashboard` أو endpoints role-specific تحل الدور والسياق بالخادم؛ إعادة استخدام build وأي aggregates حالية، narrow DTOs، reference filter endpoints، timestamps ISO، error states/permissions. لا تحميل جميع الطلاب/الحضور لبناء graph داخل Flutter. أزمنة الاستعلام الفعلية UNCONFIRMED.

## 26. SEARCH / FILTER / SORT READINESS

leaves: student/type/status/source/stage/class/grade/date range وper_page؛ holidays: from/to/is_active؛ school users فقط per_page في index، لا search/sort contract مؤكد؛ tickets/subtasks per_page وlatest id؛ /plans role_type؛ reports تحتوي server search/filter/capped options، لكنها Inertia/export. لا global mobile search. لا تسمح arbitrary SQL sort، واعتمد whitelist per endpoint وschool-scoped lookup. تجنب تنزيل قوائم ضخمة ثم فلترتها في Flutter.

## 27. PAGINATION ASSESSMENT

page-based Laravel paginator يستخدم في عدة JSON lists فقط عند per_page>0، max100؛ من دونها get() كامل، ويتغير envelope. notifications limit-only bounded200 بلا استمرار؛ holiday/role/group/reference lists غير paginated؛ بعض web reports paginated وبعض exports تجمع datasets. لا cursor API مثبت.

التوصية: v1 default25/max100 و`data + meta(current_page,per_page,last_page,total) + links` حيث تحتاج totals، مع mapping من pagination الحالية بدل تغيير Vue. notifications يمكن cursor مستقرة newest id إذا اعتمدت لاحقًا؛ لا حاجة لخلط cursor في كل الوحدة. reference catalogs الصغيرة bounded بوضوح. اختبر missing/invalid/excessive per_page وempty/last page.

## 28. LOCALIZATION / RTL ASSESSMENT

AppServiceProvider يفرض ar وCarbon/Date ar؛ lang/ar وUserFacingMessageTranslator يترجمان validation/errors، مع نصوص عربية/إنجليزية hardcoded في controllers/services. domain statuses machine constants موجودة؛ labels عربية في serializers/analytics. بعض terminal reads القديمة تعطي mojibake عند encoding خاطئ؛ لا إثبات شامل لتلف الملفات نفسها.

Laravel يرسل machine `code/status` مستقرًا مع localized message مفيد، وerrors keyed field names. Flutter يملك ARB/localization/RTL وnavigation labels، ولا يتخذ قرارًا من تطابق نص عربي. النصوص المؤلفة بواسطة المستخدم/notification body تبقى content؛ contract Accept-Language يجب تحديده لاحقًا لأن force-ar الحالي يتغلب على التفضيل.

## 29. DATE / TIME ASSESSMENT

config/app.php timezone UTC، ونفسه cached محليًا. ORM timestamps كثيرًا ما تستخدم toISOString، لكن Dashboard generatedAt `Y-m-d H:i` بلا timezone؛ due_date cast `date:Y-m-d`، الإجازات/العطل date-only، الجداول time fields. timezone المدرسة غير مؤكد كحقل/عقد مستقل.

v1: timestamps UTC ISO8601 مع Z/offset؛ school dates تبقى YYYY-MM-DD ولا تُحوّل UTC؛ clock times HH:mm ومع school timezone صريح عند بناء occurrence. Flutter يعرض التاريخ وفق locale والسياق، ولا يعيد حساب academic working days. اختبر midnight/DST/term boundaries وexpiry؛ لا تفترض أن timezone جهاز المستخدم هو المدرسة.

## 30. ERROR CONTRACT ASSESSMENT

الحالي: JSON validation `{message,errors}` في bootstrap؛ HttpException `{message}` مترجم؛ بعض middleware redirects؛ action-specific409 impact/message_code؛ OTP limits كثيرًا422؛ auth login lockout validation422؛ CSRF419 بسبب web؛ لا schema موحد لunexpected errors. لا دليل على stable machine code في كل خطأ.

الموصى به v1: success `{data,message?}` + meta/links للقوائم، error `{message,code,errors?,request_id?}` مع الحفاظ على semantics. 401→مسح session محلي وlogin مرة واحدة؛ 403→permission/approval/school state حسب code؛404→not found؛409→state conflict/impact confirmation لا retry أعمى؛422→field errors؛429→Retry-After؛500→generic message+trace ID ولا stack. network/offline/timeout ليس HTTP500؛ Dio يميزه محليًا. retry للقراءات الآمنة فقط وبحدود؛ mutations تحتاج idempotency/explicit confirmation. لا تجدد token وهميًا عند كل401.

## 31. PERFORMANCE FINDINGS

P1: optional pagination تتيح unbounded tickets/subtasks/leaves/users؛ staff list eager-loads كل messages/attachments لكل subtask حتى مع pagination، فتظل items ثقيلة. P1: reports exports تجمع datasets؛ API list ليس export path. P2: role/group/permission catalogs غير bounded، User capability methods قد تستدعي department/permission queries عدة مرات، وserializers قد lazy-load attachments بعد syncRoles؛ query budget مطلوب. P2: DashboardAnalyticsService يحسب عدة modules كل request وQuickSetup يجري counts كثيرة؛ لا قياسات latency، ولا دليل N+1 مؤكد لكل شاشة.

توجد إيجابيات: eager loading، aggregates، limit200/50 في manager dashboard، shared UI caching flags، attendance indexes واختبارات heavy pagination. المطلوب measurement محلي مع fixtures مصطنعة، EXPLAIN وpayload budgets لاحقًا؛ لم يجر load test أو اتصال DB.

## 32. SECURITY FINDINGS

### CRITICAL

لا ثغرة CRITICAL مؤكدة بالمسار التنفيذي في هذه المراجعة. هذا ليس ضمانًا؛ بيانات/خوادم الاختبار لم تُستخدم.

### HIGH

| ID | Finding / code evidence | شرط الخطر والأثر | المطلوب لاحقًا |
|---|---|---|---|
| H01 | Staff/SubtaskController.php:87 يحفظ ticket attachments على public؛ PublicStorageController::show بلا auth؛ Attachment::getLegacyUrl يعيد public URL | أي حامل URL يستطيع قراءة المرفق متجاوزًا صلاحيات المدرسة؛ وجود الملفات الحقيقية غير مفحوص | private storage+authorized streaming+legacy migration+deny public paths |
| H02 | Staff/SubtaskController.php:19-51 index يرشح assigned_to فقط؛ SubtaskPolicy::view يشترط school match | بعد نقل موظف لمدرسة جديدة مع احتفاظ tasks القديمة، list قد يعيد بيانات المدرسة السابقة بينما show يمنعها | school filter+relationship scopes+transfer regression test؛ finding مشروط بسجلات تاريخية، لم يُستغل |
| H03 | database/seeders/SuperAdminSeeder.php:30-46 يحتوي بيانات اعتماد admin ثابتة ويستخدم updateOrCreate ثم syncRoles(super_admin) | تشغيل seeder قد ينشئ/يعيد تعيين privileged credentials المعروفة من المصدر؛ لا تأكيد أنها مستخدمة live | إخراج credentials من المصدر، عدم reset existing admin، rotation مراجعة منفصلة؛ لم تطبع القيم |
| H04 | N8nWhatsAppClient.php:16 dispatch لا يفحص enabled؛ jobs تستدعيه دون configured | queued job سابق مع URL/secret صالحين قد يرسل رغم flag=false | kill-switch قبل كل outbound HTTP+job test؛ لم يشغل أي job |
| H05 | local .env وbootstrap/cache/config.php: APP_ENV=production مع debug=true | إذا استُخدمت هذه الإعدادات على خادم متاح، أخطاء قد تكشف تفاصيل حساسة؛ حالة live UNCONFIRMED | فصل env/cache والتحقق من debug=false في release gate؛ لا تعديل الآن |

### MEDIUM

| ID | Finding / evidence | الأثر والتوصية |
|---|---|---|
| M01 | StudentLeaveManagementController.php:364 downloadAttachment بلا `manage-student-leaves`؛ EnsureStudentLeaveAccess يسمح OR calendar/holidays/leave-types | مستخدم مخول بالتقويم فقط يستطيع بلوغ تنزيل مرفق إجازة في نفس المدرسة إذا عرف IDs؛ add specific gate+negative test |
| M02 | routes/web.php:412 quick-setup لا active_school_association؛ EnsureAcademicPlanningAccess.php:34-41 manager branch يعود قبل school active check | مدير مدرسة suspended يمكن أن يقرأ setup counts/status؛ inconsistency مؤكدة static، add school-state check بحسب قرار الأعمال |
| M03 | EnsureApprovedAccount يعيد302 ويخرج web guard؛ لا mobile active/approval middleware | v1 يحتاج فحصًا مستقلاً كل request وإلغاء tokens؛ middleware الحالي لا يكفي بمجرد auth:sanctum |
| M04 | UserApproval يعتمد primaryRole مع mixed legacy/multiple roles؛ RoleRedirectService يستخدم precedence مختلفًا | قرارات role/approval قد تختلف في حالات مركبة؛ ليست privilege escalation مثبتة؛ P0 multi-role tests |
| M05 | User fillable privileged fields + domain guarded=[] + raw model JSON | contract fragile/overexposure/mass-assignment risk عند API extraction؛ لا full-request exploit مؤكد؛ explicit DTO/allowlists |
| M06 | forgot-password يفرق sent/not-found؛ no explicit route throttle عليه | email enumeration/abuse potential؛ broker throttle وحده لا يساوي route/IP limit؛ generic result+rate tests |
| M07 | legacy leave public fallback، upload safety checks مختلفة في ticket/leave عن UploadSecurity | private access/unsafe content policies غير موحدة؛ inventory/migrate legacy، enforce production-safe validation |

### LOW

L01: read endpoints لها write effects: assignable role catalog يصلح staff role baseline؛ leave-types ينشئ defaults؛ calendar settings getOrCreate. لا تستخدم GETs في staging على أنها read-only بلا مراجعة. L02: unread count محلي لقائمة bounded وليس إجماليًا؛ notification route data web-specific. L03: pagination/type/date/status inconsistencies وعدم request ID موحد تعوق contract stability. L04: OTP send throttling keyed by IP ولا يضمن account-global budget؛ send concurrency غير محمي بقفل المستخدم في الكود المفحوص.

Secret review: .env يحمل application key، DB/mail passwords، HMAC/verification secrets؛ cached config قد يتضمنها أيضًا. القيم لم تُطبع، والملفان غير متتبعين حاليًا وفق git ls-files. هذا وجود أسرار في بيئة محلية وليس وحده تسريبًا. أما fixed admin credentials في SuperAdminSeeder فموجودة في source وهي finding منفصلة. التاريخ السابق أو الأرشيف الخارجي لم يُفحص للأسرار. لا log token/OTP leak مؤكد؛ يلزم redaction policy لـv1 ورفض Authorization/OTP في logging.

CORS: لا config/cors.php مخصص؛ framework default يسمح origins/methods/headers * مع supports_credentials=false ومسارات api/* وsanctum/csrf-cookie، والcached config يحتوي CORS. لا إثبات cookie cross-origin exposure. native Flutter لا يحتاج توسيع CORS أو تعطيل CSRF للويب؛ إن جاء Flutter Web لاحقًا فله مراجعة منفصلة.

## 33. EXISTING TESTS

Static discovery: **97 ملف Test.php، 508 دالة test_**. العدد ليس expanded PHPUnit cases؛ data providers تزيد الحالات. Executed=0، passed=0، failed=0، skipped-by-runner=0؛ **NOT_RUN** لكل المجموعة، وليس «نجحت» أو «تخطاها PHPUnit».

السبب: php غير موجود في PATH ولا C:\xampp\php\php.exe، ولا runtime في مواقع البرامج المفحوصة، وWSL غير مثبت، ولا Docker command متاح. لم تُثبت أدوات امتثالًا للطلب. لم يشغل artisan route:list أو tests أو migrations. توجد cached config محلية production/MySQL، لذلك لا يجوز تشغيل RefreshDatabase قبل تثبيت عزل الاختبار وإبطال الاعتماد على production cache في عملية الاختبار. AppServiceProvider ينفذ PublicStorageLinkMaintainer::ensure خارج unit tests وقد يعدل links/files حتى عند boot: سبب إضافي لإجراء static route review بدل bootstrap تجريبي غير مدروس.

Appendix E يسرد test files وعدد methods. phpunit.xml يحدد testing/SQLite :memory:/array cache/session وqueue sync/mail array، لكن ذلك ليس إثبات runtime isolation مع cached config. لم يُقرأ أي سجل نتائج سابق على أنه نتيجة جديدة.

## 34. TEST COVERAGE BY AREA

كل «موجود» أدناه يعني source coverage مكتشفًا، لا نجاح اختبار اليوم.

| Area | Existing evidence | Gap |
|---|---|---|
| Auth/reset/profile | tests/Feature/Auth/*، ProfileTest | bearer/device lifecycle غير موجود |
| Approval | AccountApprovalFlowTest | token already issued+role combinations |
| Roles/delegation | SystemRoleManagementApiTest، SchoolUserRoleAssignmentApiTest | v1 guard/abilities contract |
| Tenant isolation | SchoolTenantIsolationTest، access tests، ownership middleware tests | staff transfer/list، all new endpoints matrix |
| Associations/supervision | AssociationHandshakeTest، SubscriptionHandshakeFlowTest، SupervisorAssignmentConstraintsTest | stable mobile state/error schema |
| Subscription/seats | SubscriptionBillingAndSeatsTest | authenticated self-service contracts |
| API/pagination/rate | ApiRateLimitingTest، HeavyEndpointsPaginationTest | v1 snapshots/types/status/error handling |
| Uploads | SchoolAttachmentManagementTest، UploadValidationFeatureFlagTest، MediaStorageUrlFallbackTest | ticket private files/legacy URL denial/multipart bearer |
| Notifications | RealtimeNotificationFlowTest | unread count/pagination/device tokens |
| WhatsApp/OTP | WhatsAppIntegrationTest: HMAC/replay/claim/uncertain/attempts/phone change | flag=false existing jobs، send race/phone identity، shared cache deployment |
| Attendance/planning/exams | many focused access/validation/scheduling tests | native API adapters and round-trip DTOs |
| Localization/security | ArabicValidationMessagesTest، SecurityHeadersTest | request_id/machine codes/Accept-Language |

## 35. MISSING TESTS

**P0:** token issue rejects pending/rejected/inactive؛ token expiry/revoke current/all devices؛ compromised/disabled account token؛ cross-school list/show/create/update/delete/attachment for every v1 route؛ staff transfer list؛ foreign relationship IDs؛ manager impersonation/school injection؛ non-admin role escalation； multi-role approval semantics؛ public URL rejection for sensitive files؛ fixed-admin seeder safety؛ queued WhatsApp kill-switch with HTTP fake and no real sends.

**P1:** capability-specific leave download؛ suspended school quick-setup؛ exact success/error schema and types؛ 401/403/404/409/422/429/500؛ pagination defaults/caps؛ multipart upload+MIME+size+private download؛ OTP unavailable/cooldown/global budgets/concurrency/phone replacement؛ notifications ownership/count/read-all/deep links؛ subscription entitlement/proration round trip؛ dashboard aggregate parity.

**P2:** large fixtures/query budgets/N+1 checks؛ Arabic/date-only/timezone/DST boundaries؛ background/foreground polling؛ additive contract compatibility؛ exports/certificates rendering؛ push token reassignment/logout cleanup when implemented؛ accessibility and mobile retry/cancel behavior. لا يُكتب test جديد في Phase 0.

## 36. BACKEND WORK REQUIRED BEFORE FLUTTER

قبل أي اعتماد mobile على بيانات حقيقية: عزل dev/test config، local PHP test runtime، إغلاق findings H01-H05/P0، تحديد role/approval/tenant semantics، bearer auth+revocation+me، v1 JSON/error/resources contract، explicit server school context، minimal paginated reference/data endpoints، private file access، read-only dashboards. تثبيت scope للمدير/المشرف/الموظف أولًا وعدم التعهد ببوابة ولي أمر لا يملكها Backend.

قبل إنشاء Flutter بعد موافقة PM: احسم عقد auth/me/capabilities والبيئات وfeature split. يمكن عمل mock design foundation لاحقًا بالتوازي بعد هذه العقود، لكن لا يبدأ في هذه المرحلة.

## 37. BACKEND WORK THAT CAN HAPPEN DURING FLUTTER DEVELOPMENT

بعد إقرار العقود الأساسية وأمنها: إضافة adapters للطلاب والحضور والمهام والتقويم تدريجيًا، DTOs الاختبارات والتقارير والشهادات، pagination/search والتحسينات، notifications feed/device registry وpush لاحقًا، OTP state endpoints مع بقاء outbound disabled حتى تصريح منفصل. كل وحدة API تسبق screen المعتمد عليها وتملك contract fixtures+tenant tests. backend validation/calculations تبقى Laravel؛ client mocks لا تصبح business implementation.

## 38. RECOMMENDED FLUTTER ARCHITECTURE

Flutter stable/Dart compatible مثبتا الإصدار عند Phase2؛ feature-first مع UI/data layers، repository لكل مجال، thin API sources وDTO mappers، Riverpod لإدارة async/session/dependencies، Dio client مركزي، GoRouter hierarchical role-aware shell. domain layer مستقل فقط عندما يوجد منطق client متعدد الخطوات يستحقه؛ لا clean-architecture folders فارغة لكل CRUD.

Interceptors: auth header، Accept JSON، timeout/cancel، safe request ID/redacted logging، central error mapper و401 single session reset. لا retry writes تلقائيًا، ولا حساب أسعار/permissions/KPI/academic validity محليًا. repositories تدير freshness وmapping، والخادم يبقى source of truth. هذا اختيار معماري مناسب للمشروع، تدعمه توصيات Flutter العامة حول data/UI separation وrepositories: [Flutter architecture guide](https://docs.flutter.dev/app-architecture/guide).

## 39. RECOMMENDED FLUTTER PACKAGES

Required أدناه يعني مطلوبًا في التصميم المقترح، وليس أن Flutter لا يمكنه استخدام بديل. لا إصدارات مثبتة أو packages أضيفت الآن.

| Package | Purpose tied to Edaratek | Required/optional |
|---|---|---|
| [flutter_riverpod](https://pub.dev/packages/flutter_riverpod) | session/capability+async module state and dependency injection | Required in proposed design |
| [dio](https://pub.dev/packages/dio) | centralized HTTP، multipart، authenticated downloads، cancellation/interceptors | Required in proposed design |
| [go_router](https://pub.dev/packages/go_router) | role shells، nested task/detail routes، notification deep links | Required in proposed design |
| [flutter_secure_storage](https://pub.dev/packages/flutter_secure_storage) | per-device bearer secret in platform secure storage | Required |
| [json_serializable](https://pub.dev/packages/json_serializable) + json_annotation/build_runner | typed DTOs for numerous APIs with nullable/decimal/date fields | Required in proposed design |
| Flutter SDK flutter_localizations + [intl](https://pub.dev/packages/intl) | Arabic/RTL/localized dates/numbers/currency presentation | Required |
| [freezed](https://pub.dev/packages/freezed) + annotation | immutable states/unions when needed؛ ليس شرطًا لكل model | Optional |
| [image_picker](https://pub.dev/packages/image_picker) | profile/logo and attendance camera/photo selection | Required when uploads phase begins |
| [file_picker](https://pub.dev/packages/file_picker) | school documents and ticket/leave attachments | Required when documents phase begins |
| [cached_network_image](https://pub.dev/packages/cached_network_image) | public school logos/avatars bounded disk cache | Optional؛ لا sensitive attachment cache |
| [connectivity_plus](https://pub.dev/packages/connectivity_plus) | network change hints/UI؛ ليس إثبات internet | Optional؛ Dio errors authoritative |
| firebase_messaging | device push، بعد backend device registry وموافقة phase | Optional/later؛ [FCM Flutter guide](https://firebase.google.com/docs/cloud-messaging/flutter/get-started) |

لا تحتاج WhatsApp/Meta/n8n SDK داخل Flutter. لا تضف SQL/offline DB/chart/PDF packages قبل اختيار احتياج screen وcontract؛ يمكن عرض server report/download بدل إعادة بنائه.

## 40. RECOMMENDED FLUTTER DIRECTORY STRUCTURE

اقتراح فقط؛ لم تُنشأ هذه المجلدات:

```text
lib/
  app/                 # bootstrap/providers/environment composition
  core/
    api/               # Dio, DTO envelope, pagination
    auth/              # device session and capabilities
    config/
    errors/
    routing/
    storage/
    theme/
    localization/
  features/
    auth/
    onboarding/
    dashboard/
    profile/
    school_team/       # staff/teachers/delegation; shared real module
    supervision/
    students/
    attendance/
    leaves_calendar/
    tasks/
    academic_planning/
    exams/
    reports/
    certificates/
    subscriptions/
    notifications/
    verification/
  shared/              # genuinely shared widgets/utilities
```

لكل feature `data/` و`presentation/`، و`domain/` فقط عند الحاجة. لا parents portal أو student self-service directory حتى تعريف علاقة وتفويض بالـBackend واعتماد PM. admin CMS يبقى ويب أولًا ما لم يطلب نطاق موبايل.

## 41. AUTHENTICATION FLOW FOR FLUTTER

Proposed: launch→secure token lookup→GET me→account status+roles+effective capabilities+school context→onboarding or role shell. Login JSON يتحقق credential+approval+active ويصدر device token مع expiry وallowlisted user/context؛ pending/rejected تعرض code/state دون token كامل الصلاحيات. logout يلغي current token بالخادم وينظف local memory/secure token/permission cache/files؛ all-device logout يلغي الجميع؛ lost device يمكن إلغاء token الخاص به.

401 مركزي ينهي session دون حلقات،403 لا يساوي logout دائمًا؛ suspended/unlinked school لهما state واضح. secure storage يحمل token فقط ومعلومات session دنيا إن لزم، ولا passwords/OTP/HMAC/Meta secrets في preferences/logs. TLS verification افتراضي بلا badCertificateCallback bypass. input validation client UX فقط؛ Laravel authoritative. password reset/email links تحتاج deep-link contract بدل HTML browser assumptions.

## 42. ROLE-AWARE ROUTING STRATEGY

manager: home، students/attendance، tasks، school/team، more reports/planning/certificates/leaves حسب capability. supervisor: home، authorized schools، requests، tasks، profile. staff/teacher-with-staff: home، my tasks، permitted school modules، profile. super_admin: نطاق محدود اختياري بعد قرار PM، لا نسخ كل CMS تلقائيًا. student/parent: no promised portal؛ account not-supported state until scope exists.

bottom navigation من 3–5 وجهات متكررة، nested routes للتفاصيل، more/drawer للأدوات الأقل استخدامًا؛ لا تحشر جميع وحدات manager أسفل الشاشة. server capabilities تحدد ظهور الوجهة؛ direct deep link يعاد التحقق server-side. لا حفظ role choice من العميل كتفويض.

## 43. TENANT-SAFE MOBILE STRATEGY

ordinary school APIs لا تأخذ school_id لتحديد tenant؛ Laravel ينشئ authorized context من user/relationships. supervisor multi-school selection تحمل route selector مسموحًا فقط ويعاد فحص relationship لكل طلب. كل child ID يثبت انتماءه للمدرسة والأب؛ لا ترسل school_id من local storage كسلطة. cache keys تتضمن environment+user+school context+filters؛ امسح عند logout/transfer/role changes. hide UI لا يغني عن policies. error404 لا ينبغي أن يؤكد وجود سجل بمدرسة أخرى حيث الخصوصية تتطلب ذلك.

## 44. OFFLINE / CACHE RECOMMENDATION

| Data | Class | Policy |
|---|---|---|
| Tokens/OTP/password/reset tokens | SENSITIVE_NO_PERSISTENCE | bearer exception: secure storage only؛ OTP/password لا persistence |
| Approval/capabilities/current school/entitlements | NO_CACHE for authorization | memory snapshot للعرض مع revalidation؛ لا إذن offline |
| Dashboard summaries/calendar/list pages | SHORT_CACHE | read-only foreground memory، show stale time، user/school partition |
| Public plans/taxonomy/public logos | PERSISTENT_READ_CACHE | bounded TTL/version؛ public only |
| Student identity/attendance details/private files | SENSITIVE_NO_PERSISTENCE initially | no disk/offline default؛ يمكن سياسة لاحقة صريحة |
| Mutations/scores/approval/attendance submissions | NO_CACHE / no offline queue initially | server confirmation؛ pending UI لا تعني نجاحًا |

لا offline-first أو sync engine مطلوبًا حاليًا. يمكن إضافة offline attendance فقط بعد متطلب واضح وحل idempotency/conflicts/authorization expiry. توصيات caching هنا خاصة بتقييم البيانات وليست إلزامًا من package: [Flutter offline patterns](https://docs.flutter.dev/app-architecture/design-patterns/offline-first).

## 45. PUSH NOTIFICATION RECOMMENDATION

لا push implementation مكتشف. لاحقًا: Flutter يحصل device FCM token→Laravel authenticated device registration يربطه user/device/platform/environment→server provider adapter يرسل بعد business event→data minimal entity/type→التطبيق يجلب التفاصيل من API بعد authorization. token refresh/reassignment/logout cleanup وinvalid token removal والتفضيلات والpermission prompt وdedup مطلوبون. payload لا يحمل student medical/PII أو bearer tokens. لا Firebase configuration أو credentials أُنشئت في هذه المرحلة؛ [FCM Flutter setup](https://firebase.google.com/docs/cloud-messaging/flutter/get-started).

## 46. ENVIRONMENT STRATEGY

development محلي مع بيانات مصطنعة وLaravel dev URL مناسبة للمحاكي/الجهاز؛ staging host `https://test.edaratek.com` وpath `/home/test/public_html` للمراجعة اللاحقة فقط؛ production `https://edaratek.com` بعد تصريح release منفصل. flavors/build-time config للـbase URL وapp IDs، ولا provider secrets في app حتى مع dart-define. لا استخدام production أثناء التطوير الأولي.

العزل يشمل tokens/secure-storage namespaces/cache/device push registry. cached config نسخة env حساسة؛ لا نسخ cache production إلى local testing. Native Flutter لا يحتاج CORS edits؛ المتصفح فقط له origin policy. TLS validation on؛ لا تعطيل SSL لحل staging. staging GETs قد يكتب بعضها defaults؛ لهذا لم تُستدعَ أثناء Phase0.

## 47. SPEC KIT CURRENT STATE

موجود من العمل السابق: `.specify/` scripts/powershell/templates/memory/constitution/workflows/integrations، `.agents/skills/speckit-*` عشر مهارات، docs/spec-kit.md. init-options/integration.json: codex، ps، version1.2.6.dev0، sequential numbering. constitution موجود Version1.0.0 بتاريخ2026-10-31. هذه الملفات غير متتبعة ضمن baseline الحالي؛ لم يُستنتج أنها committed. specs/ غير موجود؛ لا feature spec/task حالية مكتشفة. لم تشغل أو تعدل أي مهارة/command لـSpec Kit في التدقيق.

## 48. SPEC KIT RECOMMENDED STRUCTURE

افصل prerequisite backend readiness feature داخل Laravel repo عن mobile foundation feature داخل mobile repo مقترح مستقل. Backend repo يحتفظ بالعقود والأمان وregression services؛ mobile repo يستهلك نسخة موثقة من contracts/fixtures ويملك UI/client tasks. هذا يفصل releases وCI وplatform builds ويقلل اختلاف authorization بين clients. monorepo لاحقًا خيار PM، لا نقل ملفات الآن.

بعد الموافقة فقط: Constitution review لكل repo→Specify scope→Clarify unresolved roles/phone/subscriptions→Plan dependencies/contracts/security→Checklist quality→Tasks by approved phase→Analyze consistency→Implement current phase→Converge/review. لا كتابة constitution/spec/tasks في Phase0. موافقة phase لا تعني موافقة commit/push/merge/deploy أو production/destructive DB؛ كل منها explicit approval مستقل كما طلب PM.

## 49. PROPOSED SPEC KIT FEATURE NAME

Backend prerequisite المقترح: **001-mobile-api-readiness** داخل Laravel؛ أول mobile: **001-flutter-mobile-foundation** داخل repository جديد مستقل عند اعتماده. افحص numbering الحالي وقت التنفيذ قبل تثبيت الرقم. فصل backend ضروري لأن missing bearer auth/files/tenant safety توقف أي client؛ لا تُدفن كمهام جانبية داخل Flutter. لا features أُنشئت الآن.

## 50. PROPOSED IMPLEMENTATION PHASES

كل صف phase مستقلة؛ التنفيذ داخل phase المصرح بها dependency order ثم STOP لمراجعة PM. لا tiny-task approval متكرر، مع الاستثناءات الصريحة أعلاه.

| Phase | Goal | Backend work | Flutter work | Tests | Acceptance gate | Dependencies |
|---|---|---|---|---|---|---|
| 0 — audit | توثيق الواقع | read-only findings/contracts inventory | none | static discovery؛ runtime NOT_RUN | PM reviews this report and scope | completed static review |
| 1 — security+API foundation | إزالة blockers وتثبيت contract | H/P0، test isolation، v1 auth/me/revoke، resources/errors/context، private files، outbound kill-switch | none | P0 suite+web regression+JSON contracts | tests pass، no untriaged high exposure، auth/schema agreed | PM approval |
| 2 — mobile foundation | buildable client shell | contract fixtures/staging access readiness | stable Flutter، flavors، Dio/Riverpod/router/localization/secure storage | unit/DTO/error/RTL smoke | staging-only build، no real secrets، CI checks | Phase1 contract approval |
| 3 — auth/onboarding/session | reliable approved account flow | registration/reset/status/onboarding adapters | login/logout/expiry/approval+school setup screens | auth/device/401/relaunch/negative tenant | no session/cookie dependency؛ pending/disabled deny | 1+2 |
| 4 — role shell+dashboard+tasks | useful first role slice | role analytics، task DTO/private files/default pagination | manager/supervisor/staff destinations، KPI/tasks | capability matrix+school transfer+metric parity | task lifecycle and dashboard acceptance by role | 3 |
| 5 — students/attendance/leaves/calendar | daily operations | student/class APIs، batch attendance، hardened leave/calendar/upload | lists/filters/forms/upload/status | cross-school CRUD، academic bounds، duplicate submission | end-to-end scoped workflow with server results | 4+private files |
| 6 — planning/exams | role-specific academic work | planning/schedule/exam/score contracts using services | timetable/exams/authorized teacher tools | collision/assignment/score/permission tests | Laravel service parity؛ no client business duplication | 5 |
| 7 — reports/certificates/subscriptions | extended workflows | paginated reports، secure downloads، current subscription summary | reports/certificate/entitlement screens | resource/privacy/export/pricing tests | PM accepts included scope؛ no payment claims without gateway | 4–6 |
| 8 — notifications/verification/push | communication | feed/count/mobile targets/device registry؛ OTP state؛ separately approve real sends | inbox/deep links/OTP UI/push lifecycle | mocked provider، dedup/token reassignment/cooldown | outbound stays disabled until separate authorization | auth+kill-switch+contracts |
| 9 — QA/security/performance | release candidate | query/payload budgets، all regression، env/log checks | accessibility/RTL/offline errors/device testing | P0/P1+integration+performance fixtures | no open release blockers، signed PM review | chosen modules complete |
| 10 — Android then iOS release | controlled releases | compatibility/monitoring/rollback preparation | signing/build/store assets/platform privacy | platform smoke/security/store checks | explicit release/deploy approval per environment/platform | 9+credentials supplied securely |

Parent/student self-service بوابة منفصلة discovery/design/backend auth relationships، خارج هذه المراحل الافتراضية حتى قرار PM. ترتيب OTP مبكرًا إذا أصبح gate للدخول بقرار أعمال، وإلا لا يعطل أول vertical slice. payments/store compliance scope لا يُفترض من وجود Plan.

## 51. RISKS / BLOCKERS

1. لا PHP local runtime أو نتائج اختبارات حالية؛ cached production/MySQL config يجعل bootstrap غير المنضبط خطرًا؛ no runtime safety certificate.
2. لا native token auth ولا stable v1 contract؛ API prefixes الحالية تعطي انطباع جاهزية غير صحيح.
3. H01/H02/M01 يجب حلها قبل client يعتمد private data؛ H03 privileged seeded credentials؛ H04 disable flag ليس outbound kill-switch كاملًا.
4. roles teacher/student/parent لا تساوي product features مكتملة؛ parent-child auth model مفقود.
5. default/raw JSON/pagination/time inconsistencies ستسبب DTO fragility وlarge payloads.
6. local debug true مع env production؛ live configuration UNCONFIRMED؛ لا نقل إعدادات أو كاش لهذا الغرض.
7. n8n/Meta deployment/shared cache/provider delivery behavior UNCONFIRMED؛ لا إرسال تجريبي مسموح.
8. يوجد dirty baseline سابق وSpec Kit untracked؛ لا commit تلقائي ولا reset/stash.
9. API inventory static؛ closures/vendor/dynamic runtime behavior تحتاج route:list في عملية معزولة لاحقًا. لا ادعاء فهم كل business branch أو خلو كل query من IDOR بمجرد هذا التدقيق.

## 52. RECOMMENDED NEXT ACTION

يراجع PM findings والنطاق ويعتمد **Phase1 backend readiness فقط** عند الرغبة في المتابعة. يبدأ العمل التالي بتوفير تشغيل PHP محلي مع DB اختبار منعزلة وnetwork fakes، ثم إصلاح P0/H findings وعقد auth/me/context والـv1 errors/resources. لا Flutter create قبل إذن المرحلة التالية، ولا إصلاحات تلقائية في نهاية التدقيق.

الملاحق التالية جزء من هذا التقرير نفسه؛ الـFINAL VERDICT بعد الملاحق. تم الاحتفاظ بملف routes.json للمراجعة الآلية، لكنه ليس OpenAPI أو output فعليًا من artisan.


## Appendix A — Complete static API inventory

Scope: every explicitly declared /api route, integration webhook and controller action returning JSON, including JSON behind non-API web paths. GET also permits HEAD under Laravel. Routes are unversioned. This is a static declaration inventory, not runtime route:list: PHP is unavailable. Vendor-provided routes and cached runtime route expansion are UNCONFIRMED. No endpoint below is a production-ready native bearer API today. PARTIAL means reusable server behavior under the current web session/CSRF contract; NO means integration-only.

Common HTTP/error rules: authenticated web routes require web session cookie; state-changing calls require CSRF (419), except the three signed WhatsApp POST callbacks. Anonymous JSON requests normally produce 401; role/tenant denial 403, binding absence 404, validation 422 {message,errors}, throttle 429, unexpected failure 500. EnsureApprovedAccount instead redirects to login (302) even for JSON. Status codes below list direct action codes plus this applicable common set; transitive business/service error codes must be verified with runtime contract tests. Error shape is not uniform. Input extraction below includes literal input/validation fields; consult linked FormRequest and helper/service evidence for conditional/nested rules. A key listed from the method body may be an output key, explicitly labeled as such.

### A1. GET /plans

- Route name: `plans.index`. Action: `App\Http\Controllers\PlanController@index`.
- Authentication: Public; no login required. Middleware: `web`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Public global active plans, no school context.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: role_type. Other action keys (may be response fields): role_type. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:60` / `app/Http/Controllers/PlanController.php:11`.

### A2. GET /webhooks/whatsapp/meta/verify

- Route name: `webhooks.whatsapp.meta.verify`. Action: `App\Http\Controllers\WhatsAppMetaVerificationController@__invoke`.
- Authentication: No user auth; HMAC or Meta verify token (integration-only). Middleware: `web, throttle:api.school`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Provider challenge token verification, no tenant context.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: hub.mode, hub_mode, hub.verify_token, hub_verify_token, hub.challenge, hub_challenge. Other action keys (may be response fields): hub.mode, hub_mode, hub.verify_token, hub_verify_token, hub.challenge, hub_challenge. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 403, 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: NO — backend integration only. Notes/evidence: `routes/web.php:70` / `app/Http/Controllers/WhatsAppMetaVerificationController.php:10`.

### A3. POST /webhooks/whatsapp/status

- Route name: `webhooks.whatsapp.status`. Action: `App\Http\Controllers\WhatsAppStatusCallbackController@__invoke`.
- Authentication: No user auth; HMAC or Meta verify token (integration-only). Middleware: `web, throttle:api.school`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Signed integration message reference; no mobile tenant input.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: status, statuses.0.status, provider_message_id, wamid, statuses.0.id, message_id, failure_code, errors.0.code, failure_reason, errors.0.message. Other action keys (may be response fields): message, provider_message_id, ok, updated. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200, 422, 404; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: NO — backend integration only. Notes/evidence: `routes/web.php:73` / `app/Http/Controllers/WhatsAppStatusCallbackController.php:12`.

### A4. POST /webhooks/whatsapp/dispatch/claim

- Route name: `webhooks.whatsapp.dispatch.claim`. Action: `App\Http\Controllers\WhatsAppDispatchClaimController@__invoke`.
- Authentication: No user auth; HMAC or Meta verify token (integration-only). Middleware: `web, throttle:api.school`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Signed integration message reference; no mobile tenant input.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: message_id, idempotency_key. Other action keys (may be response fields): message. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200, 422; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: NO — backend integration only. Notes/evidence: `routes/web.php:76` / `app/Http/Controllers/WhatsAppDispatchClaimController.php:12`.

### A5. POST /webhooks/whatsapp/dispatch/result

- Route name: `webhooks.whatsapp.dispatch.result`. Action: `App\Http\Controllers\WhatsAppDispatchResultController@__invoke`.
- Authentication: No user auth; HMAC or Meta verify token (integration-only). Middleware: `web, throttle:api.school`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Signed integration message reference; no mobile tenant input.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: message_id, idempotency_key, dispatch_claim_token, status, provider_message_id, wamid, failure_code, failure_reason. Other action keys (may be response fields): message. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200, 422, 409; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: NO — backend integration only. Notes/evidence: `routes/web.php:79` / `app/Http/Controllers/WhatsAppDispatchResultController.php:12`.

### A6. GET /admin/org-structure-roles

- Route name: `admin.org_structure_roles.index`. Action: `App\Http\Controllers\Admin\RoleController@indexOrgStructureRoles`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:89` / `app/Http/Controllers/Admin/RoleController.php:51`.

### A7. POST /admin/org-structure-roles

- Route name: `admin.org_structure_roles.store`. Action: `App\Http\Controllers\Admin\RoleController@storeOrgStructureRole`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: name, code, is_active. Other action keys (may be response fields): name, code, is_active, created_by, updated_by, data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:90` / `app/Http/Controllers/Admin/RoleController.php:133`.

### A8. PUT|PATCH /admin/org-structure-roles/{orgStructureRoleTemplate}

- Route name: `admin.org_structure_roles.update`. Action: `App\Http\Controllers\Admin\RoleController@updateOrgStructureRole`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: name, code, is_active. Other action keys (may be response fields): name, code, is_active, updated_by, before, after, data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:91` / `app/Http/Controllers/Admin/RoleController.php:178`.

### A9. POST /admin/org-structure-roles/{orgStructureRoleTemplate}/disable

- Route name: `admin.org_structure_roles.disable`. Action: `App\Http\Controllers\Admin\RoleController@disableOrgStructureRole`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): is_active, updated_by, name, usage_count, data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:92` / `app/Http/Controllers/Admin/RoleController.php:241`.

### A10. POST /admin/roles/org-structure

- Route name: `roles.org_structure.store`. Action: `App\Http\Controllers\Admin\RoleController@storeOrgStructureRole`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: name, code, is_active. Other action keys (may be response fields): name, code, is_active, created_by, updated_by, data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:93` / `app/Http/Controllers/Admin/RoleController.php:133`.

### A11. PUT /admin/roles/org-structure/{orgStructureRoleTemplate}

- Route name: `roles.org_structure.update`. Action: `App\Http\Controllers\Admin\RoleController@updateOrgStructureRole`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: name, code, is_active. Other action keys (may be response fields): name, code, is_active, updated_by, before, after, data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:94` / `app/Http/Controllers/Admin/RoleController.php:178`.

### A12. POST /admin/roles/org-structure/{orgStructureRoleTemplate}/disable

- Route name: `roles.org_structure.disable`. Action: `App\Http\Controllers\Admin\RoleController@disableOrgStructureRole`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): is_active, updated_by, name, usage_count, data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:95` / `app/Http/Controllers/Admin/RoleController.php:241`.

### A13. GET /admin/media

- Route name: `admin.media.index`. Action: `App\Http\Controllers\Admin\MediaController@index`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: per_page. Other action keys (may be response fields): per_page, data, pagination, current_page, last_page, total. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: YES, optional per_page; missing per_page can return all rows.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:105` / `app/Http/Controllers/Admin/MediaController.php:14`.

### A14. POST /admin/media

- Route name: `admin.media.store`. Action: `App\Http\Controllers\Admin\MediaController@store`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: file. Other action keys (may be response fields): file, file.mimetypes, file.max, file_name, file_path, file_type, mime_type, file_size. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:106` / `app/Http/Controllers/Admin/MediaController.php:41`.

### A15. DELETE /admin/media/{id}

- Route name: `admin.media.destroy`. Action: `App\Http\Controllers\Admin\MediaController@destroy`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): message. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:108` / `app/Http/Controllers/Admin/MediaController.php:94`.

### A16. GET /admin/school-defaults

- Route name: `admin.school_defaults.index`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@index`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: embedded, editor. Other action keys (may be response fields): embedded, editor. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:126` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:44`.

### A17. GET /admin/school-defaults/country-reference

- Route name: `admin.school_defaults.country_reference`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@countryReference`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: country_id. Other action keys (may be response fields): country_id. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:127` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:264`.

### A18. GET /admin/school-defaults/reference/holidays/preview

- Route name: `admin.school_defaults.reference.holidays.preview`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@previewReferenceHolidays`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: country_id, year. Other action keys (may be response fields): country_id, year. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:128` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:281`.

### A19. POST /admin/school-defaults/reference/holidays/import

- Route name: `admin.school_defaults.reference.holidays.import`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@importReferenceHolidays`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: country_id, education_type_id, year. Other action keys (may be response fields): country_id, education_type_id, year. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:129` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:302`.

### A20. POST /admin/school-defaults/scopes

- Route name: `admin.school_defaults.scopes.store`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@storeScopeConfig`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: template_name, country_id, education_type_id, reference_snapshot. Other action keys (may be response fields): template_name, country_id, education_type_id, reference_snapshot, id, name, code, scope, country_reference, holiday_sync, academic_year_sync, template_bootstrap. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:130` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:70`.

### A21. DELETE /admin/school-defaults/scopes/{country}/{educationType}

- Route name: `admin.school_defaults.scopes.destroy`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@destroyScope`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: embedded, editor. Other action keys (may be response fields): scope, country_id, country_name, education_type_id, education_type_name, counts, schools_with_imported_copies, status, message, data, deleted_counts, embedded, editor. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:131` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:181`.

### A22. POST /admin/school-defaults/stages

- Route name: `admin.school_defaults.stages.store`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@storeStage`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: education_stage_id, name, code, sort_order, is_active, school_day_start_time, school_day_end_time. Other action keys (may be response fields): education_stage_id, name, code, sort_order, is_active, school_day_start_time, school_day_end_time, name.unique, code.unique, country_id, education_type_id, directorate_id, created_by, updated_by, payload. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:132` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:332`.

### A23. PUT /admin/school-defaults/stages/{schoolDefaultStageTemplate}

- Route name: `admin.school_defaults.stages.update`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@updateStage`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: education_stage_id, name, code, sort_order, is_active, school_day_start_time, school_day_end_time. Other action keys (may be response fields): education_stage_id, name, code, sort_order, is_active, school_day_start_time, school_day_end_time, name.unique, code.unique, country_id, education_type_id, directorate_id, updated_by, before, after. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:133` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:420`.

### A24. DELETE /admin/school-defaults/stages/{schoolDefaultStageTemplate}

- Route name: `admin.school_defaults.stages.destroy`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@destroyStage`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): before. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:134` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:535`.

### A25. POST /admin/school-defaults/stage-terms

- Route name: `admin.school_defaults.stage_terms.store`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@storeStageTerm`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): school_default_stage_template_id, name, start_date, end_date, source, sort_order, is_active, created_by, updated_by, payload. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:135` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:556`.

### A26. PUT /admin/school-defaults/stage-terms/{schoolDefaultStageTermTemplate}

- Route name: `admin.school_defaults.stage_terms.update`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@updateStageTerm`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): school_default_stage_template_id, name, start_date, end_date, source, sort_order, is_active, updated_by, before, after. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:136` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:607`.

### A27. DELETE /admin/school-defaults/stage-terms/{schoolDefaultStageTermTemplate}

- Route name: `admin.school_defaults.stage_terms.destroy`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@destroyStageTerm`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): before. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:137` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:668`.

### A28. POST /admin/school-defaults/stage-grades

- Route name: `admin.school_defaults.stage_grades.store`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@storeStageGrade`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: school_default_stage_template_id, name, sort_order, is_active. Other action keys (may be response fields): school_default_stage_template_id, name, sort_order, is_active, name.unique, created_by, updated_by, payload. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:138` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:689`.

### A29. PUT /admin/school-defaults/stage-grades/{schoolDefaultStageGradeTemplate}

- Route name: `admin.school_defaults.stage_grades.update`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@updateStageGrade`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: school_default_stage_template_id, name, sort_order, is_active. Other action keys (may be response fields): school_default_stage_template_id, name, sort_order, is_active, name.unique, updated_by, updated_at, before, after. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:139` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:748`.

### A30. DELETE /admin/school-defaults/stage-grades/{schoolDefaultStageGradeTemplate}

- Route name: `admin.school_defaults.stage_grades.destroy`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@destroyStageGrade`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): before. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:140` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:830`.

### A31. POST /admin/school-defaults/stage-grade-terms

- Route name: `admin.school_defaults.stage_grade_terms.store`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@storeStageGradeTerm`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): school_default_stage_grade_template_id, name, sort_order, is_active, created_by, updated_by, payload. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:141` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:851`.

### A32. PUT /admin/school-defaults/stage-grade-terms/{gradeTermTemplate}

- Route name: `admin.school_defaults.stage_grade_terms.update`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@updateStageGradeTerm`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): school_default_stage_grade_template_id, name, sort_order, is_active, updated_by, before, after. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:142` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:900`.

### A33. DELETE /admin/school-defaults/stage-grade-terms/{gradeTermTemplate}

- Route name: `admin.school_defaults.stage_grade_terms.destroy`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@destroyStageGradeTerm`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): before. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:143` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:957`.

### A34. POST /admin/school-defaults/classrooms

- Route name: `admin.school_defaults.classrooms.store`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@storeClassroom`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): school_default_stage_template_id, school_default_stage_grade_template_id, name, code, sort_order, is_active, created_by, updated_by, payload. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:144` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:978`.

### A35. PUT /admin/school-defaults/classrooms/{schoolDefaultClassroomTemplate}

- Route name: `admin.school_defaults.classrooms.update`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@updateClassroom`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): school_default_stage_template_id, school_default_stage_grade_template_id, name, code, sort_order, is_active, updated_by, before, after. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:145` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:1042`.

### A36. DELETE /admin/school-defaults/classrooms/{schoolDefaultClassroomTemplate}

- Route name: `admin.school_defaults.classrooms.destroy`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@destroyClassroom`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): before. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:146` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:1117`.

### A37. POST /admin/school-defaults/academic-years

- Route name: `admin.school_defaults.academic_years.store`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@storeAcademicYear`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: name, starts_on, ends_on, is_active. Other action keys (may be response fields): name, starts_on, ends_on, is_active, name.unique, country_id, education_type_id, directorate_id, created_by, updated_by, payload. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:147` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:1138`.

### A38. PUT /admin/school-defaults/academic-years/{yearTemplate}

- Route name: `admin.school_defaults.academic_years.update`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@updateAcademicYear`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: name, starts_on, ends_on, is_active. Other action keys (may be response fields): name, starts_on, ends_on, is_active, name.unique, country_id, education_type_id, directorate_id, updated_by, before, after. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:148` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:1183`.

### A39. DELETE /admin/school-defaults/academic-years/{yearTemplate}

- Route name: `admin.school_defaults.academic_years.destroy`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@destroyAcademicYear`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): before. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:149` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:1243`.

### A40. POST /admin/school-defaults/holidays

- Route name: `admin.school_defaults.holidays.store`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@storeHoliday`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): country_id, education_type_id, directorate_id, name, start_date, end_date, return_date, notes, is_active, created_by, updated_by, payload. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:150` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:1264`.

### A41. PUT /admin/school-defaults/holidays/{schoolDefaultHolidayTemplate}

- Route name: `admin.school_defaults.holidays.update`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@updateHoliday`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): country_id, education_type_id, directorate_id, name, start_date, end_date, return_date, notes, is_active, updated_by, before, after. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:151` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:1313`.

### A42. DELETE /admin/school-defaults/holidays/{schoolDefaultHolidayTemplate}

- Route name: `admin.school_defaults.holidays.destroy`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@destroyHoliday`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): before. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:152` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:1372`.

### A43. POST /admin/school-defaults/leave-types

- Route name: `admin.school_defaults.leave_types.store`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@storeLeaveType`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: name, code, requires_attachment, is_active. Other action keys (may be response fields): name, code, requires_attachment, is_active, name.unique, code.unique, country_id, education_type_id, directorate_id, created_by, updated_by, payload. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:153` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:1393`.

### A44. PUT /admin/school-defaults/leave-types/{schoolDefaultLeaveTypeTemplate}

- Route name: `admin.school_defaults.leave_types.update`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@updateLeaveType`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: name, code, requires_attachment, is_active. Other action keys (may be response fields): name, code, requires_attachment, is_active, name.unique, code.unique, country_id, education_type_id, directorate_id, updated_by, before, after. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:154` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:1457`.

### A45. DELETE /admin/school-defaults/leave-types/{schoolDefaultLeaveTypeTemplate}

- Route name: `admin.school_defaults.leave_types.destroy`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@destroyLeaveType`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): before. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:155` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:1543`.

### A46. POST /admin/school-defaults/subjects

- Route name: `admin.school_defaults.subjects.store`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@storeSubject`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: name, code, branches, branches.*, is_active. Other action keys (may be response fields): name, code, branches, branches.*, is_active, name.unique, code.unique, country_id, education_type_id, directorate_id, created_by, updated_by, payload. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:156` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:1564`.

### A47. PUT /admin/school-defaults/subjects/{schoolDefaultSubjectTemplate}

- Route name: `admin.school_defaults.subjects.update`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@updateSubject`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: name, code, branches, branches.*, is_active. Other action keys (may be response fields): name, code, branches, branches.*, is_active, name.unique, code.unique, country_id, education_type_id, directorate_id, updated_by, before, after. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:157` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:1629`.

### A48. DELETE /admin/school-defaults/subjects/{schoolDefaultSubjectTemplate}

- Route name: `admin.school_defaults.subjects.destroy`. Action: `App\Http\Controllers\Admin\SchoolDefaultDataController@destroySubject`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): before. Path parameters are required where shown.
- Response: Plain text / mixed (see action). Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:158` / `app/Http/Controllers/Admin/SchoolDefaultDataController.php:1716`.

### A49. POST /admin/countries/sync-global

- Route name: `admin.countries.sync_global`. Action: `App\Http\Controllers\Admin\SchoolManagementController@syncCountriesFromGlobalApi`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): status, message, taxonomySync, summary. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:159` / `app/Http/Controllers/Admin/SchoolManagementController.php:111`.

### A50. POST /admin/governorates/sync-global

- Route name: `admin.governorates.sync_global`. Action: `App\Http\Controllers\Admin\SchoolManagementController@syncGovernoratesFromGlobalApi`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: country_id. Other action keys (may be response fields): country_id, status, message, taxonomySync, skipped_existing, empty, summary. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:163` / `app/Http/Controllers/Admin/SchoolManagementController.php:178`.

### A51. POST /admin/education-types

- Route name: `admin.education_types.store`. Action: `App\Http\Controllers\Admin\SchoolManagementController@storeEducationType`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data, id, name. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:167` / `app/Http/Controllers/Admin/SchoolManagementController.php:286`.

### A52. PUT /admin/education-types/{id}

- Route name: `admin.education_types.update`. Action: `App\Http\Controllers\Admin\SchoolManagementController@updateEducationType`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): name, data, id. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:168` / `app/Http/Controllers/Admin/SchoolManagementController.php:304`.

### A53. DELETE /admin/education-types/{id}

- Route name: `admin.education_types.delete`. Action: `App\Http\Controllers\Admin\SchoolManagementController@destroyEducationType`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): education_type, status. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:169` / `app/Http/Controllers/Admin/SchoolManagementController.php:327`.

### A54. POST /admin/education-stages

- Route name: `admin.education_stages.store`. Action: `App\Http\Controllers\Admin\SchoolManagementController@storeEducationStage`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:170` / `app/Http/Controllers/Admin/SchoolManagementController.php:352`.

### A55. PUT /admin/education-stages/{id}

- Route name: `admin.education_stages.update`. Action: `App\Http\Controllers\Admin\SchoolManagementController@updateEducationStage`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:171` / `app/Http/Controllers/Admin/SchoolManagementController.php:367`.

### A56. DELETE /admin/education-stages/{id}

- Route name: `admin.education_stages.delete`. Action: `App\Http\Controllers\Admin\SchoolManagementController@destroyEducationStage`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): education_stage, status. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:172` / `app/Http/Controllers/Admin/SchoolManagementController.php:383`.

### A57. GET /api/system/permissions

- Route name: `api.system.permissions.index`. Action: `App\Http\Controllers\Api\System\SystemPermissionController@index`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin, throttle:api.system`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Global system data; super_admin route guard.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data, id, name, guard_name, created_at, updated_at. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:199` / `app/Http/Controllers/Api/System/SystemPermissionController.php:14`.

### A58. POST /api/system/permissions

- Route name: `api.system.permissions.store`. Action: `App\Http\Controllers\Api\System\SystemPermissionController@store`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin, throttle:api.system`.
- Role/permission: Action/FormRequest capability references: manage-system-roles; per-action FormRequest/guards are authoritative.
- Tenant rule: Global system data; super_admin route guard.
- FormRequest: `app/Http/Requests/System/StoreSystemPermissionRequest.php`.
- Request fields from FormRequest: name, guard_name. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): name, guard_name, data, id, created_at, updated_at. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:200` / `app/Http/Controllers/Api/System/SystemPermissionController.php:34`.

### A59. GET /api/system/roles

- Route name: `api.system.roles.index`. Action: `App\Http\Controllers\Api\System\SystemRoleController@index`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin, throttle:api.system`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Global system data; super_admin route guard.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): permissions, data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:202` / `app/Http/Controllers/Api/System/SystemRoleController.php:18`.

### A60. POST /api/system/roles

- Route name: `api.system.roles.store`. Action: `App\Http\Controllers\Api\System\SystemRoleController@store`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin, throttle:api.system`.
- Role/permission: Action/FormRequest capability references: manage-system-roles; per-action FormRequest/guards are authoritative.
- Tenant rule: Global system data; super_admin route guard.
- FormRequest: `app/Http/Requests/System/StoreSystemRoleRequest.php`.
- Request fields from FormRequest: name, guard_name, display_name, description, assignable_by_school_admin, is_system, permission_names, permission_names.*. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): name, guard_name, display_name, description, assignable_by_school_admin, is_system, data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:203` / `app/Http/Controllers/Api/System/SystemRoleController.php:34`.

### A61. GET /api/system/roles/{role}

- Route name: `api.system.roles.show`. Action: `App\Http\Controllers\Api\System\SystemRoleController@show`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin, throttle:api.system`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Global system data; super_admin route guard.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): permissions, data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:204` / `app/Http/Controllers/Api/System/SystemRoleController.php:63`.

### A62. PUT /api/system/roles/{role}

- Route name: `api.system.roles.update`. Action: `App\Http\Controllers\Api\System\SystemRoleController@update`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin, throttle:api.system`.
- Role/permission: Action/FormRequest capability references: manage-system-roles; per-action FormRequest/guards are authoritative.
- Tenant rule: Global system data; super_admin route guard.
- FormRequest: `app/Http/Requests/System/UpdateSystemRoleRequest.php`.
- Request fields from FormRequest: name, guard_name, display_name, description, assignable_by_school_admin, is_system, permission_names, permission_names.*. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:205` / `app/Http/Controllers/Api/System/SystemRoleController.php:74`.

### A63. DELETE /api/system/roles/{role}

- Route name: `api.system.roles.destroy`. Action: `App\Http\Controllers\Api\System\SystemRoleController@destroy`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin, throttle:api.system`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Global system data; super_admin route guard.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): role. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 204; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:206` / `app/Http/Controllers/Api/System/SystemRoleController.php:126`.

### A64. PUT /api/system/roles/{role}/permissions

- Route name: `api.system.roles.permissions.sync`. Action: `App\Http\Controllers\Api\System\SystemRoleController@syncPermissions`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:super_admin, throttle:api.system`.
- Role/permission: Action/FormRequest capability references: manage-system-roles; per-action FormRequest/guards are authoritative.
- Tenant rule: Global system data; super_admin route guard.
- FormRequest: `app/Http/Requests/System/SyncSystemRolePermissionsRequest.php`.
- Request fields from FormRequest: permission_names, permission_names.*. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:207` / `app/Http/Controllers/Api/System/SystemRoleController.php:109`.

### A65. GET /api/school/roles/assignable

- Route name: `api.school.roles.assignable`. Action: `App\Http\Controllers\Api\School\SchoolAssignableRoleController@index`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school, active_school_association, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-school-users; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: `app/Http/Requests/School/ListAssignableSchoolRolesRequest.php`.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data, id, name, display_name, description, assignable_by_school_admin, is_system. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:211` / `app/Http/Controllers/Api/School/SchoolAssignableRoleController.php:18`.

### A66. GET /api/school/org-structure-roles

- Route name: `api.school.org_structure_roles.index`. Action: `App\Http\Controllers\Api\School\SchoolOrgStructureRoleController@index`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school, active_school_association, throttle:api.school`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: department_id. Other action keys (may be response fields): department_id, data, id, name, code, is_active, created_at, updated_at. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200, 404; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:212` / `app/Http/Controllers/Api/School/SchoolOrgStructureRoleController.php:15`.

### A67. GET /api/school/permission-groups

- Route name: `api.school.permission_groups.index`. Action: `App\Http\Controllers\Api\School\SchoolPermissionGroupController@index`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school, active_school_association, throttle:api.school`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:213` / `app/Http/Controllers/Api/School/SchoolPermissionGroupController.php:24`.

### A68. POST /api/school/permission-groups

- Route name: `api.school.permission_groups.store`. Action: `App\Http\Controllers\Api\School\SchoolPermissionGroupController@store`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school, active_school_association, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-school-users; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: `app/Http/Requests/School/StoreSchoolPermissionGroupRequest.php`.
- Request fields from FormRequest: name, group_type, permission_names, permission_names.*. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): school_id, name, group_type, permission_names, created_by, updated_by, data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:214` / `app/Http/Controllers/Api/School/SchoolPermissionGroupController.php:41`.

### A69. PUT /api/school/permission-groups/{schoolPermissionGroup}

- Route name: `api.school.permission_groups.update`. Action: `App\Http\Controllers\Api\School\SchoolPermissionGroupController@update`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school, active_school_association, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-school-users; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: `app/Http/Requests/School/UpdateSchoolPermissionGroupRequest.php`.
- Request fields from FormRequest: name, group_type, permission_names, permission_names.*. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): name, group_type, permission_names, updated_by, school_id, data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:215` / `app/Http/Controllers/Api/School/SchoolPermissionGroupController.php:83`.

### A70. DELETE /api/school/permission-groups/{schoolPermissionGroup}

- Route name: `api.school.permission_groups.destroy`. Action: `App\Http\Controllers\Api\School\SchoolPermissionGroupController@destroy`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school, active_school_association, throttle:api.school`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): school_id, before, message. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:216` / `app/Http/Controllers/Api/School/SchoolPermissionGroupController.php:124`.

### A71. GET /api/school/users

- Route name: `api.school.users.index`. Action: `App\Http\Controllers\Api\School\SchoolUserManagementController@index`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school, active_school_association, throttle:api.school`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: per_page. Other action keys (may be response fields): per_page, data, pagination, current_page, last_page, total. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: YES, optional per_page; missing per_page can return all rows.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:218` / `app/Http/Controllers/Api/School/SchoolUserManagementController.php:44`.

### A72. POST /api/school/users

- Route name: `api.school.users.store`. Action: `App\Http\Controllers\Api\School\SchoolUserManagementController@store`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school, active_school_association, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-school-users; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: `app/Http/Requests/School/StoreSchoolUserWithRolesRequest.php`.
- Request fields from FormRequest: name, email, mobile, department_id, department_role_id, password, role_names, role_names.*, permission_names, permission_names.*, school_permission_group_ids, school_permission_group_ids.*, can_manage_student_structure, can_manage_student_attendance, can_manage_academic_planning, can_manage_student_leaves, can_manage_leave_types, can_manage_school_calendar, can_manage_school_holidays. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): department_id, department_role_id, name, email, mobile, password, role, is_active, school_id, school_staff_type, user, seat_addon, actor_id, target_user_id, assigned_roles, data, added_seats_count, extra_user_monthly_price, daily_price, remaining_days, amount, ends_at, message. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:219` / `app/Http/Controllers/Api/School/SchoolUserManagementController.php:76`.

### A73. PUT /api/school/users/{user}

- Route name: `api.school.users.update`. Action: `App\Http\Controllers\Api\School\SchoolUserManagementController@update`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school, active_school_association, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-school-users; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: `app/Http/Requests/School/UpdateSchoolUserWithRolesRequest.php`.
- Request fields from FormRequest: name, email, mobile, department_id, department_role_id, password, role_names, role_names.*, permission_names, permission_names.*, school_permission_group_ids, school_permission_group_ids.*, can_manage_student_structure, can_manage_student_attendance, can_manage_academic_planning, can_manage_student_leaves, can_manage_leave_types, can_manage_school_calendar, can_manage_school_holidays. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): department_id, department_role_id, name, email, mobile, school_id, school_staff_type, role, actor_id, target_user_id, assigned_roles, before, after, data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:220` / `app/Http/Controllers/Api/School/SchoolUserManagementController.php:246`.

### A74. PUT /api/school/users/{user}/roles

- Route name: `api.school.users.roles.sync`. Action: `App\Http\Controllers\Api\School\SchoolUserManagementController@syncRoles`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school, active_school_association, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-school-users; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: `app/Http/Requests/School/SyncSchoolUserRolesRequest.php`.
- Request fields from FormRequest: role_names, role_names.*. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): role, actor_id, school_id, target_user_id, assigned_roles, before_roles, after_roles, data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:221` / `app/Http/Controllers/Api/School/SchoolUserManagementController.php:203`.

### A75. GET /supervisor/onboarding/regions

- Route name: `supervisor.onboarding.regions`. Action: `App\Http\Controllers\Supervisor\OnboardingController@regions`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:supervisor`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Supervisor selected school validated against server relationships / ticket owner policy; see findings.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): countries, governorates, regions, id, name, governorate, country_id, governorate_id, country, governorate_model. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:226` / `app/Http/Controllers/Supervisor/OnboardingController.php:39`.

### A76. GET /supervisor/onboarding/location-schools

- Route name: `supervisor.onboarding.location_schools`. Action: `App\Http\Controllers\Supervisor\OnboardingController@locationSchools`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:supervisor`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Supervisor selected school validated against server relationships / ticket owner policy; see findings.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: country_id, governorate_id. Other action keys (may be response fields): country_id, governorate_id. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:227` / `app/Http/Controllers/Supervisor/OnboardingController.php:82`.

### A77. GET /supervisor/onboarding/regions/{region}/schools

- Route name: `supervisor.onboarding.schools`. Action: `App\Http\Controllers\Supervisor\OnboardingController@schools`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:supervisor`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Supervisor selected school validated against server relationships / ticket owner policy; see findings.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): none. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:228` / `app/Http/Controllers/Supervisor/OnboardingController.php:144`.

### A78. POST /supervisor/onboarding/select

- Route name: `supervisor.onboarding.select`. Action: `App\Http\Controllers\Supervisor\OnboardingController@select`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:supervisor`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Supervisor selected school validated against server relationships / ticket owner policy; see findings.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: region_id, school_ids, school_ids.*, country_id, governorate_id. Other action keys (may be response fields): region_id, school_ids, school_ids.*, message, skipped_school_ids, onboarding_region_id, onboarding_completed_at, created_count, country_id, governorate_id. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200, 422; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:229` / `app/Http/Controllers/Supervisor/OnboardingController.php:178`.

### A79. GET /supervisor/requests

- Route name: `supervisor.requests.index`. Action: `App\Http\Controllers\Supervisor\RequestController@index`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:supervisor`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Supervisor selected school validated against server relationships / ticket owner policy; see findings.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): none. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:232` / `app/Http/Controllers/Supervisor/RequestController.php:32`.

### A80. POST /supervisor/requests/{schoolSupervisionRequest}/confirm

- Route name: `supervisor.requests.confirm`. Action: `App\Http\Controllers\Supervisor\RequestController@confirm`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:supervisor`.
- Role/permission: Action/FormRequest capability references: confirm; per-action FormRequest/guards are authoritative.
- Tenant rule: Supervisor selected school validated against server relationships / ticket owner policy; see findings.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): none. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:233` / `app/Http/Controllers/Supervisor/RequestController.php:43`.

### A81. POST /supervisor/requests/{schoolSupervisionRequest}/cancel

- Route name: `supervisor.requests.cancel`. Action: `App\Http\Controllers\Supervisor\RequestController@cancel`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:supervisor`.
- Role/permission: Action/FormRequest capability references: cancel; per-action FormRequest/guards are authoritative.
- Tenant rule: Supervisor selected school validated against server relationships / ticket owner policy; see findings.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: notes. Other action keys (may be response fields): notes. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:234` / `app/Http/Controllers/Supervisor/RequestController.php:52`.

### A82. GET /supervisor/tickets

- Route name: `supervisor.tickets.index`. Action: `App\Http\Controllers\Supervisor\TicketController@index`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:supervisor`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Supervisor selected school validated against server relationships / ticket owner policy; see findings.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: per_page. Other action keys (may be response fields): per_page, data, pagination, current_page, last_page, total. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: YES, optional per_page; missing per_page can return all rows.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:238` / `app/Http/Controllers/Supervisor/TicketController.php:18`.

### A83. POST /supervisor/tickets

- Route name: `supervisor.tickets.store`. Action: `App\Http\Controllers\Supervisor\TicketController@store`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:supervisor`.
- Role/permission: Action/FormRequest capability references: create; per-action FormRequest/guards are authoritative.
- Tenant rule: Supervisor selected school validated against server relationships / ticket owner policy; see findings.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: title, description, priority, due_date, school_id, assigned_to. Other action keys (may be response fields): title, description, priority, due_date, school_id, assigned_to, message, created_by, status. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 422, 403, 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:239` / `app/Http/Controllers/Supervisor/TicketController.php:49`.

### A84. GET /supervisor/tickets/{ticket}

- Route name: `supervisor.tickets.show`. Action: `App\Http\Controllers\Supervisor\TicketController@show`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:supervisor`.
- Role/permission: Action/FormRequest capability references: view; per-action FormRequest/guards are authoritative.
- Tenant rule: Supervisor selected school validated against server relationships / ticket owner policy; see findings.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): none. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:240` / `app/Http/Controllers/Supervisor/TicketController.php:93`.

### A85. PUT /supervisor/tickets/{ticket}

- Route name: `supervisor.tickets.update`. Action: `App\Http\Controllers\Supervisor\TicketController@update`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:supervisor`.
- Role/permission: Action/FormRequest capability references: update; per-action FormRequest/guards are authoritative.
- Tenant rule: Supervisor selected school validated against server relationships / ticket owner policy; see findings.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: title, description, priority, due_date, status. Other action keys (may be response fields): title, description, priority, due_date, status. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:241` / `app/Http/Controllers/Supervisor/TicketController.php:107`.

### A86. POST /supervisor/tickets/{ticket}/close

- Route name: `supervisor.tickets.close`. Action: `App\Http\Controllers\Supervisor\TicketController@close`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:supervisor`.
- Role/permission: Action/FormRequest capability references: close; per-action FormRequest/guards are authoritative.
- Tenant rule: Supervisor selected school validated against server relationships / ticket owner policy; see findings.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): reason, closed_at. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:242` / `app/Http/Controllers/Supervisor/TicketController.php:128`.

### A87. GET /manager/onboarding/regions

- Route name: `manager.onboarding.regions`. Action: `App\Http\Controllers\Manager\OnboardingController@regions`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: School selection is input; manager ownership, region and locking checks.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): countries, governorates, educationTypes, educationStages, regions, id, name, governorate, country_id, governorate_id, education_type_id, country, governorate_model, education_type. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:247` / `app/Http/Controllers/Manager/OnboardingController.php:93`.

### A88. GET /manager/onboarding/templates

- Route name: `manager.onboarding.templates`. Action: `App\Http\Controllers\Manager\OnboardingController@templates`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: School selection is input; manager ownership, region and locking checks.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: country_id, education_type_id. Other action keys (may be response fields): country_id, education_type_id, templates, message. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:248` / `app/Http/Controllers/Manager/OnboardingController.php:153`.

### A89. GET /manager/onboarding/governorates

- Route name: `manager.onboarding.governorates`. Action: `App\Http\Controllers\Manager\OnboardingController@governorates`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: School selection is input; manager ownership, region and locking checks.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: country_id. Other action keys (may be response fields): country_id, message, governorates. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:249` / `app/Http/Controllers/Manager/OnboardingController.php:177`.

### A90. GET /manager/onboarding/regions/{region}/schools

- Route name: `manager.onboarding.schools`. Action: `App\Http\Controllers\Manager\OnboardingController@schools`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: School selection is input; manager ownership, region and locking checks.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): none. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:250` / `app/Http/Controllers/Manager/OnboardingController.php:211`.

### A91. POST /manager/onboarding/select

- Route name: `manager.onboarding.select`. Action: `App\Http\Controllers\Manager\OnboardingController@select`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: School selection is input; manager ownership, region and locking checks.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: region_id, school_id. Other action keys (may be response fields): region_id, school_id, manager_user_id, status, manager_id, school, pending_request_ids. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:251` / `app/Http/Controllers/Manager/OnboardingController.php:235`.

### A92. POST /manager/onboarding/schools

- Route name: `manager.onboarding.schools.store`. Action: `App\Http\Controllers\Manager\OnboardingController@storeSchool`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager`.
- Role/permission: Action/FormRequest capability references: school_manager; per-action FormRequest/guards are authoritative.
- Tenant rule: School selection is input; manager ownership, region and locking checks.
- FormRequest: `app/Http/Requests/Manager/StoreManagerOnboardingSchoolRequest.php`.
- Request fields from FormRequest: region_id, country_id, governorate_id, education_type_id, template_key, school_type, education_stage_ids, education_stage_ids.*, name, phone, email, address, notes, logo, country_id.required_without, governorate_id.required_without, governorate_id.exists, education_type_id.required_without, school_type.required, school_type.in, education_stage_ids.required, education_stage_ids.array, education_stage_ids.min, education_stage_ids.*.exists, name.required, name.max, phone.required, phone.max, email.email, email.max, address.max, notes.max, logo.mimes, logo.max. Inline/direct input fields: region_id, country_id, education_type_id, template_key, name, phone, email, address, notes, logo, country_id.required_without, education_type_id.required_without, school. Other action keys (may be response fields): region_id, country_id, education_type_id, template_key, name, phone, email, address, notes, logo, country_id.required_without, education_type_id.required_without, school, directorate_id, school_type, school_id, logo_path, status, supervision_status, manager_user_id, default_template_key, default_template_name, education_stage_ids, manager_id, used_template_selection. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:252` / `app/Http/Controllers/Manager/OnboardingController.php:335`.

### A93. PUT /manager/onboarding/schools/{school}

- Route name: `manager.onboarding.schools.update`. Action: `App\Http\Controllers\Manager\OnboardingController@updateSchool`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: School selection is input; manager ownership, region and locking checks.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: name, phone, email, address, notes, logo. Other action keys (may be response fields): name, phone, email, address, notes, logo, school. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:253` / `app/Http/Controllers/Manager/OnboardingController.php:472`.

### A94. GET /manager/requests

- Route name: `manager.requests.index`. Action: `App\Http\Controllers\Manager\RequestController@index`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Manager school/assigned ticket context; inspect policy and query (see findings).
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): none. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:257` / `app/Http/Controllers/Manager/RequestController.php:32`.

### A95. POST /manager/requests/{schoolSupervisionRequest}/approve

- Route name: `manager.requests.approve`. Action: `App\Http\Controllers\Manager\RequestController@approve`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school`.
- Role/permission: Action/FormRequest capability references: approve; per-action FormRequest/guards are authoritative.
- Tenant rule: Manager school/assigned ticket context; inspect policy and query (see findings).
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: notes. Other action keys (may be response fields): notes. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:258` / `app/Http/Controllers/Manager/RequestController.php:47`.

### A96. POST /manager/requests/{schoolSupervisionRequest}/reject

- Route name: `manager.requests.reject`. Action: `App\Http\Controllers\Manager\RequestController@reject`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school`.
- Role/permission: Action/FormRequest capability references: reject; per-action FormRequest/guards are authoritative.
- Tenant rule: Manager school/assigned ticket context; inspect policy and query (see findings).
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: notes. Other action keys (may be response fields): notes. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:259` / `app/Http/Controllers/Manager/RequestController.php:65`.

### A97. GET /association-requests

- Route name: `association_requests.index`. Action: `App\Http\Controllers\AssociationRequestController@index`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school`.
- Role/permission: Action/FormRequest capability references: school_manager; per-action FormRequest/guards are authoritative.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): none. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:261` / `app/Http/Controllers/AssociationRequestController.php:16`.

### A98. POST /association-requests/{associationRequest}/approve

- Route name: `association_requests.approve`. Action: `App\Http\Controllers\AssociationRequestController@approve`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school`.
- Role/permission: Action/FormRequest capability references: respond; per-action FormRequest/guards are authoritative.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): none. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:262` / `app/Http/Controllers/AssociationRequestController.php:31`.

### A99. POST /association-requests/{associationRequest}/reject

- Route name: `association_requests.reject`. Action: `App\Http\Controllers\AssociationRequestController@reject`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school`.
- Role/permission: Action/FormRequest capability references: respond; per-action FormRequest/guards are authoritative.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: notes. Other action keys (may be response fields): notes. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:263` / `app/Http/Controllers/AssociationRequestController.php:40`.

### A100. GET /manager/tickets

- Route name: `manager.tickets.index`. Action: `App\Http\Controllers\Manager\TicketController@index`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school, active_school_association`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Manager school/assigned ticket context; inspect policy and query (see findings).
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: per_page. Other action keys (may be response fields): per_page, data, pagination, current_page, last_page, total. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: YES, optional per_page; missing per_page can return all rows.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:276` / `app/Http/Controllers/Manager/TicketController.php:20`.

### A101. POST /manager/tickets

- Route name: `manager.tickets.store`. Action: `App\Http\Controllers\Manager\TicketController@store`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school, active_school_association`.
- Role/permission: Action/FormRequest capability references: staff; per-action FormRequest/guards are authoritative.
- Tenant rule: Manager school/assigned ticket context; inspect policy and query (see findings).
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: title, description, priority, due_date, assigned_to. Other action keys (may be response fields): title, description, priority, due_date, assigned_to, message, school_id, created_by, status, ticket_id, action. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 422, 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:277` / `app/Http/Controllers/Manager/TicketController.php:70`.

### A102. GET /manager/tickets/{ticket}

- Route name: `manager.tickets.show`. Action: `App\Http\Controllers\Manager\TicketController@show`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school, active_school_association`.
- Role/permission: Action/FormRequest capability references: view; per-action FormRequest/guards are authoritative.
- Tenant rule: Manager school/assigned ticket context; inspect policy and query (see findings).
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): none. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:278` / `app/Http/Controllers/Manager/TicketController.php:56`.

### A103. POST /manager/tickets/{ticket}/final-report

- Route name: `manager.tickets.final_report`. Action: `App\Http\Controllers\Manager\TicketController@finalReport`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school, active_school_association`.
- Role/permission: Action/FormRequest capability references: addFinalReport; per-action FormRequest/guards are authoritative.
- Tenant rule: Manager school/assigned ticket context; inspect policy and query (see findings).
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: manager_final_report. Other action keys (may be response fields): message, manager_final_report, action. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200, 422; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:279` / `app/Http/Controllers/Manager/TicketController.php:144`.

### A104. POST /manager/tickets/{ticket}/close

- Route name: `manager.tickets.close`. Action: `App\Http\Controllers\Manager\TicketController@close`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school, active_school_association`.
- Role/permission: Action/FormRequest capability references: view; per-action FormRequest/guards are authoritative.
- Tenant rule: Manager school/assigned ticket context; inspect policy and query (see findings).
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): message, reason, closed_at. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200, 422; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:280` / `app/Http/Controllers/Manager/TicketController.php:172`.

### A105. POST /manager/subtasks

- Route name: `manager.subtasks.store`. Action: `App\Http\Controllers\Manager\SubtaskController@store`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school, active_school_association`.
- Role/permission: Action/FormRequest capability references: create, view, staff; per-action FormRequest/guards are authoritative.
- Tenant rule: Manager school/assigned ticket context; inspect policy and query (see findings).
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: ticket_id, title, description, due_date, assigned_to. Other action keys (may be response fields): ticket_id, title, description, due_date, assigned_to, message, school_id, created_by, status, action. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 422, 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:282` / `app/Http/Controllers/Manager/SubtaskController.php:19`.

### A106. PUT /manager/subtasks/{subtask}

- Route name: `manager.subtasks.update`. Action: `App\Http\Controllers\Manager\SubtaskController@update`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school, active_school_association`.
- Role/permission: Action/FormRequest capability references: update, staff; per-action FormRequest/guards are authoritative.
- Tenant rule: Manager school/assigned ticket context; inspect policy and query (see findings).
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: title, description, due_date, status, assigned_to. Other action keys (may be response fields): title, description, due_date, status, assigned_to, message. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200, 422; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:283` / `app/Http/Controllers/Manager/SubtaskController.php:66`.

### A107. POST /manager/subtasks/{subtask}/approve

- Route name: `manager.subtasks.approve`. Action: `App\Http\Controllers\Manager\SubtaskController@approve`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:school_manager, managed_school, active_school_association`.
- Role/permission: Action/FormRequest capability references: approve; per-action FormRequest/guards are authoritative.
- Tenant rule: Manager school/assigned ticket context; inspect policy and query (see findings).
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): action. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:284` / `app/Http/Controllers/Manager/SubtaskController.php:120`.

### A108. POST /school/default-data/import

- Route name: `school.default_data.import`. Action: `App\Http\Controllers\School\DefaultDataImportController@store`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, throttle:api.school`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): message, data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200, 403; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:317` / `app/Http/Controllers/School/DefaultDataImportController.php:19`.

### A109. GET /school/reports/export

- Route name: `school.reports.export`. Action: `App\Http\Controllers\School\ReportsController@export`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, school_reports_access`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: See linked controller; role guard inherited.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): school_id, entity, format, rows_count, filters, generated_at, datasets, نطاق التقرير, عدد السجلات, من تاريخ, إلى تاريخ, البحث, school, schoolLogoImage, documentTitle, documentSubtitle, generatedAt, exportedBy, details, الصيغة. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 403, 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:344` / `app/Http/Controllers/School/ReportsController.php:97`.

### A110. GET /api/school/leave-types

- Route name: `api.school.leave_types.index`. Action: `App\Http\Controllers\Api\School\StudentLeaveManagementController@leaveTypes`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, student_leave_access, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-student-leaves, manage-leave-types; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data, id, code, name, category, requires_attachment, is_active. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200, 403; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:385` / `app/Http/Controllers/Api/School/StudentLeaveManagementController.php:36`.

### A111. POST /api/school/leave-types

- Route name: `api.school.leave_types.store`. Action: `App\Http\Controllers\Api\School\StudentLeaveManagementController@storeLeaveType`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, student_leave_access, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-leave-types; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: `app/Http/Requests/School/Leaves/StoreSchoolLeaveTypeRequest.php`.
- Request fields from FormRequest: name, code, category, requires_attachment, is_active. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data, id, code, name, category, requires_attachment, is_active. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:386` / `app/Http/Controllers/Api/School/StudentLeaveManagementController.php:72`.

### A112. PATCH /api/school/leave-types/{schoolLeaveType}

- Route name: `api.school.leave_types.update`. Action: `App\Http\Controllers\Api\School\StudentLeaveManagementController@updateLeaveType`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, student_leave_access, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-leave-types; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: `app/Http/Requests/School/Leaves/UpdateSchoolLeaveTypeRequest.php`.
- Request fields from FormRequest: name, code, category, requires_attachment, is_active. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data, id, code, name, category, requires_attachment, is_active. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:387` / `app/Http/Controllers/Api/School/StudentLeaveManagementController.php:96`.

### A113. GET /api/school/leave-types/{schoolLeaveType}/delete-impact

- Route name: `api.school.leave_types.delete_impact`. Action: `App\Http\Controllers\Api\School\StudentLeaveManagementController@leaveTypeDeleteImpact`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, student_leave_access, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-leave-types; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200, 403; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:388` / `app/Http/Controllers/Api/School/StudentLeaveManagementController.php:150`.

### A114. POST /api/school/leave-types/{schoolLeaveType}/disable

- Route name: `api.school.leave_types.disable`. Action: `App\Http\Controllers\Api\School\StudentLeaveManagementController@disableLeaveType`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, student_leave_access, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-leave-types; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: `app/Http/Requests/School/Leaves/DisableSchoolLeaveTypeRequest.php`.
- Request fields from FormRequest: confirm_impact. Inline/direct input fields: confirm_impact. Other action keys (may be response fields): confirm_impact, data, id, code, name, category, requires_attachment, is_active. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:389` / `app/Http/Controllers/Api/School/StudentLeaveManagementController.php:123`.

### A115. GET /api/school/school-calendar-settings

- Route name: `api.school.calendar_settings.show`. Action: `App\Http\Controllers\Api\School\SchoolCalendarManagementController@showSettings`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, student_leave_access, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-school-calendar; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: `app/Http/Requests/School/Calendar/ShowSchoolCalendarSettingsRequest.php`.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:391` / `app/Http/Controllers/Api/School/SchoolCalendarManagementController.php:27`.

### A116. PUT /api/school/school-calendar-settings

- Route name: `api.school.calendar_settings.update`. Action: `App\Http\Controllers\Api\School\SchoolCalendarManagementController@updateSettings`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, student_leave_access, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-school-calendar; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: `app/Http/Requests/School/Calendar/UpdateSchoolCalendarSettingsRequest.php`.
- Request fields from FormRequest: week_start_day, weekly_off_days, weekly_off_days.*. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:392` / `app/Http/Controllers/Api/School/SchoolCalendarManagementController.php:38`.

### A117. GET /api/school/holidays

- Route name: `api.school.holidays.index`. Action: `App\Http\Controllers\Api\School\SchoolCalendarManagementController@indexHolidays`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, student_leave_access, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-school-holidays; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: `app/Http/Requests/School/Calendar/ListSchoolHolidaysRequest.php`.
- Request fields from FormRequest: from, to, is_active. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:394` / `app/Http/Controllers/Api/School/SchoolCalendarManagementController.php:55`.

### A118. POST /api/school/holidays

- Route name: `api.school.holidays.store`. Action: `App\Http\Controllers\Api\School\SchoolCalendarManagementController@storeHoliday`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, student_leave_access, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-school-holidays; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: `app/Http/Requests/School/Calendar/StoreSchoolHolidayRequest.php`.
- Request fields from FormRequest: name, start_date, days_count, end_date, return_date, notes, is_active. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:395` / `app/Http/Controllers/Api/School/SchoolCalendarManagementController.php:65`.

### A119. GET /api/school/holidays/{schoolHoliday}/delete-impact

- Route name: `api.school.holidays.delete_impact`. Action: `App\Http\Controllers\Api\School\SchoolCalendarManagementController@holidayDeleteImpact`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, student_leave_access, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-school-holidays; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200, 403; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:396` / `app/Http/Controllers/Api/School/SchoolCalendarManagementController.php:148`.

### A120. GET /api/school/holidays/{schoolHoliday}/update-impact

- Route name: `api.school.holidays.update_impact`. Action: `App\Http\Controllers\Api\School\SchoolCalendarManagementController@holidayUpdateImpact`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, student_leave_access, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-school-holidays; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: name, start_date, days_count, end_date, return_date, notes, is_active. Other action keys (may be response fields): name, start_date, days_count, end_date, return_date, notes, is_active, data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200, 403; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:397` / `app/Http/Controllers/Api/School/SchoolCalendarManagementController.php:166`.

### A121. PATCH /api/school/holidays/{schoolHoliday}

- Route name: `api.school.holidays.update`. Action: `App\Http\Controllers\Api\School\SchoolCalendarManagementController@updateHoliday`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, student_leave_access, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-school-holidays; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: `app/Http/Requests/School/Calendar/UpdateSchoolHolidayRequest.php`.
- Request fields from FormRequest: name, start_date, days_count, end_date, return_date, notes, is_active, confirm_impact. Inline/direct input fields: confirm_impact. Other action keys (may be response fields): confirm_impact, message, message_code, impact, data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200, 409; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:398` / `app/Http/Controllers/Api/School/SchoolCalendarManagementController.php:82`.

### A122. POST /api/school/holidays/{schoolHoliday}/disable

- Route name: `api.school.holidays.disable`. Action: `App\Http\Controllers\Api\School\SchoolCalendarManagementController@disableHoliday`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, student_leave_access, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-school-holidays; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: `app/Http/Requests/School/Calendar/DisableSchoolHolidayRequest.php`.
- Request fields from FormRequest: confirm_impact. Inline/direct input fields: confirm_impact. Other action keys (may be response fields): confirm_impact, message, message_code, impact, data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200, 409; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:399` / `app/Http/Controllers/Api/School/SchoolCalendarManagementController.php:117`.

### A123. GET /api/school/leaves

- Route name: `api.school.leaves.index`. Action: `App\Http\Controllers\Api\School\StudentLeaveManagementController@index`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, student_leave_access, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-student-leaves; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: `app/Http/Requests/School/Leaves/ListStudentLeavesRequest.php`.
- Request fields from FormRequest: school_student_id, school_stage_id, school_classroom_id, classroom_grade_name, school_leave_type_id, status, source, date_from, date_to, per_page. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data, pagination, current_page, per_page, last_page, total. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: YES, optional per_page; missing per_page can return all rows.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:401` / `app/Http/Controllers/Api/School/StudentLeaveManagementController.php:169`.

### A124. POST /api/school/leaves

- Route name: `api.school.leaves.store`. Action: `App\Http\Controllers\Api\School\StudentLeaveManagementController@store`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, student_leave_access, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-student-leaves; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: `app/Http/Requests/School/Leaves/StoreStudentLeaveRequest.php`.
- Request fields from FormRequest: school_student_id, school_leave_type_id, source, start_date, end_date, reason, status. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:402` / `app/Http/Controllers/Api/School/StudentLeaveManagementController.php:244`.

### A125. PATCH /api/school/leaves/{schoolStudentLeaveRequest}

- Route name: `api.school.leaves.update`. Action: `App\Http\Controllers\Api\School\StudentLeaveManagementController@update`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, student_leave_access, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-student-leaves; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: `app/Http/Requests/School/Leaves/UpdateStudentLeaveRequest.php`.
- Request fields from FormRequest: school_student_id, school_leave_type_id, source, start_date, end_date, reason. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:403` / `app/Http/Controllers/Api/School/StudentLeaveManagementController.php:262`.

### A126. POST /api/school/leaves/{schoolStudentLeaveRequest}/approve

- Route name: `api.school.leaves.approve`. Action: `App\Http\Controllers\Api\School\StudentLeaveManagementController@approve`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, student_leave_access, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-student-leaves; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: `app/Http/Requests/School/Leaves/ApproveStudentLeaveRequest.php`.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:404` / `app/Http/Controllers/Api/School/StudentLeaveManagementController.php:281`.

### A127. POST /api/school/leaves/{schoolStudentLeaveRequest}/reject

- Route name: `api.school.leaves.reject`. Action: `App\Http\Controllers\Api\School\StudentLeaveManagementController@reject`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, student_leave_access, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-student-leaves; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: `app/Http/Requests/School/Leaves/RejectStudentLeaveRequest.php`.
- Request fields from FormRequest: reason. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:405` / `app/Http/Controllers/Api/School/StudentLeaveManagementController.php:298`.

### A128. POST /api/school/leaves/{schoolStudentLeaveRequest}/cancel

- Route name: `api.school.leaves.cancel`. Action: `App\Http\Controllers\Api\School\StudentLeaveManagementController@cancel`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, student_leave_access, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-student-leaves; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: `app/Http/Requests/School/Leaves/CancelStudentLeaveRequest.php`.
- Request fields from FormRequest: reason. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): data. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:406` / `app/Http/Controllers/Api/School/StudentLeaveManagementController.php:317`.

### A129. POST /api/school/leaves/{schoolStudentLeaveRequest}/attachments

- Route name: `api.school.leaves.attachments.store`. Action: `App\Http\Controllers\Api\School\StudentLeaveManagementController@storeAttachment`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, student_leave_access, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: manage-student-leaves; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: `app/Http/Requests/School/Leaves/UploadStudentLeaveAttachmentRequest.php`.
- Request fields from FormRequest: file. Inline/direct input fields: file. Other action keys (may be response fields): data, id, file_name, file_path, download_url, mime_type, file_size, uploaded_at. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 201; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:407` / `app/Http/Controllers/Api/School/StudentLeaveManagementController.php:336`.

### A130. GET /api/school/leaves/{schoolStudentLeaveRequest}/attachments/{schoolStudentLeaveAttachment}/download

- Route name: `api.school.leaves.attachments.download`. Action: `App\Http\Controllers\Api\School\StudentLeaveManagementController@downloadAttachment`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, active_school_association, student_leave_access, throttle:api.school`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): Content-Type. Path parameters are required where shown.
- Response: Binary/stream download. Direct status evidence: 200, 403, 404; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:408` / `app/Http/Controllers/Api/School/StudentLeaveManagementController.php:364`.

### A131. GET /api/school/quick-setup/status

- Route name: `api.school.quick_setup.status`. Action: `App\Http\Controllers\Api\School\QuickSetupStatusController@show`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, academic_planning_access, throttle:api.school`.
- Role/permission: Action/FormRequest capability references: school_manager; per-action FormRequest/guards are authoritative.
- Tenant rule: Server-side user.school_id / school_context_id; related-record checks in controller/service.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): total, active, key, label, required, met, linked_to_academic_year, weekly_off_days_count, with_teacher_assignments, published, data, school_id, generated_at, steps, first_incomplete_step_key. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:413` / `app/Http/Controllers/Api/School/QuickSetupStatusController.php:24`.

### A132. GET /staff/subtasks

- Route name: `staff.subtasks.index`. Action: `App\Http\Controllers\Staff\SubtaskController@index`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:staff, active_school_association`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Staff assignment and current school; list missing school filter (finding H02).
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: per_page. Other action keys (may be response fields): per_page, data, pagination, current_page, last_page, total. Path parameters are required where shown.
- Response: JSON {data,...}. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: YES, optional per_page; missing per_page can return all rows.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:455` / `app/Http/Controllers/Staff/SubtaskController.php:19`.

### A133. GET /staff/subtasks/{subtask}

- Route name: `staff.subtasks.show`. Action: `App\Http\Controllers\Staff\SubtaskController@show`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:staff, active_school_association`.
- Role/permission: Action/FormRequest capability references: view; per-action FormRequest/guards are authoritative.
- Tenant rule: Staff assignment and current school; list missing school filter (finding H02).
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): none. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:456` / `app/Http/Controllers/Staff/SubtaskController.php:54`.

### A134. POST /staff/subtasks/{subtask}/reply

- Route name: `staff.subtasks.reply`. Action: `App\Http\Controllers\Staff\SubtaskController@reply`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:staff, active_school_association`.
- Role/permission: Action/FormRequest capability references: reply; per-action FormRequest/guards are authoritative.
- Tenant rule: Staff assignment and current school; list missing school filter (finding H02).
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: message, attachment. Other action keys (may be response fields): message, attachment, attachment.mimetypes, subtask_id, user_id, message_type, school_id, ticket_message_id, attachable_type, attachable_id, module, action_type, uploaded_by, file_name, stored_name, disk, file_path, mime_type, extension, file_size, is_private. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:457` / `app/Http/Controllers/Staff/SubtaskController.php:67`.

### A135. POST /staff/subtasks/{subtask}/submit

- Route name: `staff.subtasks.submit`. Action: `App\Http\Controllers\Staff\SubtaskController@submit`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, role:staff, active_school_association`.
- Role/permission: Action/FormRequest capability references: submit; per-action FormRequest/guards are authoritative.
- Tenant rule: Staff assignment and current school; list missing school filter (finding H02).
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): action. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:458` / `app/Http/Controllers/Staff/SubtaskController.php:113`.

### A136. GET /notifications

- Route name: `notifications.index`. Action: `App\Http\Controllers\NotificationController@index`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Authenticated user ownership.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: limit. Other action keys (may be response fields): limit. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:462` / `app/Http/Controllers/NotificationController.php:11`.

### A137. POST /notifications/{notification}/read

- Route name: `notifications.read`. Action: `App\Http\Controllers\NotificationController@markAsRead`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Authenticated user ownership.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): read_at. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200, 403; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:463` / `app/Http/Controllers/NotificationController.php:25`.

### A138. POST /notifications/read-all

- Route name: `notifications.read_all`. Action: `App\Http\Controllers\NotificationController@markAllAsRead`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Authenticated user ownership.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): read_at, marked_count. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:464` / `app/Http/Controllers/NotificationController.php:34`.

### A139. POST /phone/verification/whatsapp/send

- Route name: `phone.verification.whatsapp.send`. Action: `App\Http\Controllers\WhatsAppPhoneVerificationController@send`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, throttle:api.school`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Authenticated user ownership.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: none extracted; see linked helpers/manual payload handling. Other action keys (may be response fields): message, expires_in_seconds, resend_after_seconds. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:465` / `app/Http/Controllers/WhatsAppPhoneVerificationController.php:11`.

### A140. POST /phone/verification/whatsapp/verify

- Route name: `phone.verification.whatsapp.verify`. Action: `App\Http\Controllers\WhatsAppPhoneVerificationController@verify`.
- Authentication: Required: web session cookie; CSRF for writes. Middleware: `web, auth, throttle:api.school`.
- Role/permission: route middleware and linked policy/FormRequest; no additional literal controller capability found.
- Tenant rule: Authenticated user ownership.
- FormRequest: None; inline/manual validation or no request body.
- Request fields from FormRequest: N/A. Inline/direct input fields: code. Other action keys (may be response fields): code, message, verified. Path parameters are required where shown.
- Response: JSON raw model/array or action-specific object. Direct status evidence: 200; plus applicable common HTTP errors above. Pagination: NO in this action.
- Error format: common errors above; action-specific `message`, `errors`, `impact` or integration `ok/reason` as shown in source.
- MOBILE READY: PARTIAL — session API, native bearer contract missing. Notes/evidence: `routes/web.php:468` / `app/Http/Controllers/WhatsAppPhoneVerificationController.php:22`.

## Appendix B — All application route declarations, including web-only gaps

Every declaration in routes/web.php and routes/auth.php is included; resource routes expanded for the four enabled resource actions. Combined PUT/PATCH is shown as one row. Runtime/vendor routes remain UNCONFIRMED.

| Method | Path | Name | Action | Declared middleware | Evidence |
|---|---|---|---|---|---|
| GET | / | welcome | App\Http\Controllers\WelcomeController@index | web | routes/web.php:57 |
| GET | /p/{slug} | page.show | App\Http\Controllers\PageController@show | web | routes/web.php:58 |
| GET | /pricing | pricing.index | App\Http\Controllers\PricingController@index | web | routes/web.php:59 |
| GET | /plans | plans.index | App\Http\Controllers\PlanController@index | web | routes/web.php:60 |
| GET | /media-files/{path} | public.media_file.show | App\Http\Controllers\PublicStorageController@show | web | routes/web.php:61 |
| GET | /storage/{path} | public.storage.show | App\Http\Controllers\PublicStorageController@show | web | routes/web.php:64 |
| GET | /certificates/verify/{token} | certificates.verify | App\Http\Controllers\CertificateVerificationController@__invoke | web | routes/web.php:67 |
| GET | /webhooks/whatsapp/meta/verify | webhooks.whatsapp.meta.verify | App\Http\Controllers\WhatsAppMetaVerificationController@__invoke | web, throttle:api.school | routes/web.php:70 |
| POST | /webhooks/whatsapp/status | webhooks.whatsapp.status | App\Http\Controllers\WhatsAppStatusCallbackController@__invoke | web, throttle:api.school | routes/web.php:73 |
| POST | /webhooks/whatsapp/dispatch/claim | webhooks.whatsapp.dispatch.claim | App\Http\Controllers\WhatsAppDispatchClaimController@__invoke | web, throttle:api.school | routes/web.php:76 |
| POST | /webhooks/whatsapp/dispatch/result | webhooks.whatsapp.dispatch.result | App\Http\Controllers\WhatsAppDispatchResultController@__invoke | web, throttle:api.school | routes/web.php:79 |
| GET | /admin/users | users.index | App\Http\Controllers\Admin\UserController@index | web, auth, role:super_admin | routes/web.php:84 |
| POST | /admin/users | users.store | App\Http\Controllers\Admin\UserController@store | web, auth, role:super_admin | routes/web.php:84 |
| PUT/PATCH | /admin/users/{user} | users.update | App\Http\Controllers\Admin\UserController@update | web, auth, role:super_admin | routes/web.php:84 |
| DELETE | /admin/users/{user} | users.destroy | App\Http\Controllers\Admin\UserController@destroy | web, auth, role:super_admin | routes/web.php:84 |
| POST | /admin/users/{user}/approve | users.approve | App\Http\Controllers\Admin\UserController@approve | web, auth, role:super_admin | routes/web.php:85 |
| POST | /admin/users/{user}/reject | users.reject | App\Http\Controllers\Admin\UserController@reject | web, auth, role:super_admin | routes/web.php:86 |
| GET | /admin/departments | departments.index | App\Http\Controllers\Admin\DepartmentController@index | web, auth, role:super_admin | routes/web.php:87 |
| POST | /admin/departments | departments.store | App\Http\Controllers\Admin\DepartmentController@store | web, auth, role:super_admin | routes/web.php:87 |
| PUT/PATCH | /admin/departments/{department} | departments.update | App\Http\Controllers\Admin\DepartmentController@update | web, auth, role:super_admin | routes/web.php:87 |
| DELETE | /admin/departments/{department} | departments.destroy | App\Http\Controllers\Admin\DepartmentController@destroy | web, auth, role:super_admin | routes/web.php:87 |
| GET | /admin/roles | roles.index | App\Http\Controllers\Admin\RoleController@index | web, auth, role:super_admin | routes/web.php:88 |
| POST | /admin/roles | roles.store | App\Http\Controllers\Admin\RoleController@store | web, auth, role:super_admin | routes/web.php:88 |
| PUT/PATCH | /admin/roles/{role} | roles.update | App\Http\Controllers\Admin\RoleController@update | web, auth, role:super_admin | routes/web.php:88 |
| DELETE | /admin/roles/{role} | roles.destroy | App\Http\Controllers\Admin\RoleController@destroy | web, auth, role:super_admin | routes/web.php:88 |
| GET | /admin/org-structure-roles | admin.org_structure_roles.index | App\Http\Controllers\Admin\RoleController@indexOrgStructureRoles | web, auth, role:super_admin | routes/web.php:89 |
| POST | /admin/org-structure-roles | admin.org_structure_roles.store | App\Http\Controllers\Admin\RoleController@storeOrgStructureRole | web, auth, role:super_admin | routes/web.php:90 |
| PUT/PATCH | /admin/org-structure-roles/{orgStructureRoleTemplate} | admin.org_structure_roles.update | App\Http\Controllers\Admin\RoleController@updateOrgStructureRole | web, auth, role:super_admin | routes/web.php:91 |
| POST | /admin/org-structure-roles/{orgStructureRoleTemplate}/disable | admin.org_structure_roles.disable | App\Http\Controllers\Admin\RoleController@disableOrgStructureRole | web, auth, role:super_admin | routes/web.php:92 |
| POST | /admin/roles/org-structure | roles.org_structure.store | App\Http\Controllers\Admin\RoleController@storeOrgStructureRole | web, auth, role:super_admin | routes/web.php:93 |
| PUT | /admin/roles/org-structure/{orgStructureRoleTemplate} | roles.org_structure.update | App\Http\Controllers\Admin\RoleController@updateOrgStructureRole | web, auth, role:super_admin | routes/web.php:94 |
| POST | /admin/roles/org-structure/{orgStructureRoleTemplate}/disable | roles.org_structure.disable | App\Http\Controllers\Admin\RoleController@disableOrgStructureRole | web, auth, role:super_admin | routes/web.php:95 |
| POST | /admin/components | admin.components.store | App\Http\Controllers\Admin\PageComponentController@store | web, auth, role:super_admin | routes/web.php:97 |
| PUT | /admin/components/{id} | admin.components.update | App\Http\Controllers\Admin\PageComponentController@update | web, auth, role:super_admin | routes/web.php:98 |
| DELETE | /admin/components/{id} | admin.components.destroy | App\Http\Controllers\Admin\PageComponentController@destroy | web, auth, role:super_admin | routes/web.php:99 |
| POST | /admin/pages | admin.pages.store | App\Http\Controllers\Admin\PageController@store | web, auth, role:super_admin | routes/web.php:101 |
| PUT | /admin/pages/{id} | admin.pages.update | App\Http\Controllers\Admin\PageController@update | web, auth, role:super_admin | routes/web.php:102 |
| DELETE | /admin/pages/{id} | admin.pages.destroy | App\Http\Controllers\Admin\PageController@destroy | web, auth, role:super_admin | routes/web.php:103 |
| GET | /admin/media | admin.media.index | App\Http\Controllers\Admin\MediaController@index | web, auth, role:super_admin | routes/web.php:105 |
| POST | /admin/media | admin.media.store | App\Http\Controllers\Admin\MediaController@store | web, auth, role:super_admin | routes/web.php:106 |
| GET | /admin/media/{media}/preview | admin.media.preview | App\Http\Controllers\Admin\MediaController@preview | web, auth, role:super_admin | routes/web.php:107 |
| DELETE | /admin/media/{id} | admin.media.destroy | App\Http\Controllers\Admin\MediaController@destroy | web, auth, role:super_admin | routes/web.php:108 |
| POST | /admin/header/menu | admin.header.menu.store | App\Http\Controllers\Admin\HeaderController@storeMenu | web, auth, role:super_admin | routes/web.php:110 |
| DELETE | /admin/header/menu/{id} | admin.header.menu.delete | App\Http\Controllers\Admin\HeaderController@destroyMenu | web, auth, role:super_admin | routes/web.php:111 |
| POST | /admin/header/item | admin.header.item.store | App\Http\Controllers\Admin\HeaderController@storeItem | web, auth, role:super_admin | routes/web.php:112 |
| DELETE | /admin/header/item/{id} | admin.header.item.delete | App\Http\Controllers\Admin\HeaderController@destroyItem | web, auth, role:super_admin | routes/web.php:113 |
| GET | /admin/footer | admin.footer.index | App\Http\Controllers\Admin\FooterController@index | web, auth, role:super_admin | routes/web.php:115 |
| POST | /admin/footer/column | admin.footer.column.store | App\Http\Controllers\Admin\FooterController@storeColumn | web, auth, role:super_admin | routes/web.php:116 |
| DELETE | /admin/footer/column/{id} | admin.footer.column.delete | App\Http\Controllers\Admin\FooterController@destroyColumn | web, auth, role:super_admin | routes/web.php:117 |
| POST | /admin/footer/item | admin.footer.item.store | App\Http\Controllers\Admin\FooterController@storeItem | web, auth, role:super_admin | routes/web.php:118 |
| DELETE | /admin/footer/item/{id} | admin.footer.item.delete | App\Http\Controllers\Admin\FooterController@destroyItem | web, auth, role:super_admin | routes/web.php:119 |
| GET | /admin/settings | admin.settings.index | App\Http\Controllers\Admin\SettingsController@index | web, auth, role:super_admin | routes/web.php:121 |
| POST | /admin/settings | admin.settings.update | App\Http\Controllers\Admin\SettingsController@update | web, auth, role:super_admin | routes/web.php:122 |
| PUT | /admin/schools/{id} | admin.schools.update | App\Http\Controllers\Admin\SchoolManagementController@updateSchool | web, auth, role:super_admin | routes/web.php:124 |
| GET | /admin/schools | admin.schools.index | App\Http\Controllers\Admin\SchoolManagementController@index | web, auth, role:super_admin | routes/web.php:125 |
| GET | /admin/school-defaults | admin.school_defaults.index | App\Http\Controllers\Admin\SchoolDefaultDataController@index | web, auth, role:super_admin | routes/web.php:126 |
| GET | /admin/school-defaults/country-reference | admin.school_defaults.country_reference | App\Http\Controllers\Admin\SchoolDefaultDataController@countryReference | web, auth, role:super_admin | routes/web.php:127 |
| GET | /admin/school-defaults/reference/holidays/preview | admin.school_defaults.reference.holidays.preview | App\Http\Controllers\Admin\SchoolDefaultDataController@previewReferenceHolidays | web, auth, role:super_admin | routes/web.php:128 |
| POST | /admin/school-defaults/reference/holidays/import | admin.school_defaults.reference.holidays.import | App\Http\Controllers\Admin\SchoolDefaultDataController@importReferenceHolidays | web, auth, role:super_admin | routes/web.php:129 |
| POST | /admin/school-defaults/scopes | admin.school_defaults.scopes.store | App\Http\Controllers\Admin\SchoolDefaultDataController@storeScopeConfig | web, auth, role:super_admin | routes/web.php:130 |
| DELETE | /admin/school-defaults/scopes/{country}/{educationType} | admin.school_defaults.scopes.destroy | App\Http\Controllers\Admin\SchoolDefaultDataController@destroyScope | web, auth, role:super_admin | routes/web.php:131 |
| POST | /admin/school-defaults/stages | admin.school_defaults.stages.store | App\Http\Controllers\Admin\SchoolDefaultDataController@storeStage | web, auth, role:super_admin | routes/web.php:132 |
| PUT | /admin/school-defaults/stages/{schoolDefaultStageTemplate} | admin.school_defaults.stages.update | App\Http\Controllers\Admin\SchoolDefaultDataController@updateStage | web, auth, role:super_admin | routes/web.php:133 |
| DELETE | /admin/school-defaults/stages/{schoolDefaultStageTemplate} | admin.school_defaults.stages.destroy | App\Http\Controllers\Admin\SchoolDefaultDataController@destroyStage | web, auth, role:super_admin | routes/web.php:134 |
| POST | /admin/school-defaults/stage-terms | admin.school_defaults.stage_terms.store | App\Http\Controllers\Admin\SchoolDefaultDataController@storeStageTerm | web, auth, role:super_admin | routes/web.php:135 |
| PUT | /admin/school-defaults/stage-terms/{schoolDefaultStageTermTemplate} | admin.school_defaults.stage_terms.update | App\Http\Controllers\Admin\SchoolDefaultDataController@updateStageTerm | web, auth, role:super_admin | routes/web.php:136 |
| DELETE | /admin/school-defaults/stage-terms/{schoolDefaultStageTermTemplate} | admin.school_defaults.stage_terms.destroy | App\Http\Controllers\Admin\SchoolDefaultDataController@destroyStageTerm | web, auth, role:super_admin | routes/web.php:137 |
| POST | /admin/school-defaults/stage-grades | admin.school_defaults.stage_grades.store | App\Http\Controllers\Admin\SchoolDefaultDataController@storeStageGrade | web, auth, role:super_admin | routes/web.php:138 |
| PUT | /admin/school-defaults/stage-grades/{schoolDefaultStageGradeTemplate} | admin.school_defaults.stage_grades.update | App\Http\Controllers\Admin\SchoolDefaultDataController@updateStageGrade | web, auth, role:super_admin | routes/web.php:139 |
| DELETE | /admin/school-defaults/stage-grades/{schoolDefaultStageGradeTemplate} | admin.school_defaults.stage_grades.destroy | App\Http\Controllers\Admin\SchoolDefaultDataController@destroyStageGrade | web, auth, role:super_admin | routes/web.php:140 |
| POST | /admin/school-defaults/stage-grade-terms | admin.school_defaults.stage_grade_terms.store | App\Http\Controllers\Admin\SchoolDefaultDataController@storeStageGradeTerm | web, auth, role:super_admin | routes/web.php:141 |
| PUT | /admin/school-defaults/stage-grade-terms/{gradeTermTemplate} | admin.school_defaults.stage_grade_terms.update | App\Http\Controllers\Admin\SchoolDefaultDataController@updateStageGradeTerm | web, auth, role:super_admin | routes/web.php:142 |
| DELETE | /admin/school-defaults/stage-grade-terms/{gradeTermTemplate} | admin.school_defaults.stage_grade_terms.destroy | App\Http\Controllers\Admin\SchoolDefaultDataController@destroyStageGradeTerm | web, auth, role:super_admin | routes/web.php:143 |
| POST | /admin/school-defaults/classrooms | admin.school_defaults.classrooms.store | App\Http\Controllers\Admin\SchoolDefaultDataController@storeClassroom | web, auth, role:super_admin | routes/web.php:144 |
| PUT | /admin/school-defaults/classrooms/{schoolDefaultClassroomTemplate} | admin.school_defaults.classrooms.update | App\Http\Controllers\Admin\SchoolDefaultDataController@updateClassroom | web, auth, role:super_admin | routes/web.php:145 |
| DELETE | /admin/school-defaults/classrooms/{schoolDefaultClassroomTemplate} | admin.school_defaults.classrooms.destroy | App\Http\Controllers\Admin\SchoolDefaultDataController@destroyClassroom | web, auth, role:super_admin | routes/web.php:146 |
| POST | /admin/school-defaults/academic-years | admin.school_defaults.academic_years.store | App\Http\Controllers\Admin\SchoolDefaultDataController@storeAcademicYear | web, auth, role:super_admin | routes/web.php:147 |
| PUT | /admin/school-defaults/academic-years/{yearTemplate} | admin.school_defaults.academic_years.update | App\Http\Controllers\Admin\SchoolDefaultDataController@updateAcademicYear | web, auth, role:super_admin | routes/web.php:148 |
| DELETE | /admin/school-defaults/academic-years/{yearTemplate} | admin.school_defaults.academic_years.destroy | App\Http\Controllers\Admin\SchoolDefaultDataController@destroyAcademicYear | web, auth, role:super_admin | routes/web.php:149 |
| POST | /admin/school-defaults/holidays | admin.school_defaults.holidays.store | App\Http\Controllers\Admin\SchoolDefaultDataController@storeHoliday | web, auth, role:super_admin | routes/web.php:150 |
| PUT | /admin/school-defaults/holidays/{schoolDefaultHolidayTemplate} | admin.school_defaults.holidays.update | App\Http\Controllers\Admin\SchoolDefaultDataController@updateHoliday | web, auth, role:super_admin | routes/web.php:151 |
| DELETE | /admin/school-defaults/holidays/{schoolDefaultHolidayTemplate} | admin.school_defaults.holidays.destroy | App\Http\Controllers\Admin\SchoolDefaultDataController@destroyHoliday | web, auth, role:super_admin | routes/web.php:152 |
| POST | /admin/school-defaults/leave-types | admin.school_defaults.leave_types.store | App\Http\Controllers\Admin\SchoolDefaultDataController@storeLeaveType | web, auth, role:super_admin | routes/web.php:153 |
| PUT | /admin/school-defaults/leave-types/{schoolDefaultLeaveTypeTemplate} | admin.school_defaults.leave_types.update | App\Http\Controllers\Admin\SchoolDefaultDataController@updateLeaveType | web, auth, role:super_admin | routes/web.php:154 |
| DELETE | /admin/school-defaults/leave-types/{schoolDefaultLeaveTypeTemplate} | admin.school_defaults.leave_types.destroy | App\Http\Controllers\Admin\SchoolDefaultDataController@destroyLeaveType | web, auth, role:super_admin | routes/web.php:155 |
| POST | /admin/school-defaults/subjects | admin.school_defaults.subjects.store | App\Http\Controllers\Admin\SchoolDefaultDataController@storeSubject | web, auth, role:super_admin | routes/web.php:156 |
| PUT | /admin/school-defaults/subjects/{schoolDefaultSubjectTemplate} | admin.school_defaults.subjects.update | App\Http\Controllers\Admin\SchoolDefaultDataController@updateSubject | web, auth, role:super_admin | routes/web.php:157 |
| DELETE | /admin/school-defaults/subjects/{schoolDefaultSubjectTemplate} | admin.school_defaults.subjects.destroy | App\Http\Controllers\Admin\SchoolDefaultDataController@destroySubject | web, auth, role:super_admin | routes/web.php:158 |
| POST | /admin/countries/sync-global | admin.countries.sync_global | App\Http\Controllers\Admin\SchoolManagementController@syncCountriesFromGlobalApi | web, auth, role:super_admin | routes/web.php:159 |
| POST | /admin/countries | admin.countries.store | App\Http\Controllers\Admin\SchoolManagementController@storeCountry | web, auth, role:super_admin | routes/web.php:160 |
| PUT | /admin/countries/{id} | admin.countries.update | App\Http\Controllers\Admin\SchoolManagementController@updateCountry | web, auth, role:super_admin | routes/web.php:161 |
| DELETE | /admin/countries/{id} | admin.countries.delete | App\Http\Controllers\Admin\SchoolManagementController@destroyCountry | web, auth, role:super_admin | routes/web.php:162 |
| POST | /admin/governorates/sync-global | admin.governorates.sync_global | App\Http\Controllers\Admin\SchoolManagementController@syncGovernoratesFromGlobalApi | web, auth, role:super_admin | routes/web.php:163 |
| POST | /admin/governorates | admin.governorates.store | App\Http\Controllers\Admin\SchoolManagementController@storeGovernorate | web, auth, role:super_admin | routes/web.php:164 |
| PUT | /admin/governorates/{id} | admin.governorates.update | App\Http\Controllers\Admin\SchoolManagementController@updateGovernorate | web, auth, role:super_admin | routes/web.php:165 |
| DELETE | /admin/governorates/{id} | admin.governorates.delete | App\Http\Controllers\Admin\SchoolManagementController@destroyGovernorate | web, auth, role:super_admin | routes/web.php:166 |
| POST | /admin/education-types | admin.education_types.store | App\Http\Controllers\Admin\SchoolManagementController@storeEducationType | web, auth, role:super_admin | routes/web.php:167 |
| PUT | /admin/education-types/{id} | admin.education_types.update | App\Http\Controllers\Admin\SchoolManagementController@updateEducationType | web, auth, role:super_admin | routes/web.php:168 |
| DELETE | /admin/education-types/{id} | admin.education_types.delete | App\Http\Controllers\Admin\SchoolManagementController@destroyEducationType | web, auth, role:super_admin | routes/web.php:169 |
| POST | /admin/education-stages | admin.education_stages.store | App\Http\Controllers\Admin\SchoolManagementController@storeEducationStage | web, auth, role:super_admin | routes/web.php:170 |
| PUT | /admin/education-stages/{id} | admin.education_stages.update | App\Http\Controllers\Admin\SchoolManagementController@updateEducationStage | web, auth, role:super_admin | routes/web.php:171 |
| DELETE | /admin/education-stages/{id} | admin.education_stages.delete | App\Http\Controllers\Admin\SchoolManagementController@destroyEducationStage | web, auth, role:super_admin | routes/web.php:172 |
| POST | /admin/directorates | admin.directorates.store | App\Http\Controllers\Admin\SchoolManagementController@storeDirectorate | web, auth, role:super_admin | routes/web.php:173 |
| PUT | /admin/directorates/{id} | admin.directorates.update | App\Http\Controllers\Admin\SchoolManagementController@updateDirectorate | web, auth, role:super_admin | routes/web.php:174 |
| DELETE | /admin/directorates/{id} | admin.directorates.delete | App\Http\Controllers\Admin\SchoolManagementController@destroyDirectorate | web, auth, role:super_admin | routes/web.php:175 |
| POST | /admin/schools | admin.schools.store | App\Http\Controllers\Admin\SchoolManagementController@storeSchool | web, auth, role:super_admin | routes/web.php:176 |
| DELETE | /admin/schools/{id} | admin.schools.delete | App\Http\Controllers\Admin\SchoolManagementController@destroySchool | web, auth, role:super_admin | routes/web.php:177 |
| GET | /admin/supervisor-assignments | admin.supervisor_assignments.index | App\Http\Controllers\Admin\SupervisorAssignmentController@index | web, auth, role:super_admin | routes/web.php:179 |
| POST | /admin/supervisor-assignments | admin.supervisor_assignments.store | App\Http\Controllers\Admin\SupervisorAssignmentController@store | web, auth, role:super_admin | routes/web.php:180 |
| DELETE | /admin/supervisor-assignments/{supervisorAssignment} | admin.supervisor_assignments.destroy | App\Http\Controllers\Admin\SupervisorAssignmentController@destroy | web, auth, role:super_admin | routes/web.php:181 |
| GET | /admin/plans | admin.plans.index | App\Http\Controllers\Admin\PlanController@index | web, auth, role:super_admin | routes/web.php:183 |
| POST | /admin/plans | admin.plans.store | App\Http\Controllers\Admin\PlanController@store | web, auth, role:super_admin | routes/web.php:184 |
| POST | /admin/plans/{plan}/freeze | admin.plans.freeze | App\Http\Controllers\Admin\PlanController@freeze | web, auth, role:super_admin | routes/web.php:185 |
| POST | /admin/plans/{plan}/activate | admin.plans.activate | App\Http\Controllers\Admin\PlanController@activate | web, auth, role:super_admin | routes/web.php:186 |
| PUT | /admin/plans/{plan} | admin.plans.update | App\Http\Controllers\Admin\PlanController@update | web, auth, role:super_admin | routes/web.php:187 |
| DELETE | /admin/plans/{plan} | admin.plans.destroy | App\Http\Controllers\Admin\PlanController@destroy | web, auth, role:super_admin | routes/web.php:188 |
| POST | /admin/subscriptions/{subscription}/activate | admin.subscriptions.activate | App\Http\Controllers\Admin\SubscriptionManagementController@activate | web, auth, role:super_admin | routes/web.php:190 |
| POST | /admin/subscriptions/{subscription}/freeze | admin.subscriptions.freeze | App\Http\Controllers\Admin\SubscriptionManagementController@freeze | web, auth, role:super_admin | routes/web.php:191 |
| POST | /admin/subscriptions/{subscription}/cancel | admin.subscriptions.cancel | App\Http\Controllers\Admin\SubscriptionManagementController@cancel | web, auth, role:super_admin | routes/web.php:192 |
| DELETE | /admin/subscriptions/{subscription} | admin.subscriptions.destroy | App\Http\Controllers\Admin\SubscriptionManagementController@destroy | web, auth, role:super_admin | routes/web.php:193 |
| GET | /admin/dashboard | admin.dashboard | App\Http\Controllers\Admin\DashboardController@index | web, auth, role:super_admin | routes/web.php:195 |
| GET | /api/system/permissions | api.system.permissions.index | App\Http\Controllers\Api\System\SystemPermissionController@index | web, auth, role:super_admin, throttle:api.system | routes/web.php:199 |
| POST | /api/system/permissions | api.system.permissions.store | App\Http\Controllers\Api\System\SystemPermissionController@store | web, auth, role:super_admin, throttle:api.system | routes/web.php:200 |
| GET | /api/system/roles | api.system.roles.index | App\Http\Controllers\Api\System\SystemRoleController@index | web, auth, role:super_admin, throttle:api.system | routes/web.php:202 |
| POST | /api/system/roles | api.system.roles.store | App\Http\Controllers\Api\System\SystemRoleController@store | web, auth, role:super_admin, throttle:api.system | routes/web.php:203 |
| GET | /api/system/roles/{role} | api.system.roles.show | App\Http\Controllers\Api\System\SystemRoleController@show | web, auth, role:super_admin, throttle:api.system | routes/web.php:204 |
| PUT | /api/system/roles/{role} | api.system.roles.update | App\Http\Controllers\Api\System\SystemRoleController@update | web, auth, role:super_admin, throttle:api.system | routes/web.php:205 |
| DELETE | /api/system/roles/{role} | api.system.roles.destroy | App\Http\Controllers\Api\System\SystemRoleController@destroy | web, auth, role:super_admin, throttle:api.system | routes/web.php:206 |
| PUT | /api/system/roles/{role}/permissions | api.system.roles.permissions.sync | App\Http\Controllers\Api\System\SystemRoleController@syncPermissions | web, auth, role:super_admin, throttle:api.system | routes/web.php:207 |
| GET | /api/school/roles/assignable | api.school.roles.assignable | App\Http\Controllers\Api\School\SchoolAssignableRoleController@index | web, auth, role:school_manager, managed_school, active_school_association, throttle:api.school | routes/web.php:211 |
| GET | /api/school/org-structure-roles | api.school.org_structure_roles.index | App\Http\Controllers\Api\School\SchoolOrgStructureRoleController@index | web, auth, role:school_manager, managed_school, active_school_association, throttle:api.school | routes/web.php:212 |
| GET | /api/school/permission-groups | api.school.permission_groups.index | App\Http\Controllers\Api\School\SchoolPermissionGroupController@index | web, auth, role:school_manager, managed_school, active_school_association, throttle:api.school | routes/web.php:213 |
| POST | /api/school/permission-groups | api.school.permission_groups.store | App\Http\Controllers\Api\School\SchoolPermissionGroupController@store | web, auth, role:school_manager, managed_school, active_school_association, throttle:api.school | routes/web.php:214 |
| PUT | /api/school/permission-groups/{schoolPermissionGroup} | api.school.permission_groups.update | App\Http\Controllers\Api\School\SchoolPermissionGroupController@update | web, auth, role:school_manager, managed_school, active_school_association, throttle:api.school | routes/web.php:215 |
| DELETE | /api/school/permission-groups/{schoolPermissionGroup} | api.school.permission_groups.destroy | App\Http\Controllers\Api\School\SchoolPermissionGroupController@destroy | web, auth, role:school_manager, managed_school, active_school_association, throttle:api.school | routes/web.php:216 |
| GET | /api/school/users | api.school.users.index | App\Http\Controllers\Api\School\SchoolUserManagementController@index | web, auth, role:school_manager, managed_school, active_school_association, throttle:api.school | routes/web.php:218 |
| POST | /api/school/users | api.school.users.store | App\Http\Controllers\Api\School\SchoolUserManagementController@store | web, auth, role:school_manager, managed_school, active_school_association, throttle:api.school | routes/web.php:219 |
| PUT | /api/school/users/{user} | api.school.users.update | App\Http\Controllers\Api\School\SchoolUserManagementController@update | web, auth, role:school_manager, managed_school, active_school_association, throttle:api.school | routes/web.php:220 |
| PUT | /api/school/users/{user}/roles | api.school.users.roles.sync | App\Http\Controllers\Api\School\SchoolUserManagementController@syncRoles | web, auth, role:school_manager, managed_school, active_school_association, throttle:api.school | routes/web.php:221 |
| GET | /supervisor/onboarding | supervisor.onboarding.show | App\Http\Controllers\Supervisor\OnboardingController@show | web, auth, role:supervisor | routes/web.php:225 |
| GET | /supervisor/onboarding/regions | supervisor.onboarding.regions | App\Http\Controllers\Supervisor\OnboardingController@regions | web, auth, role:supervisor | routes/web.php:226 |
| GET | /supervisor/onboarding/location-schools | supervisor.onboarding.location_schools | App\Http\Controllers\Supervisor\OnboardingController@locationSchools | web, auth, role:supervisor | routes/web.php:227 |
| GET | /supervisor/onboarding/regions/{region}/schools | supervisor.onboarding.schools | App\Http\Controllers\Supervisor\OnboardingController@schools | web, auth, role:supervisor | routes/web.php:228 |
| POST | /supervisor/onboarding/select | supervisor.onboarding.select | App\Http\Controllers\Supervisor\OnboardingController@select | web, auth, role:supervisor | routes/web.php:229 |
| GET | /supervisor/requests/inbox | supervisor.requests.page | App\Http\Controllers\Supervisor\RequestController@page | web, auth, role:supervisor | routes/web.php:231 |
| GET | /supervisor/requests | supervisor.requests.index | App\Http\Controllers\Supervisor\RequestController@index | web, auth, role:supervisor | routes/web.php:232 |
| POST | /supervisor/requests/{schoolSupervisionRequest}/confirm | supervisor.requests.confirm | App\Http\Controllers\Supervisor\RequestController@confirm | web, auth, role:supervisor | routes/web.php:233 |
| POST | /supervisor/requests/{schoolSupervisionRequest}/cancel | supervisor.requests.cancel | App\Http\Controllers\Supervisor\RequestController@cancel | web, auth, role:supervisor | routes/web.php:234 |
| GET | /supervisor/dashboard | supervisor.dashboard | App\Http\Controllers\Supervisor\DashboardController@index | web, auth, role:supervisor | routes/web.php:236 |
| GET | /supervisor/tickets | supervisor.tickets.index | App\Http\Controllers\Supervisor\TicketController@index | web, auth, role:supervisor | routes/web.php:238 |
| POST | /supervisor/tickets | supervisor.tickets.store | App\Http\Controllers\Supervisor\TicketController@store | web, auth, role:supervisor | routes/web.php:239 |
| GET | /supervisor/tickets/{ticket} | supervisor.tickets.show | App\Http\Controllers\Supervisor\TicketController@show | web, auth, role:supervisor | routes/web.php:240 |
| PUT | /supervisor/tickets/{ticket} | supervisor.tickets.update | App\Http\Controllers\Supervisor\TicketController@update | web, auth, role:supervisor | routes/web.php:241 |
| POST | /supervisor/tickets/{ticket}/close | supervisor.tickets.close | App\Http\Controllers\Supervisor\TicketController@close | web, auth, role:supervisor | routes/web.php:242 |
| GET | /manager/onboarding | manager.onboarding.show | App\Http\Controllers\Manager\OnboardingController@show | web, auth, role:school_manager | routes/web.php:246 |
| GET | /manager/onboarding/regions | manager.onboarding.regions | App\Http\Controllers\Manager\OnboardingController@regions | web, auth, role:school_manager | routes/web.php:247 |
| GET | /manager/onboarding/templates | manager.onboarding.templates | App\Http\Controllers\Manager\OnboardingController@templates | web, auth, role:school_manager | routes/web.php:248 |
| GET | /manager/onboarding/governorates | manager.onboarding.governorates | App\Http\Controllers\Manager\OnboardingController@governorates | web, auth, role:school_manager | routes/web.php:249 |
| GET | /manager/onboarding/regions/{region}/schools | manager.onboarding.schools | App\Http\Controllers\Manager\OnboardingController@schools | web, auth, role:school_manager | routes/web.php:250 |
| POST | /manager/onboarding/select | manager.onboarding.select | App\Http\Controllers\Manager\OnboardingController@select | web, auth, role:school_manager | routes/web.php:251 |
| POST | /manager/onboarding/schools | manager.onboarding.schools.store | App\Http\Controllers\Manager\OnboardingController@storeSchool | web, auth, role:school_manager | routes/web.php:252 |
| PUT | /manager/onboarding/schools/{school} | manager.onboarding.schools.update | App\Http\Controllers\Manager\OnboardingController@updateSchool | web, auth, role:school_manager | routes/web.php:253 |
| GET | /manager/requests/inbox | manager.requests.page | App\Http\Controllers\Manager\RequestController@page | web, auth, role:school_manager, managed_school | routes/web.php:256 |
| GET | /manager/requests | manager.requests.index | App\Http\Controllers\Manager\RequestController@index | web, auth, role:school_manager, managed_school | routes/web.php:257 |
| POST | /manager/requests/{schoolSupervisionRequest}/approve | manager.requests.approve | App\Http\Controllers\Manager\RequestController@approve | web, auth, role:school_manager, managed_school | routes/web.php:258 |
| POST | /manager/requests/{schoolSupervisionRequest}/reject | manager.requests.reject | App\Http\Controllers\Manager\RequestController@reject | web, auth, role:school_manager, managed_school | routes/web.php:259 |
| GET | /association-requests | association_requests.index | App\Http\Controllers\AssociationRequestController@index | web, auth, role:school_manager, managed_school | routes/web.php:261 |
| POST | /association-requests/{associationRequest}/approve | association_requests.approve | App\Http\Controllers\AssociationRequestController@approve | web, auth, role:school_manager, managed_school | routes/web.php:262 |
| POST | /association-requests/{associationRequest}/reject | association_requests.reject | App\Http\Controllers\AssociationRequestController@reject | web, auth, role:school_manager, managed_school | routes/web.php:263 |
| GET | /manager/structure | manager.structure.index | App\Http\Controllers\Manager\SchoolStructureController@index | web, auth, role:school_manager, managed_school, active_school_association | routes/web.php:266 |
| POST | /manager/structure/departments | manager.structure.departments.store | App\Http\Controllers\Manager\DepartmentController@store | web, auth, role:school_manager, managed_school, active_school_association | routes/web.php:267 |
| PUT | /manager/structure/departments/{department} | manager.structure.departments.update | App\Http\Controllers\Manager\DepartmentController@update | web, auth, role:school_manager, managed_school, active_school_association | routes/web.php:268 |
| DELETE | /manager/structure/departments/{department} | manager.structure.departments.destroy | App\Http\Controllers\Manager\DepartmentController@destroy | web, auth, role:school_manager, managed_school, active_school_association | routes/web.php:269 |
| POST | /manager/structure/users | manager.structure.users.store | App\Http\Controllers\Manager\SchoolUserController@store | web, auth, role:school_manager, managed_school, active_school_association | routes/web.php:270 |
| PUT | /manager/structure/users/{user} | manager.structure.users.update | App\Http\Controllers\Manager\SchoolUserController@update | web, auth, role:school_manager, managed_school, active_school_association | routes/web.php:271 |
| DELETE | /manager/structure/users/{user} | manager.structure.users.destroy | App\Http\Controllers\Manager\SchoolUserController@destroy | web, auth, role:school_manager, managed_school, active_school_association | routes/web.php:272 |
| GET | /manager/dashboard | manager.dashboard | App\Http\Controllers\Manager\DashboardController@index | web, auth, role:school_manager, managed_school, active_school_association | routes/web.php:274 |
| GET | /manager/tickets | manager.tickets.index | App\Http\Controllers\Manager\TicketController@index | web, auth, role:school_manager, managed_school, active_school_association | routes/web.php:276 |
| POST | /manager/tickets | manager.tickets.store | App\Http\Controllers\Manager\TicketController@store | web, auth, role:school_manager, managed_school, active_school_association | routes/web.php:277 |
| GET | /manager/tickets/{ticket} | manager.tickets.show | App\Http\Controllers\Manager\TicketController@show | web, auth, role:school_manager, managed_school, active_school_association | routes/web.php:278 |
| POST | /manager/tickets/{ticket}/final-report | manager.tickets.final_report | App\Http\Controllers\Manager\TicketController@finalReport | web, auth, role:school_manager, managed_school, active_school_association | routes/web.php:279 |
| POST | /manager/tickets/{ticket}/close | manager.tickets.close | App\Http\Controllers\Manager\TicketController@close | web, auth, role:school_manager, managed_school, active_school_association | routes/web.php:280 |
| POST | /manager/subtasks | manager.subtasks.store | App\Http\Controllers\Manager\SubtaskController@store | web, auth, role:school_manager, managed_school, active_school_association | routes/web.php:282 |
| PUT | /manager/subtasks/{subtask} | manager.subtasks.update | App\Http\Controllers\Manager\SubtaskController@update | web, auth, role:school_manager, managed_school, active_school_association | routes/web.php:283 |
| POST | /manager/subtasks/{subtask}/approve | manager.subtasks.approve | App\Http\Controllers\Manager\SubtaskController@approve | web, auth, role:school_manager, managed_school, active_school_association | routes/web.php:284 |
| GET | /school/student-structure | school.student_structure.index | App\Http\Controllers\School\StudentStructureController@index | web, auth, active_school_association, student_structure_access | routes/web.php:290 |
| POST | /school/student-structure/stages | school.student_structure.stages.store | App\Http\Controllers\School\StudentStructureController@storeStage | web, auth, active_school_association, student_structure_access | routes/web.php:292 |
| PUT | /school/student-structure/stages/{schoolStage} | school.student_structure.stages.update | App\Http\Controllers\School\StudentStructureController@updateStage | web, auth, active_school_association, student_structure_access | routes/web.php:293 |
| DELETE | /school/student-structure/stages/{schoolStage} | school.student_structure.stages.destroy | App\Http\Controllers\School\StudentStructureController@destroyStage | web, auth, active_school_association, student_structure_access | routes/web.php:294 |
| POST | /school/student-structure/stage-terms | school.student_structure.stage_terms.store | App\Http\Controllers\School\StudentStructureController@storeStageTerm | web, auth, active_school_association, student_structure_access | routes/web.php:295 |
| PUT | /school/student-structure/stage-terms/{schoolStageTerm} | school.student_structure.stage_terms.update | App\Http\Controllers\School\StudentStructureController@updateStageTerm | web, auth, active_school_association, student_structure_access | routes/web.php:296 |
| DELETE | /school/student-structure/stage-terms/{schoolStageTerm} | school.student_structure.stage_terms.destroy | App\Http\Controllers\School\StudentStructureController@destroyStageTerm | web, auth, active_school_association, student_structure_access | routes/web.php:297 |
| POST | /school/student-structure/stage-grades | school.student_structure.stage_grades.store | App\Http\Controllers\School\StudentStructureController@storeStageGrade | web, auth, active_school_association, student_structure_access | routes/web.php:298 |
| PUT | /school/student-structure/stage-grades/{schoolStageGrade} | school.student_structure.stage_grades.update | App\Http\Controllers\School\StudentStructureController@updateStageGrade | web, auth, active_school_association, student_structure_access | routes/web.php:299 |
| DELETE | /school/student-structure/stage-grades/{schoolStageGrade} | school.student_structure.stage_grades.destroy | App\Http\Controllers\School\StudentStructureController@destroyStageGrade | web, auth, active_school_association, student_structure_access | routes/web.php:300 |
| POST | /school/student-structure/stage-grade-terms | school.student_structure.stage_grade_terms.store | App\Http\Controllers\School\StudentStructureController@storeStageGradeTerm | web, auth, active_school_association, student_structure_access | routes/web.php:301 |
| PUT | /school/student-structure/stage-grade-terms/{schoolStageGradeTerm} | school.student_structure.stage_grade_terms.update | App\Http\Controllers\School\StudentStructureController@updateStageGradeTerm | web, auth, active_school_association, student_structure_access | routes/web.php:302 |
| DELETE | /school/student-structure/stage-grade-terms/{schoolStageGradeTerm} | school.student_structure.stage_grade_terms.destroy | App\Http\Controllers\School\StudentStructureController@destroyStageGradeTerm | web, auth, active_school_association, student_structure_access | routes/web.php:303 |
| POST | /school/student-structure/classrooms | school.student_structure.classrooms.store | App\Http\Controllers\School\StudentStructureController@storeClassroom | web, auth, active_school_association, student_structure_access | routes/web.php:305 |
| PUT | /school/student-structure/classrooms/{schoolClassroom} | school.student_structure.classrooms.update | App\Http\Controllers\School\StudentStructureController@updateClassroom | web, auth, active_school_association, student_structure_access | routes/web.php:306 |
| DELETE | /school/student-structure/classrooms/{schoolClassroom} | school.student_structure.classrooms.destroy | App\Http\Controllers\School\StudentStructureController@destroyClassroom | web, auth, active_school_association, student_structure_access | routes/web.php:307 |
| GET | /school/student-structure/students/import-template | school.student_structure.students.import_template | App\Http\Controllers\School\StudentStructureController@downloadStudentImportTemplate | web, auth, active_school_association, student_structure_access | routes/web.php:309 |
| POST | /school/student-structure/students/import | school.student_structure.students.import | App\Http\Controllers\School\StudentStructureController@importStudents | web, auth, active_school_association, student_structure_access | routes/web.php:310 |
| POST | /school/student-structure/students | school.student_structure.students.store | App\Http\Controllers\School\StudentStructureController@storeStudent | web, auth, active_school_association, student_structure_access | routes/web.php:311 |
| PUT | /school/student-structure/students/{schoolStudent} | school.student_structure.students.update | App\Http\Controllers\School\StudentStructureController@updateStudent | web, auth, active_school_association, student_structure_access | routes/web.php:312 |
| DELETE | /school/student-structure/students/{schoolStudent} | school.student_structure.students.destroy | App\Http\Controllers\School\StudentStructureController@destroyStudent | web, auth, active_school_association, student_structure_access | routes/web.php:313 |
| POST | /school/default-data/import | school.default_data.import | App\Http\Controllers\School\DefaultDataImportController@store | web, auth, active_school_association, throttle:api.school | routes/web.php:317 |
| GET | /school/student-attendance | school.student_attendance.index | App\Http\Controllers\School\StudentAttendanceController@index | web, auth, active_school_association, student_attendance_access | routes/web.php:321 |
| POST | /school/student-attendance/records | school.student_attendance.records.upsert | App\Http\Controllers\School\StudentAttendanceController@upsertRecords | web, auth, active_school_association, student_attendance_access | routes/web.php:322 |
| POST | /school/student-attendance/attachments | school.student_attendance.attachments.store | App\Http\Controllers\School\StudentAttendanceController@storeAttachments | web, auth, active_school_association, student_attendance_access | routes/web.php:323 |
| GET | /school/student-attendance/attachments/{schoolAttendanceAttachment}/download | school.student_attendance.attachments.download | App\Http\Controllers\School\StudentAttendanceController@downloadAttachment | web, auth, active_school_association, student_attendance_access | routes/web.php:324 |
| DELETE | /school/student-attendance/attachments/{schoolAttendanceAttachment} | school.student_attendance.attachments.destroy | App\Http\Controllers\School\StudentAttendanceController@destroyAttachment | web, auth, active_school_association, student_attendance_access | routes/web.php:328 |
| GET | /school/student-attendance/report/export | school.student_attendance.report.export | App\Http\Controllers\School\StudentAttendanceController@exportReportCsv | web, auth, active_school_association, student_attendance_access | routes/web.php:332 |
| GET | /school/attachments/{attachment}/download | school.attachments.download | App\Http\Controllers\School\SchoolAttachmentController@download | web, auth, active_school_association | routes/web.php:336 |
| DELETE | /school/attachments/{attachment} | school.attachments.destroy | App\Http\Controllers\School\SchoolAttachmentController@destroy | web, auth, active_school_association | routes/web.php:338 |
| GET | /school/reports | school.reports.index | App\Http\Controllers\School\ReportsController@index | web, auth, active_school_association, school_reports_access | routes/web.php:343 |
| GET | /school/reports/export | school.reports.export | App\Http\Controllers\School\ReportsController@export | web, auth, active_school_association, school_reports_access | routes/web.php:344 |
| GET | /school/certificates | school.certificates.index | App\Http\Controllers\School\StudentCertificateController@index | web, auth, active_school_association, school_certificates_access | routes/web.php:348 |
| POST | /school/certificates/templates | school.certificates.templates.store | App\Http\Controllers\School\StudentCertificateController@storeTemplate | web, auth, active_school_association, school_certificates_access | routes/web.php:349 |
| PUT | /school/certificates/templates/{certificateTemplate} | school.certificates.templates.update | App\Http\Controllers\School\StudentCertificateController@updateTemplate | web, auth, active_school_association, school_certificates_access | routes/web.php:350 |
| DELETE | /school/certificates/templates/{certificateTemplate} | school.certificates.templates.destroy | App\Http\Controllers\School\StudentCertificateController@destroyTemplate | web, auth, active_school_association, school_certificates_access | routes/web.php:351 |
| POST | /school/certificates/signatures | school.certificates.signatures.store | App\Http\Controllers\School\StudentCertificateController@storeSignature | web, auth, active_school_association, school_certificates_access | routes/web.php:352 |
| DELETE | /school/certificates/signatures/{schoolCertificateSignature} | school.certificates.signatures.destroy | App\Http\Controllers\School\StudentCertificateController@destroySignature | web, auth, active_school_association, school_certificates_access | routes/web.php:353 |
| POST | /school/certificates/issue | school.certificates.issue | App\Http\Controllers\School\StudentCertificateController@issue | web, auth, active_school_association, school_certificates_access | routes/web.php:354 |
| GET | /school/certificates/{studentCertificate}/print | school.certificates.print | App\Http\Controllers\School\StudentCertificateController@print | web, auth, active_school_association, school_certificates_access | routes/web.php:355 |
| GET | /school/certificates/{studentCertificate}/download | school.certificates.download | App\Http\Controllers\School\StudentCertificateController@download | web, auth, active_school_association, school_certificates_access | routes/web.php:356 |
| POST | /school/certificates/{studentCertificate}/cancel | school.certificates.cancel | App\Http\Controllers\School\StudentCertificateController@cancel | web, auth, active_school_association, school_certificates_access | routes/web.php:357 |
| GET | /school/exams | school.exams.index | App\Http\Controllers\School\SchoolExamController@index | web, auth, active_school_association, school_exams_access | routes/web.php:361 |
| PUT | /school/exams/settings | school.exams.settings.update | App\Http\Controllers\School\SchoolExamController@updateSettings | web, auth, active_school_association, school_exams_access | routes/web.php:362 |
| POST | /school/exams/templates | school.exams.templates.store | App\Http\Controllers\School\SchoolExamController@storeTemplate | web, auth, active_school_association, school_exams_access | routes/web.php:364 |
| PUT | /school/exams/templates/{schoolExamTemplate} | school.exams.templates.update | App\Http\Controllers\School\SchoolExamController@updateTemplate | web, auth, active_school_association, school_exams_access | routes/web.php:365 |
| DELETE | /school/exams/templates/{schoolExamTemplate} | school.exams.templates.destroy | App\Http\Controllers\School\SchoolExamController@destroyTemplate | web, auth, active_school_association, school_exams_access | routes/web.php:366 |
| POST | /school/exams | school.exams.store | App\Http\Controllers\School\SchoolExamController@storeExam | web, auth, active_school_association, school_exams_access | routes/web.php:368 |
| PUT | /school/exams/{schoolExam} | school.exams.update | App\Http\Controllers\School\SchoolExamController@updateExam | web, auth, active_school_association, school_exams_access | routes/web.php:369 |
| DELETE | /school/exams/{schoolExam} | school.exams.destroy | App\Http\Controllers\School\SchoolExamController@destroyExam | web, auth, active_school_association, school_exams_access | routes/web.php:370 |
| POST | /school/exams/{schoolExam}/status | school.exams.status.update | App\Http\Controllers\School\SchoolExamController@updateExamStatus | web, auth, active_school_association, school_exams_access | routes/web.php:371 |
| POST | /school/exams/question-bank | school.exams.question_bank.store | App\Http\Controllers\School\SchoolExamController@storeQuestion | web, auth, active_school_association, school_exams_access | routes/web.php:373 |
| PUT | /school/exams/question-bank/{schoolQuestionBankItem} | school.exams.question_bank.update | App\Http\Controllers\School\SchoolExamController@updateQuestion | web, auth, active_school_association, school_exams_access | routes/web.php:374 |
| DELETE | /school/exams/question-bank/{schoolQuestionBankItem} | school.exams.question_bank.destroy | App\Http\Controllers\School\SchoolExamController@destroyQuestion | web, auth, active_school_association, school_exams_access | routes/web.php:375 |
| POST | /school/exams/{schoolExam}/questions/sync | school.exams.questions.sync | App\Http\Controllers\School\SchoolExamController@syncExamQuestions | web, auth, active_school_association, school_exams_access | routes/web.php:377 |
| POST | /school/exams/{schoolExam}/scores/upsert | school.exams.scores.upsert | App\Http\Controllers\School\SchoolExamController@upsertScores | web, auth, active_school_association, school_exams_access | routes/web.php:378 |
| GET | /school/student-leaves | school.student_leaves.index | App\Http\Controllers\School\StudentLeaveController@index | web, auth, active_school_association, student_leave_access | routes/web.php:382 |
| GET | /api/school/leave-types | api.school.leave_types.index | App\Http\Controllers\Api\School\StudentLeaveManagementController@leaveTypes | web, auth, active_school_association, student_leave_access, throttle:api.school | routes/web.php:385 |
| POST | /api/school/leave-types | api.school.leave_types.store | App\Http\Controllers\Api\School\StudentLeaveManagementController@storeLeaveType | web, auth, active_school_association, student_leave_access, throttle:api.school | routes/web.php:386 |
| PATCH | /api/school/leave-types/{schoolLeaveType} | api.school.leave_types.update | App\Http\Controllers\Api\School\StudentLeaveManagementController@updateLeaveType | web, auth, active_school_association, student_leave_access, throttle:api.school | routes/web.php:387 |
| GET | /api/school/leave-types/{schoolLeaveType}/delete-impact | api.school.leave_types.delete_impact | App\Http\Controllers\Api\School\StudentLeaveManagementController@leaveTypeDeleteImpact | web, auth, active_school_association, student_leave_access, throttle:api.school | routes/web.php:388 |
| POST | /api/school/leave-types/{schoolLeaveType}/disable | api.school.leave_types.disable | App\Http\Controllers\Api\School\StudentLeaveManagementController@disableLeaveType | web, auth, active_school_association, student_leave_access, throttle:api.school | routes/web.php:389 |
| GET | /api/school/school-calendar-settings | api.school.calendar_settings.show | App\Http\Controllers\Api\School\SchoolCalendarManagementController@showSettings | web, auth, active_school_association, student_leave_access, throttle:api.school | routes/web.php:391 |
| PUT | /api/school/school-calendar-settings | api.school.calendar_settings.update | App\Http\Controllers\Api\School\SchoolCalendarManagementController@updateSettings | web, auth, active_school_association, student_leave_access, throttle:api.school | routes/web.php:392 |
| GET | /api/school/holidays | api.school.holidays.index | App\Http\Controllers\Api\School\SchoolCalendarManagementController@indexHolidays | web, auth, active_school_association, student_leave_access, throttle:api.school | routes/web.php:394 |
| POST | /api/school/holidays | api.school.holidays.store | App\Http\Controllers\Api\School\SchoolCalendarManagementController@storeHoliday | web, auth, active_school_association, student_leave_access, throttle:api.school | routes/web.php:395 |
| GET | /api/school/holidays/{schoolHoliday}/delete-impact | api.school.holidays.delete_impact | App\Http\Controllers\Api\School\SchoolCalendarManagementController@holidayDeleteImpact | web, auth, active_school_association, student_leave_access, throttle:api.school | routes/web.php:396 |
| GET | /api/school/holidays/{schoolHoliday}/update-impact | api.school.holidays.update_impact | App\Http\Controllers\Api\School\SchoolCalendarManagementController@holidayUpdateImpact | web, auth, active_school_association, student_leave_access, throttle:api.school | routes/web.php:397 |
| PATCH | /api/school/holidays/{schoolHoliday} | api.school.holidays.update | App\Http\Controllers\Api\School\SchoolCalendarManagementController@updateHoliday | web, auth, active_school_association, student_leave_access, throttle:api.school | routes/web.php:398 |
| POST | /api/school/holidays/{schoolHoliday}/disable | api.school.holidays.disable | App\Http\Controllers\Api\School\SchoolCalendarManagementController@disableHoliday | web, auth, active_school_association, student_leave_access, throttle:api.school | routes/web.php:399 |
| GET | /api/school/leaves | api.school.leaves.index | App\Http\Controllers\Api\School\StudentLeaveManagementController@index | web, auth, active_school_association, student_leave_access, throttle:api.school | routes/web.php:401 |
| POST | /api/school/leaves | api.school.leaves.store | App\Http\Controllers\Api\School\StudentLeaveManagementController@store | web, auth, active_school_association, student_leave_access, throttle:api.school | routes/web.php:402 |
| PATCH | /api/school/leaves/{schoolStudentLeaveRequest} | api.school.leaves.update | App\Http\Controllers\Api\School\StudentLeaveManagementController@update | web, auth, active_school_association, student_leave_access, throttle:api.school | routes/web.php:403 |
| POST | /api/school/leaves/{schoolStudentLeaveRequest}/approve | api.school.leaves.approve | App\Http\Controllers\Api\School\StudentLeaveManagementController@approve | web, auth, active_school_association, student_leave_access, throttle:api.school | routes/web.php:404 |
| POST | /api/school/leaves/{schoolStudentLeaveRequest}/reject | api.school.leaves.reject | App\Http\Controllers\Api\School\StudentLeaveManagementController@reject | web, auth, active_school_association, student_leave_access, throttle:api.school | routes/web.php:405 |
| POST | /api/school/leaves/{schoolStudentLeaveRequest}/cancel | api.school.leaves.cancel | App\Http\Controllers\Api\School\StudentLeaveManagementController@cancel | web, auth, active_school_association, student_leave_access, throttle:api.school | routes/web.php:406 |
| POST | /api/school/leaves/{schoolStudentLeaveRequest}/attachments | api.school.leaves.attachments.store | App\Http\Controllers\Api\School\StudentLeaveManagementController@storeAttachment | web, auth, active_school_association, student_leave_access, throttle:api.school | routes/web.php:407 |
| GET | /api/school/leaves/{schoolStudentLeaveRequest}/attachments/{schoolStudentLeaveAttachment}/download | api.school.leaves.attachments.download | App\Http\Controllers\Api\School\StudentLeaveManagementController@downloadAttachment | web, auth, active_school_association, student_leave_access, throttle:api.school | routes/web.php:408 |
| GET | /api/school/quick-setup/status | api.school.quick_setup.status | App\Http\Controllers\Api\School\QuickSetupStatusController@show | web, auth, academic_planning_access, throttle:api.school | routes/web.php:413 |
| GET | /school/academic-planning | school.academic_planning.index | App\Http\Controllers\School\AcademicPlanningController@index | web, auth, active_school_association, academic_planning_access | routes/web.php:417 |
| POST | /school/academic-planning/years | school.academic_planning.years.store | App\Http\Controllers\School\AcademicPlanningController@storeYear | web, auth, active_school_association, academic_planning_access | routes/web.php:419 |
| PUT | /school/academic-planning/years/{schoolAcademicYear} | school.academic_planning.years.update | App\Http\Controllers\School\AcademicPlanningController@updateYear | web, auth, active_school_association, academic_planning_access | routes/web.php:420 |
| DELETE | /school/academic-planning/years/{schoolAcademicYear} | school.academic_planning.years.destroy | App\Http\Controllers\School\AcademicPlanningController@destroyYear | web, auth, active_school_association, academic_planning_access | routes/web.php:421 |
| POST | /school/academic-planning/terms | school.academic_planning.terms.store | App\Http\Controllers\School\AcademicPlanningController@storeTerm | web, auth, active_school_association, academic_planning_access | routes/web.php:423 |
| PUT | /school/academic-planning/terms/{schoolTerm} | school.academic_planning.terms.update | App\Http\Controllers\School\AcademicPlanningController@updateTerm | web, auth, active_school_association, academic_planning_access | routes/web.php:424 |
| DELETE | /school/academic-planning/terms/{schoolTerm} | school.academic_planning.terms.destroy | App\Http\Controllers\School\AcademicPlanningController@destroyTerm | web, auth, active_school_association, academic_planning_access | routes/web.php:425 |
| POST | /school/academic-planning/versions | school.academic_planning.versions.store | App\Http\Controllers\School\AcademicPlanningController@storeTimetableVersion | web, auth, active_school_association, academic_planning_access | routes/web.php:427 |
| PUT | /school/academic-planning/versions/{schoolTimetableVersion} | school.academic_planning.versions.update | App\Http\Controllers\School\AcademicPlanningController@updateTimetableVersion | web, auth, active_school_association, academic_planning_access | routes/web.php:428 |
| POST | /school/academic-planning/versions/{schoolTimetableVersion}/publish | school.academic_planning.versions.publish | App\Http\Controllers\School\AcademicPlanningController@publishTimetableVersion | web, auth, active_school_association, academic_planning_access | routes/web.php:429 |
| POST | /school/academic-planning/subjects | school.academic_planning.subjects.store | App\Http\Controllers\School\AcademicPlanningController@storeSubject | web, auth, active_school_association, academic_planning_access | routes/web.php:431 |
| PUT | /school/academic-planning/subjects/{schoolSubject} | school.academic_planning.subjects.update | App\Http\Controllers\School\AcademicPlanningController@updateSubject | web, auth, active_school_association, academic_planning_access | routes/web.php:432 |
| DELETE | /school/academic-planning/subjects/{schoolSubject} | school.academic_planning.subjects.destroy | App\Http\Controllers\School\AcademicPlanningController@destroySubject | web, auth, active_school_association, academic_planning_access | routes/web.php:433 |
| POST | /school/academic-planning/subjects/{schoolSubject}/teachers | school.academic_planning.subjects.teachers.sync | App\Http\Controllers\School\AcademicPlanningController@syncSubjectTeachers | web, auth, active_school_association, academic_planning_access | routes/web.php:434 |
| POST | /school/academic-planning/offerings | school.academic_planning.offerings.store | App\Http\Controllers\School\AcademicPlanningController@storeCourseOffering | web, auth, active_school_association, academic_planning_access | routes/web.php:436 |
| PUT | /school/academic-planning/offerings/{schoolCourseOffering} | school.academic_planning.offerings.update | App\Http\Controllers\School\AcademicPlanningController@updateCourseOffering | web, auth, active_school_association, academic_planning_access | routes/web.php:437 |
| DELETE | /school/academic-planning/offerings/{schoolCourseOffering} | school.academic_planning.offerings.destroy | App\Http\Controllers\School\AcademicPlanningController@destroyCourseOffering | web, auth, active_school_association, academic_planning_access | routes/web.php:438 |
| POST | /school/academic-planning/offerings/{schoolCourseOffering}/assignment/sync | school.academic_planning.offerings.assignment.sync | App\Http\Controllers\School\AcademicPlanningController@syncCourseOfferingAssignment | web, auth, active_school_association, academic_planning_access | routes/web.php:439 |
| POST | /school/academic-planning/teachers/{teacher}/availability/sync | school.academic_planning.teachers.availability.sync | App\Http\Controllers\School\AcademicPlanningController@syncTeacherAvailability | web, auth, active_school_association, academic_planning_access | routes/web.php:441 |
| POST | /school/academic-planning/schedules/grid/sync | school.academic_planning.schedules.grid.sync | App\Http\Controllers\School\AcademicPlanningController@syncWeeklyGrid | web, auth, active_school_association, academic_planning_access | routes/web.php:443 |
| GET | /school/academic-planning/schedules/grid/export/{format} | school.academic_planning.schedules.grid.export | App\Http\Controllers\School\AcademicPlanningController@exportWeeklyGrid | web, auth, active_school_association, academic_planning_access | routes/web.php:444 |
| POST | /school/academic-planning/schedules | school.academic_planning.schedules.store | App\Http\Controllers\School\AcademicPlanningController@storeSchedule | web, auth, active_school_association, academic_planning_access | routes/web.php:447 |
| PUT | /school/academic-planning/schedules/{schoolClassSchedule} | school.academic_planning.schedules.update | App\Http\Controllers\School\AcademicPlanningController@updateSchedule | web, auth, active_school_association, academic_planning_access | routes/web.php:448 |
| DELETE | /school/academic-planning/schedules/{schoolClassSchedule} | school.academic_planning.schedules.destroy | App\Http\Controllers\School\AcademicPlanningController@destroySchedule | web, auth, active_school_association, academic_planning_access | routes/web.php:449 |
| GET | /staff/dashboard | staff.dashboard | App\Http\Controllers\Staff\DashboardController@index | web, auth, role:staff, active_school_association | routes/web.php:453 |
| GET | /staff/subtasks | staff.subtasks.index | App\Http\Controllers\Staff\SubtaskController@index | web, auth, role:staff, active_school_association | routes/web.php:455 |
| GET | /staff/subtasks/{subtask} | staff.subtasks.show | App\Http\Controllers\Staff\SubtaskController@show | web, auth, role:staff, active_school_association | routes/web.php:456 |
| POST | /staff/subtasks/{subtask}/reply | staff.subtasks.reply | App\Http\Controllers\Staff\SubtaskController@reply | web, auth, role:staff, active_school_association | routes/web.php:457 |
| POST | /staff/subtasks/{subtask}/submit | staff.subtasks.submit | App\Http\Controllers\Staff\SubtaskController@submit | web, auth, role:staff, active_school_association | routes/web.php:458 |
| GET | /notifications | notifications.index | App\Http\Controllers\NotificationController@index | web, auth | routes/web.php:462 |
| POST | /notifications/{notification}/read | notifications.read | App\Http\Controllers\NotificationController@markAsRead | web, auth | routes/web.php:463 |
| POST | /notifications/read-all | notifications.read_all | App\Http\Controllers\NotificationController@markAllAsRead | web, auth | routes/web.php:464 |
| POST | /phone/verification/whatsapp/send | phone.verification.whatsapp.send | App\Http\Controllers\WhatsAppPhoneVerificationController@send | web, auth, throttle:api.school | routes/web.php:465 |
| POST | /phone/verification/whatsapp/verify | phone.verification.whatsapp.verify | App\Http\Controllers\WhatsAppPhoneVerificationController@verify | web, auth, throttle:api.school | routes/web.php:468 |
| GET | /dashboard | dashboard | Closure@closure | web, auth, verified | routes/web.php:473 |
| GET | /profile | profile.edit | App\Http\Controllers\ProfileController@edit | web, auth | routes/web.php:478 |
| PATCH | /profile | profile.update | App\Http\Controllers\ProfileController@update | web, auth | routes/web.php:479 |
| DELETE | /profile | profile.destroy | App\Http\Controllers\ProfileController@destroy | web, auth | routes/web.php:480 |
| GET | /register | register | App\Http\Controllers\Auth\RegisteredUserController@create | web, guest | routes/auth.php:18 |
| POST | /register |  | App\Http\Controllers\Auth\RegisteredUserController@store | web, guest, throttle:6,1 | routes/auth.php:21 |
| GET | /auth/register/supervisor | register.supervisor | App\Http\Controllers\Auth\RegisterSupervisorController@create | web, guest | routes/auth.php:24 |
| POST | /auth/register/supervisor | register.supervisor.store | App\Http\Controllers\Auth\RegisterSupervisorController@store | web, guest, throttle:6,1 | routes/auth.php:27 |
| GET | /auth/register/manager | register.manager.plan | App\Http\Controllers\Auth\RegisterManagerSubscriptionController@create | web, guest | routes/auth.php:31 |
| POST | /auth/register/manager | register.manager.plan.store | App\Http\Controllers\Auth\RegisterManagerSubscriptionController@store | web, guest, throttle:6,1 | routes/auth.php:34 |
| GET | /auth/register-manager | register.manager | App\Http\Controllers\Auth\RegisterManagerController@create | web, guest | routes/auth.php:38 |
| POST | /auth/register-manager | register.manager.store | App\Http\Controllers\Auth\RegisterManagerController@store | web, guest, throttle:6,1 | routes/auth.php:41 |
| GET | /login | login | App\Http\Controllers\Auth\AuthenticatedSessionController@create | web, guest | routes/auth.php:45 |
| POST | /login |  | App\Http\Controllers\Auth\AuthenticatedSessionController@store | web, guest | routes/auth.php:48 |
| GET | /forgot-password | password.request | App\Http\Controllers\Auth\PasswordResetLinkController@create | web, guest | routes/auth.php:50 |
| POST | /forgot-password | password.email | App\Http\Controllers\Auth\PasswordResetLinkController@store | web, guest | routes/auth.php:53 |
| GET | /reset-password/{token} | password.reset | App\Http\Controllers\Auth\NewPasswordController@create | web, guest | routes/auth.php:56 |
| POST | /reset-password | password.store | App\Http\Controllers\Auth\NewPasswordController@store | web, guest | routes/auth.php:59 |
| GET | /verify-email | verification.notice | App\Http\Controllers\Auth\EmailVerificationPromptController@__invoke | web, auth | routes/auth.php:64 |
| GET | /verify-email/{id}/{hash} | verification.verify | App\Http\Controllers\Auth\VerifyEmailController@__invoke | web, auth, signed, throttle:6,1 | routes/auth.php:67 |
| POST | /email/verification-notification | verification.send | App\Http\Controllers\Auth\EmailVerificationNotificationController@store | web, auth, throttle:6,1 | routes/auth.php:71 |
| GET | /confirm-password | password.confirm | App\Http\Controllers\Auth\ConfirmablePasswordController@show | web, auth | routes/auth.php:75 |
| POST | /confirm-password |  | App\Http\Controllers\Auth\ConfirmablePasswordController@store | web, auth | routes/auth.php:78 |
| PUT | /password | password.update | App\Http\Controllers\Auth\PasswordController@update | web, auth | routes/auth.php:80 |
| POST | /logout | logout | App\Http\Controllers\Auth\AuthenticatedSessionController@destroy | web, auth | routes/auth.php:82 |

## Appendix C — Web page / controller map

| Inertia page | Controller/action | Evidence |
|---|---|---|
| Admin/Dashboard | Admin/DashboardController.php@index | app/Http/Controllers/Admin/DashboardController.php:16 |
| Admin/Departments/Index | Admin/DepartmentController.php@index | app/Http/Controllers/Admin/DepartmentController.php:25 |
| Admin/Footer/Index | Admin/FooterController.php@index | app/Http/Controllers/Admin/FooterController.php:16 |
| Admin/Plans/Index | Admin/PlanController.php@index | app/Http/Controllers/Admin/PlanController.php:22 |
| Admin/Roles/Index | Admin/RoleController.php@index | app/Http/Controllers/Admin/RoleController.php:26 |
| Admin/SchoolDefaults/Index | Admin/SchoolDefaultDataController.php@index | app/Http/Controllers/Admin/SchoolDefaultDataController.php:44 |
| Admin/Schools/Index | Admin/SchoolManagementController.php@index | app/Http/Controllers/Admin/SchoolManagementController.php:42 |
| Admin/Settings/Index | Admin/SettingsController.php@index | app/Http/Controllers/Admin/SettingsController.php:31 |
| Admin/SupervisorAssignments/Index | Admin/SupervisorAssignmentController.php@index | app/Http/Controllers/Admin/SupervisorAssignmentController.php:19 |
| Admin/Users/Index | Admin/UserController.php@index | app/Http/Controllers/Admin/UserController.php:32 |
| Auth/Login | Auth/AuthenticatedSessionController.php@create | app/Http/Controllers/Auth/AuthenticatedSessionController.php:21 |
| Auth/ConfirmPassword | Auth/ConfirmablePasswordController.php@show | app/Http/Controllers/Auth/ConfirmablePasswordController.php:18 |
| Auth/VerifyEmail | Auth/EmailVerificationPromptController.php@__invoke | app/Http/Controllers/Auth/EmailVerificationPromptController.php:16 |
| Auth/ResetPassword | Auth/NewPasswordController.php@create | app/Http/Controllers/Auth/NewPasswordController.php:22 |
| Auth/ForgotPassword | Auth/PasswordResetLinkController.php@create | app/Http/Controllers/Auth/PasswordResetLinkController.php:18 |
| Auth/Register | Auth/RegisteredUserController.php@create | app/Http/Controllers/Auth/RegisteredUserController.php:25 |
| Auth/RegisterManagerSubscription | Auth/RegisterManagerSubscriptionController.php@create | app/Http/Controllers/Auth/RegisterManagerSubscriptionController.php:35 |
| Auth/RegisterSupervisor | Auth/RegisterSupervisorController.php@create | app/Http/Controllers/Auth/RegisterSupervisorController.php:33 |
| Manager/Dashboard | Manager/DashboardController.php@index | app/Http/Controllers/Manager/DashboardController.php:15 |
| Manager/Onboarding | Manager/OnboardingController.php@show | app/Http/Controllers/Manager/OnboardingController.php:51 |
| Manager/Requests | Manager/RequestController.php@page | app/Http/Controllers/Manager/RequestController.php:19 |
| Manager/SchoolStructure | Manager/SchoolStructureController.php@index | app/Http/Controllers/Manager/SchoolStructureController.php:28 |
| PageViewer | PageController.php@show | app/Http/Controllers/PageController.php:17 |
| Pricing/Index | PricingController.php@index | app/Http/Controllers/PricingController.php:11 |
| Profile/Edit | ProfileController.php@edit | app/Http/Controllers/ProfileController.php:23 |
| School/AcademicPlanning | School/AcademicPlanningController.php@index | app/Http/Controllers/School/AcademicPlanningController.php:60 |
| School/Reports | School/ReportsController.php@index | app/Http/Controllers/School/ReportsController.php:38 |
| School/Exams | School/SchoolExamController.php@index | app/Http/Controllers/School/SchoolExamController.php:51 |
| School/StudentAttendance | School/StudentAttendanceController.php@index | app/Http/Controllers/School/StudentAttendanceController.php:45 |
| School/Certificates/Index | School/StudentCertificateController.php@index | app/Http/Controllers/School/StudentCertificateController.php:44 |
| School/StudentLeaves | School/StudentLeaveController.php@index | app/Http/Controllers/School/StudentLeaveController.php:24 |
| School/StudentStructure | School/StudentStructureController.php@index | app/Http/Controllers/School/StudentStructureController.php:39 |
| Staff/Dashboard | Staff/DashboardController.php@index | app/Http/Controllers/Staff/DashboardController.php:12 |
| Supervisor/Dashboard | Supervisor/DashboardController.php@index | app/Http/Controllers/Supervisor/DashboardController.php:13 |
| Supervisor/Onboarding | Supervisor/OnboardingController.php@show | app/Http/Controllers/Supervisor/OnboardingController.php:24 |
| Supervisor/Requests | Supervisor/RequestController.php@page | app/Http/Controllers/Supervisor/RequestController.php:19 |
| Welcome | WelcomeController.php@index | app/Http/Controllers/WelcomeController.php:22 |

## Appendix D — Service discovery and caller evidence

| Service | Public functions | Transactions | Caller files |
|---|---|---|---|
| app/Services/Association/AssociationService.php | createForManager, approve, reject | YES in at least one method; not all methods transactional | app/Http/Controllers/AssociationRequestController.php |
| app/Services/Auth/RoleRedirectService.php | redirectRouteFor | No explicit DB::transaction | app/Http/Controllers/Auth/AuthenticatedSessionController.php |
| app/Services/Auth/SchoolRoleAssignmentService.php | assignableRolesForSchoolManager, normalizeRoleNamesForSchoolManager | No explicit DB::transaction | app/Http/Controllers/Api/School/SchoolAssignableRoleController.php, app/Http/Controllers/Api/School/SchoolUserManagementController.php |
| app/Services/Auth/UserApprovalService.php | initialStateForRole, roleRequiresApproval, notifyPendingApproval, approve, reject | YES in at least one method; not all methods transactional | app/Http/Controllers/Admin/UserController.php, app/Http/Controllers/Auth/RegisterManagerSubscriptionController.php, app/Http/Controllers/Auth/RegisterSupervisorController.php |
| app/Services/Certificates/CertificateIssuingService.php | issueForStudents, issueForRecipients, cancel | YES in at least one method; not all methods transactional | app/Http/Controllers/School/StudentCertificateController.php |
| app/Services/Certificates/CertificateRenderingService.php | renderHtml, storePrintableHtml | No explicit DB::transaction | app/Http/Controllers/School/StudentCertificateController.php, app/Services/Certificates/CertificateIssuingService.php |
| app/Services/Certificates/CertificateTemplateService.php | create, update, assertTemplateBelongsToSchool | No explicit DB::transaction | app/Http/Controllers/School/StudentCertificateController.php, app/Services/Certificates/CertificateIssuingService.php |
| app/Services/Certificates/CertificateVerificationService.php | publicPayload | No explicit DB::transaction | app/Http/Controllers/CertificateVerificationController.php |
| app/Services/Exports/SchoolExportDocumentService.php | schoolForExport, writeCsvPreamble, writeCsvFooter, putCsvRow, writeUtf8Bom, safeFileName, csvHeaders, wordHeaders, downloadPdfFromHtml, schoolLogoDataUri | No explicit DB::transaction | app/Http/Controllers/School/AcademicPlanningController.php, app/Http/Controllers/School/ReportsController.php, app/Http/Controllers/School/StudentAttendanceController.php, app/Http/Controllers/School/StudentCertificateController.php |
| app/Services/Integrity/IntegrityImpactService.php | checkDeleteImpact, checkUpdateImpact | No explicit DB::transaction | app/Http/Controllers/Api/School/SchoolCalendarManagementController.php, app/Http/Controllers/Api/School/StudentLeaveManagementController.php, app/Http/Controllers/School/StudentStructureController.php, app/Services/School/SchoolCalendarService.php, app/Services/School/StudentLeaveService.php |
| app/Services/Pricing/PricingComponentPlanStateService.php | freezePlan, activatePlan, removePlan | No explicit DB::transaction | app/Http/Controllers/Admin/PlanController.php |
| app/Services/Pricing/PricingComponentPlanSyncService.php | syncContent | No explicit DB::transaction | app/Http/Controllers/Admin/PageComponentController.php, app/Http/Controllers/WelcomeController.php |
| app/Services/School/AcademicPlanningValidationService.php | validateScheduleAgainstSchoolReferences, validateCourseOfferingReferences | No explicit DB::transaction | app/Http/Controllers/School/AcademicPlanningController.php |
| app/Services/School/DailyAttendanceInitializerService.php | ensureDailyRecordsForClassroom, ensureDailyRecordsForSchool | YES in at least one method; not all methods transactional | app/Http/Controllers/School/StudentAttendanceController.php |
| app/Services/School/ExamSchedulingValidationService.php | validateForScheduling | No explicit DB::transaction | app/Http/Controllers/School/SchoolExamController.php |
| app/Services/School/SchoolCalendarService.php | getOrCreateSettings, updateSettings, listHolidays, createHoliday, updateHoliday, disableHoliday, resolveDayTypeForDate, hasAcademicYears, hasAcademicTerms, hasAcademicDateReferences, resolveOperationalAcademicContextForDate, ensureDateWithinOperationalAcademicTerm, ensureAcademicYearRangeIntegrity, ensureTermRangeIntegrity, normalizeWeeklyOffDays | YES in at least one method; not all methods transactional | app/Http/Controllers/Api/School/SchoolCalendarManagementController.php, app/Http/Controllers/School/AcademicPlanningController.php, app/Http/Controllers/School/StudentAttendanceController.php, app/Services/School/AcademicPlanningValidationService.php, app/Services/School/DailyAttendanceInitializerService.php, app/Services/School/ExamSchedulingValidationService.php |
| app/Services/School/SchoolDashboardAnalyticsService.php | build | No explicit DB::transaction | app/Http/Controllers/Manager/DashboardController.php |
| app/Services/School/SchoolDefaultDataProvisioningService.php | templateAvailability, templateOptions, schoolProvisioningStatus, importForSchool | YES in at least one method; not all methods transactional | app/Http/Controllers/Manager/OnboardingController.php, app/Http/Controllers/School/AcademicPlanningController.php, app/Http/Controllers/School/DefaultDataImportController.php, app/Http/Controllers/School/StudentStructureController.php |
| app/Services/School/SchoolDefaultTemplateBootstrapService.php | ensureFallbackDefaults | YES in at least one method; not all methods transactional | app/Http/Controllers/Admin/SchoolDefaultDataController.php |
| app/Services/School/SchoolDefaultTemplateScopeRegistry.php | all, findByDirectorate, find, upsert, delete, appliesToDirectorate, normalizeReferenceSnapshot | No explicit DB::transaction | app/Http/Controllers/Admin/SchoolDefaultDataController.php, app/Services/School/SchoolDefaultDataProvisioningService.php, app/Services/School/StudentLeaveService.php |
| app/Services/School/SchoolDelegationService.php | managerCatalogGroups, managerGroupTypeOptions, delegationTemplates, ensurePermissionBaseline, normalizeManagerPermissionNames, normalizeSchoolPermissionGroupIds, syncUserDelegation, delegationAuditFeedForSchool | No explicit DB::transaction | app/Http/Controllers/Api/School/SchoolPermissionGroupController.php, app/Http/Controllers/Api/School/SchoolUserManagementController.php, app/Http/Controllers/Manager/SchoolStructureController.php |
| app/Services/School/StudentImportService.php | templateResponse, import | YES in at least one method; not all methods transactional | app/Http/Controllers/School/StudentStructureController.php |
| app/Services/School/StudentLeaveService.php | ensureDefaultLeaveTypes, createLeaveType, updateLeaveType, disableLeaveType, create, update, approve, reject, cancel, uploadAttachment, applyLeaveStateToAttendanceRows, activeApprovedLeavesForDate, ensureNoApprovedOverlap | YES in at least one method; not all methods transactional | app/Http/Controllers/Api/School/StudentLeaveManagementController.php, app/Http/Controllers/School/AcademicPlanningController.php, app/Http/Controllers/School/StudentAttendanceController.php, app/Http/Controllers/School/StudentLeaveController.php |
| app/Services/Subscription/SubscriptionPricingService.php | normalizeBillingCycle, priceForPlan, endsAtForBillingCycle, snapshotForPlan, initialSubscriptionEstimate, remainingDays, dailyExtraSeatPrice, calculateExtraSeats, activeExtraSeatsForSchool, allowedSeatsForSchool, reserveSeatsForSchoolStaff, currentSchoolStaffUsersCount, activeSubscriptionForManager, ensureSubscriptionBelongsToSchool, ensureSubscriptionCanAddSeats, syncSchoolContextForUser, formatAddonSummary | YES in at least one method; not all methods transactional | app/Http/Controllers/Api/School/SchoolUserManagementController.php, app/Http/Controllers/Auth/RegisterManagerSubscriptionController.php, app/Http/Controllers/Manager/SchoolUserController.php, app/Services/Subscription/SubscriptionService.php |
| app/Services/Subscription/SubscriptionService.php | createForUser, activate, freeze, cancel, delete, syncSchoolContextForUser | YES in at least one method; not all methods transactional | app/Http/Controllers/Admin/SubscriptionManagementController.php, app/Http/Controllers/Auth/RegisterManagerSubscriptionController.php, app/Http/Controllers/Auth/RegisterSupervisorController.php, app/Http/Controllers/Manager/OnboardingController.php, app/Services/Auth/UserApprovalService.php |
| app/Services/Supervision/SchoolSupervisionRequestService.php | createBySupervisorSelection, createBySupervisorLocationSelection, managerApprove, managerReject, supervisorConfirm, supervisorCancel | YES in at least one method; not all methods transactional | app/Http/Controllers/Manager/RequestController.php, app/Http/Controllers/Supervisor/OnboardingController.php, app/Http/Controllers/Supervisor/RequestController.php |
| app/Services/Support/AttachmentService.php | uploadValidationRules, uploadValidationMessages, strictValidationEnabled, allowedMimeTypes, storeManyForAttachable, deleteInstitutionalAttachment, downloadInstitutionalAttachment, serializeForUi, attachmentDisk | No explicit DB::transaction | app/Http/Controllers/Api/School/SchoolUserManagementController.php, app/Http/Controllers/Manager/SchoolStructureController.php, app/Http/Controllers/Manager/SchoolUserController.php, app/Http/Controllers/School/AcademicPlanningController.php, app/Http/Controllers/School/SchoolAttachmentController.php, app/Http/Controllers/School/SchoolExamController.php, app/Http/Controllers/School/StudentStructureController.php |
| app/Services/Support/AuditLogger.php | log | No explicit DB::transaction | app/Http/Controllers/Admin/DepartmentController.php, app/Http/Controllers/Admin/PlanController.php, app/Http/Controllers/Admin/RoleController.php, app/Http/Controllers/Admin/SchoolDefaultDataController.php, app/Http/Controllers/Admin/SubscriptionManagementController.php, app/Http/Controllers/Api/School/SchoolPermissionGroupController.php, app/Http/Controllers/Api/School/SchoolUserManagementController.php, app/Http/Controllers/Manager/DepartmentController.php, app/Http/Controllers/Manager/OnboardingController.php, app/Http/Controllers/School/AcademicPlanningController.php, app/Http/Controllers/School/ReportsController.php, app/Http/Controllers/School/SchoolExamController.php, app/Http/Controllers/School/StudentAttendanceController.php, app/Http/Controllers/School/StudentStructureController.php, app/Services/Association/AssociationService.php, app/Services/School/SchoolCalendarService.php, app/Services/School/SchoolDefaultDataProvisioningService.php, app/Services/School/SchoolDelegationService.php, app/Services/School/StudentLeaveService.php, app/Services/Supervision/SchoolSupervisionRequestService.php, app/Services/Support/AttachmentService.php, app/Services/Ticketing/TicketingService.php |
| app/Services/Support/NotificationService.php | notifyUser, notifyUsers, withRoute, notifySuperAdmins, superAdminUserIds, responsibleSupervisorUserIdsForSchool | No explicit DB::transaction | app/Http/Controllers/Manager/OnboardingController.php, app/Services/Association/AssociationService.php, app/Services/Auth/UserApprovalService.php, app/Services/Subscription/SubscriptionService.php, app/Services/Supervision/SchoolSupervisionRequestService.php, app/Services/Ticketing/TicketingService.php, app/Services/WhatsApp/WhatsAppNotificationService.php, app/Services/WhatsApp/WhatsAppOtpService.php |
| app/Services/Support/StatusHistoryService.php | record | No explicit DB::transaction | app/Services/Association/AssociationService.php, app/Services/School/StudentLeaveService.php, app/Services/Supervision/SchoolSupervisionRequestService.php, app/Services/Ticketing/TicketingService.php |
| app/Services/System/CountryReferenceDataService.php | fetchForCountry | No explicit DB::transaction | app/Http/Controllers/Admin/SchoolDefaultDataController.php |
| app/Services/System/GlobalLocationTaxonomySyncService.php | syncCountries, syncGovernoratesForCountry, resolveCountryApiMetadata | No explicit DB::transaction | app/Http/Controllers/Admin/SchoolDefaultDataController.php, app/Http/Controllers/Admin/SchoolManagementController.php, app/Http/Controllers/Manager/OnboardingController.php, app/Services/System/CountryReferenceDataService.php |
| app/Services/System/IslamicHolidayPresetService.php | presetsForCountry, supports | No explicit DB::transaction | No named class reference found; dynamic callers UNCONFIRMED |
| app/Services/System/SaudiCountryReferenceSnapshotProvider.php | resolve, supports | No explicit DB::transaction | app/Services/System/CountryReferenceDataService.php |
| app/Services/Ticketing/TicketingService.php | markTicketStatus, markSubtaskStatus, notifyTicketCreated, notifySubtaskAssigned, notifySubtaskSubmitted, notifySubtaskApproved, notifySubtaskReplied, notifyManagerFinalReportSubmitted, notifyTicketClosed | No explicit DB::transaction | app/Http/Controllers/Manager/SubtaskController.php, app/Http/Controllers/Manager/TicketController.php, app/Http/Controllers/Staff/SubtaskController.php, app/Http/Controllers/Supervisor/TicketController.php |
| app/Services/WhatsApp/N8nWhatsAppClient.php | dispatch, configured, messageUuid | No explicit DB::transaction | app/Services/WhatsApp/WhatsAppNotificationService.php, app/Services/WhatsApp/WhatsAppOtpService.php, app/Jobs/DispatchWhatsAppNotification.php, app/Jobs/DispatchWhatsAppOtp.php |
| app/Services/WhatsApp/PhoneNumberNormalizer.php | normalize | No explicit DB::transaction | app/Services/WhatsApp/WhatsAppRecipientResolver.php |
| app/Services/WhatsApp/SignedWebhookPayloadVerifier.php | verify | No explicit DB::transaction | app/Http/Controllers/WhatsAppDispatchClaimController.php, app/Http/Controllers/WhatsAppDispatchResultController.php, app/Http/Controllers/WhatsAppStatusCallbackController.php |
| app/Services/WhatsApp/WebhookSignature.php | sign, verify | No explicit DB::transaction | app/Services/WhatsApp/N8nWhatsAppClient.php, app/Services/WhatsApp/SignedWebhookPayloadVerifier.php |
| app/Services/WhatsApp/WhatsAppDispatchClaimService.php | claim, recordAccepted, recordDefinitiveFailure, markUncertain | No explicit DB::transaction | app/Http/Controllers/WhatsAppDispatchClaimController.php, app/Http/Controllers/WhatsAppDispatchResultController.php, app/Jobs/DispatchWhatsAppNotification.php, app/Jobs/DispatchWhatsAppOtp.php |
| app/Services/WhatsApp/WhatsAppNotificationService.php | queueForNotification, idempotencyKey | No explicit DB::transaction | app/Services/Support/NotificationService.php, app/Services/WhatsApp/WhatsAppOtpService.php |
| app/Services/WhatsApp/WhatsAppOtpService.php | send, verify | YES in at least one method; not all methods transactional | app/Http/Controllers/WhatsAppPhoneVerificationController.php |
| app/Services/WhatsApp/WhatsAppRecipientResolver.php | phoneForUser | No explicit DB::transaction | app/Services/WhatsApp/WhatsAppNotificationService.php, app/Services/WhatsApp/WhatsAppOtpService.php |

## Appendix E — Test discovery (not test execution)

| File | test_* methods |
|---|---|
| tests/Feature/AcademicPlanningAccessTest.php | 6 |
| tests/Feature/AcademicPlanningCentralValidationTest.php | 10 |
| tests/Feature/AcademicPlanningGridEditorTest.php | 7 |
| tests/Feature/AcademicPlanningPageSegmentationTest.php | 4 |
| tests/Feature/AcademicPlanningSubjectManagementTest.php | 10 |
| tests/Feature/AcademicYearManagementTest.php | 8 |
| tests/Feature/AccountApprovalFlowTest.php | 16 |
| tests/Feature/ActiveSchoolAssociationMiddlewareTest.php | 9 |
| tests/Feature/AdminDashboardPageDesignTest.php | 1 |
| tests/Feature/AdminDefaultStageTemplateLinkingTest.php | 1 |
| tests/Feature/AdminDirectorateManagementTest.php | 3 |
| tests/Feature/AdminEducationStageManagementTest.php | 4 |
| tests/Feature/AdminGlobalTaxonomySyncTest.php | 5 |
| tests/Feature/AdminModalStructureTest.php | 3 |
| tests/Feature/AdminPlansPageDesignTest.php | 1 |
| tests/Feature/AdminSchoolDefaultEducationTypeManagementTest.php | 2 |
| tests/Feature/AdminSchoolDefaultTemplateSettingsTest.php | 14 |
| tests/Feature/AdminSchoolManagementStructureTest.php | 3 |
| tests/Feature/AdminSchoolsPageDesignTest.php | 1 |
| tests/Feature/AdminSettingsPageDesignTest.php | 1 |
| tests/Feature/AdminSidebarLayoutTest.php | 2 |
| tests/Feature/AdminUsersPageDesignTest.php | 1 |
| tests/Feature/ApiRateLimitingTest.php | 2 |
| tests/Feature/ArabicValidationMessagesTest.php | 6 |
| tests/Feature/AssociationHandshakeTest.php | 2 |
| tests/Feature/AttendancePerformanceIndexesTest.php | 1 |
| tests/Feature/Auth/AuthenticationTest.php | 5 |
| tests/Feature/Auth/EmailVerificationTest.php | 3 |
| tests/Feature/Auth/PasswordConfirmationTest.php | 3 |
| tests/Feature/Auth/PasswordResetTest.php | 4 |
| tests/Feature/Auth/PasswordUpdateTest.php | 2 |
| tests/Feature/Auth/RegistrationTest.php | 3 |
| tests/Feature/CountryReferenceHolidayTemplateImportTest.php | 2 |
| tests/Feature/CourseOfferingAssignmentTest.php | 4 |
| tests/Feature/CourseStudyPlanScopeTest.php | 11 |
| tests/Feature/DepartmentRolesStructureTest.php | 14 |
| tests/Feature/ExampleTest.php | 1 |
| tests/Feature/GulfCountriesAndGovernoratesSeederTest.php | 1 |
| tests/Feature/HeavyEndpointsPaginationTest.php | 4 |
| tests/Feature/LightModeThemeDesignTest.php | 3 |
| tests/Feature/ManagerDashboardAnalyticsTest.php | 6 |
| tests/Feature/ManagerOnboardingCountryEducationTypeTemplateMatchTest.php | 4 |
| tests/Feature/ManagerOnboardingGovernorateSyncTest.php | 2 |
| tests/Feature/ManagerOnboardingLogoUploadTest.php | 3 |
| tests/Feature/ManagerOnboardingStructureTest.php | 3 |
| tests/Feature/ManagerSchoolOwnershipMiddlewareTest.php | 6 |
| tests/Feature/ManagerSchoolStructurePermissionIndicatorTest.php | 1 |
| tests/Feature/ManagerStructurePageDesignTest.php | 2 |
| tests/Feature/MediaStorageUrlFallbackTest.php | 6 |
| tests/Feature/OrgStructureRoleTemplateManagementTest.php | 8 |
| tests/Feature/ProfilePageDesignTest.php | 1 |
| tests/Feature/ProfileTest.php | 5 |
| tests/Feature/PublicContentSanitizationTest.php | 2 |
| tests/Feature/PublicHomepageBannerSettingsIntegrationTest.php | 1 |
| tests/Feature/PublicHomepageSettingsIntegrationTest.php | 6 |
| tests/Feature/PublicPricingTableThemeIsolationTest.php | 1 |
| tests/Feature/PublicStorageLinkStructureTest.php | 2 |
| tests/Feature/QuickSetupStatusTest.php | 6 |
| tests/Feature/QuickSetupWizardStructureTest.php | 1 |
| tests/Feature/RealtimeNotificationFlowTest.php | 2 |
| tests/Feature/SaudiAcademicYear20252026DefaultTemplateSeederTest.php | 1 |
| tests/Feature/SchoolAttachmentManagementTest.php | 13 |
| tests/Feature/SchoolCalendarManagementApiTest.php | 11 |
| tests/Feature/SchoolDefaultDataProvisioningTest.php | 9 |
| tests/Feature/SchoolExamManagementTest.php | 29 |
| tests/Feature/SchoolExportDocumentServiceTest.php | 4 |
| tests/Feature/SchoolReportsPageDesignTest.php | 1 |
| tests/Feature/SchoolReportsTest.php | 2 |
| tests/Feature/SchoolSidebarStructureLabelsTest.php | 2 |
| tests/Feature/SchoolStructureCompatibilityTest.php | 2 |
| tests/Feature/SchoolTenantIsolationTest.php | 8 |
| tests/Feature/SchoolUserRoleAssignmentApiTest.php | 24 |
| tests/Feature/SecurityHeadersTest.php | 2 |
| tests/Feature/SharedFilterFeedbackFoundationTest.php | 2 |
| tests/Feature/SharedUiCacheTest.php | 9 |
| tests/Feature/StudentAttendanceAccessTest.php | 23 |
| tests/Feature/StudentCertificateFeatureTest.php | 6 |
| tests/Feature/StudentExcelImportTest.php | 4 |
| tests/Feature/StudentLeaveManagementTest.php | 10 |
| tests/Feature/StudentStructureAccessTest.php | 6 |
| tests/Feature/StudentStructureEnhancementsTest.php | 12 |
| tests/Feature/StudentStructureTreeViewTest.php | 3 |
| tests/Feature/SubscriptionBillingAndSeatsTest.php | 8 |
| tests/Feature/SubscriptionHandshakeFlowTest.php | 5 |
| tests/Feature/SupervisorAssignmentConstraintsTest.php | 1 |
| tests/Feature/SupervisorOnboardingGovernorateSchoolFilterTest.php | 2 |
| tests/Feature/SystemRoleManagementApiTest.php | 3 |
| tests/Feature/TeacherAvailabilitySchedulingTest.php | 4 |
| tests/Feature/TicketWorkflowAuthorizationTest.php | 1 |
| tests/Feature/TimetableVersionManagementTest.php | 4 |
| tests/Feature/UploadValidationFeatureFlagTest.php | 5 |
| tests/Feature/WhatsAppIntegrationTest.php | 33 |
| tests/Unit/ArabicInputGuardsStructureTest.php | 2 |
| tests/Unit/ExampleTest.php | 1 |
| tests/Unit/NativeDialogRegressionTest.php | 1 |
| tests/Unit/PublicHtmlSanitizerTest.php | 2 |
| tests/Unit/ThemeModeStructureTest.php | 2 |


## FINAL VERDICT

MOBILE_BACKEND_NOT_READY

FLUTTER_IMPLEMENTATION_STARTED=NO

BACKEND_FILES_MODIFIED=NO

SPEC_KIT_MODIFIED=NO

COMMIT_CREATED=NO

PUSH_PERFORMED=NO

STAGING_MODIFIED=NO

PRODUCTION_TOUCHED=NO

REAL_WHATSAPP_SENDS=0
