# T101 — مطابقة CHK051–064

المرجع: العقد المحمول docs/mobile-api-v1.md وcontracts/mobile-api.md، ونتائج المصدر النهائي في results.md/validated-runtime.json. PASS أدناه للنطاق التقني المعزول فقط؛ موافقة PM على Phase7 ما زالت مطلوبة. لا إغلاق للجاهزية العامة أو البيئة الفعلية.

| البند | التنفيذ والاختبارات | المعالجة |
|---|---|---|
| CHK051 | نفس10 نقاط routes/api.php، تسجيل bootstrap قائم، adapters مشتركة؛ NativeWebCompatibilityTest + P2–P6/full consumers | PASS تقني |
| CHK052 | تعريف additive optional fields مقابل breaking v2/coexistence وانتقال معلن؛ DTO fixtures ثابتة؛ لا v2 أو CRUD جديد مفترض | PASS توثيق/توافق النطاق |
| CHK053 | MobileApiContractTest يغطي login/me/devices/task/detail/downloads و3 mutations204 فارغة؛ لا201 حيث لا إنشاء أعمال | PASS |
| CHK054 | MobileErrorContractTest: guest401 لكل protected endpoint، invalid credentials موحد، account precedence403 وrole/suspension403 | PASS |
| CHK055 | foreign/missing/malformed/range404، unlinked409، typed conflict fixtures409، body/query/field422؛ capability يسبق disclosure | PASS |
| CHK056 | RATE_LIMITED مع Retry-After/retry_after مطابقين، lock timeout1، forced500 حتى debug=true دون trace/echo، UUID مولد | PASS |
| CHK057 | مفاتيح DTO مطابقة allowlist حرفياً؛ لا password/PAT hash/abilities/disk/path/permission groups؛ plaintext عند login فقط؛ private download policy/headers | PASS |
| CHK058 | MobileSerializationTest: date-only فعلي عبر timezone وDST، UTC Z، العربية، IDs أكبر من32bit، null/status enum4؛ لا monetary field هنا أو DTO مخترع | PASS |
| CHK059 | minimum actual /me، current capabilities/permission removal/school=null، phone canonical timestamp+invalidation؛ DB log لا provisioning عبر GET | PASS |
| CHK060 | default25/max100/page bounds، null/empty/array/invalid422، empty/overrun200، ثابت meta/links وrelative validated URLs؛ مرفقات detail مرقمة | PASS |
| CHK061 | title-only literal bound search، status/sort allowlist، distinct date ascending/descending + ties/NULL-last + foreign counts؛ query budget صغير/1001 rows | PASS للنقاط الحالية؛ لا ادعاء benchmark لكل modules أو بيئة حية |
| CHK062 | login5/30، read120/300، session10/30، reset3/10؛ reset/login/IP rotation/recovery، atomic shared fixture process races4 budgets؛ لا credentials/keys echo | PASS للنطاق المعزول والقيم الفعلية |
| CHK063 | WhatsApp/OTP send/verify/budget غير مطلوب نهائياً؛ القرار PM supersedes original requirement. Generic phone compatibility موجودة ومنفذة في Phase6 regression | SUPERSEDED، ليس اختبار delivery ناجحاً |
| CHK064 | PasswordResetTest7 حالات ضمن Phase3 regression: known/unknown general outcome، legitimate fake mail، identity/IP ceilings/recovery؛ middleware الحجز الذري مع نفس الردود والأرقام | PASS، تغطية سابقة أعيد التحقق منها |

CHK067: subset خطأ v1/log correlation/redaction مثبت؛ مراجعة security/regression الأشمل في Phase8 باقية. CHK078: Phase6 اعتمد صراحة، Phase7 technical acceptance ينتظر PM. CHK006/034/040/068/069/074 والـoperational/final readiness لا تُغلق بما يتجاوز أدلتها السابقة. actual aliases/CDN UNKNOWN، ولا local fixture يشهد على الخوادم.

PHASE_8_STARTED=NO. MOBILE_BACKEND_NOT_READY.
