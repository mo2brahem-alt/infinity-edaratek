# Edaratek Project Details

> **قرار PM نافذ 2026-11-09:** WhatsApp خارج المشروع نهائياً. الإشعارات app/web فقط، وpush حيث يوجد مطلب معتمد قائم؛ لا delivery tests أو provider configuration. أي نص WhatsApp/OTP/control/kill-switch قديم أدناه HISTORICAL/SUPERSEDED وليس شرط قبول نشطاً. المرجع docs/notification-policy.md وevidence/phase-06/scope-decision.md. لا staging/production أو Phase8؛ actual aliases/CDN تبقى UNKNOWN.

> PM2026-11-09: Phase6 APPROVED; Phase7 T089-T102 authorized on the existing feature branch with isolated CI commits/pushes. STOP at Phase7 PM review. No Phase8 or environment/deployment action.

## 1. نظرة عامة عامة

- **اسم المشروع المحلي:** `infinity-edaratek`
- **اسم المنصة:** Edaratek
- **نوع المشروع:** نظام SaaS تعليمي متعدد المستأجرين (multi-tenant)
- **الهدف:** إدارة المدارس، المستخدمين، الاشتراكات، الهيكل الطلابي، الحضور، الإجازات، التخطيط الأكاديمي، الاختبارات، التقارير، التذاكر، الشهادات، والمرفقات.
- **الواجهة النشطة:** Vue 3 + Inertia + Vite.
- **واجهة المستخدم:** عربية RTL افتراضيًا.
- **جذر Laravel:** يحتوي على `artisan`, `composer.json`, `app`, `routes`, `resources`, `database`.

## 2. التقنيات الأساسية

- **PHP:** `^8.2`
- **Laravel:** `^12.0`
- **Frontend:** Vue `^3.5.28`, Inertia Vue `^2.3.13`, Vite `^7.0.7`
- **CSS:** Tailwind CSS `^3.2.1` مع `@tailwindcss/forms`
- **Routing helper:** Ziggy `^2.0`
- **Authentication:** Laravel Sanctum `^4.0`، Laravel Breeze موجود كاعتماد تطوير.
- **RBAC:** Spatie Permission `^6.24`
- **Charts:** ApexCharts `^5.12.0`, Vue3 ApexCharts `^1.11.1`
- **Icons:** `lucide-vue-next` للمكونات Vue.
- **HTTP client:** Axios.

## 3. الاعتماديات

### composer.json

- `inertiajs/inertia-laravel:^2.0`
- `laravel/framework:^12.0`
- `laravel/sanctum:^4.0`
- `laravel/tinker:^2.10.1`
- `spatie/laravel-permission:^6.24`
- `tightenco/ziggy:^2.0`

### package.json

#### dependencies
- `@inertiajs/vue3:^2.3.13`
- `@vitejs/plugin-vue:^6.0.4`
- `apexcharts:^5.12.0`
- `lucide-react:^0.563.0` (أثر قديم، واجهة Vue نشطة)
- `lucide-vue-next:^0.563.0`
- `vue:^3.5.28`
- `vue3-apexcharts:^1.11.1`

#### devDependencies
- `@headlessui/react:^2.0.0`
- `@inertiajs/react:^2.0.0`
- `@tailwindcss/forms:^0.5.3`
- `@tailwindcss/vite:^4.0.0`
- `autoprefixer:^10.4.12`
- `axios:^1.11.0`
- `concurrently:^9.0.1`
- `laravel-vite-plugin:^2.0.0`
- `postcss:^8.4.31`
- `tailwindcss:^3.2.1`
- `vite:^7.0.7`

## 4. أوامر التثبيت الأساسية

- `composer install`
- `npm install`
- `npm run build`
- `npm run dev`
- `php artisan test`
- `php artisan route:list --except-vendor`
- `php artisan migrate:status`

## 5. سكربتات composer

- `setup`: تثبيت composer، نسخ `.env`, توليد مفتاح، تشغيل الميغراشن، تثبيت npm وبناء Vite.
- `dev`: تشغيل السيرفر، قائمة الطابور، pail، وvite بالتوازي.
- `test`: مسح الكاش ثم تشغيل الاختبارات.

## 6. إعدادات الميزات والمراسلات

### `config/features.php`

