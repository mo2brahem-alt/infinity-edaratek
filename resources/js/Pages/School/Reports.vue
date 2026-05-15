<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    BookOpenCheck,
    Building2,
    CalendarClock,
    Download,
    FileSpreadsheet,
    FileText,
    Layers3,
    RotateCcw,
    Users,
    UserRound,
} from 'lucide-vue-next';
import RoleLayout from '@/Layouts/RoleLayout.vue';
import AppFilterBar from '@/Components/AppFilterBar.vue';
import AppSearchField from '@/Components/AppSearchField.vue';
import AppStatePanel from '@/Components/AppStatePanel.vue';

const props = defineProps({
    school: { type: Object, default: null },
    summary: { type: Object, default: () => ({}) },
    selectedEntity: { type: String, default: 'students' },
    entityOptions: { type: Array, default: () => [] },
    table: { type: Object, default: () => ({ columns: [], rows: [], meta: {} }) },
    filters: { type: Object, default: () => ({}) },
    filterOptions: { type: Object, default: () => ({}) },
    isManager: { type: Boolean, default: false },
    permissions: { type: Object, default: () => ({}) },
});

const page = usePage();
const currentUser = computed(() => page.props.auth?.user || null);
const roleForLayout = computed(() => {
    if (props.isManager) return 'SCHOOL_MANAGER';
    return currentUser.value?.primary_role === 'school_manager' ? 'SCHOOL_MANAGER' : 'STAFF';
});

const form = ref({
    entity: props.filters.entity || props.selectedEntity || 'students',
    search: props.filters.search || '',
    stage_id: props.filters.stage_id || '',
    grade_name: props.filters.grade_name || '',
    classroom_id: props.filters.classroom_id || '',
    student_id: props.filters.student_id || '',
    teacher_id: props.filters.teacher_id || '',
    leave_type_id: props.filters.leave_type_id || '',
    attendance_status: props.filters.attendance_status || '',
    leave_status: props.filters.leave_status || '',
    active_state: props.filters.active_state || 'all',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    per_page: props.filters.per_page || 25,
});

const exportFormat = ref('csv');

watch(
    () => props.filters,
    (next) => {
        form.value = {
            entity: next.entity || props.selectedEntity || 'students',
            search: next.search || '',
            stage_id: next.stage_id || '',
            grade_name: next.grade_name || '',
            classroom_id: next.classroom_id || '',
            student_id: next.student_id || '',
            teacher_id: next.teacher_id || '',
            leave_type_id: next.leave_type_id || '',
            attendance_status: next.attendance_status || '',
            leave_status: next.leave_status || '',
            active_state: next.active_state || 'all',
            date_from: next.date_from || '',
            date_to: next.date_to || '',
            per_page: next.per_page || 25,
        };
    },
    { deep: true }
);

const tableColumns = computed(() => props.table?.columns || []);
const tableRows = computed(() => props.table?.rows || []);
const tableMeta = computed(() => props.table?.meta || {});
const hasRows = computed(() => tableRows.value.length > 0);

const selectedEntityLabel = computed(() => {
    const options = props.filterOptions?.entityOptions || props.entityOptions || [];
    return options.find((item) => item.value === form.value.entity)?.label || 'التقارير';
});

