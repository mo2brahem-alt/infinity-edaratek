# T112 — إعادة التقييم بعد دليل العمليات وتصحيحات PM

## التحديث الأحدث — إثبات CGI wrapper من الفحص الثالث،2026-11-09

اعتمدنا نجاح تنفيذ `server-readonly-h05-wiring.sh` بحسب مخرجات المشغل. [الدليل الثالث](probe-3-operator-evidence.json) يثبت FCGIWrapper في كلا VirtualHost blocks مع configured_executable=/bin/php-cgi8.3 وkind=CGI_WRAPPER_CONFIGURATION. السطران27 و70 هما PROXY_EXCLUSION_NOT_FPM_MAPPING، وليسا ربطًا بـFPM أو دليل كاش. لم يُرسل timestamp جديد أو اسم wrapper الفعلي؛ لا نختلقهما.

PHP_HANDLER=CGI_WRAPPER؛ PHP_EXECUTABLE=/bin/php-cgi8.3؛ FPM_NOT_APPLICABLE_FOR_THIS_VHOST=YES. أصبحت linked_pools=[] متوافقة مع المسار المثبت، لا عطلًا أو دليلًا ناقصًا يستدعي مزيدًا من اكتشاف FPM. **انتهى البحث عن FPM لهذا المسار.** كل إشارات FPM في سجل الفحصين السابقين أدناه تاريخية، وليست متطلبات حالية لهذا الـvhost. معيار H05 يتعلق بسلامة runtime الذي يخدم الموقع، لا بوجوب استخدام FPM.

**H05_STATUS=BLOCKED**: APP_DEBUG_CACHED_VALUE=true finding باقٍ ضمن H05؛ effective CGI error configuration ومنع الإفشاء المكافئ لم يثبتا. يطبق SR-013 حرفيًا: false أو حماية مكافئة مثبتة. إن ظهر display_errors=On في مصدر مرتبط يُسجل finding صريحًا مع حدود precedence/loading؛ لا يُخفى ولا يصنف حماية. لكن قيمة PHP INI وحدها لا تحسم طريقة عرض Laravel للأخطاء مع app.debug=true. أي PASS/FAIL نهائي يحتاج إثبات معيار عدم الإفشاء أو مخالفته، ولا نحول UNKNOWN إلى أي منهما بالتخمين.

**H01_STATUS=BLOCKED مستقلة**: ProxyPass exclusions تفسر السطرين فقط؛ لا تثبت منع bytes لملفات خاصة موجودة، تفويض المدرسة/الأب/العملية، أو منع legacy/static/CDN/cache. لا دليل جديد يغلق CHK034/040، حتى بعد حسم نوع PHP handler. لذلك **T112_STATUS=BLOCKED**، ويظل غير مؤشّر.

### الفحص الرابع المحدد: CGI configuration فقط

[server-readonly-h05-cgi.sh](server-readonly-h05-cgi.sh) جاهز ولم ينفذ على الخادم. يحدد wrapper الحرفي من FCGIWrapper/FcgidWrapper في كتل vhost الاختبار فقط، ويقرأه ضمن fcgi-bin/cgi-bin الخاصين بالمستخدم test، ثم يستخرج PHPRC وPHP_INI_SCAN_DIR و-d وأي mentions بيئية للإعدادات الأربعة فقط. لا source/eval أو shell expansion. القيم الديناميكية/الموروثة غير المثبتة تبقى UNKNOWN؛ لا تُطبع قيم environment أخرى. يقرأ php.ini الذي يشير إليه PHPRC فقط، وملفات *.ini المباشرة، بالترتيب، من scan dirs المصرح بها حرفيًا. لا scan recursive أو دليل FPM أو بحث عن default ini غير محدد. أي مسار/وجهة symlink خارج /home/test أو /etc/php/8.3 يُرفض دون قراءة محتواه.