- `course_offerings.enforce_for_scheduling`
- `api.system_rate_limit_per_minute`
- `api.school_rate_limit_per_minute`
- `shared_ui.cache_enabled`
- `shared_ui.cache_ttl_seconds`
- `uploads.strict_validation_enabled`
- `uploads.strict_student_leave_attachment_validation`
- `uploads.strict_student_attendance_attachment_validation`
- `uploads.strict_school_attachment_validation`
- `uploads.media_mime_types`
- `uploads.ticket_attachment_mime_types`
- `uploads.student_leave_attachment_mime_types`
- `uploads.student_attendance_attachment_mime_types`
- `uploads.school_attachment_mime_types`
- `student_leaves.enabled`
- `student_leaves.retroactive_max_days`
- `student_leaves.retroactive_grace_days`
- `student_leaves.attachment_grace_days`
- `student_leaves.enforce_attendance_leave_state`
- `attendance.daily_initialization_enabled`
- `attendance.daily_initialization_time`
- `rbac.system_role_api_enabled`
- `rbac.school_role_assignment_enabled`
- `rbac.manager_assigns_structure_permissions`

## 7. بنية قواعد البيانات والكيانات الرئيسية

### جداول النظام الأساسية
- `users`, `password_reset_tokens`, `sessions`
- `cache`, `cache_locks`
- `jobs`, `job_batches`, `failed_jobs`
- `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`
- `settings`, `audit_logs`, `status_history`, `notifications`

### جداول CMS
- `pages`, `page_components`
- `media`
- `header_menus`, `header_items`
- `footer_columns`, `footer_items`

### جداول المدارس والجغرافيا
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

### جداول التنظيم الداخلي والأدوار
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

### جداول الامتحانات وبنك الأسئلة
- `school_exam_settings`
- `school_exam_templates`
- `school_exams`
- `school_question_bank_items`
- `school_question_options`
- `school_exam_questions`
- `school_exam_student_scores`
- `school_exam_status_logs`

### جداول القوالب والمحتوى الافتراضي
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

## 8. العلاقات الرئيسية في النماذج

- `School` مرتبط بـ `users`, `subscriptions`, `stages`, `classrooms`, `students`, `studentAttendances`, `leaveTypes`, `studentLeaveRequests`, `calendarSetting`, `holidays`, `academicYears`, `terms`, `subjects`, `classSchedules`, `courseOfferings`, `teachingAssignments`, `exams`, `questionBankItems`, `examScores`, `certificateTemplates`, `studentCertificates`, `certificateSignatures`, `tickets`.
- `School` ينتمي إلى `EducationalDirectorate` وله `manager_user_id` و`supervisor_id`.
- `User` يدعم Spatie roles و legacy role داخل `users.role`.
- `SchoolStudent` ينتمي إلى مدرسة وفصل وله حضور وإجازات ودرجات ومرفقات وشهادات.
- `SchoolClassroom` ينتمي إلى مدرسة ومرحلة وله طلاب، حضور، جداول، مقررات، اختبارات.
- `SchoolSubject`, `SchoolCourseOffering`, `SchoolTeachingAssignment`, `SchoolClassSchedule` تشكل منطق التخطيط الأكاديمي.
- `SchoolExam` يرتبط بالمدرسة، القالب، الترم، المرحلة، الصف، المادة، المعلم، الأسئلة، النتائج، حالة الامتحان.
- `Attachment` علاقة بوليمورفيك `attachable` مع `school_id` و `uploaded_by`.
- `CertificateTemplate`, `SchoolCertificateSignature`, `StudentCertificate` كلها مرتبطة بمدرسة واحدة.

## 9. الأدوار وحقوق الوصول

### أدوار النظام الأساسية
- `super_admin`
- `supervisor`
- `school_manager`
- `staff`
- `teacher`
- `student`
- `parent`

### آلية الصلاحيات
- Spatie Permission مستخدم عبر `App\Models\User` و `HasRoles`.
- هناك دعم legacy role في حقل `users.role`.
- `User::hasSystemRole()` يجمع بين Spatie وال legacy.
- حسابات `school_manager` و `supervisor` تحتاج موافقة من Super Admin عبر `approval_status`.
- توجد أعمدة تشغيلية على `users` مثل: `can_manage_student_structure`, `can_manage_student_attendance`, `can_manage_academic_planning`, `can_manage_student_leaves`, `can_manage_leave_types`, `can_manage_school_calendar`, `can_manage_school_holidays`.
- يوجد `App\Support\SchoolPermissionCatalog` مع صلاحيات مدرسة مثل:
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

