<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    Building2,
    CalendarDays,
    Filter,
    GraduationCap,
    Pencil,
    PlusCircle,
    Save,
    School,
    Trash2,
    UserRound,
    Users,
    X,
} from 'lucide-vue-next';
import AttachmentPanel from '@/Components/AttachmentPanel.vue';
import RoleLayout from '@/Layouts/RoleLayout.vue';
import { stageAccentStyle } from '@/utils/stagePalette';
import { useActionDialog } from '@/composables/useActionDialog';
import {
    defaultDataProvisioningCountItems,
    defaultDataProvisioningSummaryText,
} from '@/utils/defaultDataProvisioning';

const props = defineProps({
    school: { type: Object, default: null },
    stages: { type: Array, default: () => [] },
    isManager: { type: Boolean, default: false },
    defaultDataProvisioning: { type: Object, default: null },
    permissions: { type: Object, default: () => ({}) },
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

const classroomNameInput = ref(null);
const stageTermNameInput = ref(null);
const studentNameInput = ref(null);
const isStageTermModalOpen = ref(false);
const isClassroomModalOpen = ref(false);
const isStudentModalOpen = ref(false);

const focusInput = (inputRef) => {
    nextTick(() => {
        inputRef.value?.focus?.();
    });
};

const normalizeGradeName = (value) => {
    const normalized = String(value || '').trim();
    return normalized !== '' ? normalized : 'غير محدد';
};

const stageAccent = (stageId, stageName = '') => stageAccentStyle(stageId, stageName);

const stageOptions = computed(() => props.stages.map((stage) => ({ id: stage.id, name: stage.name })));
const defaultStageId = computed(() => stageOptions.value[0]?.id || '');

const classroomOptions = computed(() =>
    props.stages.flatMap((stage) =>
        (stage.classrooms || []).map((classroom) => ({
            ...classroom,
            stage_name: stage.name,
            school_stage_id: stage.id,
            grade_name: normalizeGradeName(classroom.grade_name),
        }))
    )
);

const stageGradeOptionsMap = computed(() =>
    new Map(
        props.stages.map((stage) => {
            const stageGrades = (stage.grades || [])
                .map((grade) => normalizeGradeName(grade.name));

            return [Number(stage.id), [...new Set(stageGrades)]];
        })
    )
);

const uniqueGradesForStage = (stageId) => {
    const normalizedStageId = Number(stageId || 0);
    if (normalizedStageId <= 0) return [];

    const configuredGrades = stageGradeOptionsMap.value.get(normalizedStageId) || [];
    if (configuredGrades.length > 0) {
        return configuredGrades;
    }

    const grades = classroomOptions.value
        .filter((classroom) => Number(classroom.school_stage_id) === normalizedStageId)
        .map((classroom) => normalizeGradeName(classroom.grade_name));

    return [...new Set(grades)];
};

const classroomEditId = ref(null);
const classroomForm = useForm({
    school_stage_id: defaultStageId.value,
    grade_name: '',
    name: '',
    code: '',
    sort_order: 0,
    is_active: true,
});

const stageTermEditId = ref(null);
const stageTermForm = useForm({
    school_stage_id: defaultStageId.value,
    name: '',
    start_date: '',
    end_date: '',
    sort_order: 0,
    is_active: true,
});

const gradeTermEditId = ref(null);
const gradeTermForm = useForm({
    school_stage_id: defaultStageId.value,
    school_stage_grade_id: '',
    name: '',
    sort_order: 0,
    is_active: true,
});

const studentEditId = ref(null);
const pendingStudentAttachments = ref([]);
const studentForm = useForm({
    school_stage_id: defaultStageId.value,
    classroom_grade_name: '',
    school_classroom_id: '',
    full_name: '',
    student_code: '',
    national_id: '',
    is_active: true,
    attachments: [],
});

const studentFilterStageId = ref('');
const studentFilterGradeName = ref('');
const studentFilterClassroomId = ref('');
const classroomStageGrades = computed(() => uniqueGradesForStage(classroomForm.school_stage_id));
const gradeTermStageGrades = computed(() =>
    (props.stages.find((stage) => Number(stage.id) === Number(gradeTermForm.school_stage_id || 0))?.grades || [])
        .map((grade) => ({ id: grade.id, name: normalizeGradeName(grade.name) }))
);

const classroomsForStudentScope = computed(() => {
    const stageId = Number(studentForm.school_stage_id || 0);
    const gradeName = normalizeGradeName(studentForm.classroom_grade_name || '');

    let rows = classroomOptions.value.filter((classroom) => Number(classroom.school_stage_id) === stageId);
    if (gradeName !== '') {
        rows = rows.filter((classroom) => normalizeGradeName(classroom.grade_name) === gradeName);
    }

    return rows;
});

const studentGradeOptions = computed(() => uniqueGradesForStage(studentForm.school_stage_id));

const filterGradeOptions = computed(() => {
    if (studentFilterStageId.value) {
        return uniqueGradesForStage(studentFilterStageId.value);
    }

    const configured = [...stageGradeOptionsMap.value.values()].flat();
    if (configured.length > 0) {
        return [...new Set(configured)];
    }

    return [...new Set(classroomOptions.value.map((classroom) => normalizeGradeName(classroom.grade_name)))];
});

const classroomsForFilterScope = computed(() => {
    let rows = classroomOptions.value;

    if (studentFilterStageId.value) {
        rows = rows.filter((classroom) => Number(classroom.school_stage_id) === Number(studentFilterStageId.value));
    }

    if (studentFilterGradeName.value) {
        rows = rows.filter((classroom) => normalizeGradeName(classroom.grade_name) === normalizeGradeName(studentFilterGradeName.value));
    }

    return rows;
});

watch(
    () => stageOptions.value.map((stage) => Number(stage.id)).join(','),
    () => {
        const validStageIds = stageOptions.value.map((stage) => Number(stage.id));

        if (!validStageIds.includes(Number(stageTermForm.school_stage_id))) {
            stageTermForm.school_stage_id = defaultStageId.value;
        }

        if (!validStageIds.includes(Number(classroomForm.school_stage_id))) {
            classroomForm.school_stage_id = defaultStageId.value;
        }

        if (!validStageIds.includes(Number(studentForm.school_stage_id))) {
            studentForm.school_stage_id = defaultStageId.value;
        }

        if (!validStageIds.includes(Number(studentFilterStageId.value))) {
            studentFilterStageId.value = '';
            studentFilterGradeName.value = '';
            studentFilterClassroomId.value = '';
        }
    }
);

watch(
    () => classroomForm.school_stage_id,
    () => {
        const grades = uniqueGradesForStage(classroomForm.school_stage_id);
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

watch(
    () => studentForm.school_stage_id,
    () => {
        const grades = uniqueGradesForStage(studentForm.school_stage_id);
        const currentGrade = normalizeGradeName(studentForm.classroom_grade_name);

        if (grades.length > 0 && !grades.includes(currentGrade)) {
            studentForm.classroom_grade_name = grades[0];
        } else if (grades.length === 0) {
            studentForm.classroom_grade_name = '';
        }

        const availableClassroomIds = classroomsForStudentScope.value.map((classroom) => Number(classroom.id));
        if (!availableClassroomIds.includes(Number(studentForm.school_classroom_id))) {
            studentForm.school_classroom_id = classroomsForStudentScope.value[0]?.id || '';
        }
    }
);

watch(
    () => studentForm.classroom_grade_name,
    () => {
        const availableClassroomIds = classroomsForStudentScope.value.map((classroom) => Number(classroom.id));
        if (!availableClassroomIds.includes(Number(studentForm.school_classroom_id))) {
            studentForm.school_classroom_id = classroomsForStudentScope.value[0]?.id || '';
        }
    }
);

watch(
    () => studentFilterStageId.value,
    () => {
        const grades = filterGradeOptions.value;
        if (studentFilterGradeName.value && !grades.includes(normalizeGradeName(studentFilterGradeName.value))) {
            studentFilterGradeName.value = '';
        }

        const availableClassroomIds = classroomsForFilterScope.value.map((classroom) => Number(classroom.id));
        if (!availableClassroomIds.includes(Number(studentFilterClassroomId.value))) {
            studentFilterClassroomId.value = '';
        }
    }
);

watch(
    () => studentFilterGradeName.value,
    () => {
        const availableClassroomIds = classroomsForFilterScope.value.map((classroom) => Number(classroom.id));
        if (!availableClassroomIds.includes(Number(studentFilterClassroomId.value))) {
            studentFilterClassroomId.value = '';
        }
    }
);

const studentRows = computed(() => {
    const rows = [];

    for (const stage of props.stages) {
        for (const classroom of stage.classrooms || []) {
            for (const student of classroom.students || []) {
                rows.push({
                    ...student,
                    stage_id: stage.id,
                    stage_name: stage.name,
                    classroom_id: classroom.id,
                    classroom_name: classroom.name,
                    classroom_grade_name: normalizeGradeName(classroom.grade_name),
                });
            }
        }
    }

    return rows;
});

const filteredStudentRows = computed(() =>
    studentRows.value.filter((row) => {
        if (studentFilterStageId.value && Number(row.stage_id) !== Number(studentFilterStageId.value)) return false;
        if (studentFilterGradeName.value && normalizeGradeName(row.classroom_grade_name) !== normalizeGradeName(studentFilterGradeName.value)) return false;
        if (studentFilterClassroomId.value && Number(row.classroom_id) !== Number(studentFilterClassroomId.value)) return false;
        return true;
    })
);

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

const selectedStudentRow = computed(() =>
    studentRows.value.find((row) => Number(row.id) === Number(studentEditId.value || 0)) || null
);

const selectedStudentAttachments = computed(() =>
    normalizeExistingAttachments(selectedStudentRow.value?.attachments || [])
);

const classroomRows = computed(() =>
    classroomOptions.value
        .map((classroom) => ({
            ...classroom,
            students_count: (classroom.students || []).length || 0,
        }))
        .sort((a, b) => {
            if (Number(a.school_stage_id) !== Number(b.school_stage_id)) return Number(a.school_stage_id) - Number(b.school_stage_id);
            if (normalizeGradeName(a.grade_name) !== normalizeGradeName(b.grade_name)) {
                return normalizeGradeName(a.grade_name).localeCompare(normalizeGradeName(b.grade_name), 'ar');
            }
            if (Number(a.sort_order || 0) !== Number(b.sort_order || 0)) return Number(a.sort_order || 0) - Number(b.sort_order || 0);
            return String(a.name || '').localeCompare(String(b.name || ''), 'ar');
        })
);

const stageTermRows = computed(() =>
    props.stages
        .flatMap((stage) =>
            (stage.stage_terms || []).map((term) => ({
                ...term,
                stage_id: stage.id,
                stage_name: stage.name,
                school_stage_id: stage.id,
            }))
        )
        .sort((a, b) => {
            if (Number(a.stage_id) !== Number(b.stage_id)) return Number(a.stage_id) - Number(b.stage_id);
            if (Number(a.sort_order || 0) !== Number(b.sort_order || 0)) return Number(a.sort_order || 0) - Number(b.sort_order || 0);
            return String(a.name || '').localeCompare(String(b.name || ''), 'ar');
        })
);

const gradeTermRows = computed(() =>
    props.stages
        .flatMap((stage) =>
            (stage.grades || []).flatMap((grade) =>
                (grade.grade_terms || []).map((term) => ({
                    ...term,
                    stage_id: stage.id,
                    stage_name: stage.name,
                    school_stage_grade_id: grade.id,
                    grade_name: normalizeGradeName(grade.name),
                }))
            )
        )
        .sort((a, b) => {
            if (Number(a.stage_id) !== Number(b.stage_id)) return Number(a.stage_id) - Number(b.stage_id);
            if (a.grade_name !== b.grade_name) return String(a.grade_name).localeCompare(String(b.grade_name), 'ar');
            if (Number(a.sort_order || 0) !== Number(b.sort_order || 0)) return Number(a.sort_order || 0) - Number(b.sort_order || 0);
            return String(a.name || '').localeCompare(String(b.name || ''), 'ar');
        })
);

const extractDeleteErrorMessage = (errors = {}) => {
    const keys = ['stage', 'stage_term', 'stage_grade', 'stage_grade_term', 'classroom', 'student', 'confirm_impact'];
    for (const key of keys) {
        if (typeof errors[key] === 'string' && errors[key].trim() !== '') {
            return errors[key];
        }
    }

    const firstError = Object.values(errors || {}).find((value) => typeof value === 'string' && value.trim() !== '');
    return firstError || 'تعذر تنفيذ عملية الحذف بسبب وجود بيانات مرتبطة.';
};

const guardedDelete = (endpoint) => {
    const deleteForm = useForm({});
    deleteForm.delete(endpoint, {
        preserveScroll: true,
        onError: (errors) => {
            actionDialog.alert({
                title: 'تعذر تنفيذ العملية',
                message: extractDeleteErrorMessage(errors),
                confirmText: 'حسنًا',
                variant: 'danger',
            });
        },
    });
};
const resetStageTermForm = (preferredStageId = null, shouldFocus = true) => {
    stageTermEditId.value = null;
    stageTermForm.reset();

    const availableStageIds = stageOptions.value.map((stage) => String(stage.id));
    stageTermForm.school_stage_id =
        preferredStageId && availableStageIds.includes(String(preferredStageId))
            ? preferredStageId
            : defaultStageId.value;

    stageTermForm.sort_order = 0;
    stageTermForm.is_active = true;
    stageTermForm.clearErrors();
    if (shouldFocus) {
        focusInput(stageTermNameInput);
    }
};

const editStageTerm = (termRow) => {
    stageTermEditId.value = termRow.id;
    stageTermForm.school_stage_id = termRow.school_stage_id || termRow.stage_id;
    stageTermForm.name = termRow.name || '';
    stageTermForm.start_date = termRow.start_date || '';
    stageTermForm.end_date = termRow.end_date || '';
    stageTermForm.sort_order = Number(termRow.sort_order || 0);
    stageTermForm.is_active = Boolean(termRow.is_active);
    stageTermForm.clearErrors();
    focusInput(stageTermNameInput);
};

const openCreateStageTermModal = (preferredStageId = null) => {
    isStageTermModalOpen.value = true;
    nextTick(() => {
        resetStageTermForm(preferredStageId);
    });
};

const openEditStageTermModal = (termRow) => {
    isStageTermModalOpen.value = true;
    nextTick(() => {
        editStageTerm(termRow);
    });
};

const closeStageTermModal = () => {
    isStageTermModalOpen.value = false;
    resetStageTermForm(null, false);
};

const submitStageTerm = () => {
    const preferredStageId = stageTermForm.school_stage_id;

    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            isStageTermModalOpen.value = false;
            resetStageTermForm(preferredStageId, false);
        },
    };

    if (stageTermEditId.value) {
        stageTermForm.put(route('school.student_structure.stage_terms.update', stageTermEditId.value), options);
        return;
    }

    stageTermForm.post(route('school.student_structure.stage_terms.store'), options);
};

const removeStageTerm = async (stageTermId) => {
    const confirmed = await actionDialog.confirm({
        title: 'حذف الفصل الدراسي',
        message: 'سيتم حذف هذا الفصل الدراسي من المرحلة داخل مدرستك فقط، ولن يتأثر القالب العام أو أي مدرسة أخرى.',
        confirmText: 'نعم، احذف الفصل',
        cancelText: 'إلغاء',
        variant: 'danger',
    });
    if (!confirmed) return;
    guardedDelete(route('school.student_structure.stage_terms.destroy', stageTermId));
};

const resetClassroomForm = (preferredStageId = null, shouldFocus = true) => {
    classroomEditId.value = null;
    classroomForm.reset();

    const availableStageIds = stageOptions.value.map((stage) => String(stage.id));
    classroomForm.school_stage_id =
        preferredStageId && availableStageIds.includes(String(preferredStageId))
            ? preferredStageId
            : defaultStageId.value;

    const grades = uniqueGradesForStage(classroomForm.school_stage_id);
    classroomForm.grade_name = grades[0] || '';
    classroomForm.sort_order = 0;
    classroomForm.is_active = true;
    classroomForm.clearErrors();
    if (shouldFocus) {
        focusInput(classroomNameInput);
    }
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
    focusInput(classroomNameInput);
};

const openCreateClassroomModal = () => {
    isClassroomModalOpen.value = true;
    nextTick(() => {
        resetClassroomForm();
    });
};

const openEditClassroomModal = (classroom) => {
    isClassroomModalOpen.value = true;
    nextTick(() => {
        editClassroom(classroom);
    });
};

const closeClassroomModal = () => {
    isClassroomModalOpen.value = false;
    resetClassroomForm(null, false);
};

const submitClassroom = () => {
    const preferredStageId = classroomForm.school_stage_id;
    classroomForm.grade_name = classroomForm.grade_name ? normalizeGradeName(classroomForm.grade_name) : '';

    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            isClassroomModalOpen.value = false;
            resetClassroomForm(preferredStageId, false);
        },
    };

    if (classroomEditId.value) {
        classroomForm.put(route('school.student_structure.classrooms.update', classroomEditId.value), options);
        return;
    }

    classroomForm.post(route('school.student_structure.classrooms.store'), options);
};

