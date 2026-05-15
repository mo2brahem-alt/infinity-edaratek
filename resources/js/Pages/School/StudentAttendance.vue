<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import RoleLayout from '@/Layouts/RoleLayout.vue';
import AppInlineAlert from '@/Components/AppInlineAlert.vue';
import AttachmentPanel from '@/Components/AttachmentPanel.vue';
import AppModal from '@/Components/AppModal.vue';
import { stageAccentStyle } from '@/utils/stagePalette';
import { useActionDialog } from '@/composables/useActionDialog';

const props = defineProps({
    school: {
        type: Object,
        default: null,
    },
    stages: {
        type: Array,
        default: () => [],
    },
    students: {
        type: Array,
        default: () => [],
    },
    dailyAttachments: {
        type: Array,
        default: () => [],
    },
    selectedDate: {
        type: String,
        default: '',
    },
    selectedStageId: {
        type: [Number, String, null],
        default: null,
    },
    selectedClassroomId: {
        type: [Number, String, null],
        default: null,
    },
    selectedClassroomGradeName: {
        type: [String, null],
        default: null,
    },
    reportDateFrom: {
        type: String,
        default: '',
    },
    reportDateTo: {
        type: String,
        default: '',
    },
    attendanceReport: {
        type: Object,
        default: () => ({}),
    },
    reportFilters: {
        type: Object,
        default: () => ({ day_type: null, holiday_name: '', leave_type_id: null }),
    },
    reportDayTypeOptions: {
        type: Array,
        default: () => [],
    },
    reportLeaveTypeOptions: {
        type: Array,
        default: () => [],
    },
    dayState: {
        type: Object,
        default: () => ({ day_type: 'SCHOOL_DAY', holiday_name: null }),
    },
    statusOptions: {
        type: Array,
        default: () => [],
    },
    isManager: {
        type: Boolean,
        default: false,
    },
    permissions: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const actionDialog = useActionDialog();
const currentUser = computed(() => page.props.auth?.user || null);
const pendingAttachments = ref([]);
const dailyAttendanceModalOpen = ref(false);
const isAttendanceLoading = ref(false);
const attendanceSaveNotice = ref('');
const roleForLayout = computed(() => {
    if (props.isManager) return 'SCHOOL_MANAGER';
    return currentUser.value?.primary_role === 'school_manager' ? 'SCHOOL_MANAGER' : 'STAFF';
});

const attendanceFieldLabels = {
    attendance_date: 'تاريخ الحضور',
    school_stage_id: 'المرحلة',
    classroom_grade_name: 'الصف',
    school_classroom_id: 'الفصل',
    school_student_id: 'الطالب',
    status: 'الحالة',
    check_in_time: 'وقت الحضور',
    check_out_time: 'وقت الانصراف',
    permission_reason: 'سبب الإذن',
    notes: 'الملاحظات',
    records: 'سجلات الحضور',
    attachments: 'مرفقات الحضور',
};

const toReadableFieldLabel = (field) => attendanceFieldLabels[field] || field;

const normalizeApiMessage = (message, field = '') => {
    if (!message || typeof message !== 'string') {
        return '';
    }

    const msg = message.trim();

    const required = msg.match(/^The (.+?) field is required\.$/i);
    if (required) return `حقل ${toReadableFieldLabel(required[1])} مطلوب.`;

    const invalid = msg.match(/^The selected (.+?) is invalid\.$/i);
    if (invalid) return `القيمة المحددة في حقل ${toReadableFieldLabel(invalid[1])} غير صالحة.`;

    const validDate = msg.match(/^The (.+?) is not a valid date\.$/i);
    if (validDate) return `حقل ${toReadableFieldLabel(validDate[1])} يجب أن يكون تاريخًا صحيحًا.`;

    const afterOrEqual = msg.match(/^The (.+?) field must be a date after or equal to (.+)\.$/i);
    if (afterOrEqual) return `حقل ${toReadableFieldLabel(afterOrEqual[1])} يجب أن يكون بعد أو مساويًا لـ ${toReadableFieldLabel(afterOrEqual[2])}.`;

    const beforeOrEqual = msg.match(/^The (.+?) field must be a date before or equal to (.+)\.$/i);
    if (beforeOrEqual) return `حقل ${toReadableFieldLabel(beforeOrEqual[1])} يجب أن يكون قبل أو مساويًا لـ ${toReadableFieldLabel(beforeOrEqual[2])}.`;

    const dateFormat = msg.match(/^The (.+?) does not match the format (.+)\.$/i);
    if (dateFormat) return `تنسيق ${toReadableFieldLabel(dateFormat[1])} غير صحيح.`;

    if (msg === 'Unauthorized.' || msg === 'This action is unauthorized.' || msg === 'Forbidden.') {
        return 'ليس لديك صلاحية لتنفيذ هذا الإجراء.';
    }

    return msg;
};

const defaultAttendanceDate = () => new Date().toISOString().slice(0, 10);

const normalizeGradeName = (value) => {
    const normalized = String(value || '').trim();
    return normalized !== '' ? normalized : 'غير محدد';
};

const stageAccent = (stageId, stageName = '') => stageAccentStyle(stageId, stageName);

const stageOptions = computed(() =>
    props.stages.map((stage) => ({
        id: stage.id,
        name: stage.name,
        grades: (stage.grades || []).map((grade) => normalizeGradeName(grade.name)),
        classrooms: (stage.classrooms || []).map((classroom) => ({
            ...classroom,
            grade_name: normalizeGradeName(classroom.grade_name),
        })),
    }))
);

const filterForm = ref({
    attendance_date: props.selectedDate || defaultAttendanceDate(),
    stage_id: props.selectedStageId || '',
    classroom_grade_name: props.selectedClassroomGradeName || '',
    classroom_id: props.selectedClassroomId || '',
    report_date_from: props.reportDateFrom || '',
    report_date_to: props.reportDateTo || '',
    report_day_type: props.reportFilters?.day_type || '',
    report_holiday_name: props.reportFilters?.holiday_name || '',
    report_leave_type_id: props.reportFilters?.leave_type_id || '',
});

const selectedStageName = computed(() => (
    stageOptions.value.find((item) => Number(item.id) === Number(filterForm.value.stage_id))?.name || ''
));

const gradeOptionsForSelectedStage = computed(() => {
    const stage = stageOptions.value.find((item) => Number(item.id) === Number(filterForm.value.stage_id));
    if (!stage) return [];

    const configuredGrades = [...new Set((stage.grades || []).map((grade) => normalizeGradeName(grade)))];
    if (configuredGrades.length > 0) {
        return configuredGrades;
    }

    const classroomGrades = (stage.classrooms || []).map((classroom) => normalizeGradeName(classroom.grade_name));
    return [...new Set(classroomGrades)];
});

const classroomsForSelectedStage = computed(() => {
    const stage = stageOptions.value.find((item) => Number(item.id) === Number(filterForm.value.stage_id));
    if (!stage) return [];

    let rows = stage.classrooms || [];
    if (filterForm.value.classroom_grade_name) {
        rows = rows.filter((classroom) => normalizeGradeName(classroom.grade_name) === normalizeGradeName(filterForm.value.classroom_grade_name));
    }

    return rows;
});

watch(
    () => filterForm.value.stage_id,
    () => {
        const gradeOptions = gradeOptionsForSelectedStage.value;
        const currentGrade = normalizeGradeName(filterForm.value.classroom_grade_name);

        if (filterForm.value.classroom_grade_name && !gradeOptions.includes(currentGrade)) {
            filterForm.value.classroom_grade_name = '';
        }

        const classroomIds = classroomsForSelectedStage.value.map((item) => Number(item.id));
        if (!classroomIds.includes(Number(filterForm.value.classroom_id))) {
            filterForm.value.classroom_id = classroomsForSelectedStage.value[0]?.id || '';
        }
    }
);

watch(
    () => filterForm.value.classroom_grade_name,
    () => {
        const classroomIds = classroomsForSelectedStage.value.map((item) => Number(item.id));
        if (!classroomIds.includes(Number(filterForm.value.classroom_id))) {
            filterForm.value.classroom_id = classroomsForSelectedStage.value[0]?.id || '';
        }
    }
);

const leaveReasonFor = (student) => {
    const leaveTypeName = student?.leave_state?.leave_type?.name;
    if (leaveTypeName) {
        return `إجازة معتمدة: ${leaveTypeName}`;
    }

    return 'إجازة معتمدة';
};

const buildRecords = () =>
    (props.students || []).map((student) => ({
        school_student_id: student.id,
        status: student.attendance?.status || (student.leave_state ? 'LEAVE' : 'PRESENT'),
        check_in_time: student.attendance?.check_in_time || '',
        check_out_time: student.attendance?.check_out_time || '',
        permission_reason: student.attendance?.permission_reason || (student.leave_state ? leaveReasonFor(student) : ''),
        notes: student.attendance?.notes || '',
        __leave_request_id: student.attendance?.school_student_leave_request_id || student.leave_state?.leave_request_id || null,
    }));

const attendanceForm = useForm({
    attendance_date: filterForm.value.attendance_date,
    school_stage_id: filterForm.value.stage_id || null,
    classroom_grade_name: filterForm.value.classroom_grade_name || null,
    school_classroom_id: filterForm.value.classroom_id || null,
    records: buildRecords(),
    attachments: [],
});

const attendanceAttachmentUploadForm = useForm({
    attendance_date: filterForm.value.attendance_date,
    school_stage_id: filterForm.value.stage_id || null,
    classroom_grade_name: filterForm.value.classroom_grade_name || null,
    school_classroom_id: filterForm.value.classroom_id || null,
    report_date_from: filterForm.value.report_date_from || null,
    report_date_to: filterForm.value.report_date_to || null,
    report_day_type: filterForm.value.report_day_type || null,
    report_holiday_name: filterForm.value.report_holiday_name || null,
    report_leave_type_id: filterForm.value.report_leave_type_id || null,
    attachments: [],
});

const showExceptionsOnly = ref(false);
const selectedStudentIds = ref([]);
const bulkPermissionReason = ref('');

const syncFromProps = () => {
    filterForm.value.attendance_date = props.selectedDate || defaultAttendanceDate();
    filterForm.value.stage_id = props.selectedStageId || '';
    filterForm.value.classroom_grade_name = props.selectedClassroomGradeName || '';
    filterForm.value.classroom_id = props.selectedClassroomId || '';
    filterForm.value.report_date_from = props.reportDateFrom || '';
    filterForm.value.report_date_to = props.reportDateTo || '';
    filterForm.value.report_day_type = props.reportFilters?.day_type || '';
    filterForm.value.report_holiday_name = props.reportFilters?.holiday_name || '';
    filterForm.value.report_leave_type_id = props.reportFilters?.leave_type_id || '';

    attendanceForm.attendance_date = filterForm.value.attendance_date;
    attendanceForm.school_stage_id = filterForm.value.stage_id || null;
    attendanceForm.classroom_grade_name = filterForm.value.classroom_grade_name || null;
    attendanceForm.school_classroom_id = filterForm.value.classroom_id || null;
    attendanceForm.records = buildRecords();
    attendanceForm.attachments = [];
    attendanceForm.clearErrors();
    attendanceAttachmentUploadForm.attendance_date = filterForm.value.attendance_date;
    attendanceAttachmentUploadForm.school_stage_id = filterForm.value.stage_id || null;
    attendanceAttachmentUploadForm.classroom_grade_name = filterForm.value.classroom_grade_name || null;
    attendanceAttachmentUploadForm.school_classroom_id = filterForm.value.classroom_id || null;
    attendanceAttachmentUploadForm.report_date_from = filterForm.value.report_date_from || null;
    attendanceAttachmentUploadForm.report_date_to = filterForm.value.report_date_to || null;
    attendanceAttachmentUploadForm.report_day_type = filterForm.value.report_day_type || null;
    attendanceAttachmentUploadForm.report_holiday_name = filterForm.value.report_holiday_name || null;
    attendanceAttachmentUploadForm.report_leave_type_id = filterForm.value.report_leave_type_id || null;
    attendanceAttachmentUploadForm.attachments = [];
    attendanceAttachmentUploadForm.clearErrors();
    selectedStudentIds.value = [];
    pendingAttachments.value = [];
};

watch(
    () => [
        props.selectedDate,
        props.selectedStageId,
        props.selectedClassroomId,
        props.reportDateFrom,
        props.reportDateTo,
        props.reportFilters,
        props.students,
    ],
    () => syncFromProps(),
    { deep: true }
);

syncFromProps();

const applyFilters = () => {
    const keepDailyModalOpen = dailyAttendanceModalOpen.value;
    if (keepDailyModalOpen) {
        isAttendanceLoading.value = true;
        attendanceSaveNotice.value = '';
    }

    router.get(
        route('school.student_attendance.index'),
        {
            attendance_date: filterForm.value.attendance_date || undefined,
            stage_id: filterForm.value.stage_id || undefined,
            classroom_grade_name: filterForm.value.classroom_grade_name || undefined,
            classroom_id: filterForm.value.classroom_id || undefined,
            report_date_from: filterForm.value.report_date_from || undefined,
            report_date_to: filterForm.value.report_date_to || undefined,
            report_day_type: filterForm.value.report_day_type || undefined,
            report_holiday_name: filterForm.value.report_holiday_name || undefined,
            report_leave_type_id: filterForm.value.report_leave_type_id || undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onFinish: () => {
                isAttendanceLoading.value = false;
            },
        }
    );
};

const resetDailyFilters = () => {
    filterForm.value.attendance_date = defaultAttendanceDate();
    filterForm.value.stage_id = '';
    filterForm.value.classroom_grade_name = '';
    filterForm.value.classroom_id = '';
    applyFilters();
};

const resetReportFilters = () => {
    filterForm.value.report_date_from = '';
    filterForm.value.report_date_to = '';
    filterForm.value.report_day_type = '';
    filterForm.value.report_holiday_name = '';
    filterForm.value.report_leave_type_id = '';
    applyFilters();
};

const statusLabel = (status) => {
    const item = (props.statusOptions || []).find((option) => option.value === status);
    return item?.label || status || '-';
};

const isSelectedStudent = (studentId) => selectedStudentIds.value.includes(Number(studentId));
const isAbsent = (index) => attendanceForm.records[index]?.status === 'ABSENT';
const isExcused = (index) => attendanceForm.records[index]?.status === 'EXCUSED';
const isLeave = (index) => attendanceForm.records[index]?.status === 'LEAVE';
const hasActiveLeave = (index) => Boolean(props.students[index]?.leave_state);
const statusOptionsFor = (index) => {
    const options = props.statusOptions || [];
    if (hasActiveLeave(index)) {
        return options;
    }

    const filtered = options.filter((option) => option.value !== 'LEAVE');
    if (attendanceForm.records[index]?.status !== 'LEAVE') {
        return filtered;
    }

    const leaveOption = options.find((option) => option.value === 'LEAVE');
    if (!leaveOption) {
        return filtered;
    }

    return [...filtered, leaveOption];
};

const attendanceRows = computed(() => {
    const rows = (props.students || []).map((student, index) => ({
        ...student,
        __index: index,
    }));

    if (!showExceptionsOnly.value) {
        return rows;
    }

    return rows.filter((row) => {
        if (row.leave_state) {
            return true;
        }

        return (attendanceForm.records[row.__index]?.status || 'PRESENT') !== 'PRESENT';
    });
});

const selectableStudentIds = computed(() =>
    attendanceRows.value
        .filter((row) => !row.leave_state)
        .map((row) => Number(row.id))
);

const selectedEditableIndexes = computed(() =>
    attendanceRows.value
        .filter((row) => isSelectedStudent(row.id) && !row.leave_state)
        .map((row) => row.__index)
);

const selectedCount = computed(() => selectedEditableIndexes.value.length);
const allSelectableRowsSelected = computed(
    () => selectableStudentIds.value.length > 0
        && selectableStudentIds.value.every((studentId) => isSelectedStudent(studentId))
);

const clearSelection = () => {
    selectedStudentIds.value = [];
};

const toggleSelectAllVisible = () => {
    if (allSelectableRowsSelected.value) {
        clearSelection();
        return;
    }

    selectedStudentIds.value = [...selectableStudentIds.value];
};

const toggleStudentSelection = (studentId, checked) => {
    const normalizedId = Number(studentId);
    if (Number.isNaN(normalizedId)) {
        return;
    }

    if (checked) {
        if (!isSelectedStudent(normalizedId)) {
            selectedStudentIds.value = [...selectedStudentIds.value, normalizedId];
        }
        return;
    }

    selectedStudentIds.value = selectedStudentIds.value.filter((id) => id !== normalizedId);
};

const onStatusChange = (index) => {
    if (hasActiveLeave(index)) {
        const student = props.students[index];
        attendanceForm.records[index].status = 'LEAVE';
        attendanceForm.records[index].check_in_time = '';
        attendanceForm.records[index].check_out_time = '';
        attendanceForm.records[index].permission_reason = leaveReasonFor(student);
        attendanceForm.records[index].__leave_request_id = student?.leave_state?.leave_request_id || null;
        return;
    }

    if (isLeave(index)) {
        attendanceForm.records[index].status = 'ABSENT';
    }

    if (isAbsent(index)) {
        attendanceForm.records[index].check_in_time = '';
        attendanceForm.records[index].check_out_time = '';
        attendanceForm.records[index].permission_reason = '';
        attendanceForm.records[index].__leave_request_id = null;
        return;
    }

    attendanceForm.records[index].__leave_request_id = null;

    if (!isExcused(index)) {
        attendanceForm.records[index].permission_reason = '';
    }
};

const applyBulkStatus = async (status) => {
    if (selectedEditableIndexes.value.length === 0) {
        return;
    }

    const normalizedStatus = String(status || '').toUpperCase();

    if (normalizedStatus === 'EXCUSED' && bulkPermissionReason.value.trim() === '') {
        await actionDialog.alert({
            title: 'سبب الإذن مطلوب',
            message: 'يرجى إدخال سبب الإذن قبل تطبيقه على الطلاب المحددين.',
            confirmText: 'حسنًا',
            variant: 'warning',
        });
        return;
    }

    selectedEditableIndexes.value.forEach((index) => {
        attendanceForm.records[index].status = normalizedStatus;

        if (normalizedStatus === 'EXCUSED') {
            attendanceForm.records[index].permission_reason = bulkPermissionReason.value.trim();
        }

        onStatusChange(index);
    });

    clearSelection();
};

const appendAttachmentFiles = (fileList) => {
    const incoming = Array.from(fileList || []).filter((file) => file instanceof File);
    if (incoming.length === 0) {
        return;
    }

    const merged = [...pendingAttachments.value, ...incoming];
    pendingAttachments.value = merged.slice(0, 10);
};

const removePendingAttachment = (index) => {
    pendingAttachments.value = pendingAttachments.value.filter((_, itemIndex) => itemIndex !== index);
};

const clearPendingAttachments = () => {
    pendingAttachments.value = [];
    attendanceAttachmentUploadForm.attachments = [];
};

const formatFileSize = (size) => {
    const bytes = Number(size || 0);
    if (!Number.isFinite(bytes) || bytes <= 0) {
        return '0 ب';
    }

    if (bytes < 1024) {
        return `${bytes} ب`;
    }

    const kb = bytes / 1024;
    if (kb < 1024) {
        return `${kb.toFixed(1)} ك.ب`;
    }

    const mb = kb / 1024;
    return `${mb.toFixed(1)} م.ب`;
};

const deleteDailyAttachment = async (attachment) => {
    if (!attachment?.id) {
        return;
    }

    const confirmed = await actionDialog.confirm({
        title: 'حذف المرفق',
        message: 'سيتم حذف هذا المرفق من سجل اليوم الحالي. هل تريد المتابعة؟',
        confirmText: 'نعم، احذف المرفق',
        cancelText: 'إلغاء',
        variant: 'danger',
    });

    if (!confirmed) {
        return;
    }

    router.delete(route('school.student_attendance.attachments.destroy', { schoolAttendanceAttachment: attachment.id }), {
        data: {
            attendance_date: filterForm.value.attendance_date || undefined,
            stage_id: filterForm.value.stage_id || undefined,
            classroom_grade_name: filterForm.value.classroom_grade_name || undefined,
        },
        preserveScroll: true,
        preserveState: true,
    });
};

const rowError = (index, field) => normalizeApiMessage(attendanceForm.errors[`records.${index}.${field}`] || '', field);
const formError = (field) => normalizeApiMessage(attendanceForm.errors[field] || '', field);
const uploadFormError = (field) => normalizeApiMessage(attendanceAttachmentUploadForm.errors[field] || '', field);
const canUploadDailyAttachments = computed(() => Boolean(filterForm.value.attendance_date && filterForm.value.classroom_id));
const hasPendingAttendanceAttachments = computed(() => pendingAttachments.value.length > 0);
const attendanceAttachmentErrors = computed(() => (
    [
        formError('attachments'),
        formError('attachments.0'),
        uploadFormError('attachments'),
        uploadFormError('attachments.0'),
    ].filter(Boolean)
));

const submitDailyAttachmentsOnly = () => {
    if (!canUploadDailyAttachments.value) {
        attendanceAttachmentUploadForm.setError('attachments', 'اختر تاريخ الحضور والفصل أولًا قبل رفع المرفقات.');
        return;
    }

    if (!hasPendingAttendanceAttachments.value) {
        attendanceAttachmentUploadForm.setError('attachments', 'يرجى اختيار مرفق واحد على الأقل.');
        return;
    }

    attendanceAttachmentUploadForm.clearErrors();
    attendanceAttachmentUploadForm.attendance_date = filterForm.value.attendance_date || null;
    attendanceAttachmentUploadForm.school_stage_id = filterForm.value.stage_id || null;
    attendanceAttachmentUploadForm.classroom_grade_name = filterForm.value.classroom_grade_name || null;
    attendanceAttachmentUploadForm.school_classroom_id = filterForm.value.classroom_id || null;
    attendanceAttachmentUploadForm.report_date_from = filterForm.value.report_date_from || null;
    attendanceAttachmentUploadForm.report_date_to = filterForm.value.report_date_to || null;
    attendanceAttachmentUploadForm.report_day_type = filterForm.value.report_day_type || null;
    attendanceAttachmentUploadForm.report_holiday_name = filterForm.value.report_holiday_name || null;
    attendanceAttachmentUploadForm.report_leave_type_id = filterForm.value.report_leave_type_id || null;
    attendanceAttachmentUploadForm.attachments = [...pendingAttachments.value];

    attendanceAttachmentUploadForm.post(route('school.student_attendance.attachments.store'), {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            pendingAttachments.value = [];
            attendanceAttachmentUploadForm.attachments = [];
            attendanceSaveNotice.value = 'تم رفع مرفقات الحضور بنجاح.';
        },
    });
};

const summary = computed(() => {
    const totals = {
        PRESENT: 0,
        ABSENT: 0,
        EXCUSED: 0,
        LEAVE: 0,
    };

    for (const row of attendanceForm.records || []) {
        const status = row.status || 'PRESENT';
        if (Object.prototype.hasOwnProperty.call(totals, status)) {
            totals[status] += 1;
        }
    }

    return totals;
});

const emptyReportTotals = {
    recorded_days: 0,
    present_days: 0,
    excused_days: 0,
    leave_days: 0,
    absent_days: 0,
    unexcused_absence_days: 0,
};

const reportTotals = computed(() => ({
    ...emptyReportTotals,
    ...(props.attendanceReport?.totals || {}),
}));

const reportRows = computed(() => props.attendanceReport?.per_student || []);

const hasIncompleteReportRange = computed(() => {
    const from = (filterForm.value.report_date_from || '').trim();
    const to = (filterForm.value.report_date_to || '').trim();

    return (from !== '' && to === '') || (from === '' && to !== '');
});

const isReportRangeMissing = computed(() => {
    const from = (filterForm.value.report_date_from || '').trim();
    const to = (filterForm.value.report_date_to || '').trim();

    return from === '' && to === '';
});

const hasInvalidReportRange = computed(() => {
    const from = (filterForm.value.report_date_from || '').trim();
    const to = (filterForm.value.report_date_to || '').trim();

    if (from === '' || to === '') {
        return false;
    }

    return from > to;
});

const hasRequiredDailyFilters = computed(() => Boolean(filterForm.value.attendance_date) && Boolean(filterForm.value.stage_id) && Boolean(filterForm.value.classroom_id));

const canExportReport = computed(
    () => Boolean(filterForm.value.classroom_id) && !hasInvalidReportRange.value && !hasIncompleteReportRange.value
);
const canSubmitAttendance = computed(
    () => hasRequiredDailyFilters.value && attendanceForm.records.length > 0 && !attendanceForm.processing && !isAttendanceLoading.value
);
const isNonSchoolDay = computed(() => props.dayState?.day_type === 'HOLIDAY' || props.dayState?.day_type === 'WEEKLY_OFF');

const dayStateBadge = computed(() => {
    if (props.dayState?.day_type === 'HOLIDAY') {
        return {
            text: `عطلة رسمية${props.dayState?.holiday_name ? `: ${props.dayState.holiday_name}` : ''}`,
            className: 'border-amber-500/40 bg-amber-500/10 text-amber-200',
        };
    }

    if (props.dayState?.day_type === 'WEEKLY_OFF') {
        return {
            text: 'إجازة أسبوعية (غير يوم دراسي)',
            className: 'border-sky-500/40 bg-sky-500/10 text-sky-200',
        };
    }

    return null;
});

const exportHint = computed(() => {
    if (!filterForm.value.classroom_id) {
        return 'اختر المرحلة والفصل أولًا قبل تصدير التقرير.';
    }

    if (hasInvalidReportRange.value) {
        return 'تاريخ البداية يجب أن يكون قبل أو يساوي تاريخ النهاية.';
    }

    if (isReportRangeMissing.value) {
        const rangeFrom = props.attendanceReport?.range?.from || '-';
        const rangeTo = props.attendanceReport?.range?.to || '-';
        return `لم يتم تحديد فترة مخصصة؛ سيتم التصدير حسب النطاق الحالي (${rangeFrom} إلى ${rangeTo}).`;
    }

    if (hasIncompleteReportRange.value) {
        return 'أدخل تاريخ البداية والنهاية معًا للحصول على فترة دقيقة، أو اتركهما فارغين لاستخدام النطاق الافتراضي.';
    }

    return '';
});

const exportHintClass = computed(() => {
    if (hasInvalidReportRange.value) {
        return 'text-red-300';
    }

    if (!filterForm.value.classroom_id || hasIncompleteReportRange.value) {
        return 'text-amber-300';
    }

    if (isReportRangeMissing.value) {
        return 'text-sky-300';
    }

    return 'text-gray-400';
});

const applyFiltersHint = computed(() => {
    if (!filterForm.value.attendance_date) {
        return 'اختر تاريخ اليوم الدراسي أولًا.';
    }

    if (!filterForm.value.stage_id) {
        return 'اختر المرحلة لعرض الطلاب بشكل صحيح.';
    }

    if (!filterForm.value.classroom_id) {
        return 'اختر الفصل قبل تحديث العرض أو حفظ الحضور.';
    }

    return '';
});

const applyFiltersHintClass = computed(() => {
    if (applyFiltersHint.value) return 'text-amber-300';
    return 'text-gray-400';
});

const reportFiltersHint = computed(() => {
    if (!filterForm.value.classroom_id) {
        return 'حدد المرحلة والفصل في فلاتر الحضور اليومي أولًا لعرض التقرير.';
    }

    if (hasInvalidReportRange.value) {
        return 'فترة التقرير غير صحيحة: تاريخ البداية يجب أن يكون قبل أو مساويًا لتاريخ النهاية.';
    }

    if (hasIncompleteReportRange.value) {
        return 'لفترة تقرير دقيقة، أدخل تاريخ البداية والنهاية معًا أو اتركهما فارغين لاستخدام النطاق الافتراضي.';
    }

    return '';
});

const reportFiltersHintClass = computed(() => {
    if (hasInvalidReportRange.value) return 'text-red-300';
    if (reportFiltersHint.value) return 'text-amber-300';
    return 'text-gray-400';
});

const exportReport = () => {
    if (!canExportReport.value) {
        return;
    }

    const href = route('school.student_attendance.report.export', {
        attendance_date: filterForm.value.attendance_date || undefined,
        school_stage_id: filterForm.value.stage_id || undefined,
        classroom_grade_name: filterForm.value.classroom_grade_name || undefined,
        school_classroom_id: filterForm.value.classroom_id || undefined,
        report_date_from: filterForm.value.report_date_from || undefined,
        report_date_to: filterForm.value.report_date_to || undefined,
        report_day_type: filterForm.value.report_day_type || undefined,
        report_holiday_name: filterForm.value.report_holiday_name || undefined,
        report_leave_type_id: filterForm.value.report_leave_type_id || undefined,
    });

    window.location.assign(href);
};

const submitAttendance = () => {
    attendanceSaveNotice.value = '';
    attendanceForm.attendance_date = filterForm.value.attendance_date;
    attendanceForm.school_stage_id = filterForm.value.stage_id || null;
    attendanceForm.classroom_grade_name = filterForm.value.classroom_grade_name || null;
    attendanceForm.school_classroom_id = filterForm.value.classroom_id || null;
    attendanceForm.attachments = [...pendingAttachments.value];

    attendanceForm.post(route('school.student_attendance.records.upsert'), {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            clearSelection();
            pendingAttachments.value = [];
            attendanceForm.attachments = [];
            attendanceSaveNotice.value = 'تم حفظ سجل الحضور اليومي بنجاح.';
        },
    });
};

