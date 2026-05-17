<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    BookOpenText,
    CalendarDays,
    ChevronDown,
    ChevronLeft,
    Clock3,
    FileSpreadsheet,
    Filter,
    LayoutTemplate,
    Pencil,
    PlusCircle,
    Save,
    School,
    Search,
    Settings2,
    Trash2,
    UserRound,
    Users,
    X,
} from 'lucide-vue-next';
import AttachmentPanel from '@/Components/AttachmentPanel.vue';
import RoleLayout from '@/Layouts/RoleLayout.vue';
import QuickSetupWizardModal from '@/Components/School/QuickSetupWizardModal.vue';
import { stageAccentStyle } from '@/utils/stagePalette';
import { useActionDialog } from '@/composables/useActionDialog';
import {
    defaultDataProvisioningCountItems,
    defaultDataProvisioningSummaryText,
} from '@/utils/defaultDataProvisioning';

axios.defaults.headers.common['Accept-Language'] = 'ar';

const props = defineProps({
    school: {
        type: Object,
        default: null,
    },
    academicYears: {
        type: Array,
        default: () => [],
    },
    terms: {
        type: Array,
        default: () => [],
    },
    stages: {
        type: Array,
        default: () => [],
    },
    structureStages: {
        type: Array,
        default: () => [],
    },
    teachers: {
        type: Array,
        default: () => [],
    },
    teacherAvailabilities: {
        type: Object,
        default: () => ({}),
    },
    subjects: {
        type: Array,
        default: () => [],
    },
    leaveTypes: {
        type: Array,
        default: () => [],
    },
    calendarSettings: {
        type: Object,
        default: null,
    },
    holidays: {
        type: Array,
        default: () => [],
    },
    courseOfferings: {
        type: Array,
        default: () => [],
    },
    approvedCoursesTree: {
        type: Array,
        default: () => [],
    },
    courseAssignmentsTree: {
        type: Array,
        default: () => [],
    },
    timetableVersions: {
        type: Array,
        default: () => [],
    },
    schedules: {
        type: Array,
        default: () => [],
    },
    selectedTermId: {
        type: [Number, String, null],
        default: null,
    },
    selectedScope: {
        type: String,
        default: '',
    },
    selectedStageId: {
        type: [Number, String, null],
        default: null,
    },
    selectedGradeName: {
        type: [String, null],
        default: null,
    },
    selectedClassroomId: {
        type: [Number, String, null],
        default: null,
    },
    selectedVersionId: {
        type: [Number, String, null],
        default: null,
    },
    weeklyGrid: {
        type: Object,
        default: () => ({ entries: [] }),
    },
    selectedPage: {
        type: String,
        default: 'stages',
    },
    scopeOptions: {
        type: Array,
        default: () => [],
    },
    weekDays: {
        type: Array,
        default: () => [],
    },
    isManager: {
        type: Boolean,
        default: false,
    },
    defaultDataProvisioning: {
        type: Object,
        default: null,
    },
    permissions: {
        type: Object,
        default: () => ({}),
    },
    scheduleRules: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const actionDialog = useActionDialog();
const currentUser = computed(() => page.props.auth?.user || null);
const roleForLayout = computed(() => {
    if (props.isManager) return 'SCHOOL_MANAGER';
    return currentUser.value?.primary_role === 'school_manager' ? 'SCHOOL_MANAGER' : 'STAFF';
});
const defaultDataImportForm = useForm({});
const hasDefaultDataTemplates = computed(() => Boolean(props.defaultDataProvisioning?.has_any_templates));
const isDefaultDataImported = computed(() => Boolean(props.defaultDataProvisioning?.is_imported));
const canImportDefaultData = computed(() => Boolean(props.defaultDataProvisioning?.can_import));
const defaultDataImportedBy = computed(() => String(props.defaultDataProvisioning?.imported_by?.name || '').trim());
const defaultDataTemplateCountItems = computed(() =>
    defaultDataProvisioningCountItems(props.defaultDataProvisioning?.available_counts || {})
);
const defaultDataTemplateSummaryText = computed(() =>
    defaultDataProvisioningSummaryText(props.defaultDataProvisioning?.available_counts || {})
);
const defaultDataImportButtonLabel = computed(() => {
    if (defaultDataImportForm.processing) {
        return isDefaultDataImported.value ? 'جاري استكمال الاستيراد...' : 'جاري الاستيراد...';
    }

    return isDefaultDataImported.value ? 'استيراد العناصر الجديدة' : 'استيراد القوالب العامة';
});
const formatProvisioningDate = (value) => {
    if (!value) return '';

    try {
        return new Intl.DateTimeFormat('ar-EG', {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(value));
    } catch (_error) {
        return String(value);
    }
};

const importDefaultData = async () => {
    if (!canImportDefaultData.value || defaultDataImportForm.processing) return;

    const confirmed = await actionDialog.confirm({
        title: 'استيراد البيانات الافتراضية',
        message: isDefaultDataImported.value
            ? 'سيتم فحص القالب المطابق الحالي ونسخ العناصر الجديدة المفقودة فقط إلى هذه المدرسة، دون تعديل البيانات الموجودة فيها حاليًا.'
            : 'سيتم نسخ القوالب العامة الحالية إلى هذه المدرسة مرة واحدة، ثم تصبح البيانات داخل المدرسة مستقلة وقابلة للتخصيص دون التأثير على المنصة أو المدارس الأخرى.',
        confirmText: isDefaultDataImported.value ? 'استكمال الاستيراد' : 'بدء الاستيراد',
        cancelText: 'إلغاء',
        variant: 'warning',
    });

    if (!confirmed) return;

    defaultDataImportForm.post(route('school.default_data.import'), {
        preserveScroll: true,
    });
};

const canManageLeaves = computed(() => Boolean(props.permissions?.can_manage_student_leaves || props.isManager));
const canManageLeaveTypes = computed(() => Boolean(props.permissions?.can_manage_leave_types || canManageLeaves.value || props.isManager));
const canManageCalendar = computed(() => Boolean(props.permissions?.can_manage_school_calendar || props.isManager));
const canManageHolidays = computed(() => Boolean(props.permissions?.can_manage_school_holidays || canManageLeaves.value || props.isManager));

const fieldLabels = {
    name: 'الاسم',
    code: 'الكود',
    week_start_day: 'بداية الأسبوع',
    weekly_off_days: 'أيام الإجازة الأسبوعية',
    days_count: 'عدد الأيام',
    start_date: 'تاريخ البداية',
    end_date: 'تاريخ النهاية',
    return_date: 'تاريخ العودة',
    notes: 'الملاحظات',
    school_day_start_time: 'بداية اليوم الدراسي',
    school_day_end_time: 'نهاية اليوم الدراسي',
};

const toReadableFieldLabel = (field) => fieldLabels[field] || field;

const normalizeApiMessage = (message, field = '') => {
    if (!message || typeof message !== 'string') {
        return '';
    }

    const msg = message.trim();
    const label = toReadableFieldLabel(field);

    const required = msg.match(/^The (.+?) field is required\.$/i);
    if (required) return `حقل ${toReadableFieldLabel(required[1])} مطلوب.`;

    const unique = msg.match(/^The (.+?) has already been taken\.$/i);
    if (unique) return `قيمة ${toReadableFieldLabel(unique[1])} مستخدمة مسبقًا.`;

    const date = msg.match(/^The (.+?) is not a valid date\.$/i);
    if (date) return `حقل ${toReadableFieldLabel(date[1])} يجب أن يكون تاريخًا صحيحًا.`;

    const afterOrEqual = msg.match(/^The (.+?) field must be a date after or equal to (.+)\.$/i);
    if (afterOrEqual) return `حقل ${toReadableFieldLabel(afterOrEqual[1])} يجب أن يكون بعد أو مساويًا لـ ${toReadableFieldLabel(afterOrEqual[2])}.`;

    const beforeOrEqual = msg.match(/^The (.+?) field must be a date before or equal to (.+)\.$/i);
    if (beforeOrEqual) return `حقل ${toReadableFieldLabel(beforeOrEqual[1])} يجب أن يكون قبل أو مساويًا لـ ${toReadableFieldLabel(beforeOrEqual[2])}.`;

    const integer = msg.match(/^The (.+?) field must be an integer\.$/i);
    if (integer) return `حقل ${toReadableFieldLabel(integer[1])} يجب أن يكون رقمًا صحيحًا.`;

    const numeric = msg.match(/^The (.+?) field must be a number\.$/i);
    if (numeric) return `حقل ${toReadableFieldLabel(numeric[1])} يجب أن يكون رقمًا.`;

    const min = msg.match(/^The (.+?) field must be at least (.+)\.$/i);
    if (min) return `حقل ${toReadableFieldLabel(min[1])} يجب ألا يقل عن ${min[2]}.`;

    const boolean = msg.match(/^The (.+?) field must be true or false\.$/i);
    if (boolean) return `حقل ${toReadableFieldLabel(boolean[1])} يجب أن يكون صحيحًا أو خطأ.`;

    const inList = msg.match(/^The selected (.+?) is invalid\.$/i);
    if (inList) return `القيمة المختارة في حقل ${toReadableFieldLabel(inList[1])} غير صالحة.`;

    if (msg === 'Unauthorized.' || msg === 'This action is unauthorized.') {
        return 'ليس لديك صلاحية لتنفيذ هذا الإجراء.';
    }

    if (msg === 'Forbidden.') {
        return 'ليس لديك صلاحية للوصول إلى هذا المحتوى.';
    }

    return label && msg.includes(field) ? msg.replace(field, label) : msg;
};

const extractValidationErrors = (error) => {
    const errors = error?.response?.data?.errors;
    if (!errors || typeof errors !== 'object') {
        return {};
    }

    const bag = {};
    Object.entries(errors).forEach(([field, messages]) => {
        const first = Array.isArray(messages) ? messages[0] : messages;
        bag[field] = normalizeApiMessage(String(first || ''), field);
    });
    return bag;
};

const resolveApiErrorMessage = (error, fallbackMessage, preferredFields = []) => {
    const validation = extractValidationErrors(error);

    for (const field of preferredFields) {
        if (validation[field]) {
            return validation[field];
        }
    }

    const firstValidation = Object.values(validation)[0];
    if (firstValidation) {
        return firstValidation;
    }

    const direct = normalizeApiMessage(error?.response?.data?.message || '');
    if (direct) {
        return direct;
    }

    if (error?.response?.status === 403) {
        return 'ليس لديك صلاحية لتنفيذ هذا الإجراء.';
    }

    if (error?.response?.status === 404) {
        return 'العنصر المطلوب غير موجود.';
    }

    return fallbackMessage;
};

const actionFeedback = ref({
    type: '',
    message: '',
    visible: false,
});

let actionFeedbackTimer = null;

const clearActionFeedbackTimer = () => {
    if (actionFeedbackTimer) {
        clearTimeout(actionFeedbackTimer);
        actionFeedbackTimer = null;
    }
};

const showActionFeedback = (type, message) => {
    if (!message) return;

    clearActionFeedbackTimer();
    actionFeedback.value = {
        type,
        message,
        visible: true,
    };

    actionFeedbackTimer = setTimeout(() => {
        actionFeedback.value.visible = false;
        actionFeedbackTimer = null;
    }, 4000);
};

const showCrudSuccess = (message) => showActionFeedback('success', message);
const showCrudError = (message = 'تعذر تنفيذ العملية. يرجى المحاولة مرة أخرى.') => showActionFeedback('error', message);
const stageAccent = (stageId, stageName = '') => stageAccentStyle(stageId, stageName);

onBeforeUnmount(() => {
    clearActionFeedbackTimer();
});

const impactMessageByCode = {
    INTEGRITY_LEAVE_TYPE_DISABLE_WARNING_PENDING_REQUESTS: 'تعطيل نوع الإجازة سيؤثر على طلبات معلقة. هل تريد المتابعة؟',
    INTEGRITY_HOLIDAY_DISABLE_WARNING_ATTENDANCE_IMPACT: 'تعطيل العطلة سيؤثر على تقارير الحضور ضمن نفس الفترة. هل تريد المتابعة؟',
    INTEGRITY_HOLIDAY_UPDATE_WARNING_ATTENDANCE_IMPACT: 'تعديل العطلة سيؤثر على سجلات حضور موجودة. هل تريد المتابعة؟',
};

const isImpactConfirmationRequired = (payload) => Boolean(payload?.impact?.requires_confirmation || payload?.requires_confirmation);

const getImpactMessage = (payload, fallback) => {
    const messageCode = payload?.impact?.message_code || payload?.message_code || '';
    if (messageCode && impactMessageByCode[messageCode]) {
        return impactMessageByCode[messageCode];
    }

    const message = payload?.impact?.message || payload?.message || '';
    if (typeof message === 'string' && message.trim() !== '') {
        return message;
    }

    return fallback;
};

const confirmAction = (message, options = {}) => actionDialog.confirm({
    title: options.title || 'تأكيد الإجراء',
    message,
    confirmText: options.confirmText || 'متابعة',
    cancelText: options.cancelText || 'إلغاء',
    variant: options.variant || 'warning',
});

const normalizeGradeName = (value) => {
    const normalized = String(value || '').trim();
    return normalized !== '' ? normalized : 'غير محدد';
};

const safeArray = (value) => (Array.isArray(value) ? value : []);
const safeText = (value, fallback = 'غير محدد') => {
    const normalized = String(value ?? '').trim();
    return normalized !== '' ? normalized : fallback;
};

const stageOptions = computed(() =>
    safeArray(props.stages).map((stage) => ({
        id: stage.id,
        name: stage.name,
        grades: (stage.grades || [])
            .filter((grade) => Boolean(grade.is_active))
            .map((grade) => ({
                ...grade,
                name: normalizeGradeName(grade.name),
            })),
        classrooms: (stage.classrooms || []).map((classroom) => ({
            ...classroom,
            grade_name: normalizeGradeName(classroom.grade_name),
        })),
    }))
);

const classroomOptions = computed(() =>
    stageOptions.value.flatMap((stage) =>
        (stage.classrooms || []).map((classroom) => ({
            id: classroom.id,
            name: classroom.name,
            grade_name: normalizeGradeName(classroom.grade_name),
            school_stage_id: stage.id,
            stage_name: stage.name,
        }))
    )
);

const gradeFilterOptions = computed(() =>
    [...new Set(classroomOptions.value.map((classroom) => normalizeGradeName(classroom.grade_name)))]
);

const structureStageOptions = computed(() =>
    safeArray(props.structureStages).map((stage) => ({
        ...stage,
        grades: (stage.grades || []).map((grade) => ({
            ...grade,
            name: normalizeGradeName(grade.name),
        })),
        classrooms: (stage.classrooms || []).map((classroom) => ({
            ...classroom,
            grade_name: normalizeGradeName(classroom.grade_name),
        })),
    }))
);

const structureClassroomOptions = computed(() =>
    structureStageOptions.value.flatMap((stage) =>
        (stage.classrooms || []).map((classroom) => ({
            ...classroom,
            grade_name: normalizeGradeName(classroom.grade_name),
            stage_name: stage.name,
            school_stage_id: stage.id,
        }))
    )
);

const defaultStructureStageId = computed(() => structureStageOptions.value[0]?.id || '');

const gradeOptionsForStructureStage = (stageId) => {
    const stage = structureStageOptions.value.find((item) => Number(item.id) === Number(stageId));
    if (!stage) return [];

    const configured = (stage.grades || [])
        .map((grade) => normalizeGradeName(grade.name));

    if (configured.length > 0) {
        return [...new Set(configured)];
    }

    const fromClassrooms = (stage.classrooms || []).map((classroom) => normalizeGradeName(classroom.grade_name));
    return [...new Set(fromClassrooms)];
};

const stageCountById = computed(() => {
    const map = new Map();
    for (const stage of props.structureStages) {
        const classrooms = stage.classrooms || [];
        const studentsCount = classrooms.reduce((sum, classroom) => sum + Number(classroom.students_count || 0), 0);
        map.set(Number(stage.id), {
            classroomsCount: classrooms.length,
            studentsCount,
        });
    }
    return map;
});

const canManagePlanning = computed(() => Boolean(props.permissions?.can_manage_academic_planning || props.isManager));
const canManageTeachingAssignments = computed(() => Boolean(props.permissions?.can_manage_teaching_assignments || props.isManager));
const PLANNING_PAGE_STAGES = 'stages';
const PLANNING_PAGE_YEARS = 'years';
const PLANNING_PAGE_TERMS = 'terms';
const PLANNING_PAGE_CALENDAR = 'calendar';
const PLANNING_PAGE_SUBJECTS = 'subjects';
const PLANNING_PAGE_SCHEDULES = 'schedules';
const PLANNING_PAGE_CLASSROOMS = 'classrooms';

const planningPageLabels = Object.freeze({
    [PLANNING_PAGE_STAGES]: 'المراحل الدراسية',
    [PLANNING_PAGE_YEARS]: 'العام الدراسي',
    [PLANNING_PAGE_TERMS]: 'الفصل الدراسي',
    [PLANNING_PAGE_CALENDAR]: 'إعدادات التقويم المدرسي',
    [PLANNING_PAGE_SUBJECTS]: 'المواد التعليمية',
    [PLANNING_PAGE_SCHEDULES]: 'الجداول الدراسية',
    [PLANNING_PAGE_CLASSROOMS]: 'الفصول التعليمية',
});

const planningPageSections = Object.freeze({
    [PLANNING_PAGE_STAGES]: ['stages'],
    [PLANNING_PAGE_YEARS]: ['years'],
    [PLANNING_PAGE_TERMS]: ['terms'],
    [PLANNING_PAGE_CALENDAR]: ['calendar'],
    [PLANNING_PAGE_SUBJECTS]: ['subjects'],
    [PLANNING_PAGE_SCHEDULES]: ['schedules'],
    [PLANNING_PAGE_CLASSROOMS]: ['classrooms'],
});

const currentPlanningPage = computed(() => {
    const requestedFromProps = String(props.selectedPage || '').trim();
    if (Object.prototype.hasOwnProperty.call(planningPageLabels, requestedFromProps)) {
        return requestedFromProps;
    }

    const pageUrl = String(page.url || '');
    const query = pageUrl.includes('?') ? pageUrl.split('?')[1] : '';
    const requestedPage = String(new URLSearchParams(query).get('page') || '').trim();

    if (Object.prototype.hasOwnProperty.call(planningPageLabels, requestedPage)) {
        return requestedPage;
    }

    return PLANNING_PAGE_STAGES;
});

const currentPlanningPageLabel = computed(() => planningPageLabels[currentPlanningPage.value] || 'الهيكل الدراسي');
const isSectionVisible = (sectionKey) => (planningPageSections[currentPlanningPage.value] || []).includes(sectionKey);

const withPlanningPage = (url) => {
    const pageKey = String(currentPlanningPage.value || '').trim();
    if (pageKey === '' || pageKey === PLANNING_PAGE_STAGES) {
        return url;
    }

    const separator = url.includes('?') ? '&' : '?';
    return `${url}${separator}page=${encodeURIComponent(pageKey)}`;
};

const activeSubjects = computed(() => (props.subjects || []).filter((subject) => Boolean(subject.is_active)));
const subjectSearchQuery = ref('');
const filteredSubjects = computed(() => {
    const needle = String(subjectSearchQuery.value || '').trim().toLowerCase();
    if (needle === '') {
        return props.subjects || [];
    }

    return (props.subjects || []).filter((subject) => {
        const name = String(subject.name || '').toLowerCase();
        const code = String(subject.code || '').toLowerCase();
        return name.includes(needle) || code.includes(needle);
    });
});

const overviewStats = computed(() => {
    const stagesCount = structureStageOptions.value.length;
    const gradesCount = structureStageOptions.value.reduce((sum, stage) => sum + (stage.grades || []).length, 0);
    const classroomsCount = structureClassroomOptions.value.length;
    const studentsCount = structureClassroomOptions.value.reduce((sum, classroom) => sum + Number(classroom.students_count || 0), 0);

    return [
        { key: 'stages', label: 'المراحل', value: stagesCount },
        { key: 'grades', label: 'الصفوف', value: gradesCount },
        { key: 'classrooms', label: 'الفصول', value: classroomsCount },
        { key: 'students', label: 'الطلاب', value: studentsCount },
        { key: 'subjects', label: 'المواد', value: (props.subjects || []).length },
        { key: 'schedules', label: 'الحصص المجدولة', value: (props.schedules || []).length },
    ];
});


const leaveTypes = ref([...(props.leaveTypes || [])]);
const leaveTypeError = ref('');
const leaveTypeFormErrors = ref({});
const leaveTypeEditingId = ref(null);
const isLeaveTypeModalOpen = ref(false);
const leaveTypeSearchQuery = ref('');
const leaveTypeStatusFilter = ref('ALL');
const leaveTypeAttachmentFilter = ref('ALL');
const leaveTypeNameInputRef = ref(null);
const leaveTypeForm = ref({
    name: '',
    code: '',
    category: 'STUDENT',
    requires_attachment: false,
    is_active: true,
});

const filteredLeaveTypes = computed(() => {
    const needle = String(leaveTypeSearchQuery.value || '').trim().toLowerCase();
    const statusFilter = String(leaveTypeStatusFilter.value || 'ALL');
    const attachmentFilter = String(leaveTypeAttachmentFilter.value || 'ALL');

    return leaveTypes.value.filter((typeItem) => {
        const searchable = [
            typeItem.name,
            typeItem.code,
            typeItem.category,
        ]
            .filter(Boolean)
            .map((value) => String(value).toLowerCase());

        const matchesSearch = needle === '' || searchable.some((value) => value.includes(needle));
        const matchesStatus = statusFilter === 'ALL'
            || (statusFilter === 'ACTIVE' && Boolean(typeItem.is_active))
            || (statusFilter === 'INACTIVE' && !Boolean(typeItem.is_active));
        const matchesAttachment = attachmentFilter === 'ALL'
            || (attachmentFilter === 'REQUIRED' && Boolean(typeItem.requires_attachment))
            || (attachmentFilter === 'OPTIONAL' && !Boolean(typeItem.requires_attachment));

        return matchesSearch && matchesStatus && matchesAttachment;
    });
});

const resetLeaveTypeForm = () => {
    leaveTypeEditingId.value = null;
    leaveTypeError.value = '';
    leaveTypeFormErrors.value = {};
    leaveTypeForm.value = { name: '', code: '', category: 'STUDENT', requires_attachment: false, is_active: true };
};

const openCreateLeaveTypeModal = () => {
    resetLeaveTypeForm();
    isLeaveTypeModalOpen.value = true;
    nextTick(() => leaveTypeNameInputRef.value?.focus?.());
};

const closeLeaveTypeModal = () => {
    isLeaveTypeModalOpen.value = false;
    resetLeaveTypeForm();
};

const refreshLeaveTypes = async () => {
    leaveTypeError.value = '';
    try {
        const response = await axios.get(route('api.school.leave_types.index'));
        leaveTypes.value = response.data?.data || [];
    } catch (error) {
        leaveTypeError.value = resolveApiErrorMessage(error, 'تعذر تحميل أنواع الإجازات.');
    }
};

const saveLeaveType = async () => {
    leaveTypeError.value = '';
    leaveTypeFormErrors.value = {};
    try {
        const payload = { ...leaveTypeForm.value, code: leaveTypeForm.value.code || null };
        if (leaveTypeEditingId.value) await axios.patch(route('api.school.leave_types.update', leaveTypeEditingId.value), payload);
        else await axios.post(route('api.school.leave_types.store'), payload);
        await refreshLeaveTypes();
        closeLeaveTypeModal();
    } catch (error) {
        leaveTypeFormErrors.value = extractValidationErrors(error);
        leaveTypeError.value = resolveApiErrorMessage(error, 'تعذر حفظ نوع الإجازة.', ['name', 'code']);
    }
};

const editLeaveType = (typeItem) => {
    leaveTypeEditingId.value = typeItem.id;
    leaveTypeForm.value = {
        name: typeItem.name || '',
        code: typeItem.code || '',
        category: typeItem.category || 'STUDENT',
        requires_attachment: Boolean(typeItem.requires_attachment),
        is_active: Boolean(typeItem.is_active),
    };
    isLeaveTypeModalOpen.value = true;
    nextTick(() => leaveTypeNameInputRef.value?.focus?.());
};

const disableLeaveType = async (typeItem) => {
    leaveTypeError.value = '';
    try {
        const impactResponse = await axios.get(route('api.school.leave_types.delete_impact', typeItem.id));
        const impact = impactResponse.data?.data || {};
        let confirmImpact = false;

        if (impact.requires_confirmation) {
            const promptMessage = getImpactMessage(
                { impact },
                'تعطيل نوع الإجازة سيؤثر على بيانات مرتبطة. هل تريد المتابعة؟'
            );
            if (!(await confirmAction(promptMessage, { title: 'تأكيد أثر العملية' }))) {
                return;
            }
            confirmImpact = true;
        }

        await axios.post(route('api.school.leave_types.disable', typeItem.id), {
            confirm_impact: confirmImpact,
        });
        await refreshLeaveTypes();
    } catch (error) {
        if (isImpactConfirmationRequired(error?.response?.data)) {
            leaveTypeError.value = getImpactMessage(
                error?.response?.data,
                'تأكيد العملية مطلوب بسبب وجود بيانات مرتبطة.'
            );
            return;
        }

        leaveTypeError.value = resolveApiErrorMessage(error, 'تعذر تعطيل نوع الإجازة.');
    }
};

const calendarError = ref('');
const calendarFormErrors = ref({});
const calendarForm = ref({
    week_start_day: Number(props.calendarSettings?.week_start_day ?? 0),
    weekly_off_days: Array.isArray(props.calendarSettings?.weekly_off_days)
        ? props.calendarSettings.weekly_off_days.map((day) => Number(day))
        : [],
});

const loadCalendar = async () => {
    calendarError.value = '';
    try {
        const response = await axios.get(route('api.school.calendar_settings.show'));
        const payload = response.data?.data || {};
        calendarForm.value.week_start_day = Number(payload.week_start_day ?? 0);
        calendarForm.value.weekly_off_days = Array.isArray(payload.weekly_off_days)
            ? payload.weekly_off_days.map((day) => Number(day))
            : [];
    } catch (error) {
        calendarError.value = resolveApiErrorMessage(error, 'تعذر تحميل إعدادات التقويم.');
    }
};

const saveCalendar = async () => {
    calendarError.value = '';
    calendarFormErrors.value = {};
    try {
        await axios.put(route('api.school.calendar_settings.update'), {
            week_start_day: Number(calendarForm.value.week_start_day),
            weekly_off_days: (calendarForm.value.weekly_off_days || []).map((day) => Number(day)),
        });
        await loadCalendar();
    } catch (error) {
        calendarFormErrors.value = extractValidationErrors(error);
        calendarError.value = resolveApiErrorMessage(error, 'تعذر حفظ إعدادات التقويم.', ['weekly_off_days', 'week_start_day']);
    }
};

const toggleWeeklyOffDay = (value) => {
    const day = Number(value);
    const next = new Set((calendarForm.value.weekly_off_days || []).map((item) => Number(item)));
    if (next.has(day)) next.delete(day); else next.add(day);
    calendarForm.value.weekly_off_days = [...next].sort((a, b) => a - b);
};

const holidays = ref([...(props.holidays || [])]);
const holidayError = ref('');
const holidayFormErrors = ref({});
const holidayEditingId = ref(null);
const isHolidayModalOpen = ref(false);
const holidaySearchQuery = ref('');
const holidayStatusFilter = ref('ALL');
const holidayNameInputRef = ref(null);
const holidayForm = ref({
    name: '',
    start_date: '',
    days_count: '',
    end_date: '',
    return_date: '',
    notes: '',
    is_active: true,
});

const filteredHolidays = computed(() => {
    const needle = String(holidaySearchQuery.value || '').trim().toLowerCase();
    const statusFilter = String(holidayStatusFilter.value || 'ALL');

    return holidays.value.filter((holiday) => {
        const searchable = [
            holiday.name,
            holiday.notes,
            holiday.start_date,
            holiday.end_date,
            holiday.return_date,
        ]
            .filter(Boolean)
            .map((value) => String(value).toLowerCase());

        const matchesSearch = needle === '' || searchable.some((value) => value.includes(needle));
        const matchesStatus = statusFilter === 'ALL'
            || (statusFilter === 'ACTIVE' && Boolean(holiday.is_active))
            || (statusFilter === 'INACTIVE' && !Boolean(holiday.is_active));

        return matchesSearch && matchesStatus;
    });
});

const normalizeHolidayCalcDateInput = (value) => {
    const normalized = String(value || '').trim();
    if (!/^\d{4}-\d{2}-\d{2}$/.test(normalized)) {
        return null;
    }

    const parsed = new Date(`${normalized}T00:00:00`);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const formatHolidayCalcDateInput = (value) => {
    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const addHolidayCalcDateDays = (value, days = 1) => {
    const next = new Date(value.getTime());
    next.setDate(next.getDate() + Number(days || 0));
    return next;
};

const resolveWeeklyOffDaySet = () => new Set(
    (calendarForm.value.weekly_off_days || [])
        .map((day) => Number(day))
        .filter((day) => Number.isInteger(day) && day >= 0 && day <= 6)
);

const isHolidayWeeklyOffDate = (value, weeklyOffDaySet) => weeklyOffDaySet.has(value.getDay());

const calculateHolidayEndDate = (startDateValue, daysCountValue, weeklyOffDaySet) => {
    const startDate = normalizeHolidayCalcDateInput(startDateValue);
    const daysCount = Number(daysCountValue);

    if (!startDate || !Number.isInteger(daysCount) || daysCount <= 0) {
        return '';
    }

    let cursor = new Date(startDate.getTime());
    let counted = 0;
    let guard = 0;

    while (counted < daysCount && guard < 3700) {
        if (!isHolidayWeeklyOffDate(cursor, weeklyOffDaySet)) {
            counted += 1;
            if (counted >= daysCount) {
                break;
            }
        }

        cursor = addHolidayCalcDateDays(cursor, 1);
        guard += 1;
    }

    if (guard >= 3700) {
        return '';
    }

    return formatHolidayCalcDateInput(cursor);
};

const calculateHolidayReturnDate = (endDateValue, weeklyOffDaySet) => {
    const endDate = normalizeHolidayCalcDateInput(endDateValue);
    if (!endDate) {
        return '';
    }

    let cursor = addHolidayCalcDateDays(endDate, 1);
    let guard = 0;

    while (isHolidayWeeklyOffDate(cursor, weeklyOffDaySet) && guard < 7) {
        cursor = addHolidayCalcDateDays(cursor, 1);
        guard += 1;
    }

    if (guard >= 7) {
        return '';
    }

    return formatHolidayCalcDateInput(cursor);
};

const calculateHolidayDaysCountFromRange = (startDateValue, endDateValue, weeklyOffDaySet) => {
    const startDate = normalizeHolidayCalcDateInput(startDateValue);
    const endDate = normalizeHolidayCalcDateInput(endDateValue);

    if (!startDate || !endDate || endDate < startDate) {
        return '';
    }

    let cursor = new Date(startDate.getTime());
    let counted = 0;
    let guard = 0;

    while (cursor <= endDate && guard < 3700) {
        if (!isHolidayWeeklyOffDate(cursor, weeklyOffDaySet)) {
            counted += 1;
        }

        cursor = addHolidayCalcDateDays(cursor, 1);
        guard += 1;
    }

    if (guard >= 3700) {
        return '';
    }

    return counted > 0 ? counted : '';
};

const resetHolidayForm = () => {
    holidayEditingId.value = null;
    holidayError.value = '';
    holidayFormErrors.value = {};
    holidayForm.value = { name: '', start_date: '', days_count: '', end_date: '', return_date: '', notes: '', is_active: true };
};

const openCreateHolidayModal = () => {
    resetHolidayForm();
    isHolidayModalOpen.value = true;
    nextTick(() => holidayNameInputRef.value?.focus?.());
};

const closeHolidayModal = () => {
    isHolidayModalOpen.value = false;
    resetHolidayForm();
};

const loadHolidays = async () => {
    holidayError.value = '';
    try {
        const response = await axios.get(route('api.school.holidays.index'));
        holidays.value = response.data?.data || [];
    } catch (error) {
        holidayError.value = resolveApiErrorMessage(error, 'تعذر تحميل العطل الرسمية.');
    }
};

const saveHoliday = async () => {
    holidayError.value = '';
    holidayFormErrors.value = {};
    try {
        const weeklyOffDaySet = resolveWeeklyOffDaySet();
        const computedEndDate = calculateHolidayEndDate(holidayForm.value.start_date, holidayForm.value.days_count, weeklyOffDaySet);
        const computedReturnDate = calculateHolidayReturnDate(computedEndDate, weeklyOffDaySet);
        const payload = {
            ...holidayForm.value,
            days_count: holidayForm.value.days_count ? Number(holidayForm.value.days_count) : null,
            end_date: holidayForm.value.days_count ? (computedEndDate || null) : (holidayForm.value.end_date || null),
            return_date: holidayForm.value.days_count ? (computedReturnDate || null) : (holidayForm.value.return_date || null),
            notes: holidayForm.value.notes || null,
        };

        if (holidayEditingId.value) {
            const impactResponse = await axios.get(route('api.school.holidays.update_impact', holidayEditingId.value), {
                params: payload,
            });

            const impact = impactResponse.data?.data || {};
            let confirmImpact = false;
            if (impact.requires_confirmation) {
                const promptMessage = getImpactMessage(
                    { impact },
                    'تعديل العطلة سيؤثر على بيانات حضور مرتبطة. هل تريد المتابعة؟'
                );
                if (!(await confirmAction(promptMessage, { title: 'تأكيد أثر العملية' }))) {
                    return;
                }
                confirmImpact = true;
            }

            await axios.patch(route('api.school.holidays.update', holidayEditingId.value), {
                ...payload,
                confirm_impact: confirmImpact,
            });
        } else {
            await axios.post(route('api.school.holidays.store'), payload);
        }

        await loadHolidays();
        closeHolidayModal();
    } catch (error) {
        if (isImpactConfirmationRequired(error?.response?.data)) {
            holidayError.value = getImpactMessage(
                error?.response?.data,
                'تأكيد العملية مطلوب بسبب وجود بيانات مرتبطة.'
            );
            return;
        }

        holidayFormErrors.value = extractValidationErrors(error);
        holidayError.value = resolveApiErrorMessage(error, 'تعذر حفظ العطلة.', ['name', 'start_date', 'days_count', 'end_date', 'return_date']);
    }
};

const editHoliday = (holiday) => {
    const weeklyOffDaySet = resolveWeeklyOffDaySet();
    const derivedDaysCount = calculateHolidayDaysCountFromRange(
        holiday.start_date || '',
        holiday.end_date || '',
        weeklyOffDaySet
    );

    holidayEditingId.value = holiday.id;
    holidayForm.value = {
        name: holiday.name || '',
        start_date: holiday.start_date || '',
        days_count: derivedDaysCount || holiday.days_count || '',
        end_date: holiday.end_date || '',
        return_date: holiday.return_date || '',
        notes: holiday.notes || '',
        is_active: Boolean(holiday.is_active),
    };
    isHolidayModalOpen.value = true;
    nextTick(() => holidayNameInputRef.value?.focus?.());
};

watch(
    () => [
        holidayForm.value.start_date,
        holidayForm.value.days_count,
        (calendarForm.value.weekly_off_days || []).join(','),
    ],
    ([startDate, daysCount]) => {
        const weeklyOffDaySet = resolveWeeklyOffDaySet();
        const calculatedEndDate = calculateHolidayEndDate(startDate, daysCount, weeklyOffDaySet);
        if (!calculatedEndDate) {
            return;
        }

        if (holidayForm.value.end_date !== calculatedEndDate) {
            holidayForm.value.end_date = calculatedEndDate;
        }

        const calculatedReturnDate = calculateHolidayReturnDate(calculatedEndDate, weeklyOffDaySet);
        if (calculatedReturnDate && holidayForm.value.return_date !== calculatedReturnDate) {
            holidayForm.value.return_date = calculatedReturnDate;
        }
    }
);

const disableHoliday = async (holiday) => {
    holidayError.value = '';
    try {
        const impactResponse = await axios.get(route('api.school.holidays.delete_impact', holiday.id));
        const impact = impactResponse.data?.data || {};
        let confirmImpact = false;

        if (impact.requires_confirmation) {
            const promptMessage = getImpactMessage(
                { impact },
                'تعطيل العطلة سيؤثر على بيانات حضور مرتبطة. هل تريد المتابعة؟'
            );
            if (!(await confirmAction(promptMessage, { title: 'تأكيد أثر العملية' }))) {
                return;
            }
            confirmImpact = true;
        }

        await axios.post(route('api.school.holidays.disable', holiday.id), {
            confirm_impact: confirmImpact,
        });
        await loadHolidays();
    } catch (error) {
        if (isImpactConfirmationRequired(error?.response?.data)) {
            holidayError.value = getImpactMessage(
                error?.response?.data,
                'تأكيد العملية مطلوب بسبب وجود بيانات مرتبطة.'
            );
            return;
        }

        holidayError.value = resolveApiErrorMessage(error, 'تعذر تعطيل العطلة.');
    }
};

const filterForm = ref({
    term_id: props.selectedTermId || props.terms[0]?.id || '',
    scope: props.selectedScope || 'WEEKLY',
    stage_id: props.selectedStageId || '',
    grade_name: props.selectedGradeName || '',
    classroom_id: props.selectedClassroomId || '',
    version_id: props.selectedVersionId || '',
});

const classroomOptionsForFilters = computed(() =>
    classroomOptions.value.filter((classroom) =>
        (!filterForm.value.stage_id || Number(classroom.school_stage_id) === Number(filterForm.value.stage_id))
        && (
        !filterForm.value.grade_name || normalizeGradeName(classroom.grade_name) === normalizeGradeName(filterForm.value.grade_name)
        )
    )
);

const scheduleGradeFilterOptions = computed(() => {
    const scopedClassrooms = classroomOptions.value.filter((classroom) =>
        !filterForm.value.stage_id || Number(classroom.school_stage_id) === Number(filterForm.value.stage_id)
    );

    return [...new Set(scopedClassrooms.map((classroom) => normalizeGradeName(classroom.grade_name)))];
});

const yearSectionRef = ref(null);
const stageSectionRef = ref(null);
const classroomSectionRef = ref(null);
const termSectionRef = ref(null);
const calendarSectionRef = ref(null);
const holidaySectionRef = ref(null);
const leaveTypeSectionRef = ref(null);
const versionSectionRef = ref(null);
const subjectSectionRef = ref(null);
const offeringSectionRef = ref(null);
const teachingAssignmentSectionRef = ref(null);
const scheduleSectionRef = ref(null);

const sectionShortcuts = computed(() => [
    {
        key: 'stages',
        label: 'المراحل والصفوف',
        section: 'stages',
        available: Boolean(props.permissions?.can_manage_student_structure),
        ref: stageSectionRef,
    },
    {
        key: 'years',
        label: 'العام الدراسي',
        section: 'years',
        available: canManagePlanning.value,
        ref: yearSectionRef,
    },
    {
        key: 'terms',
        label: 'الفصول الدراسية',
        section: 'terms',
        available: canManagePlanning.value,
        ref: termSectionRef,
    },
    {
        key: 'classrooms',
        label: 'الفصول التعليمية',
        section: 'classrooms',
        available: Boolean(props.permissions?.can_manage_student_structure),
        ref: classroomSectionRef,
    },
    {
        key: 'subjects',
        label: 'المواد التعليمية',
        section: 'subjects',
        available: canManagePlanning.value,
        ref: subjectSectionRef,
    },
    {
        key: 'course_offerings',
        label: 'المقررات المعتمدة',
        section: 'subjects',
        available: canManagePlanning.value,
        ref: offeringSectionRef,
    },
    {
        key: 'teaching_assignments',
        label: 'إسنادات الاختبارات',
        section: 'subjects',
        available: canManageTeachingAssignments.value,
        ref: teachingAssignmentSectionRef,
    },
    {
        key: 'schedules',
        label: 'الجداول الدراسية',
        section: 'schedules',
        available: canManagePlanning.value,
        ref: scheduleSectionRef,
    },
    {
        key: 'calendar',
        label: 'التقويم والإجازات',
        section: 'calendar',
        available: canManageCalendar.value || canManageHolidays.value || canManageLeaveTypes.value,
        ref: calendarSectionRef,
    },
].filter((item) => item.available && isSectionVisible(item.section)));

const stageNameInputRef = ref(null);
const stageGradeNameInputRef = ref(null);
const classroomNameInputRef = ref(null);
const yearNameInputRef = ref(null);
const termNameInputRef = ref(null);
const versionNameInputRef = ref(null);
const subjectNameInputRef = ref(null);
const scheduleTermSelectRef = ref(null);

const keepAddFlowContext = (sectionRef, focusRef) => {
    nextTick(() => {
        sectionRef?.value?.scrollIntoView?.({
            behavior: 'smooth',
            block: 'start',
        });

        focusRef?.value?.focus?.();
    });
};

const scrollToSection = (sectionRef) => {
    if (!sectionRef) return;
    keepAddFlowContext(sectionRef);
};

const submitOptions = ({ onSuccess = null, successMessage = '' } = {}) => ({
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
        if (successMessage) {
            showCrudSuccess(successMessage);
        }

        if (typeof onSuccess === 'function') {
            onSuccess();
        }
    },
    onError: (errors) => {
        const firstError = Object.values(errors || {}).find((value) => typeof value === 'string' && value.trim() !== '');
        if (firstError) {
            showCrudError(firstError);
        }
    },
});

const applyFilters = () => {
    router.get(
        withPlanningPage(route('school.academic_planning.index')),
        {
            term_id: filterForm.value.term_id || undefined,
            scope: filterForm.value.scope || undefined,
            stage_id: filterForm.value.stage_id || undefined,
            grade_name: filterForm.value.grade_name || undefined,
            classroom_id: filterForm.value.classroom_id || undefined,
            version_id: filterForm.value.version_id || undefined,
            period_count: filterForm.value.scope === 'WEEKLY' ? scheduleGridPeriodCount.value || undefined : undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        }
    );
};

const normalizeDateInput = (value) => {
    if (!value) return '';
    return String(value).slice(0, 10);
};

const normalizeTimeInputValue = (value) => {
    if (!value) return '';
    return String(value).slice(0, 5);
};

const formatTimeForDisplay = (value) => {
    if (!value) return '-';
    return String(value).slice(0, 5);
};

const statusLabel = (value) => (value ? 'نشط' : 'غير نشط');

const extractDeleteErrorMessage = (errors = {}) => {
    const keys = ['stage', 'stage_grade', 'classroom', 'student', 'confirm_impact'];
    for (const key of keys) {
        if (typeof errors[key] === 'string' && errors[key].trim() !== '') {
            return errors[key];
        }
    }

    const firstError = Object.values(errors || {}).find((value) => typeof value === 'string' && value.trim() !== '');
    return firstError || 'تعذر تنفيذ عملية الحذف بسبب وجود بيانات مرتبطة.';
};

const submitBackgroundAction = (method, endpoint, { successMessage = '', errorMessage = '' } = {}) => {
    const actionForm = useForm({});
    actionForm[method](endpoint, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (successMessage) {
                showCrudSuccess(successMessage);
            }
        },
        onError: (errors) => {
            const firstError = Object.values(errors || {}).find((value) => typeof value === 'string' && value.trim() !== '');
            if (firstError) {
                showCrudError(firstError);
                return;
            }

            showCrudError(errorMessage || extractDeleteErrorMessage(errors));
        },
    });
};

const guardedDelete = (endpoint, successMessage = 'تم حذف العنصر بنجاح.') => {
    submitBackgroundAction('delete', endpoint, {
        successMessage,
        errorMessage: 'تعذر تنفيذ عملية الحذف. يرجى المحاولة مرة أخرى.',
    });
};

const stageEditId = ref(null);
const stageForm = useForm({
    name: '',
    code: '',
    school_day_start_time: '',
    school_day_end_time: '',
    sort_order: 0,
    is_active: true,
});

const stageDayTimeForm = useForm({
    school_stage_id: defaultStructureStageId.value,
    name: '',
    code: '',
    school_day_start_time: '',
    school_day_end_time: '',
    sort_order: 0,
    is_active: true,
});
const isStageDayTimeModalOpen = ref(false);
const stageDayTimeSearchQuery = ref('');
const stageDayTimeStatusFilter = ref('ALL');

const stageGradeEditId = ref(null);
const stageGradeForm = useForm({
    school_stage_id: defaultStructureStageId.value,
    name: '',
    sort_order: 0,
    is_active: true,
});

const classroomEditId = ref(null);
const classroomForm = useForm({
    school_stage_id: defaultStructureStageId.value,
    grade_name: '',
    name: '',
    code: '',
    sort_order: 0,
    is_active: true,
});

const classroomGradeOptions = computed(() => gradeOptionsForStructureStage(classroomForm.school_stage_id));

watch(
    () => structureStageOptions.value.map((stage) => Number(stage.id)).join(','),
    () => {
        const validStageIds = structureStageOptions.value.map((stage) => Number(stage.id));
        if (!validStageIds.includes(Number(stageGradeForm.school_stage_id))) {
            stageGradeForm.school_stage_id = defaultStructureStageId.value;
        }
        if (!validStageIds.includes(Number(classroomForm.school_stage_id))) {
            classroomForm.school_stage_id = defaultStructureStageId.value;
        }
        if (!validStageIds.includes(Number(stageDayTimeForm.school_stage_id))) {
            stageDayTimeForm.school_stage_id = defaultStructureStageId.value;
        }

        hydrateStageDayTimeForm(stageDayTimeForm.school_stage_id || defaultStructureStageId.value);
    }
);

watch(
    () => classroomForm.school_stage_id,
    () => {
        const grades = gradeOptionsForStructureStage(classroomForm.school_stage_id);
        const currentGrade = normalizeGradeName(classroomForm.grade_name);

        if (grades.length > 0 && !grades.includes(currentGrade)) {
            classroomForm.grade_name = grades[0];
            return;
        }

        if (grades.length === 0) {
            classroomForm.grade_name = '';
        }
    }
);

const resetStageForm = () => {
    stageEditId.value = null;
    stageForm.reset();
    stageForm.school_day_start_time = '';
    stageForm.school_day_end_time = '';
    stageForm.sort_order = 0;
    stageForm.is_active = true;
    stageForm.clearErrors();
};

const hydrateStageDayTimeForm = (preferredStageId = null) => {
    const availableStages = structureStageOptions.value || [];
    const currentStageId = preferredStageId ?? stageDayTimeForm.school_stage_id;
    const matchedStage = availableStages.find((stage) => Number(stage.id) === Number(currentStageId))
        || availableStages[0]
        || null;

    if (!matchedStage) {
        stageDayTimeForm.school_stage_id = '';
        stageDayTimeForm.name = '';
        stageDayTimeForm.code = '';
        stageDayTimeForm.school_day_start_time = '';
        stageDayTimeForm.school_day_end_time = '';
        stageDayTimeForm.sort_order = 0;
        stageDayTimeForm.is_active = true;
        return;
    }

    stageDayTimeForm.school_stage_id = matchedStage.id;
    stageDayTimeForm.name = matchedStage.name || '';
    stageDayTimeForm.code = matchedStage.code || '';
    stageDayTimeForm.school_day_start_time = normalizeTimeInputValue(matchedStage.school_day_start_time);
    stageDayTimeForm.school_day_end_time = normalizeTimeInputValue(matchedStage.school_day_end_time);
    stageDayTimeForm.sort_order = Number(matchedStage.sort_order || 0);
    stageDayTimeForm.is_active = Boolean(matchedStage.is_active);
    stageDayTimeForm.clearErrors();
};

watch(
    () => stageDayTimeForm.school_stage_id,
    (stageId) => {
        hydrateStageDayTimeForm(stageId);
    },
    { immediate: true }
);

const filteredStageDayTimeStages = computed(() => {
    const needle = String(stageDayTimeSearchQuery.value || '').trim().toLowerCase();
    const statusFilter = String(stageDayTimeStatusFilter.value || 'ALL');

    return structureStageOptions.value.filter((stage) => {
        const searchable = [
            stage.name,
            stage.code,
        ]
            .filter(Boolean)
            .map((value) => String(value).toLowerCase());

        const matchesSearch = needle === '' || searchable.some((value) => value.includes(needle));
        const matchesStatus = statusFilter === 'ALL'
            || (statusFilter === 'ACTIVE' && Boolean(stage.is_active))
            || (statusFilter === 'INACTIVE' && !Boolean(stage.is_active));

        return matchesSearch && matchesStatus;
    });
});

const openStageDayTimeModal = (stageId = null) => {
    hydrateStageDayTimeForm(stageId || stageDayTimeForm.school_stage_id || defaultStructureStageId.value);
    stageDayTimeForm.clearErrors();
    isStageDayTimeModalOpen.value = true;
};

const closeStageDayTimeModal = () => {
    isStageDayTimeModalOpen.value = false;
    stageDayTimeForm.clearErrors();
};

const editStage = (stage) => {
    stageEditId.value = stage.id;
    stageForm.name = stage.name || '';
    stageForm.code = stage.code || '';
    stageForm.school_day_start_time = normalizeTimeInputValue(stage.school_day_start_time);
    stageForm.school_day_end_time = normalizeTimeInputValue(stage.school_day_end_time);
    stageForm.sort_order = Number(stage.sort_order || 0);
    stageForm.is_active = Boolean(stage.is_active);
    stageForm.clearErrors();
};

const submitStageDayTimes = () => {
    const stageId = Number(stageDayTimeForm.school_stage_id || 0);
    if (!stageId) return;

    stageDayTimeForm.put(
        route('school.student_structure.stages.update', stageId),
        submitOptions({
            successMessage: 'تم حفظ مواعيد اليوم الدراسي بنجاح.',
            onSuccess: () => {
                closeStageDayTimeModal();
                keepAddFlowContext(calendarSectionRef);
            },
        })
    );
};

const submitStage = () => {
    if (stageEditId.value) {
        stageForm.put(
            route('school.student_structure.stages.update', stageEditId.value),
            submitOptions({ successMessage: 'تم تعديل العنصر بنجاح.' })
        );
        return;
    }

    stageForm.post(
        route('school.student_structure.stages.store'),
        submitOptions({
            successMessage: 'تم إضافة العنصر بنجاح.',
            onSuccess: () => {
                resetStageForm();
                keepAddFlowContext(stageSectionRef, stageNameInputRef);
            },
        })
    );
};

const removeStage = async (stageId) => {
    if (!(await confirmAction('سيتم الحذف فقط إذا لم توجد بيانات تشغيلية مرتبطة بالمرحلة. هل تريد المتابعة؟', { title: 'حذف المرحلة', variant: 'danger', confirmText: 'نعم، احذف المرحلة' }))) return;
    guardedDelete(route('school.student_structure.stages.destroy', stageId), 'تم حذف العنصر بنجاح.');
};

const resetStageGradeForm = (preferredStageId = null) => {
    stageGradeEditId.value = null;
    stageGradeForm.reset();

    const availableStageIds = structureStageOptions.value.map((stage) => String(stage.id));
    stageGradeForm.school_stage_id =
        preferredStageId && availableStageIds.includes(String(preferredStageId))
            ? preferredStageId
            : defaultStructureStageId.value;

    stageGradeForm.sort_order = 0;
    stageGradeForm.is_active = true;
    stageGradeForm.clearErrors();
    keepAddFlowContext(stageSectionRef, stageGradeNameInputRef);
};

const editStageGrade = (stage, grade) => {
    stageGradeEditId.value = grade.id;
    stageGradeForm.school_stage_id = stage.id;
    stageGradeForm.name = grade.name || '';
    stageGradeForm.sort_order = Number(grade.sort_order || 0);
    stageGradeForm.is_active = Boolean(grade.is_active);
    stageGradeForm.clearErrors();
    keepAddFlowContext(stageSectionRef, stageGradeNameInputRef);
};

const submitStageGrade = () => {
    const preferredStageId = stageGradeForm.school_stage_id;

    if (stageGradeEditId.value) {
        stageGradeForm.put(
            route('school.student_structure.stage_grades.update', stageGradeEditId.value),
            submitOptions({ successMessage: 'تم تعديل العنصر بنجاح.' })
        );
        return;
    }

    stageGradeForm.post(
        route('school.student_structure.stage_grades.store'),
        submitOptions({
            successMessage: 'تم إضافة العنصر بنجاح.',
            onSuccess: () => {
                resetStageGradeForm(preferredStageId);
            },
        })
    );
};

const removeStageGrade = async (stageGradeId) => {
    if (!(await confirmAction('سيتم حذف الصف فقط إذا لم توجد فصول مرتبطة به. هل تريد المتابعة؟', { title: 'حذف الصف', variant: 'danger', confirmText: 'نعم، احذف الصف' }))) return;
    guardedDelete(route('school.student_structure.stage_grades.destroy', stageGradeId), 'تم حذف العنصر بنجاح.');
};

const resetClassroomForm = (preferredStageId = null) => {
    classroomEditId.value = null;
    classroomForm.reset();
    const availableStageIds = structureStageOptions.value.map((stage) => String(stage.id));
    classroomForm.school_stage_id =
        preferredStageId && availableStageIds.includes(String(preferredStageId))
            ? preferredStageId
            : defaultStructureStageId.value;
    const grades = gradeOptionsForStructureStage(classroomForm.school_stage_id);
    classroomForm.grade_name = grades[0] || '';
    classroomForm.sort_order = 0;
    classroomForm.is_active = true;
    classroomForm.clearErrors();
};

const editClassroom = (classroom) => {
    classroomEditId.value = classroom.id;
    classroomForm.school_stage_id = classroom.school_stage_id;
    classroomForm.grade_name = normalizeGradeName(classroom.grade_name);
    classroomForm.name = classroom.name || '';
    classroomForm.code = classroom.code || '';
    classroomForm.sort_order = Number(classroom.sort_order || 0);
    classroomForm.is_active = Boolean(classroom.is_active);
    classroomForm.clearErrors();
};

const submitClassroom = () => {
    const preferredStageId = classroomForm.school_stage_id;
    classroomForm.grade_name = classroomForm.grade_name ? normalizeGradeName(classroomForm.grade_name) : '';

    if (classroomEditId.value) {
        classroomForm.put(
            route('school.student_structure.classrooms.update', classroomEditId.value),
            submitOptions({ successMessage: 'تم تعديل العنصر بنجاح.' })
        );
        return;
    }

    classroomForm.post(
        route('school.student_structure.classrooms.store'),
        submitOptions({
            successMessage: 'تم إضافة العنصر بنجاح.',
            onSuccess: () => {
                resetClassroomForm(preferredStageId);
                keepAddFlowContext(classroomSectionRef, classroomNameInputRef);
            },
        })
    );
};

const removeClassroom = async (classroomId) => {
    if (!(await confirmAction('سيتم الحذف فقط إذا لم توجد بيانات تشغيلية مرتبطة بالفصل. هل تريد المتابعة؟', { title: 'حذف الفصل', variant: 'danger', confirmText: 'نعم، احذف الفصل' }))) return;
    guardedDelete(route('school.student_structure.classrooms.destroy', classroomId), 'تم حذف العنصر بنجاح.');
};

if (!stageGradeForm.school_stage_id && defaultStructureStageId.value) {
    stageGradeForm.school_stage_id = defaultStructureStageId.value;
}

if (!classroomForm.school_stage_id && defaultStructureStageId.value) {
    classroomForm.school_stage_id = defaultStructureStageId.value;
}

const initialClassroomGrades = gradeOptionsForStructureStage(classroomForm.school_stage_id);
if (!classroomForm.grade_name && initialClassroomGrades.length > 0) {
    classroomForm.grade_name = initialClassroomGrades[0];
}

const yearEditId = ref(null);
const yearForm = useForm({
    name: '',
    starts_on: '',
    ends_on: '',
    is_active: true,
});

const resetYearForm = () => {
    yearEditId.value = null;
    yearForm.reset();
    yearForm.is_active = true;
    yearForm.clearErrors();
};

const editYear = (year) => {
    yearEditId.value = year.id;
    yearForm.name = year.name || '';
    yearForm.starts_on = normalizeDateInput(year.starts_on);
    yearForm.ends_on = normalizeDateInput(year.ends_on);
    yearForm.is_active = Boolean(year.is_active);
    yearForm.clearErrors();
};

const submitYear = () => {
    if (yearEditId.value) {
        yearForm.put(
            route('school.academic_planning.years.update', yearEditId.value),
            submitOptions({ successMessage: 'تم تعديل العنصر بنجاح.' })
        );
        return;
    }

    yearForm.post(
        route('school.academic_planning.years.store'),
        submitOptions({
            successMessage: 'تم إضافة العنصر بنجاح.',
            onSuccess: () => {
                resetYearForm();
                keepAddFlowContext(yearSectionRef, yearNameInputRef);
            },
        })
    );
};

const removeYear = async (yearId) => {
    if (!(await confirmAction('سيتم حذف العام الدراسي وفك ارتباطه من أي ترم مرتبط به. هل تريد المتابعة؟', { title: 'حذف العام الدراسي', variant: 'danger', confirmText: 'نعم، احذف العام' }))) return;
    guardedDelete(route('school.academic_planning.years.destroy', yearId), 'تم حذف العنصر بنجاح.');
};

const termEditId = ref(null);
const termForm = useForm({
    school_academic_year_id: '',
    name: '',
    start_date: '',
    end_date: '',
    is_active: true,
});

const resetTermForm = () => {
    termEditId.value = null;
    termForm.reset();
    termForm.school_academic_year_id = '';
    termForm.is_active = true;
    termForm.clearErrors();
};

const editTerm = (term) => {
    termEditId.value = term.id;
    termForm.school_academic_year_id = term.school_academic_year_id || '';
    termForm.name = term.name || '';
    termForm.start_date = normalizeDateInput(term.start_date);
    termForm.end_date = normalizeDateInput(term.end_date);
    termForm.is_active = Boolean(term.is_active);
    termForm.clearErrors();
};

const submitTerm = () => {
    if (termEditId.value) {
        termForm.put(
            route('school.academic_planning.terms.update', termEditId.value),
            submitOptions({ successMessage: 'تم تعديل العنصر بنجاح.' })
        );
        return;
    }

    termForm.post(
        route('school.academic_planning.terms.store'),
        submitOptions({
            successMessage: 'تم إضافة العنصر بنجاح.',
            onSuccess: () => {
                resetTermForm();
                keepAddFlowContext(termSectionRef, termNameInputRef);
            },
        })
    );
};

const removeTerm = async (termId) => {
    if (!(await confirmAction('سيتم حذف الترم وكل الجداول المرتبطة به. هل تريد المتابعة؟', { title: 'حذف الفصل الدراسي', variant: 'danger', confirmText: 'نعم، احذف الترم' }))) return;
    guardedDelete(route('school.academic_planning.terms.destroy', termId), 'تم حذف العنصر بنجاح.');
};

const timetableVersionEditId = ref(null);
const pendingTimetableVersionAttachments = ref([]);
const timetableVersionForm = useForm({
    school_term_id: filterForm.value.term_id || props.terms[0]?.id || '',
    name: '',
    attachments: [],
});

const editingTimetableVersion = computed(() =>
    (props.timetableVersions || []).find((version) => Number(version.id) === Number(timetableVersionEditId.value || 0)) || null
);

const timetableAttachmentErrors = computed(() => [
    timetableVersionForm.errors.attachments,
    timetableVersionForm.errors['attachments.0'],
].filter((value) => typeof value === 'string' && value.trim() !== ''));

const resetTimetableVersionForm = () => {
    timetableVersionEditId.value = null;
    timetableVersionForm.reset();
    timetableVersionForm.school_term_id = filterForm.value.term_id || props.terms[0]?.id || '';
    timetableVersionForm.attachments = [];
    pendingTimetableVersionAttachments.value = [];
    timetableVersionForm.clearErrors();
};

const editTimetableVersion = (version) => {
    timetableVersionEditId.value = version.id;
    timetableVersionForm.school_term_id = version.school_term_id || filterForm.value.term_id || '';
    timetableVersionForm.name = version.name || '';
    timetableVersionForm.attachments = [];
    pendingTimetableVersionAttachments.value = [];
    timetableVersionForm.clearErrors();
};

const appendTimetableAttachmentFiles = (fileList) => {
    const incoming = Array.from(fileList || []).filter((file) => file instanceof File);
    if (incoming.length === 0) return;

    const merged = [...pendingTimetableVersionAttachments.value, ...incoming];
    pendingTimetableVersionAttachments.value = merged.slice(0, 10);
};

const removePendingTimetableAttachment = (index) => {
    pendingTimetableVersionAttachments.value = pendingTimetableVersionAttachments.value.filter((_, itemIndex) => itemIndex !== index);
};

const clearPendingTimetableAttachments = () => {
    pendingTimetableVersionAttachments.value = [];
    timetableVersionForm.attachments = [];
};

const deleteTimetableAttachment = async (attachment) => {
    if (!attachment?.id) return;
    if (!(await confirmAction('سيتم حذف هذا المرفق من نسخة الجدول الحالية. هل تريد المتابعة؟', {
        title: 'حذف مرفق الجدول',
        variant: 'danger',
        confirmText: 'نعم، احذف المرفق',
    }))) return;

    router.delete(route('school.attachments.destroy', { attachment: attachment.id }), {
        preserveScroll: true,
        preserveState: true,
    });
};

const submitTimetableVersion = () => {
    timetableVersionForm.attachments = [...pendingTimetableVersionAttachments.value];

    if (timetableVersionEditId.value) {
        timetableVersionForm.put(
            withPlanningPage(route('school.academic_planning.versions.update', timetableVersionEditId.value)),
            {
                ...submitOptions({
                    successMessage: 'تم تعديل العنصر بنجاح.',
                    onSuccess: () => {
                        clearPendingTimetableAttachments();
                    },
                }),
                forceFormData: true,
            }
        );
        return;
    }

    const selectedVersionTermId = timetableVersionForm.school_term_id || filterForm.value.term_id || props.terms[0]?.id || '';

    timetableVersionForm.post(
        withPlanningPage(route('school.academic_planning.versions.store')),
        {
            ...submitOptions({
                successMessage: 'تم إضافة العنصر بنجاح.',
                onSuccess: () => {
                    clearPendingTimetableAttachments();
                    filterForm.value.term_id = selectedVersionTermId;
                    filterForm.value.version_id = '';
                    resetTimetableVersionForm();
                    keepAddFlowContext(versionSectionRef, versionNameInputRef);
                },
            }),
            forceFormData: true,
        }
    );
};

const publishTimetableVersion = (versionId) => {
    submitBackgroundAction('post', withPlanningPage(route('school.academic_planning.versions.publish', versionId)), {
        successMessage: 'تم تعديل العنصر بنجاح.',
        errorMessage: 'تعذر تنفيذ العملية. يرجى المحاولة مرة أخرى.',
    });
};

const subjectEditId = ref(null);
const isSubjectModalOpen = ref(false);
const subjectForm = useForm({
    name: '',
    code: '',
    branches_text: '',
    teacher_user_ids: [],
    is_active: true,
});

const parseSubjectBranchesInput = (value) =>
    [...new Set(
        String(value || '')
            .split(/[\n,،]+/)
            .map((item) => item.trim())
            .filter((item) => item !== '')
    )];

const resetSubjectForm = () => {
    subjectEditId.value = null;
    subjectForm.reset();
    subjectForm.branches_text = '';
    subjectForm.teacher_user_ids = [];
    subjectForm.is_active = true;
    subjectForm.clearErrors();
};

const openCreateSubjectModal = () => {
    resetSubjectForm();
    isSubjectModalOpen.value = true;
};

const closeSubjectModal = () => {
    isSubjectModalOpen.value = false;
    resetSubjectForm();
};

const editSubject = (subject) => {
    subjectEditId.value = subject.id;
    subjectForm.name = subject.name || '';
    subjectForm.code = subject.code || '';
    subjectForm.branches_text = (subject.branches || []).join('، ');
    subjectForm.teacher_user_ids = (subject.teacher_assignments || []).map((item) => Number(item.teacher_user_id));
    subjectForm.is_active = Boolean(subject.is_active);
    subjectForm.clearErrors();
    isSubjectModalOpen.value = true;
};

const submitSubject = () => {
    const payloadTransform = (data) => ({
        name: data.name,
        code: data.code,
        branches: parseSubjectBranchesInput(data.branches_text),
        teacher_user_ids: (data.teacher_user_ids || []).map((id) => Number(id)),
        is_active: Boolean(data.is_active),
    });

    if (subjectEditId.value) {
        subjectForm
            .transform(payloadTransform)
            .put(
                route('school.academic_planning.subjects.update', subjectEditId.value),
                submitOptions({
                    successMessage: 'تم تعديل العنصر بنجاح.',
                    onSuccess: () => {
                        closeSubjectModal();
                        keepAddFlowContext(subjectSectionRef);
                    },
                })
            );
        return;
    }

    subjectForm
        .transform(payloadTransform)
        .post(
            route('school.academic_planning.subjects.store'),
            submitOptions({
                successMessage: 'تم إضافة العنصر بنجاح.',
                onSuccess: () => {
                    closeSubjectModal();
                    keepAddFlowContext(subjectSectionRef);
                },
            })
        );
};

const removeSubject = async (subjectId) => {
    if (!(await confirmAction('سيتم حذف المادة وكل العلاقات المرتبطة بها. هل تريد المتابعة؟', { title: 'حذف المادة', variant: 'danger', confirmText: 'نعم، احذف المادة' }))) return;
    guardedDelete(route('school.academic_planning.subjects.destroy', subjectId), 'تم حذف العنصر بنجاح.');
};

const subjectTeachersEditId = ref(null);
const subjectTeachersForm = useForm({
    teacher_user_ids: [],
});

const subjectBeingAssigned = computed(
    () => props.subjects.find((subject) => Number(subject.id) === Number(subjectTeachersEditId.value)) || null
);

const openSubjectTeachers = (subject) => {
    subjectTeachersEditId.value = subject.id;
    subjectTeachersForm.teacher_user_ids = (subject.teacher_assignments || []).map((item) => Number(item.teacher_user_id));
    subjectTeachersForm.clearErrors();
};

const cancelSubjectTeachers = () => {
    subjectTeachersEditId.value = null;
    subjectTeachersForm.reset();
    subjectTeachersForm.clearErrors();
};

const submitSubjectTeachers = () => {
    if (!subjectTeachersEditId.value) return;

    subjectTeachersForm
        .transform((data) => ({
            teacher_user_ids: (data.teacher_user_ids || []).map((id) => Number(id)),
        }))
        .post(route('school.academic_planning.subjects.teachers.sync', subjectTeachersEditId.value), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showCrudSuccess('تم تعديل العنصر بنجاح.');
                cancelSubjectTeachers();
                keepAddFlowContext(subjectSectionRef, subjectNameInputRef);
            },
            onError: (errors) => {
                const firstError = Object.values(errors || {}).find((value) => typeof value === 'string' && value.trim() !== '');
                if (firstError) {
                    showCrudError(firstError);
                }
            },
        });
};

const subjectTeachersLabel = (subject) => {
    const names = (subject?.teachers || []).map((teacher) => teacher.name);
    if (names.length === 0) return 'غير محدد';
    return names.join('، ');
};

const DEFAULT_STUDY_PLAN_BRANCH_NAME = 'الفرع الرئيسي';
const normalizeExistingAttachments = (attachments) =>
    (Array.isArray(attachments) ? attachments : [])
        .map((attachment) => ({
            id: Number(attachment?.id || 0),
            file_name: String(attachment?.file_name || attachment?.original_name || '').trim(),
            file_size: Number(attachment?.file_size || attachment?.size || 0),
            mime_type: String(attachment?.mime_type || '').trim(),
            uploaded_by: String(attachment?.uploaded_by || attachment?.uploader?.name || '').trim() || null,
            uploaded_at: attachment?.uploaded_at || attachment?.created_at || null,
            download_url: attachment?.download_url || attachment?.url || null,
        }))
        .filter((attachment) => attachment.id > 0 && attachment.file_name !== '' && attachment.download_url);

const teachingAssignmentEditId = ref(null);
const isTeachingAssignmentModalOpen = ref(false);
const pendingTeachingAssignmentAttachments = ref([]);
const teachingAssignmentForm = useForm({
    teacher_user_id: '',
    school_classroom_ids: [],
    can_create_exam: true,
    can_update_exam: true,
    can_delete_exam: true,
    can_approve_exam: false,
    can_enter_exam_scores: true,
    can_edit_exam_scores: true,
    can_use_question_bank: true,
    attachments: [],
});

const selectedOfferingForTeachingAssignment = computed(() =>
    (props.courseOfferings || []).find((row) => Number(row.id) === Number(teachingAssignmentEditId.value)) || null
);

const selectedTeachingAssignmentAttachments = computed(() =>
    normalizeExistingAttachments(selectedOfferingForTeachingAssignment.value?.teaching_assignment?.attachments || [])
);

const teachingAssignmentAttachmentErrors = computed(() => [
    teachingAssignmentForm.errors.attachments,
    teachingAssignmentForm.errors['attachments.0'],
].filter((value) => typeof value === 'string' && value.trim() !== ''));

const selectedSubjectForTeachingAssignment = computed(() => {
    const subjectId = Number(selectedOfferingForTeachingAssignment.value?.school_subject_id || 0);
    if (subjectId <= 0) return null;
    return (props.subjects || []).find((subject) => Number(subject.id) === subjectId) || null;
});

const subjectBranchesForTeachingAssignment = computed(() => {
    const configuredBranches = [...new Set(
        ((selectedSubjectForTeachingAssignment.value?.branches || []) || [])
            .map((branch) => String(branch || '').trim())
            .filter((branch) => branch !== '')
    )];

    return [...new Set([DEFAULT_STUDY_PLAN_BRANCH_NAME, ...configuredBranches])];
});

const teachersForOfferingAssignment = computed(() => {
    const subjectId = Number(selectedOfferingForTeachingAssignment.value?.school_subject_id || 0);
    if (subjectId <= 0) return [];
    return teachersForSubject(subjectId);
});

const classroomsForOfferingAssignment = computed(() => {
    const offering = selectedOfferingForTeachingAssignment.value;
    if (!offering) return [];

    const stageId = Number(offering.school_stage_id || 0);
    if (stageId <= 0) return [];

    const offeringGradeName = normalizeGradeName(offering?.stage_grade?.name || offering?.classroom?.grade_name || '');
    const scoped = classroomOptions.value.filter((classroom) =>
        Number(classroom.school_stage_id) === stageId
        && normalizeGradeName(classroom.grade_name) === offeringGradeName
    );

    if (scoped.length > 0) {
        return scoped;
    }

    const legacyClassroomId = Number(offering.school_classroom_id || 0);
    if (legacyClassroomId <= 0) return [];
    return classroomOptions.value.filter((classroom) => Number(classroom.id) === legacyClassroomId);
});

const resetTeachingAssignmentForm = () => {
    isTeachingAssignmentModalOpen.value = false;
    teachingAssignmentEditId.value = null;
    teachingAssignmentForm.reset();
    teachingAssignmentForm.teacher_user_id = '';
    teachingAssignmentForm.school_classroom_ids = [];
    teachingAssignmentForm.can_create_exam = true;
    teachingAssignmentForm.can_update_exam = true;
    teachingAssignmentForm.can_delete_exam = true;
    teachingAssignmentForm.can_approve_exam = false;
    teachingAssignmentForm.can_enter_exam_scores = true;
    teachingAssignmentForm.can_edit_exam_scores = true;
    teachingAssignmentForm.can_use_question_bank = true;
    teachingAssignmentForm.attachments = [];
    pendingTeachingAssignmentAttachments.value = [];
    teachingAssignmentForm.clearErrors();
};

const openTeachingAssignmentForm = (offering) => {
    teachingAssignmentEditId.value = offering.id;
    isTeachingAssignmentModalOpen.value = true;
    pendingTeachingAssignmentAttachments.value = [];

    const assignment = offering?.teaching_assignment || null;
    const subjectTeachers = teachersForSubject(offering.school_subject_id);
    const assignmentClassroomIds = (assignment?.classrooms || []).map((classroom) => Number(classroom.id));

    teachingAssignmentForm.teacher_user_id = assignment?.teacher_user_id || subjectTeachers[0]?.id || '';
    if (assignmentClassroomIds.length > 0) {
        teachingAssignmentForm.school_classroom_ids = assignmentClassroomIds;
    } else if (offering?.school_classroom_id) {
        teachingAssignmentForm.school_classroom_ids = [Number(offering.school_classroom_id)];
    } else {
        teachingAssignmentForm.school_classroom_ids = classroomsForOfferingAssignment.value
            .slice(0, 1)
            .map((classroom) => Number(classroom.id));
    }
    teachingAssignmentForm.can_create_exam = Boolean(assignment?.can_create_exam ?? true);
    teachingAssignmentForm.can_update_exam = Boolean(assignment?.can_update_exam ?? true);
    teachingAssignmentForm.can_delete_exam = Boolean(assignment?.can_delete_exam ?? true);
    teachingAssignmentForm.can_approve_exam = Boolean(assignment?.can_approve_exam ?? false);
    teachingAssignmentForm.can_enter_exam_scores = Boolean(assignment?.can_enter_exam_scores ?? true);
    teachingAssignmentForm.can_edit_exam_scores = Boolean(assignment?.can_edit_exam_scores ?? true);
    teachingAssignmentForm.can_use_question_bank = Boolean(assignment?.can_use_question_bank ?? true);
    teachingAssignmentForm.attachments = [];
    teachingAssignmentForm.clearErrors();
};

const appendTeachingAssignmentAttachmentFiles = (fileList) => {
    const incoming = Array.from(fileList || []).filter((file) => file instanceof File);
    if (incoming.length === 0) return;

    const merged = [...pendingTeachingAssignmentAttachments.value, ...incoming];
    pendingTeachingAssignmentAttachments.value = merged.slice(0, 10);
};

const removePendingTeachingAssignmentAttachment = (index) => {
    pendingTeachingAssignmentAttachments.value = pendingTeachingAssignmentAttachments.value.filter((_, itemIndex) => itemIndex !== index);
};

const clearPendingTeachingAssignmentAttachments = () => {
    pendingTeachingAssignmentAttachments.value = [];
    teachingAssignmentForm.attachments = [];
};

const deleteTeachingAssignmentAttachment = async (attachment) => {
    if (!attachment?.id) return;
    if (!(await confirmAction('سيتم حذف هذا المرفق من تحضير المعلم المرتبط بالمقرر الحالي. هل تريد المتابعة؟', {
        title: 'حذف مرفق تحضير المعلم',
        variant: 'danger',
        confirmText: 'نعم، احذف المرفق',
    }))) return;

    router.delete(route('school.attachments.destroy', { attachment: attachment.id }), {
        preserveScroll: true,
        preserveState: true,
    });
};

const submitTeachingAssignment = () => {
    if (!teachingAssignmentEditId.value) return;

    teachingAssignmentForm.attachments = [...pendingTeachingAssignmentAttachments.value];

    teachingAssignmentForm
        .transform((data) => ({
            teacher_user_id: data.teacher_user_id ? Number(data.teacher_user_id) : null,
            school_classroom_ids: (data.school_classroom_ids || []).map((id) => Number(id)),
            can_create_exam: Boolean(data.can_create_exam),
            can_update_exam: Boolean(data.can_update_exam),
            can_delete_exam: Boolean(data.can_delete_exam),
            can_approve_exam: Boolean(data.can_approve_exam),
            can_enter_exam_scores: Boolean(data.can_enter_exam_scores),
            can_edit_exam_scores: Boolean(data.can_edit_exam_scores),
            can_use_question_bank: Boolean(data.can_use_question_bank),
            attachments: data.attachments || [],
        }))
        .post(route('school.academic_planning.offerings.assignment.sync', teachingAssignmentEditId.value), {
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
            onSuccess: () => {
                showCrudSuccess('تم تعديل إسناد المعلم وصلاحيات الاختبارات بنجاح.');
                clearPendingTeachingAssignmentAttachments();
                resetTeachingAssignmentForm();
                keepAddFlowContext(teachingAssignmentSectionRef);
            },
            onError: (errors) => {
                const firstError = Object.values(errors || {}).find((value) => typeof value === 'string' && value.trim() !== '');
                if (firstError) {
                    showCrudError(firstError);
                }
            },
        });
};

const clearTeachingAssignment = async (offering) => {
    if (!(await confirmAction('سيتم إلغاء إسناد هذا المقرر. هل تريد المتابعة؟', { title: 'إلغاء الإسناد', variant: 'danger', confirmText: 'نعم، ألغِ الإسناد' }))) return;

    useForm({ teacher_user_id: null, school_classroom_ids: [] }).post(route('school.academic_planning.offerings.assignment.sync', offering.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showCrudSuccess('تم إلغاء الإسناد بنجاح.');
            if (Number(teachingAssignmentEditId.value) === Number(offering.id)) {
                resetTeachingAssignmentForm();
            }
        },
        onError: (errors) => {
            const firstError = Object.values(errors || {}).find((value) => typeof value === 'string' && value.trim() !== '');
            if (firstError) {
                showCrudError(firstError);
            }
        },
    });
};

const courseOfferingLabel = (offering) => {
    const term = offering?.term?.name || '-';
    const stage = offering?.stage?.name || '-';
    const grade = offering?.stage_grade?.name || offering?.classroom?.grade_name || '-';
    const subject = offering?.subject?.name || '-';
    return `${stage} | ${grade} | ${term} | ${subject}`;
};

const assignmentClassroomsLabel = (offering) => {
    const classrooms = offering?.teaching_assignment?.classrooms || [];
    if (classrooms.length === 0) {
        if (offering?.classroom?.id) {
            return `${offering.classroom.grade_name} / ${offering.classroom.name}`;
        }

        return 'غير محدد';
    }
    return classrooms.map((classroom) => `${classroom.grade_name} / ${classroom.name}`).join('، ');
};

const assignmentPermissionBadges = (assignment) => {
    if (!assignment) return [];

    const badges = [
        assignment.can_create_exam ? 'إنشاء اختبار' : null,
        assignment.can_update_exam ? 'تعديل اختبار' : null,
        assignment.can_delete_exam ? 'حذف اختبار' : null,
        assignment.can_approve_exam ? 'اعتماد اختبار' : null,
        assignment.can_enter_exam_scores ? 'إدخال درجات' : null,
        assignment.can_edit_exam_scores ? 'تعديل درجات' : null,
        assignment.can_use_question_bank ? 'استخدام بنك الأسئلة' : null,
    ];

    return badges.filter(Boolean);
};

const defaultStageId = computed(() => stageOptions.value[0]?.id || '');
const firstClassroomForStage = (stageId) =>
    classroomOptions.value.find((classroom) => Number(classroom.school_stage_id) === Number(stageId))?.id || '';
const firstClassroomForStageAndGrade = (stageId, gradeName = '') =>
    classroomOptions.value.find((classroom) =>
        Number(classroom.school_stage_id) === Number(stageId) &&
        (!gradeName || normalizeGradeName(classroom.grade_name) === normalizeGradeName(gradeName))
    )?.id || '';
const gradeOptionsForStage = (stageId) => {
    const stage = stageOptions.value.find((item) => Number(item.id) === Number(stageId));
    if (!stage) return [];

    const fromGrades = (stage.grades || []).map((grade) => normalizeGradeName(grade.name));
    const fromClassrooms = (stage.classrooms || []).map((classroom) => normalizeGradeName(classroom.grade_name));

    return [...new Set([...fromGrades, ...fromClassrooms])];
};

const defaultSubjectId = computed(() => activeSubjects.value[0]?.id || '');
const teachersForSubject = (subjectId) => {
    const subject = props.subjects.find((item) => Number(item.id) === Number(subjectId));
    if (!subject) return [];
    return subject.teachers || [];
};

const normalizedCourseOfferings = computed(() => {
    return [...(props.courseOfferings || [])].sort((a, b) => {
        const activeWeight = Number(Boolean(b?.is_active)) - Number(Boolean(a?.is_active));
        if (activeWeight !== 0) return activeWeight;

        const aSort = Number(a?.sort_order ?? 0);
        const bSort = Number(b?.sort_order ?? 0);
        if (aSort !== bSort) return aSort - bSort;

        return Number(b?.id ?? 0) - Number(a?.id ?? 0);
    });
});

const approvedCourseFilters = ref({
    search: '',
    stage_id: '',
    grade_id: '',
    term_id: '',
    subject_id: '',
    teacher_id: '',
    status: '',
});
const openApprovedCourseStages = ref({});
const openApprovedCourseGrades = ref({});
const openApprovedCourseTerms = ref({});

const resetApprovedCourseFilters = () => {
    approvedCourseFilters.value = {
        search: '',
        stage_id: '',
        grade_id: '',
        term_id: '',
        subject_id: '',
        teacher_id: '',
        status: '',
    };
};

const hasApprovedCourseFilters = computed(() =>
    Object.values(approvedCourseFilters.value).some((value) => String(value ?? '').trim() !== '')
);

const approvedCourseNodeKey = (prefix, node, fallback = '') =>
    String(node?.key || node?.id || `${prefix}:${fallback || safeText(node?.name, 'unknown')}`);

const isOpenInMap = (state, key) => Boolean(state.value?.[String(key)]);
const toggleOpenInMap = (state, key) => {
    const normalizedKey = String(key);
    state.value = {
        ...(state.value || {}),
        [normalizedKey]: !Boolean(state.value?.[normalizedKey]),
    };
};

const isApprovedCourseStageOpen = (stage) =>
    isOpenInMap(openApprovedCourseStages, approvedCourseNodeKey('stage', stage));
const isApprovedCourseGradeOpen = (grade) =>
    isOpenInMap(openApprovedCourseGrades, approvedCourseNodeKey('grade', grade));
const isApprovedCourseTermOpen = (term) =>
    isOpenInMap(openApprovedCourseTerms, approvedCourseNodeKey('term', term));
const toggleApprovedCourseStage = (stage) =>
    toggleOpenInMap(openApprovedCourseStages, approvedCourseNodeKey('stage', stage));
const toggleApprovedCourseGrade = (grade) =>
    toggleOpenInMap(openApprovedCourseGrades, approvedCourseNodeKey('grade', grade));
const toggleApprovedCourseTerm = (term) =>
    toggleOpenInMap(openApprovedCourseTerms, approvedCourseNodeKey('term', term));

const normalizeApprovedCourse = (course) => {
    const assignment = course?.teaching_assignment || null;

    return {
        ...(course || {}),
        id: Number(course?.id || 0),
        school_stage_id: Number(course?.school_stage_id || course?.stage?.id || 0),
        school_stage_grade_id: Number(course?.school_stage_grade_id || course?.stage_grade?.id || 0),
        school_term_id: Number(course?.school_term_id || course?.term?.id || 0),
        school_subject_id: Number(course?.school_subject_id || course?.subject?.id || 0),
        stage_name: safeText(course?.stage_name || course?.stage?.name, 'مرحلة غير محددة'),
        grade_name: safeText(course?.grade_name || course?.stage_grade?.name || course?.classroom?.grade_name, 'صف غير محدد'),
        term_name: safeText(course?.term_name || course?.term?.name, 'فصل دراسي غير محدد'),
        subject_name: safeText(course?.subject_name || course?.subject?.name, 'مقرر غير محدد'),
        teacher_name: safeText(course?.teacher_name || assignment?.teacher?.name, 'غير مسند'),
        assigned_classrooms_count: Number(course?.assigned_classrooms_count || 0),
        teaching_assignment: assignment,
    };
};

const courseAssignedClassroomIds = (course) => {
    const classroomIds = safeArray(course?.teaching_assignment?.classrooms)
        .map((classroom) => Number(classroom?.id || 0))
        .filter((id) => id > 0);

    const legacyClassroomId = Number(course?.school_classroom_id || course?.classroom?.id || 0);
    if (legacyClassroomId > 0) {
        classroomIds.push(legacyClassroomId);
    }

    return [...new Set(classroomIds)];
};

const summarizeApprovedCourses = (courses) => {
    const safeCourses = safeArray(courses);
    const subjectIds = new Set();
    const classroomIds = new Set();
    const teacherKeys = new Set();
    let fallbackAssignedClassroomsCount = 0;

    safeCourses.forEach((course) => {
        const subjectId = Number(course?.school_subject_id || course?.subject?.id || 0);
        if (subjectId > 0) {
            subjectIds.add(subjectId);
        }

        const assignedIds = courseAssignedClassroomIds(course);
        if (assignedIds.length > 0) {
            assignedIds.forEach((id) => classroomIds.add(id));
        } else {
            fallbackAssignedClassroomsCount += Number(course?.assigned_classrooms_count || 0);
        }

        const teacherId = Number(course?.teaching_assignment?.teacher_user_id || course?.teaching_assignment?.teacher?.id || 0);
        if (teacherId > 0) {
            teacherKeys.add(`id:${teacherId}`);
        } else if (safeText(course?.teacher_name, '') !== '' && safeText(course?.teacher_name, '') !== 'غير مسند') {
            teacherKeys.add(`name:${safeText(course.teacher_name)}`);
        }
    });

    return {
        subjects_count: subjectIds.size,
        courses_count: safeCourses.length,
        active_courses_count: safeCourses.filter((course) => Boolean(course?.is_active)).length,
        inactive_courses_count: safeCourses.filter((course) => !Boolean(course?.is_active)).length,
        assigned_classrooms_count: classroomIds.size + fallbackAssignedClassroomsCount,
        teachers_count: teacherKeys.size,
    };
};

const normalizedApprovedCoursesTree = computed(() =>
    safeArray(props.approvedCoursesTree).map((stage, stageIndex) => ({
        ...(stage || {}),
        id: stage?.id ?? null,
        key: approvedCourseNodeKey('stage', stage, stageIndex),
        name: safeText(stage?.name, 'مرحلة غير محددة'),
        grades: safeArray(stage?.grades).map((grade, gradeIndex) => ({
            ...(grade || {}),
            id: grade?.id ?? null,
            key: approvedCourseNodeKey('grade', grade, `${stageIndex}-${gradeIndex}`),
            name: safeText(grade?.name, 'صف غير محدد'),
            terms: safeArray(grade?.terms).map((term, termIndex) => ({
                ...(term || {}),
                id: term?.id ?? null,
                key: approvedCourseNodeKey('term', term, `${stageIndex}-${gradeIndex}-${termIndex}`),
                name: safeText(term?.name, 'فصل دراسي غير محدد'),
                courses: safeArray(term?.courses).map((course) => normalizeApprovedCourse(course)),
            })),
        })),
    }))
);

const approvedCourseStageFilterOptions = computed(() =>
    normalizedApprovedCoursesTree.value.map((stage) => ({
        id: stage.id,
        name: stage.name,
    })).filter((stage) => Number(stage.id || 0) > 0)
);

const approvedCourseGradeFilterOptions = computed(() => {
    const selectedStageId = Number(approvedCourseFilters.value.stage_id || 0);
    const grades = new Map();

    normalizedApprovedCoursesTree.value
        .filter((stage) => selectedStageId <= 0 || Number(stage.id || 0) === selectedStageId)
        .forEach((stage) => {
            safeArray(stage.grades).forEach((grade) => {
                const gradeId = Number(grade?.id || 0);
                if (gradeId > 0 && !grades.has(gradeId)) {
                    grades.set(gradeId, {
                        id: gradeId,
                        name: grade.name,
                        stage_name: stage.name,
                    });
                }
            });
        });

    return [...grades.values()];
});

const approvedCourseMatchesFilters = (offering) => {
    const course = normalizeApprovedCourse(offering);
    const filters = approvedCourseFilters.value;
    const stageId = Number(filters.stage_id || 0);
    const gradeId = Number(filters.grade_id || 0);
    const termId = Number(filters.term_id || 0);
    const subjectId = Number(filters.subject_id || 0);
    const teacherId = Number(filters.teacher_id || 0);

    if (stageId > 0 && Number(course.school_stage_id || 0) !== stageId) return false;
    if (gradeId > 0 && Number(course.school_stage_grade_id || 0) !== gradeId) return false;
    if (termId > 0 && Number(course.school_term_id || 0) !== termId) return false;
    if (subjectId > 0 && Number(course.school_subject_id || 0) !== subjectId) return false;
    if (teacherId > 0 && Number(course.teaching_assignment?.teacher_user_id || course.teaching_assignment?.teacher?.id || 0) !== teacherId) return false;

    const status = String(filters.status || '').trim();
    if (status === 'active' && !Boolean(course.is_active)) return false;
    if (status === 'inactive' && Boolean(course.is_active)) return false;
    if (status === 'usable' && !Boolean(course.usable_in_exams ?? true)) return false;
    if (status === 'not_usable' && Boolean(course.usable_in_exams ?? true)) return false;
    if (status === 'assigned' && !course.teaching_assignment) return false;
    if (status === 'unassigned' && course.teaching_assignment) return false;

    const needle = String(filters.search || '').trim().toLowerCase();
    if (needle === '') return true;

    const haystack = [
        course.stage_name,
        course.grade_name,
        course.term_name,
        course.subject_name,
        course.teacher_name,
        courseOfferingLabel(course),
        assignmentClassroomsLabel(course),
    ].join(' ').toLowerCase();

    return haystack.includes(needle);
};

const filteredApprovedCourseIds = computed(() =>
    new Set(
        normalizedCourseOfferings.value
            .filter((offering) => approvedCourseMatchesFilters(offering))
            .map((offering) => Number(offering?.id || 0))
            .filter((id) => id > 0)
    )
);

const approvedCoursesTreeForDisplay = computed(() => {
    const activeIds = filteredApprovedCourseIds.value;
    const filtersEnabled = hasApprovedCourseFilters.value;

    return normalizedApprovedCoursesTree.value
        .map((stage) => {
            const grades = safeArray(stage.grades)
                .map((grade) => {
                    const terms = safeArray(grade.terms)
                        .map((term) => {
                            const courses = safeArray(term.courses).filter((course) => activeIds.has(Number(course?.id || 0)));
                            if (filtersEnabled && courses.length === 0) return null;

                            return {
                                ...term,
                                ...summarizeApprovedCourses(courses),
                                courses,
                            };
                        })
                        .filter(Boolean);

                    const gradeCourses = terms.flatMap((term) => safeArray(term.courses));
                    if (filtersEnabled && gradeCourses.length === 0) return null;

                    return {
                        ...grade,
                        ...summarizeApprovedCourses(gradeCourses),
                        terms_count: terms.length,
                        terms,
                    };
                })
                .filter(Boolean);

            const stageCourses = grades.flatMap((grade) =>
                safeArray(grade.terms).flatMap((term) => safeArray(term.courses))
            );
            if (filtersEnabled && stageCourses.length === 0) return null;

            return {
                ...stage,
                ...summarizeApprovedCourses(stageCourses),
                grades_count: grades.length,
                terms_count: grades.reduce((sum, grade) => sum + Number(grade.terms_count || 0), 0),
                grades,
            };
        })
        .filter(Boolean);
});

watch(approvedCoursesTreeForDisplay, (tree) => {
    const stages = safeArray(tree);
    if (stages.length === 0) return;

    if (hasApprovedCourseFilters.value) {
        const nextStages = {};
        const nextGrades = {};
        const nextTerms = {};

        stages.forEach((stage) => {
            nextStages[approvedCourseNodeKey('stage', stage)] = true;
            safeArray(stage.grades).forEach((grade) => {
                nextGrades[approvedCourseNodeKey('grade', grade)] = true;
                safeArray(grade.terms).forEach((term) => {
                    nextTerms[approvedCourseNodeKey('term', term)] = true;
                });
            });
        });

        openApprovedCourseStages.value = nextStages;
        openApprovedCourseGrades.value = nextGrades;
        openApprovedCourseTerms.value = nextTerms;
        return;
    }

    if (Object.keys(openApprovedCourseStages.value || {}).length === 0) {
        openApprovedCourseStages.value = {
            [approvedCourseNodeKey('stage', stages[0])]: true,
        };
    }
}, { immediate: true });

const courseAssignmentFilters = ref({
    search: '',
    stage_id: '',
    grade_id: '',
    term_id: '',
    subject_id: '',
    teacher_id: '',
    assignment_status: '',
    permission_status: '',
    course_status: '',
});
const openCourseAssignmentStages = ref({});
const openCourseAssignmentGrades = ref({});
const openCourseAssignmentTerms = ref({});
const openCourseAssignments = ref({});

const resetCourseAssignmentFilters = () => {
    courseAssignmentFilters.value = {
        search: '',
        stage_id: '',
        grade_id: '',
        term_id: '',
        subject_id: '',
        teacher_id: '',
        assignment_status: '',
        permission_status: '',
        course_status: '',
    };
};

const hasCourseAssignmentFilters = computed(() =>
    Object.values(courseAssignmentFilters.value).some((value) => String(value ?? '').trim() !== '')
);

const courseAssignmentNodeKey = (prefix, node, fallback = '') =>
    String(node?.key || node?.id || `${prefix}:${fallback || safeText(node?.name, 'unknown')}`);
const courseAssignmentCourseKey = (course) => `course:${Number(course?.id || 0) || safeText(course?.subject_name || course?.subject?.name, 'unknown')}`;
const isCourseAssignmentStageOpen = (stage) =>
    isOpenInMap(openCourseAssignmentStages, courseAssignmentNodeKey('stage', stage));
const isCourseAssignmentGradeOpen = (grade) =>
    isOpenInMap(openCourseAssignmentGrades, courseAssignmentNodeKey('grade', grade));
const isCourseAssignmentTermOpen = (term) =>
    isOpenInMap(openCourseAssignmentTerms, courseAssignmentNodeKey('term', term));
const isCourseAssignmentOpen = (course) =>
    isOpenInMap(openCourseAssignments, courseAssignmentCourseKey(course));
const toggleCourseAssignmentStage = (stage) =>
    toggleOpenInMap(openCourseAssignmentStages, courseAssignmentNodeKey('stage', stage));
const toggleCourseAssignmentGrade = (grade) =>
    toggleOpenInMap(openCourseAssignmentGrades, courseAssignmentNodeKey('grade', grade));
const toggleCourseAssignmentTerm = (term) =>
    toggleOpenInMap(openCourseAssignmentTerms, courseAssignmentNodeKey('term', term));
const toggleCourseAssignment = (course) =>
    toggleOpenInMap(openCourseAssignments, courseAssignmentCourseKey(course));

const courseAssignmentPermissionCount = (course) =>
    assignmentPermissionBadges(course?.teaching_assignment || null).length;

const courseAssignmentStatus = (course) => {
    const assignment = course?.teaching_assignment || null;
    const teacherId = Number(assignment?.teacher_user_id || assignment?.teacher?.id || 0);
    const classroomIds = courseAssignedClassroomIds(course);

    if (!assignment || teacherId <= 0) {
        return {
            key: 'no_teacher',
            label: 'بدون معلم',
            tone: 'bg-amber-500/15 text-amber-700 dark:text-amber-200',
        };
    }

    if (classroomIds.length === 0) {
        return {
            key: 'no_classrooms',
            label: 'بدون فصول',
            tone: 'bg-orange-500/15 text-orange-700 dark:text-orange-200',
        };
    }

    return {
        key: 'complete',
        label: 'إسناد مكتمل',
        tone: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-200',
    };
};

const summarizeCourseAssignments = (courses) => {
    const base = summarizeApprovedCourses(courses);
    const safeCourses = safeArray(courses);
    const permissionsCount = safeCourses.reduce((sum, course) => sum + courseAssignmentPermissionCount(course), 0);
    const completeCount = safeCourses.filter((course) => courseAssignmentStatus(course).key === 'complete').length;
    const noTeacherCount = safeCourses.filter((course) => courseAssignmentStatus(course).key === 'no_teacher').length;
    const noClassroomsCount = safeCourses.filter((course) => courseAssignmentStatus(course).key === 'no_classrooms').length;

    return {
        ...base,
        assignments_count: safeCourses.length,
        permissions_count: permissionsCount,
        complete_assignments_count: completeCount,
        incomplete_assignments_count: safeCourses.length - completeCount,
        no_teacher_count: noTeacherCount,
        no_classrooms_count: noClassroomsCount,
    };
};

const normalizedCourseAssignmentsTree = computed(() =>
    safeArray(props.courseAssignmentsTree).map((stage, stageIndex) => ({
        ...(stage || {}),
        id: stage?.id ?? null,
        key: courseAssignmentNodeKey('stage', stage, stageIndex),
        name: safeText(stage?.name, 'مرحلة غير محددة'),
        grades: safeArray(stage?.grades).map((grade, gradeIndex) => ({
            ...(grade || {}),
            id: grade?.id ?? null,
            key: courseAssignmentNodeKey('grade', grade, `${stageIndex}-${gradeIndex}`),
            name: safeText(grade?.name, 'صف غير محدد'),
            terms: safeArray(grade?.terms).map((term, termIndex) => ({
                ...(term || {}),
                id: term?.id ?? null,
                key: courseAssignmentNodeKey('term', term, `${stageIndex}-${gradeIndex}-${termIndex}`),
                name: safeText(term?.name, 'فصل دراسي غير محدد'),
                courses: safeArray(term?.courses).map((course) => normalizeApprovedCourse(course)),
            })),
        })),
    }))
);

const courseAssignmentStageFilterOptions = computed(() =>
    normalizedCourseAssignmentsTree.value.map((stage) => ({
        id: stage.id,
        name: stage.name,
    })).filter((stage) => Number(stage.id || 0) > 0)
);

const courseAssignmentGradeFilterOptions = computed(() => {
    const selectedStageId = Number(courseAssignmentFilters.value.stage_id || 0);
    const grades = new Map();

    normalizedCourseAssignmentsTree.value
        .filter((stage) => selectedStageId <= 0 || Number(stage.id || 0) === selectedStageId)
        .forEach((stage) => {
            safeArray(stage.grades).forEach((grade) => {
                const gradeId = Number(grade?.id || 0);
                if (gradeId > 0 && !grades.has(gradeId)) {
                    grades.set(gradeId, {
                        id: gradeId,
                        name: grade.name,
                        stage_name: stage.name,
                    });
                }
            });
        });

    return [...grades.values()];
});

const courseAssignmentMatchesFilters = (offering) => {
    const course = normalizeApprovedCourse(offering);
    const filters = courseAssignmentFilters.value;
    const stageId = Number(filters.stage_id || 0);
    const gradeId = Number(filters.grade_id || 0);
    const termId = Number(filters.term_id || 0);
    const subjectId = Number(filters.subject_id || 0);
    const teacherId = Number(filters.teacher_id || 0);
    const assignmentStatus = courseAssignmentStatus(course).key;
    const permissionCount = courseAssignmentPermissionCount(course);

    if (!Boolean(course.is_active) || !Boolean(course.usable_in_exams ?? true)) return false;
    if (stageId > 0 && Number(course.school_stage_id || 0) !== stageId) return false;
    if (gradeId > 0 && Number(course.school_stage_grade_id || 0) !== gradeId) return false;
    if (termId > 0 && Number(course.school_term_id || 0) !== termId) return false;
    if (subjectId > 0 && Number(course.school_subject_id || 0) !== subjectId) return false;
    if (teacherId > 0 && Number(course.teaching_assignment?.teacher_user_id || course.teaching_assignment?.teacher?.id || 0) !== teacherId) return false;

    const selectedAssignmentStatus = String(filters.assignment_status || '').trim();
    if (selectedAssignmentStatus === 'complete' && assignmentStatus !== 'complete') return false;
    if (selectedAssignmentStatus === 'incomplete' && assignmentStatus === 'complete') return false;
    if (selectedAssignmentStatus === 'no_teacher' && assignmentStatus !== 'no_teacher') return false;
    if (selectedAssignmentStatus === 'no_classrooms' && assignmentStatus !== 'no_classrooms') return false;

    const selectedPermissionStatus = String(filters.permission_status || '').trim();
    if (selectedPermissionStatus === 'has_permissions' && permissionCount === 0) return false;
    if (selectedPermissionStatus === 'no_permissions' && permissionCount > 0) return false;

    const selectedCourseStatus = String(filters.course_status || '').trim();
    if (selectedCourseStatus === 'active' && !Boolean(course.is_active)) return false;
    if (selectedCourseStatus === 'inactive' && Boolean(course.is_active)) return false;

    const needle = String(filters.search || '').trim().toLowerCase();
    if (needle === '') return true;

    const haystack = [
        course.stage_name,
        course.grade_name,
        course.term_name,
        course.subject_name,
        course.teacher_name,
        courseOfferingLabel(course),
        assignmentClassroomsLabel(course),
        assignmentPermissionBadges(course.teaching_assignment).join(' '),
    ].join(' ').toLowerCase();

    return haystack.includes(needle);
};

const filteredCourseAssignmentIds = computed(() =>
    new Set(
        normalizedCourseOfferings.value
            .filter((offering) => courseAssignmentMatchesFilters(offering))
            .map((offering) => Number(offering?.id || 0))
            .filter((id) => id > 0)
    )
);

const courseAssignmentsTreeForDisplay = computed(() => {
    const activeIds = filteredCourseAssignmentIds.value;
    const filtersEnabled = hasCourseAssignmentFilters.value;

    return normalizedCourseAssignmentsTree.value
        .map((stage) => {
            const grades = safeArray(stage.grades)
                .map((grade) => {
                    const terms = safeArray(grade.terms)
                        .map((term) => {
                            const courses = safeArray(term.courses).filter((course) => activeIds.has(Number(course?.id || 0)));
                            if (filtersEnabled && courses.length === 0) return null;

                            return {
                                ...term,
                                ...summarizeCourseAssignments(courses),
                                courses,
                            };
                        })
                        .filter(Boolean);

                    const gradeCourses = terms.flatMap((term) => safeArray(term.courses));
                    if (filtersEnabled && gradeCourses.length === 0) return null;

                    return {
                        ...grade,
                        ...summarizeCourseAssignments(gradeCourses),
                        terms_count: terms.length,
                        terms,
                    };
                })
                .filter(Boolean);

            const stageCourses = grades.flatMap((grade) =>
                safeArray(grade.terms).flatMap((term) => safeArray(term.courses))
            );
            if (filtersEnabled && stageCourses.length === 0) return null;

            return {
                ...stage,
                ...summarizeCourseAssignments(stageCourses),
                grades_count: grades.length,
                terms_count: grades.reduce((sum, grade) => sum + Number(grade.terms_count || 0), 0),
                grades,
            };
        })
        .filter(Boolean);
});

watch(courseAssignmentsTreeForDisplay, (tree) => {
    const stages = safeArray(tree);
    if (stages.length === 0) return;

    if (hasCourseAssignmentFilters.value) {
        const nextStages = {};
        const nextGrades = {};
        const nextTerms = {};

        stages.forEach((stage) => {
            nextStages[courseAssignmentNodeKey('stage', stage)] = true;
            safeArray(stage.grades).forEach((grade) => {
                nextGrades[courseAssignmentNodeKey('grade', grade)] = true;
                safeArray(grade.terms).forEach((term) => {
                    nextTerms[courseAssignmentNodeKey('term', term)] = true;
                });
            });
        });

        openCourseAssignmentStages.value = nextStages;
        openCourseAssignmentGrades.value = nextGrades;
        openCourseAssignmentTerms.value = nextTerms;
        return;
    }

    if (Object.keys(openCourseAssignmentStages.value || {}).length === 0) {
        openCourseAssignmentStages.value = {
            [courseAssignmentNodeKey('stage', stages[0])]: true,
        };
    }
}, { immediate: true });

const assignmentCourseOfferings = computed(() =>
    normalizedCourseOfferings.value.filter((offering) => Boolean(offering?.is_active) && Boolean(offering?.usable_in_exams ?? true))
);

const courseOfferingEditId = ref(null);
const courseOfferingForm = useForm({
    school_term_id: filterForm.value.term_id || props.terms[0]?.id || '',
    school_stage_id: defaultStageId.value,
    school_stage_grade_id: '',
    school_subject_id: defaultSubjectId.value,
    sort_order: 0,
    usable_in_exams: true,
    alert_before_term_end_days: 0,
    study_plan_units: [],
    is_active: true,
});

const courseOfferingGradeOptions = computed(() => {
    const stage = stageOptions.value.find((item) => Number(item.id) === Number(courseOfferingForm.school_stage_id));
    if (!stage) return [];

    return (stage.grades || [])
        .filter((grade) => Boolean(grade.is_active))
        .map((grade) => ({
            id: Number(grade.id),
            name: normalizeGradeName(grade.name),
        }));
});

const selectedSubjectForCourseOffering = computed(() =>
    (props.subjects || []).find((subject) => Number(subject.id) === Number(courseOfferingForm.school_subject_id)) || null
);

const isStudyPlanModalOpen = ref(false);
const studyPlanDefaultBranch = ref(DEFAULT_STUDY_PLAN_BRANCH_NAME);

const subjectBranchesForCourseOffering = computed(() => {
    const subject = selectedSubjectForCourseOffering.value;
    const configuredBranches = [...new Set(
        ((subject?.branches || []) || [])
            .map((branch) => String(branch || '').trim())
            .filter((branch) => branch !== '')
    )];

    return [...new Set([DEFAULT_STUDY_PLAN_BRANCH_NAME, ...configuredBranches])];
});

const syncStudyPlanDefaultBranch = () => {
    const allowedBranches = subjectBranchesForCourseOffering.value;
    const currentBranch = String(studyPlanDefaultBranch.value || '').trim();

    if (currentBranch !== '' && allowedBranches.includes(currentBranch)) {
        return;
    }

    studyPlanDefaultBranch.value = allowedBranches[0] || DEFAULT_STUDY_PLAN_BRANCH_NAME;
};

const createStudyPlanTopic = () => ({
    name: '',
    sort_order: 0,
    description: '',
});

const createStudyPlanLesson = () => ({
    name: '',
    sort_order: 0,
    description: '',
    topics: [],
});

const createStudyPlanUnit = (branchName = DEFAULT_STUDY_PLAN_BRANCH_NAME) => ({
    branch_name: String(branchName || '').trim() || DEFAULT_STUDY_PLAN_BRANCH_NAME,
    name: '',
    sort_order: 0,
    start_date: '',
    end_date: '',
    notes: '',
    lessons: [],
});

const normalizeStudyPlanUnitsForForm = (units) =>
    (units || []).map((unit) => ({
        branch_name: String(unit?.branch_name || '').trim() || DEFAULT_STUDY_PLAN_BRANCH_NAME,
        name: unit?.name || '',
        sort_order: Number(unit?.sort_order || 0),
        start_date: unit?.start_date || '',
        end_date: unit?.end_date || '',
        notes: unit?.notes || '',
        lessons: (unit?.lessons || []).map((lesson) => ({
            name: lesson?.name || '',
            sort_order: Number(lesson?.sort_order || 0),
            description: lesson?.description || '',
            topics: (lesson?.topics || []).map((topic) => ({
                name: topic?.name || '',
                sort_order: Number(topic?.sort_order || 0),
                description: topic?.description || '',
            })),
        })),
    }));

const reindexStudyPlan = () => {
    courseOfferingForm.study_plan_units = (courseOfferingForm.study_plan_units || []).map((unit, unitIndex) => ({
        ...unit,
        sort_order: unitIndex + 1,
        lessons: (unit.lessons || []).map((lesson, lessonIndex) => ({
            ...lesson,
            sort_order: lessonIndex + 1,
            topics: (lesson.topics || []).map((topic, topicIndex) => ({
                ...topic,
                sort_order: topicIndex + 1,
            })),
        })),
    }));
};

const openStudyPlanModal = () => {
    syncStudyPlanDefaultBranch();
    isStudyPlanModalOpen.value = true;
};

const closeStudyPlanModal = () => {
    isStudyPlanModalOpen.value = false;
};

const addStudyPlanUnit = () => {
    courseOfferingForm.study_plan_units.push(createStudyPlanUnit(studyPlanDefaultBranch.value));
    reindexStudyPlan();
};

const removeStudyPlanUnit = (unitIndex) => {
    courseOfferingForm.study_plan_units.splice(unitIndex, 1);
    reindexStudyPlan();
};

const clearStudyPlanUnits = () => {
    courseOfferingForm.study_plan_units = [];
    reindexStudyPlan();
};

const addStudyPlanLesson = (unitIndex) => {
    courseOfferingForm.study_plan_units[unitIndex].lessons.push(createStudyPlanLesson());
    reindexStudyPlan();
};

const removeStudyPlanLesson = (unitIndex, lessonIndex) => {
    courseOfferingForm.study_plan_units[unitIndex].lessons.splice(lessonIndex, 1);
    reindexStudyPlan();
};

const addStudyPlanTopic = (unitIndex, lessonIndex) => {
    courseOfferingForm.study_plan_units[unitIndex].lessons[lessonIndex].topics.push(createStudyPlanTopic());
    reindexStudyPlan();
};

const removeStudyPlanTopic = (unitIndex, lessonIndex, topicIndex) => {
    courseOfferingForm.study_plan_units[unitIndex].lessons[lessonIndex].topics.splice(topicIndex, 1);
    reindexStudyPlan();
};

const courseOfferingTermAlertMessage = (offering) => {
    const alert = offering?.term_end_alert || null;
    if (!alert || !alert.is_near_end) return '';

    const daysRemaining = Number(alert.days_remaining ?? -1);
    if (daysRemaining === 0) {
        return 'اقترب انتهاء الترم لهذا المقرر. اليوم هو آخر يوم في الترم.';
    }

    if (daysRemaining > 0) {
        return `تبقى ${daysRemaining} يوم على نهاية الترم لهذا المقرر.`;
    }

    return '';
};

watch(
    () => courseOfferingForm.school_stage_id,
    () => {
        const gradeIds = courseOfferingGradeOptions.value.map((grade) => Number(grade.id));
        if (!gradeIds.includes(Number(courseOfferingForm.school_stage_grade_id))) {
            courseOfferingForm.school_stage_grade_id = courseOfferingGradeOptions.value[0]?.id || '';
        }
    },
    { immediate: true }
);

watch(
    () => courseOfferingForm.school_subject_id,
    () => {
        const allowed = subjectBranchesForCourseOffering.value;
        courseOfferingForm.study_plan_units = (courseOfferingForm.study_plan_units || []).map((unit) => {
            const branchName = String(unit?.branch_name || '').trim();
            if (branchName !== '' && allowed.includes(branchName)) {
                return unit;
            }

            return {
                ...unit,
                branch_name: allowed[0] || DEFAULT_STUDY_PLAN_BRANCH_NAME,
            };
        });
        syncStudyPlanDefaultBranch();
    },
    { immediate: true }
);

const resetCourseOfferingForm = () => {
    courseOfferingEditId.value = null;
    courseOfferingForm.reset();
    courseOfferingForm.school_term_id = filterForm.value.term_id || props.terms[0]?.id || '';
    courseOfferingForm.school_stage_id = defaultStageId.value;
    courseOfferingForm.school_stage_grade_id = '';
    courseOfferingForm.school_subject_id = defaultSubjectId.value;
    courseOfferingForm.sort_order = 0;
    courseOfferingForm.usable_in_exams = true;
    courseOfferingForm.alert_before_term_end_days = 0;
    courseOfferingForm.study_plan_units = [];
    courseOfferingForm.is_active = true;
    isStudyPlanModalOpen.value = false;
    if (courseOfferingGradeOptions.value.length > 0) {
        courseOfferingForm.school_stage_grade_id = courseOfferingGradeOptions.value[0]?.id || '';
    }
    syncStudyPlanDefaultBranch();
    courseOfferingForm.clearErrors();
};

const editCourseOffering = (offering) => {
    courseOfferingEditId.value = offering.id;
    courseOfferingForm.school_term_id = offering.school_term_id || filterForm.value.term_id || '';
    courseOfferingForm.school_stage_id = offering.school_stage_id || '';
    courseOfferingForm.school_stage_grade_id = offering.school_stage_grade_id || offering?.stage_grade?.id || '';
    courseOfferingForm.school_subject_id = offering.school_subject_id || '';
    courseOfferingForm.sort_order = Number(offering.sort_order ?? 0);
    courseOfferingForm.usable_in_exams = Boolean(offering.usable_in_exams ?? true);
    courseOfferingForm.alert_before_term_end_days = Number(offering.alert_before_term_end_days ?? 0);
    courseOfferingForm.study_plan_units = normalizeStudyPlanUnitsForForm(offering.study_plan_units || []);
    reindexStudyPlan();
    courseOfferingForm.is_active = Boolean(offering.is_active);
    studyPlanDefaultBranch.value = courseOfferingForm.study_plan_units[0]?.branch_name || DEFAULT_STUDY_PLAN_BRANCH_NAME;
    syncStudyPlanDefaultBranch();
    courseOfferingForm.clearErrors();
};

const submitCourseOffering = () => {
    const normalizedUnits = normalizeStudyPlanUnitsForForm(courseOfferingForm.study_plan_units || []);
    if (!courseOfferingEditId.value && normalizedUnits.length === 0) {
        courseOfferingForm.setError('study_plan_units', 'لا يمكن إضافة المقرر بدون إضافة خطة دراسية واحدة على الأقل.');
        showCrudError('لا يمكن إضافة المقرر بدون إضافة خطة دراسية واحدة على الأقل.');
        openStudyPlanModal();
        return;
    }

    const payloadTransform = (data) => ({
        school_term_id: Number(data.school_term_id),
        school_stage_id: Number(data.school_stage_id),
        school_stage_grade_id: Number(data.school_stage_grade_id),
        school_subject_id: Number(data.school_subject_id),
        sort_order: Number(data.sort_order || 0),
        usable_in_exams: Boolean(data.usable_in_exams),
        alert_before_term_end_days: Number(data.alert_before_term_end_days || 0),
        study_plan_units: (data.study_plan_units || []).map((unit, unitIndex) => ({
            branch_name: String(unit.branch_name || '').trim() || DEFAULT_STUDY_PLAN_BRANCH_NAME,
            name: String(unit.name || '').trim(),
            sort_order: Number(unit.sort_order || unitIndex + 1),
            start_date: unit.start_date || '',
            end_date: unit.end_date || '',
            notes: String(unit.notes || '').trim() || null,
            lessons: (unit.lessons || []).map((lesson, lessonIndex) => ({
                name: String(lesson.name || '').trim(),
                sort_order: Number(lesson.sort_order || lessonIndex + 1),
                description: String(lesson.description || '').trim() || null,
                topics: (lesson.topics || []).map((topic, topicIndex) => ({
                    name: String(topic.name || '').trim(),
                    sort_order: Number(topic.sort_order || topicIndex + 1),
                    description: String(topic.description || '').trim() || null,
                })),
            })),
        })),
        is_active: Boolean(data.is_active),
    });

    if (courseOfferingEditId.value) {
        courseOfferingForm
            .transform(payloadTransform)
            .put(
                route('school.academic_planning.offerings.update', courseOfferingEditId.value),
                submitOptions({ successMessage: 'تم تعديل المقرر بنجاح.' })
            );
        return;
    }

    courseOfferingForm
        .transform(payloadTransform)
        .post(
            route('school.academic_planning.offerings.store'),
            submitOptions({
                successMessage: 'تم إنشاء المقرر بنجاح.',
                onSuccess: () => {
                    resetCourseOfferingForm();
                    keepAddFlowContext(offeringSectionRef);
                },
            })
        );
};

const removeCourseOffering = async (offeringId) => {
    if (!(await confirmAction('سيتم حذف المقرر المعتمد من الصف المحدد. هل تريد المتابعة؟', { title: 'حذف المقرر المعتمد', variant: 'danger', confirmText: 'نعم، احذف المقرر' }))) return;
    guardedDelete(route('school.academic_planning.offerings.destroy', offeringId), 'تم حذف المقرر بنجاح.');
};

const scheduleEditId = ref(null);
const isScheduleModalOpen = ref(false);
const scheduleForm = useForm({
    school_term_id: filterForm.value.term_id || props.terms[0]?.id || '',
    school_timetable_version_id: filterForm.value.version_id || '',
    school_stage_id: defaultStageId.value,
    school_classroom_id: firstClassroomForStage(defaultStageId.value),
    school_subject_id: defaultSubjectId.value,
    teacher_user_id: '',
    schedule_scope: filterForm.value.scope || 'WEEKLY',
    day_of_week: 0,
    day_of_month: 1,
    session_date: '',
    session_index: 1,
    starts_at: '',
    ends_at: '',
    notes: '',
    is_active: true,
});

const classroomsForSelectedStage = computed(() =>
    classroomOptions.value.filter((classroom) => Number(classroom.school_stage_id) === Number(scheduleForm.school_stage_id))
);
const scheduleGradeName = ref('');
const scheduleGradeOptions = computed(() => gradeOptionsForStage(scheduleForm.school_stage_id));
const classroomsForSelectedStageAndGrade = computed(() =>
    classroomsForSelectedStage.value.filter((classroom) =>
        !scheduleGradeName.value || normalizeGradeName(classroom.grade_name) === normalizeGradeName(scheduleGradeName.value)
    )
);

const teachersForSelectedSubject = computed(() => teachersForSubject(scheduleForm.school_subject_id));

watch(
    () => scheduleForm.school_stage_id,
    () => {
        if (!scheduleGradeName.value || !scheduleGradeOptions.value.includes(scheduleGradeName.value)) {
            scheduleGradeName.value = scheduleGradeOptions.value[0] || '';
        }

        const classroomIds = classroomsForSelectedStageAndGrade.value.map((classroom) => Number(classroom.id));
        if (!classroomIds.includes(Number(scheduleForm.school_classroom_id))) {
            scheduleForm.school_classroom_id = classroomsForSelectedStageAndGrade.value[0]?.id || '';
        }
    },
    { immediate: true }
);

watch(
    () => scheduleGradeName.value,
    () => {
        const classroomIds = classroomsForSelectedStageAndGrade.value.map((classroom) => Number(classroom.id));
        if (!classroomIds.includes(Number(scheduleForm.school_classroom_id))) {
            scheduleForm.school_classroom_id =
                firstClassroomForStageAndGrade(scheduleForm.school_stage_id, scheduleGradeName.value) || '';
        }
    }
);

watch(
    () => scheduleForm.school_subject_id,
    () => {
        const teacherIds = teachersForSelectedSubject.value.map((teacher) => Number(teacher.id));
        if (!teacherIds.includes(Number(scheduleForm.teacher_user_id))) {
            scheduleForm.teacher_user_id = teachersForSelectedSubject.value[0]?.id || '';
        }
    },
    { immediate: true }
);

watch(
    () => filterForm.value.term_id,
    () => {
        filterForm.value.version_id = '';
        if (!timetableVersionEditId.value) {
            timetableVersionForm.school_term_id = filterForm.value.term_id || props.terms[0]?.id || '';
        }
        if (!scheduleEditId.value) {
            scheduleForm.school_timetable_version_id = '';
        }
    }
);

watch(
    () => filterForm.value.stage_id,
    () => {
        const validGrades = scheduleGradeFilterOptions.value;
        if (
            filterForm.value.grade_name
            && !validGrades.includes(normalizeGradeName(filterForm.value.grade_name))
        ) {
            filterForm.value.grade_name = '';
        }

        const filteredIds = classroomOptionsForFilters.value.map((classroom) => Number(classroom.id));
        if (!filteredIds.includes(Number(filterForm.value.classroom_id))) {
            filterForm.value.classroom_id = '';
        }
    }
);

watch(
    () => filterForm.value.version_id,
    () => {
        if (!scheduleEditId.value) {
            scheduleForm.school_timetable_version_id = filterForm.value.version_id || '';
        }
    }
);

watch(
    () => filterForm.value.grade_name,
    () => {
        const filteredIds = classroomOptionsForFilters.value.map((classroom) => Number(classroom.id));
        if (!filteredIds.includes(Number(filterForm.value.classroom_id))) {
            filterForm.value.classroom_id = '';
        }
    }
);

const isWeeklyScope = computed(() => scheduleForm.schedule_scope === 'WEEKLY');
const isMonthlyScope = computed(() => scheduleForm.schedule_scope === 'MONTHLY');
const isTermScope = computed(() => scheduleForm.schedule_scope === 'TERM');
const selectedScheduleStage = computed(
    () => (props.structureStages || []).find((stage) => Number(stage.id) === Number(scheduleForm.school_stage_id)) || null
);
const activeWeeklyOffDays = computed(() => {
    const configured = Array.isArray(props.calendarSettings?.weekly_off_days)
        ? props.calendarSettings.weekly_off_days
        : (calendarForm.value.weekly_off_days || []);

    return [...new Set(configured.map((day) => Number(day)).filter((day) => Number.isInteger(day) && day >= 0 && day <= 6))];
});

const normalizeTimeValue = (value) => {
    const normalized = String(value || '').trim();
    if (!normalized) return '';
    return normalized.slice(0, 5);
};

const isSessionDateHoliday = (dateValue) => {
    const date = String(dateValue || '').trim();
    if (!date) return false;

    return (holidays.value || []).some((holiday) => {
        if (!holiday?.is_active || !holiday?.start_date || !holiday?.end_date) return false;
        return date >= holiday.start_date && date <= holiday.end_date;
    });
};

const resetScheduleForm = () => {
    scheduleEditId.value = null;
    scheduleForm.reset();
    scheduleForm.school_term_id = filterForm.value.term_id || props.terms[0]?.id || '';
    scheduleForm.school_timetable_version_id = filterForm.value.version_id || '';
    scheduleForm.school_stage_id = filterForm.value.stage_id || defaultStageId.value;
    scheduleGradeName.value = filterForm.value.grade_name || gradeOptionsForStage(scheduleForm.school_stage_id)[0] || '';
    scheduleForm.school_classroom_id =
        filterForm.value.classroom_id
        || firstClassroomForStageAndGrade(scheduleForm.school_stage_id, scheduleGradeName.value)
        || firstClassroomForStage(scheduleForm.school_stage_id);
    scheduleForm.school_subject_id = defaultSubjectId.value;
    scheduleForm.teacher_user_id = teachersForSubject(defaultSubjectId.value)[0]?.id || '';
    scheduleForm.schedule_scope = filterForm.value.scope || 'WEEKLY';
    scheduleForm.day_of_week = 0;
    scheduleForm.day_of_month = 1;
    scheduleForm.session_date = '';
    scheduleForm.session_index = 1;
    scheduleForm.is_active = true;
    scheduleForm.clearErrors();
};

const openCreateScheduleModal = () => {
    resetScheduleForm();
    isScheduleModalOpen.value = true;
};

const closeScheduleModal = () => {
    isScheduleModalOpen.value = false;
    resetScheduleForm();
};

const editSchedule = (entry) => {
    scheduleEditId.value = entry.id;
    scheduleForm.school_term_id = entry.school_term_id || filterForm.value.term_id || '';
    scheduleForm.school_timetable_version_id = entry.school_timetable_version_id || '';
    scheduleForm.school_stage_id = entry.school_stage_id || '';
    scheduleForm.school_classroom_id = entry.school_classroom_id || '';
    const matchedClassroom = classroomOptions.value.find(
        (classroom) => Number(classroom.id) === Number(entry.school_classroom_id)
    );
    const entryGradeName = normalizeGradeName(entry.classroom?.grade_name || matchedClassroom?.grade_name);
    scheduleGradeName.value = scheduleGradeOptions.value.includes(entryGradeName)
        ? entryGradeName
        : (scheduleGradeOptions.value[0] || '');
    scheduleForm.school_subject_id = entry.school_subject_id || '';
    scheduleForm.teacher_user_id = entry.teacher_user_id || '';
    scheduleForm.schedule_scope = entry.schedule_scope || 'WEEKLY';
    scheduleForm.day_of_week = entry.day_of_week ?? 0;
    scheduleForm.day_of_month = entry.day_of_month ?? 1;
    scheduleForm.session_date = entry.session_date || '';
    scheduleForm.session_index = Number(entry.session_index || 1);
    scheduleForm.starts_at = entry.starts_at ? String(entry.starts_at).slice(0, 5) : '';
    scheduleForm.ends_at = entry.ends_at ? String(entry.ends_at).slice(0, 5) : '';
    scheduleForm.notes = entry.notes || '';
    scheduleForm.is_active = Boolean(entry.is_active);
    scheduleForm.clearErrors();
};

const openEditScheduleModal = (entry) => {
    editSchedule(entry);
    isScheduleModalOpen.value = true;
};

const schedulePayload = () => {
    return {
        school_term_id: scheduleForm.school_term_id || null,
        school_timetable_version_id: scheduleForm.school_timetable_version_id || null,
        school_stage_id: scheduleForm.school_stage_id || null,
        school_classroom_id: scheduleForm.school_classroom_id || null,
        school_subject_id: scheduleForm.school_subject_id || null,
        teacher_user_id: scheduleForm.teacher_user_id || null,
        schedule_scope: scheduleForm.schedule_scope,
        day_of_week: isWeeklyScope.value ? scheduleForm.day_of_week : null,
        day_of_month: isMonthlyScope.value ? scheduleForm.day_of_month : null,
        session_date: isTermScope.value ? scheduleForm.session_date || null : null,
        session_index: scheduleForm.session_index,
        starts_at: scheduleForm.starts_at || null,
        ends_at: scheduleForm.ends_at || null,
        notes: scheduleForm.notes || null,
        is_active: scheduleForm.is_active,
    };
};

const submitSchedule = () => {
    scheduleForm.clearErrors();

    const startsAt = normalizeTimeValue(scheduleForm.starts_at);
    const endsAt = normalizeTimeValue(scheduleForm.ends_at);

    if ((startsAt && !endsAt) || (!startsAt && endsAt)) {
        scheduleForm.setError('ends_at', 'وقت البداية ووقت النهاية يجب إدخالهما معًا.');
        return;
    }

    if (startsAt && endsAt && endsAt <= startsAt) {
        scheduleForm.setError('ends_at', 'وقت النهاية يجب أن يكون بعد وقت البداية.');
        return;
    }

    const stage = selectedScheduleStage.value;
    if (stage && startsAt && endsAt) {
        const stageStart = normalizeTimeValue(stage.school_day_start_time);
        const stageEnd = normalizeTimeValue(stage.school_day_end_time);

        if (stageStart && stageEnd && (startsAt < stageStart || endsAt > stageEnd)) {
            scheduleForm.setError('starts_at', 'لا يمكن حفظ البيانات لأن الوقت خارج مواعيد اليوم الدراسي المعتمدة.');
            return;
        }
    }

    if (isWeeklyScope.value && activeWeeklyOffDays.value.includes(Number(scheduleForm.day_of_week))) {
        scheduleForm.setError('day_of_week', 'لا يمكن إضافة الجدول الدراسي لأن اليوم المحدد يوافق عطلة أسبوعية.');
        return;
    }

    if (isTermScope.value && scheduleForm.session_date) {
        const sessionDate = String(scheduleForm.session_date).trim();
        if (sessionDate) {
            const dayOfWeek = new Date(`${sessionDate}T00:00:00`).getDay();
            if (activeWeeklyOffDays.value.includes(dayOfWeek)) {
                scheduleForm.setError('session_date', 'لا يمكن إضافة الجدول الدراسي لأن اليوم المحدد يوافق عطلة أسبوعية.');
                return;
            }

            if (isSessionDateHoliday(sessionDate)) {
                scheduleForm.setError('session_date', 'لا يمكن إضافة الجدول الدراسي لأن التاريخ المحدد يوافق عطلة رسمية أو استثنائية.');
                return;
            }
        }
    }

    if (scheduleEditId.value) {
        scheduleForm
            .transform(() => schedulePayload())
            .put(
                withPlanningPage(route('school.academic_planning.schedules.update', scheduleEditId.value)),
                submitOptions({
                    successMessage: 'تم تعديل العنصر بنجاح.',
                    onSuccess: () => {
                        closeScheduleModal();
                        keepAddFlowContext(scheduleSectionRef);
                    },
                })
            );
        return;
    }

    scheduleForm.transform(() => schedulePayload()).post(
        withPlanningPage(route('school.academic_planning.schedules.store')),
        submitOptions({
            successMessage: 'تم إضافة العنصر بنجاح.',
            onSuccess: () => {
                closeScheduleModal();
                keepAddFlowContext(scheduleSectionRef);
            },
        })
    );
};

const removeSchedule = async (scheduleId) => {
    if (!(await confirmAction('هل تريد حذف هذا السجل من الجدول؟', { title: 'حذف السجل', variant: 'danger', confirmText: 'نعم، احذف السجل' }))) return;
    guardedDelete(route('school.academic_planning.schedules.destroy', scheduleId), 'تم حذف العنصر بنجاح.');
};

const weekDayLabel = (value) => props.weekDays.find((item) => Number(item.value) === Number(value))?.label || '-';
const scopeLabel = (value) => props.scopeOptions.find((item) => item.value === value)?.label || value || '-';
const scopeClass = (value) => {
    if (value === 'WEEKLY') return 'bg-blue-500/20 text-blue-200';
    if (value === 'MONTHLY') return 'bg-emerald-500/20 text-emerald-200';
    return 'bg-amber-500/20 text-amber-200';
};

const scheduleSlotLabel = (entry) => {
    if (entry.schedule_scope === 'WEEKLY') {
        return `${weekDayLabel(entry.day_of_week)} - الحصة ${entry.session_index}`;
    }

    if (entry.schedule_scope === 'MONTHLY') {
        return `يوم ${entry.day_of_month} - الحصة ${entry.session_index}`;
    }

    return `${entry.session_date || '-'} - الحصة ${entry.session_index}`;
};

const scheduleTimeLabel = (entry) => {
    const start = entry.starts_at ? String(entry.starts_at).slice(0, 5) : null;
    const end = entry.ends_at ? String(entry.ends_at).slice(0, 5) : null;
    if (!start && !end) return '-';
    return `${start || '-'} - ${end || '-'}`;
};

const readRequestedWeeklyGridPeriodCount = () => {
    const query = String(page.url || '').split('?')[1] || '';
    const requested = Number(new URLSearchParams(query).get('period_count') || 0);

    if (!Number.isInteger(requested) || requested < 1 || requested > 20) {
        return 0;
    }

    return requested;
};

const weeklyGridEntries = computed(() => (Array.isArray(props.weeklyGrid?.entries) ? props.weeklyGrid.entries : []));
const isWeeklyGridScope = computed(() => filterForm.value.scope === 'WEEKLY');
const teacherAvailabilityMap = computed(() => props.teacherAvailabilities || {});
const weeklyGridSlotKey = (dayOfWeek, sessionIndex) => `${Number(dayOfWeek)}:${Number(sessionIndex)}`;
const weeklyGridCells = ref({});
const scheduleGridPeriodCount = ref(readRequestedWeeklyGridPeriodCount() || 8);
const weeklyGridForm = useForm({
    school_term_id: filterForm.value.term_id || props.terms[0]?.id || '',
    school_timetable_version_id: filterForm.value.version_id || '',
    school_stage_id: filterForm.value.stage_id || '',
    grade_name: filterForm.value.grade_name || '',
    school_classroom_id: filterForm.value.classroom_id || '',
    period_count: scheduleGridPeriodCount.value,
    cells: [],
});

const normalizeComparableId = (value) => Number(value || 0);
const normalizeComparableGradeName = (value) => {
    const normalized = String(value || '').trim();
    return normalized === '' ? '' : normalizeGradeName(normalized);
};

const areWeeklyGridFiltersApplied = computed(() =>
    isWeeklyGridScope.value
    && normalizeComparableId(filterForm.value.term_id) === normalizeComparableId(props.selectedTermId)
    && normalizeComparableId(filterForm.value.stage_id) === normalizeComparableId(props.selectedStageId)
    && normalizeComparableId(filterForm.value.classroom_id) === normalizeComparableId(props.selectedClassroomId)
    && normalizeComparableId(filterForm.value.version_id) === normalizeComparableId(props.selectedVersionId)
    && normalizeComparableGradeName(filterForm.value.grade_name) === normalizeComparableGradeName(props.selectedGradeName)
);

const isWeeklyGridContextReady = computed(() =>
    Boolean(filterForm.value.term_id && filterForm.value.stage_id && filterForm.value.classroom_id)
);

const selectedWeeklyGridClassroom = computed(() =>
    classroomOptions.value.find((classroom) => Number(classroom.id) === Number(filterForm.value.classroom_id)) || null
);

const selectedWeeklyGridGradeName = computed(() => {
    if (selectedWeeklyGridClassroom.value?.grade_name) {
        return normalizeGradeName(selectedWeeklyGridClassroom.value.grade_name);
    }

    const normalized = String(filterForm.value.grade_name || '').trim();
    return normalized !== '' ? normalizeGradeName(normalized) : '';
});

const visibleWeeklyGridWeekDays = computed(() =>
    (props.weekDays || []).filter((day) => !activeWeeklyOffDays.value.includes(Number(day.value)))
);

const weeklyOffDayLabels = computed(() =>
    (props.weekDays || [])
        .filter((day) => activeWeeklyOffDays.value.includes(Number(day.value)))
        .map((day) => day.label)
);

const loadedWeeklyGridMaxPeriod = computed(() =>
    weeklyGridEntries.value.reduce((max, entry) => Math.max(max, Number(entry?.session_index || 0)), 0)
);

const minimumWeeklyGridPeriodCount = computed(() => Math.max(1, loadedWeeklyGridMaxPeriod.value));
const weeklyGridPeriods = computed(() =>
    Array.from({ length: Math.max(1, Number(scheduleGridPeriodCount.value || 1)) }, (_, index) => index + 1)
);

const createWeeklyGridCell = (dayOfWeek, sessionIndex, overrides = {}) => ({
    id: null,
    day_of_week: Number(dayOfWeek),
    session_index: Number(sessionIndex),
    school_subject_id: '',
    teacher_user_id: '',
    starts_at: '',
    ends_at: '',
    notes: '',
    is_active: true,
    ...overrides,
});

const cloneWeeklyGridCell = (cell) => ({
    id: cell?.id || null,
    day_of_week: Number(cell?.day_of_week || 0),
    session_index: Number(cell?.session_index || 0),
    school_subject_id: cell?.school_subject_id ? Number(cell.school_subject_id) : '',
    teacher_user_id: cell?.teacher_user_id ? Number(cell.teacher_user_id) : '',
    starts_at: normalizeTimeInputValue(cell?.starts_at),
    ends_at: normalizeTimeInputValue(cell?.ends_at),
    notes: cell?.notes || '',
    is_active: cell?.is_active !== false,
});

const ensureWeeklyGridCell = (dayOfWeek, sessionIndex) => {
    const key = weeklyGridSlotKey(dayOfWeek, sessionIndex);
    if (!weeklyGridCells.value[key]) {
        weeklyGridCells.value[key] = createWeeklyGridCell(dayOfWeek, sessionIndex);
    }

    return weeklyGridCells.value[key];
};

const weeklyGridCellValue = (dayOfWeek, sessionIndex) =>
    weeklyGridCells.value[weeklyGridSlotKey(dayOfWeek, sessionIndex)] || createWeeklyGridCell(dayOfWeek, sessionIndex);

const rebuildWeeklyGridState = () => {
    const requestedPeriodCount = readRequestedWeeklyGridPeriodCount();
    const resolvedPeriodCount = Math.max(8, loadedWeeklyGridMaxPeriod.value, requestedPeriodCount, 1);
    scheduleGridPeriodCount.value = Math.min(20, resolvedPeriodCount);

    const nextState = {};

    visibleWeeklyGridWeekDays.value.forEach((day) => {
        for (let period = 1; period <= scheduleGridPeriodCount.value; period += 1) {
            nextState[weeklyGridSlotKey(day.value, period)] = createWeeklyGridCell(day.value, period);
        }
    });

    weeklyGridEntries.value.forEach((entry) => {
        const key = weeklyGridSlotKey(entry.day_of_week, entry.session_index);
        if (!nextState[key]) {
            return;
        }

        nextState[key] = createWeeklyGridCell(entry.day_of_week, entry.session_index, cloneWeeklyGridCell(entry));
    });

    weeklyGridCells.value = nextState;
    weeklyGridForm.clearErrors();
};

watch(
    [weeklyGridEntries, visibleWeeklyGridWeekDays, () => props.selectedTermId, () => props.selectedStageId, () => props.selectedClassroomId, () => props.selectedVersionId, () => page.url],
    rebuildWeeklyGridState,
    { immediate: true, deep: true }
);

watch(
    () => scheduleGridPeriodCount.value,
    (value) => {
        const normalized = Math.min(20, Math.max(minimumWeeklyGridPeriodCount.value, Number(value || 1)));
        if (normalized !== Number(value || 0)) {
            scheduleGridPeriodCount.value = normalized;
            return;
        }

        visibleWeeklyGridWeekDays.value.forEach((day) => {
            for (let period = 1; period <= normalized; period += 1) {
                ensureWeeklyGridCell(day.value, period);
            }
        });

        Object.keys(weeklyGridCells.value).forEach((key) => {
            const cell = weeklyGridCells.value[key];
            if (Number(cell?.session_index || 0) > normalized && !cell?.id) {
                delete weeklyGridCells.value[key];
            }
        });
    },
    { immediate: true }
);

const weeklyGridRows = computed(() =>
    visibleWeeklyGridWeekDays.value.map((day) => ({
        ...day,
        cells: weeklyGridPeriods.value.map((period) => weeklyGridCellValue(day.value, period)),
    }))
);

const weeklyGridDayCount = computed(() => Math.max(1, weeklyGridRows.value.length));

const weeklyGridDensityMode = computed(() => {
    const periods = weeklyGridPeriods.value.length;
    const days = weeklyGridDayCount.value;

    if (periods >= 9 || days >= 6) {
        return 'dense';
    }

    if (periods >= 7 || days >= 5) {
        return 'compact';
    }

    return 'comfortable';
});

const weeklyGridBoardStyle = computed(() => {
    const periods = Math.max(1, weeklyGridPeriods.value.length);
    const days = Math.max(1, weeklyGridDayCount.value);
    const density = weeklyGridDensityMode.value;

    const dayColumnWidth = density === 'dense'
        ? 86
        : density === 'compact'
            ? 96
            : 110;

    const headerHeight = density === 'dense'
        ? 36
        : density === 'compact'
            ? 40
            : 44;

    const boardHeight = density === 'dense'
        ? 'clamp(320px, calc(100dvh - 23rem), 540px)'
        : density === 'compact'
            ? 'clamp(360px, calc(100dvh - 22rem), 580px)'
        : 'clamp(400px, calc(100dvh - 21rem), 660px)';

    return {
        '--weekly-grid-card-padding': density === 'dense' ? '0.32rem' : density === 'compact' ? '0.42rem' : '0.55rem',
        '--weekly-grid-card-gap': density === 'dense' ? '0.22rem' : density === 'compact' ? '0.3rem' : '0.4rem',
        '--weekly-grid-control-height': density === 'dense' ? '1.55rem' : density === 'compact' ? '1.75rem' : '1.95rem',
        '--weekly-grid-select-font-size': density === 'dense' ? '0.66rem' : density === 'compact' ? '0.71rem' : '0.78rem',
        '--weekly-grid-meta-font-size': density === 'dense' ? '0.54rem' : density === 'compact' ? '0.6rem' : '0.68rem',
        '--weekly-grid-clear-font-size': density === 'dense' ? '0.56rem' : density === 'compact' ? '0.62rem' : '0.68rem',
        '--weekly-grid-day-font-size': density === 'dense' ? '0.8rem' : density === 'compact' ? '0.88rem' : '0.96rem',
        '--weekly-grid-header-font-size': density === 'dense' ? '0.68rem' : density === 'compact' ? '0.74rem' : '0.8rem',
        height: boardHeight,
        gridTemplateColumns: `${dayColumnWidth}px repeat(${periods}, minmax(0, 1fr))`,
        gridTemplateRows: `${headerHeight}px repeat(${days}, minmax(0, 1fr))`,
    };
});

const offeringMatchesWeeklyGridContext = (offering) => {
    if (!offering || !offering.is_active) return false;
    if (Number(offering.school_term_id || 0) !== Number(filterForm.value.term_id || 0)) return false;
    if (Number(offering.school_stage_id || 0) !== Number(filterForm.value.stage_id || 0)) return false;

    const selectedClassroomId = Number(filterForm.value.classroom_id || 0);
    const selectedGradeName = selectedWeeklyGridGradeName.value;
    const offeringClassroomId = Number(offering.school_classroom_id || 0);
    const offeringGradeName = normalizeComparableGradeName(
        offering?.stage_grade?.name || offering?.classroom?.grade_name || ''
    );

    if (offeringClassroomId > 0) {
        return offeringClassroomId === selectedClassroomId;
    }

    return selectedGradeName !== '' && offeringGradeName === selectedGradeName;
};

const weeklyGridOfferings = computed(() =>
    normalizedCourseOfferings.value.filter((offering) => offeringMatchesWeeklyGridContext(offering))
);

const weeklyGridSubjectsById = computed(() =>
    new Map((props.subjects || []).map((subject) => [Number(subject.id), subject]))
);

const weeklyGridTeachersById = computed(() =>
    new Map((props.teachers || []).map((teacher) => [Number(teacher.id), teacher]))
);

const weeklyGridSubjects = computed(() => {
    if (!isWeeklyGridContextReady.value) {
        return [];
    }

    if (!props.scheduleRules?.enforce_course_offerings) {
        return activeSubjects.value;
    }

    const subjectsById = new Map();
    weeklyGridOfferings.value.forEach((offering) => {
        const subject = activeSubjects.value.find((item) => Number(item.id) === Number(offering.school_subject_id));
        if (subject) {
            subjectsById.set(Number(subject.id), subject);
        }
    });

    return [...subjectsById.values()];
});

const subjectsForWeeklyGridCell = (cell) => {
    const currentSubject = weeklyGridSubjectsById.value.get(Number(cell?.school_subject_id || 0));
    if (!currentSubject) {
        return weeklyGridSubjects.value;
    }

    if (weeklyGridSubjects.value.some((subject) => Number(subject.id) === Number(currentSubject.id))) {
        return weeklyGridSubjects.value;
    }

    return [currentSubject, ...weeklyGridSubjects.value];
};

const teacherHasConfiguredAvailability = (teacherId) =>
    Array.isArray(teacherAvailabilityMap.value?.[teacherId]) && teacherAvailabilityMap.value[teacherId].length > 0;

const isTeacherAvailableForWeeklyGridSlot = (teacherId, dayOfWeek, sessionIndex) => {
    const normalizedTeacherId = Number(teacherId || 0);
    if (normalizedTeacherId <= 0) return false;

    const availabilities = teacherAvailabilityMap.value?.[normalizedTeacherId] || [];
    if (!teacherHasConfiguredAvailability(normalizedTeacherId)) {
        return true;
    }

    return availabilities.some((slot) =>
        Number(slot?.day_of_week) === Number(dayOfWeek) && Number(slot?.session_index) === Number(sessionIndex)
    );
};

const teachersForWeeklyGridSubject = (subjectId, dayOfWeek, sessionIndex) => {
    const normalizedSubjectId = Number(subjectId || 0);
    if (normalizedSubjectId <= 0) return [];

    if (!props.scheduleRules?.enforce_course_offerings) {
        return teachersForSubject(normalizedSubjectId).filter((teacher) =>
            isTeacherAvailableForWeeklyGridSlot(teacher.id, dayOfWeek, sessionIndex)
        );
    }

    const teachersById = new Map();
    const selectedClassroomId = Number(filterForm.value.classroom_id || 0);

    weeklyGridOfferings.value
        .filter((offering) => Number(offering.school_subject_id) === normalizedSubjectId)
        .forEach((offering) => {
            const assignment = offering?.teaching_assignment || null;
            const teacher = assignment?.teacher || null;

            if (!assignment?.is_active || !teacher) {
                return;
            }

            const scopedClassroomIds = (assignment.classrooms || []).map((classroom) => Number(classroom.id));
            if (scopedClassroomIds.length > 0) {
                if (!scopedClassroomIds.includes(selectedClassroomId)) {
                    return;
                }
            } else if (Number(offering.school_classroom_id || 0) !== selectedClassroomId) {
                return;
            }

            if (!isTeacherAvailableForWeeklyGridSlot(teacher.id, dayOfWeek, sessionIndex)) {
                return;
            }

            teachersById.set(Number(teacher.id), teacher);
        });

    return [...teachersById.values()];
};

const teachersForWeeklyGridCell = (cell, dayOfWeek, sessionIndex) => {
    const teachers = teachersForWeeklyGridSubject(cell?.school_subject_id, dayOfWeek, sessionIndex);
    const currentTeacher = weeklyGridTeachersById.value.get(Number(cell?.teacher_user_id || 0));

    if (!currentTeacher) {
        return teachers;
    }

    if (teachers.some((teacher) => Number(teacher.id) === Number(currentTeacher.id))) {
        return teachers;
    }

    return [currentTeacher, ...teachers];
};

const updateWeeklyGridCell = (dayOfWeek, sessionIndex, field, value) => {
    const key = weeklyGridSlotKey(dayOfWeek, sessionIndex);
    const current = cloneWeeklyGridCell(ensureWeeklyGridCell(dayOfWeek, sessionIndex));

    weeklyGridCells.value[key] = {
        ...current,
        [field]: value,
    };
};

const onWeeklyGridSubjectChange = (dayOfWeek, sessionIndex, subjectId) => {
    const normalizedSubjectId = Number(subjectId || 0);
    updateWeeklyGridCell(dayOfWeek, sessionIndex, 'school_subject_id', normalizedSubjectId > 0 ? normalizedSubjectId : '');

    const nextTeacherOptions = teachersForWeeklyGridSubject(normalizedSubjectId, dayOfWeek, sessionIndex);
    const selectedTeacherId = Number(weeklyGridCellValue(dayOfWeek, sessionIndex).teacher_user_id || 0);
    const teacherStillValid = nextTeacherOptions.some((teacher) => Number(teacher.id) === selectedTeacherId);

    updateWeeklyGridCell(
        dayOfWeek,
        sessionIndex,
        'teacher_user_id',
        teacherStillValid ? selectedTeacherId : (nextTeacherOptions[0]?.id || '')
    );
};

const onWeeklyGridTeacherChange = (dayOfWeek, sessionIndex, teacherUserId) => {
    const normalizedTeacherId = Number(teacherUserId || 0);
    updateWeeklyGridCell(dayOfWeek, sessionIndex, 'teacher_user_id', normalizedTeacherId > 0 ? normalizedTeacherId : '');
};

const clearWeeklyGridCell = (dayOfWeek, sessionIndex) => {
    const current = weeklyGridCellValue(dayOfWeek, sessionIndex);
    weeklyGridCells.value[weeklyGridSlotKey(dayOfWeek, sessionIndex)] = createWeeklyGridCell(dayOfWeek, sessionIndex, {
        id: current?.id || null,
    });
};

const weeklyGridCellHasMetadata = (cell) =>
    Boolean(normalizeTimeInputValue(cell?.starts_at) || normalizeTimeInputValue(cell?.ends_at) || String(cell?.notes || '').trim());

const weeklyGridCellHasContent = (cell) =>
    Boolean(cell?.school_subject_id || cell?.teacher_user_id || weeklyGridCellHasMetadata(cell));

const weeklyGridCellMetadataLabel = (cell) => {
    const timeLabel = scheduleTimeLabel({
        starts_at: cell?.starts_at || null,
        ends_at: cell?.ends_at || null,
    });

    if (timeLabel !== '-') {
        return timeLabel;
    }

    if (String(cell?.notes || '').trim() !== '') {
        return 'يوجد وصف محفوظ';
    }

    return '';
};

const buildWeeklyGridPayload = () => ({
    school_term_id: filterForm.value.term_id || '',
    school_timetable_version_id: filterForm.value.version_id || '',
    school_stage_id: filterForm.value.stage_id || '',
    grade_name: selectedWeeklyGridGradeName.value || '',
    school_classroom_id: filterForm.value.classroom_id || '',
    period_count: scheduleGridPeriodCount.value,
    cells: visibleWeeklyGridWeekDays.value.flatMap((day) =>
        weeklyGridPeriods.value.map((period) => {
            const cell = weeklyGridCellValue(day.value, period);

            return {
                day_of_week: Number(day.value),
                session_index: Number(period),
                school_subject_id: cell.school_subject_id || null,
                teacher_user_id: cell.teacher_user_id || null,
                starts_at: normalizeTimeInputValue(cell.starts_at) || null,
                ends_at: normalizeTimeInputValue(cell.ends_at) || null,
                notes: String(cell.notes || '').trim() || null,
                is_active: Boolean(cell.is_active ?? true),
            };
        })
    ),
});

const submitWeeklyGrid = () => {
    if (!isWeeklyGridContextReady.value) {
        showCrudError('اختر الترم والمرحلة والفصل قبل حفظ الجدول.');
        return;
    }

    if (!areWeeklyGridFiltersApplied.value) {
        showCrudError('حدّث العرض أولًا حتى تتم مزامنة الجدول مع الفلاتر الحالية.');
        return;
    }

    weeklyGridForm.transform(() => buildWeeklyGridPayload()).post(
        withPlanningPage(route('school.academic_planning.schedules.grid.sync')),
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showCrudSuccess('تم حفظ الجدول الدراسي بنجاح.');
            },
            onError: (errors) => {
                const firstError = Object.values(errors || {}).find((value) => typeof value === 'string' && value.trim() !== '');
                if (firstError) {
                    showCrudError(firstError);
                }
            },
        }
    );
};

const exportWeeklyGrid = (format) => {
    if (!isWeeklyGridContextReady.value) {
        showCrudError('اختر الترم والمرحلة والفصل قبل التصدير.');
        return;
    }

    if (!areWeeklyGridFiltersApplied.value) {
        showCrudError('حدّث العرض أولًا قبل التصدير.');
        return;
    }

    window.location.href = route('school.academic_planning.schedules.grid.export', {
        format,
        school_term_id: filterForm.value.term_id || undefined,
        school_timetable_version_id: filterForm.value.version_id || undefined,
        school_stage_id: filterForm.value.stage_id || undefined,
        grade_name: selectedWeeklyGridGradeName.value || undefined,
        school_classroom_id: filterForm.value.classroom_id || undefined,
        period_count: scheduleGridPeriodCount.value || undefined,
    });
};

const quickSetupVisible = ref(false);
const quickSetupLoading = ref(false);
const quickSetupError = ref('');
const quickSetupSteps = ref([]);
const quickSetupCurrentStepKey = ref('');

const quickSetupStorageKey = computed(() => {
    const schoolId = props.school?.id || 'none';
    const userId = currentUser.value?.id || 'guest';
    return `quick_setup:last_step:${schoolId}:${userId}`;
});

const quickSetupStepByKey = computed(() => {
    const map = new Map();
    for (const step of quickSetupSteps.value) {
        map.set(step.key, step);
    }

    return map;
});

const hasQuickSetupStep = (stepKey) => quickSetupStepByKey.value.has(stepKey);

const readStoredQuickSetupStep = () => {
    if (typeof window === 'undefined') return '';

    try {
        return String(window.localStorage.getItem(quickSetupStorageKey.value) || '').trim();
    } catch {
        return '';
    }
};

const storeQuickSetupStep = (stepKey) => {
    if (typeof window === 'undefined') return;

    try {
        if (!stepKey) {
            window.localStorage.removeItem(quickSetupStorageKey.value);
            return;
        }

        window.localStorage.setItem(quickSetupStorageKey.value, stepKey);
    } catch {
        // Ignore persistence failures in restricted environments.
    }
};

watch(
    () => quickSetupCurrentStepKey.value,
    (stepKey) => {
        if (!stepKey) return;
        storeQuickSetupStep(stepKey);
    }
);

const findFirstQuickSetupStepKey = () => quickSetupSteps.value[0]?.key || '';

const selectQuickSetupStep = (preferredStepKey = '') => {
    const firstIncompleteFromApi = quickSetupSteps.value.find((step) => step.status !== 'completed' && step.editable)?.key || '';
    const currentStep = quickSetupCurrentStepKey.value;
    const storedStep = readStoredQuickSetupStep();
    const candidateStepKeys = [
        preferredStepKey,
        currentStep,
        storedStep,
        firstIncompleteFromApi,
        findFirstQuickSetupStepKey(),
    ];

    const resolved = candidateStepKeys.find((stepKey) => stepKey && hasQuickSetupStep(stepKey)) || '';
    quickSetupCurrentStepKey.value = resolved || findFirstQuickSetupStepKey();
};

const refreshQuickSetupStatus = async (preferredStepKey = '') => {
    quickSetupLoading.value = true;
    quickSetupError.value = '';

    try {
        const response = await axios.get(route('api.school.quick_setup.status'));
        const steps = Array.isArray(response?.data?.data?.steps) ? response.data.data.steps : [];
        quickSetupSteps.value = steps;

        const preferred = preferredStepKey || response?.data?.data?.first_incomplete_step_key || '';
        selectQuickSetupStep(preferred);
    } catch (error) {
        quickSetupError.value = resolveApiErrorMessage(error, 'تعذر تحميل حالة الإعدادات السريعة.');
        quickSetupSteps.value = [];
        quickSetupCurrentStepKey.value = '';
    } finally {
        quickSetupLoading.value = false;
    }
};

const openQuickSetup = async (preferredStepKey = '') => {
    quickSetupVisible.value = true;
    await refreshQuickSetupStatus(preferredStepKey);
};

const closeQuickSetup = () => {
    quickSetupVisible.value = false;
};

const moveQuickSetupStep = (direction) => {
    const currentIndex = quickSetupSteps.value.findIndex((step) => step.key === quickSetupCurrentStepKey.value);
    if (currentIndex < 0) return;

    const nextIndex = currentIndex + direction;
    if (nextIndex < 0 || nextIndex >= quickSetupSteps.value.length) return;

    const currentStep = quickSetupSteps.value[currentIndex];
    if (direction > 0 && currentStep?.blocked) return;

    quickSetupCurrentStepKey.value = quickSetupSteps.value[nextIndex].key;
};

const quickSetupSectionRefs = {
    stages: stageSectionRef,
    academic_years: yearSectionRef,
    terms: termSectionRef,
    calendar_settings: calendarSectionRef,
    holidays: holidaySectionRef,
    leave_types: leaveTypeSectionRef,
    classrooms: classroomSectionRef,
    subjects: subjectSectionRef,
    timetable_copy: versionSectionRef,
    timetables: scheduleSectionRef,
};

const quickSetupPageMap = {
    stages: PLANNING_PAGE_STAGES,
    academic_years: PLANNING_PAGE_YEARS,
    terms: PLANNING_PAGE_TERMS,
    calendar_settings: PLANNING_PAGE_CALENDAR,
    holidays: PLANNING_PAGE_CALENDAR,
    leave_types: PLANNING_PAGE_CALENDAR,
    classrooms: PLANNING_PAGE_CLASSROOMS,
    subjects: PLANNING_PAGE_SUBJECTS,
    timetable_copy: PLANNING_PAGE_SCHEDULES,
    timetables: PLANNING_PAGE_SCHEDULES,
};

const openQuickSetupStepSection = (stepKey) => {
    const step = quickSetupStepByKey.value.get(stepKey);
    if (!step || !step.editable || step.blocked) {
        return;
    }

    closeQuickSetup();

    if (stepKey === 'school_users') {
        router.get(route('manager.structure.index'), {
            quick_setup: 1,
            quick_setup_step: 'school_users',
        });
        return;
    }

    if (stepKey === 'classrooms') {
        router.get(route('school.student_structure.index'));
        return;
    }

    const targetPage = quickSetupPageMap[stepKey] || '';
    if (targetPage && targetPage !== currentPlanningPage.value) {
        router.get(route('school.academic_planning.index'), {
            page: targetPage,
            quick_setup: 1,
            quick_setup_step: stepKey,
        });
        return;
    }

    const sectionRef = quickSetupSectionRefs[stepKey];
    if (!sectionRef) return;

    keepAddFlowContext(sectionRef);
};

const parseQuickSetupQuery = () => {
    if (typeof window === 'undefined') {
        return {
            open: false,
            stepKey: '',
        };
    }

    const params = new URLSearchParams(window.location.search);
    return {
        open: params.get('quick_setup') === '1',
        stepKey: String(params.get('quick_setup_step') || '').trim(),
    };
};

onMounted(async () => {
    if (canManageLeaveTypes.value) await refreshLeaveTypes();
    if (canManageCalendar.value) await loadCalendar();
    if (canManageHolidays.value) await loadHolidays();

    const quickSetupQuery = parseQuickSetupQuery();
    if (quickSetupQuery.open) {
        await openQuickSetup(quickSetupQuery.stepKey);
    }
});
</script>

<template>
    <Head :title="currentPlanningPageLabel" />

    <RoleLayout :title="currentPlanningPageLabel" :role="roleForLayout" :permissions="props.permissions">
        <div v-if="!school" class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm text-amber-200">
            لا يوجد ربط لمدرسة لهذا الحساب حاليًا.
        </div>

        <div v-else class="flex flex-col gap-6">
            <div
                v-if="actionFeedback.visible"
                class="sticky top-3 z-30 rounded-xl border px-4 py-3 text-sm font-semibold shadow"
                :class="actionFeedback.type === 'success'
                    ? 'border-emerald-500/50 bg-emerald-500/15 text-emerald-100'
                    : 'border-red-500/50 bg-red-500/15 text-red-100'"
            >
                {{ actionFeedback.message }}
            </div>

            <section
                v-if="defaultDataProvisioning"
                class="rounded-2xl border p-4"
                :class="isDefaultDataImported ? 'border-emerald-500/40 bg-emerald-500/10' : 'border-blue-500/40 bg-blue-500/10'"
            >
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div class="space-y-2">
                        <p class="inline-flex items-center gap-1 text-xs" :class="isDefaultDataImported ? 'text-emerald-200' : 'text-blue-200'">
                            <School class="h-3.5 w-3.5" />
                            <span>{{ isDefaultDataImported ? 'تمت تهيئة البيانات الأساسية للمدرسة' : 'البيانات الافتراضية المدرسية' }}</span>
                        </p>
                        <p class="text-sm leading-7 text-gray-100">
                            <template v-if="isDefaultDataImported">
                                تمت تهيئة النسخة المدرسية من القوالب العامة، وأصبحت الآن المواد والعطل وأنواع الإجازات والأعوام الدراسية والمراحل بيانات خاصة بهذه المدرسة فقط. ويمكنك أيضًا استيراد أي عناصر جديدة مطابقة لاحقًا يدويًا دون تعديل البيانات الحالية.
                            </template>
                            <template v-else-if="hasDefaultDataTemplates">
                                توجد قوالب عامة جاهزة على مستوى المنصة. يمكنك استيرادها مرة واحدة لتجهيز المدرسة بسرعة ثم تخصيصها لاحقًا من داخل المدرسة نفسها.
                            </template>
                            <template v-else>
                                لا توجد قوالب عامة مفعلة حاليًا على مستوى المنصة، لذلك لن يظهر زر الاستيراد حتى يضيف السوبر أدمن البيانات الافتراضية أولًا.
                            </template>
                        </p>
                        <p v-if="hasDefaultDataTemplates" class="text-xs text-gray-200">
                            {{ defaultDataTemplateSummaryText }}
                        </p>
                        <div v-if="hasDefaultDataTemplates" class="flex flex-wrap gap-2">
                            <span
                                v-for="item in defaultDataTemplateCountItems"
                                :key="item.key"
                                class="rounded-full border px-3 py-1 text-xs"
                                :class="item.count > 0
                                    ? 'border-cyan-400/40 bg-cyan-500/10 text-cyan-100'
                                    : 'border-slate-600/60 bg-slate-800/60 text-slate-400'"
                            >
                                {{ item.label }}: {{ item.count }}
                            </span>
                        </div>
                        <p v-if="isDefaultDataImported && defaultDataProvisioning.imported_at" class="text-xs text-gray-300">
                            تاريخ الاستيراد: {{ formatProvisioningDate(defaultDataProvisioning.imported_at) }}
                            <span v-if="defaultDataImportedBy">، بواسطة {{ defaultDataImportedBy }}</span>
                        </p>
                        <p v-else-if="!canImportDefaultData && hasDefaultDataTemplates" class="text-xs text-gray-300">
                            يتطلب الاستيراد مدير المدرسة أو مستخدمًا يملك الصلاحيات المدرسية الكاملة المرتبطة بالهيكل والتخطيط والتقويم والإجازات.
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            v-if="canImportDefaultData"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="defaultDataImportForm.processing"
                            @click="importDefaultData"
                        >
                            <PlusCircle class="h-4 w-4" />
                            <span>{{ defaultDataImportButtonLabel }}</span>
                        </button>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-700/80 bg-gradient-to-l from-slate-900 to-slate-800 p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-1">
                        <p class="text-xs font-medium text-cyan-300">لوحة إدارة {{ currentPlanningPageLabel }}</p>
                        <h1 class="text-2xl font-black text-white">{{ currentPlanningPageLabel }}</h1>
                        <p class="text-sm text-slate-300">
                            {{ school.name }} <span class="text-slate-500">|</span> {{ school.school_id }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-600"
                        @click="openQuickSetup()"
                    >
                        الإعدادات السريعة
                    </button>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2 md:grid-cols-3 xl:grid-cols-6">
                    <div
                        v-for="stat in overviewStats"
                        :key="`overview-stat-${stat.key}`"
                        class="rounded-xl border border-slate-700 bg-slate-900/70 px-3 py-2"
                    >
                        <p class="text-[11px] text-slate-400">{{ stat.label }}</p>
                        <p class="text-lg font-bold text-white">{{ stat.value }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-gray-800 bg-gray-900/80 p-4">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <h2 class="text-sm font-bold text-gray-200">التنقل السريع</h2>
                    <span class="text-xs text-gray-400">اختر القسم المطلوب للانتقال المباشر</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="shortcut in sectionShortcuts"
                        :key="`shortcut-${shortcut.key}`"
                        type="button"
                        class="rounded-lg border border-gray-700 bg-gray-800 px-3 py-1.5 text-xs font-semibold text-gray-100 hover:border-blue-500 hover:bg-gray-700"
                        @click="scrollToSection(shortcut.ref)"
                    >
                        {{ shortcut.label }}
                    </button>
                </div>
            </section>

            <section v-if="props.permissions?.can_manage_student_structure && isSectionVisible('stages')" ref="stageSectionRef" style="order: 1;" class="rounded-xl border border-blue-500/70 bg-blue-900/35 p-4">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold">1) المراحل الدراسية</h2>
                        <p class="mt-1 text-xs text-blue-100/80">ابدأ بتعريف المراحل ثم أضف الصفوف داخل كل مرحلة بشكل هرمي واضح.</p>
                    </div>
                    <button type="button" class="rounded-lg bg-gray-700 px-3 py-1.5 text-xs font-semibold hover:bg-gray-600" @click="resetStageForm">جديد</button>
                </div>

                <form class="mb-4 rounded border border-gray-700 bg-gray-800 p-3" @submit.prevent="submitStage">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">اسم المرحلة</label>
                            <input ref="stageNameInputRef" v-model="stageForm.name" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="stageForm.errors.name" class="mt-1 text-xs text-red-400">{{ stageForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">الكود (اختياري)</label>
                            <input v-model="stageForm.code" placeholder="STG-001" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="stageForm.errors.code" class="mt-1 text-xs text-red-400">{{ stageForm.errors.code }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">الترتيب</label>
                            <input v-model.number="stageForm.sort_order" type="number" min="0" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="stageForm.errors.sort_order" class="mt-1 text-xs text-red-400">{{ stageForm.errors.sort_order }}</p>
                        </div>
                        <div class="flex items-end gap-2">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input v-model="stageForm.is_active" type="checkbox" class="rounded border-gray-600 bg-gray-900 text-blue-500" />
                                <span>نشط</span>
                            </label>
                            <button type="submit" :disabled="stageForm.processing" class="rounded bg-emerald-600 px-3 py-2 text-sm font-bold hover:bg-emerald-500">
                                {{ stageEditId ? 'تحديث المرحلة' : 'إضافة مرحلة' }}
                            </button>
                        </div>
                    </div>
                </form>

                <form class="mb-4 rounded border border-gray-700 bg-gray-800 p-3" @submit.prevent="submitStageGrade">
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-200">الصفوف داخل المرحلة</h3>
                        <button type="button" class="rounded bg-gray-700 px-2 py-1 text-xs hover:bg-gray-600" @click="resetStageGradeForm">جديد</button>
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-6">
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">المرحلة</label>
                            <select v-model="stageGradeForm.school_stage_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="" disabled>اختر المرحلة</option>
                                <option v-for="stage in structureStageOptions" :key="`stage-grade-stage-${stage.id}`" :value="stage.id">{{ stage.name }}</option>
                            </select>
                            <p v-if="stageGradeForm.errors.school_stage_id" class="mt-1 text-xs text-red-400">{{ stageGradeForm.errors.school_stage_id }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">اسم الصف</label>
                            <input ref="stageGradeNameInputRef" v-model="stageGradeForm.name" placeholder="مثال: الصف الأول" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="stageGradeForm.errors.name" class="mt-1 text-xs text-red-400">{{ stageGradeForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">الترتيب</label>
                            <input v-model.number="stageGradeForm.sort_order" type="number" min="0" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="stageGradeForm.errors.sort_order" class="mt-1 text-xs text-red-400">{{ stageGradeForm.errors.sort_order }}</p>
                        </div>
                        <div class="flex items-end">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input v-model="stageGradeForm.is_active" type="checkbox" class="rounded border-gray-600 bg-gray-900 text-blue-500" />
                                <span>نشط</span>
                            </label>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" :disabled="stageGradeForm.processing || !stageGradeForm.school_stage_id" class="rounded bg-emerald-600 px-3 py-2 text-sm font-bold hover:bg-emerald-500">
                                {{ stageGradeEditId ? 'تحديث الصف' : 'إضافة صف' }}
                            </button>
                        </div>
                    </div>
                </form>

                <div class="space-y-2">
                    <div
                        v-for="stage in structureStages"
                        :key="`structure-stage-${stage.id}`"
                        class="stage-row-accent flex items-center justify-between rounded border border-gray-700 bg-gray-800 p-3"
                        :style="stageAccent(stage.id, stage.name)"
                    >
                        <div>
                            <p class="font-semibold stage-inline-accent" :style="stageAccent(stage.id, stage.name)">
                                <span class="stage-badge" :style="stageAccent(stage.id, stage.name)">{{ stage.name }}</span>
                            </p>
                            <p class="text-xs text-gray-400">
                                {{ stage.code || '-' }} | {{ statusLabel(stage.is_active) }} | اليوم الدراسي: {{ formatTimeForDisplay(stage.school_day_start_time) }} - {{ formatTimeForDisplay(stage.school_day_end_time) }} |
                                صفوف: {{ (stage.grades || []).length }} |
                                فصول: {{ stageCountById.get(Number(stage.id))?.classroomsCount || 0 }} |
                                طلاب: {{ stageCountById.get(Number(stage.id))?.studentsCount || 0 }}
                            </p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span
                                    v-for="grade in stage.grades || []"
                                    :key="`stage-grade-${grade.id}`"
                                    class="inline-flex items-center gap-1 rounded bg-gray-900 px-2 py-1 text-[11px] text-gray-200"
                                >
                                    <span>{{ grade.name }}</span>
                                    <span class="text-[10px] text-gray-500">({{ statusLabel(grade.is_active) }})</span>
                                    <button class="rounded bg-blue-700 px-1 py-0.5 text-[10px] hover:bg-blue-600" @click="editStageGrade(stage, grade)">تعديل</button>
                                    <button class="rounded bg-red-700 px-1 py-0.5 text-[10px] hover:bg-red-600" @click="removeStageGrade(grade.id)">حذف</button>
                                </span>
                                <span v-if="(stage.grades || []).length === 0" class="text-xs text-gray-500">لا توجد صفوف معرفة لهذه المرحلة.</span>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button class="rounded bg-blue-700 px-2 py-1 text-xs hover:bg-blue-600" @click="editStage(stage)">تعديل</button>
                            <button class="rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600" @click="removeStage(stage.id)">حذف</button>
                        </div>
                    </div>
                    <div v-if="structureStages.length === 0" class="rounded-xl border border-gray-700 bg-gray-800/70 p-4 text-sm text-gray-300">
                        لا توجد مراحل دراسية مضافة بعد. ابدأ بإضافة مرحلة واحدة على الأقل.
                    </div>
                </div>
            </section>

            <section v-if="props.permissions?.can_manage_student_structure && isSectionVisible('classrooms')" ref="classroomSectionRef" style="order: 4;" class="rounded-xl border border-emerald-500/70 bg-emerald-900/30 p-4">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold">4) الفصول التعليمية</h2>
                        <p class="mt-1 text-xs text-emerald-100/80">اربط كل فصل بالمرحلة والصف لتسهيل الإسناد والحضور والتقارير لاحقًا.</p>
                    </div>
                    <button type="button" class="rounded-lg bg-gray-700 px-3 py-1.5 text-xs font-semibold hover:bg-gray-600" @click="resetClassroomForm">جديد</button>
                </div>

                <form class="mb-4 rounded border border-gray-700 bg-gray-800 p-3" @submit.prevent="submitClassroom">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-6">
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">المرحلة</label>
                            <select v-model="classroomForm.school_stage_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="" disabled>اختر المرحلة</option>
                                <option v-for="stage in structureStageOptions" :key="stage.id" :value="stage.id">{{ stage.name }}</option>
                            </select>
                            <p v-if="classroomForm.errors.school_stage_id" class="mt-1 text-xs text-red-400">{{ classroomForm.errors.school_stage_id }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">الصف</label>
                            <select v-model="classroomForm.grade_name" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="" disabled>اختر الصف</option>
                                <option v-for="grade in classroomGradeOptions" :key="`classroom-grade-${grade}`" :value="grade">{{ grade }}</option>
                            </select>
                            <p v-if="classroomGradeOptions.length === 0" class="mt-1 text-xs text-emerald-300">أضف صفًا واحدًا على الأقل داخل المرحلة أولًا.</p>
                            <p v-if="classroomForm.errors.grade_name" class="mt-1 text-xs text-red-400">{{ classroomForm.errors.grade_name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">اسم الفصل</label>
                            <input ref="classroomNameInputRef" v-model="classroomForm.name" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="classroomForm.errors.name" class="mt-1 text-xs text-red-400">{{ classroomForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">الكود (اختياري)</label>
                            <input v-model="classroomForm.code" placeholder="CLS-001" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="classroomForm.errors.code" class="mt-1 text-xs text-red-400">{{ classroomForm.errors.code }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">الترتيب</label>
                            <input v-model.number="classroomForm.sort_order" type="number" min="0" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="classroomForm.errors.sort_order" class="mt-1 text-xs text-red-400">{{ classroomForm.errors.sort_order }}</p>
                        </div>
                        <div class="flex items-end gap-2">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input v-model="classroomForm.is_active" type="checkbox" class="rounded border-gray-600 bg-gray-900 text-blue-500" />
                                <span>نشط</span>
                            </label>
                            <button
                                type="submit"
                                :disabled="classroomForm.processing || !classroomForm.school_stage_id || !classroomForm.grade_name || classroomGradeOptions.length === 0"
                                class="rounded bg-emerald-600 px-3 py-2 text-sm font-bold hover:bg-emerald-500"
                            >
                                {{ classroomEditId ? 'تحديث الفصل' : 'إضافة فصل' }}
                            </button>
                        </div>
                    </div>
                </form>

                <div class="space-y-3">
                    <div
                        v-for="stage in structureStages"
                        :key="`structure-stage-classrooms-${stage.id}`"
                        class="stage-row-accent rounded border border-gray-700 bg-gray-800 p-3"
                        :style="stageAccent(stage.id, stage.name)"
                    >
                        <p class="mb-2 font-semibold text-gray-200 stage-inline-accent" :style="stageAccent(stage.id, stage.name)">
                            <span class="stage-badge" :style="stageAccent(stage.id, stage.name)">{{ stage.name }}</span>
                        </p>
                        <div class="space-y-2">
                            <div v-for="classroom in stage.classrooms || []" :key="classroom.id" class="flex items-center justify-between rounded border border-gray-700 bg-gray-900 p-2">
                                <div>
                                    <p class="text-sm font-semibold">{{ classroom.grade_name }} - {{ classroom.name }}</p>
                                    <p class="text-xs text-gray-400">{{ classroom.code || '-' }} | {{ statusLabel(classroom.is_active) }} | طلاب: {{ classroom.students_count || 0 }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <button class="rounded bg-blue-700 px-2 py-1 text-xs hover:bg-blue-600" @click="editClassroom(classroom)">تعديل</button>
                                    <button class="rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600" @click="removeClassroom(classroom.id)">حذف</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="structureClassroomOptions.length === 0" class="rounded-xl border border-gray-700 bg-gray-800/70 p-4 text-sm text-gray-300">
                        لا توجد فصول مضافة بعد. أضف الصفوف والفصول لإكمال الهيكل.
                    </div>
                </div>
            </section>

            <section v-if="canManagePlanning && isSectionVisible('years')" ref="yearSectionRef" style="order: 2;" class="rounded-xl border border-emerald-500/70 bg-emerald-900/30 p-4">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold">2) العام الدراسي</h2>
                        <p class="mt-1 text-xs text-emerald-100/80">حدد الأعوام الدراسية المعتمدة قبل إضافة الفصول والجدول.</p>
                    </div>
                    <button class="rounded-lg bg-gray-700 px-3 py-1.5 text-xs font-semibold hover:bg-gray-600" @click="resetYearForm">جديد</button>
                </div>

                <form class="mb-4 rounded border border-gray-700 bg-gray-800 p-3" @submit.prevent="submitYear">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-6">
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">اسم العام الدراسي</label>
                            <input ref="yearNameInputRef" v-model="yearForm.name" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="yearForm.errors.name" class="mt-1 text-xs text-red-400">{{ yearForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">من تاريخ</label>
                            <input v-model="yearForm.starts_on" type="date" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="yearForm.errors.starts_on" class="mt-1 text-xs text-red-400">{{ yearForm.errors.starts_on }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">إلى تاريخ</label>
                            <input v-model="yearForm.ends_on" type="date" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="yearForm.errors.ends_on" class="mt-1 text-xs text-red-400">{{ yearForm.errors.ends_on }}</p>
                        </div>
                        <div class="flex items-end">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input v-model="yearForm.is_active" type="checkbox" class="rounded border-gray-600 bg-gray-900 text-blue-500" />
                                <span>نشط</span>
                            </label>
                        </div>
                        <div class="flex items-end gap-2">
                            <button class="rounded bg-emerald-600 px-3 py-2 text-sm font-bold hover:bg-emerald-500" :disabled="yearForm.processing">
                                {{ yearEditId ? 'تحديث العام' : 'إضافة العام' }}
                            </button>
                            <button
                                v-if="yearEditId"
                                type="button"
                                class="rounded bg-gray-700 px-3 py-2 text-sm hover:bg-gray-600"
                                @click="resetYearForm"
                            >
                                إلغاء
                            </button>
                        </div>
                    </div>
                </form>

                <div class="space-y-2">
                    <div v-for="year in academicYears" :key="year.id" class="flex items-center justify-between rounded border border-gray-700 bg-gray-800 p-3">
                        <div>
                            <p class="font-semibold">{{ year.name }}</p>
                            <p class="text-xs text-gray-400">{{ normalizeDateInput(year.starts_on) }} - {{ normalizeDateInput(year.ends_on) }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded px-2 py-1 text-[10px]" :class="year.is_active ? 'bg-emerald-500/20 text-emerald-200' : 'bg-gray-700 text-gray-300'">
                                {{ year.is_active ? 'نشط' : 'غير نشط' }}
                            </span>
                            <button class="rounded bg-blue-700 px-2 py-1 text-xs hover:bg-blue-600" @click="editYear(year)">تعديل</button>
                            <button class="rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600" @click="removeYear(year.id)">حذف</button>
                        </div>
                    </div>
                    <div v-if="academicYears.length === 0" class="rounded-xl border border-gray-700 bg-gray-800/70 p-4 text-sm text-gray-300">
                        لا توجد أعوام دراسية مضافة بعد.
                    </div>
                </div>
            </section>

            <section v-if="canManagePlanning && (isSectionVisible('terms') || isSectionVisible('calendar'))" ref="termSectionRef" style="order: 3;" class="rounded-xl border border-blue-500/70 bg-blue-900/30 p-4">
                <template v-if="isSectionVisible('terms')">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="inline-flex items-center gap-2 text-lg font-bold">
                            <CalendarDays class="h-5 w-5 text-blue-300" />
                            <span>3) الفصل الدراسي</span>
                        </h2>
                        <p class="mt-1 text-xs text-blue-100/80">أنشئ الفصول الدراسية واربطها بالعام الدراسي لتسهيل الفلترة والمتابعة.</p>
                    </div>
                    <button class="inline-flex items-center gap-1 rounded-lg bg-gray-700 px-3 py-1.5 text-xs font-semibold hover:bg-gray-600" @click="resetTermForm">
                        <PlusCircle class="h-3.5 w-3.5" />
                        <span>جديد</span>
                    </button>
                </div>

                <form class="mb-4 rounded border border-gray-700 bg-gray-800 p-3" @submit.prevent="submitTerm">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-6">
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <LayoutTemplate class="h-3.5 w-3.5 text-blue-300" />
                                <span>العام الدراسي (اختياري)</span>
                            </label>
                            <select v-model="termForm.school_academic_year_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="">بدون عام دراسي</option>
                                <option v-for="year in academicYears" :key="`term-year-${year.id}`" :value="year.id">
                                    {{ year.name }}
                                </option>
                            </select>
                            <p v-if="termForm.errors.school_academic_year_id" class="mt-1 text-xs text-red-400">
                                {{ termForm.errors.school_academic_year_id }}
                            </p>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <BookOpenText class="h-3.5 w-3.5 text-cyan-300" />
                                <span>اسم الترم</span>
                            </label>
                            <input ref="termNameInputRef" v-model="termForm.name" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="termForm.errors.name" class="mt-1 text-xs text-red-400">{{ termForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <CalendarDays class="h-3.5 w-3.5 text-blue-300" />
                                <span>من تاريخ</span>
                            </label>
                            <input v-model="termForm.start_date" type="date" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="termForm.errors.start_date" class="mt-1 text-xs text-red-400">{{ termForm.errors.start_date }}</p>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <Clock3 class="h-3.5 w-3.5 text-indigo-300" />
                                <span>إلى تاريخ</span>
                            </label>
                            <input v-model="termForm.end_date" type="date" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="termForm.errors.end_date" class="mt-1 text-xs text-red-400">{{ termForm.errors.end_date }}</p>
                        </div>
                        <div class="flex items-end">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input v-model="termForm.is_active" type="checkbox" class="rounded border-gray-600 bg-gray-900 text-blue-500" />
                                <span>نشط</span>
                            </label>
                        </div>
                        <div class="flex items-end gap-2">
                            <button class="inline-flex items-center gap-1 rounded bg-emerald-600 px-3 py-2 text-sm font-bold hover:bg-emerald-500" :disabled="termForm.processing">
                                <Save class="h-3.5 w-3.5" />
                                <span>{{ termEditId ? 'تحديث الترم' : 'إضافة الترم' }}</span>
                            </button>
                            <button
                                v-if="termEditId"
                                type="button"
                                class="inline-flex items-center gap-1 rounded bg-gray-700 px-3 py-2 text-sm hover:bg-gray-600"
                                @click="resetTermForm"
                            >
                                <X class="h-3.5 w-3.5" />
                                <span>إلغاء</span>
                            </button>
                        </div>
                    </div>
                </form>

                <div class="space-y-2">
                    <div v-for="term in terms" :key="term.id" class="flex items-center justify-between rounded border border-gray-700 bg-gray-800 p-3">
                        <div>
                            <p class="inline-flex items-center gap-1 font-semibold">
                                <CalendarDays class="h-3.5 w-3.5 text-blue-300" />
                                <span>{{ term.name }}</span>
                            </p>
                            <p class="inline-flex items-center gap-1 text-xs text-gray-400">
                                <LayoutTemplate class="h-3.5 w-3.5 text-gray-400" />
                                {{ term.academic_year?.name || 'بدون عام دراسي' }}
                            </p>
                            <p class="inline-flex items-center gap-1 text-xs text-gray-400">
                                <Clock3 class="h-3.5 w-3.5 text-gray-400" />
                                <span>{{ normalizeDateInput(term.start_date) }} - {{ normalizeDateInput(term.end_date) }}</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded px-2 py-1 text-[10px]" :class="term.is_active ? 'bg-emerald-500/20 text-emerald-200' : 'bg-gray-700 text-gray-300'">
                                {{ term.is_active ? 'نشط' : 'غير نشط' }}
                            </span>
                            <button class="inline-flex items-center gap-1 rounded bg-blue-700 px-2 py-1 text-xs hover:bg-blue-600" @click="editTerm(term)">
                                <Pencil class="h-3 w-3" />
                                <span>تعديل</span>
                            </button>
                            <button class="inline-flex items-center gap-1 rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600" @click="removeTerm(term.id)">
                                <Trash2 class="h-3 w-3" />
                                <span>حذف</span>
                            </button>
                        </div>
                    </div>
                    <div v-if="terms.length === 0" class="rounded-xl border border-gray-700 bg-gray-800/70 p-4 text-sm text-gray-300">
                        لا توجد فصول دراسية مضافة بعد.
                    </div>
                </div>

                </template>

                <div v-if="isSectionVisible('calendar')" class="mb-4">
                    <h2 class="text-lg font-bold">4) إعدادات التقويم المدرسي</h2>
                    <p class="mt-1 text-xs text-blue-100/80">إدارة إعدادات التقويم والعطل الرسمية وأنواع الإجازات من شاشة موحدة.</p>
                </div>

                <div v-if="(canManageCalendar || canManageHolidays || canManageLeaveTypes) && isSectionVisible('calendar')" class="mt-6 space-y-4">
                    <section v-if="canManageCalendar" ref="calendarSectionRef" class="rounded-xl border border-blue-500/70 bg-blue-900/35 p-4">
                        <h3 class="mb-3 text-base font-bold">إعدادات التقويم المدرسي</h3>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs text-gray-400">بداية الأسبوع</label>
                                <select v-model="calendarForm.week_start_day" class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm">
                                    <option v-for="day in weekDays" :key="`start-${day.value}`" :value="day.value">{{ day.label }}</option>
                                </select>
                                <p v-if="calendarFormErrors.week_start_day" class="mt-1 text-[11px] text-red-400">{{ calendarFormErrors.week_start_day }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-gray-400">أيام الإجازة الأسبوعية</label>
                                <div class="grid grid-cols-2 gap-1 rounded border border-gray-700 bg-gray-800 p-2 text-xs">
                                    <label v-for="day in weekDays" :key="`off-${day.value}`" class="inline-flex items-center gap-2">
                                        <input :checked="calendarForm.weekly_off_days.includes(day.value)" type="checkbox" @change="toggleWeeklyOffDay(day.value)" />
                                        {{ day.label }}
                                    </label>
                                </div>
                                <p v-if="calendarFormErrors.weekly_off_days" class="mt-1 text-[11px] text-red-400">{{ calendarFormErrors.weekly_off_days }}</p>
                            </div>
                        </div>
                        <div class="mt-4 rounded border border-gray-700 bg-gray-800 p-3">
                            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-200">
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-cyan-500/20 text-cyan-100">
                                            <Clock3 class="h-4 w-4" />
                                        </span>
                                        <span>مواعيد اليوم الدراسي حسب المرحلة</span>
                                    </h4>
                                    <p class="mt-1 text-xs text-gray-400">استعرض المراحل بسرعة ثم افتح نافذة ضبط بداية ونهاية اليوم الدراسي للمرحلة المطلوبة.</p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg bg-cyan-700 px-3 py-1.5 text-xs font-semibold hover:bg-cyan-600"
                                    @click="openStageDayTimeModal()"
                                >
                                    <Clock3 class="h-4 w-4" />
                                    <span>ضبط مواعيد مرحلة</span>
                                </button>
                            </div>

                            <div class="mb-3 grid grid-cols-1 gap-3 xl:grid-cols-12">
                                <label class="block text-xs text-gray-300 xl:col-span-7">
                                    بحث في المراحل
                                    <input
                                        v-model="stageDayTimeSearchQuery"
                                        class="mt-1 w-full rounded-lg border border-gray-700 bg-gray-900 p-2 text-sm"
                                        placeholder="ابحث باسم المرحلة أو الكود"
                                    />
                                </label>
                                <label class="block text-xs text-gray-300 xl:col-span-3">
                                    الحالة
                                    <select v-model="stageDayTimeStatusFilter" class="mt-1 w-full rounded-lg border border-gray-700 bg-gray-900 p-2 text-sm">
                                        <option value="ALL">كل الحالات</option>
                                        <option value="ACTIVE">النشطة فقط</option>
                                        <option value="INACTIVE">المعطلة فقط</option>
                                    </select>
                                </label>
                                <div class="flex flex-col justify-end gap-2 xl:col-span-2">
                                    <div class="rounded-lg border border-gray-700 bg-gray-900/70 px-3 py-2 text-xs text-gray-300">
                                        عدد النتائج: {{ filteredStageDayTimeStages.length }}
                                    </div>
                                    <button
                                        type="button"
                                        class="rounded-lg bg-gray-700 px-3 py-2 text-xs font-semibold hover:bg-gray-600"
                                        @click="stageDayTimeSearchQuery = ''; stageDayTimeStatusFilter = 'ALL'"
                                    >
                                        إعادة تعيين
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div
                                    v-for="stage in filteredStageDayTimeStages"
                                    :key="`calendar-stage-time-row-${stage.id}`"
                                    class="stage-row-accent flex flex-wrap items-start justify-between gap-3 rounded-lg border border-gray-700 bg-gray-900/70 p-3"
                                    :style="stageAccent(stage.id, stage.name)"
                                >
                                    <div>
                                        <p class="font-semibold stage-inline-accent" :style="stageAccent(stage.id, stage.name)">
                                            <span class="stage-badge" :style="stageAccent(stage.id, stage.name)">{{ stage.name }}</span>
                                            <span class="mx-1 text-sm">{{ stage.code || '-' }}</span>
                                        </p>
                                        <div class="mt-1 flex flex-wrap gap-2 text-xs text-gray-400">
                                            <span>بداية اليوم: {{ formatTimeForDisplay(stage.school_day_start_time) }}</span>
                                            <span>نهاية اليوم: {{ formatTimeForDisplay(stage.school_day_end_time) }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="rounded px-2 py-1 text-[10px]" :class="stage.is_active ? 'bg-emerald-500/20 text-emerald-200' : 'bg-gray-700 text-gray-300'">
                                            {{ stage.is_active ? 'نشطة' : 'معطلة' }}
                                        </span>
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded bg-blue-700 px-2 py-1 text-xs hover:bg-blue-600"
                                            @click="openStageDayTimeModal(stage.id)"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                            <span>تعديل</span>
                                        </button>
                                    </div>
                                </div>
                                <div v-if="filteredStageDayTimeStages.length === 0" class="rounded-lg border border-gray-700 bg-gray-900/60 p-3 text-sm text-gray-300">
                                    لا توجد مراحل مطابقة للفلاتر الحالية.
                                </div>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="mt-3 rounded bg-emerald-700 px-3 py-2 text-sm font-bold hover:bg-emerald-600"
                            @click="saveCalendar"
                        >
                            حفظ الإعدادات
                        </button>
                        <p v-if="calendarError" class="mt-2 text-xs text-red-400">{{ calendarError }}</p>
                    </section>

                    <div
                        v-if="isStageDayTimeModalOpen"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                        @click.self="closeStageDayTimeModal"
                    >
                        <div class="w-full max-w-3xl overflow-hidden rounded-2xl border border-cyan-400/40 bg-gray-900 shadow-2xl">
                            <div class="flex items-center justify-between gap-2 border-b border-gray-700 bg-gray-800/90 p-4">
                                <div>
                                    <h3 class="flex items-center gap-2 text-base font-bold text-cyan-100">
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-cyan-500/20 text-cyan-100">
                                            <Clock3 class="h-4 w-4" />
                                        </span>
                                        <span>ضبط مواعيد اليوم الدراسي للمرحلة</span>
                                    </h3>
                                    <p class="text-xs text-cyan-100/70">اختر المرحلة ثم حدّد بداية ونهاية اليوم الدراسي المعتمد لها داخل نفس المدرسة.</p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded bg-gray-700 px-3 py-1.5 text-xs hover:bg-gray-600"
                                    @click="closeStageDayTimeModal"
                                >
                                    <X class="h-3.5 w-3.5" />
                                    <span>إغلاق</span>
                                </button>
                            </div>

                            <div class="max-h-[75vh] space-y-4 overflow-y-auto p-4">
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                                    <div>
                                        <label class="mb-1 block text-xs text-gray-300">المرحلة الدراسية</label>
                                        <select v-model="stageDayTimeForm.school_stage_id" class="w-full rounded-lg border border-gray-700 bg-gray-900 p-2 text-sm">
                                            <option value="" disabled>اختر المرحلة</option>
                                            <option v-for="stage in structureStageOptions" :key="`calendar-stage-time-modal-${stage.id}`" :value="stage.id">
                                                {{ stage.name }}
                                            </option>
                                        </select>
                                        <p v-if="stageDayTimeForm.errors.school_stage_id" class="mt-1 text-xs text-red-400">{{ stageDayTimeForm.errors.school_stage_id }}</p>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs text-gray-300">بداية اليوم الدراسي</label>
                                        <input v-model="stageDayTimeForm.school_day_start_time" type="time" class="w-full rounded-lg border border-gray-700 bg-gray-900 p-2 text-sm" />
                                        <p v-if="stageDayTimeForm.errors.school_day_start_time" class="mt-1 text-xs text-red-400">{{ stageDayTimeForm.errors.school_day_start_time }}</p>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs text-gray-300">نهاية اليوم الدراسي</label>
                                        <input v-model="stageDayTimeForm.school_day_end_time" type="time" class="w-full rounded-lg border border-gray-700 bg-gray-900 p-2 text-sm" />
                                        <p v-if="stageDayTimeForm.errors.school_day_end_time" class="mt-1 text-xs text-red-400">{{ stageDayTimeForm.errors.school_day_end_time }}</p>
                                    </div>
                                </div>

                                <div class="rounded-lg border border-gray-700 bg-gray-800/70 p-3 text-xs text-gray-300">
                                    <div class="flex flex-wrap gap-3">
                                        <span>اسم المرحلة: {{ stageDayTimeForm.name || '-' }}</span>
                                        <span>الكود: {{ stageDayTimeForm.code || '-' }}</span>
                                        <span>الحالة: {{ stageDayTimeForm.is_active ? 'نشطة' : 'معطلة' }}</span>
                                    </div>
                                </div>

                                <div class="flex items-center justify-end gap-2 border-t border-gray-700 pt-3">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded bg-gray-700 px-3 py-2 text-sm hover:bg-gray-600"
                                        @click="closeStageDayTimeModal"
                                    >
                                        <X class="h-4 w-4" />
                                        <span>إلغاء</span>
                                    </button>
                                    <button
                                        type="button"
                                        :disabled="stageDayTimeForm.processing || !stageDayTimeForm.school_stage_id"
                                        class="inline-flex items-center gap-2 rounded bg-cyan-700 px-4 py-2 text-sm font-bold hover:bg-cyan-600 disabled:cursor-not-allowed disabled:opacity-50"
                                        @click="submitStageDayTimes"
                                    >
                                        <Save class="h-4 w-4" />
                                        <span>حفظ مواعيد المرحلة</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <section v-if="canManageHolidays" ref="holidaySectionRef" class="rounded-xl border border-emerald-500/70 bg-emerald-900/30 p-4">
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="flex items-center gap-2 text-base font-bold">
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-100">
                                        <CalendarDays class="h-4 w-4" />
                                    </span>
                                    <span>العطل الرسمية</span>
                                </h3>
                                <p class="mt-1 text-xs text-emerald-100/80">أنشئ العطل الرسمية وعدّلها من نافذة موحدة مع فلترة سريعة للقائمة.</p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold hover:bg-emerald-600"
                                @click="openCreateHolidayModal"
                            >
                                <PlusCircle class="h-4 w-4" />
                                <span>أضف عطلة</span>
                            </button>
                        </div>

                        <div class="mb-3 grid grid-cols-1 gap-3 xl:grid-cols-12">
                            <label class="block text-xs text-gray-300 xl:col-span-6">
                                بحث في العطل
                                <input
                                    v-model="holidaySearchQuery"
                                    class="mt-1 w-full rounded-lg border border-gray-700 bg-gray-900 p-2 text-sm"
                                    placeholder="ابحث باسم العطلة أو التاريخ أو الملاحظات"
                                />
                            </label>
                            <label class="block text-xs text-gray-300 xl:col-span-3">
                                الحالة
                                <select v-model="holidayStatusFilter" class="mt-1 w-full rounded-lg border border-gray-700 bg-gray-900 p-2 text-sm">
                                    <option value="ALL">كل الحالات</option>
                                    <option value="ACTIVE">النشطة فقط</option>
                                    <option value="INACTIVE">المعطلة فقط</option>
                                </select>
                            </label>
                            <div class="flex flex-col justify-end gap-2 xl:col-span-3">
                                <div class="rounded-lg border border-gray-700 bg-gray-800/70 px-3 py-2 text-xs text-gray-300">
                                    عدد النتائج: {{ filteredHolidays.length }}
                                </div>
                                <button
                                    type="button"
                                    class="rounded-lg bg-gray-700 px-3 py-2 text-xs font-semibold hover:bg-gray-600"
                                    @click="holidaySearchQuery = ''; holidayStatusFilter = 'ALL'"
                                >
                                    إعادة تعيين الفلاتر
                                </button>
                            </div>
                        </div>

                        <p v-if="holidayError" class="mb-2 text-xs text-red-400">{{ holidayError }}</p>
                        <div class="space-y-2">
                            <div
                                v-for="holiday in filteredHolidays"
                                :key="holiday.id"
                                class="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-gray-700 bg-gray-800 p-3"
                            >
                                <div>
                                    <p class="font-semibold">{{ holiday.name }}</p>
                                    <div class="mt-1 flex flex-wrap gap-2 text-xs text-gray-400">
                                        <span>من {{ holiday.start_date || '-' }}</span>
                                        <span>إلى {{ holiday.end_date || '-' }}</span>
                                        <span>العودة {{ holiday.return_date || '-' }}</span>
                                        <span v-if="holiday.days_count">عدد الأيام: {{ holiday.days_count }}</span>
                                    </div>
                                    <p v-if="holiday.notes" class="mt-1 text-xs text-gray-400">{{ holiday.notes }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded px-2 py-1 text-[10px]" :class="holiday.is_active ? 'bg-emerald-500/20 text-emerald-200' : 'bg-gray-700 text-gray-300'">
                                        {{ holiday.is_active ? 'نشطة' : 'معطلة' }}
                                    </span>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded bg-blue-700 px-2 py-1 text-xs hover:bg-blue-600"
                                        @click="editHoliday(holiday)"
                                    >
                                        <Pencil class="h-3.5 w-3.5" />
                                        <span>تعديل</span>
                                    </button>
                                    <button
                                        v-if="holiday.is_active"
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded bg-gray-700 px-2 py-1 text-xs hover:bg-gray-600"
                                        @click="disableHoliday(holiday)"
                                    >
                                        <X class="h-3.5 w-3.5" />
                                        <span>تعطيل</span>
                                    </button>
                                </div>
                            </div>
                            <div v-if="filteredHolidays.length === 0" class="rounded-lg border border-gray-700 bg-gray-800/70 p-3 text-sm text-gray-300">
                                لا توجد عطل رسمية مطابقة للفلاتر الحالية.
                            </div>
                        </div>
                    </section>

                    <div
                        v-if="isHolidayModalOpen"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                        @click.self="closeHolidayModal"
                    >
                        <div class="w-full max-w-4xl overflow-hidden rounded-2xl border border-emerald-400/40 bg-gray-900 shadow-2xl">
                            <div class="flex items-center justify-between gap-2 border-b border-gray-700 bg-gray-800/90 p-4">
                                <div>
                                    <h3 class="flex items-center gap-2 text-base font-bold text-emerald-100">
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-emerald-500/20 text-emerald-100">
                                            <CalendarDays class="h-4 w-4" />
                                        </span>
                                        <span>{{ holidayEditingId ? 'تعديل العطلة الرسمية' : 'إضافة عطلة رسمية' }}</span>
                                    </h3>
                                    <p class="text-xs text-emerald-100/70">أدخل بيانات العطلة وسيتم احتساب تاريخ النهاية والعودة تلقائيًا عند تحديد عدد الأيام.</p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded bg-gray-700 px-3 py-1.5 text-xs hover:bg-gray-600"
                                    @click="closeHolidayModal"
                                >
                                    <X class="h-3.5 w-3.5" />
                                    <span>إغلاق</span>
                                </button>
                            </div>

                            <form class="max-h-[75vh] space-y-4 overflow-y-auto p-4" @submit.prevent="saveHoliday">
                                <p v-if="holidayError" class="text-xs text-red-400">{{ holidayError }}</p>

                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                                    <label class="block text-xs text-gray-300">
                                        اسم العطلة
                                        <input
                                            ref="holidayNameInputRef"
                                            v-model="holidayForm.name"
                                            class="mt-1 w-full rounded-lg border border-gray-700 bg-gray-900 p-2 text-sm"
                                            placeholder="مثال: عطلة منتصف العام"
                                        />
                                        <span v-if="holidayFormErrors.name" class="mt-1 block text-[11px] text-red-400">{{ holidayFormErrors.name }}</span>
                                    </label>
                                    <label class="block text-xs text-gray-300">
                                        تاريخ البداية
                                        <input v-model="holidayForm.start_date" type="date" class="mt-1 w-full rounded-lg border border-gray-700 bg-gray-900 p-2 text-sm" />
                                        <span v-if="holidayFormErrors.start_date" class="mt-1 block text-[11px] text-red-400">{{ holidayFormErrors.start_date }}</span>
                                    </label>
                                    <label class="block text-xs text-gray-300">
                                        عدد الأيام
                                        <input
                                            v-model="holidayForm.days_count"
                                            type="number"
                                            min="1"
                                            class="mt-1 w-full rounded-lg border border-gray-700 bg-gray-900 p-2 text-sm"
                                            placeholder="مثال: 3"
                                        />
                                        <span v-if="holidayFormErrors.days_count" class="mt-1 block text-[11px] text-red-400">{{ holidayFormErrors.days_count }}</span>
                                    </label>
                                    <label class="block text-xs text-gray-300">
                                        تاريخ النهاية (اختياري)
                                        <input
                                            v-model="holidayForm.end_date"
                                            :readonly="Boolean(holidayForm.days_count)"
                                            type="date"
                                            class="mt-1 w-full rounded-lg border border-gray-700 bg-gray-900 p-2 text-sm"
                                        />
                                        <span v-if="holidayFormErrors.end_date" class="mt-1 block text-[11px] text-red-400">{{ holidayFormErrors.end_date }}</span>
                                    </label>
                                    <label class="block text-xs text-gray-300">
                                        تاريخ العودة (اختياري)
                                        <input
                                            v-model="holidayForm.return_date"
                                            :readonly="Boolean(holidayForm.days_count)"
                                            type="date"
                                            class="mt-1 w-full rounded-lg border border-gray-700 bg-gray-900 p-2 text-sm"
                                        />
                                        <span v-if="holidayFormErrors.return_date" class="mt-1 block text-[11px] text-red-400">{{ holidayFormErrors.return_date }}</span>
                                    </label>
                                    <label class="block text-xs text-gray-300 md:col-span-2 xl:col-span-3">
                                        ملاحظات (اختياري)
                                        <input
                                            v-model="holidayForm.notes"
                                            class="mt-1 w-full rounded-lg border border-gray-700 bg-gray-900 p-2 text-sm"
                                            placeholder="أي ملاحظة إضافية"
                                        />
                                        <span v-if="holidayFormErrors.notes" class="mt-1 block text-[11px] text-red-400">{{ holidayFormErrors.notes }}</span>
                                    </label>
                                </div>

                                <div class="flex items-center justify-end gap-2 border-t border-gray-700 pt-3">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded bg-gray-700 px-3 py-2 text-sm hover:bg-gray-600"
                                        @click="closeHolidayModal"
                                    >
                                        <X class="h-4 w-4" />
                                        <span>إلغاء</span>
                                    </button>
                                    <button class="inline-flex items-center gap-2 rounded bg-emerald-700 px-4 py-2 text-sm font-bold hover:bg-emerald-600">
                                        <Save class="h-4 w-4" />
                                        <span>{{ holidayEditingId ? 'حفظ التعديلات' : 'إضافة العطلة' }}</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <section v-if="canManageLeaveTypes" ref="leaveTypeSectionRef" class="rounded-xl border border-blue-500/70 bg-blue-900/30 p-4">
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="flex items-center gap-2 text-base font-bold">
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-blue-500/20 text-blue-100">
                                        <Settings2 class="h-4 w-4" />
                                    </span>
                                    <span>أنواع الإجازات</span>
                                </h3>
                                <p class="mt-1 text-xs text-blue-100/80">أدرّف أنواع الإجازات من نافذة مستقلة مع فلترة حسب الحالة والمرفقات.</p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg bg-blue-700 px-3 py-1.5 text-xs font-semibold hover:bg-blue-600"
                                @click="openCreateLeaveTypeModal"
                            >
                                <PlusCircle class="h-4 w-4" />
                                <span>أضف نوعًا</span>
                            </button>
                        </div>

                        <div class="mb-3 grid grid-cols-1 gap-3 xl:grid-cols-12">
                            <label class="block text-xs text-gray-300 xl:col-span-5">
                                بحث في الأنواع
                                <input
                                    v-model="leaveTypeSearchQuery"
                                    class="mt-1 w-full rounded-lg border border-gray-700 bg-gray-900 p-2 text-sm"
                                    placeholder="ابحث باسم النوع أو الكود"
                                />
                            </label>
                            <label class="block text-xs text-gray-300 xl:col-span-3">
                                حالة المرفقات
                                <select v-model="leaveTypeAttachmentFilter" class="mt-1 w-full rounded-lg border border-gray-700 bg-gray-900 p-2 text-sm">
                                    <option value="ALL">الكل</option>
                                    <option value="REQUIRED">يتطلب مرفقًا</option>
                                    <option value="OPTIONAL">بدون مرفق</option>
                                </select>
                            </label>
                            <label class="block text-xs text-gray-300 xl:col-span-2">
                                الحالة
                                <select v-model="leaveTypeStatusFilter" class="mt-1 w-full rounded-lg border border-gray-700 bg-gray-900 p-2 text-sm">
                                    <option value="ALL">كل الحالات</option>
                                    <option value="ACTIVE">النشطة فقط</option>
                                    <option value="INACTIVE">المعطلة فقط</option>
                                </select>
                            </label>
                            <div class="flex flex-col justify-end gap-2 xl:col-span-2">
                                <div class="rounded-lg border border-gray-700 bg-gray-800/70 px-3 py-2 text-xs text-gray-300">
                                    عدد النتائج: {{ filteredLeaveTypes.length }}
                                </div>
                                <button
                                    type="button"
                                    class="rounded-lg bg-gray-700 px-3 py-2 text-xs font-semibold hover:bg-gray-600"
                                    @click="leaveTypeSearchQuery = ''; leaveTypeAttachmentFilter = 'ALL'; leaveTypeStatusFilter = 'ALL'"
                                >
                                    إعادة تعيين الفلاتر
                                </button>
                            </div>
                        </div>

                        <p v-if="leaveTypeError" class="mb-2 text-xs text-red-400">{{ leaveTypeError }}</p>
                        <div class="space-y-2">
                            <div
                                v-for="typeItem in filteredLeaveTypes"
                                :key="typeItem.id"
                                class="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-gray-700 bg-gray-800 p-3"
                            >
                                <div>
                                    <p class="font-semibold">
                                        {{ typeItem.name }}
                                        <span class="text-xs text-gray-400">({{ typeItem.code || '-' }})</span>
                                    </p>
                                    <div class="mt-1 flex flex-wrap gap-2 text-xs text-gray-400">
                                        <span>{{ typeItem.requires_attachment ? 'مرفق مطلوب' : 'بدون مرفق' }}</span>
                                        <span>الفئة: {{ typeItem.category || 'STUDENT' }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded px-2 py-1 text-[10px]" :class="typeItem.is_active ? 'bg-emerald-500/20 text-emerald-200' : 'bg-gray-700 text-gray-300'">
                                        {{ typeItem.is_active ? 'نشط' : 'معطّل' }}
                                    </span>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded bg-blue-700 px-2 py-1 text-xs hover:bg-blue-600"
                                        @click="editLeaveType(typeItem)"
                                    >
                                        <Pencil class="h-3.5 w-3.5" />
                                        <span>تعديل</span>
                                    </button>
                                    <button
                                        v-if="typeItem.is_active"
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded bg-gray-700 px-2 py-1 text-xs hover:bg-gray-600"
                                        @click="disableLeaveType(typeItem)"
                                    >
                                        <X class="h-3.5 w-3.5" />
                                        <span>تعطيل</span>
                                    </button>
                                </div>
                            </div>
                            <div v-if="filteredLeaveTypes.length === 0" class="rounded-lg border border-gray-700 bg-gray-800/70 p-3 text-sm text-gray-300">
                                لا توجد أنواع إجازات مطابقة للفلاتر الحالية.
                            </div>
                        </div>
                    </section>

                    <div
                        v-if="isLeaveTypeModalOpen"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                        @click.self="closeLeaveTypeModal"
                    >
                        <div class="w-full max-w-3xl overflow-hidden rounded-2xl border border-blue-400/40 bg-gray-900 shadow-2xl">
                            <div class="flex items-center justify-between gap-2 border-b border-gray-700 bg-gray-800/90 p-4">
                                <div>
                                    <h3 class="flex items-center gap-2 text-base font-bold text-blue-100">
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-blue-500/20 text-blue-100">
                                            <Settings2 class="h-4 w-4" />
                                        </span>
                                        <span>{{ leaveTypeEditingId ? 'تعديل نوع الإجازة' : 'إضافة نوع إجازة' }}</span>
                                    </h3>
                                    <p class="text-xs text-blue-100/70">أدخل الاسم والكود الاختياري وحدد ما إذا كان النوع يتطلب مرفقًا.</p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded bg-gray-700 px-3 py-1.5 text-xs hover:bg-gray-600"
                                    @click="closeLeaveTypeModal"
                                >
                                    <X class="h-3.5 w-3.5" />
                                    <span>إغلاق</span>
                                </button>
                            </div>

                            <form class="max-h-[75vh] space-y-4 overflow-y-auto p-4" @submit.prevent="saveLeaveType">
                                <p v-if="leaveTypeError" class="text-xs text-red-400">{{ leaveTypeError }}</p>

                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <label class="block text-xs text-gray-300">
                                        اسم النوع
                                        <input
                                            ref="leaveTypeNameInputRef"
                                            v-model="leaveTypeForm.name"
                                            class="mt-1 w-full rounded-lg border border-gray-700 bg-gray-900 p-2 text-sm"
                                            placeholder="مثال: إجازة رياضية"
                                        />
                                        <span v-if="leaveTypeFormErrors.name" class="mt-1 block text-[11px] text-red-400">{{ leaveTypeFormErrors.name }}</span>
                                    </label>
                                    <label class="block text-xs text-gray-300">
                                        الكود (اختياري)
                                        <input
                                            v-model="leaveTypeForm.code"
                                            class="mt-1 w-full rounded-lg border border-gray-700 bg-gray-900 p-2 text-sm"
                                            placeholder="مثال: SPORT_LEAVE"
                                        />
                                        <span v-if="leaveTypeFormErrors.code" class="mt-1 block text-[11px] text-red-400">{{ leaveTypeFormErrors.code }}</span>
                                    </label>
                                </div>

                                <label class="inline-flex items-center gap-2 rounded-lg border border-gray-700 bg-gray-800 px-3 py-3 text-sm">
                                    <input
                                        v-model="leaveTypeForm.requires_attachment"
                                        type="checkbox"
                                        class="rounded border-gray-600 bg-gray-900 text-blue-500"
                                    />
                                    <span>يتطلب مرفقًا عند تقديم الطلب</span>
                                </label>

                                <div class="flex items-center justify-end gap-2 border-t border-gray-700 pt-3">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded bg-gray-700 px-3 py-2 text-sm hover:bg-gray-600"
                                        @click="closeLeaveTypeModal"
                                    >
                                        <X class="h-4 w-4" />
                                        <span>إلغاء</span>
                                    </button>
                                    <button class="inline-flex items-center gap-2 rounded bg-emerald-700 px-4 py-2 text-sm font-bold hover:bg-emerald-600">
                                        <Save class="h-4 w-4" />
                                        <span>{{ leaveTypeEditingId ? 'حفظ التعديلات' : 'إضافة النوع' }}</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="canManagePlanning && isSectionVisible('subjects')" ref="subjectSectionRef" style="order: 5;" class="rounded-xl border border-blue-500/70 bg-blue-900/30 p-4">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="flex items-center gap-2 text-lg font-bold">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-blue-500/20 text-blue-100">
                                <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 6v12" />
                                    <path d="M17 8v8" />
                                    <path d="M7 8v8" />
                                    <path d="M4 19h16" />
                                    <path d="M5 5h14" />
                                </svg>
                            </span>
                            <span>5) المواد التعليمية</span>
                        </h2>
                        <p class="mt-1 text-xs text-blue-100/80">أدر المواد والمعلمين المرتبطين بها بشكل واضح قبل بناء الجدول.</p>
                    </div>
                    <button class="rounded-lg bg-blue-700 px-3 py-1.5 text-xs font-semibold hover:bg-blue-600" @click="openCreateSubjectModal">أضف مادة</button>
                </div>

                <div class="mb-3">
                    <label class="mb-1 flex items-center gap-1 text-xs text-gray-300">
                        <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-none stroke-current text-blue-300" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="7" />
                            <path d="M20 20l-3.5-3.5" />
                        </svg>
                        <span>بحث في المواد</span>
                    </label>
                    <input
                        v-model="subjectSearchQuery"
                        type="text"
                        class="w-full rounded-lg border border-gray-700 bg-gray-900 p-2 text-sm"
                        placeholder="ابحث باسم المادة أو الكود"
                    />
                </div>

                <div class="space-y-2">
                    <div v-for="subject in filteredSubjects" :key="subject.id" class="rounded border border-gray-700 bg-gray-800 p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold">{{ subject.name }} <span class="text-xs text-gray-500">({{ subject.code || '-' }})</span></p>
                                <p class="text-xs text-gray-400">المعلمون: {{ subjectTeachersLabel(subject) }}</p>
                                <p class="text-xs text-gray-400">الفروع: {{ (subject.branches || []).length ? subject.branches.join(' / ') : 'الفرع الرئيسي' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded px-2 py-1 text-[10px]" :class="subject.is_active ? 'bg-emerald-500/20 text-emerald-200' : 'bg-gray-700 text-gray-300'">
                                    {{ subject.is_active ? 'نشط' : 'غير نشط' }}
                                </span>
                                <button class="rounded bg-indigo-700 px-2 py-1 text-xs hover:bg-indigo-600" @click="openSubjectTeachers(subject)">
                                    إسناد معلمين
                                </button>
                                <button class="rounded bg-blue-700 px-2 py-1 text-xs hover:bg-blue-600" @click="editSubject(subject)">تعديل</button>
                                <button class="rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600" @click="removeSubject(subject.id)">حذف</button>
                            </div>
                        </div>
                    </div>
                    <div v-if="filteredSubjects.length === 0" class="rounded-xl border border-gray-700 bg-gray-800/70 p-4 text-sm text-gray-300">
                        لا توجد مواد مطابقة للبحث الحالي.
                    </div>
                </div>

                <div
                    v-if="isSubjectModalOpen"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                    @click.self="closeSubjectModal"
                >
                    <div class="w-full max-w-5xl overflow-hidden rounded-2xl border border-blue-400/40 bg-gray-900 shadow-2xl">
                        <div class="flex items-center justify-between gap-2 border-b border-gray-700 bg-gray-800/90 p-4">
                            <div>
                                <h3 class="flex items-center gap-2 text-base font-bold text-blue-100">
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-blue-500/20 text-blue-100">
                                        <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12 5v14" />
                                            <path d="M5 12h14" />
                                        </svg>
                                    </span>
                                    <span>{{ subjectEditId ? 'تعديل المادة' : 'إضافة مادة جديدة' }}</span>
                                </h3>
                                <p class="text-xs text-blue-100/70">أدخل اسم المادة والفروع والمعلمين المرتبطين بها من نفس النافذة.</p>
                            </div>
                            <button type="button" class="rounded bg-gray-700 px-3 py-1.5 text-xs hover:bg-gray-600" @click="closeSubjectModal">
                                إغلاق
                            </button>
                        </div>

                        <form class="max-h-[75vh] space-y-3 overflow-y-auto p-4" @submit.prevent="submitSubject">
                            <div class="grid grid-cols-1 gap-3 xl:grid-cols-12">
                                <div class="xl:col-span-4">
                                    <label class="mb-1 block min-h-[18px] text-xs text-gray-400">اسم المادة</label>
                                    <input ref="subjectNameInputRef" v-model="subjectForm.name" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                                    <p v-if="subjectForm.errors.name" class="mt-1 text-xs text-red-400">{{ subjectForm.errors.name }}</p>
                                </div>
                                <div class="xl:col-span-3">
                                    <label class="mb-1 block min-h-[18px] text-xs text-gray-400">كود المادة (اختياري)</label>
                                    <input v-model="subjectForm.code" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                                    <p class="mt-1 text-[11px] text-gray-500">يُولَّد تلقائيًا عند تركه فارغًا.</p>
                                    <p v-if="subjectForm.errors.code" class="mt-1 text-xs text-red-400">{{ subjectForm.errors.code }}</p>
                                </div>
                                <div class="xl:col-span-4">
                                    <label class="mb-1 block min-h-[18px] text-xs text-gray-400">فروع المادة (اختياري)</label>
                                    <input v-model="subjectForm.branches_text" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" placeholder="مثل: نحو، نصوص، قراءة" />
                                    <p class="mt-1 text-[11px] text-gray-500">افصل بين الفروع بفاصلة عربية أو إنجليزية.</p>
                                    <p v-if="subjectForm.errors.branches" class="mt-1 text-xs text-red-400">{{ subjectForm.errors.branches }}</p>
                                    <p v-if="subjectForm.errors['branches.0']" class="mt-1 text-xs text-red-400">{{ subjectForm.errors['branches.0'] }}</p>
                                </div>
                                <div class="xl:col-span-1 flex items-end">
                                    <label class="inline-flex w-full items-center justify-center gap-2 rounded border border-gray-700 bg-gray-800 px-2 py-2 text-sm">
                                        <input v-model="subjectForm.is_active" type="checkbox" class="rounded border-gray-600 bg-gray-900 text-blue-500" />
                                        <span>نشط</span>
                                    </label>
                                </div>
                            </div>

                            <div class="rounded border border-gray-700 bg-gray-800/70 p-3">
                                <label class="mb-1 flex items-center gap-1 text-xs text-gray-400">
                                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-none stroke-current text-indigo-300" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="9" cy="8" r="3" />
                                        <circle cx="17" cy="9" r="2.5" />
                                        <path d="M4.5 18a4.5 4.5 0 0 1 9 0" />
                                        <path d="M14 18a3 3 0 0 1 6 0" />
                                    </svg>
                                    <span>المعلمون المرتبطون بالمادة</span>
                                </label>
                                <div class="max-h-32 overflow-auto rounded border border-gray-700 bg-gray-900 p-2">
                                    <div class="grid grid-cols-1 gap-1 md:grid-cols-2">
                                        <label v-for="teacher in teachers" :key="`subject-form-teacher-${teacher.id}`" class="flex items-center gap-2 text-xs text-gray-200">
                                            <input
                                                v-model="subjectForm.teacher_user_ids"
                                                type="checkbox"
                                                :value="teacher.id"
                                                class="rounded border-gray-600 bg-gray-800 text-indigo-500"
                                            />
                                            <span>{{ teacher.name }}</span>
                                        </label>
                                    </div>
                                    <p v-if="teachers.length === 0" class="text-xs text-amber-300">
                                        لا يوجد معلمون متاحون في هذه المدرسة. أضف مستخدمًا تعليميًا (معلم) أولًا.
                                    </p>
                                </div>
                                <p v-if="subjectForm.errors.teacher_user_ids" class="mt-1 text-xs text-red-400">{{ subjectForm.errors.teacher_user_ids }}</p>
                                <p v-if="subjectForm.errors['teacher_user_ids.0']" class="mt-1 text-xs text-red-400">
                                    {{ subjectForm.errors['teacher_user_ids.0'] }}
                                </p>
                            </div>

                            <div class="flex justify-end gap-2 border-t border-gray-700 pt-3">
                                <button type="button" class="rounded bg-gray-700 px-3 py-2 text-sm hover:bg-gray-600" @click="closeSubjectModal">
                                    إلغاء
                                </button>
                                <button class="rounded bg-blue-700 px-3 py-2 text-sm font-bold hover:bg-blue-600" :disabled="subjectForm.processing">
                                    {{ subjectEditId ? 'حفظ التعديلات' : 'إضافة المادة' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div v-if="subjectBeingAssigned" class="mt-4 rounded border border-indigo-500/30 bg-indigo-500/10 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="flex items-center gap-2 text-sm font-bold text-indigo-200">
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-indigo-500/20 text-indigo-200">
                                <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="9" cy="8" r="3" />
                                    <path d="M4.5 18a4.5 4.5 0 0 1 9 0" />
                                    <path d="M15.5 12.5l1.8 1.8 3.2-3.2" />
                                </svg>
                            </span>
                            <span>إسناد المعلمين للمادة: {{ subjectBeingAssigned.name }}</span>
                        </p>
                        <button class="rounded bg-gray-700 px-2 py-1 text-xs hover:bg-gray-600" @click="cancelSubjectTeachers">إلغاء</button>
                    </div>

                    <form @submit.prevent="submitSubjectTeachers">
                        <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                            <label v-for="teacher in teachers" :key="teacher.id" class="flex items-center gap-2 rounded border border-gray-700 bg-gray-900 p-2 text-xs">
                                <input
                                    v-model="subjectTeachersForm.teacher_user_ids"
                                    type="checkbox"
                                    :value="teacher.id"
                                    class="rounded border-gray-600 bg-gray-800 text-indigo-500"
                                />
                                <span>{{ teacher.name }}</span>
                            </label>
                        </div>
                        <p v-if="teachers.length === 0" class="mt-2 text-xs text-amber-300">
                            لا يوجد معلمون متاحون في هذه المدرسة. أضف مستخدمًا تعليميًا (معلم) أولًا.
                        </p>
                        <p v-if="subjectTeachersForm.errors.teacher_user_ids" class="mt-2 text-xs text-red-400">
                            {{ subjectTeachersForm.errors.teacher_user_ids }}
                        </p>
                        <div class="mt-3 flex gap-2">
                            <button class="rounded bg-indigo-700 px-3 py-2 text-sm font-bold hover:bg-indigo-600" :disabled="subjectTeachersForm.processing">
                                حفظ الإسناد
                            </button>
                            <button type="button" class="rounded bg-gray-700 px-3 py-2 text-sm hover:bg-gray-600" @click="cancelSubjectTeachers">
                                إلغاء
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <section
                v-if="canManagePlanning && isSectionVisible('subjects')"
                ref="offeringSectionRef"
                style="order: 6;"
                class="academic-tree-section academic-tree-section--courses rounded-xl border border-cyan-500/70 bg-cyan-900/20 p-4"
            >
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="academic-tree-section-title flex items-center gap-2 text-lg font-bold">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-cyan-500/20 text-cyan-100">
                                <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="4" y="5" width="16" height="14" rx="2" />
                                    <path d="M8 9h8" />
                                    <path d="M8 13h6" />
                                </svg>
                            </span>
                            <span>6) المقررات المعتمدة حسب المرحلة والصف</span>
                        </h2>
                        <p class="academic-tree-section-copy mt-1 text-xs text-cyan-100/80">
                            أضف المقررات على مستوى الترم والمرحلة والصف ثم فعّل استخدامها في الاختبارات.
                        </p>
                    </div>
                    <button class="rounded-lg bg-gray-700 px-3 py-1.5 text-xs font-semibold hover:bg-gray-600" @click="resetCourseOfferingForm">جديد</button>
                </div>

                <form class="academic-tree-editor mb-4 rounded border border-gray-700 bg-gray-800 p-3" @submit.prevent="submitCourseOffering">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-8">
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">الترم</label>
                            <select v-model="courseOfferingForm.school_term_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="" disabled>اختر الترم</option>
                                <option v-for="term in terms" :key="`offering-term-${term.id}`" :value="term.id">{{ term.name }}</option>
                            </select>
                            <p v-if="courseOfferingForm.errors.school_term_id" class="mt-1 text-xs text-red-400">{{ courseOfferingForm.errors.school_term_id }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">المرحلة</label>
                            <select v-model="courseOfferingForm.school_stage_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="" disabled>اختر المرحلة</option>
                                <option v-for="stage in stageOptions" :key="`offering-stage-${stage.id}`" :value="stage.id">{{ stage.name }}</option>
                            </select>
                            <p v-if="courseOfferingForm.errors.school_stage_id" class="mt-1 text-xs text-red-400">{{ courseOfferingForm.errors.school_stage_id }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">الصف</label>
                            <select v-model="courseOfferingForm.school_stage_grade_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="" disabled>اختر الصف</option>
                                <option v-for="grade in courseOfferingGradeOptions" :key="`offering-grade-${grade.id}`" :value="grade.id">
                                    {{ grade.name }}
                                </option>
                            </select>
                            <p v-if="courseOfferingGradeOptions.length === 0" class="mt-1 text-xs text-amber-300">لا توجد صفوف فعّالة مرتبطة بهذه المرحلة.</p>
                            <p v-if="courseOfferingForm.errors.school_stage_grade_id" class="mt-1 text-xs text-red-400">{{ courseOfferingForm.errors.school_stage_grade_id }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">المقرر</label>
                            <select v-model="courseOfferingForm.school_subject_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="" disabled>اختر المقرر</option>
                                <option v-for="subject in activeSubjects" :key="`offering-subject-${subject.id}`" :value="subject.id">{{ subject.name }}</option>
                            </select>
                            <p v-if="courseOfferingForm.errors.school_subject_id" class="mt-1 text-xs text-red-400">{{ courseOfferingForm.errors.school_subject_id }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">الترتيب</label>
                            <input v-model.number="courseOfferingForm.sort_order" type="number" min="0" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="courseOfferingForm.errors.sort_order" class="mt-1 text-xs text-red-400">{{ courseOfferingForm.errors.sort_order }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">أيام التنبيه قبل نهاية الترم</label>
                            <input
                                v-model.number="courseOfferingForm.alert_before_term_end_days"
                                type="number"
                                min="0"
                                class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm"
                            />
                            <p v-if="courseOfferingForm.errors.alert_before_term_end_days" class="mt-1 text-xs text-red-400">
                                {{ courseOfferingForm.errors.alert_before_term_end_days }}
                            </p>
                        </div>
                        <div class="flex items-end">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input v-model="courseOfferingForm.usable_in_exams" type="checkbox" class="rounded border-gray-600 bg-gray-900 text-blue-500" />
                                <span>يُستخدم في الاختبارات</span>
                            </label>
                        </div>
                        <div class="flex items-end">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input v-model="courseOfferingForm.is_active" type="checkbox" class="rounded border-gray-600 bg-gray-900 text-blue-500" />
                                <span>نشط</span>
                            </label>
                        </div>
                    </div>

                    <div class="mt-4 mb-4 rounded border border-cyan-500/40 bg-gray-900/60 p-3">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h3 class="flex items-center gap-2 text-sm font-bold text-cyan-100">
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-cyan-500/20 text-cyan-100">
                                        <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-none stroke-current" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M6 4h12v16H6z" />
                                            <path d="M9 8h6" />
                                            <path d="M9 12h6" />
                                        </svg>
                                    </span>
                                    <span>الخطة الدراسية داخل المقرر</span>
                                </h3>
                                <p class="text-xs text-cyan-100/70">أضف الوحدات والدروس والموضوعات من خلال نافذة منبثقة دون تغيير منطق الحفظ الحالي.</p>
                            </div>
                            <button type="button" class="rounded bg-cyan-700 px-3 py-1.5 text-xs font-semibold hover:bg-cyan-600" @click="openStudyPlanModal">
                                {{ courseOfferingForm.study_plan_units.length > 0 ? 'تعديل الخطة الدراسية' : 'إضافة الخطة الدراسية' }}
                            </button>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2 rounded border border-gray-700 bg-gray-800/80 p-2">
                            <p class="text-xs text-gray-300">
                                عدد الوحدات الحالية: <span class="font-semibold text-cyan-200">{{ courseOfferingForm.study_plan_units.length }}</span>
                            </p>
                            <button
                                v-if="courseOfferingForm.study_plan_units.length > 0"
                                type="button"
                                class="rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600"
                                @click="clearStudyPlanUnits"
                            >
                                مسح الخطة الدراسية
                            </button>
                        </div>

                        <div v-if="courseOfferingForm.errors.study_plan_units" class="mt-2 text-xs text-red-400">
                            {{ courseOfferingForm.errors.study_plan_units }}
                        </div>
                    </div>

                    <div class="flex items-center gap-2 border-t border-gray-700 pt-3">
                        <button class="rounded bg-emerald-600 px-3 py-2 text-sm font-bold hover:bg-emerald-500" :disabled="courseOfferingForm.processing">
                            {{ courseOfferingEditId ? 'تحديث المقرر والخطة' : 'إضافة المقرر والخطة' }}
                        </button>
                        <button
                            v-if="courseOfferingEditId"
                            type="button"
                            class="rounded bg-gray-700 px-3 py-2 text-sm hover:bg-gray-600"
                            @click="resetCourseOfferingForm"
                        >
                            إلغاء
                        </button>
                    </div>
                </form>

                <div
                    v-if="isStudyPlanModalOpen"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                    @click.self="closeStudyPlanModal"
                >
                    <div class="w-full max-w-6xl overflow-hidden rounded-2xl border border-cyan-400/40 bg-gray-900 shadow-2xl">
                        <div class="flex items-center justify-between gap-2 border-b border-gray-700 bg-gray-800/90 p-4">
                            <div>
                                <h3 class="flex items-center gap-2 text-base font-bold text-cyan-100">
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-cyan-500/20 text-cyan-100">
                                        <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3z" />
                                            <path d="M12 12l8-4.5" />
                                            <path d="M12 12L4 7.5" />
                                        </svg>
                                    </span>
                                    <span>إدارة الخطة الدراسية للمقرر</span>
                                </h3>
                                <p class="text-xs text-cyan-100/70">اختر الفرع ثم أضف الوحدات، وداخل كل وحدة الدروس، وداخل كل درس الموضوعات.</p>
                            </div>
                            <button type="button" class="rounded bg-gray-700 px-3 py-1.5 text-xs hover:bg-gray-600" @click="closeStudyPlanModal">
                                إغلاق
                            </button>
                        </div>

                        <div class="max-h-[75vh] overflow-y-auto p-4">
                            <div class="mb-3 grid grid-cols-1 gap-2 rounded border border-gray-700 bg-gray-800/80 p-3 md:grid-cols-4">
                                <div>
                                    <label class="mb-1 block text-xs text-gray-300">المادة</label>
                                    <select v-model="courseOfferingForm.school_subject_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                        <option value="" disabled>اختر المقرر</option>
                                        <option v-for="subject in activeSubjects" :key="`modal-offering-subject-${subject.id}`" :value="subject.id">{{ subject.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-gray-300">الفرع</label>
                                    <select v-model="studyPlanDefaultBranch" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                        <option v-for="branch in subjectBranchesForCourseOffering" :key="`default-subject-branch-${branch}`" :value="branch">
                                            {{ branch }}
                                        </option>
                                    </select>
                                </div>
                                <div class="flex items-end">
                                    <button type="button" class="w-full rounded bg-cyan-700 px-3 py-2 text-sm font-semibold hover:bg-cyan-600" @click="addStudyPlanUnit">
                                        إضافة وحدة
                                    </button>
                                </div>
                                <div class="flex items-end">
                                    <button
                                        type="button"
                                        class="w-full rounded bg-red-700 px-3 py-2 text-sm font-semibold hover:bg-red-600 disabled:opacity-50"
                                        :disabled="courseOfferingForm.study_plan_units.length === 0"
                                        @click="clearStudyPlanUnits"
                                    >
                                        حذف كل الوحدات
                                    </button>
                                </div>
                            </div>

                            <div v-if="courseOfferingForm.errors.study_plan_units" class="mb-2 text-xs text-red-400">
                                {{ courseOfferingForm.errors.study_plan_units }}
                            </div>

                            <div class="space-y-3">
                                <div
                                    v-for="(unit, unitIndex) in courseOfferingForm.study_plan_units"
                                    :key="`plan-unit-${unitIndex}`"
                                    class="rounded border border-cyan-500/40 bg-gray-800/80 p-3"
                                >
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <h4 class="flex items-center gap-1.5 text-sm font-semibold text-cyan-100">
                                            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-none stroke-current" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                <rect x="5" y="6" width="14" height="12" rx="2" />
                                                <path d="M9 10h6" />
                                            </svg>
                                            <span>الوحدة {{ unitIndex + 1 }}</span>
                                        </h4>
                                        <button type="button" class="rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600" @click="removeStudyPlanUnit(unitIndex)">
                                            حذف الوحدة
                                        </button>
                                    </div>

                                    <div class="grid grid-cols-1 gap-2 md:grid-cols-5">
                                        <div>
                                            <label class="mb-1 block text-xs text-gray-400">الفرع</label>
                                            <select v-model="unit.branch_name" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                                <option v-for="branch in subjectBranchesForCourseOffering" :key="`subject-branch-${branch}`" :value="branch">
                                                    {{ branch }}
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs text-gray-400">اسم الوحدة</label>
                                            <input v-model="unit.name" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs text-gray-400">بداية الوحدة</label>
                                            <input v-model="unit.start_date" type="date" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs text-gray-400">نهاية الوحدة</label>
                                            <input v-model="unit.end_date" type="date" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs text-gray-400">ملاحظات</label>
                                            <input v-model="unit.notes" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                                        </div>
                                    </div>

                                    <div class="mt-3 rounded border border-gray-700 bg-gray-900/60 p-2">
                                        <div class="mb-2 flex items-center justify-between gap-2">
                                            <h5 class="flex items-center gap-1.5 text-xs font-bold text-gray-300">
                                                <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-none stroke-current" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M6 5h12v14H6z" />
                                                    <path d="M9 9h6" />
                                                </svg>
                                                <span>الدروس</span>
                                            </h5>
                                            <button type="button" class="rounded bg-indigo-700 px-2 py-1 text-xs hover:bg-indigo-600" @click="addStudyPlanLesson(unitIndex)">
                                                إضافة درس
                                            </button>
                                        </div>

                                        <div class="space-y-2">
                                            <div
                                                v-for="(lesson, lessonIndex) in unit.lessons"
                                                :key="`plan-lesson-${unitIndex}-${lessonIndex}`"
                                                class="rounded border border-indigo-500/30 bg-gray-800/70 p-2"
                                            >
                                                <div class="mb-2 flex items-center justify-between gap-2">
                                                    <h6 class="flex items-center gap-1.5 text-xs font-semibold text-indigo-100">
                                                        <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-none stroke-current" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M7 5h10v14H7z" />
                                                            <path d="M9.5 9h5" />
                                                        </svg>
                                                        <span>الدرس {{ lessonIndex + 1 }}</span>
                                                    </h6>
                                                    <button
                                                        type="button"
                                                        class="rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600"
                                                        @click="removeStudyPlanLesson(unitIndex, lessonIndex)"
                                                    >
                                                        حذف الدرس
                                                    </button>
                                                </div>

                                                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                                    <div>
                                                        <label class="mb-1 block text-xs text-gray-400">اسم الدرس</label>
                                                        <input v-model="lesson.name" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                                                    </div>
                                                    <div>
                                                        <label class="mb-1 block text-xs text-gray-400">وصف مختصر</label>
                                                        <input v-model="lesson.description" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                                                    </div>
                                                </div>

                                                <div class="mt-2 rounded border border-gray-700 bg-gray-900/60 p-2">
                                                    <div class="mb-2 flex items-center justify-between gap-2">
                                                        <h6 class="flex items-center gap-1.5 text-xs font-semibold text-gray-300">
                                                            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-none stroke-current" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                                                <circle cx="8" cy="8" r="2" />
                                                                <circle cx="16" cy="8" r="2" />
                                                                <circle cx="12" cy="15" r="2" />
                                                                <path d="M10 9.5l1.2 3.5" />
                                                                <path d="M14 9.5l-1.2 3.5" />
                                                            </svg>
                                                            <span>الموضوعات</span>
                                                        </h6>
                                                        <button
                                                            type="button"
                                                            class="rounded bg-emerald-700 px-2 py-1 text-xs hover:bg-emerald-600"
                                                            @click="addStudyPlanTopic(unitIndex, lessonIndex)"
                                                        >
                                                            إضافة موضوع
                                                        </button>
                                                    </div>

                                                    <div class="space-y-2">
                                                        <div
                                                            v-for="(topic, topicIndex) in lesson.topics"
                                                            :key="`plan-topic-${unitIndex}-${lessonIndex}-${topicIndex}`"
                                                            class="rounded border border-emerald-500/30 bg-gray-800/60 p-2"
                                                        >
                                                            <div class="mb-2 flex items-center justify-between gap-2">
                                                                <p class="text-xs font-semibold text-emerald-100">الموضوع {{ topicIndex + 1 }}</p>
                                                                <button
                                                                    type="button"
                                                                    class="rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600"
                                                                    @click="removeStudyPlanTopic(unitIndex, lessonIndex, topicIndex)"
                                                                >
                                                                    حذف الموضوع
                                                                </button>
                                                            </div>
                                                            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                                                <input v-model="topic.name" placeholder="اسم الموضوع" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                                                                <input v-model="topic.description" placeholder="وصف مختصر" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="courseOfferingForm.study_plan_units.length === 0"
                                    class="rounded border border-gray-700 bg-gray-800/70 p-4 text-sm text-gray-300"
                                >
                                    لا توجد وحدات بعد. اختر الفرع ثم اضغط "إضافة وحدة".
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end border-t border-gray-700 bg-gray-800/80 p-4">
                            <button type="button" class="rounded bg-cyan-700 px-4 py-2 text-sm font-semibold hover:bg-cyan-600" @click="closeStudyPlanModal">
                                تم
                            </button>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="academic-tree-filter academic-tree-filter--cyan rounded-2xl border border-cyan-200 bg-white/90 p-3 text-slate-900 shadow-sm dark:border-cyan-500/30 dark:bg-gray-950/60 dark:text-gray-100">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2 text-sm font-bold text-cyan-800 dark:text-cyan-100">
                                <Filter class="h-4 w-4" />
                                <span>تصفية المقررات المعتمدة</span>
                            </div>
                            <button
                                type="button"
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                                :disabled="!hasApprovedCourseFilters"
                                @click="resetApprovedCourseFilters"
                            >
                                مسح الفلاتر
                            </button>
                        </div>

                        <div class="grid grid-cols-1 gap-2 md:grid-cols-3 xl:grid-cols-7">
                            <label class="space-y-1 md:col-span-2">
                                <span class="text-xs text-slate-600 dark:text-gray-400">بحث سريع</span>
                                <span class="relative block">
                                    <Search class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-gray-500" />
                                    <input
                                        v-model="approvedCourseFilters.search"
                                        type="search"
                                        class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-3 pr-9 text-sm text-slate-900 outline-none transition focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                        placeholder="اسم المقرر، المعلم، الصف أو الفصل"
                                    />
                                </span>
                            </label>

                            <label class="space-y-1">
                                <span class="text-xs text-slate-600 dark:text-gray-400">المرحلة</span>
                                <select v-model="approvedCourseFilters.stage_id" class="w-full rounded-xl border border-slate-300 bg-white p-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                    <option value="">كل المراحل</option>
                                    <option v-for="stage in approvedCourseStageFilterOptions" :key="`approved-filter-stage-${stage.id}`" :value="stage.id">
                                        {{ stage.name }}
                                    </option>
                                </select>
                            </label>

                            <label class="space-y-1">
                                <span class="text-xs text-slate-600 dark:text-gray-400">الصف</span>
                                <select v-model="approvedCourseFilters.grade_id" class="w-full rounded-xl border border-slate-300 bg-white p-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                    <option value="">كل الصفوف</option>
                                    <option v-for="grade in approvedCourseGradeFilterOptions" :key="`approved-filter-grade-${grade.id}`" :value="grade.id">
                                        {{ grade.name }}
                                    </option>
                                </select>
                            </label>

                            <label class="space-y-1">
                                <span class="text-xs text-slate-600 dark:text-gray-400">الفصل الدراسي</span>
                                <select v-model="approvedCourseFilters.term_id" class="w-full rounded-xl border border-slate-300 bg-white p-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                    <option value="">كل الفصول</option>
                                    <option v-for="term in terms" :key="`approved-filter-term-${term.id}`" :value="term.id">
                                        {{ term.name }}
                                    </option>
                                </select>
                            </label>

                            <label class="space-y-1">
                                <span class="text-xs text-slate-600 dark:text-gray-400">المادة</span>
                                <select v-model="approvedCourseFilters.subject_id" class="w-full rounded-xl border border-slate-300 bg-white p-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                    <option value="">كل المواد</option>
                                    <option v-for="subject in activeSubjects" :key="`approved-filter-subject-${subject.id}`" :value="subject.id">
                                        {{ subject.name }}
                                    </option>
                                </select>
                            </label>

                            <label class="space-y-1">
                                <span class="text-xs text-slate-600 dark:text-gray-400">الحالة</span>
                                <select v-model="approvedCourseFilters.status" class="w-full rounded-xl border border-slate-300 bg-white p-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                    <option value="">كل الحالات</option>
                                    <option value="active">نشط</option>
                                    <option value="inactive">غير نشط</option>
                                    <option value="usable">متاح للاختبارات</option>
                                    <option value="not_usable">غير متاح للاختبارات</option>
                                    <option value="assigned">له معلم مسند</option>
                                    <option value="unassigned">بلا معلم</option>
                                </select>
                            </label>
                        </div>

                        <label class="mt-2 block max-w-sm space-y-1">
                            <span class="text-xs text-slate-600 dark:text-gray-400">المعلم</span>
                            <select v-model="approvedCourseFilters.teacher_id" class="w-full rounded-xl border border-slate-300 bg-white p-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                <option value="">كل المعلمين</option>
                                <option v-for="teacher in teachers" :key="`approved-filter-teacher-${teacher.id}`" :value="teacher.id">
                                    {{ teacher.name }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="stage in approvedCoursesTreeForDisplay"
                            :key="`approved-stage-${stage.key}`"
                            class="academic-tree-stage-card stage-row-accent overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-950/70"
                            :style="stageAccent(stage.id, stage.name)"
                        >
                            <button
                                type="button"
                                class="flex w-full flex-wrap items-center justify-between gap-3 p-4 text-right transition hover:bg-slate-50 dark:hover:bg-gray-900/80"
                                @click="toggleApprovedCourseStage(stage)"
                            >
                                <span class="flex items-center gap-3">
                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-cyan-500/15 text-cyan-700 dark:text-cyan-200">
                                        <School class="h-5 w-5" />
                                    </span>
                                    <span>
                                        <span class="block text-base font-bold text-slate-900 dark:text-gray-100">{{ stage.name }}</span>
                                        <span class="text-xs text-slate-500 dark:text-gray-400">
                                            {{ stage.grades_count }} صفوف، {{ stage.courses_count }} مقررات، {{ stage.teachers_count }} معلمين
                                        </span>
                                    </span>
                                </span>
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-cyan-500/15 px-2.5 py-1 text-[11px] font-semibold text-cyan-700 dark:text-cyan-200">
                                        {{ stage.subjects_count }} مواد
                                    </span>
                                    <span class="rounded-full bg-emerald-500/15 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-200">
                                        {{ stage.assigned_classrooms_count }} فصول مسندة
                                    </span>
                                    <ChevronDown v-if="isApprovedCourseStageOpen(stage)" class="h-5 w-5 text-slate-500 dark:text-gray-400" />
                                    <ChevronLeft v-else class="h-5 w-5 text-slate-500 dark:text-gray-400" />
                                </span>
                            </button>

                            <div v-if="isApprovedCourseStageOpen(stage)" class="space-y-3 border-t border-slate-200 p-3 dark:border-gray-800">
                                <div
                                    v-for="grade in stage.grades"
                                    :key="`approved-grade-${grade.key}`"
                                    class="academic-tree-branch-card overflow-hidden rounded-xl border border-slate-200 bg-slate-50/80 dark:border-gray-800 dark:bg-gray-900/70"
                                >
                                    <button
                                        type="button"
                                        class="flex w-full flex-wrap items-center justify-between gap-3 p-3 text-right transition hover:bg-white dark:hover:bg-gray-800"
                                        @click="toggleApprovedCourseGrade(grade)"
                                    >
                                        <span class="flex items-center gap-2">
                                            <BookOpenText class="h-4 w-4 text-cyan-600 dark:text-cyan-300" />
                                            <span>
                                                <span class="block text-sm font-bold text-slate-900 dark:text-gray-100">{{ grade.name }}</span>
                                                <span class="text-xs text-slate-500 dark:text-gray-400">
                                                    {{ grade.terms_count }} فصول دراسية، {{ grade.courses_count }} مقررات
                                                </span>
                                            </span>
                                        </span>
                                        <span class="flex flex-wrap items-center gap-2">
                                            <span class="rounded-full bg-indigo-500/15 px-2 py-1 text-[11px] text-indigo-700 dark:text-indigo-200">
                                                {{ grade.active_courses_count }} نشط
                                            </span>
                                            <ChevronDown v-if="isApprovedCourseGradeOpen(grade)" class="h-4 w-4 text-slate-500 dark:text-gray-400" />
                                            <ChevronLeft v-else class="h-4 w-4 text-slate-500 dark:text-gray-400" />
                                        </span>
                                    </button>

                                    <div v-if="isApprovedCourseGradeOpen(grade)" class="space-y-2 border-t border-slate-200 p-3 dark:border-gray-800">
                                        <div
                                            v-for="term in grade.terms"
                                            :key="`approved-term-${term.key}`"
                                            class="academic-tree-term-card overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-gray-800 dark:bg-gray-950/70"
                                        >
                                            <button
                                                type="button"
                                                class="flex w-full flex-wrap items-center justify-between gap-3 p-3 text-right transition hover:bg-slate-50 dark:hover:bg-gray-900"
                                                @click="toggleApprovedCourseTerm(term)"
                                            >
                                                <span class="flex items-center gap-2">
                                                    <CalendarDays class="h-4 w-4 text-cyan-600 dark:text-cyan-300" />
                                                    <span>
                                                        <span class="block text-sm font-bold text-slate-900 dark:text-gray-100">{{ term.name }}</span>
                                                        <span class="text-xs text-slate-500 dark:text-gray-400">
                                                            {{ term.courses_count }} مقررات، {{ term.assigned_classrooms_count }} فصول مسندة
                                                        </span>
                                                    </span>
                                                </span>
                                                <span class="flex flex-wrap items-center gap-2">
                                                    <span class="rounded-full bg-emerald-500/15 px-2 py-1 text-[11px] text-emerald-700 dark:text-emerald-200">
                                                        {{ term.teachers_count }} معلمين
                                                    </span>
                                                    <ChevronDown v-if="isApprovedCourseTermOpen(term)" class="h-4 w-4 text-slate-500 dark:text-gray-400" />
                                                    <ChevronLeft v-else class="h-4 w-4 text-slate-500 dark:text-gray-400" />
                                                </span>
                                            </button>

                                            <div v-if="isApprovedCourseTermOpen(term)" class="border-t border-slate-200 p-3 dark:border-gray-800">
                                                <div v-if="term.courses.length > 0" class="grid grid-cols-1 gap-3 xl:grid-cols-2">
                                                    <article
                                                        v-for="course in term.courses"
                                                        :key="`approved-course-${course.id}`"
                                                        class="academic-tree-course-card stage-row-accent rounded-xl border border-slate-200 bg-slate-50 p-3 shadow-sm dark:border-gray-800 dark:bg-gray-900/80"
                                                        :style="stageAccent(course.school_stage_id || stage.id, course.stage_name || stage.name)"
                                                    >
                                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                                            <div class="min-w-0">
                                                                <p class="stage-inline-accent flex flex-wrap items-center gap-2 font-bold text-slate-900 dark:text-gray-100" :style="stageAccent(course.school_stage_id || stage.id, course.stage_name || stage.name)">
                                                                    <span class="stage-badge" :style="stageAccent(course.school_stage_id || stage.id, course.stage_name || stage.name)">
                                                                        {{ course.subject_name }}
                                                                    </span>
                                                                    <span class="text-xs font-semibold text-slate-500 dark:text-gray-400">{{ course.grade_name }}</span>
                                                                </p>
                                                                <div class="mt-2 grid gap-1 text-xs text-slate-600 dark:text-gray-400 sm:grid-cols-2">
                                                                    <span class="flex items-center gap-1">
                                                                        <UserRound class="h-3.5 w-3.5" />
                                                                        المعلم: {{ course.teacher_name }}
                                                                    </span>
                                                                    <span class="flex items-center gap-1">
                                                                        <Users class="h-3.5 w-3.5" />
                                                                        الفصول: {{ assignmentClassroomsLabel(course) }}
                                                                    </span>
                                                                </div>
                                                                <p v-if="courseOfferingTermAlertMessage(course)" class="mt-2 text-xs text-amber-600 dark:text-amber-300">
                                                                    {{ courseOfferingTermAlertMessage(course) }}
                                                                </p>
                                                                <div class="mt-2 flex flex-wrap gap-1">
                                                                    <span class="rounded-full px-2 py-1 text-[10px]" :class="course.is_active ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-200' : 'bg-slate-200 text-slate-600 dark:bg-gray-800 dark:text-gray-300'">
                                                                        {{ course.is_active ? 'نشط' : 'غير نشط' }}
                                                                    </span>
                                                                    <span class="rounded-full px-2 py-1 text-[10px]" :class="(course.usable_in_exams ?? true) ? 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-200' : 'bg-amber-500/15 text-amber-700 dark:text-amber-200'">
                                                                        {{ (course.usable_in_exams ?? true) ? 'متاح للاختبارات' : 'غير متاح للاختبارات' }}
                                                                    </span>
                                                                    <span
                                                                        v-for="badge in assignmentPermissionBadges(course.teaching_assignment)"
                                                                        :key="`approved-permission-${course.id}-${badge}`"
                                                                        class="rounded-full bg-cyan-500/15 px-2 py-1 text-[10px] text-cyan-700 dark:text-cyan-200"
                                                                    >
                                                                        {{ badge }}
                                                                    </span>
                                                                </div>
                                                            </div>

                                                            <div class="flex flex-wrap items-center gap-2">
                                                                <button type="button" class="rounded-lg bg-blue-700 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-blue-600" @click="editCourseOffering(course)">
                                                                    تعديل
                                                                </button>
                                                                <button
                                                                    v-if="canManageTeachingAssignments"
                                                                    type="button"
                                                                    class="rounded-lg bg-indigo-700 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-indigo-600"
                                                                    @click="openTeachingAssignmentForm(course)"
                                                                >
                                                                    منح الاختبارات
                                                                </button>
                                                                <button type="button" class="rounded-lg bg-red-700 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-red-600" @click="removeCourseOffering(course.id)">
                                                                    حذف
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </article>
                                                </div>
                                                <div v-else class="academic-tree-empty rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600 dark:border-gray-700 dark:bg-gray-900/70 dark:text-gray-300">
                                                    لا توجد مقررات داخل هذا الفصل الدراسي.
                                                </div>
                                            </div>
                                        </div>

                                        <div v-if="grade.terms.length === 0" class="academic-tree-empty rounded-xl border border-dashed border-slate-300 bg-white p-4 text-sm text-slate-600 dark:border-gray-700 dark:bg-gray-950/50 dark:text-gray-300">
                                            لا توجد مقررات داخل هذا الصف.
                                        </div>
                                    </div>
                                </div>

                                <div v-if="stage.grades.length === 0" class="academic-tree-empty rounded-xl border border-dashed border-slate-300 bg-white p-4 text-sm text-slate-600 dark:border-gray-700 dark:bg-gray-900/70 dark:text-gray-300">
                                    لا توجد صفوف داخل هذه المرحلة.
                                </div>
                            </div>
                        </div>

                        <div v-if="approvedCoursesTreeForDisplay.length === 0" class="academic-tree-empty rounded-2xl border border-dashed border-gray-700 bg-gray-800/70 p-5 text-sm text-gray-300">
                            {{ hasApprovedCourseFilters ? 'لا توجد نتائج مطابقة للفلاتر الحالية.' : 'لا توجد مقررات معتمدة بعد. أضف المقررات الدراسية أولًا ثم اسندها للمعلمين.' }}
                        </div>
                    </div>
                </div>
            </section>

            <section
                v-if="canManageTeachingAssignments && isSectionVisible('subjects')"
                ref="teachingAssignmentSectionRef"
                style="order: 7;"
                class="academic-tree-section academic-tree-section--assignments rounded-xl border border-emerald-500/70 bg-emerald-900/25 p-4"
            >
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="academic-tree-section-title flex items-center gap-2 text-lg font-bold">
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-500/20 text-emerald-100">
                                <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="9" cy="8" r="3" />
                                    <path d="M4.5 18a4.5 4.5 0 0 1 9 0" />
                                    <path d="M15.5 12.5l1.8 1.8 3.2-3.2" />
                                </svg>
                            </span>
                            <span>7) إسناد المقررات وصلاحيات الاختبارات</span>
                        </h2>
                        <p class="academic-tree-section-copy mt-1 text-xs text-emerald-100/80">
                            خصص المعلم لكل مقرر ثم فعّل الصلاحيات التشغيلية للاختبارات ضمن نفس المدرسة.
                        </p>
                    </div>
                    <button class="rounded-lg bg-gray-700 px-3 py-1.5 text-xs font-semibold hover:bg-gray-600" @click="resetTeachingAssignmentForm">إغلاق التعديل</button>
                </div>

                <div class="space-y-4">
                    <div class="academic-tree-filter academic-tree-filter--emerald rounded-2xl border border-emerald-200 bg-white/90 p-3 text-slate-900 shadow-sm dark:border-emerald-500/30 dark:bg-gray-950/60 dark:text-gray-100">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2 text-sm font-bold text-emerald-800 dark:text-emerald-100">
                                <Filter class="h-4 w-4" />
                                <span>تصفية الإسنادات وصلاحيات الاختبارات</span>
                            </div>
                            <button
                                type="button"
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                                :disabled="!hasCourseAssignmentFilters"
                                @click="resetCourseAssignmentFilters"
                            >
                                مسح الفلاتر
                            </button>
                        </div>

                        <div class="grid grid-cols-1 gap-2 md:grid-cols-3 xl:grid-cols-7">
                            <label class="space-y-1 md:col-span-2">
                                <span class="text-xs text-slate-600 dark:text-gray-400">بحث سريع</span>
                                <span class="relative block">
                                    <Search class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400 dark:text-gray-500" />
                                    <input
                                        v-model="courseAssignmentFilters.search"
                                        type="search"
                                        class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-3 pr-9 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                        placeholder="المقرر، المعلم، الصف، الفصل أو صلاحية الاختبار"
                                    />
                                </span>
                            </label>

                            <label class="space-y-1">
                                <span class="text-xs text-slate-600 dark:text-gray-400">المرحلة</span>
                                <select v-model="courseAssignmentFilters.stage_id" class="w-full rounded-xl border border-slate-300 bg-white p-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                    <option value="">كل المراحل</option>
                                    <option v-for="stage in courseAssignmentStageFilterOptions" :key="`assignment-filter-stage-${stage.id}`" :value="stage.id">
                                        {{ stage.name }}
                                    </option>
                                </select>
                            </label>

                            <label class="space-y-1">
                                <span class="text-xs text-slate-600 dark:text-gray-400">الصف</span>
                                <select v-model="courseAssignmentFilters.grade_id" class="w-full rounded-xl border border-slate-300 bg-white p-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                    <option value="">كل الصفوف</option>
                                    <option v-for="grade in courseAssignmentGradeFilterOptions" :key="`assignment-filter-grade-${grade.id}`" :value="grade.id">
                                        {{ grade.name }}
                                    </option>
                                </select>
                            </label>

                            <label class="space-y-1">
                                <span class="text-xs text-slate-600 dark:text-gray-400">الفصل الدراسي</span>
                                <select v-model="courseAssignmentFilters.term_id" class="w-full rounded-xl border border-slate-300 bg-white p-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                    <option value="">كل الفصول</option>
                                    <option v-for="term in terms" :key="`assignment-filter-term-${term.id}`" :value="term.id">
                                        {{ term.name }}
                                    </option>
                                </select>
                            </label>

                            <label class="space-y-1">
                                <span class="text-xs text-slate-600 dark:text-gray-400">المادة</span>
                                <select v-model="courseAssignmentFilters.subject_id" class="w-full rounded-xl border border-slate-300 bg-white p-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                    <option value="">كل المواد</option>
                                    <option v-for="subject in activeSubjects" :key="`assignment-filter-subject-${subject.id}`" :value="subject.id">
                                        {{ subject.name }}
                                    </option>
                                </select>
                            </label>

                            <label class="space-y-1">
                                <span class="text-xs text-slate-600 dark:text-gray-400">حالة الإسناد</span>
                                <select v-model="courseAssignmentFilters.assignment_status" class="w-full rounded-xl border border-slate-300 bg-white p-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                    <option value="">كل الحالات</option>
                                    <option value="complete">مكتمل</option>
                                    <option value="incomplete">غير مكتمل</option>
                                    <option value="no_teacher">بدون معلم</option>
                                    <option value="no_classrooms">بدون فصول</option>
                                </select>
                            </label>
                        </div>

                        <div class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-3">
                            <label class="space-y-1">
                                <span class="text-xs text-slate-600 dark:text-gray-400">المعلم</span>
                                <select v-model="courseAssignmentFilters.teacher_id" class="w-full rounded-xl border border-slate-300 bg-white p-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                    <option value="">كل المعلمين</option>
                                    <option v-for="teacher in teachers" :key="`assignment-filter-teacher-${teacher.id}`" :value="teacher.id">
                                        {{ teacher.name }}
                                    </option>
                                </select>
                            </label>

                            <label class="space-y-1">
                                <span class="text-xs text-slate-600 dark:text-gray-400">حالة الصلاحيات</span>
                                <select v-model="courseAssignmentFilters.permission_status" class="w-full rounded-xl border border-slate-300 bg-white p-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                    <option value="">كل الصلاحيات</option>
                                    <option value="has_permissions">لديه صلاحيات اختبارات</option>
                                    <option value="no_permissions">بدون صلاحيات اختبارات</option>
                                </select>
                            </label>

                            <label class="space-y-1">
                                <span class="text-xs text-slate-600 dark:text-gray-400">حالة المقرر</span>
                                <select v-model="courseAssignmentFilters.course_status" class="w-full rounded-xl border border-slate-300 bg-white p-2 text-sm text-slate-900 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                    <option value="">كل المقررات</option>
                                    <option value="active">نشط</option>
                                    <option value="inactive">غير نشط</option>
                                </select>
                            </label>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="stage in courseAssignmentsTreeForDisplay"
                            :key="`assignment-stage-${stage.key}`"
                            class="academic-tree-stage-card stage-row-accent overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-950/70"
                            :style="stageAccent(stage.id, stage.name)"
                        >
                            <button
                                type="button"
                                class="flex w-full flex-wrap items-center justify-between gap-3 p-4 text-right transition hover:bg-slate-50 dark:hover:bg-gray-900/80"
                                @click="toggleCourseAssignmentStage(stage)"
                            >
                                <span class="flex items-center gap-3">
                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-700 dark:text-emerald-200">
                                        <School class="h-5 w-5" />
                                    </span>
                                    <span>
                                        <span class="block text-base font-bold text-slate-900 dark:text-gray-100">{{ stage.name }}</span>
                                        <span class="text-xs text-slate-500 dark:text-gray-400">
                                            {{ stage.grades_count }} صفوف، {{ stage.assignments_count }} مقررات، {{ stage.teachers_count }} معلمين
                                        </span>
                                    </span>
                                </span>
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-emerald-500/15 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-200">
                                        {{ stage.assigned_classrooms_count }} فصول مسندة
                                    </span>
                                    <span class="rounded-full bg-indigo-500/15 px-2.5 py-1 text-[11px] font-semibold text-indigo-700 dark:text-indigo-200">
                                        {{ stage.permissions_count }} صلاحيات
                                    </span>
                                    <ChevronDown v-if="isCourseAssignmentStageOpen(stage)" class="h-5 w-5 text-slate-500 dark:text-gray-400" />
                                    <ChevronLeft v-else class="h-5 w-5 text-slate-500 dark:text-gray-400" />
                                </span>
                            </button>

                            <div v-if="isCourseAssignmentStageOpen(stage)" class="space-y-3 border-t border-slate-200 p-3 dark:border-gray-800">
                                <div
                                    v-for="grade in stage.grades"
                                    :key="`assignment-grade-${grade.key}`"
                                    class="academic-tree-branch-card overflow-hidden rounded-xl border border-slate-200 bg-slate-50/80 dark:border-gray-800 dark:bg-gray-900/70"
                                >
                                    <button
                                        type="button"
                                        class="flex w-full flex-wrap items-center justify-between gap-3 p-3 text-right transition hover:bg-white dark:hover:bg-gray-800"
                                        @click="toggleCourseAssignmentGrade(grade)"
                                    >
                                        <span class="flex items-center gap-2">
                                            <BookOpenText class="h-4 w-4 text-emerald-600 dark:text-emerald-300" />
                                            <span>
                                                <span class="block text-sm font-bold text-slate-900 dark:text-gray-100">{{ grade.name }}</span>
                                                <span class="text-xs text-slate-500 dark:text-gray-400">
                                                    {{ grade.terms_count }} فصول دراسية، {{ grade.assignments_count }} مقررات قابلة للإسناد
                                                </span>
                                            </span>
                                        </span>
                                        <span class="flex flex-wrap items-center gap-2">
                                            <span class="rounded-full bg-emerald-500/15 px-2 py-1 text-[11px] text-emerald-700 dark:text-emerald-200">
                                                {{ grade.complete_assignments_count }} مكتمل
                                            </span>
                                            <span v-if="grade.incomplete_assignments_count > 0" class="rounded-full bg-amber-500/15 px-2 py-1 text-[11px] text-amber-700 dark:text-amber-200">
                                                {{ grade.incomplete_assignments_count }} يحتاج ضبط
                                            </span>
                                            <ChevronDown v-if="isCourseAssignmentGradeOpen(grade)" class="h-4 w-4 text-slate-500 dark:text-gray-400" />
                                            <ChevronLeft v-else class="h-4 w-4 text-slate-500 dark:text-gray-400" />
                                        </span>
                                    </button>

                                    <div v-if="isCourseAssignmentGradeOpen(grade)" class="space-y-2 border-t border-slate-200 p-3 dark:border-gray-800">
                                        <div
                                            v-for="term in grade.terms"
                                            :key="`assignment-term-${term.key}`"
                                            class="academic-tree-term-card overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-gray-800 dark:bg-gray-950/70"
                                        >
                                            <button
                                                type="button"
                                                class="flex w-full flex-wrap items-center justify-between gap-3 p-3 text-right transition hover:bg-slate-50 dark:hover:bg-gray-900"
                                                @click="toggleCourseAssignmentTerm(term)"
                                            >
                                                <span class="flex items-center gap-2">
                                                    <CalendarDays class="h-4 w-4 text-emerald-600 dark:text-emerald-300" />
                                                    <span>
                                                        <span class="block text-sm font-bold text-slate-900 dark:text-gray-100">{{ term.name }}</span>
                                                        <span class="text-xs text-slate-500 dark:text-gray-400">
                                                            {{ term.assignments_count }} مقررات، {{ term.teachers_count }} معلمين، {{ term.permissions_count }} صلاحيات
                                                        </span>
                                                    </span>
                                                </span>
                                                <span class="flex flex-wrap items-center gap-2">
                                                    <span class="rounded-full bg-indigo-500/15 px-2 py-1 text-[11px] text-indigo-700 dark:text-indigo-200">
                                                        {{ term.assigned_classrooms_count }} فصول
                                                    </span>
                                                    <ChevronDown v-if="isCourseAssignmentTermOpen(term)" class="h-4 w-4 text-slate-500 dark:text-gray-400" />
                                                    <ChevronLeft v-else class="h-4 w-4 text-slate-500 dark:text-gray-400" />
                                                </span>
                                            </button>

                                            <div v-if="isCourseAssignmentTermOpen(term)" class="border-t border-slate-200 p-3 dark:border-gray-800">
                                                <div v-if="term.courses.length > 0" class="grid grid-cols-1 gap-3 xl:grid-cols-2">
                                                    <article
                                                        v-for="course in term.courses"
                                                        :key="`assignment-course-${course.id}`"
                                                        class="academic-tree-course-card stage-row-accent overflow-hidden rounded-xl border border-slate-200 bg-slate-50 shadow-sm dark:border-gray-800 dark:bg-gray-900/80"
                                                        :style="stageAccent(course.school_stage_id || stage.id, course.stage_name || stage.name)"
                                                    >
                                                        <div class="p-3">
                                                            <div class="flex flex-wrap items-start justify-between gap-3">
                                                                <button
                                                                    type="button"
                                                                    class="min-w-0 flex-1 text-right"
                                                                    @click="toggleCourseAssignment(course)"
                                                                >
                                                                    <p class="stage-inline-accent flex flex-wrap items-center gap-2 font-bold text-slate-900 dark:text-gray-100" :style="stageAccent(course.school_stage_id || stage.id, course.stage_name || stage.name)">
                                                                        <span class="stage-badge" :style="stageAccent(course.school_stage_id || stage.id, course.stage_name || stage.name)">
                                                                            {{ course.subject_name }}
                                                                        </span>
                                                                        <span class="text-xs font-semibold text-slate-500 dark:text-gray-400">{{ course.grade_name }}</span>
                                                                    </p>
                                                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-600 dark:text-gray-400">
                                                                        <span class="inline-flex items-center gap-1">
                                                                            <UserRound class="h-3.5 w-3.5" />
                                                                            {{ course.teacher_name }}
                                                                        </span>
                                                                        <span class="inline-flex items-center gap-1">
                                                                            <Users class="h-3.5 w-3.5" />
                                                                            {{ assignmentClassroomsLabel(course) }}
                                                                        </span>
                                                                    </div>
                                                                </button>

                                                                <div class="flex flex-wrap items-center gap-2">
                                                                    <span class="rounded-full px-2 py-1 text-[10px] font-semibold" :class="courseAssignmentStatus(course).tone">
                                                                        {{ courseAssignmentStatus(course).label }}
                                                                    </span>
                                                                    <ChevronDown v-if="isCourseAssignmentOpen(course)" class="h-4 w-4 text-slate-500 dark:text-gray-400" />
                                                                    <ChevronLeft v-else class="h-4 w-4 text-slate-500 dark:text-gray-400" />
                                                                </div>
                                                            </div>

                                                            <div class="mt-3 flex flex-wrap gap-1">
                                                                <span
                                                                    v-for="badge in assignmentPermissionBadges(course.teaching_assignment)"
                                                                    :key="`assignment-tree-badge-${course.id}-${badge}`"
                                                                    class="rounded-full bg-indigo-500/15 px-2 py-1 text-[10px] text-indigo-700 dark:text-indigo-200"
                                                                >
                                                                    {{ badge }}
                                                                </span>
                                                                <span
                                                                    v-if="assignmentPermissionBadges(course.teaching_assignment).length === 0"
                                                                    class="rounded-full bg-slate-200 px-2 py-1 text-[10px] text-slate-600 dark:bg-gray-800 dark:text-gray-300"
                                                                >
                                                                    بدون صلاحيات اختبارات
                                                                </span>
                                                            </div>

                                                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                                                <button type="button" class="rounded-lg bg-indigo-700 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-indigo-600" @click="openTeachingAssignmentForm(course)">
                                                                    ضبط الإسناد
                                                                </button>
                                                                <button
                                                                    v-if="course.teaching_assignment"
                                                                    type="button"
                                                                    class="rounded-lg bg-red-700 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-red-600"
                                                                    @click="clearTeachingAssignment(course)"
                                                                >
                                                                    إلغاء الإسناد
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <div v-if="isCourseAssignmentOpen(course)" class="academic-tree-detail-panel border-t border-slate-200 bg-white/80 p-3 text-xs dark:border-gray-800 dark:bg-gray-950/50">
                                                            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                                                <div class="academic-tree-detail-card rounded-lg border border-slate-200 bg-slate-50 p-2 dark:border-gray-800 dark:bg-gray-900">
                                                                    <p class="font-bold text-slate-700 dark:text-gray-200">المعلم المسند</p>
                                                                    <p class="mt-1 text-slate-600 dark:text-gray-400">{{ course.teacher_name }}</p>
                                                                </div>
                                                                <div class="academic-tree-detail-card rounded-lg border border-slate-200 bg-slate-50 p-2 dark:border-gray-800 dark:bg-gray-900">
                                                                    <p class="font-bold text-slate-700 dark:text-gray-200">الفصول والشعب</p>
                                                                    <p class="mt-1 text-slate-600 dark:text-gray-400">{{ assignmentClassroomsLabel(course) }}</p>
                                                                </div>
                                                            </div>
                                                            <div class="academic-tree-detail-card mt-2 rounded-lg border border-slate-200 bg-slate-50 p-2 dark:border-gray-800 dark:bg-gray-900">
                                                                <p class="font-bold text-slate-700 dark:text-gray-200">صلاحيات الاختبارات</p>
                                                                <div class="mt-2 flex flex-wrap gap-1">
                                                                    <span
                                                                        v-for="badge in assignmentPermissionBadges(course.teaching_assignment)"
                                                                        :key="`assignment-detail-badge-${course.id}-${badge}`"
                                                                        class="rounded bg-indigo-500/15 px-2 py-1 text-[10px] text-indigo-700 dark:text-indigo-200"
                                                                    >
                                                                        {{ badge }}
                                                                    </span>
                                                                    <span
                                                                        v-if="assignmentPermissionBadges(course.teaching_assignment).length === 0"
                                                                        class="rounded bg-slate-200 px-2 py-1 text-[10px] text-slate-600 dark:bg-gray-800 dark:text-gray-300"
                                                                    >
                                                                        لا توجد صلاحيات مفعلة لهذا الإسناد.
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </article>
                                                </div>
                                                <div v-else class="academic-tree-empty rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600 dark:border-gray-700 dark:bg-gray-900/70 dark:text-gray-300">
                                                    لا توجد مقررات قابلة للإسناد داخل هذا الفصل الدراسي.
                                                </div>
                                            </div>
                                        </div>

                                        <div v-if="grade.terms.length === 0" class="academic-tree-empty rounded-xl border border-dashed border-slate-300 bg-white p-4 text-sm text-slate-600 dark:border-gray-700 dark:bg-gray-950/50 dark:text-gray-300">
                                            لا توجد إسنادات داخل هذا الصف.
                                        </div>
                                    </div>
                                </div>

                                <div v-if="stage.grades.length === 0" class="academic-tree-empty rounded-xl border border-dashed border-slate-300 bg-white p-4 text-sm text-slate-600 dark:border-gray-700 dark:bg-gray-900/70 dark:text-gray-300">
                                    لا توجد صفوف داخل هذه المرحلة.
                                </div>
                            </div>
                        </div>

                        <div v-if="courseAssignmentsTreeForDisplay.length === 0" class="academic-tree-empty rounded-2xl border border-dashed border-gray-700 bg-gray-800/70 p-5 text-sm text-gray-300">
                            {{ hasCourseAssignmentFilters ? 'لا توجد نتائج مطابقة لفلاتر الإسناد الحالية.' : 'لا توجد مقررات نشطة ومفعلة للاختبارات بعد. أضف المقررات أو فعّل خيار استخدامها في الاختبارات أولًا.' }}
                        </div>
                    </div>
                </div>

                <div
                    v-if="isTeachingAssignmentModalOpen && selectedOfferingForTeachingAssignment"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                    @click.self="resetTeachingAssignmentForm"
                >
                    <div class="w-full max-w-3xl overflow-hidden rounded-2xl border border-indigo-400/40 bg-gray-900 shadow-2xl">
                        <div class="flex items-center justify-between gap-2 border-b border-gray-700 bg-gray-800/90 p-4">
                            <div>
                                <h3 class="flex items-center gap-2 text-base font-bold text-indigo-100">
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-indigo-500/20 text-indigo-100">
                                        <svg viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="9" cy="8" r="3" />
                                            <path d="M4.5 18a4.5 4.5 0 0 1 9 0" />
                                            <path d="M15.5 12.5l1.8 1.8 3.2-3.2" />
                                        </svg>
                                    </span>
                                    <span>ضبط إسناد المقرر</span>
                                </h3>
                                <p class="text-xs text-indigo-100/70">
                                    {{ courseOfferingLabel(selectedOfferingForTeachingAssignment) }}
                                </p>
                            </div>
                            <button type="button" class="rounded bg-gray-700 px-3 py-1.5 text-xs hover:bg-gray-600" @click="resetTeachingAssignmentForm">
                                إغلاق
                            </button>
                        </div>

                        <form class="max-h-[75vh] space-y-3 overflow-y-auto p-4" @submit.prevent="submitTeachingAssignment">
                            <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                                <div>
                                    <label class="mb-1 block text-xs text-gray-300">المادة</label>
                                    <input
                                        :value="selectedSubjectForTeachingAssignment?.name || selectedOfferingForTeachingAssignment?.subject?.name || '-'"
                                        disabled
                                        class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm text-gray-200"
                                    />
                                </div>
                                <div class="md:col-span-2">
                                    <label class="mb-1 block text-xs text-gray-300">الفروع المرتبطة بالمادة</label>
                                    <div class="flex min-h-[42px] flex-wrap items-center gap-1 rounded border border-gray-700 bg-gray-800 p-2">
                                        <span
                                            v-for="branch in subjectBranchesForTeachingAssignment"
                                            :key="`assignment-subject-branch-${branch}`"
                                            class="rounded bg-indigo-500/20 px-2 py-1 text-[11px] text-indigo-200"
                                        >
                                            {{ branch }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs text-gray-300">المعلم</label>
                                <select v-model="teachingAssignmentForm.teacher_user_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                    <option value="">بدون إسناد</option>
                                    <option v-for="teacher in teachersForOfferingAssignment" :key="`offering-teacher-${teacher.id}`" :value="teacher.id">
                                        {{ teacher.name }}
                                    </option>
                                </select>
                                <p v-if="teachingAssignmentForm.errors.teacher_user_id" class="mt-1 text-xs text-red-400">{{ teachingAssignmentForm.errors.teacher_user_id }}</p>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs text-gray-300">الفصول/الشعب المطبّقة</label>
                                <div class="rounded border border-gray-700 bg-gray-900 p-2">
                                    <div v-if="classroomsForOfferingAssignment.length === 0" class="text-xs text-amber-300">
                                        لا توجد فصول متاحة ضمن نفس المرحلة والصف لهذا المقرر.
                                    </div>
                                    <label
                                        v-for="classroom in classroomsForOfferingAssignment"
                                        :key="`assignment-classroom-${classroom.id}`"
                                        class="mb-1 flex items-center gap-2 text-xs text-gray-200"
                                    >
                                        <input
                                            v-model="teachingAssignmentForm.school_classroom_ids"
                                            :value="classroom.id"
                                            type="checkbox"
                                            class="rounded border-gray-600 bg-gray-800 text-indigo-500"
                                        />
                                        <span>{{ classroom.grade_name }} / {{ classroom.name }}</span>
                                    </label>
                                </div>
                                <p v-if="teachingAssignmentForm.errors.school_classroom_ids" class="mt-1 text-xs text-red-400">
                                    {{ teachingAssignmentForm.errors.school_classroom_ids }}
                                </p>
                                <p v-if="teachingAssignmentForm.errors['school_classroom_ids.0']" class="mt-1 text-xs text-red-400">
                                    {{ teachingAssignmentForm.errors['school_classroom_ids.0'] }}
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                <label class="flex items-center gap-2 rounded border border-gray-700 bg-gray-900 p-2 text-xs">
                                    <input v-model="teachingAssignmentForm.can_create_exam" type="checkbox" class="rounded border-gray-600 bg-gray-800 text-indigo-500" />
                                    <span>إنشاء اختبار</span>
                                </label>
                                <label class="flex items-center gap-2 rounded border border-gray-700 bg-gray-900 p-2 text-xs">
                                    <input v-model="teachingAssignmentForm.can_update_exam" type="checkbox" class="rounded border-gray-600 bg-gray-800 text-indigo-500" />
                                    <span>تعديل اختبار</span>
                                </label>
                                <label class="flex items-center gap-2 rounded border border-gray-700 bg-gray-900 p-2 text-xs">
                                    <input v-model="teachingAssignmentForm.can_delete_exam" type="checkbox" class="rounded border-gray-600 bg-gray-800 text-indigo-500" />
                                    <span>حذف اختبار</span>
                                </label>
                                <label class="flex items-center gap-2 rounded border border-gray-700 bg-gray-900 p-2 text-xs">
                                    <input v-model="teachingAssignmentForm.can_approve_exam" type="checkbox" class="rounded border-gray-600 bg-gray-800 text-indigo-500" />
                                    <span>اعتماد اختبار</span>
                                </label>
                                <label class="flex items-center gap-2 rounded border border-gray-700 bg-gray-900 p-2 text-xs">
                                    <input v-model="teachingAssignmentForm.can_enter_exam_scores" type="checkbox" class="rounded border-gray-600 bg-gray-800 text-indigo-500" />
                                    <span>إدخال درجات</span>
                                </label>
                                <label class="flex items-center gap-2 rounded border border-gray-700 bg-gray-900 p-2 text-xs">
                                    <input v-model="teachingAssignmentForm.can_edit_exam_scores" type="checkbox" class="rounded border-gray-600 bg-gray-800 text-indigo-500" />
                                    <span>تعديل درجات</span>
                                </label>
                                <label class="flex items-center gap-2 rounded border border-gray-700 bg-gray-900 p-2 text-xs md:col-span-2">
                                    <input v-model="teachingAssignmentForm.can_use_question_bank" type="checkbox" class="rounded border-gray-600 bg-gray-800 text-indigo-500" />
                                    <span>استخدام بنك الأسئلة</span>
                                </label>
                            </div>

                            <AttachmentPanel
                                title="مرفقات تحضير المعلم"
                                helper-text="يمكنك رفع ملف تحضير أو خطة درس أو مستند PDF مرتبط بإسناد هذا المقرر. المرفقات تبقى محفوظة مع نفس إسناد المعلم داخل المدرسة الحالية."
                                :existing-attachments="selectedTeachingAssignmentAttachments"
                                :pending-files="pendingTeachingAssignmentAttachments"
                                :errors="teachingAssignmentAttachmentErrors"
                                accept="application/pdf,image/*,.doc,.docx,.xls,.xlsx"
                                :busy="teachingAssignmentForm.processing"
                                :show-uploader="Boolean(teachingAssignmentForm.teacher_user_id)"
                                pending-title="مرفقات سيتم حفظها مع إسناد المعلم"
                                existing-title="المرفقات الحالية لتحضير المعلم"
                                empty-text="لا توجد مرفقات محفوظة لهذا الإسناد بعد."
                                @select-files="appendTeachingAssignmentAttachmentFiles"
                                @remove-pending="removePendingTeachingAssignmentAttachment"
                                @delete-existing="deleteTeachingAssignmentAttachment"
                            />

                            <div class="flex justify-end gap-2 border-t border-gray-700 pt-3">
                                <button type="button" class="rounded bg-gray-700 px-3 py-2 text-sm hover:bg-gray-600" @click="resetTeachingAssignmentForm">
                                    إلغاء
                                </button>
                                <button class="rounded bg-indigo-700 px-3 py-2 text-sm font-bold hover:bg-indigo-600" :disabled="teachingAssignmentForm.processing">
                                    حفظ الإسناد والصلاحيات
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            <section v-if="canManagePlanning && isSectionVisible('schedules')" ref="scheduleSectionRef" style="order: 8;" class="rounded-xl border border-blue-500/70 bg-blue-900/30 p-4">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="inline-flex items-center gap-2 text-lg font-bold">
                            <CalendarDays class="h-5 w-5 text-blue-200" />
                            <span>8) الجداول الدراسية</span>
                        </h2>
                        <p class="mt-1 text-xs text-blue-100/80">حرر الجدول الأسبوعي على شكل شبكة واضحة، مع بقاء الإدخال الفردي متاحًا للحالات الخاصة.</p>
                    </div>
                    <button class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold hover:bg-emerald-500" @click="openCreateScheduleModal">
                        <PlusCircle class="h-4 w-4" />
                        <span>{{ filterForm.scope === 'WEEKLY' ? 'إضافة حصة يدويًا' : 'إضافة جدول دراسي' }}</span>
                    </button>
                </div>

                

                <section ref="versionSectionRef" class="mb-5 rounded-xl border border-emerald-500/70 bg-emerald-900/30 p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="inline-flex items-center gap-2 text-lg font-bold">
                        <LayoutTemplate class="h-4 w-4 text-emerald-300" />
                        <span>إدارة نسخ الجدول</span>
                    </h2>
                    <button class="inline-flex items-center gap-1 rounded bg-gray-700 px-3 py-1 text-xs hover:bg-gray-600" @click="resetTimetableVersionForm">
                        <PlusCircle class="h-3.5 w-3.5" />
                        <span>جديد</span>
                    </button>
                </div>

                <form class="mb-4 rounded border border-gray-700 bg-gray-800 p-3" @submit.prevent="submitTimetableVersion">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-400">
                                <CalendarDays class="h-3.5 w-3.5 text-emerald-300" />
                                <span>الترم</span>
                            </label>
                            <select v-model="timetableVersionForm.school_term_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="" disabled>اختر الترم</option>
                                <option v-for="term in terms" :key="`version-term-${term.id}`" :value="term.id">{{ term.name }}</option>
                            </select>
                            <p v-if="timetableVersionForm.errors.school_term_id" class="mt-1 text-xs text-red-400">
                                {{ timetableVersionForm.errors.school_term_id }}
                            </p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-400">
                                <LayoutTemplate class="h-3.5 w-3.5 text-emerald-300" />
                                <span>اسم النسخة</span>
                            </label>
                            <input ref="versionNameInputRef" v-model="timetableVersionForm.name" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="timetableVersionForm.errors.name" class="mt-1 text-xs text-red-400">{{ timetableVersionForm.errors.name }}</p>
                        </div>
                        <div class="flex items-end gap-2">
                            <button class="inline-flex items-center gap-2 rounded bg-emerald-600 px-3 py-2 text-sm font-bold hover:bg-emerald-500" :disabled="timetableVersionForm.processing">
                                <Save class="h-4 w-4" />
                                <span>{{ timetableVersionEditId ? 'تحديث النسخة' : 'إضافة النسخة' }}</span>
                            </button>
                            <button
                                v-if="timetableVersionEditId"
                                type="button"
                                class="rounded bg-gray-700 px-3 py-2 text-sm hover:bg-gray-600"
                                @click="resetTimetableVersionForm"
                            >
                                إلغاء
                            </button>
                        </div>
                    </div>
                    <div class="mt-3">
                        <AttachmentPanel
                            title="مرفقات نسخة الجدول"
                            helper-text="يمكنك رفع نسخة PDF من الجدول أو مستند الاعتماد أو أي ملف تنظيمي مرتبط بهذه النسخة. الملفات الجديدة تُحفظ مع النسخة الحالية فقط."
                            :existing-attachments="editingTimetableVersion?.attachments || []"
                            :pending-files="pendingTimetableVersionAttachments"
                            :errors="timetableAttachmentErrors"
                            accept="application/pdf,image/*,.doc,.docx,.xls,.xlsx"
                            :busy="timetableVersionForm.processing"
                            pending-title="مرفقات سيتم حفظها مع النسخة"
                            existing-title="المرفقات المحفوظة للنسخة الحالية"
                            empty-text="لا توجد مرفقات محفوظة لهذه النسخة بعد."
                            @select-files="appendTimetableAttachmentFiles"
                            @remove-pending="removePendingTimetableAttachment"
                            @delete-existing="deleteTimetableAttachment"
                        />
                    </div>
                </form>

                <div class="space-y-2">
                    <div v-for="version in timetableVersions" :key="version.id" class="rounded border border-gray-700 bg-gray-800 p-3">
                        <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold">{{ version.name }}</p>
                            <p class="text-xs text-gray-500" v-if="version.published_at">
                                منشورة بتاريخ {{ String(version.published_at).slice(0, 16).replace('T', ' ') }}
                            </p>
                            <p class="mt-1 text-[11px] text-gray-400">
                                عدد المرفقات: {{ Number(version.attachments_count || 0) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="rounded px-2 py-1 text-[10px]" :class="version.is_published ? 'bg-emerald-500/20 text-emerald-200' : 'bg-gray-700 text-gray-300'">
                                {{ version.is_published ? 'منشورة' : 'مسودة' }}
                            </span>
                            <button
                                v-if="!version.is_published"
                                type="button"
                                class="rounded bg-indigo-700 px-2 py-1 text-xs hover:bg-indigo-600"
                                @click="publishTimetableVersion(version.id)"
                            >
                                نشر
                            </button>
                            <button type="button" class="rounded bg-blue-700 px-2 py-1 text-xs hover:bg-blue-600" @click="editTimetableVersion(version)">
                                تعديل
                            </button>
                        </div>
                        </div>
                        <div v-if="(version.attachments || []).length > 0" class="mt-3 space-y-2">
                            <div
                                v-for="attachment in version.attachments"
                                :key="`timetable-attachment-${attachment.id}`"
                                class="flex items-center justify-between gap-3 rounded border border-gray-700 bg-gray-900 px-3 py-2"
                            >
                                <div class="min-w-0">
                                    <a :href="attachment.download_url" class="truncate text-sm font-semibold text-sky-300 hover:text-sky-200">
                                        {{ attachment.file_name }}
                                    </a>
                                    <p class="mt-1 text-xs text-gray-400">
                                        {{ attachment.mime_type || 'مرفق' }}
                                        <span v-if="attachment.uploaded_by"> • بواسطة {{ attachment.uploaded_by }}</span>
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a :href="attachment.download_url" class="rounded bg-sky-700 px-2 py-1 text-xs font-bold text-white hover:bg-sky-600">تحميل</a>
                                    <button
                                        type="button"
                                        class="rounded bg-red-700 px-2 py-1 text-xs font-bold text-red-100 hover:bg-red-600"
                                        @click="deleteTimetableAttachment(attachment)"
                                    >
                                        حذف
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-if="timetableVersions.length === 0" class="text-sm text-gray-500">لا توجد نسخ جدول مضافة بعد.</p>
                </div>
            </section>

                <div
                    v-if="isScheduleModalOpen"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                    @click.self="closeScheduleModal"
                >
                    <div class="w-full max-w-6xl overflow-hidden rounded-2xl border border-blue-400/40 bg-gray-900 shadow-2xl">
                        <div class="flex items-center justify-between gap-2 border-b border-gray-700 bg-gray-800/90 p-4">
                            <div>
                                <h3 class="inline-flex items-center gap-2 text-base font-bold text-blue-100">
                                    <CalendarDays class="h-4 w-4 text-blue-200" />
                                    <span>{{ scheduleEditId ? 'تعديل الحصة الدراسية' : 'إضافة جدول دراسي' }}</span>
                                </h3>
                                <p class="text-xs text-blue-100/70">أدخل بيانات الحصة الدراسية من نفس النافذة ثم احفظ.</p>
                            </div>
                            <button type="button" class="inline-flex items-center gap-1 rounded bg-gray-700 px-3 py-1.5 text-xs hover:bg-gray-600" @click="closeScheduleModal">
                                <X class="h-3.5 w-3.5" />
                                <span>إغلاق</span>
                            </button>
                        </div>
                        <form class="max-h-[72vh] overflow-y-auto p-4" @submit.prevent="submitSchedule">
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <CalendarDays class="h-3.5 w-3.5 text-blue-300" />
                                <span>الترم</span>
                            </label>
                            <select ref="scheduleTermSelectRef" v-model="scheduleForm.school_term_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="" disabled>اختر الترم</option>
                                <option v-for="term in terms" :key="term.id" :value="term.id">{{ term.name }}</option>
                            </select>
                            <p v-if="scheduleForm.errors.school_term_id" class="mt-1 text-xs text-red-400">{{ scheduleForm.errors.school_term_id }}</p>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <LayoutTemplate class="h-3.5 w-3.5 text-blue-300" />
                                <span>نسخة الجدول (اختياري)</span>
                            </label>
                            <select v-model="scheduleForm.school_timetable_version_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="">بدون نسخة</option>
                                <option v-for="version in timetableVersions" :key="`schedule-version-${version.id}`" :value="version.id">
                                    {{ version.name }}{{ version.is_published ? ' (منشورة)' : '' }}
                                </option>
                            </select>
                            <p v-if="scheduleForm.errors.school_timetable_version_id" class="mt-1 text-xs text-red-400">
                                {{ scheduleForm.errors.school_timetable_version_id }}
                            </p>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <School class="h-3.5 w-3.5 text-blue-300" />
                                <span>المرحلة</span>
                            </label>
                            <select v-model="scheduleForm.school_stage_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="" disabled>اختر المرحلة</option>
                                <option v-for="stage in stageOptions" :key="stage.id" :value="stage.id">{{ stage.name }}</option>
                            </select>
                            <p v-if="scheduleForm.errors.school_stage_id" class="mt-1 text-xs text-red-400">{{ scheduleForm.errors.school_stage_id }}</p>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <School class="h-3.5 w-3.5 text-blue-300" />
                                <span>الصف (اختياري)</span>
                            </label>
                            <select v-model="scheduleGradeName" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="">كل الصفوف داخل المرحلة</option>
                                <option v-for="gradeName in scheduleGradeOptions" :key="`schedule-grade-${gradeName}`" :value="gradeName">
                                    {{ gradeName }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <School class="h-3.5 w-3.5 text-blue-300" />
                                <span>الفصل</span>
                            </label>
                            <select v-model="scheduleForm.school_classroom_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="" disabled>اختر الفصل</option>
                                <option v-for="classroom in classroomsForSelectedStageAndGrade" :key="classroom.id" :value="classroom.id">
                                    {{ classroom.grade_name }} - {{ classroom.name }}
                                </option>
                            </select>
                            <p v-if="scheduleForm.errors.school_classroom_id" class="mt-1 text-xs text-red-400">{{ scheduleForm.errors.school_classroom_id }}</p>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <BookOpenText class="h-3.5 w-3.5 text-blue-300" />
                                <span>المادة</span>
                            </label>
                            <select v-model="scheduleForm.school_subject_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="" disabled>اختر المادة</option>
                                <option v-for="subject in activeSubjects" :key="subject.id" :value="subject.id">
                                    {{ subject.name }}
                                </option>
                            </select>
                            <p v-if="scheduleForm.errors.school_subject_id" class="mt-1 text-xs text-red-400">{{ scheduleForm.errors.school_subject_id }}</p>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <UserRound class="h-3.5 w-3.5 text-blue-300" />
                                <span>المعلم</span>
                            </label>
                            <select v-model="scheduleForm.teacher_user_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="" disabled>اختر المعلم</option>
                                <option v-for="teacher in teachersForSelectedSubject" :key="teacher.id" :value="teacher.id">
                                    {{ teacher.name }}
                                </option>
                            </select>
                            <p v-if="scheduleForm.errors.teacher_user_id" class="mt-1 text-xs text-red-400">{{ scheduleForm.errors.teacher_user_id }}</p>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <Settings2 class="h-3.5 w-3.5 text-blue-300" />
                                <span>النطاق</span>
                            </label>
                            <select v-model="scheduleForm.schedule_scope" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option v-for="scopeItem in scopeOptions" :key="scopeItem.value" :value="scopeItem.value">
                                    {{ scopeItem.label }}
                                </option>
                            </select>
                            <p v-if="scheduleForm.errors.schedule_scope" class="mt-1 text-xs text-red-400">{{ scheduleForm.errors.schedule_scope }}</p>
                        </div>
                        <div v-if="isWeeklyScope">
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <CalendarDays class="h-3.5 w-3.5 text-blue-300" />
                                <span>اليوم الأسبوعي</span>
                            </label>
                            <select v-model="scheduleForm.day_of_week" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option v-for="day in weekDays" :key="day.value" :value="day.value">
                                    {{ day.label }}
                                </option>
                            </select>
                            <p v-if="scheduleForm.errors.day_of_week" class="mt-1 text-xs text-red-400">{{ scheduleForm.errors.day_of_week }}</p>
                        </div>
                        <div v-if="isMonthlyScope">
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <CalendarDays class="h-3.5 w-3.5 text-blue-300" />
                                <span>يوم الشهر</span>
                            </label>
                            <input v-model.number="scheduleForm.day_of_month" type="number" min="1" max="31" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="scheduleForm.errors.day_of_month" class="mt-1 text-xs text-red-400">{{ scheduleForm.errors.day_of_month }}</p>
                        </div>
                        <div v-if="isTermScope">
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <CalendarDays class="h-3.5 w-3.5 text-blue-300" />
                                <span>تاريخ الجلسة</span>
                            </label>
                            <input v-model="scheduleForm.session_date" type="date" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="scheduleForm.errors.session_date" class="mt-1 text-xs text-red-400">{{ scheduleForm.errors.session_date }}</p>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <FileSpreadsheet class="h-3.5 w-3.5 text-blue-300" />
                                <span>رقم الحصة</span>
                            </label>
                            <input v-model.number="scheduleForm.session_index" type="number" min="1" max="20" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="scheduleForm.errors.session_index" class="mt-1 text-xs text-red-400">{{ scheduleForm.errors.session_index }}</p>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <Clock3 class="h-3.5 w-3.5 text-blue-300" />
                                <span>من الساعة</span>
                            </label>
                            <input v-model="scheduleForm.starts_at" type="time" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="scheduleForm.errors.starts_at" class="mt-1 text-xs text-red-400">{{ scheduleForm.errors.starts_at }}</p>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <Clock3 class="h-3.5 w-3.5 text-blue-300" />
                                <span>إلى الساعة</span>
                            </label>
                            <input v-model="scheduleForm.ends_at" type="time" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="scheduleForm.errors.ends_at" class="mt-1 text-xs text-red-400">{{ scheduleForm.errors.ends_at }}</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <FileSpreadsheet class="h-3.5 w-3.5 text-blue-300" />
                                <span>ملاحظات</span>
                            </label>
                            <input v-model="scheduleForm.notes" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="scheduleForm.errors.notes" class="mt-1 text-xs text-red-400">{{ scheduleForm.errors.notes }}</p>
                        </div>
                        <div class="flex items-end">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input v-model="scheduleForm.is_active" type="checkbox" class="rounded border-gray-600 bg-gray-900 text-blue-500" />
                                <span>نشط</span>
                            </label>
                        </div>
                    </div>

                    <div class="mt-3 flex gap-2">
                        <button class="inline-flex items-center gap-2 rounded bg-emerald-600 px-3 py-2 text-sm font-bold hover:bg-emerald-500" :disabled="scheduleForm.processing">
                            <Save class="h-4 w-4" />
                            <span>{{ scheduleEditId ? 'تحديث الحصة' : 'إضافة الحصة' }}</span>
                        </button>
                        <button type="button" class="inline-flex items-center gap-1 rounded bg-gray-700 px-3 py-2 text-sm hover:bg-gray-600" @click="closeScheduleModal">
                            <X class="h-3.5 w-3.5" />
                            <span>إلغاء</span>
                        </button>
                    </div>
                </form>
                    </div>
                </div>

                <section class="mb-5 rounded-xl border border-blue-500/70 bg-blue-900/30 p-4">
                    <h2 class="mb-3 inline-flex items-center gap-2 text-lg font-bold">
                        <Settings2 class="h-4 w-4 text-blue-200" />
                        <span>فلتر الجدول الدراسي</span>
                    </h2>
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-7">
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <CalendarDays class="h-3.5 w-3.5 text-blue-300" />
                                <span>الترم</span>
                            </label>
                            <select v-model="filterForm.term_id" class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm">
                                <option value="" disabled>اختر الترم</option>
                                <option v-for="term in terms" :key="term.id" :value="term.id">
                                    {{ term.name }} ({{ normalizeDateInput(term.start_date) }} - {{ normalizeDateInput(term.end_date) }})
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <Settings2 class="h-3.5 w-3.5 text-blue-300" />
                                <span>النطاق</span>
                            </label>
                            <select v-model="filterForm.scope" class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm">
                                <option v-for="scopeItem in scopeOptions" :key="scopeItem.value" :value="scopeItem.value">
                                    {{ scopeItem.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <School class="h-3.5 w-3.5 text-blue-300" />
                                <span>المرحلة</span>
                            </label>
                            <select v-model="filterForm.stage_id" class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm">
                                <option value="">كل المراحل</option>
                                <option v-for="stage in stageOptions" :key="`schedule-stage-filter-${stage.id}`" :value="stage.id">
                                    {{ stage.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <School class="h-3.5 w-3.5 text-blue-300" />
                                <span>الصف</span>
                            </label>
                            <select v-model="filterForm.grade_name" class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm">
                                <option value="">كل الصفوف</option>
                                <option v-for="gradeName in scheduleGradeFilterOptions" :key="`schedule-grade-filter-${gradeName}`" :value="gradeName">
                                    {{ gradeName }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <School class="h-3.5 w-3.5 text-blue-300" />
                                <span>الفصل / الشعبة</span>
                            </label>
                            <select v-model="filterForm.classroom_id" class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm">
                                <option value="">كل الفصول</option>
                                <option v-for="classroom in classroomOptionsForFilters" :key="classroom.id" :value="classroom.id">
                                    {{ classroom.stage_name }} / {{ classroom.grade_name }} / {{ classroom.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-300">
                                <LayoutTemplate class="h-3.5 w-3.5 text-blue-300" />
                                <span>نسخة الجدول (اختياري)</span>
                            </label>
                            <select v-model="filterForm.version_id" class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm">
                                <option value="">كل النسخ</option>
                                <option v-for="version in timetableVersions" :key="`version-filter-${version.id}`" :value="version.id">
                                    {{ version.name }}{{ version.is_published ? ' (منشورة)' : '' }}
                                </option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button class="inline-flex w-full items-center justify-center gap-2 rounded bg-blue-700 px-3 py-2 text-sm font-bold hover:bg-blue-600" @click="applyFilters">
                                <Settings2 class="h-4 w-4" />
                                <span>تحديث العرض</span>
                            </button>
                        </div>
                    </div>
                </section>

                <section v-if="filterForm.scope === 'WEEKLY'" class="weekly-grid-section mb-5 rounded-xl border border-cyan-500/60 bg-slate-900/70 p-4">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="weekly-grid-section-title inline-flex items-center gap-2 text-lg font-bold text-cyan-100">
                                <CalendarDays class="h-4 w-4 text-cyan-300" />
                                <span>محرر الجدول الأسبوعي</span>
                            </h3>
                            <p class="weekly-grid-section-copy mt-1 text-xs text-cyan-100/75">
                                اختر المادة أولًا داخل كل خلية، ثم اختر المعلم المناسب لها. الحفظ يتم على نفس سجلات الجدول الحالية داخل المدرسة.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="weeklyGridForm.processing || !isWeeklyGridContextReady || !areWeeklyGridFiltersApplied"
                                @click="submitWeeklyGrid"
                            >
                                <Save class="h-4 w-4" />
                                <span>{{ weeklyGridForm.processing ? 'جاري الحفظ...' : 'حفظ الجدول' }}</span>
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg bg-rose-700 px-3 py-2 text-xs font-semibold hover:bg-rose-600 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="!isWeeklyGridContextReady || !areWeeklyGridFiltersApplied"
                                @click="exportWeeklyGrid('pdf')"
                            >
                                <FileSpreadsheet class="h-4 w-4" />
                                <span>تصدير PDF</span>
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg bg-sky-700 px-3 py-2 text-xs font-semibold hover:bg-sky-600 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="!isWeeklyGridContextReady || !areWeeklyGridFiltersApplied"
                                @click="exportWeeklyGrid('word')"
                            >
                                <FileSpreadsheet class="h-4 w-4" />
                                <span>تصدير Word</span>
                            </button>
                        </div>
                    </div>

                    <div v-if="!isWeeklyGridContextReady" class="rounded-xl border border-amber-400/40 bg-amber-500/10 p-4 text-sm text-amber-100">
                        اختر الترم والمرحلة والفصل أولًا لعرض محرر الجدول الأسبوعي.
                    </div>

                    <div v-else-if="!areWeeklyGridFiltersApplied" class="rounded-xl border border-amber-400/40 bg-amber-500/10 p-4 text-sm text-amber-100">
                        تم تغيير الفلاتر الحالية. اضغط "تحديث العرض" أولًا حتى يتم تحميل الجدول المطابق للسياق المختار قبل الحفظ أو التصدير.
                    </div>

                    <div v-else class="weekly-grid-workspace space-y-3">
                        <div class="weekly-grid-info-card flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-700 bg-slate-800/80 p-3">
                            <div class="weekly-grid-info-copy space-y-1 text-xs text-slate-200">
                                <p class="weekly-grid-weekoff-line">
                                    <span class="weekly-grid-weekoff-label font-semibold text-cyan-200">العطلة الأسبوعية:</span>
                                    {{ weeklyOffDayLabels.length > 0 ? weeklyOffDayLabels.join('، ') : 'لا توجد عطلة أسبوعية مسجلة.' }}
                                </p>
                                <p class="weekly-grid-info-subcopy text-slate-300">
                                    الجدول هنا يمثل النمط الأسبوعي الثابت، وتُحترم الإجازات الرسمية والتقويم الدراسي الحالي لاحقًا عند القراءة الزمنية وتطبيق الجلسات.
                                </p>
                            </div>
                            <div class="flex items-center gap-2 text-sm">
                                <label class="weekly-grid-period-label text-xs text-slate-300">عدد الحصص</label>
                                <input
                                    v-model.number="scheduleGridPeriodCount"
                                    type="number"
                                    min="1"
                                    max="20"
                                    class="weekly-grid-period-input w-24 rounded border border-slate-600 bg-slate-900 px-3 py-2 text-center text-sm text-white"
                                />
                            </div>
                        </div>

                        <div
                            v-if="props.scheduleRules?.enforce_course_offerings && weeklyGridSubjects.length === 0"
                            class="rounded-xl border border-amber-400/40 bg-amber-500/10 p-4 text-sm text-amber-100"
                        >
                            لا توجد مقررات تعليمية نشطة ومسنّدة لهذا الفصل داخل الترم والمرحلة المحددين. أضف المقرر أو إسناد المعلم أولًا ثم عد إلى الجدول.
                        </div>

                        <div class="weekly-grid-frame rounded-2xl border border-slate-700 bg-slate-950/60 p-2 sm:p-3">
                            <div
                                class="weekly-grid-board"
                                :class="`weekly-grid-board--${weeklyGridDensityMode}`"
                                :style="weeklyGridBoardStyle"
                            >
                                <div class="weekly-grid-corner">اليوم</div>
                                <div
                                    v-for="period in weeklyGridPeriods"
                                    :key="`weekly-grid-period-${period}`"
                                    class="weekly-grid-header-cell"
                                >
                                    الحصة {{ period }}
                                </div>

                                <template
                                    v-for="day in weeklyGridRows"
                                    :key="`weekly-grid-day-${day.value}`"
                                >
                                    <div class="weekly-grid-day-cell">
                                        {{ day.label }}
                                    </div>

                                    <div
                                        v-for="cell in day.cells"
                                        :key="`weekly-grid-cell-${day.value}-${cell.session_index}`"
                                        class="weekly-grid-slot"
                                    >
                                        <div class="weekly-grid-slot-card">
                                            <select
                                                :value="cell.school_subject_id"
                                                class="weekly-grid-select"
                                                :class="{ 'weekly-grid-select--empty': !cell.school_subject_id }"
                                                :aria-label="`المادة ليوم ${day.label} الحصة ${cell.session_index}`"
                                                @change="onWeeklyGridSubjectChange(day.value, cell.session_index, $event.target.value)"
                                            >
                                                <option value="">اختر المادة</option>
                                                <option
                                                    v-for="subject in subjectsForWeeklyGridCell(cell)"
                                                    :key="`weekly-grid-subject-${day.value}-${cell.session_index}-${subject.id}`"
                                                    :value="subject.id"
                                                >
                                                    {{ subject.name }}
                                                </option>
                                            </select>

                                            <select
                                                :value="cell.teacher_user_id"
                                                :class="['weekly-grid-select weekly-grid-select--teacher', { 'weekly-grid-select--empty': !cell.teacher_user_id }]"
                                                :aria-label="`المعلم ليوم ${day.label} الحصة ${cell.session_index}`"
                                                :disabled="!cell.school_subject_id"
                                                @change="onWeeklyGridTeacherChange(day.value, cell.session_index, $event.target.value)"
                                            >
                                                <option value="">{{ cell.school_subject_id ? 'اختر المعلم' : 'اختر المادة أولًا' }}</option>
                                                <option
                                                    v-for="teacher in teachersForWeeklyGridCell(cell, day.value, cell.session_index)"
                                                    :key="`weekly-grid-teacher-${day.value}-${cell.session_index}-${teacher.id}`"
                                                    :value="teacher.id"
                                                >
                                                    {{ teacher.name }}
                                                </option>
                                            </select>

                                            <div class="weekly-grid-slot-footer" :class="{ 'weekly-grid-slot-footer--empty': !weeklyGridCellHasMetadata(cell) }">
                                                <span
                                                    v-if="weeklyGridCellHasMetadata(cell)"
                                                    class="weekly-grid-slot-meta"
                                                    :title="weeklyGridCellMetadataLabel(cell)"
                                                >
                                                    {{ weeklyGridCellMetadataLabel(cell) }}
                                                </span>
                                                <button
                                                    type="button"
                                                    class="weekly-grid-clear-button"
                                                    :class="{ 'weekly-grid-clear-button--subtle': !weeklyGridCellHasContent(cell) }"
                                                    @click="clearWeeklyGridCell(day.value, cell.session_index)"
                                                >
                                                    تفريغ
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div
                            v-if="Object.keys(weeklyGridForm.errors || {}).length > 0"
                            class="rounded-xl border border-red-400/40 bg-red-500/10 p-4 text-sm text-red-100"
                        >
                            <p class="font-semibold">تعذر حفظ الجدول الحالي.</p>
                            <p class="mt-1 text-xs text-red-100/80">
                                {{ Object.values(weeklyGridForm.errors || {})[0] }}
                            </p>
                        </div>
                    </div>
                </section>

                <p v-if="filterForm.scope !== 'WEEKLY'" class="mb-2 text-xs text-blue-100/80 lg:hidden">تم تحويل الجدول إلى بطاقات على الجوال لتسهيل قراءة الحصص وإجراءاتها.</p>
                <div v-if="filterForm.scope !== 'WEEKLY'" class="space-y-3 lg:hidden">
                    <article
                        v-for="entry in schedules"
                        :key="`schedule-mobile-${entry.id}`"
                        class="rounded-2xl border border-gray-700 bg-gray-900 p-4 text-right"
                        :style="stageAccent(entry.school_stage_id, entry.stage?.name || '')"
                    >
                        <div class="mb-3">
                            <span class="stage-badge" :style="stageAccent(entry.school_stage_id, entry.stage?.name || '')">{{ entry.stage?.name || '-' }}</span>
                            <p class="mt-2 font-semibold">{{ entry.classroom?.name || '-' }}</p>
                            <p class="text-sm text-gray-300">{{ entry.subject?.name || '-' }}</p>
                            <p class="text-xs text-gray-500">{{ entry.teacher?.name || '-' }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div><p class="text-xs text-gray-500">النطاق</p><span class="rounded px-2 py-1 text-[10px]" :class="scopeClass(entry.schedule_scope)">{{ scopeLabel(entry.schedule_scope) }}</span></div>
                            <div><p class="text-xs text-gray-500">الموعد</p><p>{{ scheduleSlotLabel(entry) }}</p></div>
                            <div><p class="text-xs text-gray-500">الوقت</p><p>{{ scheduleTimeLabel(entry) }}</p></div>
                            <div><p class="text-xs text-gray-500">الحالة</p><span class="rounded px-2 py-1 text-[10px]" :class="entry.is_active ? 'bg-emerald-500/20 text-emerald-200' : 'bg-gray-700 text-gray-300'">{{ entry.is_active ? 'نشط' : 'غير نشط' }}</span></div>
                        </div>

                        <div class="mt-4 flex flex-wrap justify-end gap-2">
                            <button class="inline-flex items-center gap-1 rounded bg-blue-700 px-3 py-2 text-xs hover:bg-blue-600" @click="openEditScheduleModal(entry)"><Pencil class="h-3.5 w-3.5" /><span>تعديل</span></button>
                            <button class="inline-flex items-center gap-1 rounded bg-red-700 px-3 py-2 text-xs hover:bg-red-600" @click="removeSchedule(entry.id)"><Trash2 class="h-3.5 w-3.5" /><span>حذف</span></button>
                        </div>
                    </article>
                </div>

                <div v-if="filterForm.scope !== 'WEEKLY'" class="hidden overflow-x-auto rounded border border-gray-700 lg:block">
                    <table class="min-w-[980px] w-full text-right text-sm text-gray-200">
                        <thead class="bg-gray-800 text-xs text-gray-400">
                            <tr>
                                <th class="px-3 py-2"><span class="inline-flex items-center gap-1"><School class="h-3.5 w-3.5" />المرحلة / الفصل</span></th>
                                <th class="px-3 py-2"><span class="inline-flex items-center gap-1"><BookOpenText class="h-3.5 w-3.5" />المادة / المعلم</span></th>
                                <th class="px-3 py-2"><span class="inline-flex items-center gap-1"><Settings2 class="h-3.5 w-3.5" />النطاق</span></th>
                                <th class="px-3 py-2"><span class="inline-flex items-center gap-1"><CalendarDays class="h-3.5 w-3.5" />الموعد</span></th>
                                <th class="px-3 py-2"><span class="inline-flex items-center gap-1"><Clock3 class="h-3.5 w-3.5" />الوقت</span></th>
                                <th class="px-3 py-2"><span class="inline-flex items-center gap-1"><FileSpreadsheet class="h-3.5 w-3.5" />الحالة</span></th>
                                <th class="px-3 py-2 text-left"><span class="inline-flex items-center gap-1"><Settings2 class="h-3.5 w-3.5" />الإجراءات</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700 bg-gray-900">
                            <tr
                                v-for="entry in schedules"
                                :key="entry.id"
                                class="stage-row-accent"
                                :style="stageAccent(entry.school_stage_id, entry.stage?.name || '')"
                            >
                                <td class="px-3 py-2">
                                    <span class="stage-badge" :style="stageAccent(entry.school_stage_id, entry.stage?.name || '')">{{ entry.stage?.name || '-' }}</span>
                                    <span class="mx-1">/</span>{{ entry.classroom?.name || '-' }}
                                </td>
                                <td class="px-3 py-2">
                                    <p>{{ entry.subject?.name || '-' }}</p>
                                    <p class="text-xs text-gray-500">{{ entry.teacher?.name || '-' }}</p>
                                    <p class="text-[11px] text-gray-500" v-if="entry.timetable_version?.name">
                                        النسخة: {{ entry.timetable_version.name }}
                                    </p>
                                </td>
                                <td class="px-3 py-2">
                                    <span class="rounded px-2 py-1 text-[10px]" :class="scopeClass(entry.schedule_scope)">
                                        {{ scopeLabel(entry.schedule_scope) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">{{ scheduleSlotLabel(entry) }}</td>
                                <td class="px-3 py-2">{{ scheduleTimeLabel(entry) }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded px-2 py-1 text-[10px]" :class="entry.is_active ? 'bg-emerald-500/20 text-emerald-200' : 'bg-gray-700 text-gray-300'">
                                        {{ entry.is_active ? 'نشط' : 'غير نشط' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex justify-end gap-2">
                                        <button class="inline-flex items-center gap-1 rounded bg-blue-700 px-2 py-1 text-xs hover:bg-blue-600" @click="openEditScheduleModal(entry)">
                                            <Pencil class="h-3.5 w-3.5" />
                                            <span>تعديل</span>
                                        </button>
                                        <button class="inline-flex items-center gap-1 rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600" @click="removeSchedule(entry.id)">
                                            <Trash2 class="h-3.5 w-3.5" />
                                            <span>حذف</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="schedules.length === 0">
                                <td colspan="7" class="px-3 py-6 text-center text-gray-500">لا توجد حصص مطابقة للفلتر الحالي.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <QuickSetupWizardModal
            :open="quickSetupVisible"
            :steps="quickSetupSteps"
            :current-step-key="quickSetupCurrentStepKey"
            :loading="quickSetupLoading"
            :error="quickSetupError"
            @close="closeQuickSetup"
            @refresh="refreshQuickSetupStatus(quickSetupCurrentStepKey)"
            @change-step="quickSetupCurrentStepKey = $event"
            @open-step="openQuickSetupStepSection"
            @prev-step="moveQuickSetupStep(-1)"
            @next-step="moveQuickSetupStep(1)"
        />
    </RoleLayout>
</template>

<style scoped>
.academic-tree-section {
    --academic-tree-section-bg: linear-gradient(180deg, rgba(8, 47, 73, 0.34), rgba(2, 6, 23, 0.46));
    --academic-tree-section-border: rgba(34, 211, 238, 0.54);
    --academic-tree-title: rgb(248 250 252);
    --academic-tree-copy: rgba(207, 250, 254, 0.78);
    --academic-tree-filter-bg: linear-gradient(180deg, rgba(15, 23, 42, 0.96), rgba(2, 6, 23, 0.9));
    --academic-tree-filter-border: rgba(71, 85, 105, 0.9);
    --academic-tree-card-bg: rgba(15, 23, 42, 0.94);
    --academic-tree-card-bg-strong: rgba(2, 6, 23, 0.92);
    --academic-tree-branch-bg: rgba(15, 23, 42, 0.82);
    --academic-tree-term-bg: rgba(2, 6, 23, 0.68);
    --academic-tree-detail-bg: rgba(15, 23, 42, 0.72);
    --academic-tree-border-soft: rgba(51, 65, 85, 0.88);
    --academic-tree-text: rgb(248 250 252);
    --academic-tree-muted: rgb(148 163 184);
    --academic-tree-muted-strong: rgb(203 213 225);
    --academic-tree-control-bg: rgba(15, 23, 42, 0.96);
    --academic-tree-control-border: rgba(71, 85, 105, 0.94);
    --academic-tree-control-text: rgb(248 250 252);
    --academic-tree-control-placeholder: rgb(100 116 139);
    background: var(--academic-tree-section-bg);
    border-color: var(--academic-tree-section-border);
    color: var(--academic-tree-text);
}

.academic-tree-section--assignments {
    --academic-tree-section-bg: linear-gradient(180deg, rgba(6, 78, 59, 0.32), rgba(2, 6, 23, 0.48));
    --academic-tree-section-border: rgba(16, 185, 129, 0.52);
    --academic-tree-copy: rgba(209, 250, 229, 0.78);
}

.academic-tree-section-title {
    color: var(--academic-tree-title);
}

.academic-tree-section-copy {
    color: var(--academic-tree-copy);
}

.academic-tree-filter {
    background: var(--academic-tree-filter-bg) !important;
    border-color: var(--academic-tree-filter-border) !important;
    color: var(--academic-tree-text) !important;
}

.academic-tree-editor {
    background: var(--academic-tree-branch-bg) !important;
    border-color: var(--academic-tree-border-soft) !important;
    color: var(--academic-tree-text);
}

.academic-tree-editor label {
    color: var(--academic-tree-muted-strong) !important;
}

.academic-tree-filter :is(input, select),
.academic-tree-editor :is(input, select, textarea) {
    background: var(--academic-tree-control-bg) !important;
    border-color: var(--academic-tree-control-border) !important;
    color: var(--academic-tree-control-text) !important;
}

.academic-tree-filter input::placeholder,
.academic-tree-editor input::placeholder,
.academic-tree-editor textarea::placeholder {
    color: var(--academic-tree-control-placeholder) !important;
}

.academic-tree-filter option,
.academic-tree-editor option {
    background: var(--academic-tree-control-bg);
    color: var(--academic-tree-control-text);
}

.academic-tree-stage-card,
.academic-tree-course-card {
    position: relative;
    background: linear-gradient(
        90deg,
        hsl(var(--stage-h, 208) 82% 58% / 0.14) 0%,
        var(--academic-tree-card-bg) 42%,
        var(--academic-tree-card-bg-strong) 100%
    ) !important;
    border-color: hsl(var(--stage-h, 208) 66% 60% / 0.38) !important;
    color: var(--academic-tree-text);
}

.academic-tree-stage-card::before,
.academic-tree-course-card::before {
    content: '';
    position: absolute;
    inset-block: 0;
    inset-inline-end: 0;
    width: 4px;
    background: hsl(var(--stage-h, 208) 84% 62% / 0.82);
}

.academic-tree-branch-card {
    background: var(--academic-tree-branch-bg) !important;
    border-color: var(--academic-tree-border-soft) !important;
}

.academic-tree-term-card {
    background: var(--academic-tree-term-bg) !important;
    border-color: var(--academic-tree-border-soft) !important;
}

.academic-tree-detail-panel,
.academic-tree-detail-card,
.academic-tree-empty {
    background: var(--academic-tree-detail-bg) !important;
    border-color: var(--academic-tree-border-soft) !important;
    color: var(--academic-tree-muted-strong) !important;
}

.academic-tree-section .text-slate-900,
.academic-tree-section .text-slate-800,
.academic-tree-section .text-slate-700 {
    color: var(--academic-tree-text) !important;
}

.academic-tree-section .text-slate-600,
.academic-tree-section .text-slate-500,
.academic-tree-section .text-slate-400 {
    color: var(--academic-tree-muted) !important;
}

.academic-tree-section button[class*='hover:bg-slate']:hover,
.academic-tree-section button[class*='hover:bg-white']:hover,
.academic-tree-section button[class*='dark:hover:bg-gray']:hover {
    background-color: rgba(30, 41, 59, 0.72);
}

:global(html.theme-light) .academic-tree-section {
    --academic-tree-section-bg: linear-gradient(180deg, rgba(255, 255, 255, 0.99), rgba(239, 249, 255, 0.96));
    --academic-tree-section-border: rgba(6, 182, 212, 0.36);
    --academic-tree-title: #0f172a;
    --academic-tree-copy: #475569;
    --academic-tree-filter-bg: linear-gradient(180deg, rgba(255, 255, 255, 0.99), rgba(248, 250, 252, 0.99));
    --academic-tree-filter-border: rgba(186, 199, 214, 0.92);
    --academic-tree-card-bg: rgba(255, 255, 255, 0.99);
    --academic-tree-card-bg-strong: rgba(248, 250, 252, 0.99);
    --academic-tree-branch-bg: rgba(248, 250, 252, 0.98);
    --academic-tree-term-bg: rgba(255, 255, 255, 0.99);
    --academic-tree-detail-bg: rgba(248, 250, 252, 0.98);
    --academic-tree-border-soft: rgba(203, 213, 225, 0.95);
    --academic-tree-text: #0f172a;
    --academic-tree-muted: #64748b;
    --academic-tree-muted-strong: #334155;
    --academic-tree-control-bg: #ffffff;
    --academic-tree-control-border: rgba(203, 213, 225, 0.98);
    --academic-tree-control-text: #0f172a;
    --academic-tree-control-placeholder: #94a3b8;
    box-shadow: 0 18px 44px rgba(15, 23, 42, 0.07);
}

:global(html.theme-light) .academic-tree-section--assignments {
    --academic-tree-section-bg: linear-gradient(180deg, rgba(255, 255, 255, 0.99), rgba(240, 253, 244, 0.96));
    --academic-tree-section-border: rgba(16, 185, 129, 0.34);
}

:global(html.theme-light) .academic-tree-stage-card,
:global(html.theme-light) .academic-tree-course-card {
    background: linear-gradient(
        90deg,
        hsl(var(--stage-h, 208) 84% 96% / 0.98) 0%,
        var(--academic-tree-card-bg) 44%,
        var(--academic-tree-card-bg-strong) 100%
    ) !important;
    border-color: hsl(var(--stage-h, 208) 64% 48% / 0.25) !important;
}

:global(html.theme-light) .academic-tree-section button[class*='hover:bg-slate']:hover,
:global(html.theme-light) .academic-tree-section button[class*='hover:bg-white']:hover,
:global(html.theme-light) .academic-tree-section button[class*='dark:hover:bg-gray']:hover {
    background-color: rgba(241, 245, 249, 0.92);
}

.weekly-grid-section {
    --weekly-grid-section-bg: linear-gradient(180deg, rgba(15, 23, 42, 0.78), rgba(15, 23, 42, 0.66));
    --weekly-grid-section-border: rgba(34, 211, 238, 0.48);
    --weekly-grid-section-title: rgb(207 250 254);
    --weekly-grid-section-copy: rgba(207, 250, 254, 0.76);
    --weekly-grid-info-bg: rgba(30, 41, 59, 0.82);
    --weekly-grid-info-border: rgba(71, 85, 105, 0.95);
    --weekly-grid-info-text: rgb(226 232 240);
    --weekly-grid-info-subtle: rgb(148 163 184);
    --weekly-grid-info-accent: rgb(165 243 252);
    --weekly-grid-period-bg: rgba(15, 23, 42, 0.95);
    --weekly-grid-period-border: rgba(71, 85, 105, 0.95);
    --weekly-grid-period-text: rgb(248 250 252);
    --weekly-grid-frame-bg: rgba(2, 6, 23, 0.6);
    --weekly-grid-frame-border: rgba(71, 85, 105, 0.95);
    --weekly-grid-divider: rgba(51, 65, 85, 0.7);
    --weekly-grid-header-bg: rgba(51, 65, 85, 0.96);
    --weekly-grid-header-text: rgb(203 213 225);
    --weekly-grid-day-bg: rgba(15, 23, 42, 0.96);
    --weekly-grid-day-text: rgb(207 250 254);
    --weekly-grid-slot-bg: rgba(15, 23, 42, 0.94);
    --weekly-grid-card-bg: rgba(15, 23, 42, 0.82);
    --weekly-grid-card-border: rgba(71, 85, 105, 0.78);
    --weekly-grid-select-bg: rgba(2, 6, 23, 0.92);
    --weekly-grid-select-border: rgba(71, 85, 105, 0.85);
    --weekly-grid-select-text: rgb(248 250 252);
    --weekly-grid-select-placeholder-bg: rgba(15, 23, 42, 0.96);
    --weekly-grid-select-placeholder-border: rgba(125, 211, 252, 0.48);
    --weekly-grid-select-placeholder-text: rgb(226 232 240);
    --weekly-grid-select-disabled-bg: rgba(30, 41, 59, 0.84);
    --weekly-grid-select-disabled-text: rgb(203 213 225);
    --weekly-grid-meta-text: rgb(148 163 184);
    --weekly-grid-clear-border: rgba(100, 116, 139, 0.9);
    --weekly-grid-clear-text: rgb(226 232 240);
    --weekly-grid-clear-hover-border: rgba(251, 113, 133, 0.85);
    --weekly-grid-clear-hover-bg: rgba(127, 29, 29, 0.12);
    --weekly-grid-clear-hover-text: rgb(254 205 211);
    background: var(--weekly-grid-section-bg);
    border-color: var(--weekly-grid-section-border);
}

.weekly-grid-section-title {
    color: var(--weekly-grid-section-title);
}

.weekly-grid-section-copy {
    color: var(--weekly-grid-section-copy);
}

.weekly-grid-info-card {
    background: var(--weekly-grid-info-bg);
    border-color: var(--weekly-grid-info-border);
}

.weekly-grid-info-copy {
    color: var(--weekly-grid-info-text);
}

.weekly-grid-weekoff-label {
    color: var(--weekly-grid-info-accent);
}

.weekly-grid-info-subcopy,
.weekly-grid-period-label {
    color: var(--weekly-grid-info-subtle);
}

.weekly-grid-period-input {
    background: var(--weekly-grid-period-bg);
    border-color: var(--weekly-grid-period-border);
    color: var(--weekly-grid-period-text);
}

.weekly-grid-workspace {
    min-height: 0;
}

.weekly-grid-frame {
    overflow: hidden;
    background: var(--weekly-grid-frame-bg);
    border-color: var(--weekly-grid-frame-border);
}

.weekly-grid-board {
    display: grid;
    width: 100%;
    direction: rtl;
    gap: 1px;
    overflow: hidden;
    border-radius: 1rem;
    background: var(--weekly-grid-divider);
}

.weekly-grid-corner,
.weekly-grid-header-cell,
.weekly-grid-day-cell,
.weekly-grid-slot {
    min-width: 0;
    min-height: 0;
}

.weekly-grid-corner,
.weekly-grid-header-cell {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.45rem;
    background: var(--weekly-grid-header-bg);
    color: var(--weekly-grid-header-text);
    font-size: var(--weekly-grid-header-font-size);
    font-weight: 700;
    line-height: 1.1;
}

.weekly-grid-corner,
.weekly-grid-day-cell {
    background: var(--weekly-grid-day-bg);
}

.weekly-grid-day-cell {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem 0.4rem;
    color: var(--weekly-grid-day-text);
    font-size: var(--weekly-grid-day-font-size);
    font-weight: 700;
    text-align: center;
    line-height: 1.15;
}

.weekly-grid-slot {
    padding: 0.3rem;
    background: var(--weekly-grid-slot-bg);
    overflow: hidden;
}

.weekly-grid-slot-card {
    display: grid;
    grid-template-rows: auto auto minmax(0, 1fr);
    gap: var(--weekly-grid-card-gap);
    height: 100%;
    padding: var(--weekly-grid-card-padding);
    border: 1px solid var(--weekly-grid-card-border);
    border-radius: 0.95rem;
    background: var(--weekly-grid-card-bg);
    overflow: hidden;
}

.weekly-grid-select {
    width: 100%;
    min-width: 0;
    height: var(--weekly-grid-control-height);
    padding-inline: 0.55rem;
    border: 1px solid var(--weekly-grid-select-border);
    border-radius: 0.7rem;
    background: var(--weekly-grid-select-bg);
    color: var(--weekly-grid-select-text);
    font-size: var(--weekly-grid-select-font-size);
    line-height: 1.15;
    text-overflow: ellipsis;
    font-weight: 600;
}

.weekly-grid-select--empty {
    border-color: var(--weekly-grid-select-placeholder-border);
    background: var(--weekly-grid-select-placeholder-bg);
    color: var(--weekly-grid-select-placeholder-text);
    font-weight: 800;
}

.weekly-grid-select option {
    background: var(--weekly-grid-select-bg);
    color: var(--weekly-grid-select-text);
    font-weight: 600;
}

.weekly-grid-select option[value=''] {
    color: var(--weekly-grid-select-placeholder-text);
    font-weight: 800;
}

.weekly-grid-select:disabled {
    cursor: not-allowed;
    opacity: 0.82;
    background: var(--weekly-grid-select-disabled-bg);
    color: var(--weekly-grid-select-disabled-text);
}

.weekly-grid-select:disabled.weekly-grid-select--empty {
    color: var(--weekly-grid-select-disabled-text);
}

.weekly-grid-slot-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.35rem;
    min-height: 0;
    overflow: hidden;
}

.weekly-grid-slot-footer--empty {
    justify-content: flex-end;
}

.weekly-grid-slot-meta {
    min-width: 0;
    overflow: hidden;
    color: var(--weekly-grid-meta-text);
    font-size: var(--weekly-grid-meta-font-size);
    line-height: 1.15;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.weekly-grid-clear-button {
    flex-shrink: 0;
    padding: 0.18rem 0.45rem;
    border: 1px solid var(--weekly-grid-clear-border);
    border-radius: 0.6rem;
    background: transparent;
    color: var(--weekly-grid-clear-text);
    font-size: var(--weekly-grid-clear-font-size);
    line-height: 1.1;
    transition: border-color 0.2s ease, color 0.2s ease, background-color 0.2s ease, opacity 0.2s ease;
}

.weekly-grid-clear-button:hover {
    border-color: var(--weekly-grid-clear-hover-border);
    color: var(--weekly-grid-clear-hover-text);
    background: var(--weekly-grid-clear-hover-bg);
}

.weekly-grid-clear-button--subtle {
    opacity: 0.72;
}

.weekly-grid-board--compact .weekly-grid-slot {
    padding: 0.24rem;
}

.weekly-grid-board--compact .weekly-grid-corner,
.weekly-grid-board--compact .weekly-grid-header-cell,
.weekly-grid-board--compact .weekly-grid-day-cell {
    padding: 0.38rem;
}

.weekly-grid-board--dense .weekly-grid-slot {
    padding: 0.18rem;
}

.weekly-grid-board--dense .weekly-grid-corner,
.weekly-grid-board--dense .weekly-grid-header-cell,
.weekly-grid-board--dense .weekly-grid-day-cell {
    padding: 0.3rem;
}

.weekly-grid-board--dense .weekly-grid-slot-card {
    border-radius: 0.8rem;
}

:global(html.theme-light) .weekly-grid-section {
    --weekly-grid-section-bg: linear-gradient(180deg, rgba(251, 254, 255, 0.99), rgba(241, 248, 253, 0.99));
    --weekly-grid-section-border: rgba(56, 189, 248, 0.42);
    --weekly-grid-section-title: #0f766e;
    --weekly-grid-section-copy: #64748b;
    --weekly-grid-info-bg: linear-gradient(180deg, rgba(246, 250, 253, 0.99), rgba(236, 244, 250, 0.99));
    --weekly-grid-info-border: rgba(186, 199, 214, 0.92);
    --weekly-grid-info-text: #1e293b;
    --weekly-grid-info-subtle: #64748b;
    --weekly-grid-info-accent: #0f766e;
    --weekly-grid-period-bg: rgba(255, 255, 255, 0.98);
    --weekly-grid-period-border: rgba(186, 199, 214, 0.95);
    --weekly-grid-period-text: #0f172a;
    --weekly-grid-frame-bg: linear-gradient(180deg, rgba(244, 249, 253, 0.99), rgba(235, 243, 250, 0.99));
    --weekly-grid-frame-border: rgba(186, 199, 214, 0.95);
    --weekly-grid-divider: rgba(202, 214, 227, 0.96);
    --weekly-grid-header-bg: linear-gradient(180deg, rgba(220, 232, 244, 0.98), rgba(211, 225, 239, 0.98));
    --weekly-grid-header-text: #29435c;
    --weekly-grid-day-bg: linear-gradient(180deg, rgba(219, 245, 245, 0.98), rgba(208, 236, 239, 0.98));
    --weekly-grid-day-text: #115e59;
    --weekly-grid-slot-bg: rgba(239, 245, 251, 0.98);
    --weekly-grid-card-bg: linear-gradient(180deg, rgba(255, 255, 255, 0.995), rgba(248, 251, 254, 0.995));
    --weekly-grid-card-border: rgba(191, 203, 218, 0.96);
    --weekly-grid-select-bg: #ffffff;
    --weekly-grid-select-border: rgba(191, 203, 218, 0.98);
    --weekly-grid-select-text: #0f172a;
    --weekly-grid-select-placeholder-bg: #f8fafc;
    --weekly-grid-select-placeholder-border: rgba(14, 116, 144, 0.42);
    --weekly-grid-select-placeholder-text: #1e3a5f;
    --weekly-grid-select-disabled-bg: rgba(226, 232, 240, 0.96);
    --weekly-grid-select-disabled-text: #475569;
    --weekly-grid-meta-text: #64748b;
    --weekly-grid-clear-border: rgba(148, 163, 184, 0.92);
    --weekly-grid-clear-text: #475569;
    --weekly-grid-clear-hover-border: rgba(244, 114, 182, 0.74);
    --weekly-grid-clear-hover-bg: rgba(251, 207, 232, 0.32);
    --weekly-grid-clear-hover-text: #9d174d;
    box-shadow: 0 16px 38px rgba(148, 163, 184, 0.1);
}

:global(html.theme-light) .weekly-grid-slot-card {
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.72);
}

:global(html.theme-light) .weekly-grid-select {
    box-shadow: 0 1px 1px rgba(15, 23, 42, 0.04);
}

:global(html.theme-light) .weekly-grid-select option {
    color: #0f172a;
    background: #ffffff;
}
</style>
