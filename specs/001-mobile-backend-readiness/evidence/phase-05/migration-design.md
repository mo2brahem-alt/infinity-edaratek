# T061–T065 — manifest والترحيل الآمن

الجدول المضاف الوحيد `private_file_migrations`، migration2026_11_09_000000. الحقول: schema_version، inventory_key فريد، attachment_type/id nullable لليتيم، source/destination disk/path، source_size/hash، observed_source_metadata، state، safe_failure_code وtimestamps. المفتاح SHA256 للـlogical public namespace + path + schema version1؛ نفس الملف تحت alias لا ينتج copies مكررة. الـpaths/hashes/snapshot داخلية وليست API أو output للـcommand.

```text
inventoried → copied_verified → metadata_switched → public_revoked → verified
```

الحالة لا تتقدم بلا دليل، والفشل يحتفظ بآخر حالة وsafe code. API private read لا يثق بحقل is_private لتجاوز parent policy. orphans/soft-deleted parents/ambiguous references/missing/unsafe roots تحتاج مراجعة ولا تصبح verified تلقائياً.

`attachments:migrate-private` dry-run افتراضي بلا manifest/file/metadata mutation. apply يحتاج خياراً صريحاً وPRIVATE_FILE_MIGRATION_ENABLED الذي قيمته false افتراضياً؛ لا تعني هذه البوابة موافقة بشرية على بيئة حقيقية. limits1..500؛ تقارير أعداد/حالة/surface فقط، لا paths أو محتوى. الشكل التنفيذي الكامل في command المصدر، ولا توجد تعليمات تشغيل على shared DB هنا.

الجرد الكبير: inventory-only مع offset/next_offset وinventory_complete؛ دفعات1..500 لا تنقل source أو metadata، فتظل مواقع الجرد مستقرة داخل نافذة بلا legacy writers. surfaces المسموحة حصراً public/storage/media-files، بأسماء ثابتة وليست arbitrary path. كل surface يُراجع منفرداً، وinventory_complete يخص ذلك النطاق فقط. resume-only يستأنف manifests ولا يدعي أنه أعاد جرد البيئة كلها. اختُبر504 files عبر دفعات100 مع مصالحة counts وبقاء503 assets عامة مقصودة وملف private واحد. next_offset غير الفارغ يمنع إعلان اكتمال الجرد.

يمكن أن تكون bytes فقط في public/storage physical copy مع غياب canonical public disk. locator يراجع roots المعروفة المحصورة ويقبل source المطابق؛ اختلاف copies في checksum يوقف الجرد/الترحيل دون حذف أي نسخة. لا تعتمد معالجة النسخة المنسوخة على boot maintainer أو نقل real legacy directory تلقائياً. unknown aliases/caches لا تُفترض مغطاة.

النسخ: تثبيت المصدر وحجمه وبصمته، destination ثابت من inventory_key على private disk خارج public. destination الموجود لا يُستبدل إذا اختلف؛ private copy تُقرأ وتُفحص فعلياً. قفل MySQL GET_LOCK bounded ينتظم عبر اتصالات مستقلة؛ duplicate worker timeout لا ينسخ أو يبدل metadata.

CAS: transaction قصيرة مع row lock؛ يُقارن getRawOriginal مع observed snapshot شاملاً parent/owner/path/version. source fingerprint يعاد فحصه قبل switch. target disk/path/version مشتقة ومقيدة، وحقن foreign attachment ID أو تغيير metadata لا يغير سجل المدرسة الأخرى. metadata وmanifest state يتبدلان في transaction واحدة، لا file move داخل DB rollback.

أولوية المحتوى الحالي: إذا كان السجل يستخدم private bytes موجودة بالفعل، تقارن public copy بهذه النسخة قبل الجرد وقبل CAS. public copy قديمة أو تغيير private bytes متزامن ينتج PRIVATE_SOURCE_CONFLICT دون استبدال المحتوى الحالي أو حذف مصدر. Attachment يعلن private disk لكن bytes مفقودة يُحجز للمراجعة، ولا يُستعاد من public بصمت. leave/attendance القديمة بلا disk column يمكنها ترحيل public-only legacy source الصالح، مع بقاء أي private copy حالية هي المرجع عند وجودها.

الإبطال: بعد private verification وmetadata switch فقط، تُراجع canonical public source وphysical storage/media-files copies وsymlink targets المعروفة، وتُقارن كل بصمة قبل unlink. private bytes تظل النسخة المتحققة، ولا يحذف آخر مصدر صحيح قبل ذلك. أي copy/hash/root conflict يبقي المصادر ولا يزوّر verified. بعد نفي النسخ، تعاد مراجعة private bytes. manifest history يمنع public URL القديم من الرجوع حتى إذا اختفى attachment record أو ظهرت bytes عامة أخرى؛ audit لاحق يسجل corruption/reexposure كفشل ولا يعيد نشر المصدر.

الاستئناف: بعد copy وقبل switch تبقى public metadata/source والنسخة الخاصة؛ بعد switch تستأنف revocation دون إعادة نسخ خاطئة؛ بعد revocation تستأنف verification. duplicate run لا يكرر manifest أو destination. فشل verification/CAS لا ينشئ نجاحاً. quarantine لا تُمسح بقرار تلقائي؛ مصدر مفقود أو ملكية مبهمة يحتاجان مراجعة وbackup قبل إعادة inventory معتمدة.

rollback هو no-op حافظ للخصوصية: لا drop manifest ولا إعادة public metadata/bytes. rehearsal المعزول يثبت forward114 migrations، rollback/reapply/repeat مع بقاء سجلmanifest والمستخدم غير المتعلق؛ rehearsal PAT يستهدف path/batch الخاص به كي لا يزيل manifest بدلاً منه. recovery tests تحفظ private bytes والمنع العام. هذا ليس permission لتشغيل migrations أو تعديل ملفات حقيقية.

الإثبات التنفيذي النهائي في migration-results.md وvalidated-runtime.json. HTTP/static proof في static-results.md منفصل عن filesystem checks؛ caches/topology/inventory حقيقية ما زالت UNCONFIRMED وتحتاج الإجراء المنفصل في operational-boundaries.md.
