# CLAUDE.md - دليل مشروع Edaratek

## 1. نظرة عامة على المشروع

- **الاسم المحلي:** infinity-edaratek
- **اسم المنصة:** Edaratek
- **الهدف:** منصة SaaS تعليمية لإدارة المدارس، المستخدمين، الاشتراكات، الهيكل الطلابي، الحضور، الإجازات، التخطيط الأكاديمي، الاختبارات، التقارير، التذاكر، الشهادات، والمرفقات.
- **نوع المشروع:** Multi-tenant SaaS. أغلب وحدات المدرسة تعتمد على `school_id` وعلى مدرسة المستخدم الحالية أو المدرسة التي يديرها/ينتمي إليها.
- **جذر Laravel الفعلي:** ملفات `artisan`, `composer.json`, `app`, `routes`, `resources`, `database` موجودة مباشرة في جذر المشروع.
- **لغة الواجهة الافتراضية:** العربية.
- **اتجاه الواجهة:** RTL.
- **الواجهة النشطة:** Vue 3 + Inertia + Vite. توجد ملفات JSX/React قديمة في `resources/js` ووجودها لا يعني أن React هي الواجهة النشطة.

## 2. Stack التقني

- **Backend:** Laravel `^12.0`.
- **PHP:** `^8.2`.
- **Frontend:** Vue `^3.5.28` + Inertia Vue `^2.3.13` + Vite `^7.0.7`.
- **Inertia server adapter:** `inertiajs/inertia-laravel ^2.0`.
- **Auth:** Laravel Breeze موجود كاعتماد dev، وSanctum `^4.0`.
- **RBAC:** `spatie/laravel-permission ^6.24` مع دعم legacy role داخل جدول `users`.
- **Routes helper:** Ziggy `^2.0`.
- **CSS:** Tailwind CSS `^3.2.1` مع `@tailwindcss/forms`.
- **Charts:** `apexcharts ^5.12.0` و `vue3-apexcharts ^1.11.1`.
- **Icons:** `lucide-vue-next` للواجهة النشطة، وتوجد حزم React قديمة مثل `lucide-react`.
- **HTTP client:** Axios موجود في `package.json`.
- **Database في `.env.example`:** افتراضيًا SQLite للتطوير المحلي، لكن المشروع يدعم MySQL/MariaDB حسب إعدادات Laravel.

## 3. بيئة التشغيل والنشر

- **GitHub Actions:** يوجد workflow واحد في `.github/workflows/deploy.yml`.
- **Workflow name:** `Auto Deploy to Webmin Server`.
- **Trigger:** عند `push` على فرع `main`.
- **طريقة النشر في workflow:** SSH عبر `appleboy/ssh-action@master` باستخدام secrets:
  - `SERVER_HOST`
  - `SERVER_USER`
  - `SERVER_PASS`
- **المسار داخل workflow:** `/home/test/public_html`.
- **أوامر workflow:** `git pull origin main`, `composer install --no-dev --optimize-autoloader`, `npm install`, `npm run build`, `php artisan optimize:clear`.
- **مسار إنتاج معروف من سياق العمل:** `/home/edaratek/public_html`.
- **ملاحظة:** لا توجد IP أو بيانات دخول صريحة داخل الكود. لا تضف أسرارًا إلى المستودع.

## 4. مخطط قاعدة البيانات

> مستخلص من ملفات `database/migrations`.

### جداول النظام الأساسية

- `users`, `password_reset_tokens`, `sessions`
- `cache`, `cache_locks`
- `jobs`, `job_batches`, `failed_jobs`
- `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`
- `settings`, `audit_logs`, `status_history`, `notifications`

### جداول الموقع العام وCMS

- `pages`, `page_components`
- `media`
- `header_menus`, `header_items`
- `footer_columns`, `footer_items`

### جداول المدارس والجهات الجغرافية

- `schools`
- `educational_directorates`
- `countries`, `governorates`, `education_types`, `education_stages`, `education_stage_school`
- `school_supervisor_assignments`
- `association_requests`
- `school_supervision_requests`

### جداول الاشتراكات

- `plans`
- `subscriptions`
- `subscription_user_addons`

### جداول التنظيم الداخلي والأدوار المدرسية

- `departments`
- `department_roles`
- `org_structure_role_templates`
- `school_permission_groups`
- `school_permission_group_user`

### جداول الهيكل الطلابي

