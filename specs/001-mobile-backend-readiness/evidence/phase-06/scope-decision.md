# T071 — نطاق Phase6 بعد قرار PM

الفرع001-mobile-backend-readiness، البداية ecb6b7fd7d1b06bf7d4575f1b194ce77fa76fb54، مطابقة Phase5 report/remote/worktree. Phase5 approved صراحةً، وPhase6 مصرح بها حتى بوابة PM فقط، مع feature commits/pushes كما في workflow القائم. لا Phase7 ولا production/staging/provider/data operation.

كل T071–T088 الأصلية تدور حول WhatsApp. أصلها محفوظ في original-requirements.md؛ يلغيها قرار PM الدائم حيث تتعارض. لا تنفذ live control schema/lock/OTP budget/HMAC/callback/delivery matrix ولا تعتبر غيابها blocker. policy الفعالة docs/notification-policy.md؛ البنية الفعلية الحالية DB notifications + web polling فقط. لا mobile push registration/transport أو native notification endpoint مطلوب في عقد foundation الحالي؛ لا يُخترع هنا.

المسار الحالي: business services (approval/subscription/supervision/ticketing/onboarding) → NotificationService → notifications table، ثم NotificationController/RealtimeNotifications.vue للويب. المسار القديم fan-out من NotificationService إلى WhatsAppNotificationService وjobs وN8nWhatsAppClient يُفصل. phone verification/webhooks القديمة تتقاعد، وcontact fields/generic phone behavior تبقى. لا ترسل app notification على أنها بديل لإثبات ملكية الهاتف.

| ID | معالجة النطاق الجديد/معيار القبول |
|---|---|
| T071 | PASS عند حصر القنوات/المداخل/التبعيات وإثبات checkpoint ونشر قرار supersession |
| T072 | اختبارات توليد إشعارات app/web وownership وread-all، دون legacy side effects |
| T073 | استمرار الإشعار مع غياب/خطأ legacy bindings وstale config، دون provider credentials |
| T074 | حفظ historical rows؛ لا false sent/accepted/attempt mutation؛ old state feature superseded |
| T075 | SUPERSEDED؛ لا integration_controls schema أو migration |
| T076 | فصل مولد الإشعار عن القناة الملغاة؛ live-toggle gate superseded |
| T077 | تقاعد routes/config القديمة؛ operator control superseded، لا shared invocation |
| T078 | إبطال outbound entry point دون أي HTTP؛ لا transport implementation جديد |
| T079 | job classes القديمة inert compatibility فقط، لا delivery أو queue resume حقيقي |
| T080 | callbacks القديمة410، history محفوظ؛ claims/HMAC implementation/test requirement superseded |
| T081 | retired response fixed/minimal دون secrets؛ لا provider logs أو state schema جديدة |
| T082 | active notification recipient/ownership/idempotent-read isolation؛ WA worker races superseded |
| T083 | نقل10 generic phone compatibility cases دون delivery؛ WA OTP budgets superseded |
| T084 | لا OTP issue/verify نشط ولا false verification؛ لا قناة إثبات بديلة مخترعة |
| T085 | SUPERSEDED migrations؛ existing disposable migration regression فقط |
| T086 | focused active tests ثم P2–5 ثم full active suite مع retired-file accounting واضح |
| T087 | checklist/spec/contract supersession وH04/T0-12 خارج active feature؛ لا fabricated infrastructure |
| T088 | PHASE_6_PASS_AWAITING_PM_REVIEW بعد الأدلة؛ لا WHATSAPP_KILL_SWITCH_PASS claim ولا Phase7 |

الاختبارات الجديدة للتحقق من القنوات النشطة وإلغاء التبعيات/المداخل فقط؛ ليست WhatsApp delivery tests. legacy test class لا ينفذ، ويُحصى static discovery فقط. تبقى owner isolation والاختبارات السابقة والExcel failure ظاهرة. Approval-email/password reset غير المتعلقين بالميزة يبقيان ضمن regression.
