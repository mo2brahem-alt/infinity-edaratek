# T055–T070 — خريطة التنفيذ والقبول

الحالة والأعداد المعتمدة في EDARATEK_PHASE_5_EXECUTION_REPORT.md وacceptance.md؛ لا تُغلق مهمة لأن المصدر كُتب فقط. مصدر الـCI النهائي محدد في validated-runtime.json وsource-manifest.json.

| ID | المتطلب | التنفيذ | الاختبار/الدليل |
|---|---|---|---|
| T055 | source inventory/classification وfixtures authorized فقط | inventory.md، PrivateFileClassification/PublicAssetPath، مراجعة جميع writers/readers/models/resources | تصنيف Media/Settings/logo/profile مقابل ticket/institutional/leave/attendance/signatures/generated/temp؛ unknown/orphan/missing غير مفترض |
| T056 | per-request binary/auth/parent/header/path/metadata matrix | AttachmentAccess وPrivateFileReader وweb/native adapters | PrivateAttachmentAuthorizationTest؛ المصفوفة في download-design.md؛ calendar-only وforeign وmissing/deleted وcurrent-state revocation |
| T057 | branding وcopied/symlink aliases | PublicAssetCompatibilityTest وStaticPrivateUrlTest | public purpose مسجل200؛ private/unclassified404؛ HTTP على كلا aliases ونسختين من topology |
| T058 | shared ticket/institutional/leave download policies | AttachmentService وSchoolAttachmentController وStudentLeaveManagementController وAttachmentAccess | module/type/school/operation checks، supervisor parent read، no public fallback؛ SchoolAttachmentManagementTest |
| T059 | private writes/MIME/count/size/name/serialization | UploadSecurity، AttachmentService، staff reply، StudentLeaveService، attendance، Attachment hidden/accessors | hash/UUID naming و10MB/max10 institutional وMIME/extension، public-root misconfig denied، Vue protected URLs |
| T060 | native download routes وminimal metadata | Mobile/AttachmentController، routes/api.php، StaffTaskController | Bearer فقط، cookie denial، current state، protected relative URLs، safe headers، DTO بلا path/disk |
| T061 | minimal durable manifest/no destructive down | PrivateFileMigration و2026_11_09_000000_create_private_file_migrations_table |114 migrations forward وprivate-manifest probe؛ nullable orphan owner، unique source+version identity، rollback retain |
| T062 | dry-run/duplicates/crash/conflict/checksum tests | PrivateAttachmentMigrationTest | source/destination/metadata conflicts، orphan/deleted/missing، foreign ID tamper، independent connection lock، alias-only، reexposure،504-file batches |
| T063 | bounded resumable migrate command/service | MigratePrivateAttachments وPrivateAttachmentMigrator | five states، dry-run default، apply scope flag، inventory-only offsets/surfaces، resume-only، copy/hash/CAS/revoke/verify، aggregate-safe output |
| T064 | stop anonymous sensitive delivery and unsafe boot copies | PublicStorageController، PublicStorageLinkMaintainer، PrivateFileClassification، admin media collision guards | no automatic copy/delete directory، parent-backed public purpose، retained manifest denial، test HTTP router/template فقط؛ actual topology deferred |
| T065 | disposable forward/repeat/recovery | probe-private-manifest-migration.php وscoped PAT rehearsal وmigration tests | manifest survives rollback/reapply/repeat، unrelated users retained، byte/hash/copy recovery، no public rollback |
| T066 | actual HTTP representative aliases/copies/headers | private-static-router.php وStaticPrivateUrlTest | PHP HTTP process on loopback، routing before existing-file shortcut، physical copy+symlink، both aliases private404/public200/no-store |
| T067 | gated real-environment procedure | operational-boundaries.md | separate environment/data/serving/cache approvals، backup/private recovery وreadiness blockers؛ document only |
| T068 | focused + web + P2/P3/P4 + full | run-baseline.sh وsummarize-junit.php وprobes | results.md وvalidated-runtime.json؛ all affected suites وknown Excel failure visible |
| T069 | checklist/audit reconciliation | checklist-evidence.md | H01/M01/M07 remediation for tested scope، future real inventory/topology/cache and Phase6 controls remain open |
| T070 | authorized-scope acceptance ثمSTOP | probe-phase5-gate.php وacceptance.md | required classes present، focused clean، prior phases clean، full known regression only؛ no T071 |

لا schema tenant جديدة أو device/role table ولا منافس لmanifest المصادق عليه. لا real inventory مفترضة، ولا actual file migration أو cache purge أو staging/production operation، ولا Phase6.