المخرجات تفصل `static_configuration_evidence` ومصدر كل إعداد وCLI/env mentions عن `CGI_DISPLAY_ERRORS/CGI_DISPLAY_STARTUP_ERRORS/CGI_LOG_ERRORS/CGI_ERROR_REPORTING` التي تبقى UNKNOWN إلى أن يثبت effective loading/overrides. لا يستنتج ترتيب تنفيذ shell أو أن PHP_VALUE env ذكره وحده يغير إعداد PHP. أي -c/-n يظهر كـconfig-selection override غير محسوم؛ لا تُقرأ منه ملفات إضافية خارج النطاق المطلوب. قيمة APP_DEBUG في المخرجات هي الدليل المخبأ السابق، لا إعادة قياس.

لا توجد وسيلة runtime/error endpoint آمنة مخصصة ومثبتة في الأدلة؛ لذلك RUNTIME_ERROR_DISCLOSURE_EVIDENCE=UNAVAILABLE. هذا الفحص يجمع مصادر إعداد ذات صلة، ولا يعد مسبقًا أنه سيغلق H05. لا يشغل /bin/php-cgi8.3 أو أي PHP/Laravel/HTTP/FCGI، ولا ينشئ/يعدل ملفًا أوcache/config، ولا restart أو deployment. فُحص Bash وPython AST محليًا، و15 assertions على fixtures في الذاكرة غطت PHPRC وscan dir و-d وحجب الأسرار والمسارات الخارجية والقيم الديناميكية وإبراز مصدر يفعّل عرض الأخطاء في CGI_CONFIGURATION_FINDINGS؛ لا تشغيل على السيرفر.

