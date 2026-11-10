# T104 — فحص الوصول والصلاحيات

المصدر المرجعي8020e8b للتطبيق لم يتغير فيPhase8. مراجعة المصدر لا تكفي وحدها؛ نتائج إعادة التنفيذ وأعداد كلclass في validated-runtime.json وfull-results.md.

| الحدود | مصدر القرار المشترك | الاختبارات الفعلية ضمن focused/full |
|---|---|---|
| native identity/PAT/bearer-only | NativeSanctumAuthenticator،NativeTokenService،AuthenticateNativeBearer | tests/Feature/Mobile/NativeAuthenticationTest.php؛NativeAccountStateTest.php؛NativeDeviceLifecycleTest.php؛NativeWebCompatibilityTest.php |
| أهلية حالية وunion متعدد الأدوار | AccountAccessEvaluator،EnsureNativeAccountAccess،UserApprovalService | Mobile/Phase3/AccountApprovalMatrixTest.php؛PrivilegeEscalationTest.php؛WebSessionSafetyTest.php؛AccountApprovalFlowTest.php |
| school/manager/staff/supervisor والانتقال | SchoolContextResolver،SubtaskAccessQuery،SubtaskPolicy | Mobile/Phase4/CurrentTenantContextTest.php؛StaffTaskIsolationTest.php؛ForeignRelationshipTest.php؛SchoolTenantIsolationTest.php |
| mass assignment/role ownership | FormRequests/Api/V1،school role assignment/gates | SchoolUserRoleAssignmentApiTest.php؛SystemRoleManagementApiTest.php؛Phase3/PrivilegeEscalationTest.php |
| anonymous/unauthorized/foreign/malformed | نفس middleware/policy قبل DTO/bytes | Mobile/Phase7/MobileErrorContractTest.php؛MobileApiContractTest.php؛Phase5/PrivateAttachmentAuthorizationTest.php |

الإثبات يشمل نجاح المأذون و401/403/404/409/422/429، بيانات دخول مجهولة/خاطئة،رموز منتهية/ملغاة/حساب محذوف أو ممنوع بعد إصدار الرمز، cookie/query-token fallback،سحب الصلاحيات/المجموعات/العلاقات، staff transfers،IDOR والآباء غير المتسقين وعدم تغيير بيانات غير مأذونة. لا frontend/primaryRole/tenant field بديل لقرار الخادم. logout الحالي وحده يظل استثناءً لإنهاء رمز حساب ممنوع ولا يعيد بيانات أعمال.

النطاق هو native endpoints10 والمسارات القديمة المتأثرة بالمبادرة، لا ادعاء تدقيق كل323 route أو ميزات عمل خارج المواصفة. لم تُعدّل policy/request/resource/controller لتحقيق نتيجة اختبار. actual runtime class counts—not static method discovery—هي دليل التنفيذ.
