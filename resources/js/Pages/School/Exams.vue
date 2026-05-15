<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Award,
    BookOpenText,
    CalendarDays,
    Clock3,
    FileSpreadsheet,
    LayoutTemplate,
    Pencil,
    PlusCircle,
    Save,
    School,
    Settings2,
    ShieldCheck,
    Timer,
    Trash2,
    UserRound,
    X,
} from 'lucide-vue-next';
import AttachmentPanel from '@/Components/AttachmentPanel.vue';
import RoleLayout from '@/Layouts/RoleLayout.vue';
import { useActionDialog } from '@/composables/useActionDialog';
import { stageAccentStyle } from '@/utils/stagePalette';

const props = defineProps({
    school: { type: Object, default: null },
    settings: { type: Object, default: () => ({}) },
    templates: { type: Array, default: () => [] },
    terms: { type: Array, default: () => [] },
    stages: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
    subjectTeacherOptions: { type: Object, default: () => ({}) },
    teachers: { type: Array, default: () => [] },
    exams: { type: Array, default: () => [] },
    questionBankCourseOfferings: { type: Array, default: () => [] },
    questionBank: { type: Array, default: () => [] },
    selectedExamId: { type: Number, default: null },
    selectedExamQuestions: { type: Array, default: () => [] },
    selectedExamScores: { type: Array, default: () => [] },
    selectedExamAttachments: { type: Array, default: () => [] },
    studentsForSelectedExam: { type: Array, default: () => [] },
    templateTypes: { type: Array, default: () => [] },
    questionTypes: { type: Array, default: () => [] },
    questionDifficulties: { type: Array, default: () => [] },
    questionSelectionModes: { type: Array, default: () => [] },
    examStatuses: { type: Array, default: () => [] },
    scoreAttendanceStatuses: { type: Array, default: () => [] },
    permissions: { type: Object, default: () => ({}) },
    isManager: { type: Boolean, default: false },
});

const page = usePage();
const user = computed(() => page.props?.auth?.user || null);
const actionDialog = useActionDialog();
const validationErrors = computed(() => Object.values(page.props?.errors || {}).filter((value) => typeof value === 'string' && value.trim() !== ''));
const roleForLayout = computed(() => (props.isManager ? 'SCHOOL_MANAGER' : (user.value?.primary_role === 'school_manager' ? 'SCHOOL_MANAGER' : 'STAFF')));
const canManageTemplates = computed(() => Boolean(props.permissions?.can_manage_exam_templates));
const canCreateExam = computed(() => Boolean(props.permissions?.can_create_exam ?? props.permissions?.can_manage_school_exams));
const canUpdateExam = computed(() => Boolean(props.permissions?.can_update_exam ?? props.permissions?.can_manage_school_exams));
const canDeleteExam = computed(() => Boolean(props.permissions?.can_delete_exam ?? props.permissions?.can_manage_school_exams));
const canApproveExam = computed(() => Boolean(props.permissions?.can_approve_exam ?? props.permissions?.can_approve_exams));
const canUseQuestionBank = computed(() => Boolean(props.permissions?.can_use_question_bank ?? props.permissions?.can_manage_question_bank));
const canEnterExamScores = computed(() => Boolean(props.permissions?.can_enter_exam_scores ?? props.permissions?.can_record_exam_scores));
const canEditExamScores = computed(() => Boolean(props.permissions?.can_edit_exam_scores ?? props.permissions?.can_record_exam_scores));
const canManageQuestionBank = computed(() => canUseQuestionBank.value);
const canRecordExamScores = computed(() => canEnterExamScores.value || canEditExamScores.value);

const examSectionKeys = ['settings', 'scheduling', 'selected', 'question-bank'];
const requestedExamSection = computed(() => {
    const pageUrl = String(page.url || '');
    const query = pageUrl.includes('?') ? pageUrl.split('?')[1] : '';
    const section = String(new URLSearchParams(query).get('section') || '').trim();

    return examSectionKeys.includes(section) ? section : '';
});

const currentExamSection = computed(() => {
    const requested = requestedExamSection.value;

    // Legacy compatibility: keep old links (?section=settings) working by routing
    // users to scheduling where settings now live.
    if (requested === 'settings') {
        return 'scheduling';
    }

    if (requested === 'question-bank' && !canManageQuestionBank.value) {
        return 'selected';
    }

    if (requested === '') return 'scheduling';

    return requested;
});

const showSchedulingSection = computed(() => currentExamSection.value === 'scheduling');
const showSelectedExamSection = computed(() => currentExamSection.value === 'selected');
const showQuestionBankSection = computed(() => currentExamSection.value === 'question-bank');

const settingsForm = useForm({
    allow_subject_schedule_slot_overlap: Boolean(props.settings?.allow_subject_schedule_slot_overlap),
    exam_day_start_time: props.settings?.exam_day_start_time ? String(props.settings.exam_day_start_time).slice(0, 5) : '',
    exam_day_end_time: props.settings?.exam_day_end_time ? String(props.settings.exam_day_end_time).slice(0, 5) : '',
});

const templateEditId = ref(null);
const templateForm = useForm({
    name: '',
    exam_type: props.templateTypes[0]?.value || 'weekly',
    default_max_score: 100,
    default_passing_score: 50,
    requires_approval: false,
    teacher_can_override_max_score: true,
    teacher_can_override_passing_score: true,
    affects_final_result: true,
    is_active: true,
    sort_order: 0,
    notes: '',
});

const resetTemplateForm = () => {
    templateEditId.value = null;
    templateForm.reset();
    templateForm.exam_type = props.templateTypes[0]?.value || 'weekly';
    templateForm.default_max_score = 100;
    templateForm.default_passing_score = 50;
};

const editTemplate = (row) => {
    templateEditId.value = row.id;
    Object.assign(templateForm, {
        name: row.name || '',
        exam_type: row.exam_type || 'weekly',
        default_max_score: Number(row.default_max_score ?? 100),
        default_passing_score: Number(row.default_passing_score ?? 50),
        requires_approval: Boolean(row.requires_approval),
        teacher_can_override_max_score: Boolean(row.teacher_can_override_max_score),
        teacher_can_override_passing_score: Boolean(row.teacher_can_override_passing_score),
        affects_final_result: Boolean(row.affects_final_result),
        is_active: Boolean(row.is_active),
        sort_order: Number(row.sort_order || 0),
        notes: row.notes || '',
    });
};

const submitTemplate = () => {
    const options = { preserveScroll: true, preserveState: true, onSuccess: () => resetTemplateForm() };
    if (templateEditId.value) templateForm.put(route('school.exams.templates.update', templateEditId.value), options);
    else templateForm.post(route('school.exams.templates.store'), options);
};

const deleteTemplate = async (id) => {
    const confirmed = await actionDialog.confirm({
        title: 'حذف مسمى الاختبار',
        message: 'سيتم حذف مسمى الاختبار نهائيًا. هل تريد المتابعة؟',
        confirmText: 'نعم، احذف',
        cancelText: 'إلغاء',
        variant: 'danger',
    });
    if (!confirmed) return;
    useForm({}).delete(route('school.exams.templates.destroy', id), { preserveScroll: true, preserveState: true });
};

const examEditId = ref(null);
const pendingExamAttachments = ref([]);
const examForm = useForm({
    school_exam_template_id: '',
    school_term_id: props.terms[0]?.id || '',
    school_stage_id: props.stages[0]?.id || '',
    school_classroom_id: '',
    school_subject_id: props.subjects[0]?.id || '',
    teacher_user_id: '',
    title: '',
    exam_date: '',
    starts_at: '',
    ends_at: '',
    max_score: 100,
    passing_score: 50,
    allow_subject_schedule_overlap: Boolean(props.settings?.allow_subject_schedule_slot_overlap),
    room_label: '',
    notes: '',
    attachments: [],
});

const classroomOptions = computed(() => props.stages.find((s) => Number(s.id) === Number(examForm.school_stage_id))?.classrooms || []);
const backendTeacherOptionsForSubject = computed(() => {
    const subjectId = Number(examForm.school_subject_id || 0);
    if (!subjectId) return [];

    const map = props.subjectTeacherOptions || {};
    const rows = map[subjectId] || map[String(subjectId)] || [];
    if (!Array.isArray(rows)) return [];

    return rows
        .map((row) => ({
            id: Number(row?.id ?? row?.teacher_user_id ?? 0),
            name: row?.name || row?.teacher_name || '',
        }))
        .filter((row) => row.id > 0 && row.name !== '');
});
const subjectTeacherIds = computed(() => {
    const subject = props.subjects.find((row) => Number(row.id) === Number(examForm.school_subject_id));
    const assignments = subject?.teacher_assignments || subject?.teacherAssignments || [];

    return collectUniqueNumericIds(assignments.map((assignment) => assignment?.teacher_user_id));
});
const teacherOptionsForExam = computed(() => {
    const optionsById = new Map();
    const pushOption = (id, name) => {
        const normalizedId = Number(id || 0);
        const normalizedName = String(name || '').trim();
        if (!Number.isInteger(normalizedId) || normalizedId <= 0 || normalizedName === '') return;
        if (!optionsById.has(normalizedId)) {
            optionsById.set(normalizedId, {
                id: normalizedId,
                name: normalizedName,
            });
        }
    };

    backendTeacherOptionsForSubject.value.forEach((row) => {
        pushOption(row.id, row.name);
    });

    const ids = subjectTeacherIds.value;
    if (ids.length > 0) {
        const allowed = new Set(ids.map((id) => Number(id)));
        props.teachers
            .filter((teacher) => allowed.has(Number(teacher.id)))
            .forEach((teacher) => {
                pushOption(teacher.id, teacher.name);
            });

        ids.forEach((teacherId) => {
            pushOption(teacherId, teacherNamesById.value[Number(teacherId)] || '');
        });

        return Array.from(optionsById.values());
    }

    props.teachers.forEach((teacher) => {
        pushOption(teacher.id, teacher.name);
    });

    return Array.from(optionsById.values());
});
const teacherNamesById = computed(() => {
    const names = Object.fromEntries(
        props.teachers.map((teacher) => [Number(teacher.id), teacher.name || ''])
    );

    Object.values(props.subjectTeacherOptions || {}).forEach((rows) => {
        if (!Array.isArray(rows)) return;
        rows.forEach((row) => {
            const id = Number(row?.id ?? row?.teacher_user_id ?? 0);
            const name = row?.name || row?.teacher_name || '';
            if (id > 0 && name !== '' && !names[id]) names[id] = name;
        });
    });

    return names;
});

