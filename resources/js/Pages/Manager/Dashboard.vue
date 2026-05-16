<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    Award,
    BadgeAlert,
    BadgeCheck,
    BarChart3,
    BookOpen,
    Briefcase,
    CalendarClock,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    Clock,
    GraduationCap,
    LayoutGrid,
    RefreshCcw,
    Shield,
    SlidersHorizontal,
    UserCheck,
    UserX,
    Users,
} from 'lucide-vue-next';
import RoleLayout from '@/Layouts/RoleLayout.vue';
import { useActionDialog } from '@/composables/useActionDialog';

const props = defineProps({
    staff: {
        type: Array,
        default: () => [],
    },
    associationRequests: {
        type: Array,
        default: () => [],
    },
    analytics: {
        type: Object,
        default: () => ({}),
    },
});

const staff = computed(() => props.staff || []);
const analytics = computed(() => props.analytics || {});
const page = usePage();
const actionDialog = useActionDialog();
const currentUserId = computed(() => Number(page.props?.auth?.user?.id || 0));

const tickets = ref([]);
const associationRequests = ref([...props.associationRequests]);
const subtaskForms = ref({});
const finalReportByTicket = ref({});
const subtaskErrors = ref({});
const finalErrors = ref({});
const loadError = ref('');
const loading = ref(false);
const actionLoading = ref({});
const analyticsSlides = [
    { key: 'summary', label: 'عام' },
    { key: 'students', label: 'الطلاب' },
    { key: 'attendance', label: 'الحضور' },
    { key: 'leaves', label: 'الإجازات' },
    { key: 'exams', label: 'الاختبارات' },
    { key: 'teachers', label: 'المعلمون' },
    { key: 'schedules', label: 'الجداول' },
    { key: 'alerts', label: 'التنبيهات' },
];
const activeAnalyticsSlide = ref('summary');
const filterForm = ref({
    period: props.analytics?.filters?.period || 'last_30_days',
    stage_id: props.analytics?.filters?.stage_id || '',
    grade_id: props.analytics?.filters?.grade_id || '',
    classroom_id: props.analytics?.filters?.classroom_id || '',
    subject_id: props.analytics?.filters?.subject_id || '',
    teacher_id: props.analytics?.filters?.teacher_id || '',
});

const iconMap = {
    users: Users,
    'user-check': UserCheck,
    'graduation-cap': GraduationCap,
    briefcase: Briefcase,
    'layout-grid': LayoutGrid,
    activity: Activity,
    'user-x': UserX,
    clock: Clock,
    'calendar-clock': CalendarClock,
    'clipboard-list': ClipboardList,
    award: Award,
    'calendar-days': CalendarDays,
    'badge-check': BadgeCheck,
    'badge-alert': BadgeAlert,
    shield: Shield,
    'book-open': BookOpen,
    chart: BarChart3,
};

const kpis = computed(() => analytics.value?.kpis || []);
const filterOptions = computed(() => analytics.value?.filterOptions || {});
const activeSlideIndex = computed(() => analyticsSlides.findIndex((slide) => slide.key === activeAnalyticsSlide.value));
const filteredGrades = computed(() => {
    const stageId = Number(filterForm.value.stage_id || 0);
    const grades = filterOptions.value.grades || [];
    return stageId > 0 ? grades.filter((grade) => Number(grade.stage_id) === stageId) : grades;
});
const filteredClassrooms = computed(() => {
    const stageId = Number(filterForm.value.stage_id || 0);
    const gradeId = Number(filterForm.value.grade_id || 0);
    const grade = (filterOptions.value.grades || []).find((item) => Number(item.id) === gradeId);
    const classrooms = filterOptions.value.classrooms || [];

    return classrooms.filter((classroom) => {
        if (stageId > 0 && Number(classroom.stage_id) !== stageId) return false;
        if (grade?.name && classroom.grade_name !== grade.name) return false;
        return true;
    });
});

const taskForm = ref({
    title: '',
    description: '',
    priority: 'MEDIUM',
    due_date: '',
    assigned_to: staff.value[0]?.id ?? '',
});
const taskFormErrors = ref({});
const taskFormErrorMessage = ref('');

watch(
    () => props.analytics?.filters,
    (filters) => {
        filterForm.value = {
            period: filters?.period || 'last_30_days',
            stage_id: filters?.stage_id || '',
            grade_id: filters?.grade_id || '',
            classroom_id: filters?.classroom_id || '',
            subject_id: filters?.subject_id || '',
            teacher_id: filters?.teacher_id || '',
        };
    },
    { deep: true }
);

const iconFor = (key) => iconMap[key] || BarChart3;

const statusClassName = (status) => {
    if (status === 'success') return 'border-emerald-400/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-200';
    if (status === 'danger') return 'border-red-400/40 bg-red-500/10 text-red-700 dark:text-red-200';
    if (status === 'warning') return 'border-amber-400/40 bg-amber-500/10 text-amber-700 dark:text-amber-200';
    if (status === 'primary') return 'border-blue-400/40 bg-blue-500/10 text-blue-700 dark:text-blue-200';
    if (status === 'info') return 'border-cyan-400/40 bg-cyan-500/10 text-cyan-700 dark:text-cyan-200';
    return 'border-slate-300/70 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-200';
};

const severityClassName = (severity) => {
    if (severity === 'danger') return 'border-red-400/40 bg-red-500/10 text-red-700 dark:text-red-200';
    if (severity === 'warning') return 'border-amber-400/40 bg-amber-500/10 text-amber-700 dark:text-amber-200';
    if (severity === 'success') return 'border-emerald-400/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-200';
    return 'border-blue-400/40 bg-blue-500/10 text-blue-700 dark:text-blue-200';
};

