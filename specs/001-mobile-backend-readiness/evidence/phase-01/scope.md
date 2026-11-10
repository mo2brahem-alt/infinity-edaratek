# Phase1 — النطاق وحفظ العمل السابق

التاريخ:2026-11-07. الميزة:001-mobile-backend-readiness. موافقة PM تخص T001–T012 فقط، مع السماح بعمليات migrate/refresh/drop على قاعدة مؤقتة مثبتة العزل وحدها. لا T013 أو مرحلة ثانية، ولا تغييرات أعمال أو staging/production أو Git mutations أو إرسال خارجي.

المستودع: D:\edaratek\public_html؛ الفرع main؛ HEAD:12b9d5326d942acda55309aac05c6cee1bf928b0. إجمالي diff السابق 14 ملفًا، 5 إضافات و13 حذفًا، يشمل README.md المعدل و13 مسار public محذوفًا. المسارات المحذوفة: awstats-icon، awstatsicons، وicon/mime/{conf,csv,document,dtd,flv,fon,package,runtime,swf,vbs,xsl}.png. غير المتتبع السابق: .agents/، .specify/، .well-known/، docs/spec-kit.md، specs/. لم تُنظف أو تُضمّن أو تُسترجع.

مرجع البصمات:[source-before.json](source-before.json)، و[source-after.json](source-after.json). لا قيم .env أو كلمات مرور ضمن الأدلة. بصمات SHA256 فقط. مصدر الأعمال app/bootstrap/config/database/resources/routes/tests وملفات Composer/package وبيئة المضيف محفوظة؛ الاختلاف المتوقع في .github هو تعريف CI اختبار جديد منفصل فقط.

الملفات الجديدة تخص tools/testing/ ومسار CI يدوي وأدلة المرحلة. التعديلات المصرح بها في tasks.md وquickstart.md وملاحظة readiness-security.md فقط. tests/TestCase.php الأصلي لم يتغير؛ ملف أدوات منفصل يطبق superclass آمنًا داخل صورة الاختبار المؤقتة فقط.

التنفيذ الفعلي المسموح هنا توقف قبل بناء الصورة أو تشغيل Laravel/DB لغياب محرك الحاويات. لا حزم نظام/Composer ثبتت، ولا WSL/Docker/BIOS/إعادة تشغيل أو تغييرات بيئة مضيف. لا يثبت هذا المرور بوابة runtime.

D1: معالجة500 الأولية قبل المرحلة المتأثرة، ليست عمل Phase1. D2: CHK006/074 جزئيان ولا يغلقان. C1: أكثر من100 سجل وhistories وquery growth قبلPhase7. V1: حارس Sanctum/cookies فيPhase2. V2: تشغيل المنصة ما زال غير متحقق.