- `school_stages`
- `school_stage_terms`
- `school_stage_grades`
- `school_stage_grade_terms`
- `school_classrooms`
- `school_students`

### جداول الحضور والإجازات والتقويم

- `school_student_attendances`
- `school_attendance_attachments`
- `school_leave_types`
- `school_student_leave_requests`
- `school_student_leave_attachments`
- `school_calendar_settings`
- `school_holidays`

### جداول التخطيط الأكاديمي

- `school_academic_years`
- `school_terms`
- `school_subjects`
- `school_subject_teacher_assignments`
- `school_class_schedules`
- `school_teacher_availabilities`
- `school_course_offerings`
- `school_teaching_assignments`
- `school_teaching_assignment_classrooms`
- `school_timetable_versions`
- `school_course_plan_units`
- `school_course_plan_lessons`
- `school_course_plan_topics`

### جداول الاختبارات وبنك الأسئلة والدرجات

- `school_exam_settings`
- `school_exam_templates`
- `school_exams`
- `school_question_bank_items`
- `school_question_options`
- `school_exam_questions`
- `school_exam_student_scores`
- `school_exam_status_logs`

### جداول القوالب والبيانات الافتراضية

- `school_default_stage_templates`
- `school_default_stage_term_templates`
- `school_default_stage_grade_templates`
- `school_default_stage_grade_term_templates`
- `school_default_classroom_templates`
- `school_default_academic_year_templates`
- `school_default_holiday_templates`
- `school_default_leave_type_templates`
- `school_default_subject_templates`

### جداول التذاكر والمرفقات والشهادات

- `tickets`, `subtasks`, `ticket_messages`
- `attachments`
- `certificate_templates`
- `school_certificate_signatures`
- `student_certificates`

## 5. العلاقات الرئيسية

- `School` لديه علاقات مباشرة مع `users`, `subscriptions`, `stages`, `classrooms`, `students`, `studentAttendances`, `leaveTypes`, `studentLeaveRequests`, `calendarSetting`, `holidays`, `academicYears`, `terms`, `subjects`, `classSchedules`, `courseOfferings`, `teachingAssignments`, `exams`, `questionBankItems`, `examScores`, `certificateTemplates`, `studentCertificates`, `certificateSignatures`, `tickets`.
- `School` ينتمي إلى `EducationalDirectorate` ويرتبط بمدير عبر `manager_user_id` ومشرف عبر `supervisor_id`.
- `User` يدعم Spatie roles وlegacy `role`، ويرتبط بمدرسة عبر `school_id`، وبمدرسة يديرها عبر `managedSchool`، وبمدارس يشرف عليها عبر `supervisedSchools`.
- `SchoolStudent` ينتمي إلى مدرسة وفصل، وله حضور وإجازات ودرجات ومرفقات وشهادات.
- `SchoolClassroom` ينتمي إلى مدرسة ومرحلة، وله طلاب وحضور وجداول ومقررات واختبارات.
- `SchoolSubject`, `SchoolCourseOffering`, `SchoolTeachingAssignment`, `SchoolClassSchedule` تمثل قلب التخطيط الأكاديمي والجداول.
- `SchoolExam` يرتبط بالمدرسة والقالب والترم والمرحلة والفصل والمادة والمعلم والأسئلة والدرجات وسجلات الحالة.
- `Attachment` علاقة polymorphic (`attachable`) ومعها `school_id` و`uploaded_by`.
- `CertificateTemplate`, `SchoolCertificateSignature`, `StudentCertificate` كلها school-scoped.

## 6. الأدوار والصلاحيات

### الأدوار المعرفة في `database/seeders/RoleSeeder.php`

- `super_admin`
- `supervisor`
- `school_manager`
- `staff`
- `teacher`
- `student`
- `parent`

### آلية الصلاحيات

- Spatie Permission مستخدم رسميًا عبر `HasRoles` في `App\Models\User`.
- يوجد legacy role داخل `users.role`.
- `User::hasSystemRole()` يفحص Spatie role أو legacy role.
- حسابات `school_manager` و`supervisor` تحتاج موافقة Super Admin حسب `User::APPROVAL_REQUIRED_ROLES`.
- توجد صلاحيات مدرسية مفوضة في `App\Support\SchoolPermissionCatalog`.
- توجد أعمدة legacy/operational على users مثل:
  - `can_manage_student_structure`
  - `can_manage_student_attendance`
  - `can_manage_academic_planning`
  - `can_manage_student_leaves`
  - `can_manage_leave_types`
  - `can_manage_school_calendar`
  - `can_manage_school_holidays`
