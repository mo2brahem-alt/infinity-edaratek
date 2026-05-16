<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Award, Download, FileText, Printer, RefreshCcw, Save, ShieldCheck, Stamp, Trash2 } from 'lucide-vue-next';
import RoleLayout from '@/Layouts/RoleLayout.vue';

const props = defineProps({
    school: { type: Object, default: null },
    templates: { type: Array, default: () => [] },
    signatures: { type: Array, default: () => [] },
    certificates: { type: Array, default: () => [] },
    students: { type: Array, default: () => [] },
    recipients: { type: Array, default: () => [] },
    filterOptions: { type: Object, default: () => ({}) },
    permissions: { type: Object, default: () => ({}) },
    isManager: { type: Boolean, default: false },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || null);
const roleForLayout = computed(() => props.isManager || currentUser.value?.primary_role === 'school_manager' ? 'SCHOOL_MANAGER' : 'STAFF');
const activeTab = ref('issue');
const editingTemplateId = ref(null);
const classroomFilter = ref('');
const recipientTypeFilter = ref('student');
const cancelingCertificateId = ref(null);
const cancelReason = ref('');

const defaultStyle = (font = 'Cairo', size = 20, weight = '700', color = '#0f172a') => ({
    font_family: font,
    font_size: size,
    font_weight: weight,
    color,
});

const firstType = computed(() => props.filterOptions.types?.[0]?.value || 'appreciation');
const firstFrame = computed(() => props.filterOptions.frames?.[0]?.key || 'formal-simple');
const firstSignature = computed(() => props.signatures.find((signature) => signature.is_default)?.id || props.signatures[0]?.id || '');

const templateForm = useForm({
    name: '',
    type: firstType.value,
    orientation: 'landscape',
    paper_size: 'A4',
    frame_key: firstFrame.value,
    title_text: '',
    default_body: '',
    title_style_json: defaultStyle('Reem Kufi', 34, '800'),
    student_name_style_json: defaultStyle('Amiri', 42, '800'),
    body_style_json: defaultStyle('Cairo', 20, '500', '#1f2937'),
    date_style_json: defaultStyle('Cairo', 16, '600', '#475569'),
    signature_style_json: defaultStyle('Cairo', 16, '700', '#334155'),
    is_active: true,
});

const signatureForm = useForm({
    name: '',
    title: '',
    signature: null,
    stamp: null,
    is_default: false,
    is_active: true,
});

const issueForm = useForm({
    certificate_template_id: '',
    school_certificate_signature_id: '',
    recipient_type: 'student',
    recipient_ids: [],
    type: firstType.value,
    title: '',
    body: '',
    certificate_date: new Date().toISOString().slice(0, 10),
    hijri_date: '',
    teacher_name: '',
    activity_name: '',
    achievement_detail: '',
    school_academic_year_id: '',
    school_term_id: '',
});

watch(
    () => firstSignature.value,
    (value) => {
        if (!issueForm.school_certificate_signature_id && value) {
            issueForm.school_certificate_signature_id = value;
        }
    },
    { immediate: true }
);

const typeLabel = (value) => props.filterOptions.types?.find((type) => type.value === value)?.label || 'شهادة';
const phraseForType = (value) => props.filterOptions.phrases?.[value] || '';

watch(
    () => templateForm.type,
    (value) => {
        if (!templateForm.title_text) templateForm.title_text = typeLabel(value);
        if (!templateForm.default_body) templateForm.default_body = phraseForType(value);
    },
    { immediate: true }
);

watch(
    () => issueForm.type,
    (value) => {
        if (!issueForm.title) issueForm.title = typeLabel(value);
        if (!issueForm.body) issueForm.body = phraseForType(value);
    },
    { immediate: true }
);

watch(recipientTypeFilter, (value) => {
    issueForm.recipient_type = value;
    issueForm.recipient_ids = [];
    classroomFilter.value = '';
});

const filteredRecipients = computed(() => {
    const recipients = props.recipients.filter((recipient) => recipient.type === recipientTypeFilter.value);
    if (recipientTypeFilter.value !== 'student' || !classroomFilter.value) return recipients;
    return recipients.filter((recipient) => Number(recipient.school_classroom_id) === Number(classroomFilter.value));
});

const selectedTemplate = computed(() => props.templates.find((template) => Number(template.id) === Number(issueForm.certificate_template_id)) || null);

watch(selectedTemplate, (template) => {
    if (!template) return;
    issueForm.type = template.type;
    issueForm.title = template.title_text || template.type_label || typeLabel(template.type);
    issueForm.body = template.default_body || phraseForType(template.type);
});

const toggleRecipient = (recipientId) => {
    const id = Number(recipientId);
    const selected = new Set(issueForm.recipient_ids.map(Number));
    if (selected.has(id)) selected.delete(id);
    else selected.add(id);
    issueForm.recipient_ids = [...selected];
};

const selectVisibleRecipients = () => {
    issueForm.recipient_ids = filteredRecipients.value.map((recipient) => Number(recipient.id));
};

const clearSelectedRecipients = () => {
    issueForm.recipient_ids = [];
};

const resetTemplateForm = () => {
    editingTemplateId.value = null;
    templateForm.reset();
    templateForm.type = firstType.value;
    templateForm.frame_key = firstFrame.value;
    templateForm.title_text = typeLabel(firstType.value);
    templateForm.default_body = phraseForType(firstType.value);
    templateForm.title_style_json = defaultStyle('Reem Kufi', 34, '800');
    templateForm.student_name_style_json = defaultStyle('Amiri', 42, '800');
    templateForm.body_style_json = defaultStyle('Cairo', 20, '500', '#1f2937');
    templateForm.date_style_json = defaultStyle('Cairo', 16, '600', '#475569');
    templateForm.signature_style_json = defaultStyle('Cairo', 16, '700', '#334155');
    templateForm.is_active = true;
};

const editTemplate = (template) => {
    editingTemplateId.value = template.id;
    templateForm.name = template.name || '';
    templateForm.type = template.type || firstType.value;
    templateForm.orientation = template.orientation || 'landscape';
    templateForm.paper_size = template.paper_size || 'A4';
    templateForm.frame_key = template.frame_key || firstFrame.value;
    templateForm.title_text = template.title_text || '';
    templateForm.default_body = template.default_body || '';
    templateForm.title_style_json = { ...defaultStyle('Reem Kufi', 34, '800'), ...(template.title_style_json || {}) };
    templateForm.student_name_style_json = { ...defaultStyle('Amiri', 42, '800'), ...(template.student_name_style_json || {}) };
    templateForm.body_style_json = { ...defaultStyle('Cairo', 20, '500', '#1f2937'), ...(template.body_style_json || {}) };
    templateForm.date_style_json = { ...defaultStyle('Cairo', 16, '600', '#475569'), ...(template.date_style_json || {}) };
    templateForm.signature_style_json = { ...defaultStyle('Cairo', 16, '700', '#334155'), ...(template.signature_style_json || {}) };
    templateForm.is_active = Boolean(template.is_active);
    activeTab.value = 'templates';
};

const saveTemplate = () => {
    const options = { preserveScroll: true, onSuccess: resetTemplateForm };
    if (editingTemplateId.value) {
        templateForm.put(route('school.certificates.templates.update', editingTemplateId.value), options);
        return;
    }
    templateForm.post(route('school.certificates.templates.store'), options);
};

const deleteTemplate = (template) => {
    router.delete(route('school.certificates.templates.destroy', template.id), { preserveScroll: true });
};

const saveSignature = () => {
    signatureForm.post(route('school.certificates.signatures.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => signatureForm.reset(),
    });
};

const deleteSignature = (signature) => {
    router.delete(route('school.certificates.signatures.destroy', signature.id), { preserveScroll: true });
};

const issueCertificates = () => {
    issueForm.post(route('school.certificates.issue'), {
        preserveScroll: true,
        onSuccess: () => {
            issueForm.recipient_ids = [];
            activeTab.value = 'issued';
        },
    });
};

const cancelCertificate = (certificate) => {
    cancelingCertificateId.value = certificate.id;
    cancelReason.value = '';
};

const submitCancelCertificate = () => {
    if (!cancelingCertificateId.value || !cancelReason.value.trim()) return;

    router.post(
        route('school.certificates.cancel', cancelingCertificateId.value),
        { cancel_reason: cancelReason.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                cancelingCertificateId.value = null;
                cancelReason.value = '';
            },
        }
    );
};
</script>

