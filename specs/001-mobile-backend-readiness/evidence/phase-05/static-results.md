# T066 — HTTP/static fixture proof

المصدر ecb6b7fd7d1b06bf7d4575f1b194ce77fa76fb54، run42598238825/job129096063283. StaticPrivateUrlTest1/1 ضمن focused100/100 وfull؛ ليست static code discovery فقط.

شُغل PHP HTTP server فعلي على127.0.0.1 بمنفذ مؤقت داخل CI/network المعزولة. tools/testing/private-static-router.php يجبر /storage و/media-files على Laravel قبل موجود-file shortcut، باستعمال front-controller server variables الصحيحة. root task-owned تحت /tmp وsource public fake؛ كل child process ينتهي والنسخ/links تُنظف داخل الجذر المصطنع فقط.

| topology | /storage private | /media-files private | declared logo |
|---|---|---|---|
| physical copied directory |404/no sensitive bytes/no-store|404/no sensitive bytes/no-store|200 ونفس bytes |
| symlink target |404/no sensitive bytes/no-store|404/no sensitive bytes/no-store|200 ونفس bytes |

الطلبات anonymous، والغرض العام يثبته Media record مصطنع حقيقي مرئي للعملية المستقلة، لا path exception أو response mock. PublicAssetCompatibilityTest يختبر declared branding وorphan/traversal/private denial داخل Laravel أيضاً. الاختبارات الأخرى تثبت protected authorized bytes وMIME/filename/nosniff/private,no-store، old URL بعد migration/history/delete/reappearance، وcross-tenant/current-state denial.

قالب Apache في tools/testing/private-assets.apache.conf.example يمثل قواعد مبدئية لكلا aliases قبل -f/-d shortcuts. لم يثبت على أي server فعلي، ولم يعدل public/root .htaccess أو nginx/Webmin أو CDN. إثباتنا محدد بالـrepresentative HTTP profile؛ document roots/static aliases/cache configuration الفعلية UNCONFIRMED، وتبقى CHK034/040 وreadiness blockers مفتوحة. لا ادعاء بسحب bytes سبق أن وصلتها browser caches، ولا purge حقيقي.