- `SchoolPermissionCatalog` يحتوي صلاحيات مثل:
  - `school.student_structure.manage`
  - `school.student_attendance.manage`
  - `school.student_leaves.manage`
  - `school.leave_types.manage`
  - `school.calendar.manage`
  - `school.holidays.manage`
  - `school.academic_planning.manage`
  - `school.exams.manage`
  - `school.reports.view`
  - `school.reports.export`
  - `school.tickets.manage`
  - `school.subtasks.manage`
  - `school.attachments.manage`
  - `school.users.manage`
  - `school.delegations.manage`
  - `certificates.*`

## 7. Middleware وحماية الوصول

### Middleware aliases في `bootstrap/app.php`

- `role` => `CheckRole`
- `managed_school` => `EnsureManagerOwnsSchool`
- `active_school_association` => `EnsureActiveSchoolAssociation`
- `student_structure_access` => `EnsureStudentStructureAccess`
- `student_attendance_access` => `EnsureStudentAttendanceAccess`
- `student_leave_access` => `EnsureStudentLeaveAccess`
- `academic_planning_access` => `EnsureAcademicPlanningAccess`
- `school_exams_access` => `EnsureSchoolExamsAccess`
- `school_reports_access` => `EnsureSchoolReportsAccess`
- `school_certificates_access` => `EnsureSchoolCertificatesAccess`

### Middleware web مضافة عالميًا

- `HandleInertiaRequests`
- `AddLinkHeadersForPreloadedAssets`
- `AddSecurityHeaders`
- `EnsureApprovedAccount`

### قاعدة مهمة

لا تحذف أو تتجاوز Middleware الحماية. إذا ظهر منع وصول، افحص:

- الدور Spatie والlegacy role.
- `approval_status` و`is_active`.
- `school_id` أو `managedSchool`.
- حالة المدرسة `ACTIVE/SUSPENDED`.
- حالة الربط والإشراف.
- صلاحية الوحدة المفوضة.
- `school_context_id` داخل request attributes.

## 8. هيكل الملفات الرئيسي

### Backend

- `app/Http/Controllers/Admin`: إدارة عامة، CMS، المدارس، الخطط، الاشتراكات، الإعدادات، المستخدمين.
- `app/Http/Controllers/Manager`: Dashboard، onboarding، requests، school structure، staff/users، tickets/subtasks.
- `app/Http/Controllers/School`: الهيكل الطلابي، الحضور، الإجازات، التخطيط الأكاديمي، الاختبارات، التقارير، الشهادات، المرفقات.
- `app/Http/Controllers/Api/School`: APIs داخلية لإدارة مستخدمي المدرسة، التفويضات، الإجازات، التقويم، quick setup.
- `app/Http/Controllers/Api/System`: إدارة أدوار وصلاحيات النظام.
- `app/Http/Controllers/Supervisor`: onboarding، dashboard، requests، tickets.
- `app/Http/Controllers/Staff`: dashboard وsubtasks.
- `app/Http/Requests`: FormRequests للـ auth، profile، school calendar، leaves، users، certificates، roles/permissions.
- `app/Policies`: `AssociationRequestPolicy`, `SchoolSupervisionRequestPolicy`, `SubtaskPolicy`, `TicketPolicy`.
- `app/Services`: خدمات مركزية للتحقق، الاشتراكات، pricing، student import، attendance initialization، leaves، academic planning، exams، dashboard analytics، certificates، attachments، ticketing، notifications.

### Frontend

- `resources/js/app.js`: Vue 3 + Inertia bootstrap، lazy pages، Ziggy injection، error panel، theme/input guards.
- `resources/js/Layouts`: `AdminLayout.vue`, `FrontLayout.vue`, `GuestLayout.vue`, `RoleLayout.vue`.
- `resources/js/Pages/Admin`: Dashboard، Users، Schools، Plans، Roles، Departments، Settings، Footer، SchoolDefaults.
- `resources/js/Pages/Manager`: Dashboard، Onboarding، Requests، SchoolStructure.
- `resources/js/Pages/School`: StudentStructure، StudentAttendance، StudentLeaves، AcademicPlanning، Exams، Reports، Certificates.
- `resources/js/Pages/Supervisor`: Dashboard، Onboarding، Requests.
- `resources/js/Pages/Staff`: Dashboard.
- `resources/js/Components`: مكونات مشتركة مثل `AppModal`, `AppActionDialog`, `AppInlineAlert`, `AppFilterBar`, `AppPageFeedback`, `AppStatePanel`, `AttachmentPanel`, `RealtimeNotifications`, ومكونات أكاديمية.
- `resources/js/Components/Dashboard/DashboardChartCard.vue`: بطاقة رسوم بيانية للداشبورد.
- `resources/js/composables/useThemeMode.js`: إدارة `theme-light` و`theme-dark`.
- `resources/css/app.css`: Tailwind وCSS theme tokens وتعديلات light/dark.

