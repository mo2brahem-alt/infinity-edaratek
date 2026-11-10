# T055 — حصر ملفات Phase5 وخريطة التنفيذ

البداية ff58fcdf0c71940d02e17905df1233691e0f0928، تفويض PM بتاريخ2026-11-09: T055–T070 فقط، synthetic/disposable DB/files وfeature-branch commits/pushes. لا جرد أو نقل ملفات staging/production، ولا قراءة محتويات storage الحقيقية. هذا حصر مصدر وخطة fixtures، وليس شهادة بحالة بيانات حقيقية.

| التصنيف | المصدر/الغرض | التخزين والقرار |
|---|---|---|
| PUBLIC_INTENTIONAL / INTENTIONALLY_PUBLIC_ASSET | CMS Media uploads، Admin Settings settings، Manager Onboarding schools/logos، profile-photos للصور التعريفية المختارة صراحة | تبقى public فقط مع إثبات الغرض الحالي من Media أو Setting[type=file] أو School.logo_path أو User.profile_photo_path؛ prefix وحده ليس إذناً. أصول public/build والصور/theme الثابتة لا تتغير. صورة تعريفية ليست مستند staff_documents |
| PRIVATE_REQUIRED / AUTHORIZED_PRIVATE_ATTACHMENT | Attachment.ticket_message_id وmodule=tickets، Staff/SubtaskController::reply | كان public ticketing/attachments وlegacy url؛ نقل الرفع الجديد إلى school_attachments، ticket/subtask policy لكل قراءة، لا raw paths في JSON |
| PRIVATE_REQUIRED | Attachment.modules exams/schedules/teacher_preparations/student_records/staff_documents | AttachmentService يستخدم school_attachments؛ تثبيت نوع الأب والمدرسة والقدرة قبل download/delete، لا public disk override |
| PRIVATE_REQUIRED | SchoolStudentLeaveAttachment وStudentLeaveService وdownloadAttachment | local private للرفع الجديد؛ حذف public fallback وإضافة leave-specific permission فوق calendar middleware؛ parent/child/current state |
| PRIVATE_REQUIRED | SchoolAttendanceAttachment وStudentAttendanceController | local private مع public fallback قديم؛ إزالة fallback، تحقق classroom parent/current school، حماية delete من arbitrary path |
| INTERNAL_GENERATED / PRIVATE_REQUIRED | CertificateRenderingService وSchoolCertificateSignature | school_attachments لتوقيع/ختم، لا public URL؛ certificate controller يثبت permission والمدرسة قبل توليد الملف. لا migration جديدة لها ما دام المصدر خاصاً |
| TEMPORARY / INTERNAL_GENERATED | AcademicPlanning weekly-grid وSchoolExportDocumentService report exports | storage/app/temp أو response stream؛ controller authorization قائم، deleteFileAfterSend عند الملفات المؤقتة؛ ليست public storage ولا manifest source |
| UNKNOWN_REQUIRES_REVIEW | SchoolExam question attachment_path: مرجع string قائم، لا upload/download storage adapter له | لا يُعامل كإذن filesystem ولا يُجلب URL؛ أي legacy anonymous path غير مصنف branding يُرفض. معنى/ملكية مراجع فعلية يحتاج مراجعة بيانات مستقلة، لا اختراع module أو CRUD |
| UNKNOWN_REQUIRES_REVIEW | orphan/soft-deleted/missing/duplicate-reference أو source/hash conflict | manifest quarantine/failure code، لا verified صامتة؛ fixtures سلبية ومصالحة counts؛ inventory حقيقية لاحقة بتفويض مستقل |

بحث المصدر يشمل app/routes/config/resources وStorage URLs وstore/storeAs/putFile/download/file وpublic_path/storage_path وattachment/file/document/image paths. لا توجد خدمة تنزيل مستند منفصلة عن النماذج المذكورة في الحصر. Vue AttachmentPanel وStaff Dashboard يستخدمان url/download_url؛ تبقى مفاتيحهما مع endpoints محمية. leave file_path alias الحالي هو URL endpoint أصلاً، ويُحفظ للتوافق دون physical path.

نقاط عامة: PublicStorageController يخدم /storage و/media-files؛ PublicStorageLinkMaintainer قد ينسخ directory كاملة أثناء boot. الحماية ستقيد الخدمة العامة بأغراض branding، وتوقف نسخ/حذف legacy directories تلقائياً. symlink العام لا يُزال. إعداد HTTP تمثيلي في tools/testing فقط يثبت المنع قبل serving physical copy أو symlink؛ لا تعديل topology حقيقية أو purge CDN حقيقي.

| المهمة | التنفيذ المخطط | الاختبار والدليل/شرط الإغلاق |
|---|---|---|
| T055 | هذا الحصر، parent/storage/source ownership | fixtures لمدرستين؛ لا actual inventory مفترضة |
| T056 | PrivateAttachmentAuthorizationTest | bytes/headers/native bearer/web، 401/403/404، permission/current state/nested/path/missing/deleted |
| T057 | PublicAssetCompatibilityTest وStaticPrivateUrlTest | branding200، sensitive aliases404، copy+symlink HTTP |
| T058 | shared AttachmentAccess + protected-file reader، existing adapters | ticket/institutional/leave/attendance سياسات آباء واحدة، no fallback |
| T059 | AttachmentService/UploadSecurity وstaff/leave uploads وserializers | server MIME/extension/count/size/private naming، hidden internal fields |
| T060 | native attachment/leave adapters وTaskDetail metadata | routes/api.php shared Bearer/account/context/policy؛ relative URL وheaders |
| T061 | PrivateFileMigration + additive manifest migration | nullable identity، stable unique key/version، no destructive down |
| T062 | PrivateAttachmentMigrationTest | dry-run،missing/orphan/conflict،crash/CAS/checksum/idempotence |
| T063 | MigratePrivateAttachments + PrivateAttachmentMigrator | bounded dry-run default، خمس حالات، no path logs،copy/hash/CAS/revoke/verify |
| T064 | PublicAssetPath/PublicStorageController/maintainer + tools/testing representative router | deny anonymous sensitive aliases/copies، preserve branding، no real server edits |
| T065 | disposable schema/file rehearsal | forward/rollback/reapply/repeat مع no-op privacy-preserving down، reconciliation/recovery |
| T066 | actual internal HTTP fixture server | symlink وcopied directory مع الاثنين aliases، no-store وpublic branding |
| T067 | operational-boundaries.md | إجراء مستقبلي فقط، approval prerequisites وcache/topology/backup/recovery |
| T068 | Phase5 focused ثم P2/P3/P4 ثم full | current non-download+download matrix، صفر فشل أمني جديد، Excel baseline ظاهر |
| T069 | checklist-evidence.md | H01/M01/M07 وCHK033–042/068/069 مع فصل fixture proof عن real readiness blockers |
| T070 | acceptance.md + runtime/report | PRIVATE_ATTACHMENT_SECURITY_PASS للنطاق المصطنع المثبت فقط؛ STOP قبل T071 |

الحالات المعتمدة: رفض/إلغاء leave ليس حذفاً للأب في النموذج الحالي؛ المسؤول المخول بالإجازات يستطيع مراجعة دليل القرار، فلا نخلق حظر status جديداً. حذف الأب أو bytes أو attachment المخفي404. foreign web leave يحتفظ403 المثبت بعقد الويب، native foreign404. نسخة متصفح عامة قديمة وصلت سابقاً لا يمكن استدعاؤها؛ لا ادعاء بإزالة تاريخية أو إثبات live caches.
