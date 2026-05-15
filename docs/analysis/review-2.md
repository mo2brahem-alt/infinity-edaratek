# Review 2 - System Study Before Changes

## 1) Stack الحالي
- Backend: Laravel 12 + PHP 8.2.
- Frontend: Vue 3 + Inertia (`@inertiajs/vue3`) + Vite.
- Auth/RBAC: Laravel auth + Spatie Permission + legacy role fallback (`users.role`).
- Architecture: Monolith Laravel + Inertia pages (ليس SPA مفصول ولا Vue Router مستقل).

## 2) Routing الحالي

### Laravel `routes/web.php`
- الصفحة الرئيسية: `GET /` => `WelcomeController@index` (`welcome`).
- الخطط العامة: `GET /plans` => `PlanController@index` (`plans.index`) يعيد JSON.
- الأسعار: `GET /pricing` => `PricingController@index` (`pricing.index`).
- تسجيل الاشتراكات:
  - `register.supervisor` => `GET /auth/register/supervisor`
  - `register.manager.plan` => `GET /auth/register/manager`
  - `register.manager` => `GET /auth/register-manager` (legacy manager registration path)
- Super Admin group (prefix `admin`, middleware `auth + role:super_admin`) يحتوي المستخدمين/الأدوار/المدارس/الخطط/تعيين المشرفين.
- Profile routes خارج admin group:
  - `GET /profile` (`profile.edit`)
  - `PATCH /profile` (`profile.update`)

### Laravel `routes/api.php`
- غير موجود في المشروع حالياً.

### Vue Router
- غير مستخدم؛ التوجيه يتم عبر Inertia + Laravel routes.

## 3) Layout System في Vue
- `resources/js/Layouts/AdminLayout.vue`
  - Sidebar خاص بالسوبر أدمن (لوحة التحكم، المدارس، المستخدمين، الخطط...).
- `resources/js/Layouts/RoleLayout.vue`
  - Layout موحد لأدوار: `SUPERVISOR`, `SCHOOL_MANAGER`, `STAFF`.
- `resources/js/Layouts/FrontLayout.vue`
  - Layout الصفحات العامة (Home/Pricing).
- `resources/js/Layouts/GuestLayout.vue`
  - Layout صفحات الدخول/التسجيل.
- `resources/js/Layouts/AuthenticatedLayout.jsx`
  - ملف React legacy وغير داخل مسار Inertia Vue الحالي.

## 4) مكوّن الاشتراكات الحالي
- المكوّن الجاهز: `resources/js/Components/Shortcodes/PricingTable.vue`.
- Props الحالية: `data` object يحتوي عادة:
  - `title`, `subtitle`, `plans[]`, `design`, وألوان العرض.
- مصادر البيانات الحالية:
  - في الصفحة الرئيسية `resources/js/Pages/Welcome.vue`: المكوّن يُستخدم عبر shortcodes من `page_components` (CMS content).
  - في صفحة الأسعار `resources/js/Pages/Pricing/Index.vue`: الخطط تأتي server-side من `PricingController`.
- Endpoint جاهز للخطط:
  - `GET /plans?role_type=SUPERVISOR|SCHOOL_MANAGER` من `app/Http/Controllers/PlanController.php`.

## 5) مكان تنفيذ "تعيين المشرفين" في السوبر أدمن
- رابط/Section في الـ sidebar:
  - `resources/js/Layouts/AdminLayout.vue` (route: `admin.supervisor_assignments.index`).
- صفحة التعيين:
  - `resources/js/Pages/Admin/SupervisorAssignments/Index.vue`.
- Routes/API المستخدمة:
  - `GET /admin/supervisor-assignments`
  - `POST /admin/supervisor-assignments`
  - `DELETE /admin/supervisor-assignments/{supervisorAssignment}`
  - عبر `App\Http\Controllers\Admin\SupervisorAssignmentController`.
- تعيين الأدوار للمستخدمين موجود في إدارة المستخدمين:
  - `resources/js/Pages/Admin/Users/Index.vue`
  - `app/Http/Controllers/Admin/UserController.php` (`assignRole/syncRoles`).

## 6) صفحة تعديل البروفايل (Profile Edit) ولماذا تظهر قائمة السوبر أدمن
- الصفحة: `resources/js/Pages/Profile/Edit.vue`.
- route: `/profile` ضمن middleware `auth` العام (ليست داخل `admin` prefix).
- المشكلة الحالية: الصفحة تغلف نفسها دائماً بـ `AdminLayout` بشكل ثابت.
- النتيجة: أي مستخدم (مشرف/مدير/موظف) يرى Sidebar السوبر أدمن بالخطأ.

## 7) RBAC الحالي وكيف يتحدد ظهور الـ Sidebar
- Middleware alias: `role` => `App\Http\Middleware\CheckRole`.
- `CheckRole` يعتمد على:
  - Spatie role (`hasRole`) أو
  - legacy role column (`users.role`).
- الأدوار الرئيسية الحالية:
  - `super_admin`, `supervisor`, `school_manager`, `staff` (مع أدوار إضافية seed legacy).
- ظهور الـ sidebar يعتمد على الـ Layout المستعمل داخل صفحة Vue:
  - `AdminLayout` => Sidebar السوبر أدمن.
  - `RoleLayout` => Sidebar المستخدمين (غير السوبر أدمن).
  - middleware وحده لا يغير الـ layout تلقائياً.

## 8) Gap Analysis (ما سيتغير وما يجب إبقاؤه)

### ما سيتغير
1. إزالة/إخفاء Section "تعيين المشرفين" من واجهة السوبر أدمن (UI only).
2. دمج الاشتراكات في الصفحة الرئيسية باستخدام المكوّن الحالي مع ربط فعلي بـ `/plans`.
3. توجيه زر "اشتراك" حسب نوع الخطة:
   - `SUPERVISOR` => `register.supervisor`
   - `SCHOOL_MANAGER` => `register.manager.plan` (المسار المكافئ الحالي).
4. إصلاح Layout صفحة البروفايل ليصبح role-aware:
   - `super_admin` => `AdminLayout`
   - باقي الأدوار => `RoleLayout`.

### ما يجب إبقاؤه كما هو (Non-breaking)
1. عدم حذف أي route/controller/endpoint خاص بتعيين المشرفين.
2. عدم كسر User Management الحالية.
3. عدم تغيير أسماء routes الحالية الخاصة بالتسجيل.
4. عدم نقل أو حذف Profile routes؛ فقط إصلاح اختيار الـ layout داخل Vue.
5. إبقاء endpoint `/plans` الحالي واستعماله بدلاً من كسر التعاقدات القائمة.