const asRows = (items) => Array.isArray(items) ? items : [];
const maxValue = (items) => Math.max(...asRows(items).map((item) => Number(item.value || 0)), 1);
const barWidth = (value, items) => `${Math.max(4, Math.round((Number(value || 0) / maxValue(items)) * 100))}%`;
const hasRows = (items) => asRows(items).length > 0;
const numberText = (value) => value === null || typeof value === 'undefined' ? 'لا توجد بيانات' : value;
const emptyText = 'لا توجد بيانات كافية لعرض هذا الرسم.';
const piePalette = ['#0ea5e9', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6'];

const pieStyle = (items) => {
    const rows = asRows(items);
    const total = rows.reduce((sum, item) => sum + Number(item.value || 0), 0);
    if (total <= 0) return { background: 'conic-gradient(#64748b 0 100%)' };

    let cursor = 0;
    const segments = rows.map((item, index) => {
        const value = Number(item.value || 0);
        const start = cursor;
        cursor += (value / total) * 100;
        return `${piePalette[index % piePalette.length]} ${start}% ${cursor}%`;
    });

    return { background: `conic-gradient(${segments.join(', ')})` };
};

const applyAnalyticsFilters = () => {
    const payload = Object.fromEntries(
        Object.entries(filterForm.value).filter(([, value]) => value !== '' && value !== null && typeof value !== 'undefined')
    );

    router.get(route('manager.dashboard'), payload, {
        preserveScroll: true,
        preserveState: false,
        replace: true,
    });
};

const resetAnalyticsFilters = () => {
    filterForm.value = {
        period: 'last_30_days',
        stage_id: '',
        grade_id: '',
        classroom_id: '',
        subject_id: '',
        teacher_id: '',
    };
    applyAnalyticsFilters();
};

const onStageFilterChange = () => {
    filterForm.value.grade_id = '';
    filterForm.value.classroom_id = '';
};

const onGradeFilterChange = () => {
    filterForm.value.classroom_id = '';
};

const nextAnalyticsSlide = () => {
    const nextIndex = activeSlideIndex.value >= analyticsSlides.length - 1 ? 0 : activeSlideIndex.value + 1;
    activeAnalyticsSlide.value = analyticsSlides[nextIndex].key;
};

const previousAnalyticsSlide = () => {
    const previousIndex = activeSlideIndex.value <= 0 ? analyticsSlides.length - 1 : activeSlideIndex.value - 1;
    activeAnalyticsSlide.value = analyticsSlides[previousIndex].key;
};

const statusClass = (status) => {
    if (status === 'CLOSED') return 'bg-gray-700 text-gray-100';
    if (status === 'WAITING_SUPERVISOR_REVIEW') return 'bg-amber-700 text-white';
    if (status === 'WAITING_MANAGER_REVIEW') return 'bg-indigo-700 text-white';
    if (status === 'IN_PROGRESS') return 'bg-blue-700 text-white';
    return 'bg-emerald-700 text-white';
};

const associationStatusClass = (status) => {
    if (status === 'APPROVED') return 'bg-emerald-700 text-white';
    if (status === 'REJECTED') return 'bg-red-700 text-white';
    return 'bg-amber-700 text-white';
};

const associationStatusLabel = (status) => {
    if (status === 'PENDING') return 'قيد الانتظار';
    if (status === 'APPROVED') return 'مقبول';
    if (status === 'REJECTED') return 'مرفوض';
    return status || '-';
};

const ticketStatusLabel = (status) => {
    if (status === 'OPEN') return 'مفتوحة';
    if (status === 'IN_PROGRESS') return 'قيد التنفيذ';
    if (status === 'WAITING_MANAGER_REVIEW') return 'بانتظار مراجعة المدير';
    if (status === 'WAITING_SUPERVISOR_REVIEW') return 'بانتظار مراجعة المشرف';
    if (status === 'CLOSED') return 'مغلقة';
    return status || '-';
};

const subtaskStatusLabel = (status) => {
    if (status === 'OPEN') return 'مفتوحة';
    if (status === 'IN_PROGRESS') return 'قيد التنفيذ';
    if (status === 'SUBMITTED') return 'تم التسليم';
    if (status === 'APPROVED') return 'معتمدة';
    return status || '-';
};

const priorityLabel = (priority) => {
    if (priority === 'HIGH') return 'عالية';
    if (priority === 'MEDIUM') return 'متوسطة';
    if (priority === 'LOW') return 'منخفضة';
    return 'عادية';
};

const staffTypeLabel = (type) => {
    if (type === 'ADMINISTRATIVE') return 'إداري';
    if (type === 'EDUCATIONAL') return 'تعليمي';
    return 'غير محدد';
};

const assigneeLabel = (member) => {
    if (!member) return '-';
    const parts = [member.name];
    if (member.department?.name) parts.push(member.department.name);
    if (member.school_staff_type) parts.push(staffTypeLabel(member.school_staff_type));
    if (member.department_role?.name) parts.push(member.department_role.name);
    return parts.join(' - ');
};

const isInternalTicket = (ticket) => Number(ticket.created_by) === currentUserId.value;
const canAddSubtask = (ticket) => ticket?.status !== 'CLOSED';

const ticketSourceLabel = (ticket) => (isInternalTicket(ticket) ? 'مهمة داخلية' : 'مهمة واردة من المشرف');

const ticketSourceClass = (ticket) => (isInternalTicket(ticket)
    ? 'bg-blue-500/20 text-blue-200'
    : 'bg-amber-500/20 text-amber-200');

const ensureSubtaskForm = (ticketId) => {
    if (!subtaskForms.value[ticketId]) {
        subtaskForms.value[ticketId] = {
            title: '',
            description: '',
            due_date: '',
            assigned_to: staff.value[0]?.id ?? '',
        };
    }

    return subtaskForms.value[ticketId];
};

const normalizeTicketForms = (items) => {
    items.forEach((ticket) => {
        ensureSubtaskForm(ticket.id);
        if (typeof finalReportByTicket.value[ticket.id] === 'undefined') {
            finalReportByTicket.value[ticket.id] = ticket.manager_final_report || '';
        }
    });
};

const loadTickets = async () => {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await axios.get(route('manager.tickets.index'));
        tickets.value = response.data;
        normalizeTicketForms(response.data || []);
    } catch (error) {
        loadError.value = error?.response?.data?.message || 'تعذر تحميل المهام.';
    } finally {
        loading.value = false;
    }
};

const loadAssociationRequests = async () => {
    try {
        const response = await axios.get(route('association_requests.index'));
        associationRequests.value = response.data;
    } catch (error) {
        loadError.value = error?.response?.data?.message || 'تعذر تحميل طلبات المصافحة.';
    }
};

const approveAssociation = async (id) => {
    actionLoading.value[`association-${id}`] = true;
    try {
        await axios.post(route('association_requests.approve', id));
        await Promise.all([loadAssociationRequests(), loadTickets()]);
    } finally {
        actionLoading.value[`association-${id}`] = false;
    }
};

const rejectAssociation = async (id) => {
    actionLoading.value[`association-${id}`] = true;
    try {
        await axios.post(route('association_requests.reject', id), {
            notes: 'rejected_by_manager',
        });
        await loadAssociationRequests();
    } finally {
        actionLoading.value[`association-${id}`] = false;
    }
};

const createInternalTask = async () => {
    taskFormErrors.value = {};
    taskFormErrorMessage.value = '';

    try {
        await axios.post(route('manager.tickets.store'), {
            title: taskForm.value.title,
            description: taskForm.value.description || null,
            priority: taskForm.value.priority || 'MEDIUM',
            due_date: taskForm.value.due_date || null,
            assigned_to: taskForm.value.assigned_to,
        });

        taskForm.value = {
            title: '',
            description: '',
            priority: 'MEDIUM',
            due_date: '',
            assigned_to: staff.value[0]?.id ?? '',
        };

        await loadTickets();
    } catch (error) {
        taskFormErrors.value = error?.response?.data?.errors || {};
        taskFormErrorMessage.value = error?.response?.data?.message || 'تعذر إنشاء المهمة الداخلية.';
    }
};

const createSubtask = async (ticketId) => {
    const payload = ensureSubtaskForm(ticketId);
    subtaskErrors.value[ticketId] = '';

    try {
        await axios.post(route('manager.subtasks.store'), {
            ticket_id: ticketId,
            title: payload.title,
            description: payload.description || null,
            due_date: payload.due_date || null,
            assigned_to: payload.assigned_to,
        });

        subtaskForms.value[ticketId] = {
            title: '',
            description: '',
            due_date: '',
            assigned_to: staff.value[0]?.id ?? '',
        };

        await loadTickets();
    } catch (error) {
        subtaskErrors.value[ticketId] = error?.response?.data?.message
            || Object.values(error?.response?.data?.errors || {}).flat().join(' | ')
            || 'تعذر إنشاء المهمة الفرعية.';
    }
};

