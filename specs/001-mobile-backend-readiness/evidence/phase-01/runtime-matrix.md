# Phase1 — متطلبات المنصة المكتشفة

T001/T002:فحص ساكن مكتمل؛ ليس إثبات تشغيل PHP.

| العنصر | دليل المستودع والقرار |
|---|---|
| PHP | composer.json وcomposer.lock platform كلاهما ^8.2 |
| Laravel | القفل v12.50.0، يتطلب ^8.2 |
| Sanctum | القفل v4.3.0، يتطلب ^8.2؛ مصادقته مؤجلةPhase2 |
| PHPUnit | القفل11.5.51، يتطلب >=8.2 |
| Collision | القفل v8.8.3، يتطلب ^8.2.0 |
| extensions من القفل | ctype,dom,fileinfo,filter,hash,iconv,json,libxml,mbstring,openssl,pcre,phar,session,tokenizer,xml,xmlwriter |
| إضافات الاختبارات | PDO/pdo_mysql،pdo_sqlite/sqlite3،zip،GD؛ لا تخطٍ صامت لحالات MySQL/import/image |
| MySQL | الاختبارات/المهاجرات تحتوي سلوكًا خاصًا بـMySQL؛ AttendancePerformanceIndexesTest وSubscriptionHandshakeFlowTest لا يغطيهما SQLite وحده |
| Redis/cache | لا اعتماد إلزامي لتشغيل baseline العادي؛ CACHE_STORE=array وSESSION_DRIVER=array؛ إثبات التزامن لاحقًا يحتاج حالة مشتركة مستقلة، لاarray-only locks |
| queue | إعداد الإنتاج الافتراضيdatabase لا يُستخدم؛ profile الاختبارsync دونworkers مع HTTP fakes وشبكةداخلية |
| mail/notifications | MAIL_MAILER=array مع Mail/Notification fakes في superclass الصورة فقط |
| ملفات الاختبار | phpunit.xml يستخدمSQLite دونعزل cache؛ phpunit.mysql.xml يعزل مساراتmanifest لكنه لا يمنع وراثة host/credentials بذاته |
| Docker الحالي | صورة تشغيل:php:8.2-cli،COPY . .،Composer --no-dev؛ لم تتغير ولم تُستخدم |
| CI الحالي | deploy.yml للنشر؛ لم يتغير/يُستدعَ. تعريف اختباريدوي جديد محلي فقط |

المضيف:Windows11 Home Single Language64bit،10.0.26300/build26300؛ CLI لـPHP/Composer/Docker/Podman غير متاح. مساراتDocker/Podman الشائعة غير موجودة. WSL يبلغ أنه غير مثبت. الجلسة ليستAdministrator. CIM أبلغ VirtualizationFirmwareEnabled=false وSecondLevelAddressTranslationExtensions=false؛ هذه قراءة المضيف وليست إثباتًا مستقلًا لحالةBIOS/إمكان تثبيت hypervisor.

الأساسي:حاويةPHP8.2 +MySQL مؤقت. البديل:CI معادل؛ لم يتم تشغيله أو التحول إليه، إذ لاworkflow اختبار منشور من هذه التغييرات وcommit/push غيرمأذونين. لا اعتماد على staging/production. إصدارPHP/DB فيstaging غيرمتفحص ولا ادعاء تطابق.

راجعت سجلات manifests الرسمية لـDockerHub linux/amd64 بتاريخ2026-11-07:

| الصورة | digest مثبت | بيانات version فيmetadata، ليست تشغيلًا |
|---|---|---|
| php:8.2-cli-bookworm | sha256:26b388d187a5e592ef5e02c14d0f92dd9c761894ff0a5fe338183196471c4b4e | PHP8.2.35 |
| composer:2 | sha256:07cb23c4c9d528524a8b9a67c403d6ad7fef2268651eb1448e3874d40f28b789 | Composer2.11.1؛ يُنسخbinary فقط إلىPHP8.2 |
| mysql:8.0 | sha256:62fb722c78b24245ddff1796a0fcee4a49cc5b87e0aaaf20c92d1da9e0a2497b | MySQL8.0.46-1.el9 |

جلب manifests/configs العامة لا يشغّل صورة ولاComposer؛ لا صلاحية Composer/platform PASS دون تشغيل. apt لا يزال يحل الحزم عند البناء، لذلك يلزم تسجيلpackages/imageID الفعلي وإثبات إعادةالبناء قبل قبول reproducibility. Pin checkout11bd71901bbe5b1630ceea73d27597364c9af683 تحقق عبرGitHubAPI فقط، دونpush أوworkflowrun.