const removeClassroom = async (classroomId) => {
    const confirmed = await actionDialog.confirm({
        title: 'حذف الفصل',
        message: 'سيتم الحذف فقط إذا لم توجد بيانات تشغيلية مرتبطة بالفصل. هل تريد المتابعة؟',
        confirmText: 'نعم، احذف الفصل',
        cancelText: 'إلغاء',
        variant: 'danger',
    });
    if (!confirmed) return;
    guardedDelete(route('school.student_structure.classrooms.destroy', classroomId));
};

const resetStudentForm = (preferredStageId = null, preferredGradeName = null, preferredClassroomId = null, shouldFocus = true) => {
    studentEditId.value = null;
    studentForm.reset();

    const availableStageIds = stageOptions.value.map((stage) => String(stage.id));
    const resolvedStageId =
        preferredStageId && availableStageIds.includes(String(preferredStageId))
            ? preferredStageId
            : defaultStageId.value;

    studentForm.school_stage_id = resolvedStageId;

    const grades = uniqueGradesForStage(resolvedStageId);
    if (preferredGradeName && grades.includes(normalizeGradeName(preferredGradeName))) {
        studentForm.classroom_grade_name = normalizeGradeName(preferredGradeName);
    } else {
        studentForm.classroom_grade_name = grades[0] || '';
    }

    const availableClassrooms = classroomOptions.value.filter(
        (classroom) =>
            Number(classroom.school_stage_id) === Number(resolvedStageId)
            && normalizeGradeName(classroom.grade_name) === normalizeGradeName(studentForm.classroom_grade_name)
    );
    const availableClassroomIds = availableClassrooms.map((classroom) => String(classroom.id));

    studentForm.school_classroom_id =
        preferredClassroomId && availableClassroomIds.includes(String(preferredClassroomId))
            ? preferredClassroomId
            : availableClassrooms[0]?.id || '';

    studentForm.is_active = true;
    studentForm.attachments = [];
    pendingStudentAttachments.value = [];
    studentForm.clearErrors();
    if (shouldFocus) {
        focusInput(studentNameInput);
    }
};

