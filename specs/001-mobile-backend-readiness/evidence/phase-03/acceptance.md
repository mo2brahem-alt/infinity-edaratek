# قرار البوابة الفنية للمرحلة الثالثة

المراجع الفني: Codex تحت تفويض PM الصريح لتنفيذ T029–T040، بتاريخ 2026-11-08. الحكم فني للمصدر المنفذ، مع انتظار مراجعة PM للانتقال؛ لا ادعاء بمراجعة بشرية حدثت بالفعل.

البداية `e448618bffe70e10352b52bf20d3ffc6f43b14f5`؛ النهاية `7b8fd762bf7b61325fecf51f1dc5adba015d8221`؛ الفرع `001-mobile-backend-readiness` محليًا وعلى الأصل. أدلة التنفيذ: results.md وvalidated-runtime.json وsource-manifest.json، run42493350045/job128770255571.

| المهمة | الحالة | الدليل المحدد |
|---|---|---|
| T029 | COMPLETE | AccountApprovalMatrixTest: 48 صفًا و5 حالات أخرى؛ ترتيبَا manager/staff وsupervisor/staff، legacy/Spatie، النشاط وحالات الموافقة، لا ترقية school_admin، إصدار PAT ورفضه بحسب الأهلية |
| T030 | COMPLETE | PrivilegeEscalationTest، وحالات تعديل/إسناد المستخدم المدرسي: رفض السلطة المزورة، إزالة الأدوار والصلاحيات، دون كتابة غير مأذونة |
| T031 | COMPLETE | User helpers + AccountAccessEvaluator + EnsureApprovedAccount + UserApprovalService: أهلية واحدة، مستخدم حديث، صلاحية المنفذ داخل الخدمة |
| T032 | COMPLETE لنطاق الحساب | UserAuthorityFields والقوائم الحالية والخدمات؛ إنشاء المستخدم يتجاهل school_id وفق العقد القائم؛ مجموعات التفويض المدرسية لا تدّعي الإغلاق قبل T046 |
| T033 | COMPLETE | SafeAdminSeederTest: عدم التهيئة افتراضيًا، مدخلات صريحة، hashing وإعادة تشغيل وحساب غير مسؤول محفوظ |
| T034 | COMPLETE | SuperAdminSeeder دون قيم ثابتة أو reset/promotion لحساب موجود؛ seeding.md |
| T035 | COMPLETE | PasswordResetTest: نتيجة عامة متطابقة، broker cooldown، IP/identifier boundaries وتبديلها واستعادة النافذة، token reset مشروع |
| T036 | COMPLETE | PasswordResetLinkController + route limiter + config/mobile.php: 3/email/hour و10/IP/hour بمفاتيح HMAC |
| T037 | COMPLETE | debug الفعال false/true، RuntimeException وHTTP500 عبر v1، DTO عام وسجل منقح؛ H05 الحي غير مفحوص |
| T038 | COMPLETE | AccountApprovalFlow16، SchoolUserRoleAssignment30، CSRF حقيقي دون bypass الاختبار، سجل أحداث محدود دون قيم الطلب |
| T039 | COMPLETE | مركزة128/128، native16/16، كامل615/614/1 failure سابق فقط، صفر errors/skips |
| T040 | PASS | بوابة CI تقرأ JUnit المحمي، تتطلب كل فئات الإثبات وصفر فشل مركز أو انحدار جديد، ثم STOP |

## checklist والنطاق المؤجل

تثبت الأدلة CHK019 و020 و021 و022 و064 و070 للنطاق الحالي. CHK023–025 يبقى كامل إغلاقها مؤجلًا إلى Phase 4: حصر كل الطرق والموارد، parent/current-school، عزل مجموعات التفويض بعد النقل، ومصفوفة الحقول/السياق الأوسع. الأدلة الحالية ليست بديلًا لهذه الاختبارات.

CHK006 و067 و074 تبقى مفتوحة: توجد الآن أدلة debug/internal-500 محلية، لكن العقد الأشمل/بيئات التشغيل/سجلات بقية الأنظمة مؤجلة. CHK076 يبقى بانتظار مراجعة PM للمرحلة، وبقية بنود الجاهزية المستقبلية لا تُغلق. H03 عولج في المصدر والـfixtures؛ استخدام بيانات تاريخية حية لم يفحص ولم ينفذ تدويرًا.

## القرار والتوقف

`ACCOUNT_APPROVAL_HARDENING=PASS`

`AUTHORIZATION_HARDENING=PASS`

`PHASE_3_ACCEPTANCE=PASS`

`PHASE_3_PASS_AWAITING_PM_REVIEW`

`MOBILE_BACKEND_NOT_READY`

`PHASE_4_STARTED=NO`, `PUSH_TO_MAIN=NO`, `MERGE_PERFORMED=NO`, `DEPLOYMENT_PERFORMED=NO`, `STAGING_MODIFIED=NO`, `PRODUCTION_TOUCHED=NO`, `REAL_WHATSAPP_SENDS=0`.

لا T041 أو أي مهمة Phase 4. لا merge أو نشر أو فحص/تعديل بيئة حية أو إرسال حقيقي. ملفات Spec Kit والأدلة محدثة محليًا وغير مضمّنة في commits المصدر.
