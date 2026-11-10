# T065 — نتائج schema/file rehearsal

المصدر النهائي ecb6b7fd7d1b06bf7d4575f1b194ce77fa76fb54، run42598238825/job129096063283. schema114/114 forward PASS في MySQL8.0.46 مؤقت. migration الجديدة الوحيدة manifest2026_11_09_000000؛ لا control/tenant schema جديدة.

probe-private-manifest-migration: rollback_exit0 مع manifest_retained_on_rollback=true، reapply_exit0 وrepeat_exit0، existing_record_preserved=true وunrelated_users_preserved=true. down لا drop ولا إعادة public data. PAT forward/rollback/reapply/repeat PASS في path/batch الخاص به بعد إضافة manifest؛ بيانات المستخدم غير المتعلق محفوظة.

PrivateAttachmentMigrationTest18/18 ضمن focused100/100 وfull. تشمل dry-run دون files/metadata/manifest mutation، stable duplicate، after-copy/after-switch interruption، checksum destination/source conflict، metadata CAS، orphan/soft-deleted/missing/ambiguous quarantine، foreign manifest-ID injection، bounded apply gate، inventory continuation و504-file reconciliation، actual shared MySQL lock باتصال مستقل، corruption بعد verified، alias-only source، retained history public reappearance، stale-public/current-private precedence وconcurrent private edit قبل switch.

المصالحة الإيجابية:504 synthetic source files عبر دفعات100 inventory-only؛503 declared public assets بقيت، private source واحد verified، identity/destination واحدان عند rerun. سيناريو single-file: source hash يساوي private destination hash، metadata pointer خاص، old public source غائب، protected web download يعيد نفس bytes. حالات الفشل متعمدة ولا تحسب migration ناجحة؛ مصدرها لا يُحذف ولا يُستبدل current private content.

كل source/copy/move/unlink وschema operations تخص Storage::fake أو task-owned temp/DB. REAL_FILES_MIGRATED=0 في staging/production. counts ليست جرداً حقيقياً ولا تنفي orphans في بيئة لم تُقرأ. alias/cache proof الفعلي مؤجل كما في operational-boundaries.md؛ لا rollback يعيد نشر private bytes.