## 9. المسارات Routes

> `php artisan route:list --except-vendor` يعرض 317 route.

### Public routes

- `/` => `WelcomeController@index`
- `/p/{slug}` => `PageController@show`
- `/pricing` => `PricingController@index`
- `/plans` => `PlanController@index`
- `/media-files/{path}` و`/storage/{path}` => `PublicStorageController@show`
- `/certificates/verify/{token}` => `CertificateVerificationController`

### Admin routes

تحت prefix `admin` وmiddleware `auth`, `role:super_admin`:

- users approve/reject CRUD
- departments, roles, org structure roles
- CMS pages/components/media/header/footer/settings
- schools, countries, governorates, education types/stages, directorates
- school defaults templates and imports
- supervisor assignments
- plans and subscriptions
- admin dashboard

### System API-like routes

تحت `api/system` داخل `web.php` مع `auth`, `role:super_admin`, `throttle:api.system`:

- permissions CRUD
- roles CRUD
- sync role permissions

### School API-like routes

تحت `api/school` داخل `web.php` مع `auth`, `role:school_manager`, `managed_school`, `active_school_association`, `throttle:api.school` أو middleware وحدة مناسب:

- assignable roles
- org structure roles
- permission groups
- school users
- leave types, leaves, leave attachments
- school calendar settings and holidays
- quick setup status

### Manager routes

- onboarding routes تحت `auth`, `role:school_manager`.
- requests وassociation requests تحت `managed_school`.
- dashboard، structure، tickets، subtasks تحت `active_school_association`.

### School operational routes

- `/school/student-structure` محمي بـ `student_structure_access`.
- `/school/student-attendance` محمي بـ `student_attendance_access`.
- `/school/student-leaves` محمي بـ `student_leave_access`.
- `/school/academic-planning` محمي بـ `academic_planning_access`.
- `/school/exams` محمي بـ `school_exams_access`.
- `/school/reports` محمي بـ `school_reports_access`.
- `/school/certificates` محمي بـ `school_certificates_access`.
- `/school/attachments/{attachment}` محمي بـ `active_school_association`.

### Supervisor and Staff

- supervisor dashboard/onboarding/requests/tickets تحت `role:supervisor`.
- staff dashboard/subtasks تحت `role:staff` و`active_school_association`.

## 10. الوحدات المكتملة أو الموجودة فعليًا

- Auth وتسجيل الدخول والتسجيل.
- تسجيل مدير مدرسة ومشرف.
- موافقة Super Admin على الحسابات.
- CMS للموقع العام: صفحات، مكونات، ميديا، header/footer، settings.
- خطط وأسعار واشتراكات، مع snapshots وسعر مستخدم إضافي.
- إدارة المدارس والمديريات والبلدان والمحافظات وأنواع التعليم.
- Onboarding للمدير والمشرف.
- Requests/association/supervision flow.
- إدارة هيكل المدرسة الداخلي وStaff.
- تفويضات مدرسية ومجموعات صلاحيات.
- Student Structure: مراحل، صفوف، فصول، طلاب، Excel import.
- Attendance: حضور يومي، مرفقات، export CSV، daily initializer command.
- Student Leaves: أنواع إجازات، طلبات، اعتماد/رفض/إلغاء، مرفقات.
- Academic Planning: سنوات، ترمات، مواد، معلمين، إتاحة، جداول، مقررات معتمدة، إسناد.
- Exams: إعدادات، قوالب، بنك أسئلة، اختبارات، درجات، حالات.
- Reports: صفحة تقارير وتصدير.
- Certificates: قوالب، توقيعات، إصدار، طباعة/تحميل، تحقق عام token.
- Attachments: تخزين خاص school-scoped.
- Tickets/Subtasks.
- Notifications وRealtimeNotifications.
- Manager Dashboard analytics مع ApexCharts.
- Light/Dark theme عبر `theme-light` و`theme-dark`.

## 11. الاصطلاحات والأنماط

