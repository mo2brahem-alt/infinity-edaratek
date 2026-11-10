# T045/T046/T051 — مصدر السياق الحالي

التحقق التنفيذي النهائي مسجل في [results.md](results.md). هذا الملف يصف التصميم وربطه بحالات الاختبار، ولا يستبدل نتيجة CI.

`SchoolContextResolver` يعيد قراءة المستخدم والمدرسة من قاعدة البيانات عند القرار؛ لا static cache ولا سياق من token أو header. `users.school_id` يحدد مرشح المدرسة، ثم تثبت الأهلية والدور والعلاقة والنشاط حسب العملية. middleware يضع `school_context_id` في attributes الخاصة بالطلب بعد الإثبات. لا قراءة native تنشئ مدرسة أو عضوية.

| العملية | عوامل القرار من الخادم | أثر الإزالة/التغيير |
|---|---|---|
| staff tasks | حساب مؤهل، staff، المدرسة الحالية ACTIVE، assignment، مدرسة ticket الأم | النقل يخفي المهام القديمة من القائمة والعدد والتفاصيل والإجراءات |
| manager | حساب مؤهل، school_manager، users.school_id وschools.manager_user_id متطابقان | تغيير المالك يمنع التشغيل ويجعل مدرسة `/me` فارغة |
| supervisor selector | supervisor وعلاقة schools.supervisor_id الحالية، ACTIVE وACTIVE_ASSOCIATION، اعتماد متبادل عند وجود طلب legacy مقبول | إلغاء/استبدال العلاقة يحجب القائمة والعدد والتفاصيل والتعديل والإغلاق والإنشاء |
| delegated groups | مدرسة محلولة، predicate صريح على school_permission_groups.school_id، استعلام جديد ولو سبق تحميل collection | مجموعة المدرسة السابقة لا تمنح صلاحية بعد النقل؛ إزالة permission_names تسري فوراً |
| `/me` | الهوية مؤهلة؛ school ناتجة عن identitySchool | غير مرتبط: 200 وschool=null، وتشغيل مدرسة native:409 SCHOOL_CONTEXT_REQUIRED |
| suspension | نشاط المدرسة شرط مستقل عن موافقة الحساب | native task403 SCHOOL_SUSPENDED وquick-setup403 |

حسابات staff/teacher لا تحتاج اعتماد إشراف نشط إذا كانت قاعدة العمل الحالية `SchoolAssociationState::allowsOperationalAccessFor` تسمح لها بالتشغيل مع مدرسة ACTIVE. مدير المدرسة المالك لا يحتاج اعتماد إشراف لتشغيلها، لكن تعليق المدرسة يمنع التشغيل. يحتفظ middleware ملكية المدير بوضع operational=false لمسارات الإعداد/طلب الارتباط القائمة؛ هذا لا يفتح مسارات التشغيل.

لا تتحول صلاحية مشاهدة تذكرة بصفة supervisor إلى سلطة manager لإسناد المهام أو عرض/إغلاق تذاكر مدير مدرسة أخرى. `TicketPolicy::manage/assignSubtask` يثبتان دور المدير وملكيته وسياقه وإسناد التذكرة مستقلاً عن صلاحية العرض العامة. يظل super_admin قادراً على العرض العام المسموح في policy، دون منحه submit بدور staff أو فرض global scope جديد على استعلامات الإدارة.

`CurrentTenantContextTest` يغطي null/409 وعدم التوفير، التعليق، headers، انتحال المدير، سحب الدور والإسناد، سحب المالك بعد إصدار token، stale groups وإزالة صلاحياتها، سحب علاقة المشرف وحالة legacy غير المعتمدة ثم تأكيدها وإلغائها. `ForeignRelationshipTest` يغطي حساب مدير+مشرف وفصل سلطتيهما. `StaffTaskIsolationTest` يغطي النقل بعد الإصدار مع بقاء الإسنادات التاريخية.

لا توجد permission مستقلة للمهام في نموذج العمل الحالي. سلطة staff task هي الدور والإسناد والمدرسة والأم؛ اختبارات سحب الدور والإسناد تثبت سحبها. اختبار سحب الصلاحيات التشغيلية يستخدم المجموعة المدرسية وacademic-planning، واختبارات Phase3 تثبت سحب Spatie permissions الحالية. لم تُخترع tasks.view أو شرط جديد يكسر أعمال الموظف القائمة.

إصلاح M02 مقصود: غُيّر اختبار QuickSetupStatusTest الخاص بمدرسة SUSPENDED من توقع200 إلى403؛ حالة المدرسة ACTIVE المنتظرة لاعتماد الإشراف تبقى مسموحة للمدير المالك. بقية اختبارات الويب القائمة تبقى مرجع التوافق.
