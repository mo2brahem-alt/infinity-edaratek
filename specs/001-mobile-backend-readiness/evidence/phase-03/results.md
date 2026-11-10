# نتائج Phase 3 المنفذة

التاريخ: 2026-11-08. المصدر النهائي المحلي/المنشور: `7b8fd762bf7b61325fecf51f1dc5adba015d8221`.

التشغيل النهائي: https://github.com/mo2brahem-alt/infinity-edaratek/actions/runs/42493350045 ، job `128770255571`. السجلات المنقحة الكاملة في validated-runtime.json وبصمات الملفات في source-manifest.json. لم تُنقل سجلات التطبيق أو JUnit الخام خارج الحاوية المؤقتة.

## التشغيل والعدّ

PHP 8.2.35، Composer 2.11.1، Laravel 12.50.0، PHPUnit 11.5.51، Sanctum المقفل 4.3.0، Node 22.23.4، MySQL 8.0.46، utf8mb4 / utf8mb4_0900_ai_ci. الإضافات وفحوص Composer العادية/strict/platform جميعها PASS. البيئة الأصلية APP_ENV=testing وAPP_DEBUG=false، والتجارب debug=true تغيّر الإعداد الفعال داخل التطبيق الاختباري فقط.

| المجموعة | TOTAL | PASSED | FAILED | ERRORS | SKIPPED | WARNINGS | Exit |
|---|---:|---:|---:|---:|---:|---:|---:|
| Phase 3 المركزة | 128 | 128 | 0 | 0 | 0 | 128 | 0 |
| انحدار Phase 2 | 16 | 16 | 0 | 0 | 0 | 16 | 0 |
| المجموعة الكاملة | 615 | 614 | 1 | 0 | 0 | 614 | 1 |

المركزة: 16.281 ثانية و886 assertion، native: 8.054 ثانية و113 assertion، الكاملة: 60.035 ثانية و3871 assertion، ذروة PHP الكاملة 146.50MB ضمن حد 512M. حدث انتهاء PHPUnit واحد لكل تشغيل ومتطابق مع exit؛ XML صالح UTF-8، دون DTD، وغير فارغ، وصفر أخطاء parsing. لا إصلاح للـbytes أو تغيير assertions للحصول على نتيجة.

بصمة JUnit المركزة SHA-256: `8a7f8f4fae2f6778be17fba90ce13c13d8bcb1b6e77224f796ad96840ba1071f`.

بصمة JUnit الكامل SHA-256: `1e16e16e2c97a47e0ec00bc3441c319004492f16fe76df55b5f4d8eca25320a4`.

## توزيع المركزة

| الملف/المجموعة | الحالات الناجحة |
|---|---:|
| AccountApprovalMatrixTest | 53، منها 48 صفًا لحالات الأدوار/الموافقة/النشاط مع login وطلب PAT حقيقي |
| EnvironmentSafetyTest | 4 |
| PrivilegeEscalationTest | 5 |
| WebSessionSafetyTest | 1 |
| SafeAdminSeederTest | 4 |
| PasswordResetTest | 7 |
| AccountApprovalFlowTest | 16 |
| SchoolUserRoleAssignmentApiTest | 30 |
| SubscriptionBillingAndSeatsTest | 8 |

أمر المركزة: `php -d memory_limit=512M vendor/bin/phpunit -c phpunit.mysql.xml --colors=never --log-junit=/tmp/baseline-junit.xml --log-events-verbose-text=/tmp/baseline-events.log tests/Feature/Mobile/Phase3 tests/Feature/SafeAdminSeederTest.php tests/Feature/Auth/PasswordResetTest.php tests/Feature/AccountApprovalFlowTest.php tests/Feature/SchoolUserRoleAssignmentApiTest.php tests/Feature/SubscriptionBillingAndSeatsTest.php`، داخل scratch المحروس فقط.

يتبعه انحدار ملفات Phase 2 الأربعة مباشرة، ثم أمر المجموعة الكاملة نفسه دون معاملات اختيار ملفات. المشغل الوحيد `tools/testing/run-tests.ps1` من workflow الاختبار المنفصل؛ لا استعمال deploy.yml أو إعدادات المضيف.

## الانحدار والتصنيف

المرجع قبل Phase 3: 539/538/1/0 errors/0 skips. النهائي: 615/614/1/0/0؛ الزيادة 76 حالة منفذة وليست رقمًا ثابتًا مفروضًا على PHPUnit.

الفشل الوحيد: `Tests\Feature\StudentExcelImportTest::test_valid_excel_import_creates_students_inside_current_school`، SQLSTATE42S22/MySQL1054. هو عيب whereKey السابق في Query Builder؛ لا تغييرات في اختبار Excel أو خدمة الاستيراد. لا يُخفي نجاح بوابة المرحلة هذا الفشل؛ workflow وexit العام يبقيان أحمر/1 عمدًا.

الجولة الأولى 42492373467 عند 4672661: مركزة109/109 ثم كامل604/602/2 failures؛ رُفضت البوابة لأن منع school_id في إنشاء المستخدم خالف عقد الويب الآمن القائم. الإصلاح في 64b801d أعاد تجاهل معرف العميل، مع بقاء المصدر الحقيقي للمدرسة/الاشتراك من المدير؛ لم يتغير اختبار المقاعد. الجولة الثانية 42492860324: مركزة122/122 وكامل609/608/1؛ البوابات PASS. الجولة النهائية توسع ترتيب supervisor وتختبر login لكل صف، ولذلك أصبحت المركزة128 والكاملة615.

كل تحذيرات PHP المسجلة تعود إلى المصدر المعروف `vendor/vlucas/phpdotenv/src/Store/File/Reader.php:73` بسبب غياب ملف env الحقيقي المقصود في الحاوية. يعرض ملخص المجموعة الكاملة أيضًا PHPUnit Deprecation واحدة من suite القائمة؛ ليست error أو skip ولا يجري إخفاؤها. لا raw warning أو exception أو secret في الأدلة.

## قاعدة البيانات والسلامة

لا migrations جديدة لـPhase 3. نجحت 113/113 migrations الحالية، وفحص PAT السابق rollback/reapply/repeat، وبقي صف المستخدم الوهمي غير المتعلق ثابتًا أثناء الاختبار. فحص T005 نجح مجددًا: 13 حالة مرفوضة قبل DB، HTTP/mail/notification fakes، منع خروج TEST-NET، وقفل MySQL عبر اتصالين مستقلين. هذه ليست شهادة لأي بيئة حية أو سباقات workers الخاصة بـPhase 6.

`ACCOUNT_APPROVAL_HARDENING=PASS`, `AUTHORIZATION_HARDENING=PASS`, `PHASE_3_ACCEPTANCE=PASS`, `MOBILE_AUTH_FOUNDATION_PASS=PASS`.
