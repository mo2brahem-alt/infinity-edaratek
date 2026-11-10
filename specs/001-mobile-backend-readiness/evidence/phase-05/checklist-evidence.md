# T069 — مطابقة checklist والتدقيق

حالة القبول التقنية النهائية مقيدة بالمصدر والـCI في acceptance.md وvalidated-runtime.json. لا نجاح لبيئة حقيقية أو نقل فعلي مستنتج من الاختبارات. لا يغلق هذا الملف WhatsApp أو عقد Phase7 أو final gate/readiness.

| البند | الدليل الحالي | المعاملة |
|---|---|---|
| CHK028 | download-design.md؛ native/web/institutional/ticket/leave/attendance methods، direct/nested IDs وno-write denial؛ regressions الحالية | إغلاق مصفوفة الطرق المعدة في Phases4/5؛ native hidden404 وweb403 القديمة الموثقة حيث هي عقد قائم |
| CHK033 | inventory.md وPrivateFileClassification؛ الغرض من Media/Setting/School/User وprivate parent/history، unknown/orphan quarantine | إغلاق classification للـcode والfixtures المعتمدة، لا actual inventory مفترضة |
| CHK034 | PrivateFileReader roots والسياسات المشتركة وrepresentative HTTP | يبقى مفتوحاً كشرط readiness شامل: كل serving topology/caches الفعلية غير مثبتة؛ الجزء المصطنع PASS |
| CHK035 | PrivateAttachmentAuthorizationTest وSchoolAttachmentManagementTest وStudentLeaveManagementTest | إغلاق authorized/401/403/foreign404/missing/deleted/revoked bytes/no fallback للطرق الحالية |
| CHK036 | leave-specific canManageStudentLeaves مقابل calendar، ticket parent policy وsupervisor compatibility | إغلاق M01 الحالي وnested-parent matrix دون blanket allowlist bypass |
| CHK037 | privateReader path/MIME/filename/headers، UploadSecurity، count/size/root checks وDTO hidden fields | إغلاق للنطاق المعد؛ large-data full API/query budgets ليست مشمولة |
| CHK038 | authorized dry-run،504 files، per-surface/offset counts، orphan/deleted/missing/duplicate/conflict/quarantine cases | إغلاق synthetic inventory/reconciliation فقط؛ actual orphan inventory يحتاج تفويضاً منفصلاً |
| CHK039 | PrivateAttachmentMigrationTest: copy/hash/CAS، IO crash/resume، independent-connection lock، foreign ID tamper، private-current/public-stale conflict | إغلاق آلية النسخ والاستئناف/الفشل المتحفظ ضمن DB/files المعزولة |
| CHK040 | StaticPrivateUrlTest على storage/media-files وphysical copy/symlink، history/reappearance denial وno-store | يبقى مفتوحاً للـactual origins/aliases/CDN/proxy caches؛ لا cache purge حقيقي أو ادعاء سحب browser bytes قديمة |
| CHK041 | manifest down حافظ، schema rehearsal وbyte recovery tests؛ no public restoration | إغلاق rollback/recovery التقني المعزول؛ real changes تحتاج موافقة مستقلة |
| CHK042 | operational-boundaries.md: approvals/scope/backup/window/topology/cache/rollback | إغلاق توثيق الإجراء، مع إبقاء actual readiness blockers مفتوحة؛ ليس staging execution |
| CHK068/069 | manifest schema114 وPAT scoped rollback/reapply/repeat، existing-data preservation | يبقيان مفتوحين لنطاقهما الكامل متضمناً control/claim work في Phase6؛ جزء Phase5 مثبت |
| CHK077 | phase gate/report الحالي | يبقى مفتوحاً لمراجعة PM وباقي متطلبات قبول البيئة/الملفات؛ لا قبول PM مفترض |

H01: الرفع الجديد للمهام private، والURL قديم لا يصرح بالقراءة؛ parent-aware web/native downloads وapp/static aliases مثبتة في fixtures. M01: calendar-only لا ينزل leave attachment، والقدرة الحالية تتغير فوراً. M07: public fallback للإجازة والحضور أزيل، والـprotected MIME/size/name controls موحدة. تصنيف الإغلاق: **code + authorized synthetic verification PASS**؛ actual files/inventory/static deployment/cache state **UNCONFIRMED**. لا إغلاق live exposure أو readiness بلا دليل بيئي مستقل.

تغييرات التوافق المقصودة: PHP لا يصبح مرفق إجازة صالحاً عند إطفاء unsafe flags؛ public file بلا purpose record ليس asset عاماً؛ مسار سبق تصنيفه private يظل محجوباً من manifest حتى بعد حذف Attachment؛ boot maintainer لا يحرك directories القديمة تلقائياً. حالات الشعارات/الوسائط العامة المعلنة باقية، واختبار fallback القديم صار يحمل Media purpose record بدلاً من السماح لأي orphan.

التعدد المشروع للأدوار والعلاقات محفوظ: account approval ما زال account-wide؛ حق العملية في مدرسة لا يتقاطع مع علاقة أخرى. supervisor ticket-parent read لا يحتاج staff membership ولا يمنح manager/delete؛ staff assignment الحالي لا يمنح مهام مدرسة تاريخية. لا portal أو endpoint جديد خارج foundation downloads/TaskDetail metadata.
