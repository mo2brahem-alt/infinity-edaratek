# T106 — إشعارات app/web؛ شرط WhatsApp القديم مستبدل

النص القديم فيT106 وجداول T0-12/H04/OTP/HMAC/claim/callback/delivery SUPERSEDED_BY_PM_SCOPE_DECISION. هذا ليس WhatsApp delivery PASS ولا defect غياب مزود. لا تستدعى jobs/transport القديمة ولا تختبر رسائل إرسال أو queue resume. ملفWhatsAppIntegrationTest يُبصم static فقط ويبقى excluded باسمه وحده،كما كان منذPhase6.

المنفذ: tests/Feature/Mobile/Phase6/NotificationChannelPolicyTest.php،tests/Feature/RealtimeNotificationFlowTest.php،tests/Feature/PhoneStateCompatibilityTest.php. النطاق: business event persistence،المستلم الصحيح والدور/العلاقة،batch recipient dedup،owned list/read/read-all،عدم قراءة إشعار الغير،guest denial،حدودpayload/routing hints،عدمfan-out إلى قناة ملغاة أو اعتماد علىbindings/configقديمة،وحفظhistory بلا attempt/delivery staterewrite. حالات الهاتف10 عامة وليستdeliverytests.

NotificationService→notifications→NotificationController/web component unchanged. resources/js/Components/Notifications/RealtimeNotifications.vue يجلب60 إشعاراً ويحسبunread منالمحمّل؛هذا سلوكL02 القائم،ليسcount عالمياً مزعوماً ولاnative push implementation. readAll scoped لصاحبالحساب فيالخادم. لا event جديد أوduplicate business logic؛لاFCM/APNS/pushprovider.

مصدر التقاعد N8nWhatsAppClient/jobs/config/routes ثابت ببصمته؛المداخل القديمة410 والعملاء/jobs inert فيsource المعتمد. لا ادعاء تغييرworker حقيقي محمّل بكود قديم لأننشره لم ينفذ. H04 original product scope superseded لاrelease blocker؛القيودالحقيقيةالمتبقية فيT112 هيH01/H05 وليسواتساب.

WHATSAPP_ENABLED=NO
WHATSAPP_IN_SCOPE=NO
WHATSAPP_SENDS_ALLOWED=NO
REAL_WHATSAPP_SENDS=0