- Controllers غالبًا مقسمة حسب الدور أو الوحدة: Admin, Manager, School, Supervisor, Staff.
- FormRequests مستخدمة خصوصًا في APIs المدرسية، الإجازات، التقويم، الشهادات، النظام.
- Services مستخدمة للمنطق المركب مثل:
  - `SchoolCalendarService`
  - `AcademicPlanningValidationService`
  - `ExamSchedulingValidationService`
  - `DailyAttendanceInitializerService`
  - `StudentImportService`
  - `SchoolDashboardAnalyticsService`
  - `StudentLeaveService`
  - `TicketingService`
  - `AttachmentService`
- Multi-tenant isolation يتم غالبًا عبر `school_id`, middleware, controller checks, services, tests.
- Vue يعتمد غالبًا Composition API.
- Inertia هو قناة البيانات الأساسية بين Laravel وVue، وليس REST API مستقلة بالكامل.
- الثيم لا يعتمد فقط على Tailwind `dark:`، بل على classes `html.theme-light` و`html.theme-dark`.
- بعض ملفات JSX/React موجودة كآثار قديمة أو Breeze scaffold، لا تجعلها أساس تطوير الواجهة.

## 12. قواعد Multi-Tenant غير قابلة للكسر

- لا تقبل `school_id` من الواجهة كمصدر موثوق لوحدات المدرسة.
- استخرج المدرسة الحالية من المستخدم أو `managedSchool` أو middleware context.
- أي ID مرسل من الواجهة (`student_id`, `classroom_id`, `subject_id`, `exam_id`, `attachment_id`, `certificate_id`, `user_id`) يجب التحقق أنه تابع لنفس المدرسة قبل العرض أو التعديل أو الحذف.
- لا تستخدم fallback مثل أول مدرسة في قاعدة البيانات.
- لا تعرض options من مدارس أخرى.
- لا تعتمد على إخفاء الأزرار في Vue. التحقق النهائي يجب أن يكون server-side.
- عند إضافة فلاتر في dashboard أو التقارير، تحقق من ownership لكل filter ID.

## 13. الملفات والمرفقات

- `config/filesystems.php` يحتوي disk خاص:
  - `school_attachments` => `storage/app/private/school-attachments`
- public disk يستخدم `storage/app/public`.
- attachments العامة/المدرسية يتم التعامل معها عبر Controllers/Services لا عبر كشف مباشر عشوائي.
- `config/features.php` يحتوي MIME whitelists للميديا، التذاكر، إجازات الطلاب، حضور الطلاب، مرفقات المدرسة.
- يوجد دعم strict upload validation عبر feature flags مثل:
  - `FEATURE_STRICT_UPLOAD_VALIDATION`
  - `FEATURE_STRICT_STUDENT_LEAVE_ATTACHMENT_VALIDATION`
  - `FEATURE_STRICT_STUDENT_ATTENDANCE_ATTACHMENT_VALIDATION`
  - `FEATURE_STRICT_SCHOOL_ATTACHMENT_VALIDATION`

## 14. متغيرات البيئة المهمة

مستخرجة من `.env.example` و`config/features.php`:

```env
APP_NAME=Laravel
APP_ENV=local
APP_URL=http://localhost
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
DB_CONNECTION=sqlite
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local
VITE_APP_NAME="${APP_NAME}"

FEATURE_SYSTEM_ROLE_API=true
FEATURE_SCHOOL_ROLE_ASSIGNMENT=true
FEATURE_MANAGER_ASSIGNS_STRUCTURE_PERMISSIONS=true
FEATURE_ENFORCE_COURSE_OFFERINGS=false
FEATURE_STRICT_UPLOAD_VALIDATION=false
FEATURE_SYSTEM_API_RATE_LIMIT=60
FEATURE_SCHOOL_API_RATE_LIMIT=120
FEATURE_SHARED_UI_CACHE=false
FEATURE_SHARED_UI_CACHE_TTL=300
FEATURE_STUDENT_LEAVES=true
FEATURE_STUDENT_LEAVES_RETROACTIVE_MAX_DAYS=30
FEATURE_STUDENT_LEAVES_RETROACTIVE_GRACE_DAYS=30
FEATURE_STUDENT_LEAVES_ATTACHMENT_GRACE_DAYS=7
FEATURE_STUDENT_LEAVES_ENFORCE_ATTENDANCE=true
```

