<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { Head, usePage } from '@inertiajs/vue3';
import RoleLayout from '@/Layouts/RoleLayout.vue';
import { useActionDialog } from '@/composables/useActionDialog';
import { stageAccentStyle } from '@/utils/stagePalette';

axios.defaults.headers.common['Accept-Language'] = 'ar';

const props = defineProps({
    school: { type: Object, default: null },
    stages: { type: Array, default: () => [] },
    students: { type: Array, default: () => [] },
    leaveTypes: { type: Array, default: () => [] },
    selectedStageId: { type: [Number, String, null], default: null },
    selectedClassroomId: { type: [Number, String, null], default: null },
    selectedClassroomGradeName: { type: [String, null], default: null },
    isManager: { type: Boolean, default: false },
    permissions: { type: Object, default: () => ({}) },
});

const page = usePage();
const actionDialog = useActionDialog();
const currentUser = computed(() => page.props.auth?.user || null);
const roleForLayout = computed(() => {
    if (props.isManager) return 'SCHOOL_MANAGER';
    return currentUser.value?.primary_role === 'school_manager' ? 'SCHOOL_MANAGER' : 'STAFF';
});

const canManageLeaves = computed(() => Boolean(props.permissions?.can_manage_student_leaves || props.isManager));

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
    school_student_id: 'الطالب',
    school_leave_type_id: 'نوع الإجازة',
    source: 'مصدر الطلب',
    reason: 'سبب الإجازة',
    date_from: 'من تاريخ',
    date_to: 'إلى تاريخ',
};

const toReadableFieldLabel = (field) => fieldLabels[field] || field;