const approveSubtask = async (subtaskId) => {
    actionLoading.value[`subtask-${subtaskId}`] = true;
    try {
        await axios.post(route('manager.subtasks.approve', subtaskId));
        await loadTickets();
    } finally {
        actionLoading.value[`subtask-${subtaskId}`] = false;
    }
};

const submitFinalReport = async (ticketId) => {
    finalErrors.value[ticketId] = '';

    try {
        await axios.post(route('manager.tickets.final_report', ticketId), {
            manager_final_report: finalReportByTicket.value[ticketId] || '',
        });
        await loadTickets();
    } catch (error) {
        finalErrors.value[ticketId] = error?.response?.data?.errors?.manager_final_report?.[0]
            || error?.response?.data?.message
            || 'تعذر إرسال التقرير النهائي.';
    }
};

const closeInternalTicket = async (ticketId) => {
    const confirmed = await actionDialog.confirm({
        title: 'إغلاق المهمة الداخلية',
        message: 'سيتم إغلاق المهمة الحالية ومنع متابعة العمل عليها. هل تريد المتابعة؟',
        confirmText: 'نعم، أغلق المهمة',
        cancelText: 'إلغاء',
        variant: 'warning',
    });
    if (!confirmed) return;

    actionLoading.value[`close-${ticketId}`] = true;
    try {
        await axios.post(route('manager.tickets.close', ticketId));
        await loadTickets();
    } finally {
        actionLoading.value[`close-${ticketId}`] = false;
    }
};

onMounted(async () => {
    await Promise.allSettled([loadAssociationRequests(), loadTickets()]);
});
</script>