ملاحظة: `.env.example` عام ومحلي؛ بيئات الاختبار/الإنتاج قد تستخدم MySQL/MariaDB وقيم عربية مختلفة.

## 15. أوامر مهمة للتطوير

```bash
composer install
npm install

php artisan optimize:clear
php artisan route:list --except-vendor
php artisan migrate:status

npm run dev
npm run build

php artisan test
php artisan test --filter=StudentStructureAccessTest
php artisan test --filter=StudentAttendanceAccessTest
php artisan test --filter=AcademicPlanningAccessTest
php artisan test --filter=SchoolExamManagementTest
php artisan test --filter=ManagerDashboardAnalyticsTest
```

على Windows/XAMPP إذا لم يكن PHP في PATH:

```bash
C:\xampp\php\php.exe artisan test
C:\xampp\php\php.exe artisan route:list --except-vendor
```

## 16. الاختبارات الموجودة

- يوجد 95 ملف اختبار تقريبًا داخل `tests/Feature` و`tests/Unit`.
- اختبارات مهمة:
  - `AccountApprovalFlowTest`
  - `ActiveSchoolAssociationMiddlewareTest`
  - `ManagerSchoolOwnershipMiddlewareTest`
  - `StudentStructureAccessTest`
  - `StudentStructureTreeViewTest`
  - `StudentExcelImportTest`
  - `StudentAttendanceAccessTest`
  - `StudentLeaveManagementTest`
  - `AcademicPlanningAccessTest`
  - `SchoolExamManagementTest`
  - `SchoolUserRoleAssignmentApiTest`
  - `TicketWorkflowAuthorizationTest`
  - `SecurityHeadersTest`
  - `ArabicValidationMessagesTest`
  - `ManagerDashboardAnalyticsTest`
  - `SchoolAttachmentManagementTest`
  - `StudentCertificateFeatureTest`
  - `SubscriptionBillingAndSeatsTest`
- عند تعديل واجهة Vue يجب تشغيل `npm run build`.
- عند تعديل منطق school-scoped يجب إضافة أو تشغيل اختبار tenant isolation مركز.

## 17. ملاحظات مهمة لـ Claude Code

- تعامل مع Edaratek كمنصة SaaS تعليمية متعددة المدارس، لا كمشروع Laravel عادي.
- لا تستخدم React للتطوير الجديد؛ الواجهة النشطة Vue 3.
- لا تكسر `RoleLayout`, `AdminLayout`, `FrontLayout`, `GuestLayout`.
- حافظ على RTL والعربية والموبايل وlight/dark mode.
- لا تضف مكتبة UI جديدة بدون ضرورة واضحة.
- لا تكرر منطق التاريخ في Controller إذا يوجد Service مناسب.
- استخدم `SchoolCalendarService`, `AcademicPlanningValidationService`, `ExamSchedulingValidationService` عند العمل على التواريخ والجداول والاختبارات.
- لا تقبل `school_id` من Excel أو request كمصدر موثوق.
- عند بناء dashboard أو reports لا ترسل آلاف السجلات الخام للواجهة؛ استخدم aggregate queries.
- `routes/api.php` غير موجود حاليًا؛ API-like routes معرفة داخل `routes/web.php`.
- `README.md` هو README Laravel افتراضي وليس دليلًا خاصًا بالمشروع.

## 18. مشاكل أو ملاحظات معروفة من الفحص

- لا توجد TODO/FIXME واضحة حسب `rg "TODO|FIXME"`.
- توجد ملفات React/JSX قديمة في `resources/js` مثل `AuthenticatedLayout.jsx` ومكونات Breeze JSX. لا تعتمد عليها كواجهة نشطة.
- توجد نصوص عربية في بعض ملفات PHP/JS/lang تظهر Mojibake عند القراءة في الطرفية، مثل `lang/ar/messages.php` وبعض رسائل `User.php` و`app.js`. يحتاج ذلك مراجعة encoding يدوية قبل أي تعديل واسع على النصوص.
- `.env.example` يستخدم `APP_LOCALE=en` و`DB_CONNECTION=sqlite` رغم أن سياق المنتج عربي وقد تستخدم البيئات الفعلية MySQL/MariaDB.
- Workflow النشر يشير إلى `/home/test/public_html`; مسار الإنتاج `/home/edaratek/public_html` غير موجود في workflow الحالي.
- لا تخترع قواعد أعمال غير موجودة؛ إذا لم تجد علاقة أو شرط في الكود، اكتب `[يحتاج مراجعة يدوية]`.