const editStudent = (studentRow) => {
    studentEditId.value = studentRow.id;
    studentForm.school_stage_id = studentRow.stage_id;
    studentForm.classroom_grade_name = normalizeGradeName(studentRow.classroom_grade_name);
    studentForm.school_classroom_id = studentRow.classroom_id;
    studentForm.full_name = studentRow.full_name || '';
    studentForm.student_code = studentRow.student_code || '';
    studentForm.national_id = studentRow.national_id || '';
    studentForm.is_active = Boolean(studentRow.is_active);
    studentForm.attachments = [];
    pendingStudentAttachments.value = [];
    studentForm.clearErrors();
    focusInput(studentNameInput);
};

const openCreateStudentModal = () => {
    isStudentModalOpen.value = true;
    nextTick(() => {
        resetStudentForm();
    });
};

const openEditStudentModal = (studentRow) => {
    isStudentModalOpen.value = true;
    nextTick(() => {
        editStudent(studentRow);
    });
};

const closeStudentModal = () => {
    isStudentModalOpen.value = false;
    resetStudentForm(null, null, null, false);
};

const appendStudentAttachmentFiles = (fileList) => {
    const incoming = Array.from(fileList || []).filter((file) => file instanceof File);
    if (incoming.length === 0) return;

    pendingStudentAttachments.value = [...pendingStudentAttachments.value, ...incoming].slice(0, 10);
};