const openDailyAttendanceModal = () => {
    dailyAttendanceModalOpen.value = true;
};

const closeDailyAttendanceModal = () => {
    dailyAttendanceModalOpen.value = false;
};
</script>

<template>
    <Head title="الحضور اليومي للطلاب" />

    <RoleLayout title="الحضور اليومي للطلاب" :role="roleForLayout" :permissions="props.permissions">
        <div v-if="!school" class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm text-amber-200">
            لا يوجد ربط لمدرسة لهذا الحساب حاليًا.
        </div>

        <div v-else class="space-y-6">
            <section class="rounded-xl border border-gray-800 bg-gray-900 p-4">
                <p class="text-xs text-gray-400">المدرسة</p>
                <p class="text-lg font-bold">{{ school.name }}</p>
                <p class="text-xs text-gray-500">{{ school.school_id }}</p>
            </section>

            <section class="rounded-xl border border-gray-800 bg-gray-900 p-4">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-lg font-bold">الحضور اليومي</h2>
                        <p class="mt-1 text-xs leading-6 text-gray-400">
                            افتح نافذة الحضور لاختيار التاريخ والمرحلة والصف والفصل، ثم عدّل الحالات الاستثنائية فقط.
                        </p>
                        <p class="mt-2 text-xs text-gray-500">
                            التاريخ الحالي: {{ filterForm.attendance_date || '-' }}
                            <span v-if="selectedStageName"> | المرحلة: {{ selectedStageName }}</span>
                            <span v-if="filterForm.classroom_id"> | الفصل: {{ classroomsForSelectedStage.find((item) => Number(item.id) === Number(filterForm.classroom_id))?.name || '-' }}</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg bg-blue-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-300"
                        @click="openDailyAttendanceModal"
                    >
                        الحضور اليومي
                    </button>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2 md:grid-cols-5">
                    <div class="rounded border border-gray-600/40 bg-gray-800/50 p-3 text-sm">
                        <p class="text-xs text-gray-300">إجمالي الطلاب</p>
                        <p class="text-xl font-bold text-gray-100">{{ attendanceForm.records.length }}</p>
                    </div>
                    <div class="rounded border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm">
                        <p class="text-xs text-emerald-200">حاضر</p>
                        <p class="text-xl font-bold text-emerald-100">{{ summary.PRESENT }}</p>
                    </div>
                    <div class="rounded border border-red-500/30 bg-red-500/10 p-3 text-sm">
                        <p class="text-xs text-red-200">غائب</p>
                        <p class="text-xl font-bold text-red-100">{{ summary.ABSENT }}</p>
                    </div>
                    <div class="rounded border border-amber-500/30 bg-amber-500/10 p-3 text-sm">
                        <p class="text-xs text-amber-200">مأذون</p>
                        <p class="text-xl font-bold text-amber-100">{{ summary.EXCUSED }}</p>
                    </div>
                    <div class="rounded border border-indigo-500/30 bg-indigo-500/10 p-3 text-sm">
                        <p class="text-xs text-indigo-200">إجازة</p>
                        <p class="text-xl font-bold text-indigo-100">{{ summary.LEAVE }}</p>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-gray-800 bg-gray-900 p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h2 class="text-lg font-bold">مرفقات توثيق الحضور</h2>
                        <p class="mt-1 text-xs leading-6 text-gray-400">
                            ارفع مستندات أو صورًا لتوثيق الحضور أو حالات الغياب لليوم والفصل المحددين. يمكنك رفعها الآن مباشرة، أو تركها ضمن القائمة ليتم حفظها مع سجل الحضور عند الحفظ.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg bg-sky-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-300"
                        @click="openDailyAttendanceModal"
                    >
                        فتح نافذة الحضور والمرفقات
                    </button>
                </div>

                <AppInlineAlert
                    v-if="!canUploadDailyAttachments"
                    class="mt-3"
                    variant="warning"
                    message="اختر تاريخ الحضور والمرحلة والفصل من نافذة الحضور اليومي أولًا، ثم سيظهر رفع المرفقات لهذا اليوم بشكل كامل."
                />

                <AttachmentPanel
                    class="mt-3"
                    title="مرفقات التوثيق"
                    :helper-text="canUploadDailyAttachments
                        ? 'المرفقات الحالية مرتبطة بنفس اليوم والفصل المحددين في الحضور اليومي.'
                        : 'بعد تحديد اليوم والفصل ستظهر هنا المرفقات المحفوظة، ويمكنك إضافة مرفقات جديدة.'"
                    :existing-attachments="props.dailyAttachments || []"
                    :pending-files="pendingAttachments"
                    :errors="attendanceAttachmentErrors"
                    accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx"
                    camera-accept="image/*"
                    :allow-camera="true"
                    :show-uploader="canUploadDailyAttachments"
                    :busy="attendanceForm.processing || attendanceAttachmentUploadForm.processing"
                    pending-title="مرفقات سيتم رفعها لليوم الحالي"
                    existing-title="المرفقات المحفوظة لهذا اليوم"
                    empty-text="لا توجد مرفقات محفوظة لهذا اليوم بعد."
                    @select-files="appendAttachmentFiles"
                    @remove-pending="removePendingAttachment"
                    @delete-existing="deleteDailyAttachment"
                />

                <div class="mt-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg bg-indigo-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-indigo-600 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="!canUploadDailyAttachments || !hasPendingAttendanceAttachments || attendanceAttachmentUploadForm.processing"
                            @click="submitDailyAttachmentsOnly"
                        >
                            {{ attendanceAttachmentUploadForm.processing ? 'جار رفع المرفقات...' : 'رفع المرفقات الآن' }}
                        </button>

                        <button
                            v-if="hasPendingAttendanceAttachments"
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg bg-gray-700 px-4 py-2 text-sm font-bold text-white transition hover:bg-gray-600"
                            :disabled="attendanceAttachmentUploadForm.processing"
                            @click="clearPendingAttachments"
                        >
                            مسح الملفات المختارة
                        </button>
                    </div>

                    <p class="text-xs text-gray-500">
                        يمكنك أيضًا حفظ الملفات المختارة مع سجل الحضور مباشرة من نافذة الحضور اليومية.
                    </p>
                </div>
            </section>

            <AppModal
                :open="dailyAttendanceModalOpen"
                title="الحضور اليومي"
                description="اختر التاريخ والمرحلة والصف والفصل، ثم غيّر الحالات الاستثنائية فقط واحفظ السجل الكامل."
                max-width-class="max-w-7xl"
                panel-class="rounded-2xl"
                body-class="bg-gray-950"
                @close="closeDailyAttendanceModal"
            >
                <div class="space-y-4">
            <section class="rounded-xl border border-gray-800 bg-gray-900 p-4">
                <h2 class="mb-1 text-lg font-bold">فلاتر الحضور اليومي</h2>
                <p class="mb-3 text-xs text-gray-400">اختر اليوم الدراسي والمرحلة والصف والفصل، ثم حدّث العرض لبدء تسجيل الحضور.</p>

                <div class="grid grid-cols-1 gap-2 md:grid-cols-4">
                    <label class="block text-xs text-gray-400">
                        التاريخ
                        <input v-model="filterForm.attendance_date" type="date" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm" />
                    </label>
                    <label class="block text-xs text-gray-400">
                        المرحلة
                        <select v-model="filterForm.stage_id" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm">
                            <option value="" disabled>اختر المرحلة</option>
                            <option v-for="stage in stageOptions" :key="stage.id" :value="stage.id">{{ stage.name }}</option>
                        </select>
                    </label>
                    <label class="block text-xs text-gray-400">
                        الصف
                        <select v-model="filterForm.classroom_grade_name" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm">
                            <option value="">كل الصفوف</option>
                            <option v-for="grade in gradeOptionsForSelectedStage" :key="`attendance-grade-${grade}`" :value="grade">{{ grade }}</option>
                        </select>
                    </label>
                    <label class="block text-xs text-gray-400">
                        الفصل
                        <select v-model="filterForm.classroom_id" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm">
                            <option value="" disabled>اختر الفصل</option>
                            <option v-for="classroom in classroomsForSelectedStage" :key="classroom.id" :value="classroom.id">
                                {{ classroom.grade_name ? `${classroom.grade_name} - ` : '' }}{{ classroom.name }}
                            </option>
                        </select>
                    </label>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="rounded bg-blue-700 px-3 py-2 text-sm font-bold hover:bg-blue-600 disabled:opacity-60"
                        :disabled="isAttendanceLoading"
                        @click="applyFilters"
                    >
                        {{ isAttendanceLoading ? 'جار تحميل الطلاب...' : 'تحميل الطلاب' }}
                    </button>
                    <button type="button" class="rounded bg-gray-700 px-3 py-2 text-sm font-bold hover:bg-gray-600" @click="resetDailyFilters">
                        إعادة ضبط فلاتر الحضور
                    </button>
                </div>

                <p v-if="applyFiltersHint" :class="['mt-2 text-xs', applyFiltersHintClass]">
                    {{ applyFiltersHint }}
                </p>
            </section>

            <section class="rounded-xl border border-gray-800 bg-gray-900 p-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-bold">سجل الحضور اليومي</h2>
                        <p class="text-xs text-gray-400">سجّل حالة كل طالب مع الالتزام بالإجازات المعتمدة والعطل الرسمية.</p>
                    </div>
                    <span class="rounded border border-gray-700 bg-gray-800 px-2 py-1 text-xs text-gray-300">
                        التاريخ: {{ filterForm.attendance_date || '-' }}
                    </span>
                </div>

                <p v-if="dayStateBadge" :class="['mb-3 rounded border px-3 py-2 text-xs font-semibold', dayStateBadge.className]">
                    {{ dayStateBadge.text }}
                </p>

                <AppInlineAlert
                    v-if="attendanceSaveNotice"
                    class="mb-3"
                    variant="success"
                    :message="attendanceSaveNotice"
                />

                <div v-if="isAttendanceLoading" class="mb-3 rounded border border-sky-500/30 bg-sky-500/10 px-3 py-2 text-xs text-sky-200">
                    جار تحميل طلاب الفصل وسجلات الحضور المحفوظة...
                </div>

                <div v-if="isNonSchoolDay" class="mb-3 rounded border border-sky-500/30 bg-sky-500/10 px-3 py-2 text-xs text-sky-200">
                    اليوم مصنف كـ غير يوم دراسي. راجع سياسات المدرسة قبل إدخال أي تعديلات يدوية على السجلات.
                </div>

                <div class="mb-3 grid grid-cols-1 gap-2 md:grid-cols-5">
                    <div class="rounded border border-gray-600/40 bg-gray-800/50 p-3 text-sm">
                        <p class="text-xs text-gray-300">إجمالي الطلاب</p>
                        <p class="text-xl font-bold text-gray-100">{{ attendanceForm.records.length }}</p>
                    </div>
                    <div class="rounded border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm">
                        <p class="text-xs text-emerald-200">حاضر</p>
                        <p class="text-xl font-bold text-emerald-100">{{ summary.PRESENT }}</p>
                    </div>
                    <div class="rounded border border-red-500/30 bg-red-500/10 p-3 text-sm">
                        <p class="text-xs text-red-200">غائب</p>
                        <p class="text-xl font-bold text-red-100">{{ summary.ABSENT }}</p>
                    </div>
                    <div class="rounded border border-amber-500/30 bg-amber-500/10 p-3 text-sm">
                        <p class="text-xs text-amber-200">مأذون</p>
                        <p class="text-xl font-bold text-amber-100">{{ summary.EXCUSED }}</p>
                    </div>
                    <div class="rounded border border-indigo-500/30 bg-indigo-500/10 p-3 text-sm">
                        <p class="text-xs text-indigo-200">إجازة</p>
                        <p class="text-xl font-bold text-indigo-100">{{ summary.LEAVE }}</p>
                    </div>
                </div>

                <div class="mb-3 rounded border border-gray-700/70 bg-gray-800/40 p-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <label class="inline-flex items-center gap-2 text-xs text-gray-300">
                            <input v-model="showExceptionsOnly" type="checkbox" class="rounded border-gray-600 bg-gray-900 text-blue-500 focus:ring-blue-400" />
                            عرض الاستثناءات فقط
                        </label>

                        <button
                            type="button"
                            class="rounded bg-gray-700 px-3 py-1.5 text-xs font-bold hover:bg-gray-600 disabled:opacity-60"
                            :disabled="selectableStudentIds.length === 0"
                            @click="toggleSelectAllVisible"
                        >
                            {{ allSelectableRowsSelected ? 'إلغاء تحديد الكل' : 'تحديد الكل' }}
                        </button>
                        <button
                            type="button"
                            class="rounded bg-gray-700 px-3 py-1.5 text-xs font-bold hover:bg-gray-600 disabled:opacity-60"
                            :disabled="selectedCount === 0"
                            @click="clearSelection"
                        >
                            مسح التحديد
                        </button>
                        <button
                            type="button"
                            class="rounded bg-red-700 px-3 py-1.5 text-xs font-bold hover:bg-red-600 disabled:opacity-60"
                            :disabled="selectedCount === 0"
                            @click="applyBulkStatus('ABSENT')"
                        >
                            تعيين غياب
                        </button>
                        <button
                            type="button"
                            class="rounded bg-emerald-700 px-3 py-1.5 text-xs font-bold hover:bg-emerald-600 disabled:opacity-60"
                            :disabled="selectedCount === 0"
                            @click="applyBulkStatus('PRESENT')"
                        >
                            تعيين حضور
                        </button>
                        <input
                            v-model="bulkPermissionReason"
                            type="text"
                            class="w-full min-w-0 rounded border border-gray-700 bg-gray-900 p-2 text-xs md:min-w-[220px] md:flex-1"
                            placeholder="سبب الإذن الجماعي"
                        />
                        <button
                            type="button"
                            class="rounded bg-amber-700 px-3 py-1.5 text-xs font-bold hover:bg-amber-600 disabled:opacity-60"
                            :disabled="selectedCount === 0 || bulkPermissionReason.trim() === ''"
                            @click="applyBulkStatus('EXCUSED')"
                        >
                            تعيين إذن
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">
                        الطلاب المحددون للتعديل: {{ selectedCount }}
                    </p>
                    <p class="mt-1 text-xs text-indigo-200">
                        الغياب بعذر يبقى ضمن نظام الإجازات المعتمد (Leave)، ولا يتم تعيينه يدويًا من سجل الحضور.
                    </p>
                </div>

                <div v-if="selectedStageName" class="mb-2">
                    <span class="stage-badge" :style="stageAccent(filterForm.stage_id, selectedStageName)">
                        {{ selectedStageName }}
                    </span>
                </div>

                <div v-if="!filterForm.classroom_id" class="rounded border border-dashed border-gray-700 p-4 text-sm text-gray-400">
                    اختر المرحلة والفصل لعرض قائمة الطلاب.
                </div>

                <div v-else-if="attendanceForm.records.length === 0" class="rounded border border-dashed border-gray-700 p-4 text-sm text-gray-400">
                    لا يوجد طلاب نشطون في الفصل المحدد.
                </div>

                <form v-else @submit.prevent="submitAttendance">
                    <div class="mb-2 text-xs text-gray-400">
                        راجع الحالة لكل طالب ثم احفظ السجلات. يتم تثبيت الحالة تلقائيًا عند وجود إجازة معتمدة.
                    </div>

                    <AttachmentPanel
                        class="mb-3"
                        title="مرفقات التوثيق"
                        helper-text="يمكنك رفع مستند أو صورة لتوثيق الحضور أو حالات الغياب. سيتم حفظ المرفقات مع سجل الحضور الحالي لنفس الفصل والتاريخ."
                        :existing-attachments="props.dailyAttachments || []"
                        :pending-files="pendingAttachments"
                        :errors="attendanceAttachmentErrors"
                        accept="image/*,application/pdf,.doc,.docx,.xls,.xlsx"
                        camera-accept="image/*"
                        :allow-camera="true"
                        :busy="attendanceForm.processing"
                        pending-title="مرفقات سيتم رفعها مع حفظ الحضور"
                        existing-title="مرفقات اليوم المحفوظة"
                        empty-text="لا توجد مرفقات محفوظة لهذا اليوم بعد."
                        @select-files="appendAttachmentFiles"
                        @remove-pending="removePendingAttachment"
                        @delete-existing="deleteDailyAttachment"
                    />

                    <div
                        v-if="showExceptionsOnly && attendanceRows.length === 0"
                        class="mb-3 rounded border border-dashed border-gray-700 p-3 text-xs text-gray-400"
                    >
                        لا توجد استثناءات حالية في الفصل المحدد.
                    </div>

                    <div class="space-y-3 lg:hidden">
                        <article
                            v-for="(row, rowPosition) in attendanceRows"
                            :key="`attendance-mobile-${row.id}`"
                            class="rounded-2xl border border-gray-700 bg-gray-900 p-4"
                            :style="stageAccent(filterForm.stage_id, selectedStageName)"
                        >
                            <div class="mb-4 flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold">{{ row.full_name }}</p>
                                    <p class="text-xs text-gray-500">الهوية: {{ row.national_id || '-' }}</p>
                                    <p class="text-xs text-gray-500">كود الطالب: {{ row.student_code || '-' }}</p>
                                </div>
                                <input
                                    type="checkbox"
                                    class="rounded border-gray-600 bg-gray-900 text-blue-500 focus:ring-blue-400 disabled:opacity-40"
                                    :checked="isSelectedStudent(row.id)"
                                    :disabled="hasActiveLeave(row.__index)"
                                    @change="toggleStudentSelection(row.id, $event.target.checked)"
                                />
                            </div>

                            <p
                                v-if="row.leave_state"
                                class="mb-3 inline-flex items-center rounded bg-indigo-900/60 px-2 py-0.5 text-[11px] text-indigo-200"
                            >
                                إجازة معتمدة {{ row.leave_state.start_date }} - {{ row.leave_state.end_date }}
                            </p>

                            <div class="grid grid-cols-1 gap-3">
                                <div>
                                    <label class="mb-1 block text-xs text-gray-400">الحالة</label>
                                    <select
                                        v-model="attendanceForm.records[row.__index].status"
                                        class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                                        :disabled="hasActiveLeave(row.__index)"
                                        @change="onStatusChange(row.__index)"
                                    >
                                        <option v-for="option in statusOptionsFor(row.__index)" :key="option.value" :value="option.value">
                                            {{ option.label }}
                                        </option>
                                    </select>
                                    <p class="mt-1 text-[11px] text-gray-500">{{ statusLabel(attendanceForm.records[row.__index].status) }}</p>
                                    <p v-if="rowError(row.__index, 'status')" class="mt-1 text-xs text-red-400">{{ rowError(row.__index, 'status') }}</p>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="mb-1 block text-xs text-gray-400">وقت الحضور</label>
                                        <input
                                            v-model="attendanceForm.records[row.__index].check_in_time"
                                            type="time"
                                            class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                                            :disabled="isAbsent(row.__index) || isLeave(row.__index)"
                                        />
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs text-gray-400">وقت الانصراف</label>
                                        <input
                                            v-model="attendanceForm.records[row.__index].check_out_time"
                                            type="time"
                                            class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                                            :disabled="isAbsent(row.__index) || isLeave(row.__index)"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs text-gray-400">سبب الإذن</label>
                                    <input
                                        v-model="attendanceForm.records[row.__index].permission_reason"
                                        class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                                        placeholder="سبب الإذن"
                                        :disabled="!isExcused(row.__index)"
                                    />
                                    <p v-if="rowError(row.__index, 'permission_reason')" class="mt-1 text-xs text-red-400">{{ rowError(row.__index, 'permission_reason') }}</p>
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs text-gray-400">ملاحظات</label>
                                    <input
                                        v-model="attendanceForm.records[row.__index].notes"
                                        class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                                        placeholder="ملاحظات"
                                    />
                                    <p v-if="rowError(row.__index, 'notes')" class="mt-1 text-xs text-red-400">{{ rowError(row.__index, 'notes') }}</p>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div class="hidden overflow-hidden rounded border border-gray-700 lg:block">
                        <table class="w-full text-right text-sm text-gray-200">
                            <thead class="bg-gray-800 text-xs text-gray-400">
                                <tr>
                                    <th class="px-3 py-2">تحديد</th>
                                    <th class="px-3 py-2">#</th>
                                    <th class="px-3 py-2">الطالب</th>
                                    <th class="px-3 py-2">كود الطالب</th>
                                    <th class="px-3 py-2">الحالة</th>
                                    <th class="px-3 py-2">وقت الحضور</th>
                                    <th class="px-3 py-2">وقت الانصراف</th>
                                    <th class="px-3 py-2">سبب الإذن</th>
                                    <th class="px-3 py-2">ملاحظات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700 bg-gray-900">
                                <tr
                                    v-for="(row, rowPosition) in attendanceRows"
                                    :key="row.id"
                                    class="stage-row-accent"
                                    :style="stageAccent(filterForm.stage_id, selectedStageName)"
                                    :class="row.leave_state ? 'bg-indigo-950/10' : ''"
                                >
                                    <td class="px-3 py-2 text-center">
                                        <input
                                            type="checkbox"
                                            class="rounded border-gray-600 bg-gray-900 text-blue-500 focus:ring-blue-400 disabled:opacity-40"
                                            :checked="isSelectedStudent(row.id)"
                                            :disabled="hasActiveLeave(row.__index)"
                                            @change="toggleStudentSelection(row.id, $event.target.checked)"
                                        />
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-400">{{ rowPosition + 1 }}</td>
                                    <td class="px-3 py-2">
                                        <p class="font-semibold">{{ row.full_name }}</p>
                                        <p class="text-xs text-gray-500">الهوية: {{ row.national_id || '-' }}</p>
                                        <p
                                            v-if="row.leave_state"
                                            class="mt-1 inline-flex items-center rounded bg-indigo-900/60 px-2 py-0.5 text-[11px] text-indigo-200"
                                        >
                                            إجازة معتمدة {{ row.leave_state.start_date }} - {{ row.leave_state.end_date }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-2">{{ row.student_code || '-' }}</td>
                                    <td class="px-3 py-2">
                                        <select
                                            v-model="attendanceForm.records[row.__index].status"
                                            class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-xs"
                                            :disabled="hasActiveLeave(row.__index)"
                                            @change="onStatusChange(row.__index)"
                                        >
                                            <option v-for="option in statusOptionsFor(row.__index)" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <p class="mt-1 text-[11px] text-gray-500">{{ statusLabel(attendanceForm.records[row.__index].status) }}</p>
                                        <p
                                            v-if="hasActiveLeave(row.__index)"
                                            class="mt-1 rounded bg-indigo-900/40 px-2 py-1 text-[11px] text-indigo-200"
                                        >
                                            تم تثبيت الحالة على "إجازة" بسبب طلب إجازة معتمد.
                                        </p>
                                        <p v-if="rowError(row.__index, 'status')" class="mt-1 text-xs text-red-400">{{ rowError(row.__index, 'status') }}</p>
                                        <p v-if="rowError(row.__index, 'school_student_id')" class="mt-1 text-xs text-red-400">{{ rowError(row.__index, 'school_student_id') }}</p>
                                    </td>
                                    <td class="px-3 py-2">
                                        <input
                                            v-model="attendanceForm.records[row.__index].check_in_time"
                                            type="time"
                                            class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-xs"
                                            :disabled="isAbsent(row.__index) || isLeave(row.__index)"
                                        />
                                        <p v-if="rowError(row.__index, 'check_in_time')" class="mt-1 text-xs text-red-400">{{ rowError(row.__index, 'check_in_time') }}</p>
                                    </td>
                                    <td class="px-3 py-2">
                                        <input
                                            v-model="attendanceForm.records[row.__index].check_out_time"
                                            type="time"
                                            class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-xs"
                                            :disabled="isAbsent(row.__index) || isLeave(row.__index)"
                                        />
                                        <p v-if="rowError(row.__index, 'check_out_time')" class="mt-1 text-xs text-red-400">{{ rowError(row.__index, 'check_out_time') }}</p>
                                    </td>
                                    <td class="px-3 py-2">
                                        <input
                                            v-model="attendanceForm.records[row.__index].permission_reason"
                                            class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-xs"
                                            placeholder="سبب الإذن"
                                            :disabled="!isExcused(row.__index)"
                                        />
                                        <p v-if="rowError(row.__index, 'permission_reason')" class="mt-1 text-xs text-red-400">{{ rowError(row.__index, 'permission_reason') }}</p>
                                    </td>
                                    <td class="px-3 py-2">
                                        <input
                                            v-model="attendanceForm.records[row.__index].notes"
                                            class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-xs"
                                            placeholder="ملاحظات"
                                        />
                                        <p v-if="rowError(row.__index, 'notes')" class="mt-1 text-xs text-red-400">{{ rowError(row.__index, 'notes') }}</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-2">
                        <div class="text-xs text-gray-500">
                            {{ attendanceRows.length }} / {{ attendanceForm.records.length }} طالب
                        </div>
                        <button
                            type="submit"
                            :disabled="!canSubmitAttendance"
                            class="rounded bg-emerald-700 px-4 py-2 text-sm font-bold hover:bg-emerald-600 disabled:opacity-60"
                        >
                            حفظ سجلات الحضور
                        </button>
                    </div>

                    <p v-if="formError('attendance_date')" class="mt-2 text-xs text-red-400">{{ formError('attendance_date') }}</p>
                    <p v-if="formError('school_stage_id')" class="mt-1 text-xs text-red-400">{{ formError('school_stage_id') }}</p>
                    <p v-if="formError('school_classroom_id')" class="mt-1 text-xs text-red-400">{{ formError('school_classroom_id') }}</p>
                    <p v-if="formError('records')" class="mt-1 text-xs text-red-400">{{ formError('records') }}</p>
                </form>
            </section>
                </div>
            </AppModal>

            <section class="rounded-xl border border-gray-800 bg-gray-900 p-4">
                <h2 class="mb-1 text-lg font-bold">فلاتر تقرير الحضور والغياب</h2>
                <p class="mb-3 text-xs text-gray-400">حدّد فترة التقرير وخيارات التصفية، ثم حدّث التقرير لعرض النتائج.</p>

                <div class="grid grid-cols-1 gap-2 md:grid-cols-5">
                    <label class="block text-xs text-gray-400">
                        من تاريخ التقرير
                        <input v-model="filterForm.report_date_from" type="date" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm" />
                    </label>
                    <label class="block text-xs text-gray-400">
                        إلى تاريخ التقرير
                        <input v-model="filterForm.report_date_to" type="date" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm" />
                    </label>
                    <label class="block text-xs text-gray-400">
                        نوع يوم التقرير
                        <select v-model="filterForm.report_day_type" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm">
                            <option value="">كل الأيام</option>
                            <option v-for="option in reportDayTypeOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                    <label class="block text-xs text-gray-400">
                        اسم العطلة (اختياري)
                        <input
                            v-model="filterForm.report_holiday_name"
                            type="text"
                            placeholder="مثال: اليوم الوطني"
                            class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                        />
                    </label>
                    <label class="block text-xs text-gray-400">
                        نوع الإجازة (اختياري)
                        <select v-model="filterForm.report_leave_type_id" class="mt-1 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm">
                            <option value="">كل أنواع الإجازة</option>
                            <option v-for="typeItem in reportLeaveTypeOptions" :key="typeItem.id" :value="typeItem.id">
                                {{ typeItem.name }}
                            </option>
                        </select>
                    </label>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="button" class="rounded bg-blue-700 px-3 py-2 text-sm font-bold hover:bg-blue-600" @click="applyFilters">
                        تحديث تقرير الحضور
                    </button>
                    <button type="button" class="rounded bg-gray-700 px-3 py-2 text-sm font-bold hover:bg-gray-600" @click="resetReportFilters">
                        إعادة ضبط فلاتر التقرير
                    </button>
                </div>

                <p v-if="reportFiltersHint" :class="['mt-2 text-xs', reportFiltersHintClass]">
                    {{ reportFiltersHint }}
                </p>
            </section>

            <section class="rounded-xl border border-gray-800 bg-gray-900 p-4">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold">تقارير الحضور والغياب</h2>
                        <p class="text-xs text-gray-400">
                            المؤشرات التالية تراعي استبعاد أيام العطل الرسمية والإجازة الأسبوعية من الغياب غير المبرر.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <p class="text-xs text-gray-400">
                            {{ (props.attendanceReport?.range?.from || '-') }} إلى {{ (props.attendanceReport?.range?.to || '-') }}
                        </p>
                        <button
                            class="rounded bg-emerald-700 px-3 py-1 text-xs font-bold hover:bg-emerald-600 disabled:opacity-50"
                            :disabled="!canExportReport"
                            @click="exportReport"
                        >
                            تصدير CSV
                        </button>
                    </div>
                </div>

                <p v-if="exportHint" :class="['mb-3 text-xs', exportHintClass]">
                    {{ exportHint }}
                </p>

                <div class="mb-3 grid grid-cols-1 gap-2 md:grid-cols-4">
                    <div class="rounded border border-indigo-500/30 bg-indigo-500/10 p-3 text-sm">
                        <p class="text-xs text-indigo-200">أيام الإجازة</p>
                        <p class="text-xl font-bold text-indigo-100">{{ reportTotals.leave_days }}</p>
                    </div>
                    <div class="rounded border border-red-500/30 bg-red-500/10 p-3 text-sm">
                        <p class="text-xs text-red-200">غياب غير مبرر</p>
                        <p class="text-xl font-bold text-red-100">{{ reportTotals.unexcused_absence_days }}</p>
                    </div>
                    <div class="rounded border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm">
                        <p class="text-xs text-emerald-200">حضور</p>
                        <p class="text-xl font-bold text-emerald-100">{{ reportTotals.present_days }}</p>
                    </div>
                    <div class="rounded border border-amber-500/30 bg-amber-500/10 p-3 text-sm">
                        <p class="text-xs text-amber-200">أعذار</p>
                        <p class="text-xl font-bold text-amber-100">{{ reportTotals.excused_days }}</p>
                    </div>
                </div>

                <div class="mb-3 grid grid-cols-1 gap-2 md:grid-cols-3">
                    <div class="rounded border border-cyan-500/30 bg-cyan-500/10 p-3 text-sm">
                        <p class="text-xs text-cyan-200">أيام دراسة ضمن الفلتر</p>
                        <p class="text-xl font-bold text-cyan-100">{{ attendanceReport?.day_type_summary?.school_days || 0 }}</p>
                    </div>
                    <div class="rounded border border-violet-500/30 bg-violet-500/10 p-3 text-sm">
                        <p class="text-xs text-violet-200">أيام عطلات رسمية ضمن الفلتر</p>
                        <p class="text-xl font-bold text-violet-100">{{ attendanceReport?.day_type_summary?.holiday_days || 0 }}</p>
                    </div>
                    <div class="rounded border border-sky-500/30 bg-sky-500/10 p-3 text-sm">
                        <p class="text-xs text-sky-200">أيام إجازة أسبوعية ضمن الفلتر</p>
                        <p class="text-xl font-bold text-sky-100">{{ attendanceReport?.day_type_summary?.weekly_off_days || 0 }}</p>
                    </div>
                </div>

                <div v-if="!filterForm.classroom_id" class="rounded border border-dashed border-gray-700 p-4 text-sm text-gray-400">
                    اختر الفصل لعرض بيانات التقرير.
                </div>

                <div v-else-if="reportRows.length === 0" class="rounded border border-dashed border-gray-700 p-4 text-sm text-gray-400">
                    لا توجد بيانات ضمن فترة التقرير الحالية.
                </div>

                <div v-else class="space-y-3 lg:hidden">
                    <article v-for="row in reportRows" :key="`report-mobile-${row.school_student_id}`" class="rounded-2xl border border-gray-700 bg-gray-900 p-4 text-right">
                        <div class="mb-3">
                            <h3 class="font-semibold">{{ row.full_name || '-' }}</h3>
                            <p class="text-xs text-gray-500">كود الطالب: {{ row.student_code || '-' }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div><p class="text-xs text-gray-500">الإجازات</p><p class="font-semibold text-indigo-300">{{ row.leave_days || 0 }}</p></div>
                            <div><p class="text-xs text-gray-500">غياب غير مبرر</p><p class="font-semibold text-red-300">{{ row.unexcused_absence_days || 0 }}</p></div>
                            <div><p class="text-xs text-gray-500">حضور</p><p class="font-semibold text-emerald-300">{{ row.present_days || 0 }}</p></div>
                            <div><p class="text-xs text-gray-500">أعذار</p><p class="font-semibold text-amber-300">{{ row.excused_days || 0 }}</p></div>
                            <div class="col-span-2"><p class="text-xs text-gray-500">أيام مسجلة</p><p class="font-semibold">{{ row.recorded_days || 0 }}</p></div>
                        </div>
                    </article>
                </div>

                <div class="hidden overflow-hidden rounded border border-gray-700 lg:block">
                    <table class="w-full text-right text-sm text-gray-200">
                        <thead class="bg-gray-800 text-xs text-gray-400">
                            <tr>
                                <th class="px-3 py-2">الطالب</th>
                                <th class="px-3 py-2">كود الطالب</th>
                                <th class="px-3 py-2">الإجازات</th>
                                <th class="px-3 py-2">غياب غير مبرر</th>
                                <th class="px-3 py-2">حضور</th>
                                <th class="px-3 py-2">أعذار</th>
                                <th class="px-3 py-2">أيام مسجلة</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700 bg-gray-900">
                            <tr v-for="row in reportRows" :key="row.school_student_id">
                                <td class="px-3 py-2 font-semibold">{{ row.full_name || '-' }}</td>
                                <td class="px-3 py-2">{{ row.student_code || '-' }}</td>
                                <td class="px-3 py-2 text-indigo-300">{{ row.leave_days || 0 }}</td>
                                <td class="px-3 py-2 text-red-300">{{ row.unexcused_absence_days || 0 }}</td>
                                <td class="px-3 py-2 text-emerald-300">{{ row.present_days || 0 }}</td>
                                <td class="px-3 py-2 text-amber-300">{{ row.excused_days || 0 }}</td>
                                <td class="px-3 py-2">{{ row.recorded_days || 0 }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </RoleLayout>
</template>
