# Phase6 checklist dispositions

The PM decision permanently removes the product capability. SUPERSEDED means an old requirement is no longer implemented/tested/required, not a passing WhatsApp feature. [Original task text](original-requirements.md) remains for audit; [source design](admission-design.md) explains the replacement app/web boundaries.

| Requirement | Disposition and evidence |
|---|---|
| ConstitutionVIII, US10, FR027-029, SR014-016, H04, T0-12 | SUPERSEDED original live control/disable/admission/reenable feature. Canonical policy is docs/notification-policy.md; active source has no provider transport. No WHATSAPP_KILL_SWITCH_PASS claim. |
| CHK043-044 | SUPERSEDED live authority/freshness/disable acknowledgement. Config cannot restore the permanently removed channel; no control source/schema/operator command. |
| CHK045-047 | SUPERSEDED delivery/job/race matrices. Source retirement plus active app/web side-effect assertions replace dependence on that integration; old delivery tests are not executed. |
| CHK048-049 | SUPERSEDED old cancelled/reason/reenable state implementation. Existing history is preserved without rewriting claims, attempts or delivery status; archive immutability is checked during app notification generation. |
| CHK050 | SUPERSEDED WhatsApp HMAC/callback delivery feature regressions. Old callbacks now fixed410; general secret protection remains active. |
| SR021 / CHK063 / L04 | OTP sending/verification/global budgets SUPERSEDED. Generic phone normalization and phone-change invalidation retained as10 executable cases. Old local verification routes410, no OTP write or false verified state. |
| EC19-20/22 | WhatsApp OTP/rate/admission/in-flight portions SUPERSEDED. General rate policies and current phone-state compatibility remain. EC21 general allowlist/redaction remains fully active. |
| CHK067 | WhatsApp kill-skip/provider observability superseded. Fixed retirement payload has no input reflection or provider details. General auth/error/log-redaction work remains OPEN for its later phase scope. |
| CHK068-069 / T075 / T085 | No new Phase6 schema proposed; control/reason migration/recovery SUPERSEDED. Existing PAT/private-manifest disposable migration probes remain regression evidence; real environment/backup recovery not certified. |
| CHK065-066 | Affected app/web notification business and previous-phase regressions execute; broad later-phase consolidated regression review is not closed here. |
| CHK006/034/040/061/074 | OPEN for their remaining operational/static/cache/contract/large-data proof. Actual aliases/CDN remain UNKNOWN. |
| CHK078 and Phase6 PM gate | Technical Phase6 evidence is submitted for PM review. Phase7 tasks remain unchecked and unstarted. No final mobile-readiness declaration. |

Executed result references and measured counts are in [results](results.md), [runtime records](validated-runtime.json), and [acceptance](acceptance.md). The historical checklist's WA entries are explicitly labelled superseded rather than ticked as implemented. No broad suite exclusion or unexplained runtime skip is accepted.