const summaryCards = computed(() => [
    {
        key: 'students',
        label: 'الطلاب',
        value: props.summary.students_count || 0,
        helper: 'إجمالي السجلات الطلابية المتاحة داخل المدرسة.',
        icon: Users,
    },
    {
        key: 'stages',
        label: 'المراحل',
        value: props.summary.stages_count || 0,
        helper: 'المراحل المرتبطة بالهيكل الأكاديمي الحالي.',
        icon: Layers3,
    },
    {
        key: 'grades',
        label: 'الصفوف',
        value: props.summary.grades_count || 0,
        helper: 'الصفوف التعليمية المفعلة ضمن الهيكل الطلابي.',
        icon: BookOpenCheck,
    },
    {
        key: 'classrooms',
        label: 'الفصول',
        value: props.summary.classrooms_count || 0,
        helper: 'الفصول التعليمية المتاحة للمتابعة والتقارير.',
        icon: Building2,
    },
    {
        key: 'teachers',
        label: 'المعلمون',
        value: props.summary.teachers_count || 0,
        helper: 'المعلمون المرتبطون بالجداول والأنشطة الحالية.',
        icon: UserRound,
    },
    {
        key: 'records',
        label: 'السجلات',
        value: (props.summary.attendance_records_count || 0) + (props.summary.leave_requests_count || 0),
        helper: 'سجلات الحضور والإجازات المتاحة للتصدير والتحليل.',
        icon: FileSpreadsheet,
    },
]);

const toQuery = (payload = {}) => {
    const merged = { ...form.value, ...payload };
    const query = {};

    Object.entries(merged).forEach(([key, value]) => {
        if (value === '' || value === null || typeof value === 'undefined') return;
        query[key] = value;
    });

    return query;
};