const syncExamTeacherSelection = () => {
    const allowedTeacherIds = teacherOptionsForExam.value.map((row) => Number(row.id));
    if (!allowedTeacherIds.includes(Number(examForm.teacher_user_id))) {
        examForm.teacher_user_id = teacherOptionsForExam.value[0]?.id || '';
    }
};

watch(() => examForm.school_stage_id, () => {
    const ids = classroomOptions.value.map((row) => Number(row.id));
    if (!ids.includes(Number(examForm.school_classroom_id))) examForm.school_classroom_id = classroomOptions.value[0]?.id || '';
}, { immediate: true });
watch(() => examForm.school_subject_id, syncExamTeacherSelection, { immediate: true });
watch(
    () => settingsForm.allow_subject_schedule_slot_overlap,
    (value) => {
        if (!examEditId.value) {
            examForm.allow_subject_schedule_overlap = Boolean(value);
        }
    }
);

const examTeacherName = (exam) => {
    return exam?.teacher?.name || teacherNamesById.value[Number(exam?.teacher_user_id)] || '-';
};

const stageNameById = (stageId) => (
    props.stages.find((stage) => Number(stage.id) === Number(stageId))?.name || ''
);

const stageNameForExam = (exam) => (
    exam?.stage?.name || stageNameById(exam?.school_stage_id)
);

const stageNameForQuestion = (question) => (
    question?.stage?.name || stageNameById(question?.school_stage_id)
);

const stageAccent = (stageId, stageName = '') => stageAccentStyle(stageId, stageName);

const selectedExamTemplate = computed(() =>
    props.templates.find((row) => Number(row.id) === Number(examForm.school_exam_template_id)) || null
);

watch(() => examForm.school_exam_template_id, (id) => {
    const item = props.templates.find((row) => Number(row.id) === Number(id));
    if (!item) return;
    examForm.title = item.name || '';
    examForm.max_score = Number(item.default_max_score ?? 100);
    examForm.passing_score = Number(item.default_passing_score ?? 50);
});

const resetExamForm = () => {
    examEditId.value = null;
    examForm.reset();
    examForm.school_term_id = props.terms[0]?.id || '';
    examForm.school_stage_id = props.stages[0]?.id || '';
    examForm.school_classroom_id = classroomOptions.value[0]?.id || '';
    examForm.school_subject_id = props.subjects[0]?.id || '';
    examForm.teacher_user_id = '';
    examForm.max_score = 100;
    examForm.passing_score = 50;
    examForm.allow_subject_schedule_overlap = Boolean(settingsForm.allow_subject_schedule_slot_overlap);
    examForm.attachments = [];
    pendingExamAttachments.value = [];
    syncExamTeacherSelection();
};

const isExamModalOpen = ref(false);

const openCreateExamModal = () => {
    if (!canCreateExam.value) return;
    resetExamForm();
    examForm.clearErrors();
    isExamModalOpen.value = true;
};

const openEditExamModal = (exam) => {
    if (!canUpdateExam.value) return;
    editExam(exam);
    examForm.clearErrors();
    isExamModalOpen.value = true;
};

const closeExamModal = () => {
    isExamModalOpen.value = false;
    resetExamForm();
    examForm.clearErrors();
};

const editExam = (exam) => {
    examEditId.value = exam.id;
    Object.assign(examForm, {
        school_exam_template_id: exam.school_exam_template_id || '',
        school_term_id: exam.school_term_id || '',
        school_stage_id: exam.school_stage_id || '',
        school_classroom_id: exam.school_classroom_id || '',
        school_subject_id: exam.school_subject_id || '',
        teacher_user_id: exam.teacher_user_id || '',
        title: exam.title || '',
        exam_date: exam.exam_date ? String(exam.exam_date).slice(0, 10) : '',
        starts_at: exam.starts_at ? String(exam.starts_at).slice(0, 5) : '',
        ends_at: exam.ends_at ? String(exam.ends_at).slice(0, 5) : '',
        max_score: Number(exam.max_score ?? 100),
        passing_score: Number(exam.passing_score ?? 50),
        allow_subject_schedule_overlap: Boolean(exam.allow_subject_schedule_overlap),
        room_label: exam.room_label || '',
        notes: exam.notes || '',
        attachments: [],
    });
    syncExamTeacherSelection();
};

const appendExamAttachmentFiles = (fileList) => {
    const incoming = Array.from(fileList || []).filter((file) => file instanceof File);
    if (incoming.length === 0) return;

    const merged = [...pendingExamAttachments.value, ...incoming];
    pendingExamAttachments.value = merged.slice(0, 10);
};

const removePendingExamAttachment = (index) => {
    pendingExamAttachments.value = pendingExamAttachments.value.filter((_, itemIndex) => itemIndex !== index);
};

const clearPendingExamAttachments = () => {
    pendingExamAttachments.value = [];
    examForm.attachments = [];
};

const examAttachmentErrors = computed(() => [
    examForm.errors.attachments,
    examForm.errors['attachments.0'],
].filter((value) => typeof value === 'string' && value.trim() !== ''));

const deleteExamAttachment = async (attachment) => {
    if (!attachment?.id || !canUpdateExam.value) return;

    const confirmed = await actionDialog.confirm({
        title: 'حذف المرفق',
        message: 'سيتم حذف هذا المرفق من الاختبار المحدد. هل تريد المتابعة؟',
        confirmText: 'نعم، احذف',
        cancelText: 'إلغاء',
        variant: 'danger',
    });

    if (!confirmed) return;

    router.delete(route('school.attachments.destroy', { attachment: attachment.id }), {
        preserveScroll: true,
        preserveState: true,
    });
};

const submitExam = () => {
    if (examEditId.value && !canUpdateExam.value) return;
    if (!examEditId.value && !canCreateExam.value) return;

    // Avoid duplicate control in scheduling form; this follows global exam settings.
    examForm.allow_subject_schedule_overlap = Boolean(settingsForm.allow_subject_schedule_slot_overlap);
    examForm.attachments = [...pendingExamAttachments.value];

    const options = {
        preserveScroll: true,
        preserveState: true,
        forceFormData: true,
        onSuccess: () => {
            clearPendingExamAttachments();
            closeExamModal();
        },
    };
    if (examEditId.value) examForm.put(route('school.exams.update', examEditId.value), options);
    else examForm.post(route('school.exams.store'), options);
};

const deleteExam = async (id) => {
    if (!canDeleteExam.value) return;
    const confirmed = await actionDialog.confirm({
        title: 'حذف الاختبار',
        message: 'سيتم حذف الاختبار نهائيًا. هل تريد المتابعة؟',
        confirmText: 'نعم، احذف',
        cancelText: 'إلغاء',
        variant: 'danger',
    });
    if (!confirmed) return;
    useForm({}).delete(route('school.exams.destroy', id), { preserveScroll: true, preserveState: true });
};

function collectUniqueNumericIds(ids) {
    return [...new Set((ids || []).map((value) => Number(value)).filter((value) => Number.isInteger(value) && value > 0))];
}

const selectedExamId = ref(props.selectedExamId || null);
const loadExam = () => {
    router.get(
        route('school.exams.index'),
        { exam_id: selectedExamId.value, section: currentExamSection.value },
        { preserveScroll: true, preserveState: true, replace: true }
    );
};

const statusForm = useForm({ status: '', reason: '' });
const updateStatus = (exam) => {
    if (!canUpdateExam.value) return;
    if (['approved', 'published', 'closed'].includes(String(statusForm.status || '')) && !canApproveExam.value) return;
    if (!statusForm.status) return;
    statusForm.post(route('school.exams.status.update', exam.id), { preserveScroll: true, preserveState: true, onSuccess: () => { statusForm.status = ''; statusForm.reason = ''; } });
};

const questionBankCourseOfferingLabel = (offering) => {
    const subject = offering?.subject?.name || '-';
    const stage = offering?.stage?.name || '-';
    const classroom = `${offering?.classroom?.grade_name || '-'} / ${offering?.classroom?.name || '-'}`;
    const term = offering?.term?.name || '-';
    return `${subject} | ${stage} | ${classroom} | ${term}`;
};

const questionBankCourseOfferingOptions = computed(() =>
    (props.questionBankCourseOfferings || []).map((offering) => ({
        ...offering,
        label: questionBankCourseOfferingLabel(offering),
    }))
);

const questionBankCourseOfferingLabelsById = computed(() => {
    const entries = questionBankCourseOfferingOptions.value.map((offering) => [Number(offering.id), offering.label]);
    return new Map(entries);
});

const questionBankCourseOfferingLabelById = (courseOfferingId) =>
    questionBankCourseOfferingLabelsById.value.get(Number(courseOfferingId)) || 'غير محدد';

const DEFAULT_STUDY_PLAN_BRANCH_NAME = 'الفرع الرئيسي';

const questionForm = useForm({
    school_course_offering_id: props.questionBankCourseOfferings[0]?.id || '',
    school_subject_id: props.subjects[0]?.id || '',
    school_stage_id: '',
    school_term_id: '',
    branch_name: '',
    unit_name: '',
    chapter_name: '',
    lesson_name: '',
    question_text: '',
    question_type: props.questionTypes[0]?.value || 'multiple_choice',
    question_score: 1,
    selection_mode: 'required',
    difficulty: 'medium',
    status: 'active',
    learning_outcome: '',
    model_answer: '',
    answer_explanation: '',
    tags: '',
    options: [{ option_text: '', is_correct: false, sort_order: 1 }, { option_text: '', is_correct: false, sort_order: 2 }],
});

