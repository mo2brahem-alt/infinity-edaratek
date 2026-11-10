# T056–T060 — تفويض الملفات ومصفوفة الطرق

نتيجة التنفيذ النهائية والأعداد في results.md وvalidated-runtime.json. هذه مصفوفة المصدر والاختبار، وليست إثباتاً لتخزين أو مستخدم حقيقي.

`AttachmentAccess` يعيد قراءة المستخدم وأهلية الحساب، ثم يثبت الأب والمدرسة والقدرة الحالية. `PrivateFileReader` لا يقبل إلا local وschool_attachments بجذور خارج public وجذر public disk، ويرفض traversal والencoding والabsolute/backslash وsymlinks التي تخرج من الجذر. المسار مشتق من سجل موثوق بعد التفويض، ولا يختاره الطلب. MIME من bytes بواسطة finfo، واسم التنزيل منزوع separators/control chars، مع nosniff وprivate,no-store. لا signed/public URL بديل.

| الطريقة | السلطة الحالية | الحالات المثبتة |
|---|---|---|
| GET /api/v1/attachments/{id}/download | Bearer أصلي + account evaluator + parent-aware AttachmentAccess + reader الخاص | cookie-only401، authorized bytes200، foreign/invisible/missing/malformed/deleted404، حقول scope/path422، current account/role/membership/suspension revocation |
| GET /school/attachments/{attachment}/download | جلسة ويب + الأهلية والعلاقة/القدرة في الخدمة نفسها | staff own task، manager parent، supervisor own supervised ticket؛ institutional modules؛ لا anonymous/file-path authority |
| DELETE /school/attachments/{attachment} | تفويض delete مستقل في الخدمة | institutional manage permissions كما في الأعمال الحالية؛ same-school wrong permission403 وforeign404 مع بقاء bytes/record؛ ticket view لا يمنح delete جديداً |
| GET /api/v1/student-leaves/{leave}/attachments/{attachment}/download | Bearer + current school + canManageStudentLeaves + leave/student/type school + child leave ID | calendar-only403، foreign/nested mismatch404، missing/deleted404؛ رفع/تنزيل current owner200؛ سحب permission أو membership يسري مع token قائم |
| GET /api/school/leaves/{leave}/attachments/{attachment}/download | session + shared leave-specific parent decision | web foreign leave parent403 محفوظ من العقد السابق، mismatch child404، لا public fallback حتى لو bytes موجودة هناك |
| GET/DELETE attendance attachment routes | current active school + canManageStudentAttendance + matching classroom parent | wrong capability403، foreign/mismatched classroom404؛ لا حذف/تنزيل بمسار عميل، لا public fallback |
| GET admin media preview / DELETE media | صلاحية CMS القائمة ثم نفي وجود private reference/history | سجل Media لا يعيد نشر ملف private؛ preview404 وdelete403 عند collision، الملفات تبقى محفوظة للمراجعة |
| GET /storage/{path} و/media-files/{path} | public purpose حالي مثبت من Media/Setting/School/User، ولا private reference أو manifest history | declared branding200؛ sensitive/unclassified/orphan404؛ private past URL يظل محجوباً بعد metadata switch أو حذف السجل أو ظهور نسخة قديمة |

قواعد modules المؤسسية صريحة: exams→SchoolExam مع صلاحية الاختبارات وقيد teacher assignment القائم؛ schedules→SchoolTimetableVersion وacademic permission؛ teacher_preparations→SchoolTeachingAssignment؛ student_records→SchoolStudent وstudent-structure permission؛ staff_documents→User في المدرسة وstaff مع school-user management. mismatch النوع/الأب/المدرسة404، لا توسيع allowlist بلا تحقق.

ticket attachment: message ثم subtask/ticket؛ school الطفل والأم متطابقان، وأي ticket_id وattachable_type/id إضافيين متسقان. staff يتبع SubtaskAccessQuery الحالي، والمدير يتبع parent policy الحالي. المشرف يستطيع قراءة مرفقات رسائل المهمة الفرعية من تذكرته التي يملك حق عرضها حالياً؛ هذا يحفظ Supervisor/TicketController::show الذي يحمل subtasks.messages.attachments، ولا يعطيه manager assignment/delete. أزيل guard المدرسة العام عن مساري attachments فقط لأنه يفترض users.school_id للمشرف؛ بقي auth وأصبح قرار المدرسة/الأب/العملية داخل الخدمة المشتركة لكل طلب. بقية المسارات وmiddleware لا تُتجاوز في الاختبارات.

الرفع الخاص: UUID/hashName من الخادم، MIME/size/ext من الخادم، 10MB لكل ملف، institutional max10، وحدود العدد القائمة للحضور/الرد والإجازة محفوظة. protected MIME allowlists الحالية إلزامية حتى لو عُطلت legacy unsafe flags؛ اختبار الإجازة القديم الذي كان يسمح PHP في هذا الوضع صار422 عمداً. مسار الملف الأصلي metadata فقط ولا يتحكم في storage key؛ context disk/is_private لا يحول private upload إلى public. parent school mismatch يرفض قبل الكتابة.

`Attachment` يخفي disk/file_path/stored_name/metadata/morph internals/uploader ID في JSON ويولد url/download_url محميين للويب. TaskDetail native يعرض attachments[id,name,mime_type,size_bytes,download_url] فقط، ويعيد التحقق من تفويض كل metadata entry. TaskSummary يبقى بلا transcript. leave file_path alias الحالي يبقى URL محمياً للتوافق، وليس storage path.

الاختبارات: PrivateAttachmentAuthorizationTest، SchoolAttachmentManagementTest، StudentLeaveManagementTest، StudentAttendanceAccessTest، StudentCertificateFeatureTest، UploadValidationFeatureFlagTest، ManagerOnboardingLogoUploadTest وMediaStorageUrlFallbackTest. توليد الشهادات والتقارير/الـtemporary exports لا يضيف download API جديداً؛ السياسات القائمة والprivate signature bytes تبقى تحت اختبارات regression.

رفض/إلغاء leave ليس حذفاً أو حظر مراجعة من المسؤول المخول؛ تبقى المرفقات قابلة لمراجعة القرار لصاحب leave permission الحالي. حذف الأب أو المرفق أو غياب bytes404، دون إحياء سجل محذوف أو public fallback. لا delete/accept/task mutation أو leave-upload native جديد.
