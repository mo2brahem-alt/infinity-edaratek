# T105 — أمن الملفات وحدود الدليل

اختبارات المصدر/fixture المطلوبة: tests/Feature/Mobile/Phase5/PrivateAttachmentAuthorizationTest.php،PrivateAttachmentMigrationTest.php،StaticPrivateUrlTest.php،PublicAssetCompatibilityTest.php؛SchoolAttachmentManagementTest.php؛StudentLeaveManagementTest.php؛StudentAttendanceAccessTest.php؛StudentCertificateFeatureTest.php؛UploadValidationFeatureFlagTest.php؛PublicStorageLinkStructureTest.php؛ManagerOnboardingLogoUploadTest.php؛MediaStorageUrlFallbackTest.php.

مصادر التحكم المشتركة: AttachmentAccess،AttachmentService،PrivateFileReader،PrivateAttachmentMigrator،protected native/web adapters؛فحص المدرسة والأب والعملية قبل bytes. metadata لا تعيد disk/path/provider/uploader غير اللازم. لاpublic fallback أو traversal أو private symlink bypass أو soft-delete resurrection في النطاق المختبر. الشعارات العامة المقصودة محفوظة.

T0-10 source/representative static fixture matrix: authorized exact bytes/MIME/Content-Disposition/nosniff/private-no-store،anonymous/foreign/wrong-permission/missing/deleted denial،leave-specific gate،parent mismatch،uploadhardening،checksum/CAS/conflict/duplicate/resume/rollback privacy preservation. HTTP fixture server يمثّل symlink/copied-directory/alias routing داخلstorage مؤقت فقط، ولا يثبت إعداد خادم حقيقي.

لا migration جديدة أو apply لمستخدم حقيقي. schema114 وPAT/private-manifest rehearsals مستقلة فيDBمصطنعة؛no-op down للmanifest يحتفظ بالبيانات،وrollback لا يعيد public access. REAL_FILES_MIGRATED=0؛لم تنفذ inventory/move/delete/purge على بيئة حقيقية.

**H01 release evidence BLOCKED:** actual storage/document roots/aliases/origin/CDN/proxy/cache proof ما زالUNKNOWN/UNVERIFIED كما فيPhase5/7. CHK034/040 لا يغلقان بنجاح fixtures. لا فحص SSH أو staging/production أو URLحقيقي؛مصدر الإجراء اللاحق evidence/phase-05/operational-boundaries.md،ولا يجيز تنفيذه. انظرaudit-closure.md وacceptance.md لقرارT112.