const questionEditId = ref(null);
const canUseQuestionOptions = computed(() => ['multiple_choice', 'true_false'].includes(String(questionForm.question_type)));
const questionStatusOptions = [
    { value: 'draft', label: 'مسودة' },
    { value: 'active', label: 'فعال' },
    { value: 'archived', label: 'أرشيف' },
];

const selectedQuestionCourseOffering = computed(() =>
    questionBankCourseOfferingOptions.value.find((offering) => Number(offering.id) === Number(questionForm.school_course_offering_id)) || null
);

const normalizeStudyPlanUnits = (units) =>
    [...(units || [])]
        .sort((a, b) => Number(a?.sort_order ?? 0) - Number(b?.sort_order ?? 0))
        .map((unit) => ({
            branch_name: String(unit?.branch_name || '').trim() || DEFAULT_STUDY_PLAN_BRANCH_NAME,
            name: String(unit?.name || '').trim(),
            lessons: [...(unit?.lessons || [])]
                .sort((a, b) => Number(a?.sort_order ?? 0) - Number(b?.sort_order ?? 0))
                .map((lesson) => ({
                    name: String(lesson?.name || '').trim(),
                    topics: [...(lesson?.topics || [])]
                        .sort((a, b) => Number(a?.sort_order ?? 0) - Number(b?.sort_order ?? 0))
                        .map((topic) => String(topic?.name || '').trim())
                        .filter((topicName) => topicName !== ''),
                }))
                .filter((lesson) => lesson.name !== ''),
        }))
        .filter((unit) => unit.name !== '');

const selectedQuestionCourseOfferingStudyPlanUnits = computed(() => {
    const offering = selectedQuestionCourseOffering.value;
    const units = offering?.study_plan_units || offering?.studyPlanUnits || [];

    return normalizeStudyPlanUnits(units);
});

const questionBankBranchesForSelectedOffering = computed(() => {
    const values = selectedQuestionCourseOfferingStudyPlanUnits.value
        .map((unit) => String(unit.branch_name || '').trim())
        .filter((value) => value !== '');

    return [...new Set(values)];
});

const selectedQuestionCourseUnitsByBranch = computed(() => {
    const selectedBranchName = String(questionForm.branch_name || '').trim();
    if (selectedBranchName === '') return [];

    return selectedQuestionCourseOfferingStudyPlanUnits.value.filter(
        (unit) => String(unit.branch_name || '').trim() === selectedBranchName
    );
});

const questionBankUnitsForSelectedOffering = computed(() =>
    selectedQuestionCourseUnitsByBranch.value.map((unit) => unit.name)
);

const selectedQuestionCourseUnit = computed(() => {
    const selectedUnitName = String(questionForm.unit_name || '').trim();
    if (selectedUnitName === '') return null;

    return selectedQuestionCourseUnitsByBranch.value.find((unit) => unit.name === selectedUnitName) || null;
});

const questionBankLessonsForSelectedOffering = computed(() =>
    (selectedQuestionCourseUnit.value?.lessons || []).map((lesson) => lesson.name)
);

const selectedQuestionCourseLesson = computed(() => {
    const selectedLessonName = String(questionForm.lesson_name || '').trim();
    if (selectedLessonName === '') return null;

    return (selectedQuestionCourseUnit.value?.lessons || []).find((lesson) => lesson.name === selectedLessonName) || null;
});

const questionBankTopicsForSelectedOffering = computed(() =>
    selectedQuestionCourseLesson.value?.topics || []
);

const syncQuestionScopeFromCourseOffering = () => {
    const offering = selectedQuestionCourseOffering.value;
    if (!offering) return;

    questionForm.school_subject_id = offering.school_subject_id || '';
    questionForm.school_stage_id = offering.school_stage_id || '';
    questionForm.school_term_id = offering.school_term_id || '';
    if (!questionBankBranchesForSelectedOffering.value.includes(String(questionForm.branch_name || '').trim())) {
        questionForm.branch_name = questionBankBranchesForSelectedOffering.value[0] || '';
    }
};

const resolveQuestionBranchName = (question) => {
    const offering = questionBankCourseOfferingOptions.value.find(
        (row) => Number(row.id) === Number(question?.school_course_offering_id || 0)
    );
    if (!offering) return '';

    const units = normalizeStudyPlanUnits(offering?.study_plan_units || offering?.studyPlanUnits || []);
    if (units.length === 0) return '';

    const unitName = String(question?.unit_name || '').trim();
    const lessonName = String(question?.lesson_name || '').trim();
    const topicName = String(question?.chapter_name || '').trim();

    const matchedUnit = units.find((unit) => {
        if (unit.name !== unitName) return false;
        if (lessonName === '') return true;

        return (unit.lessons || []).some((lesson) => {
            if (lesson.name !== lessonName) return false;
            if (topicName === '') return true;

            return (lesson.topics || []).includes(topicName);
        });
    });

    return matchedUnit?.branch_name || units[0]?.branch_name || DEFAULT_STUDY_PLAN_BRANCH_NAME;
};

const resetQuestionForm = () => {
    questionEditId.value = null;
    questionForm.reset();
    questionForm.school_course_offering_id = questionBankCourseOfferingOptions.value[0]?.id || '';
    questionForm.school_subject_id = props.subjects[0]?.id || '';
    questionForm.question_type = props.questionTypes[0]?.value || 'multiple_choice';
    questionForm.selection_mode = props.questionSelectionModes[0]?.value || 'required';
    questionForm.difficulty = props.questionDifficulties[0]?.value || 'medium';
    questionForm.status = 'active';
    questionForm.question_score = 1;
    questionForm.branch_name = '';
    questionForm.unit_name = '';
    questionForm.lesson_name = '';
    questionForm.chapter_name = '';
    questionForm.options = [
        { option_text: '', is_correct: false, sort_order: 1 },
        { option_text: '', is_correct: false, sort_order: 2 },
    ];
    syncQuestionScopeFromCourseOffering();
};

const editQuestion = (question) => {
    questionEditId.value = question.id;
    Object.assign(questionForm, {
        school_course_offering_id: question.school_course_offering_id || '',
        school_subject_id: question.school_subject_id || '',
        school_stage_id: question.school_stage_id || '',
        school_term_id: question.school_term_id || '',
        branch_name: resolveQuestionBranchName(question),
        unit_name: question.unit_name || '',
        chapter_name: question.chapter_name || '',
        lesson_name: question.lesson_name || '',
        question_text: question.question_text || '',
        question_type: question.question_type || (props.questionTypes[0]?.value || 'multiple_choice'),
        question_score: Number(question.question_score ?? 1),
        selection_mode: question.selection_mode || (props.questionSelectionModes[0]?.value || 'required'),
        difficulty: question.difficulty || (props.questionDifficulties[0]?.value || 'medium'),
        status: question.status || 'active',
        learning_outcome: question.learning_outcome || '',
        model_answer: question.model_answer || '',
        answer_explanation: question.answer_explanation || '',
        tags: Array.isArray(question.tags) ? question.tags.join(', ') : (question.tags || ''),
        options: (question.options || []).map((item, index) => ({
            option_text: item.option_text || '',
            is_correct: Boolean(item.is_correct),
            sort_order: Number(item.sort_order || index + 1),
        })),
    });

    if (!questionForm.options.length && canUseQuestionOptions.value) {
        questionForm.options = [
            { option_text: '', is_correct: false, sort_order: 1 },
            { option_text: '', is_correct: false, sort_order: 2 },
        ];
    }
};

const addQuestionOption = () => {
    questionForm.options.push({
        option_text: '',
        is_correct: false,
        sort_order: questionForm.options.length + 1,
    });
};

const removeQuestionOption = (index) => {
    if (questionForm.options.length <= 2) return;
    questionForm.options.splice(index, 1);
    questionForm.options = questionForm.options.map((item, idx) => ({
        ...item,
        sort_order: idx + 1,
    }));
};

const deleteQuestion = async (id) => {
    if (!canManageQuestionBank.value) return;
    const confirmed = await actionDialog.confirm({
        title: 'حذف السؤال',
        message: 'سيتم حذف السؤال من بنك الأسئلة نهائيًا. هل تريد المتابعة؟',
        confirmText: 'نعم، احذف',
        cancelText: 'إلغاء',
        variant: 'danger',
    });
    if (!confirmed) return;
    useForm({}).delete(route('school.exams.question_bank.destroy', id), {
        preserveScroll: true,
        preserveState: true,
    });
};

const submitQuestion = () => {
    if (!canManageQuestionBank.value) return;

    const payload = {
        ...questionForm.data(),
        tags: typeof questionForm.tags === 'string'
            ? questionForm.tags.split(',').map((item) => item.trim()).filter(Boolean)
            : questionForm.tags,
        options: canUseQuestionOptions.value ? questionForm.options : [],
    };
    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => resetQuestionForm(),
    };
    if (questionEditId.value) questionForm.transform(() => payload).put(route('school.exams.question_bank.update', questionEditId.value), options);
    else questionForm.transform(() => payload).post(route('school.exams.question_bank.store'), options);
};

watch(
    () => questionForm.question_type,
    (type) => {
        if (!['multiple_choice', 'true_false'].includes(String(type))) {
            questionForm.options = [];
            return;
        }

        if (questionForm.options.length === 0) {
            questionForm.options = [
                { option_text: '', is_correct: false, sort_order: 1 },
                { option_text: '', is_correct: false, sort_order: 2 },
            ];
        }
    },
    { immediate: true }
);

watch(
    () => questionForm.school_course_offering_id,
    () => {
        syncQuestionScopeFromCourseOffering();

        const normalizedBranch = String(questionForm.branch_name || '').trim();
        if (normalizedBranch !== '' && !questionBankBranchesForSelectedOffering.value.includes(normalizedBranch)) {
            questionForm.branch_name = questionBankBranchesForSelectedOffering.value[0] || '';
        }

        const normalizedUnit = String(questionForm.unit_name || '').trim();
        if (normalizedUnit !== '' && !questionBankUnitsForSelectedOffering.value.includes(normalizedUnit)) {
            questionForm.unit_name = '';
        }

        const normalizedLesson = String(questionForm.lesson_name || '').trim();
        if (normalizedLesson !== '' && !questionBankLessonsForSelectedOffering.value.includes(normalizedLesson)) {
            questionForm.lesson_name = '';
        }

        const normalizedTopic = String(questionForm.chapter_name || '').trim();
        if (normalizedTopic !== '' && !questionBankTopicsForSelectedOffering.value.includes(normalizedTopic)) {
            questionForm.chapter_name = '';
        }
    },
    { immediate: true }
);