<template>
    <Head title="الشهادات" />

    <RoleLayout title="الشهادات" :role="roleForLayout" :permissions="props.permissions">
        <div class="ui-page-shell">
            <section class="ui-page-hero">
                <div>
                    <p class="ui-page-kicker">قسم الشهادات</p>
                    <h1 class="ui-page-title">إصدار شهادات المدرسة</h1>
                    <p class="ui-page-subtitle">إدارة قوالب الشهادات وإصدار شهادات آمنة لأي طالب أو منسوب داخل نطاق مدرسة {{ school?.name || '' }}.</p>
                </div>
                <Award class="h-12 w-12 text-emerald-300" />
            </section>

            <div class="grid gap-3 md:grid-cols-4">
                <button type="button" class="rounded-lg border px-4 py-3 text-sm font-bold" :class="activeTab === 'issue' ? 'border-emerald-400 bg-emerald-500/15 text-emerald-100' : 'border-white/10 text-slate-300'" @click="activeTab = 'issue'">إصدار شهادة</button>
                <button type="button" class="rounded-lg border px-4 py-3 text-sm font-bold" :class="activeTab === 'templates' ? 'border-emerald-400 bg-emerald-500/15 text-emerald-100' : 'border-white/10 text-slate-300'" @click="activeTab = 'templates'">القوالب</button>
                <button type="button" class="rounded-lg border px-4 py-3 text-sm font-bold" :class="activeTab === 'signatures' ? 'border-emerald-400 bg-emerald-500/15 text-emerald-100' : 'border-white/10 text-slate-300'" @click="activeTab = 'signatures'">التوقيعات والأختام</button>
                <button type="button" class="rounded-lg border px-4 py-3 text-sm font-bold" :class="activeTab === 'issued' ? 'border-emerald-400 bg-emerald-500/15 text-emerald-100' : 'border-white/10 text-slate-300'" @click="activeTab = 'issued'">الشهادات الصادرة</button>
            </div>

            <section v-if="activeTab === 'issue'" class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,1.15fr)_minmax(320px,.85fr)]">
                <form class="ui-panel space-y-4" @submit.prevent="issueCertificates">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="space-y-2">
                            <span class="text-sm font-bold text-slate-200">القالب</span>
                            <select v-model="issueForm.certificate_template_id" class="ui-input">
                                <option value="">بدون قالب محدد</option>
                                <option v-for="template in templates" :key="template.id" :value="template.id">{{ template.name }}</option>
                            </select>
                        </label>
                        <label class="space-y-2">
                            <span class="text-sm font-bold text-slate-200">نوع الشهادة</span>
                            <select v-model="issueForm.type" class="ui-input">
                                <option v-for="type in filterOptions.types" :key="type.value" :value="type.value">{{ type.label }}</option>
                            </select>
                        </label>
                        <label class="space-y-2">
                            <span class="text-sm font-bold text-slate-200">العام الدراسي</span>
                            <select v-model="issueForm.school_academic_year_id" class="ui-input">
                                <option value="">غير محدد</option>
                                <option v-for="year in filterOptions.academicYears" :key="year.id" :value="year.id">{{ year.name }}</option>
                            </select>
                        </label>
                        <label class="space-y-2">
                            <span class="text-sm font-bold text-slate-200">الفصل الدراسي</span>
                            <select v-model="issueForm.school_term_id" class="ui-input">
                                <option value="">غير محدد</option>
                                <option v-for="term in filterOptions.terms" :key="term.id" :value="term.id">{{ term.name }}</option>
                            </select>
                        </label>
                        <label class="space-y-2">
                            <span class="text-sm font-bold text-slate-200">التوقيع والختم</span>
                            <select v-model="issueForm.school_certificate_signature_id" class="ui-input">
                                <option value="">بدون توقيع</option>
                                <option v-for="signature in signatures" :key="signature.id" :value="signature.id">{{ signature.name }} - {{ signature.title || 'بدون مسمى' }}</option>
                            </select>
                        </label>
                        <label class="space-y-2">
                            <span class="text-sm font-bold text-slate-200">تاريخ الشهادة</span>
                            <input v-model="issueForm.certificate_date" type="date" class="ui-input" />
                        </label>
                    </div>

                    <label class="space-y-2 block">
                        <span class="text-sm font-bold text-slate-200">عنوان الشهادة</span>
                        <input v-model="issueForm.title" class="ui-input" />
                    </label>
                    <label class="space-y-2 block">
                        <span class="text-sm font-bold text-slate-200">نص الشهادة</span>
                        <textarea v-model="issueForm.body" rows="5" class="ui-input"></textarea>
                    </label>

                    <div class="grid gap-4 md:grid-cols-3">
                        <label class="space-y-2">
                            <span class="text-sm font-bold text-slate-200">النشاط</span>
                            <input v-model="issueForm.activity_name" class="ui-input" placeholder="اسم النشاط" />
                        </label>
                        <label class="space-y-2">
                            <span class="text-sm font-bold text-slate-200">الإنجاز</span>
                            <input v-model="issueForm.achievement_detail" class="ui-input" placeholder="تفاصيل الإنجاز" />
                        </label>
                        <label class="space-y-2">
                            <span class="text-sm font-bold text-slate-200">التاريخ الهجري</span>
                            <input v-model="issueForm.hijri_date" class="ui-input" placeholder="اختياري" />
                        </label>
                    </div>

                    <div v-if="issueForm.errors.recipient_ids || issueForm.errors.student_ids" class="text-sm font-bold text-red-300">{{ issueForm.errors.recipient_ids || issueForm.errors.student_ids }}</div>
                    <button class="ui-primary-button inline-flex items-center gap-2" :disabled="issueForm.processing || !permissions.can_issue_certificates">
                        <Save class="h-4 w-4" />
                        إصدار الشهادة
                    </button>
                </form>

                <aside class="ui-panel space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-black text-white">اختيار المستفيدين</h2>
                        <span class="rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-bold text-emerald-200">{{ issueForm.recipient_ids.length }} محدد</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" class="rounded-lg border px-3 py-2 text-sm font-bold" :class="recipientTypeFilter === 'student' ? 'border-emerald-400 bg-emerald-500/15 text-emerald-100' : 'border-white/10 text-slate-300'" @click="recipientTypeFilter = 'student'">الطلاب</button>
                        <button type="button" class="rounded-lg border px-3 py-2 text-sm font-bold" :class="recipientTypeFilter === 'user' ? 'border-emerald-400 bg-emerald-500/15 text-emerald-100' : 'border-white/10 text-slate-300'" @click="recipientTypeFilter = 'user'">منسوبو المدرسة</button>
                    </div>
                    <select v-if="recipientTypeFilter === 'student'" v-model="classroomFilter" class="ui-input">
                        <option value="">كل الفصول</option>
                        <option v-for="classroom in filterOptions.classrooms" :key="classroom.id" :value="classroom.id">{{ classroom.grade_name }} - {{ classroom.name }}</option>
                    </select>
                    <div class="flex gap-2">
                        <button type="button" class="ui-secondary-button flex-1" @click="selectVisibleRecipients">تحديد الظاهر</button>
                        <button type="button" class="ui-secondary-button flex-1" @click="clearSelectedRecipients">مسح</button>
                    </div>
                    <div class="max-h-[430px] space-y-2 overflow-y-auto pr-1">
                        <label v-for="recipient in filteredRecipients" :key="recipient.key" class="flex cursor-pointer items-start gap-3 rounded-lg border border-white/10 bg-white/5 p-3">
                            <input type="checkbox" class="mt-1" :checked="issueForm.recipient_ids.map(Number).includes(Number(recipient.id))" @change="toggleRecipient(recipient.id)" />
                            <span>
                                <span class="block font-bold text-white">{{ recipient.name }}</span>
                                <span class="text-xs text-slate-400">{{ recipient.label }}<template v-if="recipient.description"> / {{ recipient.description }}</template></span>
                            </span>
                        </label>
                        <div v-if="filteredRecipients.length === 0" class="rounded-lg border border-white/10 bg-white/5 p-4 text-center text-sm text-slate-300">لا توجد بيانات في هذا النطاق.</div>
                    </div>
                </aside>
            </section>

            <section v-if="activeTab === 'templates'" class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,.9fr)_minmax(0,1.1fr)]">
                <form class="ui-panel space-y-4" @submit.prevent="saveTemplate">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-black text-white">{{ editingTemplateId ? 'تعديل قالب' : 'قالب جديد' }}</h2>
                        <button type="button" class="text-sm font-bold text-slate-300" @click="resetTemplateForm"><RefreshCcw class="inline h-4 w-4" /> جديد</button>
                    </div>
                    <input v-model="templateForm.name" class="ui-input" placeholder="اسم القالب" />
                    <div class="grid gap-4 md:grid-cols-2">
                        <select v-model="templateForm.type" class="ui-input">
                            <option v-for="type in filterOptions.types" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                        <select v-model="templateForm.frame_key" class="ui-input">
                            <option v-for="frame in filterOptions.frames" :key="frame.key" :value="frame.key">{{ frame.label }}</option>
                        </select>
                    </div>
                    <input v-model="templateForm.title_text" class="ui-input" placeholder="عنوان الشهادة" />
                    <textarea v-model="templateForm.default_body" rows="6" class="ui-input" placeholder="نص الشهادة الافتراضي"></textarea>
                    <div class="grid gap-3 md:grid-cols-2">
                        <label v-for="field in [
                            ['title_style_json', 'خط العنوان'],
                            ['student_name_style_json', 'خط اسم الطالب'],
                            ['body_style_json', 'خط النص'],
                            ['date_style_json', 'خط التاريخ'],
                            ['signature_style_json', 'خط التوقيع'],
                        ]" :key="field[0]" class="space-y-2">
                            <span class="text-xs font-bold text-slate-300">{{ field[1] }}</span>
                            <select v-model="templateForm[field[0]].font_family" class="ui-input">
                                <option v-for="font in filterOptions.fonts" :key="font.value" :value="font.value">{{ font.label }}</option>
                            </select>
                        </label>
                    </div>
                    <button class="ui-primary-button inline-flex items-center gap-2" :disabled="templateForm.processing">
                        <Save class="h-4 w-4" />
                        حفظ القالب
                    </button>
                </form>

                <div class="space-y-3">
                    <article v-for="template in templates" :key="template.id" class="ui-panel">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-black text-white">{{ template.name }}</h3>
                                <p class="text-sm text-slate-300">{{ template.type_label }} • {{ template.frame_key || 'بدون إطار' }}</p>
                            </div>
                            <div class="flex gap-2">
                                <button class="ui-secondary-button" type="button" @click="editTemplate(template)">تعديل</button>
                                <button v-if="permissions.can_delete_certificate_templates" class="rounded-lg bg-red-500/15 px-3 py-2 text-sm font-bold text-red-200" type="button" @click="deleteTemplate(template)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </article>
                    <div v-if="templates.length === 0" class="ui-panel text-slate-300">لا توجد قوالب شهادات بعد.</div>
                </div>
            </section>

            <section v-if="activeTab === 'signatures'" class="mt-6 grid gap-5 lg:grid-cols-[minmax(0,.85fr)_minmax(0,1.15fr)]">
                <form class="ui-panel space-y-4" @submit.prevent="saveSignature">
                    <h2 class="text-lg font-black text-white">إضافة توقيع وختم</h2>
                    <input v-model="signatureForm.name" class="ui-input" placeholder="اسم صاحب التوقيع" />
                    <input v-model="signatureForm.title" class="ui-input" placeholder="المسمى الوظيفي" />
                    <label class="block space-y-2">
                        <span class="text-sm font-bold text-slate-200">صورة التوقيع</span>
                        <input type="file" accept="image/png,image/jpeg,image/webp" class="ui-input" @change="signatureForm.signature = $event.target.files?.[0] || null" />
                    </label>
                    <label class="block space-y-2">
                        <span class="text-sm font-bold text-slate-200">صورة الختم</span>
                        <input type="file" accept="image/png,image/jpeg,image/webp" class="ui-input" @change="signatureForm.stamp = $event.target.files?.[0] || null" />
                    </label>
                    <label class="flex items-center gap-2 text-sm font-bold text-slate-200">
                        <input v-model="signatureForm.is_default" type="checkbox" />
                        تعيين كتوقيع افتراضي
                    </label>
                    <button class="ui-primary-button inline-flex items-center gap-2" :disabled="signatureForm.processing || !permissions.can_manage_certificate_signatures">
                        <Stamp class="h-4 w-4" />
                        حفظ التوقيع
                    </button>
                </form>
                <div class="space-y-3">
                    <article v-for="signature in signatures" :key="signature.id" class="ui-panel flex items-center justify-between gap-4">
                        <div>
                            <h3 class="font-black text-white">{{ signature.name }}</h3>
                            <p class="text-sm text-slate-300">{{ signature.title || 'بدون مسمى' }}</p>
                            <span v-if="signature.is_default" class="text-xs font-bold text-emerald-300">افتراضي</span>
                        </div>
                        <button v-if="permissions.can_manage_certificate_signatures" type="button" class="rounded-lg bg-red-500/15 px-3 py-2 text-red-200" @click="deleteSignature(signature)">تعطيل</button>
                    </article>
                    <div v-if="signatures.length === 0" class="ui-panel text-slate-300">لا توجد توقيعات محفوظة بعد.</div>
                </div>
            </section>

            <section v-if="activeTab === 'issued'" class="mt-6 ui-panel">
                <div class="mb-4 flex items-center gap-2">
                    <ShieldCheck class="h-5 w-5 text-emerald-300" />
                    <h2 class="text-lg font-black text-white">الشهادات الصادرة</h2>
                </div>
                <form v-if="cancelingCertificateId" class="mb-4 rounded-xl border border-red-400/30 bg-red-500/10 p-4" @submit.prevent="submitCancelCertificate">
                    <label class="block space-y-2">
                        <span class="text-sm font-bold text-red-100">سبب إلغاء الشهادة</span>
                        <textarea v-model="cancelReason" rows="3" class="ui-input" placeholder="اكتب سبب الإلغاء بوضوح"></textarea>
                    </label>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white" :disabled="!cancelReason.trim()">تأكيد الإلغاء</button>
                        <button type="button" class="ui-secondary-button" @click="cancelingCertificateId = null">تراجع</button>
                    </div>
                </form>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-right text-sm">
                        <thead class="text-slate-300">
                            <tr>
                                <th class="p-3">رقم الشهادة</th>
                                <th class="p-3">المستفيد</th>
                                <th class="p-3">النوع</th>
                                <th class="p-3">الحالة</th>
                                <th class="p-3">تاريخ الإصدار</th>
                                <th class="p-3">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="certificate in certificates" :key="certificate.id" class="border-t border-white/10">
                                <td class="p-3 font-mono text-slate-200">{{ certificate.certificate_number }}</td>
                                <td class="p-3 text-white">
                                    <div class="font-bold">{{ certificate.recipient_name }}</div>
                                    <div class="text-xs text-slate-400">{{ certificate.recipient_label }}</div>
                                </td>
                                <td class="p-3 text-slate-300">{{ certificate.type_label }}</td>
                                <td class="p-3"><span class="rounded-full px-3 py-1 text-xs font-bold" :class="certificate.status === 'cancelled' ? 'bg-red-500/15 text-red-200' : 'bg-emerald-500/15 text-emerald-200'">{{ certificate.status_label }}</span></td>
                                <td class="p-3 text-slate-300">{{ certificate.issued_at || '-' }}</td>
                                <td class="p-3">
                                    <div class="flex flex-wrap gap-2">
                                        <a v-if="certificate.print_url" :href="certificate.print_url" target="_blank" class="ui-secondary-button inline-flex items-center gap-1"><Printer class="h-4 w-4" /> طباعة</a>
                                        <a v-if="certificate.download_url" :href="certificate.download_url" class="ui-secondary-button inline-flex items-center gap-1"><Download class="h-4 w-4" /> PDF</a>
                                        <a v-if="certificate.download_word_url" :href="certificate.download_word_url" class="ui-secondary-button inline-flex items-center gap-1"><FileText class="h-4 w-4" /> Word</a>
                                        <Link :href="certificate.verify_url" target="_blank" class="ui-secondary-button inline-flex items-center gap-1"><FileText class="h-4 w-4" /> تحقق</Link>
                                        <button v-if="permissions.can_cancel_certificates && certificate.status !== 'cancelled'" type="button" class="rounded-lg bg-red-500/15 px-3 py-2 text-sm font-bold text-red-200" @click="cancelCertificate(certificate)">إلغاء</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-if="certificates.length === 0" class="py-10 text-center text-slate-300">لم يتم إصدار شهادات بعد.</div>
                </div>
            </section>
        </div>
    </RoleLayout>
</template>