## 10. Middleware والحماية

### عناصر middleware الرئيسية
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

### middleware عالمية مهمة
- `HandleInertiaRequests`
- `AddLinkHeadersForPreloadedAssets`
- `AddSecurityHeaders`
- `EnsureApprovedAccount`

### قواعد سلامة أساسية
- لا تعتمد على `school_id` من الواجهة. استخرج المدرسة الحالية من المستخدم أو `managedSchool` أو سياق middleware.
- تحقق دائمًا من ملكية السجلات قبل العرض/التعديل/الحذف.
- لا تكفي حماية الزر في الواجهة، يجب أن تكون الفحص server-side.
- تحقق من `approval_status`, `is_active`, حالة المدرسة، ارتباط الإشراف و `school_context_id`.

## 11. هيكل الملفات الأساسي

### Backend
- `app/Http/Controllers/Admin`
- `app/Http/Controllers/Manager`
- `app/Http/Controllers/School`
- `app/Http/Controllers/Api/School`
- `app/Http/Controllers/Api/System`
- `app/Http/Controllers/Supervisor`
- `app/Http/Controllers/Staff`
- `app/Http/Requests`
- `app/Policies`
- `app/Services`
- `app/Support`

### Frontend
- `resources/js/app.js`
- `resources/js/Layouts`: `AdminLayout.vue`, `FrontLayout.vue`, `GuestLayout.vue`, `RoleLayout.vue`
- `resources/css/app.css`
- `vite.config.js`
- `tailwind.config.js`

### ملفات التكوين الرئيسية
- `config/app.php`
- `config/auth.php`
- `config/database.php`
- `config/filesystems.php`
- `config/features.php`
- `config/permission.php`
- `config/session.php`

## 12. مسارات رئيسية

### عامة
- `/` => `WelcomeController@index`
- `/p/{slug}` => صفحة المحتوى العام
- `/pricing` => `PricingController@index`
- `/plans` => `PlanController@index`
- `/media-files/{path}` و `/storage/{path}` => `PublicStorageController@show`
- `/certificates/verify/{token}` => `CertificateVerificationController`

### Admin
- محمي بـ `auth`, `role:super_admin`
- `users`, `departments`, `roles`, `org-structure-roles`
- `pages`, `components`, `media`, `header`, `footer`
- `settings`
- `schools`, `countries`, `governorates`, `education_types`, `education_stages`, `directorates`
- `school-defaults`
- `supervisor-assignments`
- `plans`
- `subscriptions`
- `dashboard`

### System API
- محمي بـ `auth`, `role:super_admin`, `throttle:api.system`
- `/api/system/permissions`
- `/api/system/roles`

### School API
- محمي بـ `auth`, `role:school_manager`, `managed_school`, `active_school_association`, `throttle:api.school`
- `/api/school/roles/assignable`
- `/api/school/org-structure-roles`
- `/api/school/permission-groups`

## 13. وثائق وملاحظات داخلية

### مجلد `docs/`
- `academic-planning-stage-classroom-relocation.md`
- `academic-planning-subject-improvements.md`
- `analysis/`
- `data-integrity-delete-update.md`
- `org-structure-quick-add.md`
- `org-structure-role-templates.md`
- `qa-security-audit.md`
- `requirements/`
- `student-structure-updates.md`

## 14. ملاحظات مهمة

- يوجد ملفات React/JSX قديمة في `resources/js` مثل `AuthenticatedLayout.jsx` لكنها ليست الواجهة النشطة.
- `.env.example` يستخدم `DB_CONNECTION=sqlite` و `APP_LOCALE=en` رغم أن المشروع يدعم العربية.
- تجنب إضافة أسرار إلى المستودع.
- إذا ظهر منع وصول، افحص دور المستخدم، موافقة الحساب، المدرسة الحالية، وحالة الإشراف.

## 15. توصية لتشغيل أولي

1. انسخ `.env.example` إلى `.env`.
2. تأكد من ضبط `DB_CONNECTION` و `FILESYSTEM_DISK` و `APP_URL`.
3. شغل `composer install` و `npm install`.
4. شغل `php artisan key:generate` و `php artisan migrate --force`.
5. شغل `npm run build` أو `npm run dev`.

---

هذا الملف معد لتقديم نظرة شاملة حول هيكل المشروع، التقنيات، مسارات الوصول، قواعد البيانات، والاعتبارات الأمنية لبيئة تطوير أو تشغيل جديدة.