const removePendingStudentAttachment = (index) => {
    pendingStudentAttachments.value = pendingStudentAttachments.value.filter((_, itemIndex) => itemIndex !== index);
};

const clearPendingStudentAttachments = () => {
    pendingStudentAttachments.value = [];
    studentForm.attachments = [];
};

const studentAttachmentErrors = computed(() => [
    studentForm.errors.attachments,
    studentForm.errors['attachments.0'],
].filter((value) => typeof value === 'string' && value.trim() !== ''));

const deleteStudentAttachment = async (attachment) => {
    if (!attachment?.id) return;

    const confirmed = await actionDialog.confirm({
        title: 'حذف المرفق',
        message: 'سيتم حذف هذا المرفق من ملف الطالب. هل تريد المتابعة؟',
        confirmText: 'نعم، احذف',
        cancelText: 'إلغاء',
        variant: 'danger',
    });

    if (!confirmed) return;

    useForm({}).delete(route('school.attachments.destroy', { attachment: attachment.id }), {
        preserveScroll: true,
        preserveState: true,
    });
};

const submitStudent = () => {
    const preferredStageId = studentForm.school_stage_id;
    const preferredGradeName = studentForm.classroom_grade_name;
    const preferredClassroomId = studentForm.school_classroom_id;
    studentForm.classroom_grade_name = normalizeGradeName(studentForm.classroom_grade_name || '');
    studentForm.attachments = [...pendingStudentAttachments.value];

    const options = {
        preserveScroll: true,
        preserveState: true,
        forceFormData: true,
        onSuccess: () => {
            clearPendingStudentAttachments();
            isStudentModalOpen.value = false;
            resetStudentForm(preferredStageId, preferredGradeName, preferredClassroomId, false);
        },
    };

    if (studentEditId.value) {
        studentForm.put(route('school.student_structure.students.update', studentEditId.value), options);
        return;
    }

    studentForm.post(route('school.student_structure.students.store'), options);
};

const removeStudent = async (studentId) => {
    const confirmed = await actionDialog.confirm({
        title: 'حذف الطالب',
        message: 'سيتم حذف الطالب فقط إذا لم تكن له سجلات حضور أو إجازات مرتبطة. هل تريد المتابعة؟',
        confirmText: 'نعم، احذف الطالب',
        cancelText: 'إلغاء',
        variant: 'danger',
    });
    if (!confirmed) return;
    guardedDelete(route('school.student_structure.students.destroy', studentId));
};

const statusLabel = (value) => (value ? 'نشط' : 'غير نشط');

const stageTermSourceLabel = (value) => ({
    api: 'من API الدولة',
    manual: 'معدل داخل المدرسة',
    default: 'افتراضي',
}[String(value || '').trim()] || 'افتراضي');
const formatDateLabel = (value) => {
    if (!value) return '-';

    try {
        return new Intl.DateTimeFormat('ar-EG', { dateStyle: 'medium' }).format(new Date(value));
    } catch (_error) {
        return String(value);
    }
};

