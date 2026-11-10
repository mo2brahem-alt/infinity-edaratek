# T089 — حصر عقد المرحلة السابعة

PM2026-11-09 اعتمد Phase6 وصرّح بتنفيذ Phase7 فقط، T089–T102 (14 مهمة). البداية المؤكدة d1810ccef7c8c64651098eecd33f0fbe017bfb62 على 001-mobile-backend-readiness؛ جميع39 بصمة في manifest المرحلة السابقة متطابقة. main=12b9d5326d942acda55309aac05c6cee1bf928b0. تغييرات README وحذف public والمجلدات غير المتتبعة سابقة ومحفوظة. README SHA256=1287b879863b9ba8e6081826afa5c9c6c72e7e84534e1bac6c2aea07e5b28092.

routes/api.php مسجل عبر bootstrap/app.php؛ لا نسخة API منفصلة للأعمال. الحالات/التفويض من AccountAccessEvaluator وSchoolContextResolver وSubtaskAccessQuery وAttachmentAccess وNativeTokenService. لا migration أو CRUD جديد أو إشعارات جديدة أو مزود push. واتساب خارج النطاق نهائياً؛ OTP في T096 ملغى بقرار PM وليس dependency.

| الطريقة والمسار تحت /api/v1 | الضبط الحالي والهدف المثبت | النجاح |
|---|---|---|
| POST auth/login | allowlisted credentials/device؛ حدود identity+IP؛ أهلية حالية؛ PAT أصلي محدود | 200 data:{access_token,token_type,expires_at,user} وno-store |
| GET me | native bearer ثم حدود read ثم أهلية؛ school=null مشروع؛ GET دون إنشاء defaults/roles | 200 data:CurrentUser |
| POST auth/logout | native bearer وحدود session؛ استثناء الحساب المحظور لإنهاء رمزه الحالي فقط | 204 فارغ |
| GET auth/devices | مالك/رموز أصلية غير منتهية؛ page/per_page فقط | 200 data[]/meta/links |
| DELETE auth/devices/{token} | معرف موجب ضمن PHP signed64؛ native owner scope؛ foreign/missing404 | 204 فارغ |
| DELETE auth/devices | مالك أصلي مؤهل؛ لا browser/nonnative revocation | 204 فارغ |
| GET staff/tasks | staff+school+assignment+parent قبل العد؛ filters allowlist | 200 TaskSummary[]/meta/links |
| GET staff/tasks/{subtask} | نفس scope وpolicy؛ مرفقات مأذونة ومحدودة | 200 TaskDetail |
| GET attachments/{attachment}/download | parent/school/operation policy قبل البايتات | 200 binary خاص |
| GET student-leaves/{leave}/attachments/{attachment}/download | صلاحية leave وتطابق كل الآباء/المدرسة | 200 binary خاص |

فجوات المراجعة قبل التنفيذ: device query يقبل unknown/null؛ device ID قد يتشبع عند cast؛ HttpException429/409 لا يملك mapping صحيحاً؛ الأخطاء لا تتشارك request_id مع log؛ metadata المرفقات غير محدودة؛ حدود الدخول تفحص account فقط بعد نجاح credentials؛ check ثم hit غير ذري. الموارد موجودة جزئياً في Resources/Mobile؛ تُنقل إلى Api/V1 مع بقية موارد العقد دون نسخ قواعد الأعمال.

قرار تنفيذ T093/T095: تفاصيل المهمة تستقبل page/per_page للمرفقات (default25/max100)، وتحفظ attachments[] وتضيف attachments_meta/attachments_links ثابتة. هذا يحقق شرط bounded relations عبر المسار الحالي دون نقطة أعمال جديدة أو truncation صامت. الفلاتر/العد يستبعدان المرفقات غير المتسقة قبل pagination؛ لا messages/transcripts في DTO.

الأخطاء:401 INVALID_CREDENTIALS/UNAUTHENTICATED،403 account/FORBIDDEN/SCHOOL_SUSPENDED،404 NOT_FOUND،409 SCHOOL_CONTEXT_REQUIRED/BUSINESS_CONFLICT/IMPACT_CONFIRMATION_REQUIRED،422 VALIDATION_FAILED،429 RATE_LIMITED مع Retry-After/retry_after،500 INTERNAL_ERROR دون traces حتى debug=true. يُثبت مسار renderer للتعارضات عبر fixture فقط؛ لا يُخترع workflow تعارض جديد. أخطاء routing العامة400/405/413 لها رموز ثابتة موثقة. request_id مولد لكل طلب؛ لا يعكس header المستخدم.

الترتيب: T089 ثم تأليف T090/091/092، ثم T093/094/095/096/097، ثم T098، ثم T099/100 اختبارات معزولة، ثم T101 freeze وT102 PM boundary. لا Phase8 أو staging/production؛ aliases/CDN UNKNOWN؛ MOBILE_BACKEND_NOT_READY.