<template>
    <Head title="لوحة مدير المدرسة" />

    <RoleLayout title="لوحة مدير المدرسة" role="SCHOOL_MANAGER" :animate-background="true">
        <div class="ui-page-shell manager-dashboard-shell space-y-6">
            <section class="manager-analytics-panel overflow-hidden rounded-2xl border border-slate-200 bg-white/95 shadow-sm dark:border-slate-800 dark:bg-slate-950/90">
                <div class="border-b border-slate-200 bg-slate-50/80 p-4 dark:border-slate-800 dark:bg-slate-900/70 sm:p-5">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div>
                            <p class="text-xs font-bold text-blue-700 dark:text-blue-300">لوحة تنفيذية</p>
                            <h1 class="mt-1 text-2xl font-black text-slate-950 dark:text-white">لوحة تحليلات المدرسة</h1>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                                {{ analytics.school?.name || 'مدرستك' }}
                                <span v-if="analytics.school?.school_id">- {{ analytics.school.school_id }}</span>
                                <span class="mx-2 text-slate-300 dark:text-slate-700">|</span>
                                آخر تحديث: {{ analytics.generatedAt || '-' }}
                            </p>
                        </div>

                        <form class="grid gap-2 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-950 sm:grid-cols-2 lg:grid-cols-3 xl:max-w-4xl" @submit.prevent="applyAnalyticsFilters">
                            <label class="space-y-1 text-xs font-bold text-slate-600 dark:text-slate-300">
                                الفترة
                                <select v-model="filterForm.period" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                    <option v-for="period in filterOptions.periods || []" :key="period.value" :value="period.value">{{ period.label }}</option>
                                </select>
                            </label>
                            <label class="space-y-1 text-xs font-bold text-slate-600 dark:text-slate-300">
                                المرحلة
                                <select v-model="filterForm.stage_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white" @change="onStageFilterChange">
                                    <option value="">كل المراحل</option>
                                    <option v-for="stage in filterOptions.stages || []" :key="stage.id" :value="stage.id">{{ stage.name }}</option>
                                </select>
                            </label>
                            <label class="space-y-1 text-xs font-bold text-slate-600 dark:text-slate-300">
                                الصف
                                <select v-model="filterForm.grade_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white" @change="onGradeFilterChange">
                                    <option value="">كل الصفوف</option>
                                    <option v-for="grade in filteredGrades" :key="grade.id" :value="grade.id">{{ grade.name }}</option>
                                </select>
                            </label>
                            <label class="space-y-1 text-xs font-bold text-slate-600 dark:text-slate-300">
                                الفصل
                                <select v-model="filterForm.classroom_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                    <option value="">كل الفصول</option>
                                    <option v-for="classroom in filteredClassrooms" :key="classroom.id" :value="classroom.id">{{ classroom.name }}</option>
                                </select>
                            </label>
                            <label class="space-y-1 text-xs font-bold text-slate-600 dark:text-slate-300">
                                المادة
                                <select v-model="filterForm.subject_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                    <option value="">كل المواد</option>
                                    <option v-for="subject in filterOptions.subjects || []" :key="subject.id" :value="subject.id">{{ subject.name }}</option>
                                </select>
                            </label>
                            <label class="space-y-1 text-xs font-bold text-slate-600 dark:text-slate-300">
                                المعلم
                                <select v-model="filterForm.teacher_id" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                    <option value="">كل المعلمين</option>
                                    <option v-for="teacher in filterOptions.teachers || []" :key="teacher.id" :value="teacher.id">{{ teacher.name }}</option>
                                </select>
                            </label>
                            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-3">
                                <button type="submit" class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-lg bg-blue-700 px-4 py-2 text-sm font-bold text-white hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-400">
                                    <SlidersHorizontal class="h-4 w-4" aria-hidden="true" />
                                    تطبيق الفلاتر
                                </button>
                                <button type="button" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-400 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900" @click="resetAnalyticsFilters" aria-label="إعادة ضبط فلاتر التحليلات">
                                    <RefreshCcw class="h-4 w-4" aria-hidden="true" />
                                    إعادة
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="space-y-5 p-4 sm:p-5">
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
                        <article
                            v-for="kpi in kpis"
                            :key="kpi.key"
                            class="rounded-xl border p-3 shadow-sm transition hover:-translate-y-0.5"
                            :class="statusClassName(kpi.status)"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-[11px] font-bold opacity-80">{{ kpi.label }}</p>
                                    <p class="mt-2 text-xl font-black sm:text-2xl">{{ numberText(kpi.value) }}</p>
                                </div>
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-white/70 dark:bg-slate-950/50">
                                    <component :is="iconFor(kpi.icon)" class="h-5 w-5" aria-hidden="true" />
                                </span>
                            </div>
                            <p class="mt-3 line-clamp-2 text-[11px] leading-5 opacity-80">{{ kpi.description }}</p>
                        </article>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3 dark:border-slate-800 dark:bg-slate-900/60">
                        <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div class="flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="أقسام تحليلات المدرسة">
                                <button
                                    v-for="slide in analyticsSlides"
                                    :key="slide.key"
                                    type="button"
                                    class="min-h-10 shrink-0 rounded-full border px-4 py-2 text-sm font-bold transition"
                                    :class="activeAnalyticsSlide === slide.key ? 'border-blue-500 bg-blue-700 text-white shadow-sm' : 'border-slate-200 bg-white text-slate-700 hover:border-blue-300 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200'"
                                    role="tab"
                                    :aria-selected="activeAnalyticsSlide === slide.key"
                                    @click="activeAnalyticsSlide = slide.key"
                                >
                                    {{ slide.label }}
                                </button>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" class="grid h-10 w-10 place-items-center rounded-full border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200" @click="previousAnalyticsSlide" aria-label="الشريحة السابقة">
                                    <ChevronRight class="h-5 w-5" aria-hidden="true" />
                                </button>
                                <span class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ activeSlideIndex + 1 }} / {{ analyticsSlides.length }}</span>
                                <button type="button" class="grid h-10 w-10 place-items-center rounded-full border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200" @click="nextAnalyticsSlide" aria-label="الشريحة التالية">
                                    <ChevronLeft class="h-5 w-5" aria-hidden="true" />
                                </button>
                            </div>
                        </div>

                        <div class="min-h-[360px]">
                            <div v-if="activeAnalyticsSlide === 'summary'" class="grid gap-4 lg:grid-cols-3">
                                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                    <h3 class="text-base font-black text-slate-950 dark:text-white">ملخص التشغيل</h3>
                                    <dl class="mt-4 space-y-3 text-sm">
                                        <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">حالة المدرسة</dt><dd class="font-bold text-slate-900 dark:text-white">{{ analytics.school?.status_label || '-' }}</dd></div>
                                        <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">حالة الإشراف</dt><dd class="font-bold text-slate-900 dark:text-white">{{ analytics.school?.supervision_status_label || '-' }}</dd></div>
                                        <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">الاشتراك</dt><dd class="font-bold text-slate-900 dark:text-white">{{ analytics.subscription?.status_label || '-' }}</dd></div>
                                        <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">المقاعد المستخدمة</dt><dd class="font-bold text-slate-900 dark:text-white">{{ analytics.subscription?.used_users ?? '-' }}</dd></div>
                                    </dl>
                                </article>
                                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                    <h3 class="text-base font-black text-slate-950 dark:text-white">حضور اليوم</h3>
                                    <div class="mt-4 grid grid-cols-2 gap-3 text-center">
                                        <div class="rounded-lg bg-emerald-500/10 p-3 text-emerald-700 dark:text-emerald-200"><p class="text-xs">حاضر</p><p class="text-xl font-black">{{ analytics.attendance?.summary?.today_present ?? 0 }}</p></div>
                                        <div class="rounded-lg bg-red-500/10 p-3 text-red-700 dark:text-red-200"><p class="text-xs">غائب</p><p class="text-xl font-black">{{ analytics.attendance?.summary?.today_absent ?? 0 }}</p></div>
                                        <div class="rounded-lg bg-amber-500/10 p-3 text-amber-700 dark:text-amber-200"><p class="text-xs">مأذون</p><p class="text-xl font-black">{{ analytics.attendance?.summary?.today_excused ?? 0 }}</p></div>
                                        <div class="rounded-lg bg-blue-500/10 p-3 text-blue-700 dark:text-blue-200"><p class="text-xs">النسبة</p><p class="text-xl font-black">{{ analytics.attendance?.summary?.today_attendance_rate ?? 'لا توجد' }}<span v-if="analytics.attendance?.summary?.today_attendance_rate !== null">%</span></p></div>
                                    </div>
                                </article>
                                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                    <h3 class="text-base font-black text-slate-950 dark:text-white">تنبيهات سريعة</h3>
                                    <div v-if="hasRows(analytics.summary?.alerts)" class="mt-4 space-y-2">
                                        <div v-for="alert in analytics.summary.alerts" :key="alert.title" class="rounded-lg border p-3 text-sm" :class="severityClassName(alert.severity)">
                                            <p class="font-bold">{{ alert.title }}</p>
                                            <p class="mt-1 text-xs opacity-80">{{ alert.description }}</p>
                                        </div>
                                    </div>
                                    <p v-else class="mt-4 rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">لا توجد تنبيهات حرجة حاليًا.</p>
                                </article>
                            </div>

                            <div v-else-if="activeAnalyticsSlide === 'students'" class="grid gap-4 lg:grid-cols-2">
                                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                    <h3 class="font-black text-slate-950 dark:text-white">توزيع الطلاب حسب المرحلة</h3>
                                    <div v-if="hasRows(analytics.students?.studentsByStage)" class="mt-4 space-y-3">
                                        <div v-for="row in analytics.students.studentsByStage" :key="row.label">
                                            <div class="mb-1 flex justify-between text-xs text-slate-500 dark:text-slate-400"><span>{{ row.label }}</span><span>{{ row.value }}</span></div>
                                            <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-2 rounded-full bg-blue-600" :style="{ width: barWidth(row.value, analytics.students.studentsByStage) }"></div></div>
                                        </div>
                                    </div>
                                    <p v-else class="mt-4 text-sm text-slate-500 dark:text-slate-400">{{ emptyText }}</p>
                                </article>
                                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                    <h3 class="font-black text-slate-950 dark:text-white">أعلى الفصول كثافة</h3>
                                    <div v-if="hasRows(analytics.students?.topDenseClassrooms)" class="mt-4 space-y-3">
                                        <div v-for="row in analytics.students.topDenseClassrooms" :key="row.id">
                                            <div class="mb-1 flex justify-between text-xs text-slate-500 dark:text-slate-400"><span>{{ row.label }}</span><span>{{ row.value }}</span></div>
                                            <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-2 rounded-full bg-cyan-600" :style="{ width: barWidth(row.value, analytics.students.topDenseClassrooms) }"></div></div>
                                        </div>
                                    </div>
                                    <p v-else class="mt-4 text-sm text-slate-500 dark:text-slate-400">{{ emptyText }}</p>
                                </article>
                                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                    <h3 class="font-black text-slate-950 dark:text-white">نشط وغير نشط</h3>
                                    <div class="mt-4 flex items-center gap-4">
                                        <div class="h-28 w-28 rounded-full" :style="pieStyle(analytics.students?.activeBreakdown)"></div>
                                        <div class="space-y-2 text-sm">
                                            <p v-for="row in analytics.students?.activeBreakdown || []" :key="row.label" class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                                                <span class="h-2 w-2 rounded-full bg-blue-600"></span>{{ row.label }}: <strong>{{ row.value }}</strong>
                                            </p>
                                        </div>
                                    </div>
                                </article>
                                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                    <h3 class="font-black text-slate-950 dark:text-white">الطلاب الجدد خلال الفترة</h3>
                                    <div v-if="hasRows(analytics.students?.newStudentsTrend)" class="mt-4 flex h-32 items-end gap-2">
                                        <div v-for="row in analytics.students.newStudentsTrend" :key="row.label" class="flex flex-1 flex-col items-center gap-2">
                                            <div class="w-full rounded-t bg-emerald-600" :style="{ height: barWidth(row.value, analytics.students.newStudentsTrend) }"></div>
                                            <span class="max-w-16 truncate text-[10px] text-slate-500">{{ row.label }}</span>
                                        </div>
                                    </div>
                                    <p v-else class="mt-4 text-sm text-slate-500 dark:text-slate-400">{{ emptyText }}</p>
                                </article>
                            </div>

                            <div v-else-if="activeAnalyticsSlide === 'attendance'" class="grid gap-4 lg:grid-cols-2">
                                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                    <h3 class="font-black text-slate-950 dark:text-white">توزيع حالات الحضور</h3>
                                    <div v-if="hasRows(analytics.attendance?.attendanceStatusDistribution)" class="mt-4 flex flex-col gap-3">
                                        <div v-for="row in analytics.attendance.attendanceStatusDistribution" :key="row.label">
                                            <div class="mb-1 flex justify-between text-xs text-slate-500 dark:text-slate-400"><span>{{ row.label }}</span><span>{{ row.value }}</span></div>
                                            <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-2 rounded-full bg-emerald-600" :style="{ width: barWidth(row.value, analytics.attendance.attendanceStatusDistribution) }"></div></div>
                                        </div>
                                    </div>
                                    <p v-else class="mt-4 text-sm text-slate-500 dark:text-slate-400">{{ emptyText }}</p>
                                </article>
                                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                    <h3 class="font-black text-slate-950 dark:text-white">اتجاه الحضور</h3>
                                    <div v-if="hasRows(analytics.attendance?.attendanceTrend)" class="mt-4 flex h-36 items-end gap-2">
                                        <div v-for="row in analytics.attendance.attendanceTrend" :key="row.label" class="flex flex-1 flex-col items-center gap-2">
                                            <div class="w-full rounded-t bg-blue-600" :style="{ height: `${Math.max(6, row.rate)}%` }"></div>
                                            <span class="text-[10px] text-slate-500">{{ row.rate }}%</span>
                                        </div>
                                    </div>
                                    <p v-else class="mt-4 text-sm text-slate-500 dark:text-slate-400">{{ emptyText }}</p>
                                </article>
                                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                    <h3 class="font-black text-slate-950 dark:text-white">الغياب حسب الفصل</h3>
                                    <div v-if="hasRows(analytics.attendance?.absenceByClassroom)" class="mt-4 space-y-3">
                                        <div v-for="row in analytics.attendance.absenceByClassroom" :key="row.label">
                                            <div class="mb-1 flex justify-between text-xs text-slate-500 dark:text-slate-400"><span>{{ row.label }}</span><span>{{ row.value }}</span></div>
                                            <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-2 rounded-full bg-red-600" :style="{ width: barWidth(row.value, analytics.attendance.absenceByClassroom) }"></div></div>
                                        </div>
                                    </div>
                                    <p v-else class="mt-4 text-sm text-slate-500 dark:text-slate-400">{{ emptyText }}</p>
                                </article>
                                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                    <h3 class="font-black text-slate-950 dark:text-white">طلاب يحتاجون متابعة</h3>
                                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <p class="mb-2 text-xs font-bold text-red-600 dark:text-red-300">الأكثر غيابًا</p>
                                            <p v-if="!hasRows(analytics.attendance?.topAbsentStudents)" class="text-sm text-slate-500">{{ emptyText }}</p>
                                            <p v-for="row in analytics.attendance?.topAbsentStudents || []" :key="row.label" class="mb-2 rounded-lg bg-red-500/10 p-2 text-sm text-red-700 dark:text-red-200">{{ row.label }} - {{ row.value }}</p>
                                        </div>
                                        <div>
                                            <p class="mb-2 text-xs font-bold text-amber-600 dark:text-amber-300">الأكثر إذنًا</p>
                                            <p v-if="!hasRows(analytics.attendance?.topLateStudents)" class="text-sm text-slate-500">{{ emptyText }}</p>
                                            <p v-for="row in analytics.attendance?.topLateStudents || []" :key="row.label" class="mb-2 rounded-lg bg-amber-500/10 p-2 text-sm text-amber-700 dark:text-amber-200">{{ row.label }} - {{ row.value }}</p>
                                        </div>
                                    </div>
                                </article>
                            </div>

                            <div v-else-if="activeAnalyticsSlide === 'leaves'" class="grid gap-4 lg:grid-cols-3">
                                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950 lg:col-span-1">
                                    <h3 class="font-black text-slate-950 dark:text-white">حالات الإجازات</h3>
                                    <div v-if="hasRows(analytics.leaves?.leavesByStatus)" class="mt-4 flex items-center gap-4">
                                        <div class="h-28 w-28 rounded-full" :style="pieStyle(analytics.leaves.leavesByStatus)"></div>
                                        <div class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                                            <p v-for="row in analytics.leaves.leavesByStatus" :key="row.label">{{ row.label }}: <strong>{{ row.value }}</strong></p>
                                        </div>
                                    </div>
                                    <p v-else class="mt-4 text-sm text-slate-500">{{ emptyText }}</p>
                                </article>
                                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950 lg:col-span-2">
                                    <h3 class="font-black text-slate-950 dark:text-white">أنواع الإجازات والطلاب الأكثر طلبًا</h3>
                                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                                        <div>
                                            <p class="mb-2 text-xs font-bold text-slate-500">حسب النوع</p>
                                            <p v-if="!hasRows(analytics.leaves?.leavesByType)" class="text-sm text-slate-500">{{ emptyText }}</p>
                                            <div v-for="row in analytics.leaves?.leavesByType || []" :key="row.label" class="mb-2">
                                                <div class="mb-1 flex justify-between text-xs text-slate-500"><span>{{ row.label }}</span><span>{{ row.value }}</span></div>
                                                <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-2 rounded-full bg-violet-600" :style="{ width: barWidth(row.value, analytics.leaves.leavesByType) }"></div></div>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="mb-2 text-xs font-bold text-slate-500">أكثر الطلاب إجازات</p>
                                            <p v-if="!hasRows(analytics.leaves?.topLeaveStudents)" class="text-sm text-slate-500">{{ emptyText }}</p>
                                            <p v-for="row in analytics.leaves?.topLeaveStudents || []" :key="row.label" class="mb-2 rounded-lg bg-violet-500/10 p-2 text-sm text-violet-700 dark:text-violet-200">{{ row.label }} - {{ row.value }}</p>
                                        </div>
                                    </div>
                                </article>
                            </div>

                            <div v-else-if="activeAnalyticsSlide === 'exams'" class="grid gap-4 lg:grid-cols-3">
                                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                    <h3 class="font-black text-slate-950 dark:text-white">ملخص الاختبارات</h3>
                                    <dl class="mt-4 space-y-3 text-sm">
                                        <div class="flex justify-between"><dt class="text-slate-500">اختبارات الفترة</dt><dd class="font-bold">{{ analytics.exams?.summary?.period_total ?? 0 }}</dd></div>
                                        <div class="flex justify-between"><dt class="text-slate-500">القادمة</dt><dd class="font-bold">{{ analytics.exams?.summary?.upcoming ?? 0 }}</dd></div>
                                        <div class="flex justify-between"><dt class="text-slate-500">متوسط النتائج</dt><dd class="font-bold">{{ analytics.exams?.summary?.average_percent ?? 'لا توجد' }}<span v-if="analytics.exams?.summary?.average_percent !== null">%</span></dd></div>
                                        <div class="flex justify-between"><dt class="text-slate-500">نسبة النجاح</dt><dd class="font-bold">{{ analytics.exams?.summary?.pass_rate ?? 'لا توجد' }}<span v-if="analytics.exams?.summary?.pass_rate !== null">%</span></dd></div>
                                    </dl>
                                </article>
                                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                    <h3 class="font-black text-slate-950 dark:text-white">متوسط النتائج حسب المادة</h3>
                                    <div v-if="hasRows(analytics.exams?.resultsBySubject)" class="mt-4 space-y-3">
                                        <div v-for="row in analytics.exams.resultsBySubject" :key="row.label">
                                            <div class="mb-1 flex justify-between text-xs text-slate-500"><span>{{ row.label }}</span><span>{{ row.value }}%</span></div>
                                            <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-2 rounded-full bg-blue-600" :style="{ width: `${Math.max(4, row.value)}%` }"></div></div>
                                        </div>
                                    </div>
                                    <p v-else class="mt-4 text-sm text-slate-500">{{ emptyText }}</p>
                                </article>
                                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                    <h3 class="font-black text-slate-950 dark:text-white">أقرب الاختبارات</h3>
                                    <div v-if="hasRows(analytics.exams?.upcomingExams)" class="mt-4 space-y-2">
                                        <div v-for="exam in analytics.exams.upcomingExams" :key="exam.id" class="rounded-lg bg-blue-500/10 p-3 text-sm text-blue-800 dark:text-blue-100">
                                            <p class="font-bold">{{ exam.title }}</p>
                                            <p class="mt-1 text-xs">{{ exam.subject }} - {{ exam.classroom }} - {{ exam.date }}</p>
                                        </div>
                                    </div>
                                    <p v-else class="mt-4 text-sm text-slate-500">{{ emptyText }}</p>
                                </article>
                            </div>

                            <div v-else-if="activeAnalyticsSlide === 'teachers'" class="grid gap-4 lg:grid-cols-3">
                                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                    <h3 class="font-black text-slate-950 dark:text-white">ملخص المعلمين</h3>
                                    <dl class="mt-4 space-y-3 text-sm">
                                        <div class="flex justify-between"><dt class="text-slate-500">الإجمالي</dt><dd class="font-bold">{{ analytics.teachers?.summary?.total ?? 0 }}</dd></div>
                                        <div class="flex justify-between"><dt class="text-slate-500">النشطون</dt><dd class="font-bold">{{ analytics.teachers?.summary?.active ?? 0 }}</dd></div>
                                        <div class="flex justify-between"><dt class="text-slate-500">لديهم تفويضات</dt><dd class="font-bold">{{ analytics.teachers?.summary?.delegated ?? 0 }}</dd></div>
                                        <div class="flex justify-between"><dt class="text-slate-500">لديهم اختبارات قادمة</dt><dd class="font-bold">{{ analytics.teachers?.summary?.with_upcoming_exams ?? 0 }}</dd></div>
                                    </dl>
                                </article>
                                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                    <h3 class="font-black text-slate-950 dark:text-white">الحصص الأسبوعية حسب المعلم</h3>
                                    <div v-if="hasRows(analytics.teachers?.teacherWeeklyLoad)" class="mt-4 space-y-3">
                                        <div v-for="row in analytics.teachers.teacherWeeklyLoad" :key="row.label">
                                            <div class="mb-1 flex justify-between text-xs text-slate-500"><span>{{ row.label }}</span><span>{{ row.value }}</span></div>
                                            <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-2 rounded-full bg-emerald-600" :style="{ width: barWidth(row.value, analytics.teachers.teacherWeeklyLoad) }"></div></div>
                                        </div>
                                    </div>
                                    <p v-else class="mt-4 text-sm text-slate-500">{{ emptyText }}</p>
                                </article>
                                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                    <h3 class="font-black text-slate-950 dark:text-white">متابعات المعلمين</h3>
                                    <div class="mt-4 space-y-3">
                                        <div>
                                            <p class="mb-2 text-xs font-bold text-amber-600 dark:text-amber-300">بدون جدول</p>
                                            <p v-if="!hasRows(analytics.teachers?.teachersWithoutSchedules)" class="text-sm text-slate-500">لا توجد حالات واضحة.</p>
                                            <p v-for="row in analytics.teachers?.teachersWithoutSchedules || []" :key="row.id" class="mb-2 rounded-lg bg-amber-500/10 p-2 text-sm text-amber-700 dark:text-amber-200">{{ row.label }}</p>
                                        </div>
                                        <div>
                                            <p class="mb-2 text-xs font-bold text-blue-600 dark:text-blue-300">بدون تفويضات</p>
                                            <p v-if="!hasRows(analytics.teachers?.teachersWithoutDelegations)" class="text-sm text-slate-500">لا توجد حالات واضحة.</p>
                                            <p v-for="row in analytics.teachers?.teachersWithoutDelegations || []" :key="row.id" class="mb-2 rounded-lg bg-blue-500/10 p-2 text-sm text-blue-700 dark:text-blue-200">{{ row.label }}</p>
                                        </div>
                                    </div>
                                </article>
                            </div>

                            <div v-else-if="activeAnalyticsSlide === 'schedules'" class="grid gap-4 lg:grid-cols-3">
                                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950 lg:col-span-2">
                                    <h3 class="font-black text-slate-950 dark:text-white">الحصص حسب اليوم</h3>
                                    <div v-if="hasRows(analytics.schedules?.lessonsByDay)" class="mt-4 flex h-40 items-end gap-3">
                                        <div v-for="row in analytics.schedules.lessonsByDay" :key="row.label" class="flex flex-1 flex-col items-center gap-2">
                                            <div class="w-full rounded-t bg-cyan-600" :style="{ height: barWidth(row.value, analytics.schedules.lessonsByDay) }"></div>
                                            <span class="text-xs text-slate-500">{{ row.label }}</span>
                                        </div>
                                    </div>
                                    <p v-else class="mt-4 text-sm text-slate-500">{{ emptyText }}</p>
                                </article>
                                <article class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950">
                                    <h3 class="font-black text-slate-950 dark:text-white">مواد غير مجدولة</h3>
                                    <div v-if="hasRows(analytics.schedules?.unscheduledSubjects)" class="mt-4 space-y-2">
                                        <p v-for="subject in analytics.schedules.unscheduledSubjects" :key="subject.id" class="rounded-lg bg-amber-500/10 p-2 text-sm text-amber-700 dark:text-amber-200">{{ subject.label }}</p>
                                    </div>
                                    <p v-else class="mt-4 text-sm text-slate-500">لا توجد مواد غير مجدولة ضمن البيانات الحالية.</p>
                                </article>
                            </div>

                            <div v-else-if="activeAnalyticsSlide === 'alerts'" class="grid gap-3 lg:grid-cols-2">
                                <article v-if="!hasRows(analytics.alerts)" class="rounded-xl border border-dashed border-slate-300 bg-white p-5 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400">
                                    لا توجد تنبيهات تحتاج تدخلك الآن.
                                </article>
                                <article v-for="alert in analytics.alerts || []" :key="alert.title" class="rounded-xl border p-4" :class="severityClassName(alert.severity)">
                                    <div class="flex items-start gap-3">
                                        <AlertTriangle class="mt-1 h-5 w-5 shrink-0" aria-hidden="true" />
                                        <div>
                                            <h3 class="font-black">{{ alert.title }}</h3>
                                            <p class="mt-1 text-sm opacity-85">{{ alert.description }}</p>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <div
                v-if="loadError"
                class="rounded-xl border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-200"
            >
                {{ loadError }}
            </div>

            <section class="rounded-xl border border-gray-800 bg-gray-900 p-4 sm:p-5">
                <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-lg font-bold">إنشاء مهمة داخلية للهيكل المدرسي</h2>
                </div>
                <p class="mb-4 text-xs text-gray-400">
                    يمكنك إنشاء مهمة مباشرة لأي مستخدم ضمن الهيكل التعليمي أو الإداري لنفس مدرستك فقط.
                </p>

                <form class="space-y-3" @submit.prevent="createInternalTask">
                    <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                        <div>
                            <input
                                v-model="taskForm.title"
                                class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                                placeholder="عنوان المهمة"
                            />
                            <p v-if="taskFormErrors.title" class="mt-1 text-xs text-red-400">{{ taskFormErrors.title[0] }}</p>
                        </div>
                        <div>
                            <select
                                v-model="taskForm.assigned_to"
                                class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                                :disabled="staff.length === 0"
                            >
                                <option value="" disabled>اختر مستخدمًا من نفس المدرسة</option>
                                <option v-for="member in staff" :key="member.id" :value="member.id">
                                    {{ assigneeLabel(member) }}
                                </option>
                            </select>
                            <p v-if="taskFormErrors.assigned_to" class="mt-1 text-xs text-red-400">{{ taskFormErrors.assigned_to[0] }}</p>
                        </div>
                    </div>

                    <textarea
                        v-model="taskForm.description"
                        rows="3"
                        class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                        placeholder="وصف المهمة"
                    />
                    <p v-if="taskFormErrors.description" class="text-xs text-red-400">{{ taskFormErrors.description[0] }}</p>

                    <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">الأولوية</label>
                            <select v-model="taskForm.priority" class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm">
                                <option value="LOW">منخفضة</option>
                                <option value="MEDIUM">متوسطة</option>
                                <option value="HIGH">عالية</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-400">تاريخ الاستحقاق</label>
                            <input v-model="taskForm.due_date" type="date" class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm" />
                        </div>
                        <div class="flex items-end">
                            <button
                                class="w-full rounded bg-blue-700 px-3 py-2 text-sm font-bold hover:bg-blue-600 disabled:opacity-60"
                                :disabled="staff.length === 0"
                            >
                                إنشاء المهمة
                            </button>
                        </div>
                    </div>

                    <p v-if="taskFormErrorMessage" class="text-xs text-red-400">{{ taskFormErrorMessage }}</p>
                    <p v-if="staff.length === 0" class="text-xs text-amber-300">
                        لا يوجد مستخدمون نشطون في الهيكل المدرسي لإسناد المهام حاليًا.
                    </p>
                </form>
            </section>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <section class="rounded-xl border border-gray-800 bg-gray-900 p-4 sm:p-5">
                    <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="text-lg font-bold">طلبات المصافحة</h2>
                        <button class="text-xs text-blue-400 hover:underline" @click="loadAssociationRequests">تحديث</button>
                    </div>

                    <div v-if="associationRequests.length === 0" class="rounded border border-dashed border-gray-700 p-3 text-sm text-gray-400">
                        لا توجد طلبات مصافحة حاليًا.
                    </div>

                    <div v-else class="space-y-2">
                        <div
                            v-for="item in associationRequests"
                            :key="item.id"
                            class="rounded border border-gray-700 bg-gray-800 p-3"
                        >
                            <div class="mb-1 flex items-start justify-between gap-2">
                                <p class="text-sm font-bold">{{ item.title }}</p>
                                <span class="rounded px-2 py-1 text-xs font-bold" :class="associationStatusClass(item.status)">
                                    {{ associationStatusLabel(item.status) }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-400">
                                {{ item.school?.name }} - المشرف: {{ item.supervisor?.name || '-' }}
                            </p>

                            <div v-if="item.status === 'PENDING'" class="mt-3 flex flex-wrap gap-2">
                                <button
                                    class="rounded bg-emerald-700 px-2 py-1 text-xs hover:bg-emerald-600 disabled:opacity-60"
                                    :disabled="actionLoading[`association-${item.id}`]"
                                    @click="approveAssociation(item.id)"
                                >
                                    موافقة
                                </button>
                                <button
                                    class="rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600 disabled:opacity-60"
                                    :disabled="actionLoading[`association-${item.id}`]"
                                    @click="rejectAssociation(item.id)"
                                >
                                    رفض
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-800 bg-gray-900 p-4 sm:p-5 lg:col-span-2">
                    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="text-lg font-bold">مهام المدرسة</h2>
                        <button class="text-xs text-blue-400 hover:underline" @click="loadTickets">تحديث</button>
                    </div>

                    <div v-if="loading" class="text-sm text-gray-400">جار تحميل المهام...</div>

                    <div v-else-if="tickets.length === 0" class="rounded border border-dashed border-gray-700 p-4 text-sm text-gray-400">
                        لا توجد مهام مرتبطة بحسابك.
                    </div>

                    <div v-else class="space-y-4">
                        <div
                            v-for="ticket in tickets"
                            :key="ticket.id"
                            class="rounded border border-gray-700 bg-gray-800 p-3"
                        >
                            <div class="mb-2 flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <div class="mb-1 flex flex-wrap items-center gap-2">
                                        <p class="font-bold">{{ ticket.title }}</p>
                                        <span class="rounded px-2 py-0.5 text-[11px]" :class="ticketSourceClass(ticket)">
                                            {{ ticketSourceLabel(ticket) }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-400">
                                        {{ ticket.school?.name }} - {{ priorityLabel(ticket.priority) }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        تاريخ الاستحقاق: {{ ticket.due_date || 'غير محدد' }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded px-2 py-1 text-xs font-bold" :class="statusClass(ticket.status)">
                                        {{ ticketStatusLabel(ticket.status) }}
                                    </span>
                                    <button
                                        v-if="isInternalTicket(ticket) && ticket.status !== 'CLOSED'"
                                        class="rounded bg-red-700 px-2 py-1 text-xs hover:bg-red-600 disabled:opacity-60"
                                        :disabled="actionLoading[`close-${ticket.id}`]"
                                        @click="closeInternalTicket(ticket.id)"
                                    >
                                        إغلاق
                                    </button>
                                </div>
                            </div>

                            <p class="mb-3 text-sm text-gray-300">{{ ticket.description }}</p>

                            <div class="mb-3 rounded border border-gray-700 bg-gray-900 p-3">
                                <p class="mb-2 text-xs font-bold text-gray-300">المهام الفرعية الحالية</p>
                                <div v-if="(ticket.subtasks || []).length === 0" class="text-xs text-gray-500">
                                    لا توجد مهام فرعية.
                                </div>
                                <div v-else class="space-y-2">
                                    <div
                                        v-for="subtask in ticket.subtasks"
                                        :key="subtask.id"
                                        class="rounded border border-gray-700 bg-gray-800 p-2 text-xs"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <div>
                                                <p class="font-bold">{{ subtask.title }}</p>
                                                <p class="text-gray-400">
                                                    {{ subtask.assignee?.name || `#${subtask.assigned_to}` }} - {{ subtaskStatusLabel(subtask.status) }}
                                                </p>
                                            </div>
                                            <button
                                                v-if="subtask.status === 'SUBMITTED'"
                                                class="rounded bg-emerald-700 px-2 py-1 hover:bg-emerald-600 disabled:opacity-60"
                                                :disabled="actionLoading[`subtask-${subtask.id}`]"
                                                @click="approveSubtask(subtask.id)"
                                            >
                                                اعتماد
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3 rounded border border-gray-700 bg-gray-900 p-3">
                                <p class="mb-2 text-xs font-bold text-gray-300">إضافة مهمة فرعية</p>
                                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                    <input
                                        v-model="ensureSubtaskForm(ticket.id).title"
                                        class="rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                                        placeholder="عنوان المهمة الفرعية"
                                        :disabled="!canAddSubtask(ticket)"
                                    />
                                    <select
                                        v-model="ensureSubtaskForm(ticket.id).assigned_to"
                                        class="rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                                        :disabled="staff.length === 0 || !canAddSubtask(ticket)"
                                    >
                                        <option value="" disabled>اختر مستخدمًا من نفس المدرسة</option>
                                        <option v-for="member in staff" :key="member.id" :value="member.id">
                                            {{ assigneeLabel(member) }}
                                        </option>
                                    </select>
                                </div>

                                <textarea
                                    v-model="ensureSubtaskForm(ticket.id).description"
                                    rows="2"
                                    class="mt-2 w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                                    placeholder="وصف المهمة"
                                    :disabled="!canAddSubtask(ticket)"
                                />

                                <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                                    <input
                                        v-model="ensureSubtaskForm(ticket.id).due_date"
                                        type="date"
                                        class="rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                                        :disabled="!canAddSubtask(ticket)"
                                    />
                                    <button
                                        class="rounded bg-blue-700 px-3 py-2 text-sm font-bold hover:bg-blue-600 disabled:opacity-60"
                                        :disabled="staff.length === 0 || !canAddSubtask(ticket)"
                                        @click="createSubtask(ticket.id)"
                                    >
                                        إنشاء مهمة فرعية
                                    </button>
                                </div>

                                <p v-if="subtaskErrors[ticket.id]" class="mt-2 text-xs text-red-400">{{ subtaskErrors[ticket.id] }}</p>
                                <p v-if="staff.length === 0" class="mt-2 text-xs text-amber-300">
                                    لا يوجد مستخدمون نشطون لإسناد المهام الفرعية.
                                </p>
                            </div>

                            <div v-if="!isInternalTicket(ticket)" class="rounded border border-gray-700 bg-gray-900 p-3">
                                <p class="mb-2 text-xs font-bold text-gray-300">التقرير النهائي للمدير</p>
                                <textarea
                                    v-model="finalReportByTicket[ticket.id]"
                                    rows="3"
                                    class="w-full rounded border border-gray-700 bg-gray-800 p-2 text-sm"
                                    placeholder="اكتب ملخص التنفيذ النهائي"
                                />
                                <div class="mt-2 flex justify-end">
                                    <button class="rounded bg-emerald-700 px-3 py-2 text-sm font-bold hover:bg-emerald-600" @click="submitFinalReport(ticket.id)">
                                        إرسال التقرير النهائي
                                    </button>
                                </div>
                                <p v-if="finalErrors[ticket.id]" class="mt-2 text-xs text-red-400">{{ finalErrors[ticket.id] }}</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </RoleLayout>
</template>

<style>
html.theme-dark .manager-analytics-panel {
    border-color: rgba(51, 65, 85, 0.82) !important;
    background:
        radial-gradient(circle at top right, rgba(14, 165, 233, 0.16), transparent 32rem),
        linear-gradient(180deg, rgba(15, 23, 42, 0.96), rgba(2, 6, 23, 0.96)) !important;
    color: rgb(226, 232, 240);
    box-shadow: 0 24px 70px rgba(2, 6, 23, 0.42);
}

html.theme-dark .manager-analytics-panel [class*='bg-white'],
html.theme-dark .manager-analytics-panel [class*='bg-slate-50'] {
    background: linear-gradient(180deg, rgba(15, 23, 42, 0.94), rgba(2, 6, 23, 0.88)) !important;
}

html.theme-dark .manager-analytics-panel [class*='bg-slate-100'] {
    background-color: rgba(30, 41, 59, 0.82) !important;
}

html.theme-dark .manager-analytics-panel [class*='border-slate-200'],
html.theme-dark .manager-analytics-panel [class*='border-slate-300'] {
    border-color: rgba(71, 85, 105, 0.72) !important;
}

html.theme-dark .manager-analytics-panel [class*='text-slate-950'],
html.theme-dark .manager-analytics-panel [class*='text-slate-900'] {
    color: rgb(248, 250, 252) !important;
}

html.theme-dark .manager-analytics-panel [class*='text-slate-700'],
html.theme-dark .manager-analytics-panel [class*='text-slate-600'] {
    color: rgb(203, 213, 225) !important;
}

html.theme-dark .manager-analytics-panel [class*='text-slate-500'],
html.theme-dark .manager-analytics-panel [class*='text-slate-400'] {
    color: rgb(148, 163, 184) !important;
}

html.theme-dark .manager-analytics-panel select {
    border-color: rgba(71, 85, 105, 0.82) !important;
    background-color: rgba(15, 23, 42, 0.96) !important;
    color: rgb(241, 245, 249) !important;
}

html.theme-dark .manager-analytics-panel option {
    background-color: rgb(15, 23, 42);
    color: rgb(241, 245, 249);
}

html.theme-dark .manager-analytics-panel button:not([class*='bg-blue-700']) {
    border-color: rgba(71, 85, 105, 0.75) !important;
}

html.theme-dark .manager-analytics-panel button:not([class*='bg-blue-700']):hover {
    background-color: rgba(30, 41, 59, 0.9) !important;
}

html.theme-dark .manager-analytics-panel [class*='bg-emerald-500'] {
    background-color: rgba(16, 185, 129, 0.13) !important;
}

html.theme-dark .manager-analytics-panel [class*='bg-red-500'] {
    background-color: rgba(239, 68, 68, 0.13) !important;
}

html.theme-dark .manager-analytics-panel [class*='bg-amber-500'] {
    background-color: rgba(245, 158, 11, 0.14) !important;
}

html.theme-dark .manager-analytics-panel [class*='bg-blue-500'] {
    background-color: rgba(59, 130, 246, 0.14) !important;
}

html.theme-dark .manager-analytics-panel [class*='bg-cyan-500'] {
    background-color: rgba(6, 182, 212, 0.14) !important;
}

html.theme-dark .manager-analytics-panel [class*='bg-violet-500'] {
    background-color: rgba(139, 92, 246, 0.14) !important;
}

html.theme-dark .manager-analytics-panel [class*='text-emerald-700'] {
    color: rgb(167, 243, 208) !important;
}

html.theme-dark .manager-analytics-panel [class*='text-red-700'],
html.theme-dark .manager-analytics-panel [class*='text-red-600'] {
    color: rgb(252, 165, 165) !important;
}

html.theme-dark .manager-analytics-panel [class*='text-amber-700'],
html.theme-dark .manager-analytics-panel [class*='text-amber-600'] {
    color: rgb(252, 211, 77) !important;
}

html.theme-dark .manager-analytics-panel [class*='text-blue-800'],
html.theme-dark .manager-analytics-panel [class*='text-blue-700'],
html.theme-dark .manager-analytics-panel [class*='text-blue-600'] {
    color: rgb(147, 197, 253) !important;
}

html.theme-dark .manager-analytics-panel [class*='text-cyan-700'] {
    color: rgb(103, 232, 249) !important;
}

html.theme-dark .manager-analytics-panel [class*='text-violet-700'] {
    color: rgb(196, 181, 253) !important;
}

html.theme-dark .manager-analytics-panel [class*='border-dashed'] {
    border-color: rgba(71, 85, 105, 0.8) !important;
}
</style>
