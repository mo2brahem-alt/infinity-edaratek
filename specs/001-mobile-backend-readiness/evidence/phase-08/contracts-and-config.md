# T107 — العقد والتهيئة/schema

API v1 frozen definition docs/mobile-api-v1.md وcontracts/mobile-api.md لم يتغيرا فيPhase8. نفس10 endpoints وDTO keys/types/nullability/enums/status/authorization/validation semantics. Resources/Api/V1 وRequests/Api/V1 وbootstrap/native/shared services ثابتة. مصدر البصمات السابقPhase7 تم التحقق منه68/68 قبل التحرير؛حارسCI يراجع162 immutable source (including the new Phase8 test) وoverrideين معروفين داخلimage قبل وبعدالاختبارات.

Mobile/Phase7/MobileApiContractTest.php،MobileErrorContractTest.php،MobilePaginationTest.php،MobileRatePolicyTest.php،MobileSerializationTest.php:contract/date-only/UTC/DST/Arabic/IDrange/empty-overrun/searchsort/nulls/errors/UUIDsafe logging؛login budget account-ID+identifier+IP وread/session/reset limits معrecovery/timeout/cachefailure/independentprocess races. Phase3/EnvironmentSafetyTest يثبت debugfalse/debugtrue forced errors وallowlisted rate-limited denial logs. SafeAdminSeederTest يثبتabsence of fixedsecret/reset وrepeat preservation.

AuthSecurityLog لا يقبل arbitrary event string،ويسجلevent/user_id فقط معHMAC-keyed throttle؛ApiError/internal logger لا يسجل raw error/request/body/credentials،ويستعمل UUID مولداً. الاختبارات تثبت response وmockedsafe log shapes؛لا raw logs أوproviderpayloads تصدرمنcontainer.

لاschema/migration/backfill/indexchange فيPhase8. المراجعةللموجود114 migration فقط،والـPATscopedrollback/reapply/repeat،والـmanifestno-op-down/retaineddata/reapply/repeat علىdisposableMySQL. بياناتحقيقيةولـsharedschema لمتمس؛لاbackup/restoreحقيقي مزعوم.

**H05 scope:** APP_ENV/testing،APP_DEBUGfalse،isolatedcachepaths،absentcredentials،internalnetwork،fake mail/notifications/HTTP proof +forceddebugtruev1 redaction هيlocalPASS فقط بعدالتشغيل. actualeffective runtime/debug/config/cache safety فيبيئةحقيقية لمتُتحقق؛لا يجوزتصنيف conditionalHIGH مغلقاً globally منذلك. يحتفظT110/T112 بهذاالعائق حتىدليلoperations موثوقمصرّحبه. لاادعاءأنخادمًا غيرمفحوص غيرآمن بالفعل.

Final executed evidence: Phase7 contract76/76; Phase8 composite462/462; full Artisan and explicit MySQL each724/725, known Excel only,0errors/0skips. The two new existing-requirement scale cases preserve frozen DTOs/default25/max100; device137 and attachment139 records retain constant query counts. Pre/post162+2 source guards PASS. See full-results.md and validated-runtime.json. H05 actual environment proof remains BLOCKED.
