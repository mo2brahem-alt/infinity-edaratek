<script setup>
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    BookOpenText,
    CalendarDays,
    CheckCircle2,
    Clock3,
    GraduationCap,
    Pencil,
    PlusCircle,
    Save,
    School,
    Trash2,
    X,
} from 'lucide-vue-next';
import SchoolDefaultScopeSettingsModal from '@/Components/Admin/SchoolDefaultScopeSettingsModal.vue';
import AppStatePanel from '@/Components/AppStatePanel.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useActionDialog } from '@/composables/useActionDialog';

const props = defineProps({
    embedded: { type: Boolean, default: false },
    editor: { type: Boolean, default: false },
    summary: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    countries: { type: Array, default: () => [] },
    educationStages: { type: Array, default: () => [] },
    educationTypes: { type: Array, default: () => [] },
    templateScopes: { type: Array, default: () => [] },
    scopeConfig: { type: Object, default: null },
    stageTemplates: { type: Array, default: () => [] },
    academicYearTemplates: { type: Array, default: () => [] },
    holidayTemplates: { type: Array, default: () => [] },
    leaveTypeTemplates: { type: Array, default: () => [] },
    subjectTemplates: { type: Array, default: () => [] },
});

const actionDialog = useActionDialog();
const layoutComponent = computed(() => (props.embedded ? 'div' : AdminLayout));
const filterForm = useForm({
    country_id: props.filters?.country_id ?? '',
    education_type_id: props.filters?.education_type_id ?? '',
});
const normalizeEducationTypes = (types = []) => [...types]
    .map((type) => ({
        id: Number(type.id),
        name: String(type.name || '').trim(),
    }))
    .filter((type) => type.id > 0 && type.name !== '')
    .sort((first, second) => first.name.localeCompare(second.name, 'ar'));
const normalizeEducationStages = (stages = []) => [...stages]
    .map((stage) => ({
        id: Number(stage.id),
        name: String(stage.name || '').trim(),
        sort_order: Number(stage.sort_order || 0),
        is_active: Boolean(stage.is_active),
    }))
    .filter((stage) => stage.id > 0 && stage.name !== '')
    .sort((first, second) => first.sort_order - second.sort_order || first.name.localeCompare(second.name, 'ar'));
const managedEducationTypes = ref(normalizeEducationTypes(props.educationTypes));
const managedEducationStages = ref(normalizeEducationStages(props.educationStages));
const isHydratingTemplateStageSelection = ref(false);
const hasScopeSelection = computed(() => Boolean(filterForm.country_id && filterForm.education_type_id));
const selectedCountry = computed(() =>
    props.countries.find((country) => Number(country.id) === Number(filterForm.country_id || 0)) || null);
const selectedEducationType = computed(() =>
    managedEducationTypes.value.find((type) => Number(type.id) === Number(filterForm.education_type_id || 0)) || null);
const currentTemplateScope = computed(() =>
    props.templateScopes.find((scope) =>
        Number(scope.country_id) === Number(filterForm.country_id || 0)
        && Number(scope.education_type_id) === Number(filterForm.education_type_id || 0),
    ) || null);
const embeddedEditorMode = computed(() => props.embedded && hasScopeSelection.value);
const editorSurfaceVisible = computed(() => hasScopeSelection.value && (props.embedded || scopeEditorModalOpen.value));
const createSurfaceVisible = computed(() => !embeddedEditorMode.value && (props.embedded || scopeCreateModalOpen.value));
const scopeSettingsOpen = ref(false);
const scopeSettingsLoading = ref(false);
const scopeCreateModalOpen = ref(false);
const scopeEditorModalOpen = ref(false);
const scopeEditorLoading = ref(false);
const deletingScopeKey = ref('');
const scopeDeleteForm = useForm({});
const metricCards = computed(() => [
    {
        key: 'schools_imported',
        label: 'مدارس تم تهيئتها',
        value: Number(props.summary?.schools_imported_count || 0),
        helper: 'مدارس لديها نسخها المدرسية المستقلة بالفعل',
        icon: CheckCircle2,
    },
    {
        key: 'schools_pending',
        label: 'مدارس لم تستورد بعد',
        value: Number(props.summary?.schools_pending_count || 0),
        helper: 'ستتأثر بالقوالب العامة الجديدة عند الاستيراد فقط',
        icon: School,
    },
    {
        key: 'active_templates',
        label: 'قوالب مفعلة',
        value: Object.values(props.summary?.active_template_counts || {}).reduce((sum, count) => sum + Number(count || 0), 0),
        helper: 'إجمالي العناصر العامة الجاهزة للنسخ',
        icon: PlusCircle,
    },
]);

const stageOptions = computed(() => props.stageTemplates.map((stage) => ({
    id: stage.id,
    name: stage.name,
    grades: stage.grades || [],
})));
const selectedScopeCountry = computed(() =>
    props.countries.find((country) => Number(country.id) === Number(scopeConfigForm.country_id || 0)) || null);
const selectedScopeEducationType = computed(() =>
    managedEducationTypes.value.find((type) => Number(type.id) === Number(scopeConfigForm.education_type_id || 0)) || null);
const educationTypeCreateForm = useForm({
    name: '',
});
const educationTypeEditId = ref(null);
const educationTypeEditForm = useForm({
    name: '',
});
const educationStageCreateForm = useForm({
    name: '',
    sort_order: 0,
    is_active: true,
});
const educationStageEditId = ref(null);
const educationStageEditForm = useForm({
    name: '',
    sort_order: 0,
    is_active: true,
});
const countryReference = ref(props.scopeConfig?.reference_snapshot || null);
const countryReferenceLoading = ref(false);
const countryReferenceError = ref('');
const countryReferenceRequestId = ref(0);
const countryReferenceCache = ref({});
const countryReferenceLabelMap = {
    islamic_holidays: 'الإجازات الإسلامية الافتراضية',
    public_holidays: 'العطلات الرسمية',
    academic_year_start: 'بداية العام الدراسي',
    school_breaks: 'الإجازات الدراسية',
    seasonal_breaks: 'الفترات أو الإجازات الموسمية',
    leave_types: 'أنواع الإجازات',
};
const countryReferenceSummary = computed(() =>
    countryReference.value && typeof countryReference.value === 'object'
        ? countryReference.value
        : null);
const countryReferenceHolidayPreview = computed(() => Array.isArray(countryReferenceSummary.value?.holidays)
    ? countryReferenceSummary.value.holidays.slice(0, 5)
    : []);
const countryReferenceHolidayCount = computed(() => {
    const holidays = countryReferenceSummary.value?.holidays || [];

    if (Array.isArray(holidays) && holidays.length > 0) {
        return holidays.length;
    }

    return Number(countryReferenceSummary.value?.available_counts?.public_holidays || 0)
        + Number(countryReferenceSummary.value?.available_counts?.islamic_holidays || 0);
});
const normalizeReferenceLabels = (items = []) => items
    .map((item) => countryReferenceLabelMap[item] || item)
    .filter(Boolean);
const countryReferenceState = computed(() => {
    if (countryReferenceLoading.value) return 'loading';
    if (countryReferenceError.value) return 'error';

    const summary = countryReferenceSummary.value;
    if (!summary) return 'idle';
    if (summary.status === 'unsupported') return 'unsupported';

    return (summary.supported_data || []).length > 0 && (summary.unavailable_data || []).length > 0
        ? 'partial_success'
        : 'success';
});

const decorateSetupStep = (status, payload = {}) => ({
    ...(status === 'done'
        ? {
            badge: 'مكتملة',
            cardClass: 'border-emerald-500/30 bg-emerald-500/10',
            badgeClass: 'bg-emerald-500/15 text-emerald-200',
            dotClass: 'bg-emerald-400',
        }
        : status === 'current'
            ? {
                badge: 'الحالية',
                cardClass: 'border-cyan-500/30 bg-cyan-500/10',
                badgeClass: 'bg-cyan-500/15 text-cyan-200',
                dotClass: 'bg-cyan-400',
            }
            : status === 'warning'
                ? {
                    badge: 'تحتاج مراجعة',
                    cardClass: 'border-amber-500/30 bg-amber-500/10',
                    badgeClass: 'bg-amber-500/15 text-amber-200',
                    dotClass: 'bg-amber-400',
                }
                : status === 'error'
                    ? {
                        badge: 'تعذر التنفيذ',
                        cardClass: 'border-red-500/30 bg-red-500/10',
                        badgeClass: 'bg-red-500/15 text-red-200',
                        dotClass: 'bg-red-400',
                    }
                    : {
                        badge: 'بانتظار الإدخال',
                        cardClass: 'border-slate-700 bg-slate-950/70',
                        badgeClass: 'bg-slate-800 text-slate-300',
                        dotClass: 'bg-slate-500',
                    }),
    ...payload,
});

const scopeSaveReady = computed(() => Boolean(
    scopeConfigForm.template_name
    && scopeConfigForm.country_id
    && scopeConfigForm.education_type_id,
));
const scopeSummaryItems = computed(() => [
    {
        key: 'template_name',
        label: 'اسم القالب',
        value: scopeConfigForm.template_name || currentTemplateScope.value?.template_name || 'لم يُحدد بعد',
        helper: 'اسم واضح يسهل العودة إلى القالب لاحقًا.',
    },
    {
        key: 'country',
        label: 'الدولة',
        value: selectedScopeCountry.value?.name || 'اختر الدولة',
        helper: 'تحدد الدولة المرجعيات التي سيجهزها النظام تلقائيًا داخل هذا القالب.',
    },
    {
        key: 'education_type',
        label: 'نوع التعليم',
        value: selectedScopeEducationType.value?.name || 'اختر نوع التعليم',
        helper: 'يرتبط القالب الآن بالدولة ونوع التعليم فقط دون أي نطاقات تعليمية.',
    },
]);
const scopeSetupSteps = computed(() => {
    const hasCountry = Boolean(scopeConfigForm.country_id);
    const hasEducationType = Boolean(scopeConfigForm.education_type_id);
    const hasTemplateName = Boolean(scopeConfigForm.template_name);

    let referenceStep = decorateSetupStep('pending', {
        key: 'reference',
        title: 'تجهيز البيانات المرجعية',
        description: 'بعد اختيار الدولة يجهز النظام المرجعيات المتاحة تلقائيًا لاستخدامها داخل القالب.',
    });

    if (countryReferenceState.value === 'loading') {
        referenceStep = decorateSetupStep('current', {
            key: 'reference',
            title: 'تجهيز البيانات المرجعية',
            description: 'يجري تجهيز المرجعيات المتاحة تلقائيًا دون أي خطوة تشغيلية إضافية.',
        });
    } else if (countryReferenceState.value === 'success') {
        referenceStep = decorateSetupStep('done', {
            key: 'reference',
            title: 'تجهيز البيانات المرجعية',
            description: countryReferenceSummary.value?.message || 'تم تجهيز المرجعيات المتاحة وربطها بمسودة القالب.',
        });
    } else if (countryReferenceState.value === 'partial_success' || countryReferenceState.value === 'unsupported') {
        referenceStep = decorateSetupStep('warning', {
            key: 'reference',
            title: 'تجهيز البيانات المرجعية',
            description: countryReferenceSummary.value?.message || 'تم حفظ ما هو متاح من المرجعيات، ويمكن استكمال بقية عناصر القالب يدويًا.',
        });
    } else if (countryReferenceState.value === 'error') {
        referenceStep = decorateSetupStep('error', {
            key: 'reference',
            title: 'تجهيز البيانات المرجعية',
            description: countryReferenceError.value || 'تعذر تجهيز بعض البيانات المرجعية تلقائيًا الآن، ويمكن متابعة إعداد القالب وحفظه.',
        });
    }

    return [
        decorateSetupStep(hasCountry ? 'done' : 'current', {
            key: 'country',
            title: 'اختيار الدولة',
            description: hasCountry
                ? `الدولة المختارة حاليًا: ${selectedScopeCountry.value?.name || '-'}`
                : 'ابدأ باختيار الدولة التي سيعتمد عليها القالب في المرجعيات الافتراضية.',
        }),
        decorateSetupStep(hasEducationType ? 'done' : hasCountry ? 'current' : 'pending', {
            key: 'education_type',
            title: 'اختيار نوع التعليم',
            description: hasEducationType
                ? `نوع التعليم المختار: ${selectedScopeEducationType.value?.name || '-'}`
                : 'اختر نوع تعليم من القائمة المركزية المضافة داخل صفحة القوالب نفسها.',
        }),
        decorateSetupStep(hasTemplateName ? 'done' : hasEducationType ? 'current' : 'pending', {
            key: 'template_name',
            title: 'تسمية القالب',
            description: hasTemplateName
                ? scopeConfigForm.template_name
                : 'أدخل اسمًا واضحًا يشرح الدولة ونوع التعليم ليسهل استخدامه لاحقًا.',
        }),
        referenceStep,
        decorateSetupStep(scopeSaveReady.value ? 'current' : 'pending', {
            key: 'save',
            title: 'حفظ القالب وربطه',
            description: scopeSaveReady.value
                ? 'أصبحت الحقول الأساسية جاهزة. عند الحفظ ستثبت المرجعيات والعام الدراسي الحالي داخل القالب.'
                : 'لن يظهر المحتوى التفصيلي للقالب إلا بعد استكمال الحقول الأساسية وحفظ الربط.',
        }),
    ];
});
const templateInventoryCards = computed(() => [
    {
        key: 'stages',
        label: 'المراحل',
        value: props.stageTemplates.length,
        helper: 'كل مرحلة يمكن أن تحتوي على فصول دراسية مستقلة بتاريخ بداية ونهاية خاصين بها.',
    },
    {
        key: 'stage_terms',
        label: 'فصول المراحل',
        value: props.stageTemplates.reduce((sum, stage) => sum + ((stage.stage_terms || []).length), 0),
        helper: 'تمثل الفصل الدراسي الأول والثاني أو أي فترات إضافية محفوظة على مستوى المرحلة نفسها.',
    },
    {
        key: 'grade_terms',
        label: 'ترمات الصفوف',
        value: props.stageTemplates.reduce((sum, stage) => sum + (stage.grades || []).reduce((gradeSum, grade) => gradeSum + ((grade.grade_terms || []).length), 0), 0),
        helper: 'تُدار لكل صف بصورة مستقلة وتُنسخ للمدرسة كما هي.',
    },
    {
        key: 'academic_years',
        label: 'الأعوام الدراسية',
        value: props.academicYearTemplates.length,
        helper: 'يضاف العام الحالي تلقائيًا إذا لم يكن موجودًا، ويُنشأ عند الاستيراد الترم الأول والثاني تلقائيًا من نفس العام.',
    },
    {
        key: 'holidays',
        label: 'العطل الرسمية',
        value: props.holidayTemplates.length,
        helper: 'منها ما جرى جلبه من API الدولة.',
    },
    {
        key: 'leave_types',
        label: 'أنواع الإجازات',
        value: props.leaveTypeTemplates.length,
        helper: 'تنسخ إلى المدرسة عند التهيئة فقط.',
    },
    {
        key: 'subjects',
        label: 'المواد',
        value: props.subjectTemplates.length,
        helper: 'تشمل الفروع أو المسارات إن وجدت.',
    },
]);
const activeScopeReferenceSummary = computed(() =>
    currentTemplateScope.value?.reference_snapshot || countryReferenceSummary.value || null);
const activeScopeReferenceSupportedLabels = computed(() =>
    normalizeReferenceLabels(activeScopeReferenceSummary.value?.supported_data || []));
const activeScopeReferenceUnavailableLabels = computed(() =>
    normalizeReferenceLabels(activeScopeReferenceSummary.value?.unavailable_data || []));
const scopeSettingsDataSections = computed(() => [
    {
        key: 'stages',
        title: 'المراحل التعليمية',
        description: 'المراحل وما يرتبط بها من فصول دراسية على مستوى المرحلة، ثم الصفوف والشعب الجاهزة للنسخ إلى المدرسة.',
        count: props.stageTemplates.length,
        items: props.stageTemplates.slice(0, 5).map((stage) => ({
            title: stage.name,
            meta: `${(stage.stage_terms || []).length} فصل مرحلة / ${(stage.grades || []).length} صف / ${(stage.classrooms || []).length} شعبة`,
        })),
        emptyTitle: 'لا توجد مراحل محفوظة بعد',
        emptyDescription: 'أضف مراحل القالب أولًا لتظهر هنا ضمن المعاينة السريعة.',
    },
    {
        key: 'academic_years',
        title: 'الأعوام الدراسية',
        description: 'الأعوام الدراسية الفعالة داخل القالب الحالي.',
        count: props.academicYearTemplates.length,
        items: props.academicYearTemplates.slice(0, 5).map((year) => ({
            title: year.name,
            meta: `${formatDate(year.starts_on)} - ${formatDate(year.ends_on)}`,
        })),
        emptyTitle: 'لا توجد أعوام دراسية محفوظة بعد',
        emptyDescription: 'يمكنك إضافة عام دراسي واحد أو أكثر لهذا القالب لاحقًا.',
    },
    {
        key: 'holidays',
        title: 'العطلات الرسمية',
        description: 'العطلات التي حُفظت داخل القالب، سواء أُضيفت يدويًا أو جُلِبت من API الدولة.',
        count: props.holidayTemplates.length,
        items: props.holidayTemplates.slice(0, 5).map((holiday) => ({
            title: holiday.name,
            meta: holidayDateSummary(holiday),
        })),
        emptyTitle: 'لا توجد عطلات محفوظة بعد',
        emptyDescription: 'ستظهر هنا العطلات الرسمية بعد حفظها داخل القالب.',
    },
    {
        key: 'leave_types',
        title: 'أنواع الإجازات',
        description: 'أنواع الإجازات التي تُنسخ لاحقًا إلى المدرسة بحسب الدولة ونوع التعليم فقط.',
        count: props.leaveTypeTemplates.length,
        items: props.leaveTypeTemplates.slice(0, 5).map((leaveType) => ({
            title: leaveType.name,
            meta: leaveType.code ? `الكود: ${leaveType.code}` : 'بدون كود مخصص',
        })),
        emptyTitle: 'لا توجد أنواع إجازات محفوظة بعد',
        emptyDescription: 'أضف أنواع الإجازات المطلوبة لتظهر هنا ضمن إعدادات القالب.',
    },
    {
        key: 'subjects',
        title: 'المواد التعليمية',
        description: 'المواد أو المسارات الجاهزة داخل القالب لهذه الدولة ونوع التعليم.',
        count: props.subjectTemplates.length,
        items: props.subjectTemplates.slice(0, 5).map((subject) => ({
            title: subject.name,
            meta: Array.isArray(subject.branches) && subject.branches.length
                ? subject.branches.join('، ')
                : 'الفرع الرئيسي فقط',
        })),
        emptyTitle: 'لا توجد مواد محفوظة بعد',
        emptyDescription: 'بعد إضافة المواد ستظهر هنا كجزء من ملخص القالب.',
    },
]);
const statusLabel = (value) => (value ? 'مفعّل' : 'غير مفعّل');
const stageTermSourceLabel = (value) => ({
    api: 'من API الدولة',
    manual: 'مكتمل يدويًا',
    default: 'افتراضي',
}[String(value || '').trim()] || 'افتراضي');
const stageTermSourceClass = (value) => ({
    api: 'border-cyan-500/30 bg-cyan-500/10 text-cyan-200',
    manual: 'border-amber-500/30 bg-amber-500/10 text-amber-200',
    default: 'border-slate-700 bg-slate-900 text-slate-300',
}[String(value || '').trim()] || 'border-slate-700 bg-slate-900 text-slate-300');
const formatDate = (value) => {
    if (!value) return '-';
    try {
        return new Intl.DateTimeFormat('ar-EG', { dateStyle: 'medium' }).format(new Date(value));
    } catch (_error) {
        return String(value);
    }
};
const holidayDateSummary = (holiday) => {
    if (holiday?.start_date || holiday?.end_date) {
        return `${formatDate(holiday?.start_date)} - ${formatDate(holiday?.end_date)}`;
    }

    if (holiday?.date) {
        return formatDate(holiday.date);
    }

    return holiday?.holiday_category === 'islamic'
        ? 'يحتاج التاريخ إلى اعتماد رسمي لاحقًا'
        : '-';
};
const holidayCategoryLabel = (holiday) => (
    holiday?.holiday_category === 'islamic'
        ? 'إجازة إسلامية'
        : 'عطلة عامة'
);
const normalizeBranchesInput = (value) => String(value || '')
    .split(/[\n،,]/)
    .map((item) => item.trim())
    .filter(Boolean);