const normalizeApiMessage = (message, field = '') => {
    if (!message || typeof message !== 'string') {
        return '';
    }

    const msg = message.trim();
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

    return msg;
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

const normalizeGradeName = (value) => {
    const normalized = String(value || '').trim();
    return normalized !== '' ? normalized : 'غير محدد';
};

const stageAccent = (stageId, stageName = '') => stageAccentStyle(stageId, stageName);

const stageOptions = computed(() => (props.stages || []).map((s) => ({
    id: s.id,
    name: s.name,
    grades: (s.grades || [])
        .filter((grade) => Boolean(grade.is_active))
        .map((grade) => ({
            ...grade,
            name: normalizeGradeName(grade.name),
        })),
    classrooms: (s.classrooms || []).map((classroom) => ({
        ...classroom,
        grade_name: normalizeGradeName(classroom.grade_name),
    })),
})));

const classroomStageById = computed(() => {
    const map = new Map();

    (stageOptions.value || []).forEach((stage) => {
        (stage.classrooms || []).forEach((classroom) => {
            map.set(Number(classroom.id), {
                stageId: Number(stage.id),
                stageName: stage.name || '',
            });
        });
    });

    return map;
});

const leaveStageMeta = (leave) => {
    const classroomId = Number(
        leave?.school_classroom_id
        || leave?.classroom?.id
        || leave?.student?.school_classroom_id
        || 0
    );

    if (!classroomId) {
        return null;
    }

    return classroomStageById.value.get(classroomId) || null;
};

const leaveStageName = (leave) => leaveStageMeta(leave)?.stageName || '';
const leaveStageStyle = (leave) => {
    const meta = leaveStageMeta(leave);
    return stageAccent(meta?.stageId || null, meta?.stageName || '');
};

const leaveFilters = ref({
    stage_id: props.selectedStageId || '',
    classroom_grade_name: props.selectedClassroomGradeName || '',
    classroom_id: props.selectedClassroomId || '',
    school_student_id: '',
    school_leave_type_id: '',
    status: '',
    source: '',
    date_from: '',
    date_to: '',
});

const gradeOptions = computed(() => {
    const stage = stageOptions.value.find((s) => Number(s.id) === Number(leaveFilters.value.stage_id));
    if (!stage) return [];

    const configured = (stage.grades || []).map((grade) => normalizeGradeName(grade.name));
    if (configured.length > 0) {
        return [...new Set(configured)];
    }

    const grades = (stage.classrooms || []).map((classroom) => normalizeGradeName(classroom.grade_name));
    return [...new Set(grades)];
});

const classrooms = computed(() => {
    const stage = stageOptions.value.find((s) => Number(s.id) === Number(leaveFilters.value.stage_id));
    if (!stage) return [];

    let rows = stage.classrooms || [];
    if (leaveFilters.value.classroom_grade_name) {
        rows = rows.filter((classroom) => normalizeGradeName(classroom.grade_name) === normalizeGradeName(leaveFilters.value.classroom_grade_name));
    }

    return rows;
});

const students = computed(() => {
    if (!leaveFilters.value.classroom_id) return props.students || [];
    return (props.students || []).filter((s) => Number(s.school_classroom_id) === Number(leaveFilters.value.classroom_id));
});

watch(() => leaveFilters.value.stage_id, () => {
    const grades = gradeOptions.value;
    if (leaveFilters.value.classroom_grade_name && !grades.includes(normalizeGradeName(leaveFilters.value.classroom_grade_name))) {
        leaveFilters.value.classroom_grade_name = '';
    }

    const allowed = classrooms.value.map((c) => Number(c.id));
    if (leaveFilters.value.classroom_id && !allowed.includes(Number(leaveFilters.value.classroom_id))) {
        leaveFilters.value.classroom_id = '';
    }
});

watch(() => leaveFilters.value.classroom_grade_name, () => {
    const allowed = classrooms.value.map((c) => Number(c.id));
    if (leaveFilters.value.classroom_id && !allowed.includes(Number(leaveFilters.value.classroom_id))) {
        leaveFilters.value.classroom_id = '';
    }
});

const leaveTypes = ref([...(props.leaveTypes || [])]);
const activeLeaveTypes = computed(() => leaveTypes.value.filter((t) => Boolean(t.is_active)));

const refreshLeaveTypes = async () => {
    try {
        const response = await axios.get(route('api.school.leave_types.index'));
        leaveTypes.value = response.data?.data || [];
    } catch {
        // Keep the current list if refreshing leave types fails.
    }
};

const statusOptions = [
    { value: '', label: 'الكل' },
    { value: 'PENDING', label: 'معلّق' },
    { value: 'APPROVED', label: 'معتمد' },
    { value: 'REJECTED', label: 'مرفوض' },
    { value: 'CANCELLED', label: 'ملغي' },
];
const sourceOptions = [
    { value: '', label: 'الكل' },
    { value: 'PRE_APPROVED', label: 'مسبقة' },
    { value: 'RETROACTIVE', label: 'بأثر رجعي' },
];

const leaves = ref([]);
const leaveError = ref('');
const leaveFormErrors = ref({});
const attachmentFiles = ref({});
const attachmentUploadingFor = ref(null);

const leaveForm = ref({
    id: null,
    school_student_id: '',
    school_leave_type_id: '',
    source: 'PRE_APPROVED',
    start_date: '',
    end_date: '',
    reason: '',
});

const resetLeaveForm = () => {
    leaveFormErrors.value = {};
    leaveForm.value = {
        id: null,
        school_student_id: students.value[0]?.id || '',
        school_leave_type_id: activeLeaveTypes.value[0]?.id || '',
        source: 'PRE_APPROVED',
        start_date: '',
        end_date: '',
        reason: '',
    };
};

watch(() => leaveFilters.value.classroom_id, () => {
    const allowed = students.value.map((s) => Number(s.id));
    if (leaveFilters.value.school_student_id && !allowed.includes(Number(leaveFilters.value.school_student_id))) {
        leaveFilters.value.school_student_id = '';
    }
    if (!leaveForm.value.id) {
        leaveForm.value.school_student_id = students.value[0]?.id || '';
    }
});

const loadLeaves = async () => {
    leaveError.value = '';
    try {
        const response = await axios.get(route('api.school.leaves.index'), {
            params: {
                school_stage_id: leaveFilters.value.stage_id || undefined,
                classroom_grade_name: leaveFilters.value.classroom_grade_name || undefined,
                school_classroom_id: leaveFilters.value.classroom_id || undefined,
                school_student_id: leaveFilters.value.school_student_id || undefined,
                school_leave_type_id: leaveFilters.value.school_leave_type_id || undefined,
                status: leaveFilters.value.status || undefined,
                source: leaveFilters.value.source || undefined,
                date_from: leaveFilters.value.date_from || undefined,
                date_to: leaveFilters.value.date_to || undefined,
                per_page: 20,
            },
        });
        leaves.value = response.data?.data || [];
    } catch (error) {
        leaveError.value = resolveApiErrorMessage(error, 'تعذر تحميل طلبات الإجازات.');
    }
};

const saveLeave = async () => {
    leaveError.value = '';
    leaveFormErrors.value = {};
    try {
        const payload = {
            school_student_id: leaveForm.value.school_student_id,
            school_leave_type_id: leaveForm.value.school_leave_type_id,
            source: leaveForm.value.source,
            start_date: leaveForm.value.start_date,
            end_date: leaveForm.value.end_date,
            reason: leaveForm.value.reason || null,
        };
        if (leaveForm.value.id) await axios.patch(route('api.school.leaves.update', leaveForm.value.id), payload);
        else await axios.post(route('api.school.leaves.store'), payload);
        resetLeaveForm();
        await loadLeaves();
    } catch (error) {
        leaveFormErrors.value = extractValidationErrors(error);
        leaveError.value = resolveApiErrorMessage(error, 'تعذر حفظ طلب الإجازة.', ['school_student_id', 'school_leave_type_id', 'start_date', 'end_date']);
    }
};

const setLeaveFormForEdit = (leave) => {
    leaveForm.value = {
        id: leave.id,
        school_student_id: leave.school_student_id,
        school_leave_type_id: leave.school_leave_type_id,
        source: leave.source,
        start_date: leave.start_date,
        end_date: leave.end_date,
        reason: leave.reason || '',
    };
};
const approveLeave = async (leave) => {
    leaveError.value = '';
    try {
        await axios.post(route('api.school.leaves.approve', leave.id), {});
        await loadLeaves();
    } catch (error) {
        leaveError.value = resolveApiErrorMessage(error, 'تعذر اعتماد الإجازة.');
    }
};

const rejectLeave = async (leave) => {
    const reason = await actionDialog.prompt({
        title: 'رفض الإجازة',
        message: 'أدخل سبب الرفض قبل متابعة الإجراء.',
        inputLabel: 'سبب الرفض',
        inputPlaceholder: 'اكتب سبب الرفض',
        inputRequired: true,
        inputMultiline: true,
        confirmText: 'رفض الإجازة',
        cancelText: 'إلغاء',
        variant: 'danger',
    });
    if (reason === null) return;
    leaveError.value = '';
    try {
        await axios.post(route('api.school.leaves.reject', leave.id), { reason });
        await loadLeaves();
    } catch (error) {
        leaveError.value = resolveApiErrorMessage(error, 'تعذر رفض الإجازة.');
    }
};

const cancelLeave = async (leave) => {
    const reason = await actionDialog.prompt({
        title: 'إلغاء الإجازة',
        message: 'يمكنك إضافة سبب الإلغاء اختياريًا قبل المتابعة.',
        inputLabel: 'سبب الإلغاء',
        inputPlaceholder: 'اكتب سبب الإلغاء إن وجد',
        inputRequired: false,
        inputMultiline: true,
        confirmText: 'إلغاء الإجازة',
        cancelText: 'تراجع',
        variant: 'warning',
    });
    if (reason === null) return;
    leaveError.value = '';
    try {
        await axios.post(route('api.school.leaves.cancel', leave.id), { reason });
        await loadLeaves();
    } catch (error) {
        leaveError.value = resolveApiErrorMessage(error, 'تعذر إلغاء الإجازة.');
    }
};

const setAttachmentFile = (leaveId, event) => {
    attachmentFiles.value[leaveId] = event?.target?.files?.[0] || null;
};

const uploadAttachment = async (leave) => {
    const file = attachmentFiles.value[leave.id];
    if (!file) return;
    const formData = new FormData();
    formData.append('file', file);
    attachmentUploadingFor.value = leave.id;
    leaveError.value = '';
    try {
        await axios.post(route('api.school.leaves.attachments.store', leave.id), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        attachmentFiles.value[leave.id] = null;
        await loadLeaves();
    } catch (error) {
        leaveError.value = resolveApiErrorMessage(error, 'تعذر رفع المرفق.');
    } finally {
        attachmentUploadingFor.value = null;
    }
};

const resetLeaveFilters = async () => {
    leaveFilters.value = {
        stage_id: props.selectedStageId || '',
        classroom_grade_name: props.selectedClassroomGradeName || '',
        classroom_id: props.selectedClassroomId || '',
        school_student_id: '',
        school_leave_type_id: '',
        status: '',
        source: '',
        date_from: '',
        date_to: '',
    };
    await loadLeaves();
};

const statusBadgeClass = (status) => {
    if (status === 'APPROVED') return 'bg-emerald-500/20 text-emerald-200';
    if (status === 'REJECTED') return 'bg-red-500/20 text-red-200';
    if (status === 'CANCELLED') return 'bg-gray-600/40 text-gray-200';
    return 'bg-amber-500/20 text-amber-200';
};

onMounted(async () => {
    await refreshLeaveTypes();
    if (canManageLeaves.value) {
        resetLeaveForm();
        await loadLeaves();
    }
});
</script>

<template>
    <Head title="إجازات الطلاب" />
    <RoleLayout title="إجازات الطلاب" :role="roleForLayout" :permissions="props.permissions">
        <div v-if="!school" class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm text-amber-200">
            لا يوجد ربط لمدرسة لهذا الحساب حاليًا.
        </div>

        <div v-else class="space-y-6">
            <section class="rounded-xl border border-gray-800 bg-gray-900 p-4">
                <p class="text-xs text-gray-400">المدرسة</p>
                <p class="text-lg font-bold">{{ school.name }}</p>
                <p class="text-xs text-gray-500">{{ school.school_id }}</p>
            </section>

            <section v-if="canManageLeaves" class="rounded-xl border border-gray-800 bg-gray-900 p-4">
                <h2 class="mb-3 text-lg font-bold">1) طلبات إجازات الطلاب</h2>

                <div class="mt-4 rounded-lg border border-gray-800 bg-gray-950/40 p-3">
                    <div class="mb-2 text-xs font-bold text-gray-300">إضافة / تعديل طلب إجازة</div>
                    <form class="grid grid-cols-1 gap-2 md:grid-cols-3" @submit.prevent="saveLeave">
                        <label class="block text-xs text-gray-400">
                            الطالب
                            <select v-model="leaveForm.school_student_id" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"><option value="" disabled>اختر الطالب</option><option v-for="student in students" :key="student.id" :value="student.id">{{ student.full_name }}</option></select>
                            <span v-if="leaveFormErrors.school_student_id" class="mt-1 block text-[11px] text-red-400">{{ leaveFormErrors.school_student_id }}</span>
                        </label>
                        <label class="block text-xs text-gray-400">
                            نوع الإجازة
                            <select v-model="leaveForm.school_leave_type_id" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"><option value="" disabled>اختر نوع الإجازة</option><option v-for="typeItem in activeLeaveTypes" :key="typeItem.id" :value="typeItem.id">{{ typeItem.name }}</option></select>
                            <span v-if="leaveFormErrors.school_leave_type_id" class="mt-1 block text-[11px] text-red-400">{{ leaveFormErrors.school_leave_type_id }}</span>
                        </label>
                        <label class="block text-xs text-gray-400">
                            مصدر الطلب
                            <select v-model="leaveForm.source" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"><option v-for="source in sourceOptions.filter((x) => x.value)" :key="source.value" :value="source.value">{{ source.label }}</option></select>
                            <span v-if="leaveFormErrors.source" class="mt-1 block text-[11px] text-red-400">{{ leaveFormErrors.source }}</span>
                        </label>
                        <label class="block text-xs text-gray-400">
                            تاريخ البداية
                            <input v-model="leaveForm.start_date" type="date" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm" />
                            <span v-if="leaveFormErrors.start_date" class="mt-1 block text-[11px] text-red-400">{{ leaveFormErrors.start_date }}</span>
                        </label>
                        <label class="block text-xs text-gray-400">
                            تاريخ النهاية
                            <input v-model="leaveForm.end_date" type="date" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm" />
                            <span v-if="leaveFormErrors.end_date" class="mt-1 block text-[11px] text-red-400">{{ leaveFormErrors.end_date }}</span>
                        </label>
                        <label class="block text-xs text-gray-400">
                            سبب الإجازة (اختياري)
                            <input v-model="leaveForm.reason" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm" placeholder="مثال: تقرير طبي أو ظرف أسري" />
                            <span v-if="leaveFormErrors.reason" class="mt-1 block text-[11px] text-red-400">{{ leaveFormErrors.reason }}</span>
                        </label>
                        <div class="flex flex-wrap items-center gap-2 md:col-span-3">
                            <button class="rounded bg-emerald-700 px-3 py-2 text-sm font-bold hover:bg-emerald-600">{{ leaveForm.id ? 'تحديث الطلب' : 'إضافة الطلب' }}</button>
                            <button v-if="leaveForm.id" type="button" class="rounded bg-gray-700 px-3 py-2 text-sm font-bold hover:bg-gray-600" @click="resetLeaveForm">إلغاء التعديل</button>
                        </div>
                    </form>
                </div>

                <div class="rounded-lg border border-gray-800 bg-gray-950/40 p-3">
                    <div class="mb-2 text-xs font-bold text-gray-300">فلترة الطلبات</div>
                    <div class="mb-3 grid grid-cols-1 gap-2 md:grid-cols-5">
                        <label class="block text-xs text-gray-400">
                            المرحلة
                            <select v-model="leaveFilters.stage_id" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"><option value="">كل المراحل</option><option v-for="stage in stageOptions" :key="stage.id" :value="stage.id">{{ stage.name }}</option></select>
                        </label>
                        <label class="block text-xs text-gray-400">
                            الصف
                            <select v-model="leaveFilters.classroom_grade_name" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"><option value="">كل الصفوف</option><option v-for="grade in gradeOptions" :key="`grade-${grade}`" :value="grade">{{ grade }}</option></select>
                        </label>
                        <label class="block text-xs text-gray-400">
                            الفصل
                            <select v-model="leaveFilters.classroom_id" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"><option value="">كل الفصول</option><option v-for="classroom in classrooms" :key="classroom.id" :value="classroom.id">{{ classroom.grade_name }} - {{ classroom.name }}</option></select>
                        </label>
                        <label class="block text-xs text-gray-400">
                            الطالب
                            <select v-model="leaveFilters.school_student_id" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"><option value="">كل الطلاب</option><option v-for="student in students" :key="student.id" :value="student.id">{{ student.full_name }}</option></select>
                        </label>
                        <label class="block text-xs text-gray-400">
                            نوع الإجازة
                            <select v-model="leaveFilters.school_leave_type_id" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"><option value="">كل الأنواع</option><option v-for="typeItem in leaveTypes" :key="typeItem.id" :value="typeItem.id">{{ typeItem.name }}</option></select>
                        </label>
                        <label class="block text-xs text-gray-400">
                            الحالة
                            <select v-model="leaveFilters.status" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"><option v-for="status in statusOptions" :key="status.value" :value="status.value">{{ status.label }}</option></select>
                        </label>
                        <label class="block text-xs text-gray-400">
                            المصدر
                            <select v-model="leaveFilters.source" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"><option v-for="source in sourceOptions" :key="source.value" :value="source.value">{{ source.label }}</option></select>
                        </label>
                        <label class="block text-xs text-gray-400">
                            من تاريخ
                            <input v-model="leaveFilters.date_from" type="date" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm" />
                        </label>
                        <label class="block text-xs text-gray-400">
                            إلى تاريخ
                            <input v-model="leaveFilters.date_to" type="date" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm" />
                        </label>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="rounded bg-blue-700 px-3 py-2 text-sm font-bold hover:bg-blue-600" @click="loadLeaves">تحديث النتائج</button>
                        <button type="button" class="rounded bg-gray-700 px-3 py-2 text-sm font-bold hover:bg-gray-600" @click="resetLeaveFilters">إعادة تعيين الفلاتر</button>
                    </div>
                </div>

                <div class="mt-4 rounded-lg border border-gray-800 bg-gray-950/40 p-3">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-gray-200">نتائج الطلبات</h3>
                        <span class="text-xs text-gray-400">عدد النتائج: {{ leaves.length }}</span>
                    </div>
                    <p v-if="leaveError" class="mb-2 text-xs text-red-400">{{ leaveError }}</p>
                    <div class="space-y-2">
                        <div
                            v-for="leave in leaves"
                            :key="leave.id"
                            class="stage-row-accent rounded border border-gray-700 bg-gray-800 p-3"
                            :style="leaveStageStyle(leave)"
                        >
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <p>
                                    {{ leave.student?.full_name || '-' }}
                                    <span class="text-xs text-gray-400">| {{ leave.leave_type?.name || '-' }}</span>
                                    <span class="text-xs text-gray-400">| {{ leave.start_date }} إلى {{ leave.end_date }}</span>
                                </p>
                                <span v-if="leaveStageName(leave)" class="stage-badge" :style="leaveStageStyle(leave)">
                                    {{ leaveStageName(leave) }}
                                </span>
                                <div class="flex items-center gap-2">
                                    <span class="rounded px-2 py-1 text-[10px]" :class="statusBadgeClass(leave.status)">{{ statusOptions.find((x) => x.value === leave.status)?.label || leave.status }}</span>
                                    <button v-if="leave.status === 'PENDING'" type="button" class="rounded bg-emerald-700 px-2 py-1 text-xs hover:bg-emerald-600" @click="approveLeave(leave)">اعتماد</button>
                                    <button v-if="leave.status === 'PENDING'" type="button" class="rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600" @click="rejectLeave(leave)">رفض</button>
                                    <button v-if="leave.status === 'PENDING'" type="button" class="rounded bg-blue-700 px-2 py-1 text-xs hover:bg-blue-600" @click="setLeaveFormForEdit(leave)">تعديل</button>
                                    <button v-if="leave.status === 'PENDING' || leave.status === 'APPROVED'" type="button" class="rounded bg-gray-700 px-2 py-1 text-xs hover:bg-gray-600" @click="cancelLeave(leave)">إلغاء</button>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 text-xs text-gray-300">
                                <span>عدد المرفقات: {{ leave.attachments_count || 0 }}</span>
                                <label class="inline-flex items-center gap-2">
                                    <span>مرفق جديد:</span>
                                    <input type="file" class="text-[11px]" @change="setAttachmentFile(leave.id, $event)" />
                                </label>
                                <button type="button" class="rounded bg-blue-700 px-2 py-1 text-[11px] hover:bg-blue-600 disabled:opacity-60" :disabled="attachmentUploadingFor === leave.id" @click="uploadAttachment(leave)">رفع مرفق</button>
                            </div>
                        </div>
                        <p v-if="leaves.length === 0" class="rounded border border-dashed border-gray-700 p-4 text-sm text-gray-400">لا توجد طلبات إجازة مطابقة للفلاتر الحالية.</p>
                    </div>
                </div>
            </section>

            <section v-if="!canManageLeaves" class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm text-amber-200">
                ليس لديك صلاحية إدارة إجازات الطلاب في هذه المدرسة.
            </section>
        </div>
    </RoleLayout>
</template>