[دليل PHP لملف الإعداد](https://www.php.net/manual/en/configuration.file.php) يوضح أن scan dir غير المحدد أو المكوّن الفارغ قد يعتمد على build default، وأن *.ini تقرأ بترتيب الأسماء؛ لا يُفترض هذا default من CLI. [دليل الخيارات](https://www.php.net/commandline.options) يوضح -d/-c/-n. نستخدم هذه القواعد لتمييز مصادر الإعداد فقط، دون تنفيذ PHP أو ادعاء إثبات live CGI behavior.

## سجل سابق — ما كان معروفًا بعد الفحص الثاني

## استلام نتيجة probe الثاني،2026-11-09 — قبل حسم CGI

اعتمد PM نجاح تنفيذ `server-readonly-h05.sh` يدويًا عبر SSH موثوق على بيئة الاختبار. [الدليل المنظم للفحص الثاني](probe-2-operator-evidence.json) منفصل عن مخرجات الفحص الأول. لم يرسل المشغل timestamp جديدًا؛ لا ننسب إليه وقت الفحص الأول أو نختلق وقت تنفيذ. لم ينفذ الوكيل اتصالًا أو تغييرًا على الخادم.

| الملاحظة الجديدة | ما تثبته | ما لا تثبته |
|---|---|---|
| enabled_site_link_matches_candidate=true | رابط enabled-site المحدد يطابق ملف vhost المرشح للاختبار | أن Apache الجاري حمّل آخر نسخة أو ما هو PHP handler الفعلي |
| ProxyPass فيblock1 وblock2 | وجود توجيهي ProxyPass في كتل vhost المختارة | هل هما exclusions أو reverse proxy أو FCGI؛ المعاملات غير متاحة. لا دليل أنهما cache أو PHP-FPM |
| test_owned_php83_fpm_workers=[] | لم يجد هذا الفحص worker مطابقًا لشروط اكتشافه | غياب خدمة FPM أو أن PHP يعمل بغير FPM؛ قد توجد إعدادات on-demand أو طريقة تشغيل مختلفة، وهي احتمالات غير مثبتة |
| socket_linked_test_pools=[] | لم يُثبت ربط socket بمسبح ضمن مرشحي الفحص | وجود/عدم وجود مسبح آخر أو قيمة display_errors |
| fpm_php_ini_error_settings=[] | لم تُجمع إعدادات INI مرتبطة؛ السكربت يقرأها فقط بعد إثبات pool | أن الإعدادات غير موجودة أو Off |

**H05_STATUS=BLOCKED**: التنفيذ ناجح، لكن FPM_RUNTIME_DEBUG_VALUE وFPM_DISPLAY_ERRORS ما زالا UNKNOWN، وRUNTIME_ERROR_DISCLOSURE_EVIDENCE=UNAVAILABLE. finding الإعداد المخبأ APP_DEBUG=true باقٍ. لم يثبت حتى الآن أي display_errors=Off مرتبط بالموقع، وحتى لو ثبت لاحقًا يُحسب كدليل جزئي لضوابط PHP، وليس وحده برهان منع Laravel debug disclosure وفق بديل SR-013.

**H01_STATUS=BLOCKED مستقلًا عن H05**: لا يحتوي الفحص الثاني على تنزيل QA مأذون/غير مأذون، أو دليل منع bytes لملف خاص موجود عبر old URLs/aliases/static/proxy/CDN، أو مراجعة مكتملة لضوابط خدمة الجذور الخاصة الواقعة تحت DocumentRoot. مطابقة enabled-site تضيف معلومة topology محدودة ولا تغلق CHK034/040. وجود public/storage وغياب public/media-files لا يصبحان PASS أو FAIL تلقائيًا. حتى نجاح H05 لاحقًا لا يغلق H01. **T112_STATUS=BLOCKED** لكلتا الفجوتين المستقلتين.

توجد ضرورة محددة لفحص ثالث صغير: probe الثاني أخفى معاملات ProxyPass ولم يفحص FcgidWrapper/FCGIWrapper؛ لذلك لم يميز exclusion من backend أو ربط CGI بديل. [server-readonly-h05-wiring.sh](server-readonly-h05-wiring.sh) يقتصر على أسطر الربط في vhost الاختبار، يصنف exclusions، ويخرج socket أو loopback backend دون credentials/path/query. إذا أشار vhost إلى wrapper تحت fcgi-bin/cgi-bin الخاصين بالاختبار، يقرأ فقط مسار executable الثابت وPHPRC/إعدادات الأخطاء المرتبطة، دون تشغيله. لا نفترض أن وجود wrapper يثبت FPM؛ CGI/FPM احتمالات منفصلة. يفحص فقط أسماء pool المرشحة المباشرة تحت PHP8.3 عند وجود socket، ويقبل pool فقط مع user=test وlisten مطابق. لا process sweep جديد ولا بحث recursive أو قراءة بيئات أخرى.

هذه نتيجة المراجعة السابقة قبل التنفيذ؛ فُحصت صيغة الفحص الثالث و9 assertions محليًا، ثم نفذه المشغل بنجاح وحسم CGI كما في التحديث الأحدث أعلاه. توصيات اكتشاف FPM السابقة انتهى انطباقها. توثيق [Apache mod_fcgid](https://httpd.apache.org/mod_fcgid/mod/mod_fcgid.html) و[PHP-FPM](https://www.php.net/manual/en/install.fpm.configuration.php) كان تفسيرًا للاحتمالات قبل ورود الدليل؛ لا يغيّر الدليل الجديد إلى FPM.

الدليل: مخرجات SSH المبلّغ عنها من المستخدم،2026-11-09 الساعة16:01:47 بتوقيت القاهرة (14:01:47Z)، ثم تصحيحات PM الصريحة في المرفق50c2ec14-bb74-41ca-915b-711a0bf3ce0a. [نسخة منظمة](operator-evidence.json). لا يُقدَّم كجلسة SSH أو قياس FPM نفذه الوكيل. لا اتصالات خادم أو تغييرات في هذه الجولة. المرفق الجديد يجيز فقط إعداد H05 probe محدد؛ لا فحص واسع أو نشر أو إصلاح.

## المعايير الفعلية والقرار

tasks.md T112: «FULL_SECURITY_REGRESSION_GATE_PASS requires T103–T111, all required executed blocking tests passing/failures=0, no mandatory P0 skips and no unresolved CRITICAL/HIGH mobile blocker; otherwise STOP with blockers, never weaken expectations.»

H01 الأصلي في Constitution: «Staff reply attachments use the public disk; known URLs can bypass school/record authorization. Actual private contents were not inspected.» معيار الإغلاق النشط هو spec.md SR-008/010 وFR-019–026 مع CHK034/040: تفويض كل تنزيل خاص ومنع bytes عبر public/legacy/preview/static aliases/cache مع إبقاء branding العام.

H05 الأصلي في Constitution: «Local env/cached config combine production mode with debug=true. Exposure depends on deployment; live production settings are UNCONFIRMED.»
النص الحاكم spec.md SR-013: «MUST لا تعتمد الاختبارات/إعداد الإطلاق على cached production credentials، ولا تكشف الأخطاء تفاصيل debug؛ debug المتاح للمستخدم في إعداد الإطلاق false أو ما يكافئ منع الإفشاء، ويثبت ذلك غير إنتاجيًا. حالة live لا تُفترض.» تكمله SR-018 لعزل DB/cache/queue/mail ومنع provider egress في الاختبارات، وSR-020 للأخطاء/المعرفات دون traces أو أسرار. CHK074/083 لا تسمح باستنتاج live safety من local tests.

**النتيجة الحالية: H01=BLOCKED، H05=BLOCKED، T112=BLOCKED.** H05 لا يفرض false وحده دون بديل: النص يقبل حماية مكافئة تمنع الإفشاء. APP_DEBUG=true finding حقيقي ومقلق ضمن H05؛ يثبت فقد مسار الإعداد الآمن الافتراضي، لكنه لا يثبت وحده effective CGI app.debug أو أن خطأ web كشف تفاصيل، ولا ينفي بمفرده وجود الحماية المكافئة. FPM لا ينطبق على هذا المسار المثبت. لا PASS ولا FAIL مؤكد لشرط الإفشاء الكامل من هذه العينة؛ runtime غير المثبت يبقى UNKNOWN.

## H01: مطابقة كل criterion بالدليل

| Criterion / مرجع | الدليل الفعلي | النتيجة المحدودة / ما بقي |
|---|---|---|
| CHK034 جذور التخزين الخاصة الفعلية | local وschool_attachments تحت storage/app/private؛755 للمجلد الجذري،2775 للمجلد الخاص وملكيتها test:test | أسماء الجذور مثبتة؛ عمل التطبيق/CGI والمستخدم الفعال وصلاحيات المسار كاملة غير مثبتة لأن الفحص root. اسم private لا يثبت منع HTTP. |
| CHK034 فصل private عن كل serving root/alias | DocumentRoot=/home/test/public_html؛ الجذور الخاصة أيضًا تحت هذا المسار | احتواء الجذور الخاصة داخل DocumentRoot ثابت، وهو مؤشر خطر يحتاج قواعد منع/rewrites فعلية. لا دليل bytes ولا حكم تسريب مؤكد. لا تُعتبر «خارج webroot». |
| SR-008 تفويض parent/school/operation لكل تنزيل | لا اختبار QA مأذون/غير مأذون أو session/PAT في مخرجات التشغيل | UNKNOWN؛ اختبارات الفرع المصطنعة لا تشهد للنسخة المنشورة. |
| CHK040 منع /storage والنسخ الفيزيائية وsymlinks | public/storage رابط إلى storage/app/public | الرابط العام قد يكون مقصودًا للbranding؛ وجوده وحده ليس FAIL ولا PASS للخصوصية. محتواه وقواعد منع الملفات الخاصة غير مثبتين. لا حذف أو storage:link مطلوب تلقائيًا. |
| CHK040 /media-files وlegacy/preview | public/media-files غير موجود في filesystem | لا يثبت غياب virtual alias/route/Include/ProxyPass أو أن المسار مطلوب فيزيائيًا. ليس خطأ إعداد مثبتًا بذاته. |
| CHK040 static/proxy/CDN/cache | الفحص الثالث حسم السطرين27 و70 كـPROXY_EXCLUSION_NOT_FPM_MAPPING؛CACHE_STORE=database | exclusions ليست إثبات cache أو منع serving لملف خاص. cache التطبيق مختلف عن HTTP/CDN cache. CDN class D/UNKNOWN؛ لا نفترض وجود CDN أو عدمه. |
| SR-010 منع old URLs مع إبقاء branding | لا QA bytes أو authorized/private no-store/branding smoke على ملف معروف | UNKNOWN.404 لاسم غير موجود أو route غير مثبت لا يحقق ذلك. |

لا يكفي دليل إضافي شكلي لإغلاق H01؛ نحتاج serving/access proof للنسخة المناسبة. لا توجد من العينة وحدها ثغرة كشف ملف مثبتة تستدعي تغيير alias تلقائيًا. DocumentRoot الحالي انحراف عن نمط Laravel المعتاد القائم على public، لكن فعالية rewrites وDirectory restrictions ما زالت مجهولة.

## H05: مطابقة كل criterion بالدليل

| Criterion / مرجع | الدليل الفعلي | الحكم |
|---|---|---|
| SR-013 debug=false أو منع إفشاء مكافئ | APP_ENV=production وAPP_DEBUG=true من cached/source config حسب تأكيد PM | finding H05-CFG-01 مثبت في الإعداد. production وحده ليس خطأ ولا يلزم تغييره إلى staging. البديل المكافئ وCGI effective app.debug وweb exception behavior UNKNOWN، لذا H05 BLOCKED. |
| CHK074 قيمة runtime وإعداد cache | ملف bootstrap/cache/config.php موجود؛CLI8.3.6؛probe الثالث أثبت CGI wrapper مع /bin/php-cgi8.3 في كلا الكتلتين | نوع handler معلوم؛ effective CGI settings/loading غير مثبتة. FPM غير منطبق ولا حاجة لاكتشاف pool. لا نعيد تصنيف نجاح تنفيذ probe كنجاح H05. |
| SR-013/018 عزل cached credentials والDB/cache/queue/mail | database cache/queue وsmtp mail أسماء drivers فقط؛ لا backend operation ولا بيانات عزل | الاختيارات ليست عيبًا بذاتها ولا تفرض Redis/array في staging. الانفصال عن الإنتاج وتوافر backend غير مثبتين؛ لا اختبارات أو اتصال mail/queue/DB هنا. |
| SR-020 وCHK074 redaction/request IDs في خطأ web | / و/up يعيدان200، /api/v1/me يعيد404؛ لا exception آمن مخصص | RUNTIME_ERROR_DISCLOSURE_EVIDENCE=UNAVAILABLE. نجاح health ليس اختبار500؛ لا fuzzing/تصنيع خطأ/تفعيل debug أو route جديد. |
| Apache/CGI overrides | CGI wrapper وexecutable مثبتان، لكن path الفعلي وPHPRC وscan dir و-d وإعدادات INI لم تُرسل | probe الرابع محدد لهذه المصادر فقط. PHP display_errors مختلف عن Laravel app.debug؛off وحده لا يثبت الحماية المكافئة. |
| نقل HTTPS | TLS_VERIFY=0 في ملاحظة المستخدم؛ السكربت السابق استعمل ssl_verify_result، بلا insecure | يعني نجاح التحقق المطلوب لشهادة TLS، وليس تعطيله. ليس finding أمنيًا ولا دليلًا على redaction. |

[توثيق curl الرسمي](https://curl.se/docs/manpage.html#--write-out) يعرّف ssl_verify_result=0 كنتيجة تحقق ناجحة. [توثيق Laravel12](https://laravel.com/framework/docs/12.x/deployment#debug-mode) يوصي بإيقاف debug في الإنتاج لتقليل خطر الإفشاء. المعيار المحلي المذكور أعلاه هو المرجع الحاكم للقرار، ولا تُستبدل صيغة «أو ما يكافئ» بتوصية أشد ثم يُدّعى أنها نص spec.

## فرق النسخة وحماية عقد API

TEST_ENVIRONMENT_HEAD=12b9d5326d942acda55309aac05c6cee1bf928b0.
FEATURE_HEAD=fbc63a4615f48ac0f832d91945d9652aeabef466.
TEST_ENVIRONMENT_VERSION_DRIFT=YES.

الـcommit المنشور المبلّغ عنه ancestor لفرع الميزة، لكن مساواة النسخة أو قصد إبقاء ancestor وفق deployment history لم يُثبتا. أصل Git لا يثبت تطابق working tree أو CGI runtime/OPcache. لا نعدل النسخة ولا ننشر لمعالجة هذا الفرق.

فحص Git المحلي read-only أظهر أن12b9d53 لا يحتوي routes/api.php وbootstrap/app.php فيه لا يسجّل api routing. الفرع الحالي يسجل routes/api.php، prefix v1، GET /me، واختبارات Phase7 تشترط401 UNAUTHENTICATED للمجهول. لذلك404 متسق مع مصدر server HEAD المعلن؛ ليس regression لعقد Phase7/8 ولا FAIL جديدًا لاختبارات الفرع. لا نستعمل هذه البيئة كشهادة API v1 الميزة.

## Finding الإعداد وخطة تصحيح غير منفذة

| البند | التقييم |
|---|---|
| الحالي | APP_ENV=production،APP_DEBUG=true في cached/source snapshot؛ CGI effective value UNKNOWN. FPM غير منطبق. |
| المطلوب المقترح | إبقاء APP_ENV المعتمد، APP_DEBUG=false، واتساق إعدادات المصدر والكاش وCGI على تطبيق الاختبار، مع إثبات redaction. النص يسمح بدلًا منه بمنع إفشاء مكافئ مثبت؛ هذا البديل غير متاح حاليًا. |
| علاقته بالspec | H05 مباشرة وSR-013/020 وCHK074؛ مسار false غير مستوفى، ومسار الحماية المكافئة UNKNOWN. ليس خارج نطاق التدقيق ولا كشف أسرار مؤكدًا للمستخدم. |
| التعديل المقترح بعد اعتماد منفصل فقط | تغيير APP_DEBUG وحده إلى false بوسيلة إدارة البيئة المعتمدة للاختبار، ثم إعادة بناء config cache المخصص للتطبيق عبر php artisan config:cache بعد مراجعة boot side effects والعزل وهوية التطبيق. **ليس جزءًا من probe أو أمرًا منفذًا/معتمدًا الآن.** لا cache:clear أو optimize:clear أو flush شامل. |
| المخاطر | boot أثناء config:cache، صلاحيات وملكية cache، اختلاف env/cached values، حالة CGI processes/OPcache طويلة العمر إن وجدت، حاجة نافذة عمليات. لا نغيّر env أو خدمة مشتركة أو مستخدم إنتاج. |
| rollback | خطة عمليات مع snapshot خاص وإصدار config معلوم قبل التنفيذ؛ إبقاء debug=false عند التعافي، وعدم إعادة cached debug=true تلقائيًا. إذا احتاج التعافي تغييرًا أمنيًا أو restart فهو gate مستقل. لا backup أو file write أو rollback نُفذ هنا. |
| PM approval | نعم، لإصلاح الإعداد وتحديث config cache وأي restart/deployment لاحق. التفويض الحالي إثبات read-only فقط. |

لا تصحيح مثبتًا مطلوبًا لـpublic/media-files أوcache driver أوrouting/API من هذه العينة. DocumentRoot/السطران27 و70 يحتاجان تفسيرًا قبل اقتراح تعديل محدد؛ لا تغيير webroot أو alias بالتخمين.

## Probe الثاني: H05 فقط — سجل تاريخي قبل إثبات CGI

[server-readonly-h05.sh](server-readonly-h05.sh) يقرأ vhost الاختبار المحدد وenabled-site linkage، يلتقط socket/handler وallowlisted overrides، ثم يفحص فقط PHP8.3 FPM worker metadata للمستخدم test وpool files المرشحة مباشرة، ولا يصدّر pool إلا إذا طابق user=test وlisten socket المأخوذ من vhost. يقرأ فقط أربعة إعدادات أخطاء من php.ini/conf.d المرتبطة بعد تحقق الربط. لا /etc recursive dump، ولا /proc/environ، ولا secrets أو raw directives، ولاPHP/Laravel/HTTP/FCGI execution.

الـprobe لا يمكنه قياس Laravel app.debug داخل FPM دون وسيلة live موجودة أصلًا. لا يوجد لدينا endpoint مخصص وآمن مثبت لخطأ اختبار، و/up ليس بديلًا. لذلك يصرح runtime UNKNOWN وerror-disclosure UNAVAILABLE، ولا ينشئ ملف PHP أوroute أوخطأ. Includes غير المحسومة أو pool mapping غير المكتشف تبقى UNKNOWN بدل بحث واسع.

تم فحص Bash syntax وPython AST محليًا، مع7 فحوص allowlist/redaction قبل التسليم. **تحديث:** نفذ المشغل الفحصين الثاني والثالث بنجاح؛ حسم الثالث CGI وبقيت قيم CGI runtime غير مثبتة. لا تُنسب النتائج إلى تشغيل الوكيل. السكربت الرابع فقط هو الذي لم ينفذ على السيرفر بعد.

الاختبارات السابقة لا تتغير:462/462 focused و724/725 في كلا runner، Excel وحده،0errors/0skips؛ هذه نتائج CI السابقة لا إعادة تشغيل في هذه الجولة. لا application/API/schema/test-source changes تستدعي نسبة نتائج جديدة إليها. T112 يظل غير مؤشّر، وT103–T111 مقبولة من PM، وPhase9 لم تبدأ.

```text
H01_STATUS=BLOCKED
H05_STATUS=BLOCKED
T112_STATUS=BLOCKED
APP_DEBUG_CACHED_VALUE=true
PHP_HANDLER=CGI_WRAPPER
PHP_EXECUTABLE=/bin/php-cgi8.3
FPM_NOT_APPLICABLE_FOR_THIS_VHOST=YES
CGI_WRAPPER_PATH=UNKNOWN
CGI_PHPRC=UNKNOWN
CGI_PHP_INI_SCAN_DIR=UNKNOWN
CGI_DISPLAY_ERRORS=UNKNOWN
CGI_DISPLAY_STARTUP_ERRORS=UNKNOWN
CGI_LOG_ERRORS=UNKNOWN
CGI_ERROR_REPORTING=UNKNOWN
RUNTIME_ERROR_DISCLOSURE_EVIDENCE=UNAVAILABLE
TEST_ENVIRONMENT_HEAD=12b9d5326d942acda55309aac05c6cee1bf928b0
FEATURE_HEAD=fbc63a4615f48ac0f832d91945d9652aeabef466
TEST_ENVIRONMENT_VERSION_DRIFT=YES
NO_CONFIG_CHANGES=YES
NO_APPLICATION_CHANGES=YES
NO_DEPLOYMENT=YES
NO_SERVICE_RESTARTS=YES
PRODUCTION_TOUCHED=NO
REAL_FILES_MIGRATED=0
REAL_WHATSAPP_SENDS=0
PHASE_9_STARTED=NO
MOBILE_BACKEND_NOT_READY
```

