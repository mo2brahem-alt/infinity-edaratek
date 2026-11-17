# T116 — الدستور: 14 مبدأ فعلياً

المراجع Codex، 2026-11-17؛ مراجعة أدلة؛ تبعها تشغيل توافق PHP 8.3 معزول بتفويض مستقل، دون اتصال بخادم. C=`d57cf9831c3c7123534aedfd34021c7b649968c7`؛ B=`fbc63a4615f48ac0f832d91945d9652aeabef466`. الاختبارات المسجلة على B، والتطبيق/الاختبارات/runner مطابقون في C: [source guard](source-guard.json). قبول Phase9 النهائي بانتظار PM.

المرجع الحاكم [.specify/memory/constitution.md](../../../../.specify/memory/constitution.md)،Version2.0.0. نستخدم أسماء المبادئ الفعلية I–XIV؛قائمة المحاور في تفويضPM تغطى داخلها وليست دستوراً بديلاً.

| Principle | النص الحاكم | التحكم والدليل | نتائج/مرجع فعلي | الحكم | الحد |
|---|---|---|---|---|---|
| I | Security First | P0 active11/11+scope12；HIGH applicable4/4 closed；positive+negativeIDOR/denial/current-state | [P0](p0-closure.md);[HIGH](high-findings.md);R8 | PASS | لا تأكيد audit خارج نطاق الميزة |
| II | Laravel Is the Single Source of Truth | AccountAccessEvaluator/SchoolContextResolver/SubtaskAccessQuery/AttachmentAccess/NotificationService مشتركة؛thin native adapters | [access](../phase-08/access-security.md);[contract](../phase-07/contract-inventory.md) | PASS | لا Flutter أوbusiness backend آخر |
| III | Server-Resolved Tenant Context | current tenant+parent+assignment قبلcount/filter/pagination؛foreign IDs/current relation removal | Phase4 65/65;Phase5 100/100;[isolation](../phase-04/isolation.md) | PASS | كلprepared native endpoint والمسارات القديمة المتأثرة |
| IV | Backward Compatibility | browserCSRF/session/approval/tasks/billing/association/download/export regressions؛security fixes مقصودة | [full results](../phase-08/full-results.md);[compatibility](../phase-07/compatibility.md) | PASS | Excel قديمفاشل مستثنى صراحة؛غير mobile ولا source delta |
| V | Stable Mobile API Contract | 10 endpoints؛DTO allowlists/status/null/date/Arabic/pagination/rates؛additivev1/v2 strategy | [contract](../../contracts/mobile-api.md);Phase7 76/76;LargePreparedLists2/2 | PASS | لا ضمان features مؤجلة أو API لكل323route |
| VI | Mobile Authentication Security | real finite PAT;Bearer-only;expiry/current/target/all revoke;state/permission removal | Phase2 16/16;Phase3 128/128;[access](../phase-08/access-security.md) | PASS | secure-storage تنفيذعميل مؤجل؛native expiry1440min default لاsliding |
| VII | Attachment Security | private physical disk/current parent/permission؛legacy/static denial؛public branding؛history closure | Phase5 100/100;[operator summary](accepted-operator-summary.json) | PASS | server-side فقط؛irretrievable earlier browser bytes خارج التحكم |
| VIII | Supported Notification Channels | PM permanent WhatsApp retirement؛app/web recipients/ownership/read-all/history؛generic phone compatibility | [scope](../phase-06/scope-decision.md);Phase6 20/20;phone10 | PASS | no provider/OTP/Meta/n8n/push implementation |
| IX | Tests Are Release Gates | Executed isolated PHP 8.2 and 8.3 suites; P0 positive/negative coverage; no new failures/errors/skips | [PHP8.3](php83-compatibility.md);[records](php83-runtime.json);[manifest](evidence-manifest.md) | PASS | Known Excel remains FAIL under prior accepted exception; final PM gate withheld |
| X | No Production Changes During Development | isolatedCI/test-only separately approved operations؛Phase9 local docs-only | [PM authorization](pm-phase09-authorization.txt);[operator summary](accepted-operator-summary.json);source-guard.json | PASS | لا production inspection/certification/authorization |
| XI | Phase-Level Implementation | accepted1–8 chain؛explicit9 authorization؛stopT119؛noT120 | [tasks](../../tasks.md);[phase history](readiness-report.md);pm-phase09-authorization.txt | PASS | المرحلة9مفتوحة؛historicalphase prohibitions لا تلغي التفويض الأحدث |
| XII | Explicit Approval Gates | separate prior test deployment/env/QA approvals؛now noGit/deploy/DB/Flutter؛PM acceptance not fabricated | [authorization](pm-phase09-authorization.txt);[PM package](pm-decision.md) | PASS | no root/sudo؛commit/push/merge require future separate approval |
| XIII | No Secret Exposure | only reviewed sanitized summaries؛no.env/cache contents/credentials/rawHTTP؛redaction tests | EnvironmentSafetyTest/MobileErrorContractTest;[safe summary](accepted-operator-summary.json) | PASS | لا ادعاء أن حادث exposure التاريخي لم يحدث؛لا نسخ مادته أو hash لها |
| XIV | Audit-Driven Work | all84/12/5/14 dispositions؛FR/SR/US mapping؛immutable baselines andfailedExcel preserved | [manifest](evidence-manifest.md);[checklist](checklist-review.md);[readiness](readiness-report.md) | PASS | not-ready held until technical gaps+actualPM decision resolved |

```ini
T116=PASS
CONSTITUTION_EVALUATED=14/14
CONSTITUTION_PASS=14/14
CONSTITUTION_BLOCKED=0
```

أُغلقIX بدليلتنفيذPHP8.3المعتمد؛لاexceptionجديدة،وفشلExcelالسابقمحفوظ. لميمسالدستورأوsourceأوreviewercheckboxes.
