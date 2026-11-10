# استخدام GitHub Spec Kit في Edaratek

تم تجهيز الأداة داخل جذر التطبيق public_html بتكامل Codex وسكربتات PowerShell.
تنظم الأداة المواصفات والخطط والمهام، ولا تحتاج إلى حزمة Laravel أو Vue.

## الملفات

- `.agents/skills/speckit-*`: مهارات Codex.
- `.specify/memory/constitution.md`: مبادئ المشروع وعزل المدارس والتحقق.
- `.specify/templates/`: قوالب المواصفات والخطط والمهام.
- `.specify/scripts/powershell/`: سكربتات إدارة ملفات المزايا.
- `specs/`: ينشأ عند بدء أول مواصفة.
- `CLAUDE.md` و`PROJECT_DETAILS.md`: مراجع للدراسة، مع التحقق من الكود.

## البدء

افتح مجلد public_html في Codex. افتح جلسة جديدة إذا لم تظهر المهارات.
اكتب الخطوات التالية في محادثة Codex، وليست أوامر PowerShell:

```text
$speckit-specify ادرس وحدة الحضور الحالية وحدد مواصفات تحسين تقرير الغياب الشهري مع الحفاظ على الصلاحيات وعزل المدارس والعربية.
$speckit-clarify
$speckit-plan
$speckit-tasks
$speckit-analyze
$speckit-implement
```

هذا مثال لميزة مستقبلية؛ لم تنشأ له مواصفات أو تغييرات برمجية أثناء التثبيت.
ابدأ بميزة محددة واقرأ الملفات المرتبطة بها قبل اعتماد الخطة. خطوتا clarify وanalyze
اختياريتان حسب الحاجة. استخدم `$speckit-constitution` لتحديث مبادئ المشروع،
و`$speckit-checklist` لمراجعة اكتمال المتطلبات.

## إعداد CLI والتحقق

ملفات المهارات متاحة داخل المشروع. يلزم Git وuv لتثبيت CLI للتشخيص والتحديث:

```powershell
uv tool install specify-cli --from git+https://github.com/github/spec-kit.git
cd D:\edaratek\public_html
specify check
specify integration list
```

إصدار التهيئة: `1.2.6.dev0` من commit الرسمي
`7370e592f3cd9c06e5b698983957e227c75b30e2`.
لتثبيت الإصدار نفسه:

```powershell
uv tool install specify-cli --from git+https://github.com/github/spec-kit.git@7370e592f3cd9c06e5b698983957e227c75b30e2
```

احتفظ بملفات المهارات والقوالب والدستور في Git وراجع الفروق عند التحديث.

المراجع: [المستودع الرسمي](https://github.com/github/spec-kit)،
[المشاريع القائمة](https://github.github.io/spec-kit/guides/existing-projects.html)،
[تكامل Codex](https://github.github.io/spec-kit/integrations/codex.html).
