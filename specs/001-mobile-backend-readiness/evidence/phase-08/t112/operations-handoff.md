# T112 — الدليل المطلوب من عمليات بيئة الاختبار

هذه قائمة تسليم وليست أوامر نُفذت على الخادم. الهدف الوحيد `test.edaratek.com`. لا حاجة لإنتاج `edaratek.com` وفق spec.md SR-013 وتوضيح H05. التفويض الحالي يجيز التحقق المحدود؛ لا يفترض معرفة host/user أو fingerprint أو revision من أسماء أسرار GitHub.

## نقطة الوصول والنسخة

زوّد الجلسة باسم اتصال SSH مُعدّ مسبقًا ومقيد بالاختبار مع تحقق هوية الخادم، أو تقرير عمليات موثوقًا ومؤرخًا بالأدلة التالية. لا تُرسل كلمة مرور أو مفتاحًا في المحادثة/المستودع. المسار `/home/test/public_html` مرجع workflow فقط، ويجب تأكيد أنه تطبيق vhost الاختبار قبل استخدامه. لا تستعمل حساب الإنتاج أو تنفذ أمرًا عليه.

بعد التأكد من حساب الاختبار والمسار فقط، أوامر قراءة أساسية للمشغل:

```sh
date -u +%Y-%m-%dT%H:%M:%SZ
id -un
cd /home/test/public_html
test "$(pwd -P)" = /home/test/public_html || exit 41
git rev-parse HEAD
git show HEAD:routes/api.php | sha256sum
git show HEAD:bootstrap/app.php | sha256sum
```

إذا اختلف canonical path أو حساب/vhost الاختبار، توقف؛ لا تصلح ذلك تلقائيًا ولا تستخدم safe.directory عامًّا. قارن النسخة والبصمات مع fbc63a4615f48ac0f832d91945d9652aeabef466 والمصدر المحمي في `../source-manifest.json`. حتى النسخة المطابقة لا تثبت وحدها config/cache الفعالين أو ملفات runtime غير المتتبعة.

## H01

- لكل disk خاص وعام ذي صلة: الاسم، physical root الفعلي بعد realpath، وجوده، مالك/صلاحيات القراءة للتطبيق، وقابلية الوصول من document root أو aliases. دون أسماء/محتويات ملفات مستخدمين.
- vhost/document root الفعلي لهذا hostname فقط؛ قواعد `/storage` و`/media-files` وpreviews والنسخ الفيزيائية وsymlink targets قبل file/directory shortcuts. لا dump لإعداد الخادم كله أو vhosts الإنتاج.
- صنّف CDN/base URL: A مُعدّ ومثبت، B غير مستخدم لأن التصميم مباشر مع دليل config/vhost، C مطلوب ومفقود، أو D غير معلوم. غياب CDN headers لا يكفي B.
- استخدم ملف QA معروف الغرض وله parent/tenant وصلاحيات مؤكدة؛ أثبت bytes للمأذون، ومنعها لغير المأذون عبر session وnative bearer والروابط القديمة وكل alias/cache معروف. لا تعدد ملفات حقيقية أو تقرأ محتوياتها. HEAD404 لاسم عشوائي غير موجود لا يغلق الشرط.
- سجل Content-Type/Disposition وprivate no-store للرد الخاص، وسلوك origin/proxy/CDN مع cache policy. أي purge أو نقل/حذف نسخ حقيقية يحتاج تفويضًا مستقلًا؛ لا تعيد public bytes في rollback. لا ادعاء بإزالة نسخ متصفح تاريخية.

## H05

- أخرج allowlist فقط: APP_ENV/APP_DEBUG/APP_URL الفعلية، حالة config cache ومصدر القيم مقابل runtime/FPM، نسخة PHP، cache store والnamespace وحالة العزل عن الإنتاج. الأسرار بوصف `PRESENT/ABSENT` فقط؛ لا APP_KEY أو كلمة مرور أو config dump.
- أثبت أن DB/cache/storage/queue/mail للاختبار منفصلة، دون عرض credentials أو تشغيل providers/workers. لا `php artisan test` أو RefreshDatabase أو migrations على staging.
- إذا تطلب الشرط اختبار cache، تحقق أولًا من العزل؛ أنشئ فقط `edaratek:t112:qa:<UUID>` مع TTL قصير، read/expire/delete لهذا المفتاح فقط، وسجل النتيجة. لا flush/config:clear/cache:clear/optimize:clear.
- تحقق من private route denial وerror code/request_id، وforced-error redaction عبر وسيلة QA معتمدة غير مكشوفة للعامة. لا تضف route تشخيص دائم أو تشغّل debug=true أو تفتعل خطأ SQL/تحميل على بيانات حقيقية. إن لم توجد وسيلة آمنة متاحة فسجلها كدليل ناقص.

## شرط النشر

HTTP الحالي لـ`/api/v1/me` أعاد404. هذا مؤشر عدم تطابق محتمل للنسخة أو التوجيه، وليس إثباتًا لسبب محدد. احسم HEAD/runtime/vhost أولًا. workflow الحالي ينشر main فقط ويشغّل optimize:clear/config:cache، لذلك **لا يُستخدم لجمع الدليل**؛ دفع/دمج main محظور. إذا ثبت لزوم نشر نسخة الميزة، يلزم مسار نشر اختبار محدد واعتماده قبل التنفيذ، مع نسخة قبل/بعد وخطة rollback تحافظ على الخصوصية. لا يمكن تحديد تغيير config أو أمر نشر تصحيحي دقيق قبل إثبات الحالة الفعلية.

بعد إثبات H01/H05، أعد تقييم T112 دون تغيير شرطه، ثم أعد suites المطلوبة عبر CI المعزول. لا Phase9 في هذا التفويض.