watch(
    () => questionForm.branch_name,
    () => {
        const normalizedUnit = String(questionForm.unit_name || '').trim();
        if (normalizedUnit !== '' && !questionBankUnitsForSelectedOffering.value.includes(normalizedUnit)) {
            questionForm.unit_name = '';
        }

        const normalizedLesson = String(questionForm.lesson_name || '').trim();
        if (normalizedLesson !== '' && !questionBankLessonsForSelectedOffering.value.includes(normalizedLesson)) {
            questionForm.lesson_name = '';
        }

        const normalizedTopic = String(questionForm.chapter_name || '').trim();
        if (normalizedTopic !== '' && !questionBankTopicsForSelectedOffering.value.includes(normalizedTopic)) {
            questionForm.chapter_name = '';
        }
    }
);

watch(
    () => questionForm.unit_name,
    () => {
        const normalizedLesson = String(questionForm.lesson_name || '').trim();
        if (normalizedLesson !== '' && !questionBankLessonsForSelectedOffering.value.includes(normalizedLesson)) {
            questionForm.lesson_name = '';
        }

        const normalizedTopic = String(questionForm.chapter_name || '').trim();
        if (normalizedTopic !== '' && !questionBankTopicsForSelectedOffering.value.includes(normalizedTopic)) {
            questionForm.chapter_name = '';
        }
    }
);

watch(
    () => questionForm.lesson_name,
    () => {
        const normalizedTopic = String(questionForm.chapter_name || '').trim();
        if (normalizedTopic !== '' && !questionBankTopicsForSelectedOffering.value.includes(normalizedTopic)) {
            questionForm.chapter_name = '';
        }
    }
);

const questionFilters = ref({
    course_offering_id: '',
    subject_id: '',
    type: '',
    difficulty: '',
    unit_name: '',
    chapter_name: '',
    lesson_name: '',
    tag: '',
    learning_outcome: '',
});

const resetQuestionFilters = () => {
    questionFilters.value = {
        course_offering_id: '',
        subject_id: '',
        type: '',
        difficulty: '',
        unit_name: '',
        chapter_name: '',
        lesson_name: '',
        tag: '',
        learning_outcome: '',
    };
};

const filteredQuestionBank = computed(() => {
    const offeringId = Number(questionFilters.value.course_offering_id || 0);
    const subjectId = Number(questionFilters.value.subject_id || 0);
    const type = String(questionFilters.value.type || '').trim();
    const difficulty = String(questionFilters.value.difficulty || '').trim();
    const unitName = String(questionFilters.value.unit_name || '').trim().toLowerCase();
    const chapterName = String(questionFilters.value.chapter_name || '').trim().toLowerCase();
    const lessonName = String(questionFilters.value.lesson_name || '').trim().toLowerCase();
    const tag = String(questionFilters.value.tag || '').trim().toLowerCase();
    const learningOutcome = String(questionFilters.value.learning_outcome || '').trim().toLowerCase();

    return (props.questionBank || []).filter((question) => {
        if (offeringId > 0 && Number(question.school_course_offering_id) !== offeringId) return false;
        if (subjectId > 0 && Number(question.school_subject_id) !== subjectId) return false;
        if (type !== '' && String(question.question_type) !== type) return false;
        if (difficulty !== '' && String(question.difficulty) !== difficulty) return false;
        if (unitName !== '' && !String(question.unit_name || '').toLowerCase().includes(unitName)) return false;
        if (chapterName !== '' && !String(question.chapter_name || '').toLowerCase().includes(chapterName)) return false;
        if (lessonName !== '' && !String(question.lesson_name || '').toLowerCase().includes(lessonName)) return false;
        if (learningOutcome !== '' && !String(question.learning_outcome || '').toLowerCase().includes(learningOutcome)) return false;

        if (tag !== '') {
            const tags = Array.isArray(question.tags)
                ? question.tags.map((item) => String(item).toLowerCase())
                : String(question.tags || '').split(',').map((item) => item.trim().toLowerCase()).filter(Boolean);

            if (!tags.some((item) => item.includes(tag))) return false;
        }

        return true;
    });
});

const selectedExam = computed(() =>
    (props.exams || []).find((exam) => Number(exam.id) === Number(selectedExamId.value || 0)) || null
);

const selectedExamQuestionIds = computed(() =>
    new Set((examQuestionDraft.value || []).map((row) => Number(row.school_question_bank_item_id)))
);

const selectedExamCandidateQuestions = computed(() => {
    const exam = selectedExam.value;
    if (!exam) return [];

    const examSubjectId = Number(exam.school_subject_id || 0);
    const examStageId = Number(exam.school_stage_id || 0);
    const examTermId = Number(exam.school_term_id || 0);
    const examClassroomId = Number(exam.school_classroom_id || 0);

    return (props.questionBank || []).filter((question) => {
        if (String(question.status || '') !== 'active') return false;

        const questionSubjectId = Number(question.school_subject_id || 0);
        if (examSubjectId > 0 && questionSubjectId > 0 && questionSubjectId !== examSubjectId) return false;

        const questionStageId = Number(question.school_stage_id || 0);
        if (examStageId > 0 && questionStageId > 0 && questionStageId !== examStageId) return false;

        const questionTermId = Number(question.school_term_id || 0);
        if (examTermId > 0 && questionTermId > 0 && questionTermId !== examTermId) return false;

        const courseOffering = question.course_offering || question.courseOffering || null;
        const questionClassroomId = Number(
            courseOffering?.school_classroom_id
            ?? question.school_classroom_id
            ?? 0
        );
        if (examClassroomId > 0 && questionClassroomId > 0 && questionClassroomId !== examClassroomId) return false;

        return true;
    });
});

const selectedExamAvailableQuestions = computed(() =>
    selectedExamCandidateQuestions.value.filter(
        (question) => !selectedExamQuestionIds.value.has(Number(question.id))
    )
);

const examQuestionDraft = ref([]);
watch(
    () => props.selectedExamQuestions,
    (rows) => {
        examQuestionDraft.value = (rows || []).map((row, idx) => ({
            school_question_bank_item_id: row.school_question_bank_item_id,
            score: Number(row.score || row.question?.question_score || 1),
            is_required: Boolean(row.is_required),
            sort_order: Number(row.sort_order || idx + 1),
            label: row.question?.question_text || `#${row.school_question_bank_item_id}`,
        }));
    },
    { immediate: true }
);

const addQuestionToExam = (question) => {
    if (!canUseQuestionBank.value || !canUpdateExam.value) return;
    if (!selectedExamId.value) return;
    const exists = examQuestionDraft.value.some((row) => Number(row.school_question_bank_item_id) === Number(question.id));
    if (exists) return;
    examQuestionDraft.value.push({
        school_question_bank_item_id: Number(question.id),
        score: Number(question.question_score || 1),
        is_required: true,
        sort_order: examQuestionDraft.value.length + 1,
        label: question.question_text || `#${question.id}`,
    });
};

const removeQuestionFromExam = (questionId) => {
    examQuestionDraft.value = examQuestionDraft.value.filter((row) => Number(row.school_question_bank_item_id) !== Number(questionId));
};

const examQuestionScoreTotal = computed(() =>
    examQuestionDraft.value.reduce((sum, row) => sum + Number(row.score || 0), 0)
);

const syncQuestions = () => {
    if (!canUpdateExam.value || !canUseQuestionBank.value) return;
    if (!selectedExamId.value) return;
    const rows = (examQuestionDraft.value || []).map((row, idx) => ({
        school_question_bank_item_id: row.school_question_bank_item_id,
        score: Number(row.score || 1),
        is_required: Boolean(row.is_required),
        sort_order: Number(row.sort_order || idx + 1),
    }));
    useForm({ questions: rows }).post(route('school.exams.questions.sync', selectedExamId.value), { preserveScroll: true, preserveState: true });
};

const submitScores = () => {
    if (!canRecordExamScores.value) return;
    if (!selectedExamId.value) return;
    useForm({
        scores: scoreDraft.value.map((row) => ({
            school_student_id: Number(row.school_student_id),
            score: row.score === '' || row.score === null ? null : Number(row.score),
            attendance_status: row.attendance_status || 'present',
            notes: row.notes || '',
            is_finalized: Boolean(row.is_finalized),
        })),
    }).post(route('school.exams.scores.upsert', selectedExamId.value), { preserveScroll: true, preserveState: true });
};

const scoreDraft = ref([]);
watch(
    () => [props.studentsForSelectedExam, props.selectedExamScores],
    () => {
        const scoreMap = new Map((props.selectedExamScores || []).map((row) => [Number(row.school_student_id), row]));
        scoreDraft.value = (props.studentsForSelectedExam || []).map((student) => {
            const row = scoreMap.get(Number(student.id));
            return {
                school_student_id: Number(student.id),
                full_name: student.full_name,
                student_code: student.student_code,
                score: row?.score ?? '',
                attendance_status: row?.attendance_status || 'present',
                notes: row?.notes || '',
                is_finalized: Boolean(row?.is_finalized),
            };
        });
    },
    { immediate: true }
);
</script>