const applyFilters = () => {
    router.get(route('school.reports.index'), toQuery({ page: 1 }), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const resetFilters = () => {
    form.value = {
        entity: props.selectedEntity || 'students',
        search: '',
        stage_id: '',
        grade_name: '',
        classroom_id: '',
        student_id: '',
        teacher_id: '',
        leave_type_id: '',
        attendance_status: '',
        leave_status: '',
        active_state: 'all',
        date_from: '',
        date_to: '',
        per_page: 25,
    };
    applyFilters();
};

const goToPage = (targetPage) => {
    const pageNumber = Number(targetPage || 1);
    if (!Number.isFinite(pageNumber) || pageNumber <= 0) return;

    router.get(route('school.reports.index'), toQuery({ page: pageNumber }), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const exportReport = () => {
    const href = route('school.reports.export', {
        ...toQuery(),
        entity: form.value.entity || props.selectedEntity || 'students',
        format: exportFormat.value || 'csv',
    });

    window.open(href, '_blank');
};

const formatCellValue = (value) => {
    if (value === null || typeof value === 'undefined' || value === '') {
        return '-';
    }

    if (Array.isArray(value)) {
        return value.join('، ');
    }

    if (typeof value === 'object') {
        return value.label || value.name || value.title || JSON.stringify(value);
    }

    return String(value);
};
</script>

<template>
    <Head title="التقارير" />

    <RoleLayout title="التقارير" :role="roleForLayout" :permissions="props.permissions">
        <div class="ui-page-shell">
            <section class="ui-page-hero">
                <div class="ui-page-header">
                    <div class="ui-page-heading text-right">
                        <span class="ui-page-kicker">
                            <FileText class="h-4 w-4" />
                            <span>مركز التقارير والتحليلات</span>
                        </span>
                        <h1 class="ui-page-title">التقارير</h1>
                        <p class="ui-page-copy">
                            استعرض البيانات المدرسية، صفِّها بدقة، ثم صدّر التقرير المناسب من واجهة أوضح وأكثر اتساقًا على جميع المقاسات.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2 text-right">
                        <span class="ui-chip">نوع التقرير: {{ selectedEntityLabel }}</span>
                        <span class="ui-chip">إجمالي السجلات: {{ tableMeta.total || tableRows.length || 0 }}</span>
                    </div>
                </div>

                <div class="ui-stat-grid mt-6">
                    <article v-for="item in summaryCards" :key="item.key" class="ui-stat-card">
                        <div class="ui-stat-meta">
                            <div class="text-right">
                                <p class="ui-stat-label">{{ item.label }}</p>
                                <h2 class="ui-stat-value">{{ item.value }}</h2>
                            </div>
                            <div class="ui-stat-icon">
                                <component :is="item.icon" class="h-5 w-5" />
                            </div>
                        </div>
                        <p class="text-sm leading-7 text-slate-400">{{ item.helper }}</p>
                    </article>
                </div>
            </section>

            <AppFilterBar>
                <div class="ui-section-header !mb-0">
                    <div class="ui-section-heading text-right">
                        <h2 class="ui-section-title">فلاتر التقرير</h2>
                        <p class="ui-section-subtitle">اختر النطاق المناسب ثم طبّق الفلاتر للحصول على تقرير أدق وأسهل قراءة.</p>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <span class="ui-chip">لكل صفحة: {{ form.per_page }}</span>
                        <span class="ui-chip">التصدير: {{ exportFormat }}</span>
                    </div>
                </div>

                <div class="ui-filter-row mt-4">
                    <AppSearchField v-model="form.search" class="flex-1" placeholder="بحث عام..." aria-label="بحث عام في التقرير" />

                    <select v-model="form.entity" class="ui-select md:max-w-[13rem]" aria-label="نوع التقرير">
                        <option v-for="item in filterOptions.entityOptions || entityOptions" :key="item.value" :value="item.value">{{ item.label }}</option>
                    </select>

                    <select v-model="form.stage_id" class="ui-select md:max-w-[13rem]" aria-label="المرحلة">
                        <option value="">كل المراحل</option>
                        <option v-for="item in filterOptions.stages || []" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </select>

                    <select v-model="form.grade_name" class="ui-select md:max-w-[13rem]" aria-label="الصف">
                        <option value="">كل الصفوف</option>
                        <option v-for="item in filterOptions.grades || []" :key="item.id" :value="item.name">{{ item.name }}</option>
                    </select>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <select v-model="form.classroom_id" class="ui-select" aria-label="الفصل">
                        <option value="">كل الفصول</option>
                        <option v-for="item in filterOptions.classrooms || []" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </select>
                    <select v-model="form.student_id" class="ui-select" aria-label="الطالب">
                        <option value="">كل الطلاب</option>
                        <option v-for="item in filterOptions.students || []" :key="item.id" :value="item.id">{{ item.full_name }}</option>
                    </select>
                    <select v-model="form.teacher_id" class="ui-select" aria-label="المعلم">
                        <option value="">كل المعلمين</option>
                        <option v-for="item in filterOptions.teachers || []" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </select>
                    <select v-model="form.leave_type_id" class="ui-select" aria-label="نوع الإجازة">
                        <option value="">كل أنواع الإجازات</option>
                        <option v-for="item in filterOptions.leaveTypes || []" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </select>
                    <select v-model="form.attendance_status" class="ui-select" aria-label="حالة الحضور">
                        <option value="">كل حالات الحضور</option>
                        <option v-for="item in filterOptions.attendanceStatuses || []" :key="item.value" :value="item.value">{{ item.label }}</option>
                    </select>
                    <select v-model="form.leave_status" class="ui-select" aria-label="حالة الإجازة">
                        <option value="">كل حالات الإجازات</option>
                        <option v-for="item in filterOptions.leaveStatuses || []" :key="item.value" :value="item.value">{{ item.label }}</option>
                    </select>
                    <select v-model="form.active_state" class="ui-select" aria-label="الحالة">
                        <option v-for="item in filterOptions.activeStates || []" :key="item.value" :value="item.value">{{ item.label }}</option>
                    </select>
                    <select v-model="form.per_page" class="ui-select" aria-label="عدد العناصر في الصفحة">
                        <option v-for="item in filterOptions.perPageOptions || [10, 25, 50, 100]" :key="item" :value="item">{{ item }} / صفحة</option>
                    </select>
                    <input v-model="form.date_from" type="date" class="ui-input" aria-label="من تاريخ" />
                    <input v-model="form.date_to" type="date" class="ui-input" aria-label="إلى تاريخ" />
                    <select v-model="exportFormat" class="ui-select" aria-label="صيغة التصدير">
                        <option v-for="fmt in filterOptions.exportFormats || []" :key="fmt.value" :value="fmt.value">{{ fmt.label }}</option>
                    </select>
                </div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="ui-primary-button" @click="applyFilters">
                            <Search class="h-4 w-4" />
                            <span>تطبيق الفلاتر</span>
                        </button>
                        <button type="button" class="ui-ghost-button" @click="resetFilters">
                            <RotateCcw class="h-4 w-4" />
                            <span>إعادة ضبط</span>
                        </button>
                    </div>

                    <button type="button" class="ui-secondary-button self-end sm:self-auto" @click="exportReport">
                        <Download class="h-4 w-4" />
                        <span>تصدير التقرير</span>
                    </button>
                </div>
            </AppFilterBar>

            <section class="ui-table-shell">
                <div class="ui-table-header">
                    <div class="ui-section-header !mb-0">
                        <div class="ui-section-heading text-right">
                            <h2 class="ui-section-title">{{ table.title || 'نتائج التقرير' }}</h2>
                            <p class="ui-section-subtitle">عرض جدولي على الشاشات الكبيرة، وبطاقات مبسطة على الشاشات الصغيرة لتسهيل القراءة بالعربية.</p>
                        </div>

                        <div class="flex items-center justify-end gap-2">
                            <span class="ui-chip">
                                <CalendarClock class="h-3.5 w-3.5" />
                                <span>صفحة {{ tableMeta.current_page || 1 }} من {{ tableMeta.last_page || 1 }}</span>
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="!hasRows" class="p-4 md:p-6">
                    <AppStatePanel
                        variant="no-results"
                        title="لا توجد بيانات مطابقة للفلاتر الحالية"
                        description="جرّب تعديل الفلاتر أو توسيع البحث لعرض المزيد من النتائج داخل التقرير."
                        compact
                    />
                </div>

                <template v-else>
                    <div class="hidden lg:block ui-table-container">
                        <table class="ui-data-table min-w-[960px]">
                            <thead>
                                <tr>
                                    <th v-for="col in tableColumns" :key="col.key">{{ col.label }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, idx) in tableRows" :key="idx">
                                    <td v-for="col in tableColumns" :key="`${idx}-${col.key}`">{{ formatCellValue(row[col.key]) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="ui-mobile-card-list">
                        <article v-for="(row, idx) in tableRows" :key="`mobile-${idx}`" class="ui-mobile-row-card text-right">
                            <div class="mb-3 flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-base font-black text-white">{{ formatCellValue(row[tableColumns[0]?.key]) }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ tableColumns[0]?.label || 'العنصر' }}</p>
                                </div>
                                <span class="ui-chip shrink-0">{{ selectedEntityLabel }}</span>
                            </div>

                            <div class="grid grid-cols-1 gap-3">
                                <div v-for="col in tableColumns.slice(1)" :key="`${idx}-mobile-${col.key}`">
                                    <p class="ui-mobile-row-label">{{ col.label }}</p>
                                    <p class="mt-1 break-words text-sm text-slate-200">{{ formatCellValue(row[col.key]) }}</p>
                                </div>
                            </div>
                        </article>
                    </div>
                </template>

                <div v-if="(tableMeta.last_page || 1) > 1" class="border-t border-white/10 px-4 py-4 md:px-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-slate-400">صفحة {{ tableMeta.current_page || 1 }} من {{ tableMeta.last_page || 1 }}</p>
                        <div class="flex flex-wrap justify-end gap-2">
                            <button
                                type="button"
                                class="ui-ghost-button"
                                :disabled="(tableMeta.current_page || 1) <= 1"
                                @click="goToPage((tableMeta.current_page || 1) - 1)"
                            >
                                السابق
                            </button>
                            <button
                                type="button"
                                class="ui-ghost-button"
                                :disabled="(tableMeta.current_page || 1) >= (tableMeta.last_page || 1)"
                                @click="goToPage((tableMeta.current_page || 1) + 1)"
                            >
                                التالي
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </RoleLayout>
</template>