if (!studentForm.school_stage_id && defaultStageId.value) {
    studentForm.school_stage_id = defaultStageId.value;
}

if (!classroomForm.school_stage_id && defaultStageId.value) {
    classroomForm.school_stage_id = defaultStageId.value;
}

const initialClassroomGrades = uniqueGradesForStage(classroomForm.school_stage_id);
if (!classroomForm.grade_name && initialClassroomGrades.length > 0) {
    classroomForm.grade_name = initialClassroomGrades[0];
}

const initialStudentGrades = uniqueGradesForStage(studentForm.school_stage_id);
if (!studentForm.classroom_grade_name && initialStudentGrades.length > 0) {
    studentForm.classroom_grade_name = initialStudentGrades[0];
}

if (!studentForm.school_classroom_id) {
    studentForm.school_classroom_id = classroomsForStudentScope.value[0]?.id || '';
}

focusInput(studentNameInput);
</script>

<template>
    <Head title="الهيكل الطلابي" />

    <RoleLayout title="الهيكل الطلابي" :role="roleForLayout" :permissions="props.permissions">
        <div v-if="!school" class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm text-amber-200">
            لا يوجد ربط لمدرسة لهذا الحساب حاليًا.
        </div>

        <div v-else class="space-y-6">
            <section
                v-if="defaultDataProvisioning"
                class="rounded-xl border p-4"
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
                                تم نسخ القوالب العامة إلى هذه المدرسة وأصبحت الآن بيانات مدرسية مستقلة يمكن تعديلها أو حذفها أو الإضافة عليها داخل المدرسة فقط. ويمكنك أيضًا استيراد أي عناصر جديدة مطابقة لاحقًا يدويًا دون المساس بالبيانات الحالية.
                            </template>
                            <template v-else-if="hasDefaultDataTemplates">
                                توجد قوالب عامة جاهزة على مستوى المنصة. يمكنك استيرادها مرة واحدة لتكوين نقطة بداية سريعة للمراحل والصفوف والعام الدراسي والعطل وأنواع الإجازات والمواد.
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

            <section class="rounded-xl border border-gray-800 bg-gray-900 p-4">
                <p class="inline-flex items-center gap-1 text-xs text-gray-400">
                    <School class="h-3.5 w-3.5" />
                    <span>المدرسة</span>
                </p>
                <p class="text-lg font-bold">{{ school.name }}</p>
                <p class="text-xs text-gray-500">{{ school.school_id }}</p>
            </section>

            <section class="rounded-xl border border-gray-800 bg-gray-900 p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="inline-flex items-center gap-2 text-lg font-bold">
                        <Building2 class="h-4 w-4 text-emerald-300" />
                        <span>1) الصفوف والفصول</span>
                    </h2>
                    <button type="button" class="inline-flex items-center gap-1 rounded bg-gray-700 px-3 py-1 text-xs hover:bg-gray-600" @click="openCreateClassroomModal">
                        <PlusCircle class="h-3.5 w-3.5" />
                        <span>جديد</span>
                    </button>
                </div>

                <div
                    v-if="isClassroomModalOpen"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                    @click.self="closeClassroomModal"
                >
                    <div class="w-full max-w-5xl overflow-hidden rounded-2xl border border-emerald-500/40 bg-gray-900 shadow-2xl">
                        <div class="flex items-center justify-between gap-2 border-b border-gray-700 bg-gray-800/90 p-4">
                            <h3 class="inline-flex items-center gap-2 text-base font-bold text-emerald-100">
                                <Building2 class="h-4 w-4 text-emerald-200" />
                                <span>{{ classroomEditId ? 'تعديل الفصل' : 'إضافة فصل' }}</span>
                            </h3>
                            <button type="button" class="inline-flex items-center gap-1 rounded bg-gray-700 px-3 py-1.5 text-xs hover:bg-gray-600" @click="closeClassroomModal">
                                <X class="h-3.5 w-3.5" />
                                <span>إغلاق</span>
                            </button>
                        </div>

                <form class="max-h-[72vh] overflow-y-auto p-4" @submit.prevent="submitClassroom">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-7">
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-400">
                                <School class="h-3.5 w-3.5 text-emerald-300" />
                                <span>المرحلة</span>
                            </label>
                            <select v-model="classroomForm.school_stage_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="" disabled>اختر المرحلة</option>
                                <option v-for="stage in stageOptions" :key="`classroom-stage-${stage.id}`" :value="stage.id">{{ stage.name }}</option>
                            </select>
                            <p v-if="classroomForm.errors.school_stage_id" class="mt-1 text-xs text-red-400">{{ classroomForm.errors.school_stage_id }}</p>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-400">
                                <GraduationCap class="h-3.5 w-3.5 text-emerald-300" />
                                <span>الصف</span>
                            </label>
                            <select v-model="classroomForm.grade_name" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="" disabled>اختر الصف</option>
                                <option v-for="grade in classroomStageGrades" :key="`classroom-grade-${grade}`" :value="grade">{{ grade }}</option>
                            </select>
                            <p v-if="classroomStageGrades.length === 0" class="mt-1 text-xs text-amber-400">
                                لا توجد صفوف معرفة لهذه المرحلة. أضف الصفوف أولًا من صفحة الهيكل الدراسي.
                            </p>
                            <p v-if="classroomForm.errors.grade_name" class="mt-1 text-xs text-red-400">{{ classroomForm.errors.grade_name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-400">
                                <Building2 class="h-3.5 w-3.5 text-emerald-300" />
                                <span>الفصل</span>
                            </label>
                            <input ref="classroomNameInput" v-model="classroomForm.name" placeholder="مثال: أ" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="classroomForm.errors.name" class="mt-1 text-xs text-red-400">{{ classroomForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-400">
                                <Building2 class="h-3.5 w-3.5 text-emerald-300" />
                                <span>كود الفصل</span>
                            </label>
                            <input v-model="classroomForm.code" placeholder="CLS-001" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="classroomForm.errors.code" class="mt-1 text-xs text-red-400">{{ classroomForm.errors.code }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">الترتيب</label>
                            <input v-model.number="classroomForm.sort_order" type="number" min="0" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="classroomForm.errors.sort_order" class="mt-1 text-xs text-red-400">{{ classroomForm.errors.sort_order }}</p>
                        </div>
                        <div class="flex items-end">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input v-model="classroomForm.is_active" type="checkbox" class="rounded border-gray-600 bg-gray-900 text-blue-500" />
                                <span>نشط</span>
                            </label>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" :disabled="classroomForm.processing || !classroomForm.school_stage_id || classroomStageGrades.length === 0 || !classroomForm.grade_name" class="inline-flex items-center gap-2 rounded bg-emerald-600 px-3 py-2 text-sm font-bold hover:bg-emerald-500">
                                <Save class="h-4 w-4" />
                                <span>{{ classroomEditId ? 'تحديث الفصل' : 'إضافة فصل' }}</span>
                            </button>
                        </div>
                    </div>
                </form>
                    </div>
                </div>
                <div class="space-y-3 lg:hidden">
                    <article v-for="classroom in classroomRows" :key="`classroom-mobile-${classroom.id}`" class="rounded-2xl border border-gray-700 bg-gray-900 p-4 text-right" :style="stageAccent(classroom.school_stage_id, classroom.stage_name)">
                        <div class="mb-3">
                            <span class="stage-badge" :style="stageAccent(classroom.school_stage_id, classroom.stage_name)">{{ classroom.stage_name }}</span>
                            <h3 class="mt-2 font-semibold">{{ classroom.grade_name }} / {{ classroom.name }}</h3>
                            <p class="text-xs text-gray-500">الكود: {{ classroom.code || '-' }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div><p class="text-xs text-gray-500">الترتيب</p><p class="font-semibold">{{ classroom.sort_order || 0 }}</p></div>
                            <div><p class="text-xs text-gray-500">الطلاب</p><p class="font-semibold">{{ classroom.students_count || 0 }}</p></div>
                            <div class="col-span-2"><p class="text-xs text-gray-500">الحالة</p><p class="font-semibold">{{ statusLabel(classroom.is_active) }}</p></div>
                        </div>
                        <div class="mt-4 flex flex-wrap justify-end gap-2">
                            <button class="inline-flex items-center gap-1 rounded bg-blue-700 px-3 py-2 text-xs hover:bg-blue-600" @click="openEditClassroomModal(classroom)"><Pencil class="h-3.5 w-3.5" /><span>تعديل</span></button>
                            <button class="inline-flex items-center gap-1 rounded bg-red-700 px-3 py-2 text-xs hover:bg-red-600" @click="removeClassroom(classroom.id)"><Trash2 class="h-3.5 w-3.5" /><span>حذف</span></button>
                        </div>
                    </article>
                </div>
                <div class="hidden overflow-hidden rounded border border-gray-700 lg:block">
                    <table class="w-full text-right text-sm text-gray-200">
                        <thead class="bg-gray-800 text-xs text-gray-400">
                            <tr>
                                <th class="px-3 py-2"><span class="inline-flex items-center gap-1"><School class="h-3.5 w-3.5" />المرحلة</span></th>
                                <th class="px-3 py-2"><span class="inline-flex items-center gap-1"><GraduationCap class="h-3.5 w-3.5" />الصف</span></th>
                                <th class="px-3 py-2"><span class="inline-flex items-center gap-1"><Building2 class="h-3.5 w-3.5" />الفصل</span></th>
                                <th class="px-3 py-2"><span class="inline-flex items-center gap-1"><Building2 class="h-3.5 w-3.5" />الكود</span></th>
                                <th class="px-3 py-2">الترتيب</th>
                                <th class="px-3 py-2"><span class="inline-flex items-center gap-1"><Users class="h-3.5 w-3.5" />الطلاب</span></th>
                                <th class="px-3 py-2">الحالة</th>
                                <th class="px-3 py-2 text-left">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700 bg-gray-900">
                            <tr v-for="classroom in classroomRows" :key="classroom.id" class="stage-row-accent" :style="stageAccent(classroom.school_stage_id, classroom.stage_name)">
                                <td class="px-3 py-2">
                                    <span class="stage-badge" :style="stageAccent(classroom.school_stage_id, classroom.stage_name)">
                                        {{ classroom.stage_name }}
                                    </span>
                                </td>
                                <td class="px-3 py-2">{{ classroom.grade_name }}</td>
                                <td class="px-3 py-2 font-semibold">{{ classroom.name }}</td>
                                <td class="px-3 py-2">{{ classroom.code || '-' }}</td>
                                <td class="px-3 py-2">{{ classroom.sort_order || 0 }}</td>
                                <td class="px-3 py-2">{{ classroom.students_count || 0 }}</td>
                                <td class="px-3 py-2">{{ statusLabel(classroom.is_active) }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex justify-end gap-2">
                                        <button class="inline-flex items-center gap-1 rounded bg-blue-700 px-2 py-1 text-xs hover:bg-blue-600" @click="openEditClassroomModal(classroom)">
                                            <Pencil class="h-3.5 w-3.5" />
                                            <span>تعديل</span>
                                        </button>
                                        <button class="inline-flex items-center gap-1 rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600" @click="removeClassroom(classroom.id)">
                                            <Trash2 class="h-3.5 w-3.5" />
                                            <span>حذف</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="classroomRows.length === 0">
                                <td colspan="8" class="px-3 py-6 text-center text-gray-500">لا توجد فصول مضافة بعد.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-xl border border-gray-800 bg-gray-900 p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="inline-flex items-center gap-2 text-lg font-bold">
                        <Users class="h-4 w-4 text-blue-300" />
                        <span>2) الطلاب</span>
                    </h2>
                    <button type="button" class="inline-flex items-center gap-1 rounded bg-gray-700 px-3 py-1 text-xs hover:bg-gray-600" @click="openCreateStudentModal">
                        <PlusCircle class="h-3.5 w-3.5" />
                        <span>جديد</span>
                    </button>
                </div>

                <div
                    v-if="isStudentModalOpen"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                    @click.self="closeStudentModal"
                >
                    <div class="w-full max-w-6xl overflow-hidden rounded-2xl border border-blue-500/40 bg-gray-900 shadow-2xl">
                        <div class="flex items-center justify-between gap-2 border-b border-gray-700 bg-gray-800/90 p-4">
                            <h3 class="inline-flex items-center gap-2 text-base font-bold text-blue-100">
                                <UserRound class="h-4 w-4 text-blue-200" />
                                <span>{{ studentEditId ? 'تعديل الطالب' : 'إضافة طالب' }}</span>
                            </h3>
                            <button type="button" class="inline-flex items-center gap-1 rounded bg-gray-700 px-3 py-1.5 text-xs hover:bg-gray-600" @click="closeStudentModal">
                                <X class="h-3.5 w-3.5" />
                                <span>إغلاق</span>
                            </button>
                        </div>

                <form class="max-h-[72vh] overflow-y-auto p-4" @submit.prevent="submitStudent">
                    <div class="mb-4 space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="space-y-1">
                                <p class="text-sm font-semibold text-white">مرفقات الطالب</p>
                                <p class="text-xs text-gray-400">
                                    ارفع شهادة الميلاد أو الهوية أو أي وثائق تعريفية خاصة بالطالب داخل نطاق نفس المدرسة.
                                </p>
                            </div>
                            <button
                                v-if="pendingStudentAttachments.length > 0"
                                type="button"
                                class="inline-flex items-center gap-1 rounded bg-gray-700 px-3 py-1.5 text-xs hover:bg-gray-600"
                                @click="clearPendingStudentAttachments"
                            >
                                <X class="h-3.5 w-3.5" />
                                <span>مسح الملفات المختارة</span>
                            </button>
                        </div>

                        <AttachmentPanel
                            title="وثائق الطالب"
                            helper-text="يمكنك رفع شهادة الميلاد، صورة الهوية، أو مستندات تعريفية أخرى تخص الطالب."
                            :existing-attachments="selectedStudentAttachments"
                            :pending-files="pendingStudentAttachments"
                            :errors="studentAttachmentErrors"
                            pending-title="مرفقات ستُحفظ مع بيانات الطالب"
                            existing-title="مرفقات الطالب الحالية"
                            :busy="studentForm.processing"
                            @select-files="appendStudentAttachmentFiles"
                            @remove-pending="removePendingStudentAttachment"
                            @delete-existing="deleteStudentAttachment"
                        />
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-7">
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-400">
                                <School class="h-3.5 w-3.5 text-blue-300" />
                                <span>المرحلة</span>
                            </label>
                            <select v-model="studentForm.school_stage_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="" disabled>اختر المرحلة</option>
                                <option v-for="stage in stageOptions" :key="stage.id" :value="stage.id">{{ stage.name }}</option>
                            </select>
                            <p v-if="studentForm.errors.school_stage_id" class="mt-1 text-xs text-red-400">{{ studentForm.errors.school_stage_id }}</p>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-400">
                                <GraduationCap class="h-3.5 w-3.5 text-blue-300" />
                                <span>الصف</span>
                            </label>
                            <select v-model="studentForm.classroom_grade_name" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="" disabled>اختر الصف</option>
                                <option v-for="grade in studentGradeOptions" :key="`student-grade-${grade}`" :value="grade">{{ grade }}</option>
                            </select>
                            <p v-if="studentForm.errors.classroom_grade_name" class="mt-1 text-xs text-red-400">{{ studentForm.errors.classroom_grade_name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-400">
                                <Building2 class="h-3.5 w-3.5 text-blue-300" />
                                <span>الفصل</span>
                            </label>
                            <select v-model="studentForm.school_classroom_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="" disabled>اختر الفصل</option>
                                <option v-for="classroom in classroomsForStudentScope" :key="classroom.id" :value="classroom.id">{{ classroom.name }}</option>
                            </select>
                            <p v-if="studentForm.errors.school_classroom_id" class="mt-1 text-xs text-red-400">{{ studentForm.errors.school_classroom_id }}</p>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-400">
                                <UserRound class="h-3.5 w-3.5 text-blue-300" />
                                <span>اسم الطالب</span>
                            </label>
                            <input ref="studentNameInput" v-model="studentForm.full_name" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="studentForm.errors.full_name" class="mt-1 text-xs text-red-400">{{ studentForm.errors.full_name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-400">
                                <Users class="h-3.5 w-3.5 text-blue-300" />
                                <span>كود الطالب</span>
                            </label>
                            <input v-model="studentForm.student_code" placeholder="STU-001" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="studentForm.errors.student_code" class="mt-1 text-xs text-red-400">{{ studentForm.errors.student_code }}</p>
                        </div>
                        <div>
                            <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-400">
                                <UserRound class="h-3.5 w-3.5 text-blue-300" />
                                <span>الرقم الوطني</span>
                            </label>
                            <input v-model="studentForm.national_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                            <p v-if="studentForm.errors.national_id" class="mt-1 text-xs text-red-400">{{ studentForm.errors.national_id }}</p>
                        </div>
                        <div class="flex items-end gap-2">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input v-model="studentForm.is_active" type="checkbox" class="rounded border-gray-600 bg-gray-900 text-blue-500" />
                                <span>نشط</span>
                            </label>
                            <button type="submit" :disabled="studentForm.processing || !studentForm.school_classroom_id" class="inline-flex items-center gap-2 rounded bg-emerald-600 px-3 py-2 text-sm font-bold hover:bg-emerald-500">
                                <Save class="h-4 w-4" />
                                <span>{{ studentEditId ? 'تحديث الطالب' : 'إضافة طالب' }}</span>
                            </button>
                        </div>
                    </div>
                </form>
                    </div>
                </div>

                <div class="mb-3 grid grid-cols-1 gap-2 rounded border border-gray-700 bg-gray-800 p-3 md:grid-cols-4">
                    <div>
                        <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-400">
                            <Filter class="h-3.5 w-3.5 text-blue-300" />
                            <span>فلترة حسب المرحلة</span>
                        </label>
                        <select v-model="studentFilterStageId" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                            <option value="">الكل</option>
                            <option v-for="stage in stageOptions" :key="`filter-stage-${stage.id}`" :value="stage.id">{{ stage.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-400">
                            <Filter class="h-3.5 w-3.5 text-blue-300" />
                            <span>فلترة حسب الصف</span>
                        </label>
                        <select v-model="studentFilterGradeName" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                            <option value="">الكل</option>
                            <option v-for="grade in filterGradeOptions" :key="`filter-grade-${grade}`" :value="grade">{{ grade }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 inline-flex items-center gap-1 text-xs text-gray-400">
                            <Filter class="h-3.5 w-3.5 text-blue-300" />
                            <span>فلترة حسب الفصل</span>
                        </label>
                        <select v-model="studentFilterClassroomId" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                            <option value="">الكل</option>
                            <option v-for="classroom in classroomsForFilterScope" :key="`filter-class-${classroom.id}`" :value="classroom.id">
                                {{ classroom.stage_name }} - {{ classroom.grade_name }} - {{ classroom.name }}
                            </option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="button" class="inline-flex items-center gap-1 rounded bg-gray-700 px-3 py-2 text-xs hover:bg-gray-600" @click="studentFilterStageId = ''; studentFilterGradeName = ''; studentFilterClassroomId = ''">
                            <X class="h-3.5 w-3.5" />
                            <span>مسح الفلاتر</span>
                        </button>
                    </div>
                </div>

                <div class="space-y-3 lg:hidden">
                    <article v-for="row in filteredStudentRows" :key="`student-mobile-${row.id}`" class="rounded-2xl border border-gray-700 bg-gray-900 p-4 text-right" :style="stageAccent(row.stage_id, row.stage_name)">
                        <div class="mb-3">
                            <h3 class="font-semibold">{{ row.full_name }}</h3>
                            <p class="text-xs text-gray-500">كود الطالب: {{ row.student_code || '-' }}</p>
                            <p class="text-xs text-gray-500">الرقم الوطني: {{ row.national_id || '-' }}</p>
                        </div>
                        <div class="space-y-2 text-sm">
                            <p><span class="text-xs text-gray-500">المرحلة / الصف / الفصل:</span> {{ row.stage_name }} / {{ row.classroom_grade_name }} / {{ row.classroom_name }}</p>
                            <p><span class="text-xs text-gray-500">الحالة:</span> {{ statusLabel(row.is_active) }}</p>
                        </div>
                        <div class="mt-4 flex flex-wrap justify-end gap-2">
                            <button class="inline-flex items-center gap-1 rounded bg-blue-700 px-3 py-2 text-xs hover:bg-blue-600" @click="openEditStudentModal(row)"><Pencil class="h-3.5 w-3.5" /><span>تعديل</span></button>
                            <button class="inline-flex items-center gap-1 rounded bg-red-700 px-3 py-2 text-xs hover:bg-red-600" @click="removeStudent(row.id)"><Trash2 class="h-3.5 w-3.5" /><span>حذف</span></button>
                        </div>
                    </article>
                </div>

                <div class="hidden overflow-hidden rounded border border-gray-700 lg:block">
                    <table class="w-full text-right text-sm text-gray-200">
                        <thead class="bg-gray-800 text-xs text-gray-400">
                            <tr>
                                <th class="px-3 py-2"><span class="inline-flex items-center gap-1"><UserRound class="h-3.5 w-3.5" />الطالب</span></th>
                                <th class="px-3 py-2"><span class="inline-flex items-center gap-1"><Users class="h-3.5 w-3.5" />كود الطالب</span></th>
                                <th class="px-3 py-2"><span class="inline-flex items-center gap-1"><UserRound class="h-3.5 w-3.5" />الرقم الوطني</span></th>
                                <th class="px-3 py-2"><span class="inline-flex items-center gap-1"><School class="h-3.5 w-3.5" />المرحلة / الصف / الفصل</span></th>
                                <th class="px-3 py-2">الحالة</th>
                                <th class="px-3 py-2 text-left">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700 bg-gray-900">
                            <tr v-for="row in filteredStudentRows" :key="row.id" class="stage-row-accent" :style="stageAccent(row.stage_id, row.stage_name)">
                                <td class="px-3 py-2 font-semibold">{{ row.full_name }}</td>
                                <td class="px-3 py-2">{{ row.student_code || '-' }}</td>
                                <td class="px-3 py-2">{{ row.national_id || '-' }}</td>
                                <td class="px-3 py-2">
                                    <span class="stage-badge" :style="stageAccent(row.stage_id, row.stage_name)">{{ row.stage_name }}</span>
                                    <span class="mx-1">/</span>{{ row.classroom_grade_name }} / {{ row.classroom_name }}
                                </td>
                                <td class="px-3 py-2">{{ statusLabel(row.is_active) }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex justify-end gap-2">
                                        <button class="inline-flex items-center gap-1 rounded bg-blue-700 px-2 py-1 text-xs hover:bg-blue-600" @click="openEditStudentModal(row)">
                                            <Pencil class="h-3.5 w-3.5" />
                                            <span>تعديل</span>
                                        </button>
                                        <button class="inline-flex items-center gap-1 rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600" @click="removeStudent(row.id)">
                                            <Trash2 class="h-3.5 w-3.5" />
                                            <span>حذف</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredStudentRows.length === 0">
                                <td colspan="6" class="px-3 py-6 text-center text-gray-500">لا توجد بيانات مطابقة للفلاتر.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </RoleLayout>
</template>