<template>
    <Head title="الاختبارات وبنك الأسئلة" />

    <RoleLayout title="الاختبارات وبنك الأسئلة" :role="roleForLayout" :permissions="props.permissions">
        <div v-if="!school" class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm text-amber-200">لا يوجد ربط بمدرسة لهذا الحساب حاليًا.</div>
        <div v-else class="space-y-6">
            <section v-if="validationErrors.length" class="rounded-xl border border-red-500/40 bg-red-500/10 p-4 text-sm text-red-100">
                <p class="mb-2 font-semibold">تعذر تنفيذ العملية للأسباب التالية:</p>
                <ul class="space-y-1">
                    <li v-for="(error, index) in validationErrors" :key="`validation-error-${index}`">
                        {{ error }}
                    </li>
                </ul>
            </section>

            <section class="rounded-xl border border-gray-800 bg-gray-900 p-4">
                <p class="inline-flex items-center gap-1 text-xs text-gray-400">
                    <School class="h-3.5 w-3.5" />
                    المدرسة
                </p>
                <p class="text-lg font-bold">{{ school.name }}</p>
                <p class="text-xs text-gray-500">{{ school.school_id }}</p>
            </section>

            <section v-if="showSchedulingSection" class="rounded-xl border border-blue-700/40 bg-gray-900 p-4">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="inline-flex items-center gap-2 text-lg font-bold">
                            <FileSpreadsheet class="h-5 w-5 text-blue-300" />
                            جدول الاختبارات
                        </h2>
                        <p class="text-xs text-gray-400">تم دمج إعدادات ومسميات الاختبارات داخل نفس صفحة الجدول.</p>
                    </div>
                    <button
                        v-if="canCreateExam"
                        type="button"
                        class="inline-flex items-center gap-2 rounded bg-emerald-600 px-3 py-2 text-sm font-bold hover:bg-emerald-500"
                        @click="openCreateExamModal"
                    >
                        <PlusCircle class="h-4 w-4" />
                        إنشاء جدول اختبار
                    </button>
                </div>

                <div v-if="canManageTemplates" class="mb-4 rounded-xl border border-cyan-700/40 bg-gray-900/60 p-4">
                    <h3 class="mb-3 inline-flex items-center gap-2 text-base font-bold">
                        <Settings2 class="h-4 w-4 text-cyan-300" />
                        الإعدادات ومسميات الاختبارات
                    </h3>
                    <form class="mb-3 grid grid-cols-1 gap-2 md:grid-cols-4" @submit.prevent="settingsForm.put(route('school.exams.settings.update'), { preserveScroll: true, preserveState: true })">
                        <label class="space-y-1 text-xs text-gray-400 md:col-span-2">
                            <span class="inline-flex items-center gap-1">
                                <ShieldCheck class="h-3.5 w-3.5" />
                                السماح باستبدال زمن حصة المادة
                            </span>
                            <span class="inline-flex items-center gap-2 text-sm text-white">
                                <input v-model="settingsForm.allow_subject_schedule_slot_overlap" type="checkbox" class="rounded border-gray-600 bg-gray-900 text-blue-500" />
                                مفعل
                            </span>
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="inline-flex items-center gap-1">
                                <Clock3 class="h-3.5 w-3.5" />
                                بداية يوم الاختبارات
                            </span>
                            <input v-model="settingsForm.exam_day_start_time" type="time" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="inline-flex items-center gap-1">
                                <Clock3 class="h-3.5 w-3.5" />
                                نهاية يوم الاختبارات
                            </span>
                            <input v-model="settingsForm.exam_day_end_time" type="time" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                        </label>
                        <button class="inline-flex items-center gap-2 rounded bg-emerald-600 px-3 py-2 text-sm font-bold hover:bg-emerald-500 md:col-span-4 md:w-fit">
                            <Save class="h-4 w-4" />
                            حفظ
                        </button>
                    </form>
                    <form class="grid grid-cols-1 gap-2 md:grid-cols-5" @submit.prevent="submitTemplate">
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="inline-flex items-center gap-1">
                                <LayoutTemplate class="h-3.5 w-3.5" />
                                اسم مسمى الاختبار
                            </span>
                            <input v-model="templateForm.name" placeholder="مثال: اختبار شهري" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="inline-flex items-center gap-1">
                                <BookOpenText class="h-3.5 w-3.5" />
                                نوع الاختبار
                            </span>
                            <select v-model="templateForm.exam_type" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm"><option v-for="item in templateTypes" :key="item.value" :value="item.value">{{ item.label }}</option></select>
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="inline-flex items-center gap-1">
                                <Award class="h-3.5 w-3.5" />
                                الدرجة النهائية الافتراضية
                            </span>
                            <input v-model.number="templateForm.default_max_score" type="number" min="1" step="0.01" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="inline-flex items-center gap-1">
                                <Award class="h-3.5 w-3.5" />
                                درجة النجاح الافتراضية
                            </span>
                            <input v-model.number="templateForm.default_passing_score" type="number" min="0" step="0.01" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" />
                        </label>
                        <button class="inline-flex items-center gap-2 self-end rounded bg-emerald-600 px-3 py-2 text-sm font-bold hover:bg-emerald-500">
                            <Save class="h-4 w-4" />
                            {{ templateEditId ? 'تحديث' : 'إضافة' }}
                        </button>
                    </form>

                    <div class="mt-3 overflow-hidden rounded border border-gray-700">
                        <table class="w-full text-right text-sm">
                            <thead class="bg-gray-800 text-xs text-gray-300">
                                <tr><th class="px-3 py-2">الاسم</th><th class="px-3 py-2">النوع</th><th class="px-3 py-2">الدرجة</th><th class="px-3 py-2">الحالة</th><th class="px-3 py-2">إجراءات</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700 bg-gray-900">
                                <tr v-for="row in templates" :key="row.id">
                                    <td class="px-3 py-2">{{ row.name }}</td>
                                    <td class="px-3 py-2">{{ templateTypes.find((item) => item.value === row.exam_type)?.label || row.exam_type }}</td>
                                    <td class="px-3 py-2">{{ row.default_max_score }}</td>
                                    <td class="px-3 py-2">{{ row.is_active ? 'فعال' : 'غير فعال' }}</td>
                                    <td class="px-3 py-2">
                                        <div class="flex justify-end gap-2">
                                            <button type="button" class="inline-flex items-center gap-1 rounded bg-blue-700 px-2 py-1 text-xs hover:bg-blue-600" @click="editTemplate(row)">
                                                <Pencil class="h-3.5 w-3.5" />
                                                تعديل
                                            </button>
                                            <button type="button" class="inline-flex items-center gap-1 rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600" @click="deleteTemplate(row.id)">
                                                <Trash2 class="h-3.5 w-3.5" />
                                                حذف
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="templates.length === 0"><td colspan="5" class="px-3 py-3 text-center text-gray-500">لا توجد مسميات اختبارات بعد.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="overflow-hidden rounded border border-gray-700">
                    <table class="w-full text-right text-sm">
                        <thead class="bg-gray-800 text-xs text-gray-300">
                            <tr>
                                <th class="px-3 py-2">الاختبار</th>
                                <th class="px-3 py-2">المادة</th>
                                <th class="px-3 py-2">المعلم</th>
                                <th class="px-3 py-2">التاريخ</th>
                                <th class="px-3 py-2">الحالة</th>
                                <th class="px-3 py-2">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700 bg-gray-900">
                            <tr
                                v-for="exam in exams"
                                :key="exam.id"
                                class="stage-row-accent"
                                :style="stageAccent(exam.school_stage_id, stageNameForExam(exam))"
                            >
                                <td class="px-3 py-2">
                                    <p>{{ exam.title }}</p>
                                    <p v-if="Number(exam.attachments_count || 0) > 0" class="mt-1 text-xs text-sky-300">
                                        المرفقات: {{ Number(exam.attachments_count || 0) }}
                                    </p>
                                    <p v-if="stageNameForExam(exam)" class="mt-1">
                                        <span class="stage-badge" :style="stageAccent(exam.school_stage_id, stageNameForExam(exam))">
                                            {{ stageNameForExam(exam) }}
                                        </span>
                                    </p>
                                </td>
                                <td class="px-3 py-2">{{ exam.subject?.name || '-' }}</td>
                                <td class="px-3 py-2">{{ examTeacherName(exam) }}</td>
                                <td class="px-3 py-2">{{ exam.exam_date }}</td>
                                <td class="px-3 py-2">{{ examStatuses.find((row) => row.value === exam.status)?.label || exam.status }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <button type="button" class="inline-flex items-center gap-1 rounded bg-indigo-700 px-2 py-1 text-xs hover:bg-indigo-600" @click="selectedExamId = exam.id; loadExam()">
                                            <FileSpreadsheet class="h-3.5 w-3.5" />
                                            تحديد
                                        </button>
                                        <button v-if="canUpdateExam" type="button" class="inline-flex items-center gap-1 rounded bg-blue-700 px-2 py-1 text-xs hover:bg-blue-600" @click="openEditExamModal(exam)">
                                            <Pencil class="h-3.5 w-3.5" />
                                            تعديل
                                        </button>
                                        <button v-if="canDeleteExam" type="button" class="inline-flex items-center gap-1 rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600" @click="deleteExam(exam.id)">
                                            <Trash2 class="h-3.5 w-3.5" />
                                            حذف
                                        </button>
                                        <select v-model="statusForm.status" :disabled="!canUpdateExam" class="rounded border border-gray-600 bg-gray-900 px-2 py-1 text-xs disabled:opacity-50">
                                            <option value="">حالة</option>
                                            <option v-for="item in examStatuses" :key="item.value" :value="item.value">{{ item.label }}</option>
                                        </select>
                                        <input v-if="statusForm.status === 'postponed' || statusForm.status === 'canceled'" v-model="statusForm.reason" class="rounded border border-gray-600 bg-gray-900 px-2 py-1 text-xs" placeholder="السبب" />
                                        <button type="button" :disabled="!canUpdateExam || ((statusForm.status === 'approved' || statusForm.status === 'published' || statusForm.status === 'closed') && !canApproveExam)" class="inline-flex items-center gap-1 rounded bg-emerald-700 px-2 py-1 text-xs hover:bg-emerald-600 disabled:opacity-50" @click="updateStatus(exam)">
                                            <Save class="h-3.5 w-3.5" />
                                            حفظ
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="exams.length === 0"><td colspan="6" class="px-3 py-4 text-center text-gray-500">لا توجد اختبارات بعد.</td></tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="isExamModalOpen"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                    @click.self="closeExamModal"
                >
                    <div class="w-full max-w-6xl max-h-[90vh] overflow-y-auto rounded-2xl border border-blue-600/40 bg-gray-900 p-4">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="inline-flex items-center gap-2 text-lg font-bold">
                                <FileSpreadsheet class="h-5 w-5 text-blue-300" />
                                {{ examEditId ? 'تعديل جدول الاختبار' : 'إنشاء جدول اختبار' }}
                            </h3>
                            <button type="button" class="inline-flex items-center gap-1 rounded bg-gray-700 px-3 py-1.5 text-xs hover:bg-gray-600" @click="closeExamModal">
                                <X class="h-3.5 w-3.5" />
                                إغلاق
                            </button>
                        </div>

                        <form class="grid grid-cols-1 gap-2 md:grid-cols-5" @submit.prevent="submitExam">
                            <label class="space-y-1 text-xs text-slate-300">
                                <span class="inline-flex items-center gap-1 text-sky-200">
                                    <LayoutTemplate class="h-3.5 w-3.5" />
                                    مسمى الاختبار
                                </span>
                                <select v-model="examForm.school_exam_template_id" class="w-full rounded border border-gray-700 bg-gray-900 py-2 text-sm text-right [padding-inline-start:0.75rem] [padding-inline-end:2.5rem]"><option value="">بدون مسمى</option><option v-for="row in templates" :key="row.id" :value="row.id">{{ row.name }}</option></select>
                            </label>
                            <label class="space-y-1 text-xs text-slate-300">
                                <span class="inline-flex items-center gap-1 text-sky-200">
                                    <CalendarDays class="h-3.5 w-3.5" />
                                    الترم
                                </span>
                                <select v-model="examForm.school_term_id" class="w-full rounded border border-gray-700 bg-gray-900 py-2 text-sm text-right [padding-inline-start:0.75rem] [padding-inline-end:2.5rem]"><option value="" disabled>اختر الترم</option><option v-for="row in terms" :key="row.id" :value="row.id">{{ row.name }}</option></select>
                            </label>
                            <label class="space-y-1 text-xs text-slate-300">
                                <span class="inline-flex items-center gap-1 text-sky-200">
                                    <School class="h-3.5 w-3.5" />
                                    المرحلة التعليمية
                                </span>
                                <select v-model="examForm.school_stage_id" class="w-full rounded border border-gray-700 bg-gray-900 py-2 text-sm text-right [padding-inline-start:0.75rem] [padding-inline-end:2.5rem]"><option value="" disabled>اختر المرحلة</option><option v-for="row in stages" :key="row.id" :value="row.id">{{ row.name }}</option></select>
                            </label>
                            <label class="space-y-1 text-xs text-slate-300">
                                <span class="inline-flex items-center gap-1 text-sky-200">
                                    <BookOpenText class="h-3.5 w-3.5" />
                                    الصف / الفصل
                                </span>
                                <select v-model="examForm.school_classroom_id" class="w-full rounded border border-gray-700 bg-gray-900 py-2 text-sm text-right [padding-inline-start:0.75rem] [padding-inline-end:2.5rem]"><option value="" disabled>اختر الصف</option><option v-for="row in classroomOptions" :key="row.id" :value="row.id">{{ row.grade_name }} / {{ row.name }}</option></select>
                            </label>
                            <label class="space-y-1 text-xs text-slate-300">
                                <span class="inline-flex items-center gap-1 text-sky-200">
                                    <BookOpenText class="h-3.5 w-3.5" />
                                    المادة
                                </span>
                                <select v-model="examForm.school_subject_id" class="w-full rounded border border-gray-700 bg-gray-900 py-2 text-sm text-right [padding-inline-start:0.75rem] [padding-inline-end:2.5rem]"><option value="" disabled>اختر المادة</option><option v-for="row in subjects" :key="row.id" :value="row.id">{{ row.name }}</option></select>
                            </label>
                            <label class="space-y-1 text-xs text-slate-300">
                                <span class="inline-flex items-center gap-1 text-sky-200">
                                    <UserRound class="h-3.5 w-3.5" />
                                    المعلم
                                </span>
                                <select v-model="examForm.teacher_user_id" class="w-full rounded border border-gray-700 bg-gray-900 py-2 text-sm text-right [padding-inline-start:0.75rem] [padding-inline-end:2.5rem] [color-scheme:dark]">
                                    <option value="" disabled class="bg-white text-gray-900">اختر المعلم</option>
                                    <option v-for="row in teacherOptionsForExam" :key="row.id" :value="row.id" class="bg-white text-gray-900">{{ row.name }}</option>
                                </select>
                            </label>
                            <p v-if="Number(examForm.school_subject_id || 0) > 0 && teacherOptionsForExam.length === 0" class="text-xs text-amber-300 md:col-span-2">
                                لا يوجد معلم مسند لهذه المادة داخل نفس المدرسة. يرجى مراجعة إسناد المعلمين للمادة أو المقرر.
                            </p>
                            <label class="space-y-1 text-xs text-slate-300">
                                <span class="inline-flex items-center gap-1 text-sky-200">
                                    <FileSpreadsheet class="h-3.5 w-3.5" />
                                    اسم الاختبار
                                </span>
                                <input v-model="examForm.title" placeholder="مثال: اختبار أسبوعي 1" class="w-full rounded border border-gray-700 bg-gray-900 py-2 text-sm text-right [padding-inline-start:0.75rem] [padding-inline-end:2.5rem]" />
                            </label>
                            <label class="space-y-1 text-xs text-slate-300">
                                <span class="inline-flex items-center gap-1 text-sky-200">
                                    <CalendarDays class="h-3.5 w-3.5" />
                                    تاريخ الاختبار
                                </span>
                                <input v-model="examForm.exam_date" type="date" class="w-full rounded border border-gray-700 bg-gray-900 py-2 text-sm text-right [padding-inline-start:0.75rem] [padding-inline-end:2.5rem]" />
                            </label>
                            <label class="space-y-1 text-xs text-slate-300">
                                <span class="inline-flex items-center gap-1 text-sky-200">
                                    <Clock3 class="h-3.5 w-3.5" />
                                    وقت البداية
                                </span>
                                <input v-model="examForm.starts_at" type="time" class="w-full rounded border border-gray-700 bg-gray-900 py-2 text-sm text-right [padding-inline-start:0.75rem] [padding-inline-end:2.5rem]" />
                            </label>
                            <label class="space-y-1 text-xs text-slate-300">
                                <span class="inline-flex items-center gap-1 text-sky-200">
                                    <Clock3 class="h-3.5 w-3.5" />
                                    وقت النهاية
                                </span>
                                <input v-model="examForm.ends_at" type="time" class="w-full rounded border border-gray-700 bg-gray-900 py-2 text-sm text-right [padding-inline-start:0.75rem] [padding-inline-end:2.5rem]" />
                            </label>
                            <label v-if="!examForm.school_exam_template_id" class="space-y-1 text-xs text-slate-300">
                                <span class="inline-flex items-center gap-1 text-sky-200">
                                    <Award class="h-3.5 w-3.5" />
                                    الدرجة النهائية
                                </span>
                                <input v-model.number="examForm.max_score" type="number" min="1" class="w-full rounded border border-gray-700 bg-gray-900 py-2 text-sm text-right [padding-inline-start:0.75rem] [padding-inline-end:2.5rem]" />
                            </label>
                            <label v-if="!examForm.school_exam_template_id" class="space-y-1 text-xs text-slate-300">
                                <span class="inline-flex items-center gap-1 text-sky-200">
                                    <Award class="h-3.5 w-3.5" />
                                    درجة النجاح
                                </span>
                                <input v-model.number="examForm.passing_score" type="number" min="0" class="w-full rounded border border-gray-700 bg-gray-900 py-2 text-sm text-right [padding-inline-start:0.75rem] [padding-inline-end:2.5rem]" />
                            </label>
                            <div v-else class="rounded border border-gray-700 bg-gray-800/60 p-3 text-xs text-gray-300 md:col-span-2">
                                <p>تم إخفاء إدخال الدرجة النهائية ودرجة النجاح لتجنب التكرار.</p>
                                <p class="mt-1">
                                    تُسحب القيم تلقائيًا من المسمى:
                                    <span class="font-semibold text-white">{{ Number(selectedExamTemplate?.default_max_score ?? examForm.max_score).toFixed(2) }}</span>
                                    /
                                    <span class="font-semibold text-white">{{ Number(selectedExamTemplate?.default_passing_score ?? examForm.passing_score).toFixed(2) }}</span>
                                </p>
                            </div>
                            <div class="rounded border border-gray-700 bg-gray-800/60 p-3 text-xs text-gray-300">
                                <p class="inline-flex items-center gap-1 font-semibold text-white">
                                    <Timer class="h-3.5 w-3.5" />
                                    استبدال حصة المادة
                                </p>
                                <p class="mt-1">يُطبق من الإعدادات العامة في جدول الاختبارات.</p>
                            </div>

                            <div class="md:col-span-5">
                                <AttachmentPanel
                                    title="مرفقات الاختبار"
                                    helper-text="يمكنك إرفاق ملف الاختبار أو تعليمات التنفيذ أو مستند الاعتماد. في وضع التعديل ستُضاف الملفات الجديدة إلى المرفقات الحالية."
                                    :existing-attachments="Number(selectedExamId || 0) === Number(examEditId || 0) ? (props.selectedExamAttachments || []) : []"
                                    :pending-files="pendingExamAttachments"
                                    :errors="examAttachmentErrors"
                                    accept="application/pdf,image/*,.doc,.docx,.xls,.xlsx"
                                    :busy="examForm.processing"
                                    pending-title="مرفقات سيتم حفظها مع الاختبار"
                                    existing-title="المرفقات المحفوظة للاختبار المحدد"
                                    empty-text="لا توجد مرفقات محفوظة لهذا الاختبار بعد."
                                    @select-files="appendExamAttachmentFiles"
                                    @remove-pending="removePendingExamAttachment"
                                    @delete-existing="deleteExamAttachment"
                                />
                            </div>

                            <div class="flex flex-wrap justify-end gap-2 md:col-span-5">
                                <button type="button" class="inline-flex items-center gap-2 rounded bg-gray-700 px-3 py-2 text-sm hover:bg-gray-600" @click="closeExamModal">
                                    <X class="h-4 w-4" />
                                    إلغاء
                                </button>
                                <button :disabled="examForm.processing" class="inline-flex items-center gap-2 rounded bg-emerald-600 px-3 py-2 text-sm font-bold hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-60">
                                    <Save class="h-4 w-4" />
                                    {{ examEditId ? 'تحديث' : 'إضافة' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
            <section v-if="showQuestionBankSection" class="rounded-xl border border-emerald-700/40 bg-gray-900 p-4">
                <h2 class="mb-3 text-lg font-bold">بنك الأسئلة</h2>

                <div v-if="canManageQuestionBank" class="space-y-3">
                    <form class="grid grid-cols-1 gap-2 md:grid-cols-6" @submit.prevent="submitQuestion">
                        <label class="space-y-1 text-xs text-gray-400 md:col-span-3">
                            <span class="block">المقرر (المرحلة / الصف / الترم)</span>
                            <select v-model="questionForm.school_course_offering_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="" disabled>اختر المقرر</option>
                                <option v-for="row in questionBankCourseOfferingOptions" :key="`qb-offering-${row.id}`" :value="row.id">
                                    {{ row.label }}
                                </option>
                            </select>
                        </label>
                        <p v-if="questionBankCourseOfferingOptions.length === 0" class="text-xs text-amber-300 md:col-span-3">
                            لا توجد مقررات متاحة لبنك الأسئلة. يرجى أولًا إسناد مقرر فعّال داخل نفس المدرسة.
                        </p>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="block">المادة</span>
                            <select v-model="questionForm.school_subject_id" disabled class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm opacity-80">
                                <option value="" disabled>المادة</option>
                                <option v-for="row in subjects" :key="row.id" :value="row.id">{{ row.name }}</option>
                            </select>
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="block">المرحلة</span>
                            <select v-model="questionForm.school_stage_id" disabled class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm opacity-80">
                                <option value="">المرحلة</option>
                                <option v-for="row in stages" :key="row.id" :value="row.id">{{ row.name }}</option>
                            </select>
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="block">الترم</span>
                            <select v-model="questionForm.school_term_id" disabled class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm opacity-80">
                                <option value="">الترم</option>
                                <option v-for="row in terms" :key="row.id" :value="row.id">{{ row.name }}</option>
                            </select>
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="block">الفرع</span>
                            <select v-model="questionForm.branch_name" :disabled="questionBankBranchesForSelectedOffering.length === 0" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm disabled:opacity-50">
                                <option value="" disabled>اختر الفرع</option>
                                <option v-for="branch in questionBankBranchesForSelectedOffering" :key="`branch-option-${branch}`" :value="branch">
                                    {{ branch }}
                                </option>
                            </select>
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="block">الوحدة</span>
                            <select v-model="questionForm.unit_name" :disabled="!questionForm.branch_name" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm disabled:opacity-50">
                                <option value="" disabled>اختر الوحدة</option>
                                <option v-for="unit in questionBankUnitsForSelectedOffering" :key="`unit-option-${unit}`" :value="unit">
                                    {{ unit }}
                                </option>
                            </select>
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="block">الدرس</span>
                            <select v-model="questionForm.lesson_name" :disabled="!questionForm.unit_name" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm disabled:opacity-50">
                                <option value="" disabled>اختر الدرس</option>
                                <option v-for="lesson in questionBankLessonsForSelectedOffering" :key="`lesson-option-${lesson}`" :value="lesson">
                                    {{ lesson }}
                                </option>
                            </select>
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="block">الموضوع</span>
                            <select v-model="questionForm.chapter_name" :disabled="!questionForm.lesson_name" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm disabled:opacity-50">
                                <option value="" disabled>اختر الموضوع</option>
                                <option v-for="topic in questionBankTopicsForSelectedOffering" :key="`topic-option-${topic}`" :value="topic">
                                    {{ topic }}
                                </option>
                            </select>
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="block">نوع السؤال</span>
                            <select v-model="questionForm.question_type" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option v-for="row in questionTypes" :key="row.value" :value="row.value">{{ row.label }}</option>
                            </select>
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="block">إجباري / اختياري</span>
                            <select v-model="questionForm.selection_mode" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option v-for="row in questionSelectionModes" :key="row.value" :value="row.value">{{ row.label }}</option>
                            </select>
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="block">مستوى الصعوبة</span>
                            <select v-model="questionForm.difficulty" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option v-for="row in questionDifficulties" :key="row.value" :value="row.value">{{ row.label }}</option>
                            </select>
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="block">حالة السؤال</span>
                            <select v-model="questionForm.status" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option v-for="row in questionStatusOptions" :key="row.value" :value="row.value">{{ row.label }}</option>
                            </select>
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="block">درجة السؤال</span>
                            <input v-model.number="questionForm.question_score" type="number" min="0.25" step="0.25" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" placeholder="درجة السؤال" />
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="block">الوسوم</span>
                            <input v-model="questionForm.tags" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" placeholder="وسوم (مفصولة بفاصلة)" />
                        </label>
                        <label class="space-y-1 text-xs text-gray-400 md:col-span-2">
                            <span class="block">ناتج التعلم / المهارة</span>
                            <input v-model="questionForm.learning_outcome" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" placeholder="ناتج التعلم / المهارة" />
                        </label>
                        <label class="space-y-1 text-xs text-gray-400 md:col-span-6">
                            <span class="block">نص السؤال</span>
                            <input v-model="questionForm.question_text" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" placeholder="نص السؤال" />
                        </label>
                        <label class="space-y-1 text-xs text-gray-400 md:col-span-3">
                            <span class="block">الإجابة النموذجية (اختياري)</span>
                            <textarea v-model="questionForm.model_answer" rows="2" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" placeholder="الإجابة النموذجية (اختياري)" ></textarea>
                        </label>
                        <label class="space-y-1 text-xs text-gray-400 md:col-span-3">
                            <span class="block">شرح الإجابة (اختياري)</span>
                            <textarea v-model="questionForm.answer_explanation" rows="2" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" placeholder="شرح الإجابة (اختياري)" ></textarea>
                        </label>
                        <div v-if="canUseQuestionOptions" class="rounded border border-gray-700 bg-gray-800 p-2 md:col-span-6">
                            <div class="mb-2 flex items-center justify-between">
                                <p class="text-xs text-gray-300">خيارات السؤال</p>
                                <button type="button" class="rounded bg-blue-700 px-2 py-1 text-xs hover:bg-blue-600" @click="addQuestionOption">إضافة خيار</button>
                            </div>
                            <div class="space-y-2">
                                <div v-for="(option, index) in questionForm.options" :key="`option-${index}`" class="grid grid-cols-1 gap-2 md:grid-cols-[1fr_auto_auto]">
                                    <input v-model="option.option_text" class="rounded border border-gray-700 bg-gray-900 p-2 text-sm" :placeholder="`الخيار ${index + 1}`" />
                                    <label class="inline-flex items-center justify-center gap-2 text-xs"><input v-model="option.is_correct" type="checkbox" class="rounded border-gray-600 bg-gray-900 text-blue-500" /> إجابة صحيحة</label>
                                    <button type="button" class="rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600" @click="removeQuestionOption(index)">حذف</button>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 md:col-span-6">
                            <button :disabled="questionBankCourseOfferingOptions.length === 0 || !questionForm.school_course_offering_id" class="rounded bg-emerald-600 px-3 py-2 text-sm font-bold hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50">
                                {{ questionEditId ? 'تحديث السؤال' : 'إضافة السؤال' }}
                            </button>
                            <button v-if="questionEditId" type="button" class="rounded bg-gray-700 px-3 py-2 text-sm hover:bg-gray-600" @click="resetQuestionForm">إلغاء التعديل</button>
                        </div>
                    </form>
                </div>

                <div class="mt-4 rounded border border-gray-700 bg-gray-800 p-3">
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-200">فلاتر بنك الأسئلة</h3>
                        <button type="button" class="rounded bg-gray-700 px-2 py-1 text-xs hover:bg-gray-600" @click="resetQuestionFilters">
                            مسح الفلاتر
                        </button>
                    </div>
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-8">
                        <label class="space-y-1 text-xs text-gray-400 md:col-span-2">
                            <span class="block">المقرر</span>
                            <select v-model="questionFilters.course_offering_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="">كل المقررات</option>
                                <option v-for="row in questionBankCourseOfferingOptions" :key="`filter-offering-${row.id}`" :value="row.id">
                                    {{ row.label }}
                                </option>
                            </select>
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="block">المادة</span>
                            <select v-model="questionFilters.subject_id" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="">كل المواد</option>
                                <option v-for="row in subjects" :key="`filter-subject-${row.id}`" :value="row.id">{{ row.name }}</option>
                            </select>
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="block">نوع السؤال</span>
                            <select v-model="questionFilters.type" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="">كل الأنواع</option>
                                <option v-for="row in questionTypes" :key="`filter-type-${row.value}`" :value="row.value">{{ row.label }}</option>
                            </select>
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="block">الصعوبة</span>
                            <select v-model="questionFilters.difficulty" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                                <option value="">كل مستويات الصعوبة</option>
                                <option v-for="row in questionDifficulties" :key="`filter-difficulty-${row.value}`" :value="row.value">{{ row.label }}</option>
                            </select>
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="block">الوحدة</span>
                            <input v-model="questionFilters.unit_name" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" placeholder="الوحدة" />
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="block">الدرس</span>
                            <input v-model="questionFilters.lesson_name" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" placeholder="الدرس" />
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="block">الموضوع</span>
                            <input v-model="questionFilters.chapter_name" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" placeholder="الموضوع" />
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="block">الوسم</span>
                            <input v-model="questionFilters.tag" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" placeholder="الوسم" />
                        </label>
                        <label class="space-y-1 text-xs text-gray-400">
                            <span class="block">ناتج التعلم</span>
                            <input v-model="questionFilters.learning_outcome" class="w-full rounded border border-gray-700 bg-gray-900 p-2 text-sm" placeholder="المهارة / ناتج التعلم" />
                        </label>
                    </div>
                </div>

                <div class="mt-3 space-y-2">
                    <div
                        v-for="question in filteredQuestionBank"
                        :key="question.id"
                        class="stage-row-accent rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                        :style="stageAccent(question.school_stage_id, stageNameForQuestion(question))"
                    >
                        <p class="font-semibold">{{ question.question_text }}</p>
                        <p class="text-xs text-gray-400">
                            {{ questionBankCourseOfferingLabelById(question.school_course_offering_id) }}
                        </p>
                        <p v-if="stageNameForQuestion(question)" class="mt-1">
                            <span class="stage-badge" :style="stageAccent(question.school_stage_id, stageNameForQuestion(question))">
                                {{ stageNameForQuestion(question) }}
                            </span>
                        </p>
                        <p class="text-xs text-gray-400">
                            {{ question.subject?.name || '-' }} |
                            {{ question.question_score }} |
                            {{ questionTypes.find((item) => item.value === question.question_type)?.label || question.question_type }}
                        </p>
                        <p class="text-xs text-gray-400">
                            {{ resolveQuestionBranchName(question) || 'بدون فرع' }} /
                            {{ question.unit_name || 'بدون وحدة' }} /
                            {{ question.lesson_name || 'بدون درس' }} /
                            {{ question.chapter_name || 'بدون موضوع' }}
                        </p>
                        <div class="mt-2 flex flex-wrap justify-end gap-2">
                            <button v-if="canManageQuestionBank" class="rounded bg-blue-700 px-2 py-1 text-xs hover:bg-blue-600" @click="editQuestion(question)">تعديل</button>
                            <button v-if="canManageQuestionBank" class="rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600" @click="deleteQuestion(question.id)">حذف</button>
                        </div>
                    </div>
                    <div v-if="filteredQuestionBank.length === 0" class="rounded border border-gray-700 bg-gray-800 p-4 text-center text-gray-500">لا توجد أسئلة مطابقة للفلاتر الحالية.</div>
                </div>
            </section>

            <section v-if="showSelectedExamSection" class="rounded-xl border border-violet-700/40 bg-gray-900 p-4">
                <h2 class="mb-3 text-lg font-bold">الاختبار المحدد: الأسئلة والدرجات</h2>
                <div class="mb-3 flex flex-wrap items-end gap-2">
                    <label class="space-y-1 text-xs text-gray-400">
                        <span class="block">الاختبار المحدد</span>
                        <select v-model="selectedExamId" class="rounded border border-gray-700 bg-gray-900 p-2 text-sm">
                            <option :value="null">اختر اختبارًا</option>
                            <option v-for="exam in exams" :key="exam.id" :value="exam.id">{{ exam.title }} - {{ exam.exam_date }}</option>
                        </select>
                    </label>
                    <button class="rounded bg-indigo-700 px-3 py-2 text-sm hover:bg-indigo-600" @click="loadExam">تحميل</button>
                    <button :disabled="!canUpdateExam || !canUseQuestionBank" class="rounded bg-emerald-700 px-3 py-2 text-sm hover:bg-emerald-600 disabled:opacity-50" @click="syncQuestions">حفظ أسئلة الاختبار</button>
                    <button :disabled="!canRecordExamScores" class="rounded bg-emerald-700 px-3 py-2 text-sm hover:bg-emerald-600 disabled:opacity-50" @click="submitScores">حفظ درجات الطلاب</button>
                </div>
                <div class="text-sm text-gray-400">
                    عدد أسئلة الاختبار: {{ examQuestionDraft.length }} |
                    إجمالي درجات الأسئلة: {{ examQuestionScoreTotal.toFixed(2) }} |
                    عدد الطلاب: {{ studentsForSelectedExam.length }}
                </div>
                <div class="mt-3 rounded border border-gray-700 bg-gray-800 p-3">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-200">مرفقات الاختبار المحدد</h3>
                        <span class="text-xs text-gray-400">إجمالي المرفقات: {{ (props.selectedExamAttachments || []).length }}</span>
                    </div>
                    <div v-if="(props.selectedExamAttachments || []).length > 0" class="space-y-2">
                        <div
                            v-for="attachment in props.selectedExamAttachments"
                            :key="`selected-attachment-${attachment.id}`"
                            class="flex items-center justify-between gap-3 rounded border border-gray-700 bg-gray-900 p-3"
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
                                    v-if="canUpdateExam"
                                    type="button"
                                    class="rounded bg-red-700 px-2 py-1 text-xs font-bold text-red-100 hover:bg-red-600"
                                    @click="deleteExamAttachment(attachment)"
                                >
                                    حذف
                                </button>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-500">لا توجد مرفقات محفوظة لهذا الاختبار بعد.</p>
                </div>
                <div v-if="canUseQuestionBank" class="mt-3 rounded border border-gray-700 bg-gray-800 p-3">
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-200">اختيار الأسئلة المناسبة من بنك الأسئلة</h3>
                        <span class="text-xs text-gray-400">متاح للإضافة: {{ selectedExamAvailableQuestions.length }}</span>
                    </div>
                    <div v-if="!selectedExamId" class="rounded border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-amber-200">
                        اختر اختبارًا محددًا ثم اضغط تحميل لإظهار الأسئلة المناسبة.
                    </div>
                    <div v-else-if="!canUpdateExam" class="rounded border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-amber-200">
                        لا تملك صلاحية تعديل أسئلة الاختبار المحدد.
                    </div>
                    <div v-else class="space-y-2">
                        <div
                            v-for="question in selectedExamAvailableQuestions"
                            :key="`candidate-${question.id}`"
                            class="stage-row-accent rounded border border-gray-700 bg-gray-900 p-2 text-sm"
                            :style="stageAccent(question.school_stage_id, stageNameForQuestion(question))"
                        >
                            <p class="font-semibold">{{ question.question_text }}</p>
                            <p class="text-xs text-gray-400">{{ questionBankCourseOfferingLabelById(question.school_course_offering_id) }}</p>
                            <p v-if="stageNameForQuestion(question)" class="mt-1">
                                <span class="stage-badge" :style="stageAccent(question.school_stage_id, stageNameForQuestion(question))">
                                    {{ stageNameForQuestion(question) }}
                                </span>
                            </p>
                            <p class="text-xs text-gray-400">
                                {{ question.question_score }} |
                                {{ questionTypes.find((item) => item.value === question.question_type)?.label || question.question_type }}
                            </p>
                            <div class="mt-2 flex flex-wrap justify-end gap-2">
                                <button class="rounded bg-indigo-700 px-2 py-1 text-xs hover:bg-indigo-600" @click="addQuestionToExam(question)">
                                    إضافة السؤال للاختبار
                                </button>
                            </div>
                        </div>
                        <div v-if="selectedExamAvailableQuestions.length === 0" class="rounded border border-gray-700 bg-gray-900 p-3 text-center text-sm text-gray-400">
                            لا توجد أسئلة مناسبة للاختبار المحدد وفق نفس المقرر والمرحلة والصف والترم.
                        </div>
                    </div>
                </div>
                <div class="mt-3 space-y-2">
                    <div v-for="row in examQuestionDraft" :key="row.school_question_bank_item_id" class="rounded border border-gray-700 bg-gray-800 p-2 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p>{{ row.label }}</p>
                            <div class="flex flex-wrap items-center gap-2">
                                <input v-model.number="row.score" type="number" min="0.25" step="0.25" class="w-24 rounded border border-gray-700 bg-gray-900 p-1 text-xs" />
                                <label class="inline-flex items-center gap-1 text-xs"><input v-model="row.is_required" type="checkbox" class="rounded border-gray-600 bg-gray-900 text-blue-500" /> إجباري</label>
                                <button class="rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600" @click="removeQuestionFromExam(row.school_question_bank_item_id)">حذف</button>
                            </div>
                        </div>
                    </div>
                    <div v-if="examQuestionDraft.length === 0" class="rounded border border-gray-700 bg-gray-800 p-3 text-center text-gray-500">لا توجد أسئلة مرتبطة بالاختبار.</div>
                </div>
                <div v-if="canRecordExamScores" class="mt-4 overflow-hidden rounded border border-gray-700">
                    <table class="w-full text-right text-sm">
                        <thead class="bg-gray-800 text-xs text-gray-300">
                            <tr><th class="px-3 py-2">الطالب</th><th class="px-3 py-2">الدرجة</th><th class="px-3 py-2">الحالة</th><th class="px-3 py-2">نهائي</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700 bg-gray-900">
                            <tr v-for="row in scoreDraft" :key="row.school_student_id">
                                <td class="px-3 py-2">{{ row.full_name }} <span class="text-xs text-gray-500">({{ row.student_code || '-' }})</span></td>
                                <td class="px-3 py-2"><input v-model="row.score" type="number" min="0" step="0.25" class="w-24 rounded border border-gray-700 bg-gray-900 p-1 text-xs" /></td>
                                <td class="px-3 py-2">
                                    <select v-model="row.attendance_status" class="rounded border border-gray-700 bg-gray-900 p-1 text-xs">
                                        <option v-for="item in scoreAttendanceStatuses" :key="item.value" :value="item.value">{{ item.label }}</option>
                                    </select>
                                </td>
                                <td class="px-3 py-2"><input v-model="row.is_finalized" type="checkbox" class="rounded border-gray-600 bg-gray-900 text-blue-500" /></td>
                            </tr>
                            <tr v-if="scoreDraft.length === 0"><td colspan="4" class="px-3 py-3 text-center text-gray-500">لا يوجد طلاب في الصف المحدد.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="mt-4 rounded border border-amber-500/40 bg-amber-500/10 p-3 text-sm text-amber-200">
                    لا تملك صلاحية رصد درجات الطلاب لهذا الاختبار.
                </div>
            </section>
        </div>
    </RoleLayout>
</template>