const buildScopeQuery = (countryId = filterForm.country_id, educationTypeId = filterForm.education_type_id) => {
    const query = {};

    if (countryId) query.country_id = countryId;
    if (educationTypeId) query.education_type_id = educationTypeId;
    if (props.embedded) query.embedded = 1;
    if (props.editor) query.editor = 1;

    return query;
};
const scopedEndpoint = (endpoint) => {
    const queryString = new URLSearchParams(
        Object.entries(buildScopeQuery()).map(([key, value]) => [key, String(value)]),
    ).toString();

    if (queryString === '') return endpoint;

    return `${endpoint}${endpoint.includes('?') ? '&' : '?'}${queryString}`;
};
const scopeReferenceStatusLabel = (scope) => {
    const status = scope?.reference_snapshot?.status || '';

    if (status === 'success') return 'مرجعيات جاهزة';
    if (status === 'partial_success') return 'جلب جزئي';
    if (status === 'unsupported') return 'مرجعيات محدودة';
    if (status === 'error') return 'تحتاج إعادة جلب';

    return 'قالب محفوظ';
};
const endpointWithEmbedded = (endpoint) => {
    if (!props.embedded && !props.editor) return endpoint;

    const params = new URLSearchParams();
    if (props.embedded) params.set('embedded', '1');
    if (props.editor) params.set('editor', '1');

    return `${endpoint}${endpoint.includes('?') ? '&' : '?'}${params.toString()}`;
};
const syncScopedFields = (form, record = null) => {
    if ('country_id' in form) {
        form.country_id = record?.country_id ?? filterForm.country_id ?? scopeConfigForm.country_id ?? '';
    }

    if ('education_type_id' in form) {
        form.education_type_id = record?.education_type_id ?? filterForm.education_type_id ?? scopeConfigForm.education_type_id ?? '';
    }

    if ('directorate_id' in form) {
        form.directorate_id = '';
    }
};
const applyFilters = () => {
    router.get(route('admin.school_defaults.index'), buildScopeQuery(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const scopeConfigForm = useForm({
    template_name: props.scopeConfig?.template_name || '',
    country_id: props.filters?.country_id ?? '',
    education_type_id: props.filters?.education_type_id ?? '',
    reference_snapshot: props.scopeConfig?.reference_snapshot || null,
});
const resetScopeConfigForm = () => {
    const resolvedReferenceSnapshot = props.scopeConfig?.reference_snapshot || currentTemplateScope.value?.reference_snapshot || null;
    scopeConfigForm.template_name = props.scopeConfig?.template_name || currentTemplateScope.value?.template_name || '';
    scopeConfigForm.country_id = props.filters?.country_id ?? currentTemplateScope.value?.country_id ?? '';
    scopeConfigForm.education_type_id = props.filters?.education_type_id ?? currentTemplateScope.value?.education_type_id ?? '';
    scopeConfigForm.reference_snapshot = resolvedReferenceSnapshot;
    scopeConfigForm.clearErrors();
    countryReference.value = resolvedReferenceSnapshot;
    countryReferenceError.value = '';
};
const buildScopeConfigPayload = () => ({
    template_name: scopeConfigForm.template_name,
    country_id: scopeConfigForm.country_id || null,
    education_type_id: scopeConfigForm.education_type_id || null,
    reference_snapshot: scopeConfigForm.reference_snapshot || null,
    embedded: props.embedded ? 1 : 0,
});
const resetNewScopeDraft = () => {
    scopeSettingsOpen.value = false;
    scopeConfigForm.template_name = '';
    scopeConfigForm.country_id = '';
    scopeConfigForm.education_type_id = '';
    scopeConfigForm.reference_snapshot = null;
    scopeConfigForm.clearErrors();
    countryReference.value = null;
    countryReferenceError.value = '';
    countryReferenceLoading.value = false;
    educationTypeCreateForm.reset();
    educationTypeCreateForm.clearErrors();
    educationTypeEditId.value = null;
    educationTypeEditForm.reset();
    educationTypeEditForm.clearErrors();
};
const openNewScope = () => {
    resetNewScopeDraft();
    if (!props.embedded) {
        scopeCreateModalOpen.value = true;
    }
};
const upsertEducationType = (type) => {
    const normalizedType = {
        id: Number(type?.id || 0),
        name: String(type?.name || '').trim(),
    };

    if (!normalizedType.id || normalizedType.name === '') {
        return;
    }

    const nextTypes = managedEducationTypes.value.filter((item) => Number(item.id) !== normalizedType.id);
    nextTypes.push(normalizedType);
    managedEducationTypes.value = normalizeEducationTypes(nextTypes);
};
const removeEducationTypeLocally = (educationTypeId) => {
    const normalizedEducationTypeId = Number(educationTypeId || 0);

    managedEducationTypes.value = managedEducationTypes.value.filter(
        (type) => Number(type.id) !== normalizedEducationTypeId,
    );

    if (Number(filterForm.education_type_id || 0) === normalizedEducationTypeId) {
        filterForm.education_type_id = '';
    }

    if (Number(scopeConfigForm.education_type_id || 0) === normalizedEducationTypeId) {
        scopeConfigForm.education_type_id = '';
    }
};
const startEditingEducationType = (educationType) => {
    educationTypeEditId.value = Number(educationType.id);
    educationTypeEditForm.name = educationType.name || '';
    educationTypeEditForm.clearErrors();
};
const cancelEducationTypeEditing = () => {
    educationTypeEditId.value = null;
    educationTypeEditForm.reset();
    educationTypeEditForm.clearErrors();
};
const submitEducationType = async () => {
    educationTypeCreateForm.clearErrors();

    try {
        const response = await axios.post(route('admin.education_types.store'), {
            name: educationTypeCreateForm.name,
        }, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const createdType = response.data?.data || null;
        upsertEducationType(createdType);

        if (createdType?.id) {
            scopeConfigForm.education_type_id = createdType.id;
        }

        educationTypeCreateForm.reset();
        educationTypeCreateForm.clearErrors();
    } catch (requestError) {
        educationTypeCreateForm.setError(
            'name',
            requestError?.response?.data?.errors?.name?.[0] || 'تعذر إضافة نوع التعليم الآن.',
        );
    }
};
const updateEducationType = async () => {
    if (!educationTypeEditId.value) {
        return;
    }

    educationTypeEditForm.clearErrors();

    try {
        const response = await axios.put(route('admin.education_types.update', educationTypeEditId.value), {
            name: educationTypeEditForm.name,
        }, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        upsertEducationType(response.data?.data || {
            id: educationTypeEditId.value,
            name: educationTypeEditForm.name,
        });
        cancelEducationTypeEditing();
    } catch (requestError) {
        educationTypeEditForm.setError(
            'name',
            requestError?.response?.data?.errors?.name?.[0] || 'تعذر تحديث نوع التعليم الآن.',
        );
    }
};
const destroyEducationType = async (educationType) => {
    const confirmed = await actionDialog.confirm({
        title: 'حذف نوع التعليم',
        message: `سيتم حذف نوع التعليم «${educationType.name}» فقط إذا لم تكن هناك قوالب أو مديريات أو مدارس مرتبطة به.`,
        confirmText: 'نعم، احذف',
        cancelText: 'إلغاء',
        variant: 'danger',
    });

    if (!confirmed) {
        return;
    }

    try {
        await axios.delete(route('admin.education_types.delete', educationType.id), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        removeEducationTypeLocally(educationType.id);

        if (Number(educationTypeEditId.value || 0) === Number(educationType.id)) {
            cancelEducationTypeEditing();
        }
    } catch (requestError) {
        await actionDialog.alert?.({
            title: 'تعذر حذف نوع التعليم',
            message: requestError?.response?.data?.errors?.education_type?.[0]
                || requestError?.response?.data?.message
                || 'تعذر حذف نوع التعليم الآن.',
            confirmText: 'حسنًا',
            variant: 'danger',
        });
    }
};
const upsertEducationStage = (stage) => {
    const normalizedStage = normalizeEducationStages([stage])[0];
    if (!normalizedStage) {
        return;
    }

    const existingIndex = managedEducationStages.value.findIndex((item) => Number(item.id) === Number(normalizedStage.id));

    if (existingIndex >= 0) {
        managedEducationStages.value.splice(existingIndex, 1, normalizedStage);
    } else {
        managedEducationStages.value = normalizeEducationStages([...managedEducationStages.value, normalizedStage]);
    }
};
const removeEducationStageLocally = (educationStageId) => {
    const normalizedEducationStageId = Number(educationStageId || 0);

    managedEducationStages.value = managedEducationStages.value.filter(
        (stage) => Number(stage.id) !== normalizedEducationStageId,
    );

    if (Number(selectedEducationStageForTemplate.value || 0) === normalizedEducationStageId) {
        selectedEducationStageForTemplate.value = '';
    }
};
const startEditingEducationStage = (educationStage) => {
    educationStageEditId.value = Number(educationStage.id);
    educationStageEditForm.name = educationStage.name || '';
    educationStageEditForm.sort_order = Number(educationStage.sort_order || 0);
    educationStageEditForm.is_active = Boolean(educationStage.is_active);
    educationStageEditForm.clearErrors();
};
const cancelEducationStageEditing = () => {
    educationStageEditId.value = null;
    educationStageEditForm.reset();
    educationStageEditForm.sort_order = 0;
    educationStageEditForm.is_active = true;
    educationStageEditForm.clearErrors();
};
const submitEducationStage = async () => {
    educationStageCreateForm.clearErrors();

    try {
        const response = await axios.post(route('admin.education_stages.store'), {
            name: educationStageCreateForm.name,
            sort_order: educationStageCreateForm.sort_order,
            is_active: educationStageCreateForm.is_active,
        }, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const createdStage = response.data?.data || null;
        upsertEducationStage(createdStage);

        if (createdStage?.id) {
            selectedEducationStageForTemplate.value = createdStage.id;
            stageForm.name = createdStage.name || '';
            if (!stageEditId.value) {
                stageForm.sort_order = Number(createdStage.sort_order || 0);
            }
        }

        educationStageCreateForm.reset();
        educationStageCreateForm.sort_order = 0;
        educationStageCreateForm.is_active = true;
        educationStageCreateForm.clearErrors();
    } catch (requestError) {
        educationStageCreateForm.setError(
            'name',
            requestError?.response?.data?.errors?.name?.[0] || 'تعذر إضافة المرحلة التعليمية الآن.',
        );
    }
};
const updateEducationStage = async () => {
    if (!educationStageEditId.value) {
        return;
    }

    educationStageEditForm.clearErrors();

    try {
        const response = await axios.put(route('admin.education_stages.update', educationStageEditId.value), {
            name: educationStageEditForm.name,
            sort_order: educationStageEditForm.sort_order,
            is_active: educationStageEditForm.is_active,
        }, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        upsertEducationStage(response.data?.data || {
            id: educationStageEditId.value,
            name: educationStageEditForm.name,
            sort_order: educationStageEditForm.sort_order,
            is_active: educationStageEditForm.is_active,
        });
        cancelEducationStageEditing();
    } catch (requestError) {
        educationStageEditForm.setError(
            'name',
            requestError?.response?.data?.errors?.name?.[0] || requestError?.response?.data?.errors?.education_stage?.[0] || 'تعذر تحديث المرحلة التعليمية الآن.',
        );
    }
};
const destroyEducationStage = async (educationStage) => {
    const confirmed = await actionDialog.confirm({
        title: 'حذف المرحلة التعليمية',
        message: `سيتم حذف المرحلة التعليمية «${educationStage.name}» فقط إذا لم تكن هناك مدارس أو مراحل قوالب مرتبطة بها.`,
        confirmText: 'نعم، احذف',
        cancelText: 'إلغاء',
        variant: 'danger',
    });

    if (!confirmed) {
        return;
    }

    try {
        await axios.delete(route('admin.education_stages.delete', educationStage.id), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        removeEducationStageLocally(educationStage.id);

        if (Number(educationStageEditId.value || 0) === Number(educationStage.id)) {
            cancelEducationStageEditing();
        }
    } catch (requestError) {
        await actionDialog.alert?.({
            title: 'تعذر حذف المرحلة التعليمية',
            message: requestError?.response?.data?.errors?.education_stage?.[0]
                || requestError?.response?.data?.message
                || 'تعذر حذف المرحلة التعليمية الآن.',
            confirmText: 'حسنًا',
            variant: 'danger',
        });
    }
};
const syncTemplateStageFromMaster = () => {
    const selectedStage = managedEducationStages.value.find((stage) => Number(stage.id) === Number(selectedEducationStageForTemplate.value || 0)) || null;

    if (!selectedStage) {
        return;
    }

    stageForm.name = selectedStage.name;
    if (!stageEditId.value || Number(stageForm.sort_order || 0) === 0) {
        stageForm.sort_order = Number(selectedStage.sort_order || 0);
    }
};
const openScopeSettings = (scope) => {
    scopeCreateModalOpen.value = false;
    filterForm.country_id = scope.country_id;
    filterForm.education_type_id = scope.education_type_id;
    scopeConfigForm.template_name = scope.template_name || '';
    scopeConfigForm.country_id = scope.country_id || '';
    scopeConfigForm.education_type_id = scope.education_type_id || '';
    scopeConfigForm.reference_snapshot = scope.reference_snapshot || null;
    countryReference.value = scope.reference_snapshot || null;
    countryReferenceError.value = '';

    const isCurrentScope =
        Number(scope.education_type_id) === Number(props.filters?.education_type_id || 0)
        && Number(scope.country_id) === Number(props.filters?.country_id || 0);

    if (isCurrentScope) {
        scopeSettingsOpen.value = true;
        return;
    }

    scopeSettingsLoading.value = true;
    router.get(route('admin.school_defaults.index'), buildScopeQuery(scope.country_id, scope.education_type_id), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            scopeSettingsOpen.value = true;
        },
        onFinish: () => {
            scopeSettingsLoading.value = false;
        },
    });
};
const closeScopeSettings = () => {
    scopeSettingsOpen.value = false;
    scopeSettingsLoading.value = false;
};
const openScopeEditorFromSettings = (scope = currentTemplateScope.value) => {
    closeScopeSettings();
    openScopeEditorModal(scope);
};
const closeScopeCreateModal = () => {
    scopeCreateModalOpen.value = false;
};
const openScopeEditorModal = (scope) => {
    if (!scope?.country_id || !scope?.education_type_id) {
        return;
    }

    scopeCreateModalOpen.value = false;
    filterForm.country_id = scope.country_id;
    filterForm.education_type_id = scope.education_type_id;
    scopeConfigForm.template_name = scope.template_name || '';
    scopeConfigForm.country_id = scope.country_id || '';
    scopeConfigForm.education_type_id = scope.education_type_id || '';
    scopeConfigForm.reference_snapshot = scope.reference_snapshot || null;
    countryReference.value = scope.reference_snapshot || null;
    countryReferenceError.value = '';
    const isCurrentScope =
        Number(scope.education_type_id) === Number(props.filters?.education_type_id || 0)
        && Number(scope.country_id) === Number(props.filters?.country_id || 0);

    if (isCurrentScope) {
        scopeEditorModalOpen.value = true;
        return;
    }

    scopeEditorLoading.value = true;
    router.get(route('admin.school_defaults.index'), buildScopeQuery(scope.country_id, scope.education_type_id), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            resetScopeConfigForm();
            scopeEditorModalOpen.value = true;
        },
        onFinish: () => {
            scopeEditorLoading.value = false;
        },
    });
};
const closeScopeEditorModal = () => {
    scopeEditorModalOpen.value = false;
    scopeEditorLoading.value = false;
};
const destroyTemplateScope = async (scope) => {
    const confirmed = await actionDialog.confirm({
        title: 'حذف القالب',
        message: `سيتم حذف القالب «${scope.template_name}» وكل بياناته الافتراضية المرتبطة بالدولة ونوع التعليم. النسخ المدرسية التي سبق استيرادها لن تتأثر، لكن لن يعود هذا القالب متاحًا للاستيراد لاحقًا. هل تريد المتابعة؟`,
        confirmText: 'نعم، احذف القالب',
        cancelText: 'إلغاء',
        variant: 'danger',
    });

    if (!confirmed) {
        return;
    }

    deletingScopeKey.value = scope.key;

    scopeDeleteForm.delete(route('admin.school_defaults.scopes.destroy', {
        country: scope.country_id,
        educationType: scope.education_type_id,
    }), {
        preserveScroll: true,
        preserveState: false,
        onError: (errors) => {
            void actionDialog.alert({
                title: 'تعذر حذف القالب',
                message: errors.scope
                    || errors.country_id
                    || errors.education_type_id
                    || 'تعذر حذف القالب الآن. حاول مرة أخرى بعد قليل.',
                confirmText: 'حسنًا',
                variant: 'danger',
            });
        },
        onFinish: () => {
            deletingScopeKey.value = '';
        },
    });
};
const submitScopeConfig = () => {
    scopeConfigForm
        .transform(() => buildScopeConfigPayload())
        .post(route('admin.school_defaults.scopes.store'), {
            preserveScroll: true,
            onSuccess: () => {
                filterForm.country_id = scopeConfigForm.country_id;
                filterForm.education_type_id = scopeConfigForm.education_type_id;
                if (scopeCreateModalOpen.value) {
                    scopeCreateModalOpen.value = false;
                }
                applyFilters();
            },
        });
};

const loadCountryReference = async (countryId, { force = false } = {}) => {
    const normalizedCountryId = Number(countryId || 0);

    if (!normalizedCountryId) {
        countryReference.value = null;
        scopeConfigForm.reference_snapshot = null;
        countryReferenceError.value = '';
        countryReferenceLoading.value = false;
        return;
    }

    if (!force && countryReferenceCache.value[normalizedCountryId]) {
        countryReference.value = countryReferenceCache.value[normalizedCountryId];
        scopeConfigForm.reference_snapshot = countryReferenceCache.value[normalizedCountryId];
        countryReferenceError.value = countryReference.value?.status === 'error'
            ? countryReference.value?.message || ''
            : '';
        return;
    }

    const requestId = countryReferenceRequestId.value + 1;
    countryReferenceRequestId.value = requestId;
    countryReferenceLoading.value = true;
    countryReferenceError.value = '';

    try {
        const response = await axios.get(route('admin.school_defaults.country_reference'), {
            params: { country_id: normalizedCountryId },
        });

        if (countryReferenceRequestId.value !== requestId) {
            return;
        }

        countryReferenceCache.value = {
            ...countryReferenceCache.value,
            [normalizedCountryId]: response.data,
        };
        countryReference.value = response.data;
        scopeConfigForm.reference_snapshot = response.data;
        countryReferenceError.value = response.data?.status === 'error'
            ? response.data?.message || 'تعذر جلب مرجعيات الدولة الآن.'
            : '';
    } catch (_error) {
        if (countryReferenceRequestId.value !== requestId) {
            return;
        }

        countryReference.value = null;
        scopeConfigForm.reference_snapshot = null;
        countryReferenceError.value = 'تعذر جلب مرجعيات الدولة الآن. يمكنك إكمال القالب ثم المحاولة لاحقًا.';
    } finally {
        if (countryReferenceRequestId.value === requestId) {
            countryReferenceLoading.value = false;
        }
    }
};

const stageEditId = ref(null);
const stageGradeEditId = ref(null);
const stageTermEditId = ref(null);
const stageGradeTermEditId = ref(null);
const classroomEditId = ref(null);
const academicYearEditId = ref(null);
const holidayEditId = ref(null);
const leaveTypeEditId = ref(null);
const subjectEditId = ref(null);
const subjectBranchesText = ref('');
const selectedEducationStageForTemplate = ref('');

const stageForm = useForm({ country_id: props.filters?.country_id ?? '', education_type_id: props.filters?.education_type_id ?? '', directorate_id: '', education_stage_id: '', name: '', code: '', sort_order: 0, is_active: true, school_day_start_time: '', school_day_end_time: '' });
const stageGradeForm = useForm({ school_default_stage_template_id: '', name: '', sort_order: 0, is_active: true });
const stageTermForm = useForm({ school_default_stage_template_id: '', name: '', start_date: '', end_date: '', sort_order: 0, is_active: true });
const stageGradeTermForm = useForm({ school_default_stage_template_id: '', school_default_stage_grade_template_id: '', name: '', sort_order: 0, is_active: true });
const classroomForm = useForm({ school_default_stage_template_id: '', school_default_stage_grade_template_id: '', name: '', code: '', sort_order: 0, is_active: true });
const academicYearForm = useForm({ country_id: props.filters?.country_id ?? '', education_type_id: props.filters?.education_type_id ?? '', directorate_id: '', name: '', starts_on: '', ends_on: '', is_active: true });
const holidayForm = useForm({ country_id: props.filters?.country_id ?? '', education_type_id: props.filters?.education_type_id ?? '', directorate_id: '', name: '', start_date: '', end_date: '', return_date: '', notes: '', is_active: true });
const leaveTypeForm = useForm({ country_id: props.filters?.country_id ?? '', education_type_id: props.filters?.education_type_id ?? '', directorate_id: '', name: '', code: '', requires_attachment: false, is_active: true });
const subjectForm = useForm({ country_id: props.filters?.country_id ?? '', education_type_id: props.filters?.education_type_id ?? '', directorate_id: '', name: '', code: '', branches: [], is_active: true });

const classroomGradeOptions = computed(() => {
    const stageId = Number(classroomForm.school_default_stage_template_id || 0);
    return stageOptions.value.find((stage) => Number(stage.id) === stageId)?.grades || [];
});

const stageGradeTermGradeOptions = computed(() => {
    const stageId = Number(stageGradeTermForm.school_default_stage_template_id || 0);
    return stageOptions.value.find((stage) => Number(stage.id) === stageId)?.grades || [];
});
const activeManagedEducationStages = computed(() => managedEducationStages.value.filter((stage) => stage.is_active));

watch(() => classroomForm.school_default_stage_template_id, () => {
    const validGradeIds = classroomGradeOptions.value.map((grade) => Number(grade.id));
    if (!validGradeIds.includes(Number(classroomForm.school_default_stage_grade_template_id))) {
        classroomForm.school_default_stage_grade_template_id = classroomGradeOptions.value[0]?.id || '';
    }
});
watch(() => stageGradeTermForm.school_default_stage_template_id, () => {
    const validGradeIds = stageGradeTermGradeOptions.value.map((grade) => Number(grade.id));
    if (!validGradeIds.includes(Number(stageGradeTermForm.school_default_stage_grade_template_id))) {
        stageGradeTermForm.school_default_stage_grade_template_id = stageGradeTermGradeOptions.value[0]?.id || '';
    }
});
watch(() => props.educationTypes, (types) => {
    managedEducationTypes.value = normalizeEducationTypes(types);
}, { deep: true });
watch(() => props.educationStages, (stages) => {
    managedEducationStages.value = normalizeEducationStages(stages);
}, { deep: true });
watch(selectedEducationStageForTemplate, () => {
    stageForm.education_stage_id = selectedEducationStageForTemplate.value || '';
    if (!isHydratingTemplateStageSelection.value) {
        syncTemplateStageFromMaster();
    }
});
watch(
    () => [filterForm.country_id, filterForm.education_type_id],
    () => {
        if (!scopeConfigForm.processing) {
            resetScopeConfigForm();
        }
        if (!stageEditId.value) syncScopedFields(stageForm);
        if (!academicYearEditId.value) syncScopedFields(academicYearForm);
        if (!holidayEditId.value) syncScopedFields(holidayForm);
        if (!leaveTypeEditId.value) syncScopedFields(leaveTypeForm);
        if (!subjectEditId.value) syncScopedFields(subjectForm);
    },
);
watch(() => scopeConfigForm.country_id, (countryId) => {
    if (countryReferenceSummary.value?.country?.id && Number(countryReferenceSummary.value.country.id) !== Number(countryId || 0)) {
        countryReference.value = null;
        scopeConfigForm.reference_snapshot = null;
    }

    void loadCountryReference(countryId);
});

const resetStageForm = () => {
    stageEditId.value = null;
    selectedEducationStageForTemplate.value = '';
    stageForm.reset();
    syncScopedFields(stageForm);
    stageForm.education_stage_id = '';
    stageForm.sort_order = 0;
    stageForm.is_active = true;
    stageForm.clearErrors();
};
const resetStageGradeForm = (stageId = '') => { stageGradeEditId.value = null; stageGradeForm.reset(); stageGradeForm.school_default_stage_template_id = stageId || stageOptions.value[0]?.id || ''; stageGradeForm.sort_order = 0; stageGradeForm.is_active = true; stageGradeForm.clearErrors(); };
const resetStageTermForm = (stageId = '') => { stageTermEditId.value = null; stageTermForm.reset(); stageTermForm.school_default_stage_template_id = stageId || stageOptions.value[0]?.id || ''; stageTermForm.sort_order = 0; stageTermForm.is_active = true; stageTermForm.clearErrors(); };
const resetStageGradeTermForm = (stageId = '', gradeId = '') => { stageGradeTermEditId.value = null; stageGradeTermForm.reset(); stageGradeTermForm.school_default_stage_template_id = stageId || stageOptions.value[0]?.id || ''; stageGradeTermForm.school_default_stage_grade_template_id = gradeId || stageGradeTermGradeOptions.value[0]?.id || ''; stageGradeTermForm.sort_order = 0; stageGradeTermForm.is_active = true; stageGradeTermForm.clearErrors(); };
const resetClassroomForm = (stageId = '', gradeId = '') => { classroomEditId.value = null; classroomForm.reset(); classroomForm.school_default_stage_template_id = stageId || stageOptions.value[0]?.id || ''; classroomForm.school_default_stage_grade_template_id = gradeId || classroomGradeOptions.value[0]?.id || ''; classroomForm.sort_order = 0; classroomForm.is_active = true; classroomForm.clearErrors(); };
const resetAcademicYearForm = () => { academicYearEditId.value = null; academicYearForm.reset(); syncScopedFields(academicYearForm); academicYearForm.is_active = true; academicYearForm.clearErrors(); };
const resetHolidayForm = () => { holidayEditId.value = null; holidayForm.reset(); syncScopedFields(holidayForm); holidayForm.is_active = true; holidayForm.clearErrors(); };
const resetLeaveTypeForm = () => { leaveTypeEditId.value = null; leaveTypeForm.reset(); syncScopedFields(leaveTypeForm); leaveTypeForm.requires_attachment = false; leaveTypeForm.is_active = true; leaveTypeForm.clearErrors(); };
const resetSubjectForm = () => { subjectEditId.value = null; subjectForm.reset(); syncScopedFields(subjectForm); subjectForm.is_active = true; subjectForm.branches = []; subjectBranchesText.value = ''; subjectForm.clearErrors(); };

const submitStage = () => {
    syncScopedFields(stageForm);
    stageForm.education_stage_id = selectedEducationStageForTemplate.value || '';
    syncTemplateStageFromMaster();
    return stageEditId.value
        ? stageForm.put(endpointWithEmbedded(route('admin.school_defaults.stages.update', stageEditId.value)), { preserveScroll: true })
        : stageForm.post(endpointWithEmbedded(route('admin.school_defaults.stages.store')), { preserveScroll: true });
};
const submitStageGrade = () => stageGradeEditId.value ? stageGradeForm.put(endpointWithEmbedded(route('admin.school_defaults.stage_grades.update', stageGradeEditId.value)), { preserveScroll: true }) : stageGradeForm.post(endpointWithEmbedded(route('admin.school_defaults.stage_grades.store')), { preserveScroll: true });
const submitStageTerm = () => stageTermEditId.value ? stageTermForm.put(endpointWithEmbedded(route('admin.school_defaults.stage_terms.update', stageTermEditId.value)), { preserveScroll: true }) : stageTermForm.post(endpointWithEmbedded(route('admin.school_defaults.stage_terms.store')), { preserveScroll: true });
const submitStageGradeTerm = () => stageGradeTermEditId.value ? stageGradeTermForm.put(endpointWithEmbedded(route('admin.school_defaults.stage_grade_terms.update', stageGradeTermEditId.value)), { preserveScroll: true }) : stageGradeTermForm.post(endpointWithEmbedded(route('admin.school_defaults.stage_grade_terms.store')), { preserveScroll: true });
const submitClassroom = () => classroomEditId.value ? classroomForm.put(endpointWithEmbedded(route('admin.school_defaults.classrooms.update', classroomEditId.value)), { preserveScroll: true }) : classroomForm.post(endpointWithEmbedded(route('admin.school_defaults.classrooms.store')), { preserveScroll: true });
const submitAcademicYear = () => { syncScopedFields(academicYearForm); return academicYearEditId.value ? academicYearForm.put(endpointWithEmbedded(route('admin.school_defaults.academic_years.update', academicYearEditId.value)), { preserveScroll: true }) : academicYearForm.post(endpointWithEmbedded(route('admin.school_defaults.academic_years.store')), { preserveScroll: true }); };
const submitHoliday = () => { syncScopedFields(holidayForm); return holidayEditId.value ? holidayForm.put(endpointWithEmbedded(route('admin.school_defaults.holidays.update', holidayEditId.value)), { preserveScroll: true }) : holidayForm.post(endpointWithEmbedded(route('admin.school_defaults.holidays.store')), { preserveScroll: true }); };
const submitLeaveType = () => { syncScopedFields(leaveTypeForm); return leaveTypeEditId.value ? leaveTypeForm.put(endpointWithEmbedded(route('admin.school_defaults.leave_types.update', leaveTypeEditId.value)), { preserveScroll: true }) : leaveTypeForm.post(endpointWithEmbedded(route('admin.school_defaults.leave_types.store')), { preserveScroll: true }); };
const submitSubject = () => { syncScopedFields(subjectForm); subjectForm.branches = normalizeBranchesInput(subjectBranchesText.value); return subjectEditId.value ? subjectForm.put(endpointWithEmbedded(route('admin.school_defaults.subjects.update', subjectEditId.value)), { preserveScroll: true }) : subjectForm.post(endpointWithEmbedded(route('admin.school_defaults.subjects.store')), { preserveScroll: true }); };

const destroyWithConfirmation = async (title, message, endpoint) => {
    const confirmed = await actionDialog.confirm({ title, message, confirmText: 'نعم، احذف', cancelText: 'إلغاء', variant: 'danger' });
    if (!confirmed) return;
    const deleteForm = useForm({});
    deleteForm.delete(scopedEndpoint(endpoint), { preserveScroll: true });
};

const editStage = (stage) => {
    stageEditId.value = stage.id;
    const matchedEducationStage = managedEducationStages.value.find((educationStage) => Number(educationStage.id) === Number(stage.education_stage_id || 0))
        || managedEducationStages.value.find((educationStage) => educationStage.name === stage.name)
        || null;
    isHydratingTemplateStageSelection.value = true;
    selectedEducationStageForTemplate.value = matchedEducationStage?.id || '';
    syncScopedFields(stageForm, stage);
    stageForm.education_stage_id = matchedEducationStage?.id || '';
    stageForm.name = stage.name || '';
    stageForm.code = stage.code || '';
    stageForm.sort_order = Number(stage.sort_order || 0);
    stageForm.is_active = Boolean(stage.is_active);
    stageForm.school_day_start_time = String(stage.school_day_start_time || '').slice(0, 5);
    stageForm.school_day_end_time = String(stage.school_day_end_time || '').slice(0, 5);
    isHydratingTemplateStageSelection.value = false;
};
const editStageGrade = (stage, grade) => { stageGradeEditId.value = grade.id; stageGradeForm.school_default_stage_template_id = stage.id; stageGradeForm.name = grade.name || ''; stageGradeForm.sort_order = Number(grade.sort_order || 0); stageGradeForm.is_active = Boolean(grade.is_active); };
const editStageTerm = (stage, term) => { stageTermEditId.value = term.id; stageTermForm.school_default_stage_template_id = stage.id; stageTermForm.name = term.name || ''; stageTermForm.start_date = term.start_date || ''; stageTermForm.end_date = term.end_date || ''; stageTermForm.sort_order = Number(term.sort_order || 0); stageTermForm.is_active = Boolean(term.is_active); };
const editStageGradeTerm = (stage, grade, term) => { stageGradeTermEditId.value = term.id; stageGradeTermForm.school_default_stage_template_id = stage.id; stageGradeTermForm.school_default_stage_grade_template_id = grade.id; stageGradeTermForm.name = term.name || ''; stageGradeTermForm.sort_order = Number(term.sort_order || 0); stageGradeTermForm.is_active = Boolean(term.is_active); };
const editClassroom = (classroom) => { classroomEditId.value = classroom.id; classroomForm.school_default_stage_template_id = classroom.school_default_stage_template_id; classroomForm.school_default_stage_grade_template_id = classroom.school_default_stage_grade_template_id; classroomForm.name = classroom.name || ''; classroomForm.code = classroom.code || ''; classroomForm.sort_order = Number(classroom.sort_order || 0); classroomForm.is_active = Boolean(classroom.is_active); };
const editAcademicYear = (year) => { academicYearEditId.value = year.id; syncScopedFields(academicYearForm, year); academicYearForm.name = year.name || ''; academicYearForm.starts_on = year.starts_on || ''; academicYearForm.ends_on = year.ends_on || ''; academicYearForm.is_active = Boolean(year.is_active); };
const editHoliday = (holiday) => { holidayEditId.value = holiday.id; syncScopedFields(holidayForm, holiday); holidayForm.name = holiday.name || ''; holidayForm.start_date = holiday.start_date || ''; holidayForm.end_date = holiday.end_date || ''; holidayForm.return_date = holiday.return_date || ''; holidayForm.notes = holiday.notes || ''; holidayForm.is_active = Boolean(holiday.is_active); };
const editLeaveType = (leaveType) => { leaveTypeEditId.value = leaveType.id; syncScopedFields(leaveTypeForm, leaveType); leaveTypeForm.name = leaveType.name || ''; leaveTypeForm.code = leaveType.code || ''; leaveTypeForm.requires_attachment = Boolean(leaveType.requires_attachment); leaveTypeForm.is_active = Boolean(leaveType.is_active); };
const editSubject = (subject) => { subjectEditId.value = subject.id; syncScopedFields(subjectForm, subject); subjectForm.name = subject.name || ''; subjectForm.code = subject.code || ''; subjectForm.is_active = Boolean(subject.is_active); subjectBranchesText.value = Array.isArray(subject.branches) ? subject.branches.join('، ') : ''; };

resetStageForm();
resetStageTermForm();
resetStageGradeTermForm();
resetAcademicYearForm();
resetHolidayForm();
resetLeaveTypeForm();
resetSubjectForm();
resetScopeConfigForm();
if (scopeConfigForm.country_id) {
    void loadCountryReference(scopeConfigForm.country_id);
}
</script>

<template>
    <Head title="البيانات الافتراضية المدرسية" />

    <component :is="layoutComponent">
        <div class="school-defaults-page space-y-6" dir="rtl">
            <section class="school-defaults-hero rounded-3xl border border-slate-700/70 bg-gradient-to-l from-slate-950 to-slate-900 p-6 text-right">
                <p class="inline-flex items-center gap-2 text-xs font-semibold text-cyan-300">
                    <School class="h-4 w-4" />
                    <span>{{ embeddedEditorMode ? 'تحرير قالب محفوظ' : embedded ? 'إضافة قالب جديد' : 'قوالب عامة مرتبطة بالدولة ونوع التعليم' }}</span>
                </p>
                <h1 class="mt-3 text-2xl font-black text-white">{{ embeddedEditorMode ? 'تحرير القالب الافتراضي المدرسي' : embedded ? 'إضافة قالب جديد وربطه بنوع التعليم' : 'القوالب الافتراضية المدرسية بحسب الدولة ونوع التعليم' }}</h1>
                <p class="mt-3 max-w-4xl text-sm leading-7 text-slate-300">
                    {{ embeddedEditorMode
                        ? 'أنت الآن داخل نافذة تحرير مستقلة. عدّل القالب واحفظه من هنا دون إغراق الصفحة الرئيسية بالتفاصيل، مع بقاء النسخ المدرسية لاحقًا مستقلة بالكامل داخل نطاقها المدرسي.'
                        : 'يدير السوبر أدمن هنا القوالب العامة لكل زوج من الدولة ونوع التعليم. بعد تسمية القالب وربطه بنوع تعليم واحد، تُنسخ بياناته إلى المدرسة مرة واحدة فقط عند التهيئة لتصبح بعد ذلك مستقلة بالكامل داخل نطاقها المدرسي.' }}
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="rounded-full border border-cyan-500/30 bg-cyan-500/10 px-3 py-1 text-xs font-bold text-cyan-200">{{ embeddedEditorMode ? 'نافذة مخصصة للتحرير والحفظ' : 'اختيار الدولة ثم نوع التعليم ثم اسم القالب' }}</span>
                    <span class="rounded-full border border-slate-700 bg-slate-950/70 px-3 py-1 text-xs font-bold text-slate-300">{{ embeddedEditorMode ? 'إعادة الجلب والحفظ تتم من نفس نافذة التحرير' : 'مرجعيات الدولة تُحفظ داخل القالب كصورة ثابتة' }}</span>
                    <span class="rounded-full border border-slate-700 bg-slate-950/70 px-3 py-1 text-xs font-bold text-slate-300">يُنسخ القالب إلى المدرسة دون ربط حي لاحقًا</span>
                </div>
            </section>

            <section v-if="!embedded" class="grid gap-4 md:grid-cols-3">
                <article v-for="card in metricCards" :key="card.key" class="rounded-2xl border border-slate-700/70 bg-slate-900/90 p-5 text-right">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm text-slate-400">{{ card.label }}</p>
                            <p class="mt-2 text-3xl font-black text-white">{{ card.value }}</p>
                            <p class="mt-2 text-xs leading-6 text-slate-500">{{ card.helper }}</p>
                        </div>
                        <div class="rounded-2xl bg-cyan-500/10 p-3 text-cyan-300"><component :is="card.icon" class="h-5 w-5" /></div>
                    </div>
                </article>
            </section>

            <section v-if="!embeddedEditorMode && !embedded" class="rounded-2xl border border-slate-700/70 bg-slate-900/90 p-5 text-right">
                <div class="flex flex-col gap-3 border-b border-slate-800 pb-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-xs font-semibold text-cyan-300">أنواع التعليم المركزية</p>
                        <h2 class="mt-2 text-lg font-black text-white">إدارة أنواع التعليم من نفس صفحة القوالب</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-400">تُدار أنواع التعليم هنا بشكل مركزي ثم تُستخدم لاحقًا في إنشاء القالب وتحريره وربطه بالدولة. لا يتم إدخال نوع التعليم كنص حر داخل كل قالب بعد الآن.</p>
                    </div>
                    <span class="rounded-full bg-slate-950 px-3 py-1 text-[11px] font-bold text-slate-300">{{ managedEducationTypes.length }} نوع</span>
                </div>

                <div class="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1.45fr)_minmax(0,1fr)]">
                    <div class="space-y-3">
                        <div v-if="managedEducationTypes.length" class="grid gap-3 md:grid-cols-2">
                            <article
                                v-for="educationType in managedEducationTypes"
                                :key="`managed-education-type-${educationType.id}`"
                                class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-bold text-white">{{ educationType.name }}</p>
                                            <span
                                                v-if="Number(educationTypeEditId || 0) === Number(educationType.id)"
                                                class="rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-[11px] font-bold text-amber-200"
                                            >
                                                قيد التحرير
                                            </span>
                                        </div>
                                        <p class="mt-2 text-xs leading-6 text-slate-500">يُستخدم هذا النوع في مطابقة القوالب الافتراضية مع المدارس بحسب الدولة ونوع التعليم فقط.</p>
                                    </div>
                                    <div class="flex gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-lg border border-slate-600 px-3 py-2 text-xs font-bold text-slate-200 transition hover:bg-slate-800"
                                            @click="startEditingEducationType(educationType)"
                                        >
                                            تعديل
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-lg bg-red-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-red-500"
                                            @click="destroyEducationType(educationType)"
                                        >
                                            حذف
                                        </button>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <AppStatePanel
                            v-else
                            compact
                            title="لا توجد أنواع تعليم مضافة بعد"
                            description="ابدأ بإضافة أول نوع تعليم من النموذج المجاور، ثم سيظهر مباشرة داخل نوافذ إنشاء القالب وتحريره."
                        />
                    </div>

                    <aside class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                        <p class="text-xs font-semibold text-cyan-300">{{ educationTypeEditId ? 'تعديل نوع تعليم' : 'إضافة نوع تعليم جديد' }}</p>
                        <h3 class="mt-2 text-base font-black text-white">{{ educationTypeEditId ? 'حدّث الاسم المركزي لنوع التعليم' : 'أدخل نوع تعليم جديد لاستخدامه في القوالب' }}</h3>
                        <p class="mt-2 text-xs leading-6 text-slate-500">سيصبح هذا النوع متاحًا فور حفظه داخل جميع نماذج القوالب في هذه الصفحة، ويُستخدم لاحقًا أيضًا في تهيئة المدرسة ومطابقة القالب الافتراضي المناسب.</p>

                        <div class="mt-4 space-y-3">
                            <label class="block text-sm font-medium text-slate-300" for="central-education-type-name">اسم نوع التعليم</label>
                            <input
                                v-if="educationTypeEditId"
                                id="central-education-type-name"
                                v-model="educationTypeEditForm.name"
                                type="text"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white"
                                placeholder="مثال: وطني أو أهلي أو دولي"
                            />
                            <input
                                v-else
                                id="central-education-type-name"
                                v-model="educationTypeCreateForm.name"
                                type="text"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white"
                                placeholder="مثال: وطني أو أهلي أو دولي"
                            />
                            <p v-if="educationTypeEditId ? educationTypeEditForm.errors.name : educationTypeCreateForm.errors.name" class="text-xs text-red-400">
                                {{ educationTypeEditId ? educationTypeEditForm.errors.name : educationTypeCreateForm.errors.name }}
                            </p>
                        </div>

                        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-end">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-xl border border-slate-600 px-4 py-2 text-sm font-bold text-slate-200 transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="educationTypeEditId ? educationTypeEditForm.processing || !educationTypeEditForm.name.trim() : educationTypeCreateForm.processing || !educationTypeCreateForm.name.trim()"
                                @click="educationTypeEditId ? updateEducationType() : submitEducationType()"
                            >
                                {{ educationTypeEditId ? 'حفظ التعديل' : 'إضافة النوع' }}
                            </button>
                            <button
                                v-if="educationTypeEditId"
                                type="button"
                                class="inline-flex items-center justify-center rounded-xl border border-slate-600 px-4 py-2 text-sm font-bold text-slate-200 transition hover:bg-slate-800"
                                @click="cancelEducationTypeEditing"
                            >
                                إلغاء
                            </button>
                        </div>
                    </aside>
                </div>
            </section>

            <section v-if="!embeddedEditorMode && !embedded" class="rounded-2xl border border-slate-700/70 bg-slate-900/90 p-5 text-right">
                <div class="flex flex-col gap-3 border-b border-slate-800 pb-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-xs font-semibold text-cyan-300">المراحل التعليمية المركزية</p>
                        <h2 class="mt-2 text-lg font-black text-white">إدارة المراحل المتاحة داخل المدارس من نفس صفحة القوالب</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-400">تُدار هذه المراحل بشكل مركزي بواسطة السوبر أدمن فقط، ثم تظهر لاحقًا كخيارات داخل تهيئة المدرسة، كما يمكن ربط مرحلة القالب بإحدى هذه المراحل لتبقى التسمية موحدة وواضحة.</p>
                    </div>
                    <span class="rounded-full bg-slate-950 px-3 py-1 text-[11px] font-bold text-slate-300">{{ managedEducationStages.length }} مرحلة</span>
                </div>

                <div class="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1.45fr)_minmax(0,1fr)]">
                    <div class="space-y-3">
                        <div v-if="managedEducationStages.length" class="grid gap-3 md:grid-cols-2">
                            <article
                                v-for="educationStage in managedEducationStages"
                                :key="`managed-education-stage-${educationStage.id}`"
                                class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-bold text-white">{{ educationStage.name }}</p>
                                            <span
                                                class="rounded-full border px-2.5 py-1 text-[11px] font-bold"
                                                :class="educationStage.is_active
                                                    ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200'
                                                    : 'border-slate-700 bg-slate-900 text-slate-300'"
                                            >
                                                {{ educationStage.is_active ? 'مفعلة' : 'غير مفعلة' }}
                                            </span>
                                            <span
                                                v-if="Number(educationStageEditId || 0) === Number(educationStage.id)"
                                                class="rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-[11px] font-bold text-amber-200"
                                            >
                                                قيد التحرير
                                            </span>
                                        </div>
                                        <p class="mt-2 text-xs leading-6 text-slate-500">الترتيب: {{ educationStage.sort_order }}. تستخدم هذه المرحلة لاحقًا في اختيار مراحل المدرسة وفي توحيد تسمية مرحلة القالب عند الربط بها.</p>
                                    </div>
                                    <div class="flex gap-2">
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-lg border border-slate-600 px-3 py-2 text-xs font-bold text-slate-200 transition hover:bg-slate-800"
                                            @click="startEditingEducationStage(educationStage)"
                                        >
                                            تعديل
                                        </button>
                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-lg bg-red-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-red-500"
                                            @click="destroyEducationStage(educationStage)"
                                        >
                                            حذف
                                        </button>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <AppStatePanel
                            v-else
                            compact
                            title="لا توجد مراحل تعليمية مضافة بعد"
                            description="ابدأ بإضافة المراحل المركزية مثل روضة وابتدائي ومتوسط وثانوي، ثم ستظهر فورًا داخل تهيئة المدرسة وربط مراحل القوالب."
                        />
                    </div>

                    <aside class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                        <p class="text-xs font-semibold text-cyan-300">{{ educationStageEditId ? 'تعديل مرحلة تعليمية' : 'إضافة مرحلة تعليمية جديدة' }}</p>
                        <h3 class="mt-2 text-base font-black text-white">{{ educationStageEditId ? 'حدّث المرحلة المركزية' : 'أدخل مرحلة تعليمية جديدة لاستخدامها داخل المدارس والقوالب' }}</h3>
                        <p class="mt-2 text-xs leading-6 text-slate-500">هذه القائمة عامة على مستوى المنصة، لكن اختيار المراحل داخل المدرسة نفسها يبقى محفوظًا لاحقًا داخل المدرسة فقط.</p>

                        <div class="mt-4 grid gap-3">
                            <div class="space-y-1.5">
                                <label class="block text-sm font-medium text-slate-300" for="central-education-stage-name">اسم المرحلة التعليمية</label>
                                <input
                                    v-if="educationStageEditId"
                                    id="central-education-stage-name"
                                    v-model="educationStageEditForm.name"
                                    type="text"
                                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white"
                                    placeholder="مثال: روضة أو ابتدائي أو متوسط أو ثانوي"
                                />
                                <input
                                    v-else
                                    id="central-education-stage-name"
                                    v-model="educationStageCreateForm.name"
                                    type="text"
                                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white"
                                    placeholder="مثال: روضة أو ابتدائي أو متوسط أو ثانوي"
                                />
                            </div>

                            <div class="space-y-1.5">
                                <label class="block text-sm font-medium text-slate-300" for="central-education-stage-order">الترتيب</label>
                                <input
                                    v-if="educationStageEditId"
                                    id="central-education-stage-order"
                                    v-model.number="educationStageEditForm.sort_order"
                                    type="number"
                                    min="0"
                                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white"
                                    placeholder="0"
                                />
                                <input
                                    v-else
                                    id="central-education-stage-order"
                                    v-model.number="educationStageCreateForm.sort_order"
                                    type="number"
                                    min="0"
                                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white"
                                    placeholder="0"
                                />
                            </div>

                            <label v-if="educationStageEditId" class="inline-flex items-center gap-2 text-sm text-slate-300">
                                <input
                                    v-model="educationStageEditForm.is_active"
                                    type="checkbox"
                                    class="rounded border-slate-600 bg-slate-950"
                                />
                                مفعلة
                            </label>
                            <label v-else class="inline-flex items-center gap-2 text-sm text-slate-300">
                                <input
                                    v-model="educationStageCreateForm.is_active"
                                    type="checkbox"
                                    class="rounded border-slate-600 bg-slate-950"
                                />
                                مفعلة
                            </label>

                            <p v-if="educationStageEditId ? educationStageEditForm.errors.name : educationStageCreateForm.errors.name" class="text-xs text-red-400">
                                {{ educationStageEditId ? educationStageEditForm.errors.name : educationStageCreateForm.errors.name }}
                            </p>
                        </div>

                        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-end">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-xl border border-slate-600 px-4 py-2 text-sm font-bold text-slate-200 transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="educationStageEditId
                                    ? educationStageEditForm.processing || !educationStageEditForm.name.trim()
                                    : educationStageCreateForm.processing || !educationStageCreateForm.name.trim()"
                                @click="educationStageEditId ? updateEducationStage() : submitEducationStage()"
                            >
                                {{ educationStageEditId ? 'حفظ التعديل' : 'إضافة المرحلة' }}
                            </button>
                            <button
                                v-if="educationStageEditId"
                                type="button"
                                class="inline-flex items-center justify-center rounded-xl border border-slate-600 px-4 py-2 text-sm font-bold text-slate-200 transition hover:bg-slate-800"
                                @click="cancelEducationStageEditing"
                            >
                                إلغاء
                            </button>
                        </div>
                    </aside>
                </div>
            </section>

            <section v-if="!embeddedEditorMode && !embedded" class="rounded-2xl border border-slate-700/70 bg-slate-900/90 p-5 text-right">
                <div class="flex flex-col gap-3 border-b border-slate-800 pb-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-xs font-semibold text-cyan-300">القوالب المحفوظة</p>
                        <h2 class="mt-2 text-lg font-black text-white">قوالب عامة مرتبطة بالدولة ونوع التعليم</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-400">أصبحت الصفحة الرئيسية مركزة على استعراض القوالب المحفوظة فقط. إنشاء القالب الجديد يتم الآن من نافذة مستقلة، بينما تظل الإعدادات والتحرير داخل نوافذ مخصصة عند الحاجة.</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex w-full items-center justify-center rounded-xl bg-cyan-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-cyan-500 sm:w-auto"
                        @click="openNewScope"
                    >
                        إضافة قالب جديد
                    </button>
                </div>

                <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-bold text-white">القوالب المحفوظة</p>
                        <p class="mt-1 text-xs leading-6 text-slate-500">يظهر كل قالب هنا بشكل مختصر، بينما تُفتح الإعدادات والتحرير داخل نوافذ مستقلة حتى تبقى الصفحة أوضح وأسهل في القراءة.</p>
                    </div>
                    <span class="rounded-full bg-slate-950 px-3 py-1 text-[11px] font-bold text-slate-300">{{ templateScopes.length }} قالب</span>
                </div>

                <div v-if="templateScopes.length" class="mt-4 grid gap-3 lg:grid-cols-2">
                    <article
                        v-for="scope in templateScopes"
                        :key="scope.key"
                        class="rounded-2xl border px-4 py-4 text-right transition"
                        :class="Number(scope.country_id) === Number(filterForm.country_id)
                            && Number(scope.education_type_id) === Number(filterForm.education_type_id)
                            ? 'border-cyan-400/60 bg-cyan-500/10'
                            : 'border-slate-700 bg-slate-950/70 hover:border-slate-600 hover:bg-slate-950'"
                    >
                        <div class="flex flex-col gap-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-base font-black text-white">{{ scope.template_name }}</p>
                                        <span
                                            class="rounded-full border px-2.5 py-1 text-[11px] font-bold"
                                            :class="Number(scope.country_id) === Number(filterForm.country_id)
                                                && Number(scope.education_type_id) === Number(filterForm.education_type_id)
                                                ? 'border-cyan-400/40 bg-cyan-500/10 text-cyan-200'
                                                : 'border-slate-700 bg-slate-900 text-slate-300'"
                                        >
                                            {{ scopeReferenceStatusLabel(scope) }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-400">{{ scope.country_name }} / {{ scope.education_type_name }}</p>
                                </div>

                            </div>

                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-xl border border-slate-600 px-4 py-2 text-sm font-bold text-slate-200 transition hover:bg-slate-800"
                                    @click="openScopeSettings(scope)"
                                >
                                    استعراض القالب
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded-xl bg-cyan-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-cyan-500"
                                    @click="openScopeEditorModal(scope)"
                                >
                                    تحرير القالب
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-red-500/40 bg-red-500/10 px-4 py-2 text-sm font-bold text-red-100 transition hover:bg-red-500/20 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="scopeDeleteForm.processing && deletingScopeKey === scope.key"
                                    @click="destroyTemplateScope(scope)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                    <span>{{ scopeDeleteForm.processing && deletingScopeKey === scope.key ? 'جارٍ حذف القالب...' : 'حذف القالب' }}</span>
                                </button>
                            </div>
                        </div>
                    </article>
                </div>
                <AppStatePanel
                    v-else
                    compact
                    title="لا توجد قوالب محفوظة بعد"
                    description="ابدأ بإنشاء أول قالب عام من زر «إضافة قالب جديد»، وسيظهر هنا مباشرة بعد الحفظ ليسهل الرجوع إليه وإدارته."
                />
            </section>

            <teleport to="body" :disabled="props.embedded">
                <div
                    v-if="createSurfaceVisible"
                    :class="props.embedded ? 'space-y-6' : 'school-defaults-modal-shell fixed inset-0 z-[94] flex items-center justify-center bg-black/80 p-3 backdrop-blur-sm sm:p-4'"
                    dir="rtl"
                >
                    <div
                        :class="props.embedded
                            ? 'space-y-6'
                            : 'school-defaults-modal-panel flex max-h-[calc(100dvh-1.5rem)] w-full max-w-6xl flex-col overflow-hidden rounded-3xl border border-white/10 bg-slate-950 shadow-2xl sm:max-h-[94vh]'"
                    >
                        <div v-if="!props.embedded" class="flex items-center justify-between border-b border-white/10 px-4 py-4 sm:px-6">
                            <div class="text-right">
                                <h3 class="text-base font-black text-white sm:text-xl">إضافة قالب جديد</h3>
                                <p class="mt-1 text-xs text-slate-400 sm:text-sm">
                                    أنشئ قالبًا جديدًا من نافذة مستقلة، ثم اربطه بالدولة ونوع التعليم، وراجع بيانات الدولة المستوردة قبل الحفظ.
                                </p>
                            </div>
                            <button
                                type="button"
                                class="rounded-xl p-2 text-slate-400 transition hover:bg-white/5 hover:text-white"
                                aria-label="إغلاق نافذة إضافة قالب جديد"
                                @click="closeScopeCreateModal"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <div :class="props.embedded ? 'space-y-6' : 'school-defaults-modal-body min-h-0 flex-1 overflow-y-auto bg-slate-950 p-5 sm:p-6'">
                            <section class="rounded-2xl border border-slate-700/70 bg-slate-900/90 p-5 text-right">
                                <div class="flex flex-col gap-3 border-b border-slate-800 pb-4 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <p class="text-xs font-semibold text-cyan-300">إضافة قالب جديد</p>
                                        <h2 class="mt-2 text-lg font-black text-white">تهيئة القالب وربطه بنوع التعليم</h2>
                                        <p class="mt-2 text-sm leading-6 text-slate-400">اختر الدولة، ثم نوع التعليم، وراجع بيانات الدولة المستوردة، وبعد الحفظ ستظهر بطاقة القالب مباشرة داخل القائمة الرئيسية.</p>
                                    </div>
                                    <span class="rounded-full border border-cyan-500/30 bg-cyan-500/10 px-3 py-1 text-xs font-bold text-cyan-200">
                                        {{ scopeConfigForm.template_name || 'قالب جديد' }}
                                    </span>
                                </div>

                                <div class="mt-4 grid gap-3 xl:grid-cols-2">
                                    <article v-for="step in scopeSetupSteps" :key="`create-${step.key}`" class="rounded-2xl border p-4" :class="step.cardClass">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="flex items-start gap-3">
                                                <span class="mt-1 h-2.5 w-2.5 rounded-full" :class="step.dotClass" />
                                                <div>
                                                    <p class="text-sm font-bold text-white">{{ step.title }}</p>
                                                    <p class="mt-1 text-xs leading-6 text-slate-300">{{ step.description }}</p>
                                                </div>
                                            </div>
                                            <span class="school-defaults-setup-badge rounded-full px-3 py-1 text-[11px] font-bold" :class="step.badgeClass">{{ step.badge }}</span>
                                        </div>
                                    </article>
                                </div>

                <div class="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2 rounded-2xl border border-slate-800 bg-slate-950/60 p-4">
                            <p class="text-sm font-bold text-white">المدخلات الأساسية</p>
                            <p class="mt-2 text-xs leading-6 text-slate-500">هذه هي الحقول التي تحدد هوية القالب. ما عدا نوع التعليم، فكل ما هنا يدخل مباشرة في الربط والحفظ.</p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-300" for="template-scope-name">اسم القالب</label>
                            <input id="template-scope-name" v-model="scopeConfigForm.template_name" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white" placeholder="مثال: قالب المدارس الأهلية - السعودية" />
                            <p class="text-xs leading-6 text-slate-500">اختر اسمًا يوضح الدولة ونوع التعليم حتى يسهل العثور على القالب من القائمة لاحقًا.</p>
                            <p v-if="scopeConfigForm.errors.template_name" class="text-xs text-red-400">{{ scopeConfigForm.errors.template_name }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-300" for="template-scope-country">الدولة</label>
                            <select id="template-scope-country" v-model="scopeConfigForm.country_id" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white">
                                <option value="">اختر الدولة</option>
                                <option v-for="country in props.countries" :key="country.id" :value="country.id">{{ country.name }}</option>
                            </select>
                            <p class="text-xs leading-6 text-slate-500">يبدأ جلب مرجعيات الدولة تلقائيًا بعد الاختيار، وتبقى محفوظة داخل القالب عند الحفظ.</p>
                            <p v-if="scopeConfigForm.errors.country_id" class="text-xs text-red-400">{{ scopeConfigForm.errors.country_id }}</p>
                        </div>
                        <div class="space-y-1.5 md:col-span-2">
                            <label class="block text-sm font-medium text-slate-300" for="template-scope-education-type">نوع التعليم</label>
                            <select id="template-scope-education-type" v-model="scopeConfigForm.education_type_id" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white">
                                <option value="">اختر نوع التعليم</option>
                                <option v-for="educationType in managedEducationTypes" :key="`create-education-type-${educationType.id}`" :value="educationType.id">{{ educationType.name }}</option>
                            </select>
                            <p class="text-xs leading-6 text-slate-500">اختر نوع التعليم من القائمة المركزية الحالية، وإذا احتجت نوعًا جديدًا فأضفه من القسم المركزي في الصفحة الرئيسية لهذه الميزة.</p>
                            <p v-if="scopeConfigForm.errors.education_type_id" class="text-xs text-red-400">{{ scopeConfigForm.errors.education_type_id }}</p>
                        </div>
                        <div class="md:col-span-2">
                            <div class="grid gap-3 lg:grid-cols-2">
                                <article v-for="item in scopeSummaryItems" :key="item.key" class="rounded-xl border border-slate-800 bg-slate-950/70 p-3">
                                    <p class="text-xs font-semibold text-slate-400">{{ item.label }}</p>
                                    <p class="mt-2 text-sm font-bold text-white">{{ item.value }}</p>
                                    <p class="mt-1 text-xs leading-6 text-slate-500">{{ item.helper }}</p>
                                </article>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold text-cyan-300">2. اختيار نوع التعليم</p>
                                <p class="mt-2 text-sm font-bold text-white">الأنواع المتاحة تُدار مركزيًا من الصفحة الرئيسية</p>
                                <p class="mt-1 text-xs leading-6 text-slate-500">اختر هنا نوع التعليم المناسب فقط. أما الإضافة والتعديل والحذف فتتم من قسم «أنواع التعليم المركزية» داخل الصفحة الرئيسية لهذه الميزة.</p>
                            </div>
                            <span class="school-defaults-count-pill rounded-full bg-slate-800 px-3 py-1 text-[11px] font-bold text-slate-300">{{ managedEducationTypes.length }}</span>
                        </div>

                        <div class="mt-4 rounded-xl border border-slate-800 bg-slate-900/80 px-4 py-4 text-sm text-slate-200">
                            <p class="font-semibold text-white">الأنواع المركزية الحالية</p>
                            <p class="mt-1 text-xs leading-6 text-slate-400">سيُستخدم النوع الذي تختاره هنا في ربط القالب بالدولة ونوع التعليم، ثم في مطابقة المدرسة مع القالب المناسب لاحقًا.</p>
                            <div v-if="managedEducationTypes.length" class="mt-3 flex flex-wrap gap-2">
                                <span
                                    v-for="educationType in managedEducationTypes.slice(0, 6)"
                                    :key="`create-modal-type-chip-${educationType.id}`"
                                    class="school-defaults-type-chip rounded-full border border-slate-700 bg-slate-950 px-3 py-1 text-[11px] font-bold text-slate-200"
                                >
                                    {{ educationType.name }}
                                </span>
                            </div>
                            <AppStatePanel
                                v-else
                                compact
                                title="لا توجد أنواع تعليم مضافة بعد"
                                description="أغلق هذه النافذة مؤقتًا، ثم أضف نوع التعليم من القسم المركزي أعلى الصفحة قبل إنشاء القالب."
                            />
                        </div>

                        <div class="mt-4 rounded-xl border border-slate-800 bg-slate-900/80 px-4 py-4 text-sm text-slate-200 school-defaults-reference-panel">
                            <div class="space-y-4">
                                <div>
                                    <p class="font-semibold text-white">3. البيانات المرجعية المرتبطة بالدولة</p>
                                    <p class="mt-1 text-xs leading-6 text-slate-400">بعد اختيار الدولة يجهز النظام المرجعيات المتاحة تلقائيًا ويثبتها داخل القالب عند الحفظ. لا توجد أي مزامنة منفصلة تحتاج إلى تشغيلها يدويًا.</p>
                                </div>

                                <AppStatePanel
                                    v-if="!scopeConfigForm.country_id"
                                    compact
                                    title="اختر الدولة أولًا"
                                    description="سيجلب النظام المرجعيات المتاحة تلقائيًا بمجرد اختيار الدولة."
                                />
                                <AppStatePanel
                                    v-else-if="countryReferenceLoading"
                                    variant="loading"
                                    compact
                                    title="جارٍ تجهيز البيانات المرجعية"
                                    description="يتم الآن تجهيز المرجعيات التي ستُحفظ مع القالب دون أي خطوة تشغيلية إضافية."
                                />
                                <AppStatePanel
                                    v-else-if="countryReferenceError && !countryReferenceSummary"
                                    variant="warning"
                                    compact
                                    title="تعذر تجهيز بعض المرجعيات تلقائيًا"
                                    :description="countryReferenceError"
                                />
                                <div v-else-if="countryReferenceSummary" class="rounded-xl border border-slate-800 bg-slate-950/70 p-3 school-defaults-reference-preview">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-sm font-bold text-white">معاينة مختصرة للعطلات الرسمية المرجعية</p>
                                            <p class="mt-1 text-xs leading-6 text-slate-500">{{ countryReferenceSummary.message }}</p>
                                        </div>
                                        <span class="rounded-full bg-slate-900 px-3 py-1 text-[11px] font-bold text-slate-300">
                                            {{ countryReferenceHolidayCount }} عنصر
                                        </span>
                                    </div>

                                    <div v-if="countryReferenceHolidayPreview.length" class="mt-4 grid gap-3 md:grid-cols-2">
                                        <article v-for="holiday in countryReferenceHolidayPreview" :key="`${holiday.reference_key || holiday.date || 'reference'}-${holiday.name}`" class="rounded-lg border border-slate-800 bg-slate-900/80 p-3">
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="text-xs font-semibold text-white">{{ holiday.name }}</p>
                                                <span class="rounded-full border border-slate-700 bg-slate-950 px-2 py-0.5 text-[10px] font-bold text-slate-300">
                                                    {{ holidayCategoryLabel(holiday) }}
                                                </span>
                                            </div>
                                            <p class="mt-1 text-[11px] text-slate-400">{{ holidayDateSummary(holiday) }}</p>
                                            <p v-if="holiday.notes" class="mt-1 text-[11px] leading-5 text-slate-500">{{ holiday.notes }}</p>
                                        </article>
                                    </div>
                                    <AppStatePanel
                                        v-else
                                        compact
                                        title="لا توجد عطلات رسمية جاهزة للحفظ الآن"
                                        description="لم تُرجع المرجعيات الحالية عطلات رسمية قابلة للاستخدام لهذه الدولة في السنة الحالية."
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-col-reverse gap-3 border-t border-slate-800 pt-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                            <p class="text-xs leading-6 text-slate-500">عند الحفظ سيُثبت آخر مرجع تم تحميله تلقائيًا داخل القالب، ثم يمكنك فتح القالب لإدارة مراحله وعامه الدراسي وعطلاته وبقية عناصره.</p>
                            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-end">
                                <button type="button" class="inline-flex w-full items-center justify-center rounded-xl border border-slate-600 px-4 py-2 text-sm font-bold text-slate-200 transition hover:bg-slate-800 sm:w-auto" @click="openNewScope">إعادة تعيين</button>
                                <button v-if="!props.embedded" type="button" class="inline-flex w-full items-center justify-center rounded-xl border border-slate-600 px-4 py-2 text-sm font-bold text-slate-200 transition hover:bg-slate-800 sm:w-auto" @click="closeScopeCreateModal">إلغاء</button>
                                <button type="button" class="inline-flex w-full items-center justify-center rounded-xl bg-cyan-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-cyan-500 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto" :disabled="scopeConfigForm.processing" @click="submitScopeConfig">حفظ القالب وربطه</button>
                            </div>
                        </div>
                    </div>
                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </teleport>

            <teleport to="body" :disabled="props.embedded">
                <div
                    v-if="editorSurfaceVisible"
                    :class="props.embedded ? 'space-y-6' : 'school-defaults-modal-shell fixed inset-0 z-[95] flex items-center justify-center bg-black/80 p-3 backdrop-blur-sm sm:p-4'"
                    dir="rtl"
                >
                    <div
                        :class="props.embedded
                            ? 'space-y-6'
                            : 'school-defaults-modal-panel flex max-h-[calc(100dvh-1.5rem)] w-full max-w-7xl flex-col overflow-hidden rounded-3xl border border-white/10 bg-slate-950 shadow-2xl sm:max-h-[94vh]'"
                    >
                        <div v-if="!props.embedded" class="flex items-center justify-between border-b border-white/10 px-4 py-4 sm:px-6">
                            <div class="text-right">
                                <h3 class="text-base font-black text-white sm:text-xl">تحرير القالب</h3>
                                <p class="mt-1 text-xs text-slate-400 sm:text-sm">
                                    عدّل اسم القالب وربطه وبياناته الافتراضية من نفس الصفحة، ثم احفظ التغييرات دون الاعتماد على نافذة iframe منفصلة.
                                </p>
                            </div>
                            <button
                                type="button"
                                class="rounded-xl p-2 text-slate-400 transition hover:bg-white/5 hover:text-white"
                                aria-label="إغلاق نافذة تحرير القالب"
                                @click="closeScopeEditorModal"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>

                        <div :class="props.embedded ? 'space-y-6' : 'school-defaults-modal-body min-h-0 flex-1 overflow-y-auto bg-slate-950 p-5 sm:p-6'">
                            <AppStatePanel
                                v-if="scopeEditorLoading"
                                compact
                                title="جارٍ تجهيز القالب المحدد"
                                description="يتم الآن تحميل بيانات القالب الحالية وتجهيز محرر التعديلات داخل هذه النافذة."
                            />

                            <template v-else>
                                <div class="space-y-6">
            <section class="rounded-2xl border border-cyan-500/20 bg-slate-900/95 p-5 text-right">
                <div class="flex flex-col gap-3 border-b border-slate-800 pb-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-xs font-semibold text-cyan-300">البيانات الأساسية للقالب</p>
                        <h2 class="mt-2 text-lg font-black text-white">حرر الاسم والدولة ونوع التعليم من نفس النافذة</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-400">يتم حفظ هذه البيانات داخل القالب نفسه، وتُحدَّث مرجعيات الدولة تلقائيًا عند اختيار الدولة ثم تبقى قابلة للمراجعة والتعديل والحفظ من النافذة نفسها.</p>
                    </div>
                    <span class="rounded-full border border-cyan-500/30 bg-cyan-500/10 px-3 py-1 text-xs font-bold text-cyan-200">
                        {{ scopeConfigForm.template_name || currentTemplateScope?.template_name || 'القالب الحالي' }}
                    </span>
                </div>

                <div class="mt-5 grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-1.5 md:col-span-2">
                            <label for="modal-template-name" class="block text-sm font-medium text-slate-300">اسم القالب</label>
                            <input id="modal-template-name" v-model="scopeConfigForm.template_name" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white" placeholder="اسم واضح يصف الدولة ونوع التعليم" />
                            <p v-if="scopeConfigForm.errors.template_name" class="text-xs text-red-400">{{ scopeConfigForm.errors.template_name }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="modal-template-country" class="block text-sm font-medium text-slate-300">الدولة</label>
                            <select id="modal-template-country" v-model="scopeConfigForm.country_id" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white">
                                <option value="" disabled>اختر الدولة</option>
                                <option v-for="country in props.countries" :key="`modal-country-${country.id}`" :value="country.id">{{ country.name }}</option>
                            </select>
                            <p v-if="scopeConfigForm.errors.country_id" class="text-xs text-red-400">{{ scopeConfigForm.errors.country_id }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="modal-template-education-type" class="block text-sm font-medium text-slate-300">نوع التعليم</label>
                            <select id="modal-template-education-type" v-model="scopeConfigForm.education_type_id" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white">
                                <option value="" disabled>اختر نوع التعليم</option>
                                <option v-for="educationType in managedEducationTypes" :key="`modal-education-type-${educationType.id}`" :value="educationType.id">{{ educationType.name }}</option>
                            </select>
                            <p v-if="scopeConfigForm.errors.education_type_id" class="text-xs text-red-400">{{ scopeConfigForm.errors.education_type_id }}</p>
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-medium text-slate-300">أنواع التعليم المركزية</p>
                                <span class="rounded-full border border-slate-700 bg-slate-900/80 px-3 py-1 text-[11px] font-bold text-slate-300">
                                    {{ managedEducationTypes.length }} نوع
                                </span>
                            </div>
                            <p class="text-xs leading-6 text-slate-500">اختر النوع من القائمة أعلاه، وإذا احتجت إضافة نوع جديد أو تعديل نوع قائم فارجع إلى قسم «أنواع التعليم المركزية» في الصفحة الرئيسية لهذه الميزة.</p>
                            <div v-if="managedEducationTypes.length" class="flex flex-wrap gap-2">
                                <span
                                    v-for="educationType in managedEducationTypes.slice(0, 6)"
                                    :key="`editor-modal-type-chip-${educationType.id}`"
                                    class="rounded-full border border-slate-700 bg-slate-950 px-3 py-1 text-[11px] font-bold text-slate-200"
                                >
                                    {{ educationType.name }}
                                </span>
                            </div>
                            <AppStatePanel
                                v-else
                                compact
                                title="لا توجد أنواع تعليم مضافة بعد"
                                description="أضف نوع التعليم من القسم المركزي أولًا، ثم عد إلى هذه النافذة لمتابعة تحرير القالب."
                            />
                        </div>
                    </div>

                    <aside class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4 school-defaults-reference-sidebar">
                        <div>
                            <p class="text-xs font-semibold text-slate-400">البيانات المرجعية المرتبطة بالقالب</p>
                            <p class="mt-1 text-sm font-bold text-white">{{ selectedScopeCountry?.name || 'بانتظار اختيار الدولة' }}</p>
                            <p class="mt-3 text-xs leading-6 text-slate-400">تُحدَّث المرجعيات تلقائيًا عند تغيير الدولة وتُحفظ مع القالب نفسه دون أي شاشة تكامل أو مزامنة منفصلة.</p>
                            <p v-if="countryReferenceLoading" class="mt-3 text-xs font-semibold text-cyan-200">جارٍ تجهيز المرجعيات المتاحة...</p>
                            <p v-else-if="countryReferenceError && !countryReferenceSummary" class="mt-3 text-xs font-semibold text-amber-200">{{ countryReferenceError }}</p>
                        </div>
                        <div class="mt-4 flex flex-col gap-3">
                            <button type="button" class="inline-flex items-center justify-center rounded-xl bg-cyan-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-cyan-500 disabled:cursor-not-allowed disabled:opacity-60" :disabled="scopeConfigForm.processing" @click="submitScopeConfig">حفظ البيانات الأساسية</button>
                            <button v-if="!props.embedded" type="button" class="inline-flex items-center justify-center rounded-xl border border-slate-600 px-4 py-2 text-sm font-bold text-slate-200 transition hover:bg-slate-800" @click="closeScopeEditorModal">إلغاء</button>
                        </div>
                    </aside>
                </div>

                <div class="mt-5">
                    <AppStatePanel
                        v-if="!scopeConfigForm.country_id"
                        compact
                        title="ابدأ باختيار الدولة"
                        description="سيجهز النظام المرجعيات المتاحة تلقائيًا بعد اختيار الدولة."
                    />
                    <AppStatePanel
                        v-else-if="countryReferenceLoading"
                        compact
                        title="جارٍ تجهيز البيانات المرجعية"
                        description="يتم الآن تحديث مسودة القالب بالمرجعيات المتاحة تلقائيًا."
                    />
                    <AppStatePanel
                        v-else-if="countryReferenceError && !countryReferenceSummary"
                        compact
                        title="تعذر تجهيز بعض المرجعيات"
                        :description="countryReferenceError"
                    />
                    <div v-else-if="countryReferenceSummary" class="space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-bold text-white">معاينة مختصرة للعطلات الرسمية المرجعية</p>
                                <p class="mt-1 text-xs leading-6 text-slate-500">{{ countryReferenceSummary.message }}</p>
                            </div>
                            <span class="rounded-full bg-slate-950 px-3 py-1 text-[11px] font-bold text-slate-300">
                                {{ countryReferenceHolidayCount }} عنصر
                            </span>
                        </div>

                        <div v-if="countryReferenceHolidayPreview.length" class="grid gap-3 md:grid-cols-2">
                            <article v-for="holiday in countryReferenceHolidayPreview" :key="`editor-holiday-${holiday.reference_key || holiday.date || 'reference'}-${holiday.name}`" class="rounded-lg border border-slate-800 bg-slate-950/80 p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs font-semibold text-white">{{ holiday.name }}</p>
                                    <span class="rounded-full border border-slate-700 bg-slate-900 px-2 py-0.5 text-[10px] font-bold text-slate-300">
                                        {{ holidayCategoryLabel(holiday) }}
                                    </span>
                                </div>
                                <p class="mt-1 text-[11px] text-slate-400">{{ holidayDateSummary(holiday) }}</p>
                                <p v-if="holiday.notes" class="mt-1 text-[11px] leading-5 text-slate-500">{{ holiday.notes }}</p>
                            </article>
                        </div>
                        <AppStatePanel
                            v-else
                            compact
                            title="لا توجد عطلات رسمية جاهزة للمراجعة الآن"
                            description="لم تُرجع المرجعيات الحالية عطلات رسمية قابلة للاستخدام لهذه الدولة في الاستجابة الحالية."
                        />
                    </div>
                </div>
            </section>

            <section
                id="school-default-template-editor"
                class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-5 text-right"
            >
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-xs font-semibold text-emerald-200">القالب المفتوح الآن</p>
                        <h2 class="mt-2 text-lg font-black text-white">{{ currentTemplateScope?.template_name || 'القالب الحالي' }}</h2>
                        <p class="mt-2 text-sm leading-7 text-emerald-50/90">
                            يتم الآن تعديل قالب مرتبط بـ
                            <strong>{{ selectedCountry?.name }}</strong>
                            /
                            <strong>{{ selectedEducationType?.name || 'نوع التعليم غير محدد' }}</strong>.
                            أي تعديل هنا سيؤثر فقط على القالب العام المرتبط بهذه الدولة ونوع التعليم، ثم يُنسخ إلى المدارس الجديدة أو المدارس التي لم تستورد القالب بعد.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:w-[520px]">
                        <article v-for="card in templateInventoryCards" :key="card.key" class="rounded-2xl border border-emerald-500/20 bg-slate-950/70 p-4">
                            <p class="text-xs font-semibold text-emerald-200">{{ card.label }}</p>
                            <p class="mt-2 text-2xl font-black text-white">{{ card.value }}</p>
                            <p class="mt-2 text-xs leading-6 text-slate-400">{{ card.helper }}</p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-4">
                <article class="rounded-2xl border border-slate-700/70 bg-slate-900/90 p-5 xl:col-span-1">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="inline-flex items-center gap-2 text-lg font-bold text-white"><School class="h-4 w-4 text-cyan-300" />المراحل التعليمية</h2>
                        <button type="button" class="text-xs text-slate-400 hover:text-white" @click="resetStageForm">تهيئة</button>
                    </div>
                    <div class="grid gap-3">
                        <div class="space-y-1.5">
                            <label for="default-stage-master" class="block text-sm font-medium text-slate-300">المرحلة المركزية المرتبطة</label>
                            <select id="default-stage-master" v-model="selectedEducationStageForTemplate" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                                <option value="">بدون ربط مركزي</option>
                                <option v-for="educationStage in activeManagedEducationStages" :key="`template-stage-master-${educationStage.id}`" :value="educationStage.id">{{ educationStage.name }}</option>
                            </select>
                            <p class="text-xs leading-6 text-slate-500">عند الربط بمرحلة مركزية سيُثبّت النظام اسم مرحلة القالب تلقائيًا من نفس المرجع، مع بقاء بقية الإعدادات مثل الكود وأوقات الدوام مستقلة داخل القالب.</p>
                            <p v-if="stageForm.errors.education_stage_id" class="text-xs text-red-400">{{ stageForm.errors.education_stage_id }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <label for="default-stage-name" class="block text-sm font-medium text-slate-300">اسم المرحلة</label>
                            <input id="default-stage-name" v-model="stageForm.name" :readonly="Boolean(selectedEducationStageForTemplate)" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" placeholder="اسم المرحلة" />
                            <p v-if="selectedEducationStageForTemplate" class="text-xs leading-6 text-slate-500">تم ملء الاسم من المرحلة المركزية المختارة، ويمكنك إلغاء الربط إذا احتجت مرحلة قالب مستقلة لا ترتبط بالماستر.</p>
                        </div>
                        <div class="space-y-1.5">
                            <label for="default-stage-code" class="block text-sm font-medium text-slate-300">الكود</label>
                            <input id="default-stage-code" v-model="stageForm.code" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" placeholder="الكود - اختياري" />
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="space-y-1.5">
                                <label for="default-stage-day-start" class="block text-sm font-medium text-slate-300">بداية اليوم الدراسي</label>
                                <input id="default-stage-day-start" v-model="stageForm.school_day_start_time" type="time" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" />
                            </div>
                            <div class="space-y-1.5">
                                <label for="default-stage-day-end" class="block text-sm font-medium text-slate-300">نهاية اليوم الدراسي</label>
                                <input id="default-stage-day-end" v-model="stageForm.school_day_end_time" type="time" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" />
                            </div>
                            <div class="space-y-1.5">
                                <label for="default-stage-sort-order" class="block text-sm font-medium text-slate-300">الترتيب</label>
                                <input id="default-stage-sort-order" v-model.number="stageForm.sort_order" type="number" min="0" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" placeholder="الترتيب" />
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <p class="text-sm font-medium text-slate-300">الحالة</p>
                            <label class="inline-flex items-center gap-2 text-sm text-slate-300"><input v-model="stageForm.is_active" type="checkbox" class="rounded border-slate-600 bg-slate-950" />مفعّل</label>
                        </div>
                        <p v-if="stageForm.errors.name" class="text-xs text-red-400">{{ stageForm.errors.name }}</p>
                        <p v-if="stageForm.errors.code" class="text-xs text-red-400">{{ stageForm.errors.code }}</p>
                        <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-600 px-4 py-2 text-sm font-bold text-white hover:bg-cyan-500" :disabled="stageForm.processing" @click="submitStage">
                            <Save class="h-4 w-4" />
                            <span>{{ stageEditId ? 'تحديث المرحلة' : 'إضافة مرحلة' }}</span>
                        </button>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-700/70 bg-slate-900/90 p-5 xl:col-span-1">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="inline-flex items-center gap-2 text-lg font-bold text-white"><CalendarDays class="h-4 w-4 text-cyan-300" />فصول المراحل</h2>
                        <button type="button" class="text-xs text-slate-400 hover:text-white" @click="resetStageTermForm()">تهيئة</button>
                    </div>
                    <div class="grid gap-3">
                        <div class="space-y-1.5">
                            <label for="default-stage-term-stage" class="block text-sm font-medium text-slate-300">المرحلة التعليمية</label>
                            <select id="default-stage-term-stage" v-model="stageTermForm.school_default_stage_template_id" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                                <option value="" disabled>اختر المرحلة</option>
                                <option v-for="stage in stageOptions" :key="`stage-term-stage-${stage.id}`" :value="stage.id">{{ stage.name }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label for="default-stage-term-name" class="block text-sm font-medium text-slate-300">اسم الفصل الدراسي</label>
                            <input id="default-stage-term-name" v-model="stageTermForm.name" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" placeholder="مثال: الفصل الدراسي الأول" />
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label for="default-stage-term-start" class="block text-sm font-medium text-slate-300">تاريخ البداية</label>
                                <input id="default-stage-term-start" v-model="stageTermForm.start_date" type="date" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" />
                            </div>
                            <div class="space-y-1.5">
                                <label for="default-stage-term-end" class="block text-sm font-medium text-slate-300">تاريخ النهاية</label>
                                <input id="default-stage-term-end" v-model="stageTermForm.end_date" type="date" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" />
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label for="default-stage-term-sort-order" class="block text-sm font-medium text-slate-300">الترتيب</label>
                            <input id="default-stage-term-sort-order" v-model.number="stageTermForm.sort_order" type="number" min="0" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" placeholder="الترتيب" />
                        </div>
                        <div class="space-y-1.5">
                            <p class="text-sm font-medium text-slate-300">الحالة</p>
                            <label class="inline-flex items-center gap-2 text-sm text-slate-300"><input v-model="stageTermForm.is_active" type="checkbox" class="rounded border-slate-600 bg-slate-950" />مفعّل</label>
                        </div>
                        <p class="text-xs leading-6 text-slate-500">استخدم هذا القسم لحفظ مواعيد الفصل الدراسي الأول والثاني لكل مرحلة على حدة. إذا لم تصل التواريخ من API فستبقى الحقول قابلة للاستكمال اليدوي.</p>
                        <p v-if="stageTermForm.errors.school_default_stage_template_id" class="text-xs text-red-400">{{ stageTermForm.errors.school_default_stage_template_id }}</p>
                        <p v-if="stageTermForm.errors.name" class="text-xs text-red-400">{{ stageTermForm.errors.name }}</p>
                        <p v-if="stageTermForm.errors.start_date" class="text-xs text-red-400">{{ stageTermForm.errors.start_date }}</p>
                        <p v-if="stageTermForm.errors.end_date" class="text-xs text-red-400">{{ stageTermForm.errors.end_date }}</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-600 px-4 py-2 text-sm font-bold text-white hover:bg-cyan-500" :disabled="stageTermForm.processing" @click="submitStageTerm">
                                <Save class="h-4 w-4" />
                                <span>{{ stageTermEditId ? 'تحديث فصل المرحلة' : 'إضافة فصل للمرحلة' }}</span>
                            </button>
                            <button
                                v-if="stageTermEditId"
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-500"
                                @click="destroyWithConfirmation('حذف فصل المرحلة', 'سيتم حذف هذا الفصل الدراسي من المرحلة داخل القالب العام فقط.', route('admin.school_defaults.stage_terms.destroy', stageTermEditId))"
                            >
                                <Trash2 class="h-4 w-4" />
                                <span>حذف الفصل</span>
                            </button>
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-700/70 bg-slate-900/90 p-5 xl:col-span-1">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="inline-flex items-center gap-2 text-lg font-bold text-white"><CalendarDays class="h-4 w-4 text-cyan-300" />ترمات الصفوف</h2>
                        <button type="button" class="text-xs text-slate-400 hover:text-white" @click="resetStageGradeTermForm()">تهيئة</button>
                    </div>
                    <div class="grid gap-3">
                        <div class="space-y-1.5">
                            <label for="default-stage-grade-term-stage" class="block text-sm font-medium text-slate-300">المرحلة التعليمية</label>
                            <select id="default-stage-grade-term-stage" v-model="stageGradeTermForm.school_default_stage_template_id" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                                <option value="" disabled>اختر المرحلة</option>
                                <option v-for="stage in stageOptions" :key="`grade-term-stage-${stage.id}`" :value="stage.id">{{ stage.name }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label for="default-stage-grade-term-grade" class="block text-sm font-medium text-slate-300">الصف</label>
                            <select id="default-stage-grade-term-grade" v-model="stageGradeTermForm.school_default_stage_grade_template_id" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                                <option value="" disabled>اختر الصف</option>
                                <option v-for="grade in stageGradeTermGradeOptions" :key="`grade-term-grade-${grade.id}`" :value="grade.id">{{ grade.name }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label for="default-stage-grade-term-name" class="block text-sm font-medium text-slate-300">اسم الترم الدراسي</label>
                            <input id="default-stage-grade-term-name" v-model="stageGradeTermForm.name" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" placeholder="مثال: الفصل الدراسي الأول" />
                        </div>
                        <div class="space-y-1.5">
                            <label for="default-stage-grade-term-sort-order" class="block text-sm font-medium text-slate-300">الترتيب</label>
                            <input id="default-stage-grade-term-sort-order" v-model.number="stageGradeTermForm.sort_order" type="number" min="0" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" placeholder="الترتيب" />
                        </div>
                        <div class="space-y-1.5">
                            <p class="text-sm font-medium text-slate-300">الحالة</p>
                            <label class="inline-flex items-center gap-2 text-sm text-slate-300"><input v-model="stageGradeTermForm.is_active" type="checkbox" class="rounded border-slate-600 bg-slate-950" />مفعّل</label>
                        </div>
                        <p v-if="stageGradeTermForm.errors.school_default_stage_template_id" class="text-xs text-red-400">{{ stageGradeTermForm.errors.school_default_stage_template_id }}</p>
                        <p v-if="stageGradeTermForm.errors.school_default_stage_grade_template_id" class="text-xs text-red-400">{{ stageGradeTermForm.errors.school_default_stage_grade_template_id }}</p>
                        <p v-if="stageGradeTermForm.errors.name" class="text-xs text-red-400">{{ stageGradeTermForm.errors.name }}</p>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-600 px-4 py-2 text-sm font-bold text-white hover:bg-cyan-500" :disabled="stageGradeTermForm.processing" @click="submitStageGradeTerm">
                                <Save class="h-4 w-4" />
                                <span>{{ stageGradeTermEditId ? 'تحديث ترم الصف' : 'إضافة ترم للصف' }}</span>
                            </button>
                            <button
                                v-if="stageGradeTermEditId"
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-500"
                                @click="destroyWithConfirmation('حذف ترم الصف', 'سيتم حذف ترم الصف من القالب العام فقط.', route('admin.school_defaults.stage_grade_terms.destroy', stageGradeTermEditId))"
                            >
                                <Trash2 class="h-4 w-4" />
                                <span>حذف الترم</span>
                            </button>
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-700/70 bg-slate-900/90 p-5 xl:col-span-1">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="inline-flex items-center gap-2 text-lg font-bold text-white"><GraduationCap class="h-4 w-4 text-cyan-300" />الصفوف الافتراضية</h2>
                        <button type="button" class="text-xs text-slate-400 hover:text-white" @click="resetStageGradeForm()">تهيئة</button>
                    </div>
                    <div class="grid gap-3">
                        <div class="space-y-1.5">
                            <label for="default-stage-grade-stage" class="block text-sm font-medium text-slate-300">المرحلة التعليمية</label>
                            <select id="default-stage-grade-stage" v-model="stageGradeForm.school_default_stage_template_id" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                                <option value="" disabled>اختر المرحلة</option>
                                <option v-for="stage in stageOptions" :key="`grade-stage-${stage.id}`" :value="stage.id">{{ stage.name }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label for="default-stage-grade-name" class="block text-sm font-medium text-slate-300">اسم الصف</label>
                            <input id="default-stage-grade-name" v-model="stageGradeForm.name" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" placeholder="اسم الصف" />
                        </div>
                        <div class="space-y-1.5">
                            <label for="default-stage-grade-sort-order" class="block text-sm font-medium text-slate-300">الترتيب</label>
                            <input id="default-stage-grade-sort-order" v-model.number="stageGradeForm.sort_order" type="number" min="0" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" placeholder="الترتيب" />
                        </div>
                        <div class="space-y-1.5">
                            <p class="text-sm font-medium text-slate-300">الحالة</p>
                            <label class="inline-flex items-center gap-2 text-sm text-slate-300"><input v-model="stageGradeForm.is_active" type="checkbox" class="rounded border-slate-600 bg-slate-950" />مفعّل</label>
                        </div>
                        <p v-if="stageGradeForm.errors.school_default_stage_template_id" class="text-xs text-red-400">{{ stageGradeForm.errors.school_default_stage_template_id }}</p>
                        <p v-if="stageGradeForm.errors.name" class="text-xs text-red-400">{{ stageGradeForm.errors.name }}</p>
                        <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-600 px-4 py-2 text-sm font-bold text-white hover:bg-cyan-500" :disabled="stageGradeForm.processing" @click="submitStageGrade">
                            <Save class="h-4 w-4" />
                            <span>{{ stageGradeEditId ? 'تحديث الصف' : 'إضافة صف' }}</span>
                        </button>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-700/70 bg-slate-900/90 p-5 xl:col-span-1">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="inline-flex items-center gap-2 text-lg font-bold text-white"><School class="h-4 w-4 text-cyan-300" />الشعب الدراسية</h2>
                        <button type="button" class="text-xs text-slate-400 hover:text-white" @click="resetClassroomForm()">تهيئة</button>
                    </div>
                    <div class="grid gap-3">
                        <div class="space-y-1.5">
                            <label for="default-classroom-stage" class="block text-sm font-medium text-slate-300">المرحلة التعليمية</label>
                            <select id="default-classroom-stage" v-model="classroomForm.school_default_stage_template_id" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                                <option value="" disabled>اختر المرحلة</option>
                                <option v-for="stage in stageOptions" :key="`class-stage-${stage.id}`" :value="stage.id">{{ stage.name }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label for="default-classroom-grade" class="block text-sm font-medium text-slate-300">الصف</label>
                            <select id="default-classroom-grade" v-model="classroomForm.school_default_stage_grade_template_id" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white">
                                <option value="" disabled>اختر الصف</option>
                                <option v-for="grade in classroomGradeOptions" :key="`class-grade-${grade.id}`" :value="grade.id">{{ grade.name }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label for="default-classroom-name" class="block text-sm font-medium text-slate-300">اسم الشعبة</label>
                            <input id="default-classroom-name" v-model="classroomForm.name" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" placeholder="اسم الشعبة" />
                        </div>
                        <div class="space-y-1.5">
                            <label for="default-classroom-code" class="block text-sm font-medium text-slate-300">الكود</label>
                            <input id="default-classroom-code" v-model="classroomForm.code" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" placeholder="الكود - اختياري" />
                        </div>
                        <div class="space-y-1.5">
                            <label for="default-classroom-sort-order" class="block text-sm font-medium text-slate-300">الترتيب</label>
                            <input id="default-classroom-sort-order" v-model.number="classroomForm.sort_order" type="number" min="0" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" placeholder="الترتيب" />
                        </div>
                        <div class="space-y-1.5">
                            <p class="text-sm font-medium text-slate-300">الحالة</p>
                            <label class="inline-flex items-center gap-2 text-sm text-slate-300"><input v-model="classroomForm.is_active" type="checkbox" class="rounded border-slate-600 bg-slate-950" />مفعّل</label>
                        </div>
                        <p v-if="classroomForm.errors.name" class="text-xs text-red-400">{{ classroomForm.errors.name }}</p>
                        <p v-if="classroomForm.errors.code" class="text-xs text-red-400">{{ classroomForm.errors.code }}</p>
                        <p v-if="classroomForm.errors.school_default_stage_grade_template_id" class="text-xs text-red-400">{{ classroomForm.errors.school_default_stage_grade_template_id }}</p>
                        <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-600 px-4 py-2 text-sm font-bold text-white hover:bg-cyan-500" :disabled="classroomForm.processing" @click="submitClassroom">
                            <Save class="h-4 w-4" />
                            <span>{{ classroomEditId ? 'تحديث الشعبة' : 'إضافة شعبة' }}</span>
                        </button>
                    </div>
                </article>
            </section>

            <section class="space-y-4">
                <div v-if="stageTemplates.length === 0" class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/70 p-5 text-sm text-slate-400">لا توجد مراحل افتراضية مضافة حتى الآن.</div>
                <article v-for="stage in stageTemplates" :key="stage.id" class="rounded-2xl border border-slate-700/70 bg-slate-900/90 p-5 text-right">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h3 class="text-lg font-black text-white">{{ stage.name }}</h3>
                            <p class="mt-1 text-xs text-slate-400">الكود: {{ stage.code || '-' }} | الترتيب: {{ stage.sort_order || 0 }} | {{ statusLabel(stage.is_active) }}</p>
                            <p class="mt-1 text-xs text-slate-500">بداية اليوم: {{ stage.school_day_start_time || '-' }} | نهاية اليوم: {{ stage.school_day_end_time || '-' }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-500" @click="editStage(stage)"><Pencil class="h-3.5 w-3.5" />تعديل المرحلة</button>
                            <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-slate-700 px-3 py-2 text-xs font-bold text-white hover:bg-slate-600" @click="resetStageTermForm(stage.id)">إضافة فصل مرحلة</button>
                            <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-slate-700 px-3 py-2 text-xs font-bold text-white hover:bg-slate-600" @click="resetStageGradeForm(stage.id)">إضافة صف</button>
                            <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-slate-700 px-3 py-2 text-xs font-bold text-white hover:bg-slate-600" @click="resetStageGradeTermForm(stage.id)">إضافة ترم صف</button>
                            <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-slate-700 px-3 py-2 text-xs font-bold text-white hover:bg-slate-600" @click="resetClassroomForm(stage.id)">إضافة شعبة</button>
                            <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-red-600 px-3 py-2 text-xs font-bold text-white hover:bg-red-500" @click="destroyWithConfirmation('حذف المرحلة', 'سيتم حذف المرحلة مع فصولها الدراسية وصفوفها وترمات الصف والشعب الافتراضية التابعة لها فقط.', route('admin.school_defaults.stages.destroy', stage.id))"><Trash2 class="h-3.5 w-3.5" />حذف</button>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 xl:grid-cols-3">
                        <div class="rounded-2xl border border-slate-800 bg-slate-950/80 p-4">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <p class="text-sm font-bold text-white">الفصول الدراسية للمراحل</p>
                                <span class="rounded-full border border-slate-700 bg-slate-900 px-2.5 py-1 text-[11px] font-bold text-slate-300">{{ (stage.stage_terms || []).length }}</span>
                            </div>
                            <div v-if="!(stage.stage_terms || []).length" class="rounded-xl border border-dashed border-slate-800 bg-slate-900/70 px-3 py-4 text-xs leading-6 text-slate-500">
                                لا توجد فصول دراسية محفوظة لهذه المرحلة بعد. سيُنشأ الفصل الدراسي الأول والثاني تلقائيًا عند حفظ القالب إن لم يأتيا من API، ويمكنك استكمال التواريخ يدويًا من النموذج الجانبي.
                            </div>
                            <div v-for="term in stage.stage_terms || []" :key="term.id" class="mb-2 rounded-xl border border-slate-800 bg-slate-900/80 px-3 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="text-sm font-semibold text-white">{{ term.name }}</p>
                                            <span class="rounded-full border px-2.5 py-1 text-[11px] font-bold" :class="stageTermSourceClass(term.source)">{{ stageTermSourceLabel(term.source) }}</span>
                                        </div>
                                        <p class="mt-2 text-xs text-slate-400">
                                            البداية: {{ formatDate(term.start_date) }} | النهاية: {{ formatDate(term.end_date) }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            الترتيب: {{ term.sort_order || 0 }} | {{ statusLabel(term.is_active) }}
                                        </p>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="button" class="rounded-lg bg-blue-600 px-2 py-1 text-xs font-bold text-white hover:bg-blue-500" @click="editStageTerm(stage, term)">تعديل</button>
                                        <button type="button" class="rounded-lg bg-red-600 px-2 py-1 text-xs font-bold text-white hover:bg-red-500" @click="destroyWithConfirmation('حذف فصل المرحلة', 'سيتم حذف هذا الفصل الدراسي من المرحلة داخل القالب العام فقط.', route('admin.school_defaults.stage_terms.destroy', term.id))">حذف</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-800 bg-slate-950/80 p-4">
                            <p class="mb-3 text-sm font-bold text-white">الصفوف المرتبطة</p>
                            <div v-if="(stage.grades || []).length === 0" class="text-xs text-slate-500">لا توجد صفوف لهذا القالب.</div>
                            <div v-for="grade in stage.grades || []" :key="grade.id" class="mb-2 flex items-center justify-between rounded-xl border border-slate-800 bg-slate-900/80 px-3 py-2">
                                <div>
                                    <p class="text-sm font-semibold text-white">{{ grade.name }}</p>
                                    <p class="text-xs text-slate-500">الترتيب: {{ grade.sort_order || 0 }} | {{ statusLabel(grade.is_active) }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <button
                                            v-for="term in grade.grade_terms || []"
                                            :key="`grade-term-chip-${term.id}`"
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-full border border-cyan-500/30 bg-cyan-500/10 px-2.5 py-1 text-[11px] font-bold text-cyan-100 hover:bg-cyan-500/20"
                                            @click="editStageGradeTerm(stage, grade, term)"
                                        >
                                            <span>{{ term.name }}</span>
                                            <span class="text-cyan-300/80">({{ term.sort_order || 0 }})</span>
                                        </button>
                                        <span v-if="!(grade.grade_terms || []).length" class="text-[11px] text-slate-500">لا توجد ترمات صف محفوظة بعد.</span>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" class="rounded-lg bg-blue-600 px-2 py-1 text-xs font-bold text-white hover:bg-blue-500" @click="editStageGrade(stage, grade)">تعديل</button>
                                    <button type="button" class="rounded-lg bg-red-600 px-2 py-1 text-xs font-bold text-white hover:bg-red-500" @click="destroyWithConfirmation('حذف الصف', 'سيتم حذف الصف مع الشعب الافتراضية التابعة له فقط.', route('admin.school_defaults.stage_grades.destroy', grade.id))">حذف</button>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-800 bg-slate-950/80 p-4">
                            <p class="mb-3 text-sm font-bold text-white">الشعب المرتبطة</p>
                            <div v-if="(stage.classrooms || []).length === 0" class="text-xs text-slate-500">لا توجد شعب لهذا القالب.</div>
                            <div v-for="classroom in stage.classrooms || []" :key="classroom.id" class="mb-2 flex items-center justify-between rounded-xl border border-slate-800 bg-slate-900/80 px-3 py-2">
                                <div>
                                    <p class="text-sm font-semibold text-white">{{ classroom.name }}</p>
                                    <p class="text-xs text-slate-500">الصف: {{ classroom.grade?.name || '-' }} | الكود: {{ classroom.code || '-' }} | {{ statusLabel(classroom.is_active) }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" class="rounded-lg bg-blue-600 px-2 py-1 text-xs font-bold text-white hover:bg-blue-500" @click="editClassroom(classroom)">تعديل</button>
                                    <button type="button" class="rounded-lg bg-red-600 px-2 py-1 text-xs font-bold text-white hover:bg-red-500" @click="destroyWithConfirmation('حذف الشعبة', 'سيتم حذف هذه الشعبة من القوالب العامة فقط.', route('admin.school_defaults.classrooms.destroy', classroom.id))">حذف</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </section>
            <section class="grid gap-4 xl:grid-cols-2">
                <article class="rounded-2xl border border-slate-700/70 bg-slate-900/90 p-5">
                    <h2 class="mb-4 inline-flex items-center gap-2 text-lg font-bold text-white"><CalendarDays class="h-4 w-4 text-cyan-300" />الأعوام الدراسية</h2>
                    <div class="grid gap-3">
                        <div class="space-y-1.5">
                            <label for="default-academic-year-name" class="block text-sm font-medium text-slate-300">اسم العام الدراسي</label>
                            <input id="default-academic-year-name" v-model="academicYearForm.name" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" placeholder="اسم العام الدراسي" />
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label for="default-academic-year-start" class="block text-sm font-medium text-slate-300">تاريخ البداية</label>
                                <input id="default-academic-year-start" v-model="academicYearForm.starts_on" type="date" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" />
                            </div>
                            <div class="space-y-1.5">
                                <label for="default-academic-year-end" class="block text-sm font-medium text-slate-300">تاريخ النهاية</label>
                                <input id="default-academic-year-end" v-model="academicYearForm.ends_on" type="date" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" />
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <p class="text-sm font-medium text-slate-300">الحالة</p>
                            <label class="inline-flex items-center gap-2 text-sm text-slate-300"><input v-model="academicYearForm.is_active" type="checkbox" class="rounded border-slate-600 bg-slate-950" />مفعّل</label>
                        </div>
                        <p v-if="academicYearForm.errors.name" class="text-xs text-red-400">{{ academicYearForm.errors.name }}</p>
                        <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-600 px-4 py-2 text-sm font-bold text-white hover:bg-cyan-500" :disabled="academicYearForm.processing" @click="submitAcademicYear">
                            <Save class="h-4 w-4" />
                            <span>{{ academicYearEditId ? 'تحديث العام الدراسي' : 'إضافة عام دراسي' }}</span>
                        </button>
                    </div>
                    <div class="mt-4 space-y-2">
                        <div v-for="year in academicYearTemplates" :key="year.id" class="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-950/80 px-3 py-3">
                            <div>
                                <p class="font-semibold text-white">{{ year.name }}</p>
                                <p class="text-xs text-slate-500">{{ formatDate(year.starts_on) }} - {{ formatDate(year.ends_on) }} | {{ statusLabel(year.is_active) }}</p>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" class="rounded-lg bg-blue-600 px-2 py-1 text-xs font-bold text-white hover:bg-blue-500" @click="editAcademicYear(year)">تعديل</button>
                                <button type="button" class="rounded-lg bg-red-600 px-2 py-1 text-xs font-bold text-white hover:bg-red-500" @click="destroyWithConfirmation('حذف العام الدراسي', 'سيتم حذف قالب العام الدراسي من المنصة فقط.', route('admin.school_defaults.academic_years.destroy', year.id))">حذف</button>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-700/70 bg-slate-900/90 p-5">
                    <h2 class="mb-4 inline-flex items-center gap-2 text-lg font-bold text-white"><Clock3 class="h-4 w-4 text-cyan-300" />العطل الرسمية</h2>
                    <div class="grid gap-3">
                        <div class="space-y-1.5">
                            <label for="default-holiday-name" class="block text-sm font-medium text-slate-300">اسم العطلة</label>
                            <input id="default-holiday-name" v-model="holidayForm.name" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" placeholder="اسم العطلة" />
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="space-y-1.5">
                                <label for="default-holiday-start-date" class="block text-sm font-medium text-slate-300">تاريخ البداية</label>
                                <input id="default-holiday-start-date" v-model="holidayForm.start_date" type="date" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" />
                            </div>
                            <div class="space-y-1.5">
                                <label for="default-holiday-end-date" class="block text-sm font-medium text-slate-300">تاريخ النهاية</label>
                                <input id="default-holiday-end-date" v-model="holidayForm.end_date" type="date" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" />
                            </div>
                            <div class="space-y-1.5">
                                <label for="default-holiday-return-date" class="block text-sm font-medium text-slate-300">تاريخ العودة</label>
                                <input id="default-holiday-return-date" v-model="holidayForm.return_date" type="date" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" />
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label for="default-holiday-notes" class="block text-sm font-medium text-slate-300">ملاحظات</label>
                            <textarea id="default-holiday-notes" v-model="holidayForm.notes" rows="3" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" placeholder="ملاحظات - اختياري" />
                        </div>
                        <div class="space-y-1.5">
                            <p class="text-sm font-medium text-slate-300">الحالة</p>
                            <label class="inline-flex items-center gap-2 text-sm text-slate-300"><input v-model="holidayForm.is_active" type="checkbox" class="rounded border-slate-600 bg-slate-950" />مفعّل</label>
                        </div>
                        <p v-if="holidayForm.errors.start_date" class="text-xs text-red-400">{{ holidayForm.errors.start_date }}</p>
                        <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-600 px-4 py-2 text-sm font-bold text-white hover:bg-cyan-500" :disabled="holidayForm.processing" @click="submitHoliday">
                            <Save class="h-4 w-4" />
                            <span>{{ holidayEditId ? 'تحديث العطلة' : 'إضافة عطلة' }}</span>
                        </button>
                    </div>
                    <div class="mt-4 space-y-2">
                        <div v-for="holiday in holidayTemplates" :key="holiday.id" class="rounded-xl border border-slate-800 bg-slate-950/80 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-white">{{ holiday.name }}</p>
                                        <span class="rounded-full border border-slate-700 bg-slate-900 px-2 py-0.5 text-[10px] font-bold text-slate-300">
                                            {{ holidayCategoryLabel(holiday) }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500">{{ formatDate(holiday.start_date) }} - {{ formatDate(holiday.end_date) }} | العودة: {{ formatDate(holiday.return_date) }} | {{ statusLabel(holiday.is_active) }}</p>
                                    <p v-if="holiday.notes" class="mt-1 text-xs text-slate-400">{{ holiday.notes }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" class="rounded-lg bg-blue-600 px-2 py-1 text-xs font-bold text-white hover:bg-blue-500" @click="editHoliday(holiday)">تعديل</button>
                                    <button type="button" class="rounded-lg bg-red-600 px-2 py-1 text-xs font-bold text-white hover:bg-red-500" @click="destroyWithConfirmation('حذف العطلة', 'سيتم حذف قالب العطلة من المنصة فقط.', route('admin.school_defaults.holidays.destroy', holiday.id))">حذف</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </section>
            <section class="grid gap-4 xl:grid-cols-2">
                <article class="rounded-2xl border border-slate-700/70 bg-slate-900/90 p-5">
                    <h2 class="mb-4 inline-flex items-center gap-2 text-lg font-bold text-white"><Clock3 class="h-4 w-4 text-cyan-300" />أنواع الإجازات</h2>
                    <div class="grid gap-3">
                        <div class="space-y-1.5">
                            <label for="default-leave-type-name" class="block text-sm font-medium text-slate-300">اسم نوع الإجازة</label>
                            <input id="default-leave-type-name" v-model="leaveTypeForm.name" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" placeholder="اسم نوع الإجازة" />
                        </div>
                        <div class="space-y-1.5">
                            <label for="default-leave-type-code" class="block text-sm font-medium text-slate-300">الكود</label>
                            <input id="default-leave-type-code" v-model="leaveTypeForm.code" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" placeholder="الكود - اختياري" />
                        </div>
                        <div class="space-y-1.5">
                            <p class="text-sm font-medium text-slate-300">المرفقات</p>
                            <label class="inline-flex items-center gap-2 text-sm text-slate-300"><input v-model="leaveTypeForm.requires_attachment" type="checkbox" class="rounded border-slate-600 bg-slate-950" />يتطلب مرفقًا</label>
                        </div>
                        <div class="space-y-1.5">
                            <p class="text-sm font-medium text-slate-300">الحالة</p>
                            <label class="inline-flex items-center gap-2 text-sm text-slate-300"><input v-model="leaveTypeForm.is_active" type="checkbox" class="rounded border-slate-600 bg-slate-950" />مفعّل</label>
                        </div>
                        <p v-if="leaveTypeForm.errors.name" class="text-xs text-red-400">{{ leaveTypeForm.errors.name }}</p>
                        <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-600 px-4 py-2 text-sm font-bold text-white hover:bg-cyan-500" :disabled="leaveTypeForm.processing" @click="submitLeaveType">
                            <Save class="h-4 w-4" />
                            <span>{{ leaveTypeEditId ? 'تحديث نوع الإجازة' : 'إضافة نوع إجازة' }}</span>
                        </button>
                    </div>
                    <div class="mt-4 space-y-2">
                        <div v-for="leaveType in leaveTypeTemplates" :key="leaveType.id" class="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-950/80 px-3 py-3">
                            <div>
                                <p class="font-semibold text-white">{{ leaveType.name }}</p>
                                <p class="text-xs text-slate-500">الكود: {{ leaveType.code || '-' }} | {{ leaveType.requires_attachment ? 'يتطلب مرفقًا' : 'بدون مرفق' }} | {{ statusLabel(leaveType.is_active) }}</p>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" class="rounded-lg bg-blue-600 px-2 py-1 text-xs font-bold text-white hover:bg-blue-500" @click="editLeaveType(leaveType)">تعديل</button>
                                <button type="button" class="rounded-lg bg-red-600 px-2 py-1 text-xs font-bold text-white hover:bg-red-500" @click="destroyWithConfirmation('حذف نوع الإجازة', 'سيتم حذف قالب نوع الإجازة من المنصة فقط.', route('admin.school_defaults.leave_types.destroy', leaveType.id))">حذف</button>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-slate-700/70 bg-slate-900/90 p-5">
                    <h2 class="mb-4 inline-flex items-center gap-2 text-lg font-bold text-white"><BookOpenText class="h-4 w-4 text-cyan-300" />المواد التعليمية</h2>
                    <div class="grid gap-3">
                        <div class="space-y-1.5">
                            <label for="default-subject-name" class="block text-sm font-medium text-slate-300">اسم المادة</label>
                            <input id="default-subject-name" v-model="subjectForm.name" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" placeholder="اسم المادة" />
                        </div>
                        <div class="space-y-1.5">
                            <label for="default-subject-code" class="block text-sm font-medium text-slate-300">الكود</label>
                            <input id="default-subject-code" v-model="subjectForm.code" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" placeholder="الكود - اختياري" />
                        </div>
                        <div class="space-y-1.5">
                            <label for="default-subject-branches" class="block text-sm font-medium text-slate-300">الفروع أو المسارات</label>
                            <textarea id="default-subject-branches" v-model="subjectBranchesText" rows="3" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white" placeholder="الفروع أو المسارات، مفصولة بفاصلة أو سطر جديد" />
                        </div>
                        <div class="space-y-1.5">
                            <p class="text-sm font-medium text-slate-300">الحالة</p>
                            <label class="inline-flex items-center gap-2 text-sm text-slate-300"><input v-model="subjectForm.is_active" type="checkbox" class="rounded border-slate-600 bg-slate-950" />مفعّل</label>
                        </div>
                        <p v-if="subjectForm.errors.name" class="text-xs text-red-400">{{ subjectForm.errors.name }}</p>
                        <p v-if="subjectForm.errors.code" class="text-xs text-red-400">{{ subjectForm.errors.code }}</p>
                        <button type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-600 px-4 py-2 text-sm font-bold text-white hover:bg-cyan-500" :disabled="subjectForm.processing" @click="submitSubject">
                            <Save class="h-4 w-4" />
                            <span>{{ subjectEditId ? 'تحديث المادة' : 'إضافة مادة' }}</span>
                        </button>
                    </div>
                    <div class="mt-4 space-y-2">
                        <div v-for="subject in subjectTemplates" :key="subject.id" class="rounded-xl border border-slate-800 bg-slate-950/80 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-white">{{ subject.name }}</p>
                                    <p class="text-xs text-slate-500">الكود: {{ subject.code || '-' }} | {{ statusLabel(subject.is_active) }}</p>
                                    <p class="mt-1 text-xs text-slate-400">الفروع: {{ Array.isArray(subject.branches) && subject.branches.length ? subject.branches.join('، ') : 'الفرع الرئيسي فقط' }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" class="rounded-lg bg-blue-600 px-2 py-1 text-xs font-bold text-white hover:bg-blue-500" @click="editSubject(subject)">تعديل</button>
                                    <button type="button" class="rounded-lg bg-red-600 px-2 py-1 text-xs font-bold text-white hover:bg-red-500" @click="destroyWithConfirmation('حذف المادة', 'سيتم حذف قالب المادة من المنصة فقط.', route('admin.school_defaults.subjects.destroy', subject.id))">حذف</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </section>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </teleport>

            <section v-if="props.embedded && !editorSurfaceVisible" class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/70 p-6 text-right">
                <AppStatePanel
                    title="ابدأ بحفظ القالب أولًا"
                    description="بعد حفظ القالب الجديد سيصبح بإمكانك فتحه لاحقًا من قائمة القوالب المحفوظة عبر زر تحرير القالب داخل نافذة مستقلة مخصصة للتحرير."
                />
            </section>
        </div>

        <SchoolDefaultScopeSettingsModal
            :open="scopeSettingsOpen"
            :loading="scopeSettingsLoading"
            :scope="currentTemplateScope"
            :country-name="selectedCountry?.name || ''"
            :education-type-name="selectedEducationType?.name || ''"
            :inventory-cards="templateInventoryCards"
            :reference-summary="activeScopeReferenceSummary"
            :reference-supported-labels="activeScopeReferenceSupportedLabels"
            :reference-unavailable-labels="activeScopeReferenceUnavailableLabels"
            :data-sections="scopeSettingsDataSections"
            @close="closeScopeSettings"
            @open-editor="openScopeEditorFromSettings"
        />
    </component>
</template>

<style>
html.theme-light .school-defaults-page .school-defaults-hero,
html.theme-light .school-defaults-modal-panel .school-defaults-hero {
    border-color: rgba(8, 145, 178, 0.22);
    background:
        radial-gradient(circle at top right, rgba(34, 211, 238, 0.16), transparent 32%),
        linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(241, 245, 249, 0.96));
    box-shadow: 0 30px 90px -58px rgba(14, 116, 144, 0.28);
}

html.theme-light .school-defaults-modal-shell {
    background: rgba(241, 245, 249, 0.76);
}

html.theme-light .school-defaults-modal-panel {
    border-color: rgba(148, 163, 184, 0.28) !important;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.99), rgba(248, 250, 252, 0.97)) !important;
    box-shadow: 0 34px 80px -52px rgba(15, 23, 42, 0.28);
}

html.theme-light .school-defaults-modal-body {
    background: linear-gradient(180deg, rgba(250, 252, 255, 0.98), rgba(244, 247, 251, 0.96)) !important;
}

html.theme-light .school-defaults-page [class*='bg-slate-900/95'],
html.theme-light .school-defaults-page [class*='bg-slate-900/90'],
html.theme-light .school-defaults-page [class*='bg-slate-900/80'],
html.theme-light .school-defaults-page [class*='bg-slate-900/70'],
html.theme-light .school-defaults-page [class*='bg-slate-950/80'],
html.theme-light .school-defaults-page [class*='bg-slate-950/70'],
html.theme-light .school-defaults-page [class*='bg-slate-950/60'],
html.theme-light .school-defaults-page [class*='bg-slate-950 '],
html.theme-light .school-defaults-page [class$='bg-slate-950'],
html.theme-light .school-defaults-modal-panel [class*='bg-slate-900/95'],
html.theme-light .school-defaults-modal-panel [class*='bg-slate-900/90'],
html.theme-light .school-defaults-modal-panel [class*='bg-slate-900/80'],
html.theme-light .school-defaults-modal-panel [class*='bg-slate-900/70'],
html.theme-light .school-defaults-modal-panel [class*='bg-slate-950/80'],
html.theme-light .school-defaults-modal-panel [class*='bg-slate-950/70'],
html.theme-light .school-defaults-modal-panel [class*='bg-slate-950/60'],
html.theme-light .school-defaults-modal-panel [class*='bg-slate-950 '],
html.theme-light .school-defaults-modal-panel [class$='bg-slate-950'] {
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.97), rgba(248, 250, 252, 0.94)) !important;
    box-shadow: 0 18px 48px -42px rgba(15, 23, 42, 0.16);
}

html.theme-light .school-defaults-page [class*='border-slate-700'],
html.theme-light .school-defaults-page [class*='border-slate-800'],
html.theme-light .school-defaults-modal-panel [class*='border-slate-700'],
html.theme-light .school-defaults-modal-panel [class*='border-slate-800'] {
    border-color: rgba(203, 213, 225, 0.82) !important;
}

html.theme-light .school-defaults-page .text-white,
html.theme-light .school-defaults-modal-panel .text-white {
    color: #0f172a !important;
}

html.theme-light .school-defaults-page :is(button, a, span, div)[class~='bg-slate-700'],
html.theme-light .school-defaults-page :is(button, a, span, div)[class~='bg-slate-800'],
html.theme-light .school-defaults-page :is(button, a, span, div)[class~='bg-slate-900'],
html.theme-light .school-defaults-page :is(button, a, span, div)[class~='bg-slate-950'],
html.theme-light .school-defaults-page :is(button, a, span, div)[class~='bg-blue-500'],
html.theme-light .school-defaults-page :is(button, a, span, div)[class~='bg-blue-600'],
html.theme-light .school-defaults-page :is(button, a, span, div)[class~='bg-blue-700'],
html.theme-light .school-defaults-page :is(button, a, span, div)[class~='bg-cyan-500'],
html.theme-light .school-defaults-page :is(button, a, span, div)[class~='bg-cyan-600'],
html.theme-light .school-defaults-page :is(button, a, span, div)[class~='bg-cyan-700'],
html.theme-light .school-defaults-page :is(button, a, span, div)[class~='bg-red-500'],
html.theme-light .school-defaults-page :is(button, a, span, div)[class~='bg-red-600'],
html.theme-light .school-defaults-page :is(button, a, span, div)[class~='bg-red-700'],
html.theme-light .school-defaults-page :is(button, a, span, div)[class~='bg-emerald-500'],
html.theme-light .school-defaults-page :is(button, a, span, div)[class~='bg-emerald-600'],
html.theme-light .school-defaults-page :is(button, a, span, div)[class~='bg-emerald-700'],
html.theme-light .school-defaults-page :is(button, a, span, div)[class~='bg-indigo-500'],
html.theme-light .school-defaults-page :is(button, a, span, div)[class~='bg-indigo-600'],
html.theme-light .school-defaults-page :is(button, a, span, div)[class~='bg-indigo-700'],
html.theme-light .school-defaults-modal-panel :is(button, a, span, div)[class~='bg-slate-700'],
html.theme-light .school-defaults-modal-panel :is(button, a, span, div)[class~='bg-slate-800'],
html.theme-light .school-defaults-modal-panel :is(button, a, span, div)[class~='bg-slate-900'],
html.theme-light .school-defaults-modal-panel :is(button, a, span, div)[class~='bg-slate-950'],
html.theme-light .school-defaults-modal-panel :is(button, a, span, div)[class~='bg-blue-500'],
html.theme-light .school-defaults-modal-panel :is(button, a, span, div)[class~='bg-blue-600'],
html.theme-light .school-defaults-modal-panel :is(button, a, span, div)[class~='bg-blue-700'],
html.theme-light .school-defaults-modal-panel :is(button, a, span, div)[class~='bg-cyan-500'],
html.theme-light .school-defaults-modal-panel :is(button, a, span, div)[class~='bg-cyan-600'],
html.theme-light .school-defaults-modal-panel :is(button, a, span, div)[class~='bg-cyan-700'],
html.theme-light .school-defaults-modal-panel :is(button, a, span, div)[class~='bg-red-500'],
html.theme-light .school-defaults-modal-panel :is(button, a, span, div)[class~='bg-red-600'],
html.theme-light .school-defaults-modal-panel :is(button, a, span, div)[class~='bg-red-700'],
html.theme-light .school-defaults-modal-panel :is(button, a, span, div)[class~='bg-emerald-500'],
html.theme-light .school-defaults-modal-panel :is(button, a, span, div)[class~='bg-emerald-600'],
html.theme-light .school-defaults-modal-panel :is(button, a, span, div)[class~='bg-emerald-700'],
html.theme-light .school-defaults-modal-panel :is(button, a, span, div)[class~='bg-indigo-500'],
html.theme-light .school-defaults-modal-panel :is(button, a, span, div)[class~='bg-indigo-600'],
html.theme-light .school-defaults-modal-panel :is(button, a, span, div)[class~='bg-indigo-700'] {
    color: #ffffff !important;
}

html.theme-light .school-defaults-page .text-slate-300,
html.theme-light .school-defaults-page .text-slate-400,
html.theme-light .school-defaults-modal-panel .text-slate-300,
html.theme-light .school-defaults-modal-panel .text-slate-400 {
    color: #475569 !important;
}

html.theme-light .school-defaults-page .text-slate-500,
html.theme-light .school-defaults-modal-panel .text-slate-500 {
    color: #64748b !important;
}

html.theme-light .school-defaults-page .text-cyan-200,
html.theme-light .school-defaults-page .text-cyan-300,
html.theme-light .school-defaults-modal-panel .text-cyan-200,
html.theme-light .school-defaults-modal-panel .text-cyan-300 {
    color: #0f766e !important;
}

html.theme-light .school-defaults-page [class*='bg-cyan-500/10'],
html.theme-light .school-defaults-page [class*='bg-cyan-500/15'],
html.theme-light .school-defaults-page [class*='bg-cyan-500/20'],
html.theme-light .school-defaults-modal-panel [class*='bg-cyan-500/10'],
html.theme-light .school-defaults-modal-panel [class*='bg-cyan-500/15'],
html.theme-light .school-defaults-modal-panel [class*='bg-cyan-500/20'] {
    color: #0f172a !important;
}

html.theme-light .school-defaults-page [class*='bg-cyan-500/10'] :is(.text-cyan-100, .text-cyan-200, .text-cyan-300, [class*='text-cyan-300/']),
html.theme-light .school-defaults-page [class*='bg-cyan-500/15'] :is(.text-cyan-100, .text-cyan-200, .text-cyan-300, [class*='text-cyan-300/']),
html.theme-light .school-defaults-page [class*='bg-cyan-500/20'] :is(.text-cyan-100, .text-cyan-200, .text-cyan-300, [class*='text-cyan-300/']),
html.theme-light .school-defaults-modal-panel [class*='bg-cyan-500/10'] :is(.text-cyan-100, .text-cyan-200, .text-cyan-300, [class*='text-cyan-300/']),
html.theme-light .school-defaults-modal-panel [class*='bg-cyan-500/15'] :is(.text-cyan-100, .text-cyan-200, .text-cyan-300, [class*='text-cyan-300/']),
html.theme-light .school-defaults-modal-panel [class*='bg-cyan-500/20'] :is(.text-cyan-100, .text-cyan-200, .text-cyan-300, [class*='text-cyan-300/']) {
    color: #0f172a !important;
}

html.theme-light .school-defaults-page [class*='text-emerald-200'],
html.theme-light .school-defaults-page [class*='text-emerald-50/90'],
html.theme-light .school-defaults-modal-panel [class*='text-emerald-200'],
html.theme-light .school-defaults-modal-panel [class*='text-emerald-50/90'] {
    color: #047857 !important;
}

html.theme-light .school-defaults-page input:not([type='checkbox']):not([type='radio']),
html.theme-light .school-defaults-page select,
html.theme-light .school-defaults-page textarea,
html.theme-light .school-defaults-modal-panel input:not([type='checkbox']):not([type='radio']),
html.theme-light .school-defaults-modal-panel select,
html.theme-light .school-defaults-modal-panel textarea {
    border-color: rgba(148, 163, 184, 0.42) !important;
    background: rgba(255, 255, 255, 0.98) !important;
    color: #0f172a !important;
    box-shadow: inset 0 1px 2px rgba(148, 163, 184, 0.1);
}

html.theme-light .school-defaults-page input:not([type='checkbox']):not([type='radio'])::placeholder,
html.theme-light .school-defaults-page textarea::placeholder,
html.theme-light .school-defaults-modal-panel input:not([type='checkbox']):not([type='radio'])::placeholder,
html.theme-light .school-defaults-modal-panel textarea::placeholder {
    color: #94a3b8;
}

html.theme-light .school-defaults-page input[type='checkbox'],
html.theme-light .school-defaults-modal-panel input[type='checkbox'] {
    border-color: rgba(148, 163, 184, 0.58);
    background: #ffffff;
    accent-color: #0f766e;
}

html.theme-light .school-defaults-page button[class*='border-slate-600'],
html.theme-light .school-defaults-page button[class*='border-slate-700'],
html.theme-light .school-defaults-modal-panel button[class*='border-slate-600'],
html.theme-light .school-defaults-modal-panel button[class*='border-slate-700'] {
    border-color: rgba(148, 163, 184, 0.4) !important;
    background: rgba(255, 255, 255, 0.94) !important;
    color: #334155 !important;
}

html.theme-light .school-defaults-page button[class*='hover:bg-slate-800']:hover,
html.theme-light .school-defaults-page button[class*='hover:bg-slate-950']:hover,
html.theme-light .school-defaults-modal-panel button[class*='hover:bg-slate-800']:hover,
html.theme-light .school-defaults-modal-panel button[class*='hover:bg-slate-950']:hover {
    background: rgba(241, 245, 249, 0.98) !important;
}

html.theme-light .school-defaults-page [class*='border-dashed'],
html.theme-light .school-defaults-modal-panel [class*='border-dashed'] {
    border-color: rgba(148, 163, 184, 0.42) !important;
    background: rgba(248, 250, 252, 0.9) !important;
}

html.theme-light .school-defaults-page .school-defaults-reference-panel,
html.theme-light .school-defaults-page .school-defaults-reference-preview,
html.theme-light .school-defaults-page .school-defaults-reference-sidebar,
html.theme-light .school-defaults-modal-panel .school-defaults-reference-panel,
html.theme-light .school-defaults-modal-panel .school-defaults-reference-preview,
html.theme-light .school-defaults-modal-panel .school-defaults-reference-sidebar {
    border-color: rgba(148, 163, 184, 0.34) !important;
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(243, 247, 251, 0.96)) !important;
    box-shadow: 0 18px 44px -40px rgba(15, 23, 42, 0.14);
}

html.theme-light .school-defaults-page .school-defaults-setup-badge,
html.theme-light .school-defaults-modal-panel .school-defaults-setup-badge {
    border: 1px solid rgba(148, 163, 184, 0.26);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
}

html.theme-light .school-defaults-page .school-defaults-setup-badge[class*='bg-slate-800'],
html.theme-light .school-defaults-page .school-defaults-setup-badge[class*='bg-slate-900'],
html.theme-light .school-defaults-modal-panel .school-defaults-setup-badge[class*='bg-slate-800'],
html.theme-light .school-defaults-modal-panel .school-defaults-setup-badge[class*='bg-slate-900'] {
    background: rgba(226, 232, 240, 0.94) !important;
    color: #334155 !important;
    border-color: rgba(148, 163, 184, 0.38) !important;
}

html.theme-light .school-defaults-page .school-defaults-setup-badge[class*='bg-cyan-500'],
html.theme-light .school-defaults-modal-panel .school-defaults-setup-badge[class*='bg-cyan-500'] {
    background: rgba(34, 211, 238, 0.16) !important;
    color: #0f766e !important;
    border-color: rgba(8, 145, 178, 0.25) !important;
}

html.theme-light .school-defaults-page .school-defaults-setup-badge[class*='bg-emerald-500'],
html.theme-light .school-defaults-modal-panel .school-defaults-setup-badge[class*='bg-emerald-500'] {
    background: rgba(16, 185, 129, 0.15) !important;
    color: #047857 !important;
    border-color: rgba(5, 150, 105, 0.24) !important;
}

html.theme-light .school-defaults-page .school-defaults-setup-badge[class*='bg-amber-500'],
html.theme-light .school-defaults-modal-panel .school-defaults-setup-badge[class*='bg-amber-500'] {
    background: rgba(245, 158, 11, 0.16) !important;
    color: #b45309 !important;
    border-color: rgba(217, 119, 6, 0.24) !important;
}

html.theme-light .school-defaults-page .school-defaults-setup-badge[class*='bg-red-500'],
html.theme-light .school-defaults-modal-panel .school-defaults-setup-badge[class*='bg-red-500'] {
    background: rgba(239, 68, 68, 0.14) !important;
    color: #b91c1c !important;
    border-color: rgba(220, 38, 38, 0.22) !important;
}

html.theme-light .school-defaults-page .school-defaults-count-pill,
html.theme-light .school-defaults-modal-panel .school-defaults-count-pill {
    background: linear-gradient(180deg, rgba(236, 253, 245, 0.98), rgba(220, 252, 231, 0.94)) !important;
    color: #0f766e !important;
    border: 1px solid rgba(110, 231, 183, 0.55);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.86);
}

html.theme-light .school-defaults-page .school-defaults-type-chip,
html.theme-light .school-defaults-modal-panel .school-defaults-type-chip {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.99), rgba(248, 250, 252, 0.96)) !important;
    color: #334155 !important;
    border-color: rgba(148, 163, 184, 0.42) !important;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.82);
}

html.theme-light .school-defaults-page .school-defaults-type-chip:hover,
html.theme-light .school-defaults-modal-panel .school-defaults-type-chip:hover {
    background: rgba(241, 245, 249, 0.98) !important;
}

html.theme-light .school-defaults-page .school-defaults-modal-body,
html.theme-light .school-defaults-page .school-defaults-modal-panel {
    background:
        linear-gradient(180deg, rgba(250, 252, 255, 0.98), rgba(244, 247, 251, 0.96)) !important;
}
</style>
