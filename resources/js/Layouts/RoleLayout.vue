<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutDashboard, User, LogOut, ListChecks, ClipboardList, Building2, GraduationCap, BookOpen, CalendarDays, FileText, Settings, ChevronDown, Menu, X, Award } from 'lucide-vue-next';
import RealtimeNotifications from '@/Components/Notifications/RealtimeNotifications.vue';
import AppDashboardFooter from '@/Components/Layout/AppDashboardFooter.vue';
import AppPageFeedback from '@/Components/AppPageFeedback.vue';
import ThemeModeSwitch from '@/Components/Layout/ThemeModeSwitch.vue';
import { useThemeMode } from '@/composables/useThemeMode';
import { useProjectBranding } from '@/composables/useProjectBranding';

const props = defineProps({
    title: { type: String, default: 'لوحة التحكم' },
    role: { type: String, default: 'USER' },
    animateBackground: { type: Boolean, default: true },
    permissions: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const { isLightMode } = useThemeMode();
const { siteName, projectLogoUrl, showHeaderLogo, headerLogoStyle } = useProjectBranding();
const user = computed(() => page.props?.auth?.user || null);
const normalizedRole = computed(() => String(props.role || '').toUpperCase());
const pulsingRouteNames = ref([]);
const isMobileSidebarOpen = ref(false);

const setDashboardScrollLock = (shouldLock) => {
    if (typeof document === 'undefined') return;

    document.documentElement.style.overflow = shouldLock ? 'hidden' : '';
    document.body.style.overflow = shouldLock ? 'hidden' : '';
};

const studentLeavesFeatureEnabled = computed(() => {
    const value = page.props?.features?.student_leaves_enabled;

    if (value === undefined || value === null) {
        return true;
    }

    return Boolean(value);
});

const fallbackRoutePaths = {
    dashboard: '/dashboard',
    'supervisor.dashboard': '/supervisor/dashboard',
    'supervisor.onboarding.show': '/supervisor/onboarding',
    'supervisor.requests.page': '/supervisor/requests/inbox',
    'manager.dashboard': '/manager/dashboard',
    'manager.onboarding.show': '/manager/onboarding',
    'manager.requests.page': '/manager/requests/inbox',
    'manager.structure.index': '/manager/structure',
    'staff.dashboard': '/staff/dashboard',
    'school.student_structure.index': '/school/student-structure',
    'school.student_attendance.index': '/school/student-attendance',
    'school.reports.index': '/school/reports',
    'school.student_leaves.index': '/school/student-leaves',
    'school.academic_planning.index': '/school/academic-planning',
    'school.exams.index': '/school/exams',
    'school.certificates.index': '/school/certificates',
};

const fallbackPathFor = (routeName) => fallbackRoutePaths[routeName] || '#';

const resolvePermission = (key) => {
    const fromLayoutProps = props.permissions?.[key];
    if (fromLayoutProps !== undefined && fromLayoutProps !== null) {
        return Boolean(fromLayoutProps);
    }

    const fromAuthUser = user.value?.[key];
    if (fromAuthUser !== undefined && fromAuthUser !== null) {
        return Boolean(fromAuthUser);
    }

    const fromPageUser = page.props?.user?.[key];
    return Boolean(fromPageUser);
};

const canManageStudentStructure = computed(() => resolvePermission('can_manage_student_structure'));
const canManageStudentAttendance = computed(() => resolvePermission('can_manage_student_attendance'));
const canManageAcademicPlanning = computed(() => resolvePermission('can_manage_academic_planning'));
const canManageSchoolExams = computed(() => resolvePermission('can_manage_school_exams'));
const canManageSchoolReports = computed(() => resolvePermission('can_manage_school_reports'));
const canAccessCertificates = computed(() => resolvePermission('can_access_certificates') || resolvePermission('can_issue_certificates') || resolvePermission('can_print_certificates'));
const canUseExamQuestionBank = computed(() => resolvePermission('can_use_question_bank') || resolvePermission('can_manage_question_bank'));
const canManageStudentLeaves = computed(() => resolvePermission('can_manage_student_leaves'));
const canManageLeaveTypes = computed(() => resolvePermission('can_manage_leave_types'));
const canManageSchoolCalendar = computed(() => resolvePermission('can_manage_school_calendar'));
const canManageSchoolHolidays = computed(() => resolvePermission('can_manage_school_holidays'));

const canAccessStudentLeavesModule = computed(() =>
    studentLeavesFeatureEnabled.value
    && (
        canManageStudentLeaves.value
        || canManageLeaveTypes.value
        || canManageSchoolCalendar.value
        || canManageSchoolHolidays.value
    )
);

const canAccessReports = computed(() =>
    canManageSchoolReports.value
    || canManageStudentStructure.value
    || canManageStudentAttendance.value
    || canManageAcademicPlanning.value
    || canAccessStudentLeavesModule.value
);

const canAccessAcademicCalendarPage = computed(() =>
    canManageSchoolCalendar.value
    || canManageSchoolHolidays.value
    || canManageLeaveTypes.value
);

const hasRouteName = (routeName) => {
    try {
        return route().has(routeName);
    } catch {
        return false;
    }
};

const routeParamsToQuery = (params = {}) => {
    const entries = Object.entries(params).filter(([, value]) => value !== undefined && value !== null && String(value) !== '');
    if (entries.length === 0) return '';

    return `?${new URLSearchParams(entries.map(([key, value]) => [key, String(value)])).toString()}`;
};

const safeRouteHref = (routeName, params = {}) => {
    try {
        if (hasRouteName(routeName)) {
            return route(routeName, params);
        }
    } catch {
        // no-op, fallback below
    }

    const fallback = fallbackPathFor(routeName);
    if (fallback === '#') return fallback;

    return `${fallback}${routeParamsToQuery(params)}`;
};

const isCurrentRoute = (routeName) => {
    try {
        if (hasRouteName(routeName)) {
            return route().current(routeName);
        }
    } catch {
        // no-op, fallback below
    }

    const fallbackPath = fallbackPathFor(routeName);
    if (fallbackPath === '#') return false;

    if (typeof window !== 'undefined') {
        return window.location.pathname === fallbackPath;
    }

    return false;
};

const dashboardRouteName = computed(() => {
    if (normalizedRole.value === 'SUPERVISOR') return 'supervisor.dashboard';
    if (normalizedRole.value === 'SCHOOL_MANAGER') return 'manager.dashboard';
    if (normalizedRole.value === 'STAFF') return 'staff.dashboard';
    return 'dashboard';
});

const profileHref = computed(() => {
    if (hasRouteName('profile.edit')) {
        return safeRouteHref('profile.edit');
    }

    return safeRouteHref(dashboardRouteName.value);
});

const roleLabel = computed(() => {
    if (normalizedRole.value === 'SUPERVISOR') return 'المشرف';
    if (normalizedRole.value === 'SCHOOL_MANAGER') return 'مدير المدرسة';
    if (normalizedRole.value === 'STAFF') return 'الموظف';
    return 'مستخدم';
});

const userInitial = computed(() => {
    const name = String(user.value?.name || '').trim();
    return name !== '' ? name.charAt(0).toUpperCase() : 'U';
});

const profilePhotoUrl = computed(() => {
    const path = String(user.value?.profile_photo_path || '').trim();
    if (path === '') return null;

    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) {
        return path;
    }

    return `/media-files/${path}`;
});

const userMenuLogoUrl = computed(() => projectLogoUrl.value || profilePhotoUrl.value);

const updatePulsingRoutes = (routeNames) => {
    pulsingRouteNames.value = Array.isArray(routeNames) ? routeNames : [];
};

const isPulsingRoute = (routeName) => pulsingRouteNames.value.includes(routeName);
const closeMobileSidebar = () => {
    isMobileSidebarOpen.value = false;
};

const handleSidebarLinkNavigation = (event) => {
    if (typeof window === 'undefined' || window.innerWidth >= 768) return;

    const target = event.target;
    if (!(target instanceof Element)) return;

    const link = target.closest('a[href]');
    if (!link) return;

    const href = String(link.getAttribute('href') || '').trim();
    if (href === '' || href === '#') return;

    closeMobileSidebar();
};

const toggleMobileSidebar = () => {
    isMobileSidebarOpen.value = !isMobileSidebarOpen.value;
};

const navItemClass = (routeName, params = {}) => {
    const isActive = isCurrentRouteWithParams(routeName, params);
    const base = isActive
        ? 'sidebar-nav-link sidebar-nav-link--active'
        : 'sidebar-nav-link sidebar-nav-link--idle';

    if (isActive || !isPulsingRoute(routeName)) {
        return base;
    }

    return `${base} ring-2 ring-amber-300/70 ring-offset-1 ring-offset-slate-900 animate-pulse`;
};

const currentExamSection = computed(() => {
    const pageUrl = String(page.url || '');
    const query = pageUrl.includes('?') ? pageUrl.split('?')[1] : '';
    return new URLSearchParams(query).get('section') || '';
});

const currentAcademicPlanningPage = computed(() => {
    const pageUrl = String(page.url || '');
    const query = pageUrl.includes('?') ? pageUrl.split('?')[1] : '';
    const requestedPage = String(new URLSearchParams(query).get('page') || '').trim();
    return requestedPage !== '' ? requestedPage : 'stages';
});

const isCurrentRouteWithParams = (routeName, params = {}) => {
    if (!isCurrentRoute(routeName)) return false;

    if (routeName === 'school.exams.index') {
        const requestedSection = String(params.section || '').trim();
        const activeSection = String(currentExamSection.value || '').trim();
        const normalizedRequestedSection = requestedSection === '' || requestedSection === 'settings'
            ? 'scheduling'
            : requestedSection;
        const normalizedActiveSection = activeSection === '' || activeSection === 'settings'
            ? 'scheduling'
            : activeSection;

        return normalizedActiveSection === normalizedRequestedSection;
    }

    if (routeName === 'school.academic_planning.index') {
        const requestedPage = String(params.page || '').trim();
        const activePage = String(currentAcademicPlanningPage.value || '').trim();

        if (requestedPage === '') {
            return activePage === 'stages';
        }

        return activePage === requestedPage;
    }

    return true;
};

const subNavItemClass = (routeName, params = {}) =>
    isCurrentRouteWithParams(routeName, params)
        ? 'sidebar-subnav-link sidebar-subnav-link--active'
        : 'sidebar-subnav-link sidebar-subnav-link--idle';

const examSubLinks = computed(() => {
    const links = [];

    links.push({
        label: 'جدول الاختبارات',
        routeName: 'school.exams.index',
        params: { section: 'scheduling' },
    });

    links.push({
        label: 'الاختبار المحدد: الأسئلة والدرجات',
        routeName: 'school.exams.index',
        params: { section: 'selected' },
    });

    if (canUseExamQuestionBank.value) {
        links.push({
            label: 'بنك الأسئلة',
            routeName: 'school.exams.index',
            params: { section: 'question-bank' },
        });
    }

    return links;
});

const roleLinks = computed(() => {
    if (normalizedRole.value === 'SUPERVISOR') {
        return [
            { label: 'التهيئة', routeName: 'supervisor.onboarding.show', icon: ListChecks },
            { label: 'الطلبات', routeName: 'supervisor.requests.page', icon: ClipboardList },
        ];
    }

    const links = [];

    if (normalizedRole.value === 'SCHOOL_MANAGER') {
        links.push(
            { label: 'التهيئة', routeName: 'manager.onboarding.show', icon: ListChecks },
            { label: 'الطلبات', routeName: 'manager.requests.page', icon: ClipboardList },
            { label: 'هيكل المدرسة', routeName: 'manager.structure.index', icon: Building2 },
        );
    }

    if (normalizedRole.value === 'SCHOOL_MANAGER' || normalizedRole.value === 'STAFF') {
        const academicStructureLinks = [];
        if (normalizedRole.value === 'SCHOOL_MANAGER' || canManageStudentStructure.value) {
            academicStructureLinks.push({ label: 'المراحل الدراسية', routeName: 'school.academic_planning.index', icon: BookOpen, params: { page: 'stages' } });
        }
        if (normalizedRole.value === 'SCHOOL_MANAGER' || canManageAcademicPlanning.value) {
            academicStructureLinks.push(
                { label: 'العام الدراسي', routeName: 'school.academic_planning.index', icon: BookOpen, params: { page: 'years' } },
                { label: 'الفصل الدراسي', routeName: 'school.academic_planning.index', icon: BookOpen, params: { page: 'terms' } },
            );
        }
        if (normalizedRole.value === 'SCHOOL_MANAGER' || canAccessAcademicCalendarPage.value) {
            academicStructureLinks.push({ label: 'إعدادات التقويم المدرسي', routeName: 'school.academic_planning.index', icon: CalendarDays, params: { page: 'calendar' } });
        }

        if (academicStructureLinks.length > 0) {
            links.push({ type: 'heading', label: 'الهيكل الأكاديمي' }, ...academicStructureLinks);
        }

        const curriculumLinks = [];
        if (normalizedRole.value === 'SCHOOL_MANAGER' || canManageAcademicPlanning.value) {
            curriculumLinks.push(
                { label: 'المواد التعليمية', routeName: 'school.academic_planning.index', icon: BookOpen, params: { page: 'subjects' } },
                { label: 'الجداول الدراسية', routeName: 'school.academic_planning.index', icon: ClipboardList, params: { page: 'schedules' } },
            );
        }
        if (normalizedRole.value === 'SCHOOL_MANAGER' || canManageSchoolExams.value) {
            curriculumLinks.push({
                label: 'الاختبارات',
                routeName: 'school.exams.index',
                icon: ClipboardList,
                params: { section: 'scheduling' },
                children: examSubLinks.value,
            });
        }

        if (curriculumLinks.length > 0) {
            links.push({ type: 'heading', label: 'إدارة المقررات والتوزيع الأكاديمي' }, ...curriculumLinks);
        }

        const studentStructureLinks = [];

        if (normalizedRole.value === 'SCHOOL_MANAGER' || canManageStudentStructure.value) {
            studentStructureLinks.push({ label: 'الفصول التعليمية', routeName: 'school.student_structure.index', icon: GraduationCap });
        }

        if (normalizedRole.value === 'SCHOOL_MANAGER' || canManageStudentAttendance.value) {
            studentStructureLinks.push({ label: 'الحضور اليومي', routeName: 'school.student_attendance.index', icon: ClipboardList });
        }

        if ((normalizedRole.value === 'SCHOOL_MANAGER' && studentLeavesFeatureEnabled.value) || canAccessStudentLeavesModule.value) {
            studentStructureLinks.push({ label: 'إجازات الطلاب', routeName: 'school.student_leaves.index', icon: CalendarDays });
        }

        if (studentStructureLinks.length > 0) {
            links.push({ type: 'heading', label: 'الهيكل الطلابي' }, ...studentStructureLinks);
        }

        if (normalizedRole.value === 'SCHOOL_MANAGER' || canAccessReports.value) {
            links.push({ label: 'التقارير', routeName: 'school.reports.index', icon: FileText });
        }

        if (normalizedRole.value === 'SCHOOL_MANAGER' || canAccessCertificates.value) {
            links.push(
                { type: 'heading', label: 'الشهادات' },
                { label: 'إدارة الشهادات', routeName: 'school.certificates.index', icon: Award },
            );
        }
    }

    return links;
});

const accordionSectionMeta = {
    'الهيكل الأكاديمي': { key: 'academic-structure', icon: GraduationCap },
    'إدارة المقررات والتوزيع الأكاديمي': { key: 'curriculum-distribution', icon: BookOpen },
    'الهيكل الطلابي': { key: 'student-structure', icon: Building2 },
    'الشهادات': { key: 'certificates', icon: Award },
};

const sidebarAccordionThemeByKey = {
    'academic-structure': {
        container: 'border-cyan-400/20 bg-gradient-to-b from-cyan-500/10 via-slate-900/65 to-slate-900/80',
        activeHeader: 'from-cyan-400/25 via-cyan-400/10 to-transparent text-cyan-100 ring-1 ring-cyan-300/35',
        idleHeader: 'text-slate-200 hover:from-cyan-400/12 hover:via-cyan-400/5 hover:to-transparent',
        icon: 'text-cyan-300',
        chevronOpen: 'text-cyan-200',
    },
    'curriculum-distribution': {
        container: 'border-emerald-400/20 bg-gradient-to-b from-emerald-500/10 via-slate-900/65 to-slate-900/80',
        activeHeader: 'from-emerald-400/25 via-emerald-400/10 to-transparent text-emerald-100 ring-1 ring-emerald-300/35',
        idleHeader: 'text-slate-200 hover:from-emerald-400/12 hover:via-emerald-400/5 hover:to-transparent',
        icon: 'text-emerald-300',
        chevronOpen: 'text-emerald-200',
    },
    'student-structure': {
        container: 'border-violet-400/20 bg-gradient-to-b from-violet-500/10 via-slate-900/65 to-slate-900/80',
        activeHeader: 'from-violet-400/25 via-violet-400/10 to-transparent text-violet-100 ring-1 ring-violet-300/35',
        idleHeader: 'text-slate-200 hover:from-violet-400/12 hover:via-violet-400/5 hover:to-transparent',
        icon: 'text-violet-300',
        chevronOpen: 'text-violet-200',
    },
    certificates: {
        container: 'border-amber-400/20 bg-gradient-to-b from-amber-500/10 via-slate-900/65 to-slate-900/80',
        activeHeader: 'from-amber-400/25 via-amber-400/10 to-transparent text-amber-100 ring-1 ring-amber-300/35',
        idleHeader: 'text-slate-200 hover:from-amber-400/12 hover:via-amber-400/5 hover:to-transparent',
        icon: 'text-amber-300',
        chevronOpen: 'text-amber-200',
    },
    default: {
        container: 'border-slate-700/80 bg-slate-900/70',
        activeHeader: 'from-blue-500/25 via-blue-500/10 to-transparent text-blue-100 ring-1 ring-blue-300/35',
        idleHeader: 'text-slate-200 hover:from-blue-500/12 hover:via-blue-500/5 hover:to-transparent',
        icon: 'text-blue-300',
        chevronOpen: 'text-blue-200',
    },
};

const resolveAccordionTheme = (section) => sidebarAccordionThemeByKey[section.key] || sidebarAccordionThemeByKey.default;

const sidebarAccordionState = ref({});

const sidebarSections = computed(() => {
    const sections = [];
    let currentAccordion = null;

    const pushAccordionSection = () => {
        if (currentAccordion && currentAccordion.items.length > 0) {
            sections.push(currentAccordion);
        }
        currentAccordion = null;
    };

    roleLinks.value.forEach((item, index) => {
        if (item.type === 'heading') {
            pushAccordionSection();
            const meta = accordionSectionMeta[item.label] || { key: `section-${index}`, icon: BookOpen };
            currentAccordion = {
                type: 'accordion',
                key: meta.key,
                icon: meta.icon,
                label: item.label,
                items: [],
            };
            return;
        }

        if (currentAccordion) {
            currentAccordion.items.push(item);
            return;
        }

        sections.push({
            type: 'link',
            key: `link-${item.routeName}-${item.params?.section || item.params?.page || index}`,
            item,
        });
    });

    pushAccordionSection();

    return sections;
});

const sidebarNestedState = ref({});

const hasSidebarItemChildren = (item) => Array.isArray(item?.children) && item.children.length > 0;

const sidebarItemKey = (item, fallbackIndex = 0) =>
    `${item?.routeName || 'item'}-${item?.params?.section || item?.params?.page || item?.label || fallbackIndex}`;

const sidebarItemChildrenId = (item, fallbackIndex = 0) =>
    `sidebar-children-${sidebarItemKey(item, fallbackIndex).replace(/[^a-zA-Z0-9_-]/g, '-')}`;

const isSidebarItemActive = (item) => {
    const currentItemParams = hasSidebarItemChildren(item) ? {} : (item.params || {});
    if (isCurrentRouteWithParams(item.routeName, currentItemParams)) return true;

    if (!hasSidebarItemChildren(item)) return false;
    return item.children.some((child) => isCurrentRouteWithParams(child.routeName, child.params || {}));
};

const syncSidebarNestedState = () => {
    const nextState = {};
    let index = 0;

    sidebarSections.value.forEach((section) => {
        const items = section.type === 'accordion' ? section.items : [section.item];
        items.forEach((item) => {
            if (!hasSidebarItemChildren(item)) {
                index++;
                return;
            }

            const key = sidebarItemKey(item, index);
            if (Object.prototype.hasOwnProperty.call(sidebarNestedState.value, key)) {
                nextState[key] = Boolean(sidebarNestedState.value[key]);
                index++;
                return;
            }

            nextState[key] = isSidebarItemActive(item);
            index++;
        });
    });

    sidebarNestedState.value = nextState;
};

const isSidebarItemChildrenOpen = (item, fallbackIndex = 0) =>
    Boolean(sidebarNestedState.value[sidebarItemKey(item, fallbackIndex)]);

const toggleSidebarItemChildren = (item, fallbackIndex = 0) => {
    const key = sidebarItemKey(item, fallbackIndex);
    sidebarNestedState.value[key] = !isSidebarItemChildrenOpen(item, fallbackIndex);
};

const isSidebarSectionActive = (section) =>
    section.items.some((item) => {
        const currentItemParams = hasSidebarItemChildren(item) ? {} : (item.params || {});
        if (isCurrentRouteWithParams(item.routeName, currentItemParams)) return true;

        if (!hasSidebarItemChildren(item)) return false;
        return item.children.some((child) => isCurrentRouteWithParams(child.routeName, child.params || {}));
    });

const sidebarSectionActiveMap = computed(() => {
    const map = {};

    sidebarSections.value.forEach((section) => {
        if (section.type !== 'accordion') return;
        map[section.key] = isSidebarSectionActive(section);
    });

    return map;
});

const syncSidebarAccordionState = () => {
    const nextState = {};
    const hasActiveSection = Object.values(sidebarSectionActiveMap.value).some(Boolean);

    sidebarSections.value.forEach((section) => {
        if (section.type !== 'accordion') return;

        if (!hasActiveSection) {
            nextState[section.key] = false;
            return;
        }

        if (Object.prototype.hasOwnProperty.call(sidebarAccordionState.value, section.key)) {
            nextState[section.key] = Boolean(sidebarAccordionState.value[section.key]);
            return;
        }

        nextState[section.key] = Boolean(sidebarSectionActiveMap.value[section.key]);
    });

    sidebarAccordionState.value = nextState;
};

watch([sidebarSections, () => page.url], syncSidebarAccordionState, { immediate: true });
watch([sidebarSections, () => page.url], syncSidebarNestedState, { immediate: true });
watch(() => page.url, closeMobileSidebar);
watch(isMobileSidebarOpen, setDashboardScrollLock);

onBeforeUnmount(() => {
    setDashboardScrollLock(false);
});

const isSidebarSectionOpen = (sectionKey) => Boolean(sidebarAccordionState.value[sectionKey]);

const toggleSidebarSection = (sectionKey) => {
    sidebarAccordionState.value[sectionKey] = !isSidebarSectionOpen(sectionKey);
};

const sidebarAccordionContainerClass = (section) => {
    const theme = resolveAccordionTheme(section);
    return `sidebar-accordion-container ${theme.container}`;
};

const sidebarAccordionHeaderClass = (section) =>
    sidebarSectionActiveMap.value[section.key]
        ? `sidebar-accordion-header bg-gradient-to-l ${resolveAccordionTheme(section).activeHeader}`
        : `sidebar-accordion-header bg-gradient-to-l ${resolveAccordionTheme(section).idleHeader}`;

const sidebarAccordionIconClass = (section) => `h-4 w-4 ${resolveAccordionTheme(section).icon}`;

const sidebarAccordionChevronClass = (section) =>
    isSidebarSectionOpen(section.key)
        ? `rotate-180 ${resolveAccordionTheme(section).chevronOpen}`
        : 'text-slate-500';

const sidebarNestedHeadingClass = (section, item, fallbackIndex = 0) => {
    const theme = resolveAccordionTheme(section);
    const isActive = isSidebarItemActive(item) || isSidebarItemChildrenOpen(item, fallbackIndex);

    return isActive
        ? `sidebar-nested-heading bg-gradient-to-l ${theme.activeHeader}`
        : `sidebar-nested-heading bg-gradient-to-l ${theme.idleHeader}`;
};

const sidebarNestedHeadingIconClass = (section, item, fallbackIndex = 0) => {
    const theme = resolveAccordionTheme(section);
    const isActive = isSidebarItemActive(item) || isSidebarItemChildrenOpen(item, fallbackIndex);

    return `h-4 w-4 ${isActive ? theme.icon : 'text-slate-400'}`;
};

const sidebarNestedHeadingChevronClass = (section, item, fallbackIndex = 0) =>
    isSidebarItemChildrenOpen(item, fallbackIndex)
        ? `rotate-180 ${resolveAccordionTheme(section).chevronOpen}`
        : 'text-slate-500';
</script>

<template>
    <div
        dir="rtl"
        class="role-dashboard-shell relative isolate flex min-h-[100svh] flex-col overflow-x-hidden overflow-y-visible bg-gray-950 text-gray-100 md:h-[100dvh] md:overflow-hidden"
        :class="isLightMode ? 'role-layout--light' : 'role-layout--dark'"
    >
        <div v-if="props.animateBackground && !isLightMode" aria-hidden="true" class="ambient-layer pointer-events-none fixed inset-0 z-0 overflow-hidden">
            <div class="ambient-noise" />
            <div class="ambient-glow ambient-glow--a" />
            <div class="ambient-glow ambient-glow--b" />
            <div class="ambient-sweep" />
        </div>

        <div v-if="isMobileSidebarOpen" class="role-mobile-backdrop fixed inset-0 z-[60] md:hidden" @click="closeMobileSidebar" />

        <div class="role-body-grid relative flex min-w-0 flex-col md:min-h-0 md:flex-1 md:grid md:grid-cols-[290px_minmax(0,1fr)] md:overflow-hidden">
            <aside
                class="sidebar-shell role-mobile-drawer fixed inset-y-0 right-0 z-[70] flex w-[min(92vw,22rem)] max-w-[calc(100vw-1rem)] flex-col overflow-hidden p-3 transition-transform duration-300 sm:p-4 md:relative md:inset-auto md:z-auto md:h-full md:min-h-0 md:w-auto md:max-w-full md:translate-x-0"
                :class="isMobileSidebarOpen ? 'role-mobile-drawer--open md:translate-x-0' : 'role-mobile-drawer--closed md:translate-x-0'"
                :role="isMobileSidebarOpen ? 'dialog' : undefined"
                :aria-modal="isMobileSidebarOpen ? 'true' : undefined"
                aria-label="القائمة الجانبية"
                @click="handleSidebarLinkNavigation"
            >
                <div class="mb-3 flex items-center justify-between px-1 md:hidden">
                    <div class="text-right">
                        <p class="text-sm font-black text-white">{{ title }}</p>
                        <p class="text-xs text-slate-400">{{ roleLabel }}</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-white/10 bg-white/5 text-slate-100 transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/70 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900"
                        aria-label="إغلاق القائمة الجانبية"
                        @click="closeMobileSidebar"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="sidebar-brand-card mb-4">
                    <div class="flex items-center gap-3">
                        <div class="sidebar-brand-logo">
                            <img :src="projectLogoUrl" :alt="siteName" class="role-logo-image" />
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-white">{{ siteName }}</p>
                            <p class="truncate text-[11px] text-cyan-100/75">منصة تشغيل موحدة للمدرسة</p>
                        </div>
                    </div>
                </div>

                <div class="sidebar-user-card mb-5">
                    <div class="flex items-center gap-3">
                        <div class="sidebar-user-avatar">
                            <img
                                v-if="userMenuLogoUrl"
                                :src="userMenuLogoUrl"
                                :alt="siteName"
                                class="role-logo-image"
                            />
                            <span v-else class="text-sm font-bold text-white">{{ userInitial }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] font-bold tracking-wide text-cyan-200/90">الحساب النشط</p>
                            <p class="truncate text-sm font-semibold text-slate-100">{{ user?.name }}</p>
                            <p class="truncate text-[11px] text-slate-400">{{ user?.email || 'حساب مفعل' }}</p>
                        </div>
                    </div>
                </div>

                <nav class="sidebar-scroll flex-1 space-y-2 overflow-y-auto">
                    <Link
                        :href="safeRouteHref(dashboardRouteName)"
                        class="sidebar-primary-link flex items-center gap-2 rounded-xl px-3 py-2 transition-all duration-200"
                        :class="navItemClass(dashboardRouteName)"
                    >
                        <LayoutDashboard class="h-4 w-4" />
                        <span class="sidebar-primary-title">لوحة التحكم</span>
                    </Link>

                    <div v-for="section in sidebarSections" :key="section.key">
                        <div v-if="section.type === 'accordion'" :class="sidebarAccordionContainerClass(section)">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-2 rounded-xl px-3 py-2.5 text-right transition-all duration-200"
                                :class="sidebarAccordionHeaderClass(section)"
                                :aria-expanded="isSidebarSectionOpen(section.key)"
                                :aria-controls="`accordion-panel-${section.key}`"
                                @click.stop="toggleSidebarSection(section.key)"
                                >
                                    <span class="flex items-center gap-2">
                                        <component :is="section.icon" :class="sidebarAccordionIconClass(section)" />
                                        <span class="sidebar-primary-title">{{ section.label }}</span>
                                    </span>
                                    <ChevronDown
                                        class="h-4 w-4 transition-transform duration-200"
                                        :class="sidebarAccordionChevronClass(section)"
                                />
                            </button>

                            <div
                                :id="`accordion-panel-${section.key}`"
                                class="grid transition-[grid-template-rows,opacity,transform] duration-200 ease-out"
                                :class="isSidebarSectionOpen(section.key) ? 'mt-1 grid-rows-[1fr] opacity-100 translate-y-0' : 'grid-rows-[0fr] opacity-70 -translate-y-1'"
                            >
                                <div class="overflow-hidden">
                                    <div class="space-y-1 border-t border-slate-700/60 px-2 pb-2 pt-2">
                                        <div
                                            v-for="(item, itemIndex) in section.items"
                                            :key="sidebarItemKey(item, itemIndex)"
                                        >
                                            <div
                                                v-if="hasSidebarItemChildren(item)"
                                                :class="sidebarNestedHeadingClass(section, item, itemIndex)"
                                            >
                                                <Link
                                                    :href="safeRouteHref(item.routeName, item.params || {})"
                                                    class="flex flex-1 items-center gap-2 px-3 py-2 text-sm font-semibold leading-5 transition-all duration-200"
                                                >
                                                    <component :is="item.icon" :class="sidebarNestedHeadingIconClass(section, item, itemIndex)" />
                                                    <span>{{ item.label }}</span>
                                                </Link>
                                                <button
                                                    type="button"
                                                    class="sidebar-toggle-btn sidebar-toggle-btn--soft"
                                                    :aria-expanded="isSidebarItemChildrenOpen(item, itemIndex)"
                                                    :aria-controls="sidebarItemChildrenId(item, itemIndex)"
                                                    @click.stop="toggleSidebarItemChildren(item, itemIndex)"
                                                >
                                                    <ChevronDown
                                                        class="h-3.5 w-3.5 transition-transform duration-200"
                                                        :class="sidebarNestedHeadingChevronClass(section, item, itemIndex)"
                                                    />
                                                </button>
                                            </div>
                                            <div v-else class="flex items-center gap-1">
                                                <Link
                                                    :href="safeRouteHref(item.routeName, item.params || {})"
                                                    class="flex flex-1 items-center gap-2 rounded-xl px-3 py-2 text-sm transition-all duration-200"
                                                    :class="navItemClass(item.routeName, item.params || {})"
                                                >
                                                    <component :is="item.icon" class="h-4 w-4" />
                                                    <span>{{ item.label }}</span>
                                                </Link>
                                            </div>

                                            <div
                                                v-if="hasSidebarItemChildren(item) && isSidebarItemChildrenOpen(item, itemIndex)"
                                                :id="sidebarItemChildrenId(item, itemIndex)"
                                                class="sidebar-nested-children mr-6 mt-1 space-y-1"
                                            >
                                                <Link
                                                    v-for="child in item.children"
                                                    :key="`${child.routeName}-${child.params?.section || child.params?.page || child.label}`"
                                                    :href="safeRouteHref(child.routeName, child.params || {})"
                                                    class="block rounded-lg px-3 py-2 text-xs transition-all duration-200"
                                                    :class="subNavItemClass(child.routeName, child.params || {})"
                                                >
                                                    {{ child.label }}
                                                </Link>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <template v-else>
                            <div class="flex items-center gap-1">
                                <Link
                                    :href="safeRouteHref(section.item.routeName, section.item.params || {})"
                                    class="sidebar-primary-link flex flex-1 items-center gap-2 rounded-xl px-3 py-2 transition-all duration-200"
                                    :class="navItemClass(section.item.routeName, hasSidebarItemChildren(section.item) ? {} : (section.item.params || {}))"
                                >
                                    <component :is="section.item.icon" class="h-4 w-4" />
                                    <span class="sidebar-primary-title">{{ section.item.label }}</span>
                                </Link>
                                <button
                                    v-if="hasSidebarItemChildren(section.item)"
                                    type="button"
                                    class="sidebar-toggle-btn"
                                    :aria-expanded="isSidebarItemChildrenOpen(section.item, 0)"
                                    :aria-controls="sidebarItemChildrenId(section.item, 0)"
                                    @click.stop="toggleSidebarItemChildren(section.item, 0)"
                                >
                                    <ChevronDown
                                        class="h-3.5 w-3.5 transition-transform duration-200"
                                        :class="isSidebarItemChildrenOpen(section.item, 0) ? 'rotate-180 text-cyan-100' : 'text-slate-400'"
                                    />
                                </button>
                            </div>

                            <div
                                v-if="hasSidebarItemChildren(section.item) && isSidebarItemChildrenOpen(section.item, 0)"
                                :id="sidebarItemChildrenId(section.item, 0)"
                                class="mr-6 mt-1 space-y-1"
                            >
                                <Link
                                    v-for="child in section.item.children"
                                    :key="`${child.routeName}-${child.params?.section || child.params?.page || child.label}`"
                                    :href="safeRouteHref(child.routeName, child.params || {})"
                                    class="block rounded-lg px-3 py-2 text-xs transition-all duration-200"
                                    :class="subNavItemClass(child.routeName, child.params || {})"
                                >
                                    {{ child.label }}
                                </Link>
                            </div>
                        </template>
                    </div>

                    <Link
                        :href="profileHref"
                        class="sidebar-primary-link sidebar-nav-link sidebar-nav-link--idle flex items-center gap-2 rounded-xl px-3 py-2 transition-all duration-200"
                        aria-label="الانتقال إلى الملف الشخصي"
                    >
                        <User class="h-4 w-4" />
                        <span class="sidebar-primary-title">الملف الشخصي</span>
                    </Link>

                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="mt-2 flex w-full items-center gap-2 rounded-xl border border-red-400/25 bg-gradient-to-l from-red-500/30 to-red-500/10 px-3 py-2 text-red-100 transition-all duration-200 hover:from-red-500/40 hover:to-red-500/20"
                    >
                        <LogOut class="h-4 w-4" />
                        <span>تسجيل الخروج</span>
                    </Link>
                </nav>
            </aside>

            <div class="role-workspace flex min-w-0 flex-col md:min-h-0 md:flex-1 md:overflow-hidden">
                <header class="role-header-shell sticky top-0 z-40 shrink-0 border-b border-gray-800 bg-gray-900/80 backdrop-blur">
                    <div class="role-content-shell role-header-grid flex min-w-0 w-full items-center justify-between px-3 py-3 sm:px-4 md:px-6 xl:px-8">
                        <div class="role-header-primary flex min-w-0 items-center gap-2.5 sm:gap-3">
                            <button
                                type="button"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-white/10 bg-white/5 text-slate-100 transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/70 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900 md:hidden"
                                aria-label="فتح القائمة الجانبية"
                                :aria-expanded="isMobileSidebarOpen"
                                @click="toggleMobileSidebar"
                            >
                                <Menu class="h-5 w-5" />
                            </button>
                            <img
                                v-if="showHeaderLogo"
                                :src="projectLogoUrl"
                                :alt="siteName"
                                :style="headerLogoStyle"
                                class="role-project-header-logo role-header-project-logo shrink-0 rounded-md object-contain"
                            />
                            <div class="role-header-title min-w-0">
                                <h1 class="truncate text-base font-bold sm:text-lg">{{ title }}</h1>
                                <p class="hidden text-xs text-gray-400 sm:block">مساحة العمل</p>
                            </div>
                        </div>
                        <div class="role-header-actions flex items-center gap-2 sm:gap-3">
                            <ThemeModeSwitch size="sm" />
                            <RealtimeNotifications @pulse-routes-changed="updatePulsingRoutes" />
                            <p class="hidden text-sm font-bold text-white lg:block">{{ user?.name || '-' }}</p>
                            <div class="role-account-avatar flex h-10 w-10 items-center justify-center overflow-hidden rounded-full border border-gray-700 bg-gray-800">
                                <img
                                    v-if="userMenuLogoUrl"
                                    :src="userMenuLogoUrl"
                                    :alt="siteName"
                                    class="role-logo-image"
                                />
                                <span v-else class="text-sm font-bold text-white">{{ userInitial }}</span>
                            </div>
                            <Link
                                :href="profileHref"
                                class="hidden h-10 w-10 items-center justify-center rounded-full border border-gray-700 bg-gray-800 text-gray-200 transition hover:bg-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/70 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900 sm:flex"
                                :title="'إعدادات الملف الشخصي'"
                                aria-label="إعدادات الملف الشخصي"
                            >
                                <Settings class="h-5 w-5" />
                            </Link>
                        </div>
                    </div>
                </header>

                <main class="role-main-shell min-w-0 overflow-visible md:flex-1 md:min-h-0 md:overflow-x-hidden md:overflow-y-auto">
                    <div class="role-main-scroll role-content-shell px-3 py-4 pb-6 sm:px-4 sm:py-6 sm:pb-10 md:px-6 xl:px-8">
                        <AppPageFeedback />
                        <slot />
                    </div>
                </main>
            </div>
        </div>

        <div class="role-footer-band role-footer-shell relative shrink-0 border-t border-white/10">
            <AppDashboardFooter />
        </div>
    </div>
</template>

<style scoped>
.role-dashboard-shell {
    width: 100%;
    max-width: 100%;
    min-height: 0;
}

.role-body-grid,
.role-workspace {
    width: 100%;
    max-width: 100%;
    min-height: 0;
    min-width: 0;
}

.role-mobile-backdrop {
    background: rgba(2, 6, 23, 0.78);
}

.sidebar-shell {
    position: relative;
    border-left: 1px solid rgba(71, 85, 105, 0.35);
    background:
        linear-gradient(180deg, rgba(15, 23, 42, 0.88), rgba(2, 6, 23, 0.92)),
        radial-gradient(130% 60% at 110% -10%, rgba(56, 189, 248, 0.14), transparent 72%);
    backdrop-filter: blur(10px);
    box-shadow:
        inset -1px 0 0 rgba(148, 163, 184, 0.12),
        0 20px 60px rgba(2, 6, 23, 0.38);
}

.sidebar-shell::before {
    content: "";
    position: absolute;
    inset: 1rem 0.85rem auto 0.85rem;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(125, 211, 252, 0.28), transparent);
    pointer-events: none;
}

.sidebar-brand-card,
.sidebar-user-card {
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(100, 116, 139, 0.28);
    border-radius: 1.25rem;
    background:
        linear-gradient(180deg, rgba(15, 23, 42, 0.92), rgba(2, 6, 23, 0.84)),
        radial-gradient(120% 80% at 100% 0%, rgba(56, 189, 248, 0.14), transparent 72%);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.04),
        0 16px 40px rgba(2, 6, 23, 0.26);
}

.sidebar-brand-card::before,
.sidebar-user-card::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(125, 211, 252, 0.12), transparent 55%);
    pointer-events: none;
}

.sidebar-brand-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 1rem;
}

.sidebar-brand-logo,
.sidebar-user-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 1rem;
    border: 1px solid rgba(125, 211, 252, 0.26);
    background: linear-gradient(180deg, rgba(14, 116, 144, 0.42), rgba(8, 47, 73, 0.58));
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.sidebar-brand-logo {
    width: 3rem;
    height: 3rem;
    flex-shrink: 0;
}

.role-logo-image {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 0.25rem;
}

.role-project-header-logo {
    display: block;
    max-width: min(320px, 42vw);
    object-fit: contain;
}

.sidebar-user-card {
    padding: 0.95rem 1rem;
}

.sidebar-user-avatar {
    width: 3rem;
    height: 3rem;
    overflow: hidden;
    flex-shrink: 0;
}

.sidebar-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(56, 189, 248, 0.4) rgba(15, 23, 42, 0.55);
}

.role-content-shell {
    width: 100%;
    max-width: none;
    min-width: 0;
    margin-inline: 0;
}

.role-main-shell {
    min-height: auto;
    min-width: 0;
    scrollbar-gutter: auto;
    overscroll-behavior: auto;
}

.role-main-scroll {
    min-height: auto;
}

.role-header-shell {
    isolation: isolate;
}

.role-main-shell::-webkit-scrollbar {
    width: 10px;
}

.role-main-shell::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.55);
}

.role-main-shell::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, rgba(59, 130, 246, 0.72), rgba(14, 165, 233, 0.5));
    border-radius: 9999px;
    border: 2px solid rgba(15, 23, 42, 0.5);
}

.sidebar-scroll::-webkit-scrollbar {
    width: 7px;
}

.sidebar-scroll::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.55);
    border-radius: 9999px;
}

.sidebar-scroll::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, rgba(56, 189, 248, 0.65), rgba(14, 165, 233, 0.45));
    border-radius: 9999px;
}

.sidebar-nav-link {
    border: 1px solid transparent;
    min-height: 3.1rem;
    font-weight: 700;
    letter-spacing: 0.01em;
    box-shadow: 0 10px 24px rgba(3, 7, 18, 0.24);
}

.sidebar-nav-link--idle {
    border-color: rgba(100, 116, 139, 0.18);
    background:
        linear-gradient(180deg, rgba(15, 23, 42, 0.62), rgba(15, 23, 42, 0.46)),
        radial-gradient(120% 80% at 100% 0%, rgba(148, 163, 184, 0.08), transparent 72%);
    color: rgb(226 232 240);
}

.sidebar-nav-link--idle:hover {
    border-color: rgba(148, 163, 184, 0.35);
    background:
        linear-gradient(180deg, rgba(30, 41, 59, 0.82), rgba(30, 41, 59, 0.66)),
        radial-gradient(120% 80% at 100% 0%, rgba(125, 211, 252, 0.12), transparent 72%);
    color: rgb(248 250 252);
    transform: translateY(-1px);
}

.sidebar-nav-link--active {
    border-color: rgba(125, 211, 252, 0.45);
    background:
        linear-gradient(95deg, rgba(14, 116, 144, 0.45), rgba(14, 165, 233, 0.15)),
        linear-gradient(180deg, rgba(15, 23, 42, 0.8), rgba(15, 23, 42, 0.72));
    color: rgb(240 249 255);
    box-shadow: 0 6px 14px rgba(2, 132, 199, 0.2);
}

.sidebar-primary-link {
    position: relative;
    justify-content: flex-start;
    overflow: hidden;
}

.sidebar-primary-link::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(125, 211, 252, 0.08), transparent 56%);
    opacity: 0;
    transition: opacity 180ms ease;
    pointer-events: none;
}

.sidebar-primary-link:hover::before,
.sidebar-nav-link--active.sidebar-primary-link::before {
    opacity: 1;
}

.sidebar-primary-title {
    font-size: 0.95rem;
    line-height: 1.4;
    font-weight: 700;
    letter-spacing: 0.01em;
}

.sidebar-primary-link > .h-4,
.sidebar-primary-link > .lucide {
    flex-shrink: 0;
}

.sidebar-subnav-link {
    border: 1px solid transparent;
    font-weight: 500;
}

.sidebar-subnav-link--idle {
    color: rgb(148 163 184);
}

.sidebar-subnav-link--idle:hover {
    color: rgb(226 232 240);
    border-color: rgba(100, 116, 139, 0.35);
    background: rgba(15, 23, 42, 0.65);
}

.sidebar-subnav-link--active {
    color: rgb(191 219 254);
    border-color: rgba(125, 211, 252, 0.35);
    background: rgba(14, 116, 144, 0.24);
}

.sidebar-nested-heading {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    border: 1px solid rgba(100, 116, 139, 0.35);
    border-radius: 0.8rem;
    background-color: rgba(2, 6, 23, 0.58);
}

.sidebar-nested-children {
    border-right: 1px dashed rgba(100, 116, 139, 0.4);
    padding-right: 0.25rem;
}

.sidebar-toggle-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.9rem;
    height: 1.9rem;
    border-radius: 0.7rem;
    border: 1px solid rgba(100, 116, 139, 0.35);
    background: rgba(15, 23, 42, 0.72);
    color: rgb(148 163 184);
    transition: all 180ms ease;
}

.sidebar-toggle-btn:hover {
    border-color: rgba(125, 211, 252, 0.45);
    background: rgba(30, 41, 59, 0.88);
    color: rgb(224 242 254);
}

.sidebar-toggle-btn--soft {
    width: 2rem;
    height: 2rem;
    margin-inline-start: 0.25rem;
    border-color: transparent;
    background: transparent;
    box-shadow: none;
}

.sidebar-toggle-btn--soft:hover {
    border-color: transparent;
    background: transparent;
}

.sidebar-accordion-container {
    border-width: 1px;
    border-radius: 0.9rem;
    box-shadow: 0 10px 24px rgba(3, 7, 18, 0.3);
}

.sidebar-accordion-header {
    position: relative;
    min-height: 3.1rem;
    overflow: hidden;
    border: 1px solid rgba(100, 116, 139, 0.18);
    background-color: rgba(2, 6, 23, 0.55);
    color: rgb(226 232 240);
    box-shadow: 0 10px 24px rgba(3, 7, 18, 0.24);
}

.sidebar-accordion-header::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(125, 211, 252, 0.08), transparent 56%);
    opacity: 0;
    transition: opacity 180ms ease;
    pointer-events: none;
}

.sidebar-accordion-header:hover::before,
.sidebar-accordion-container--open .sidebar-accordion-header::before {
    opacity: 1;
}

.sidebar-accordion-header:hover {
    border-color: rgba(148, 163, 184, 0.35);
}

.ambient-layer {
    background:
        radial-gradient(90% 120% at 92% -16%, rgba(0, 210, 255, 0.22), transparent 58%),
        radial-gradient(95% 125% at -8% 112%, rgba(79, 123, 255, 0.22), transparent 62%),
        linear-gradient(160deg, rgba(4, 11, 23, 0.96), rgba(2, 8, 17, 0.92));
}

.ambient-layer::before {
    content: "";
    position: absolute;
    inset: 0;
    background:
        radial-gradient(120% 95% at 100% -35%, rgba(255, 255, 255, 0.08), transparent 65%),
        linear-gradient(130deg, rgba(184, 241, 255, 0.12), rgba(79, 123, 255, 0.03), rgba(0, 210, 255, 0.11));
    opacity: 0.3;
}

.ambient-noise {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(184, 241, 255, 0.08) 1px, transparent 1px),
        linear-gradient(90deg, rgba(184, 241, 255, 0.08) 1px, transparent 1px);
    background-size: 46px 46px;
    -webkit-mask-image: radial-gradient(88% 80% at 50% 42%, black, transparent 96%);
    mask-image: radial-gradient(88% 80% at 50% 42%, black, transparent 96%);
    opacity: 0.13;
    will-change: transform;
    animation: ambient-grid-drift 20s linear infinite;
}

.ambient-glow {
    position: absolute;
    border-radius: 50%;
    filter: blur(18px);
    pointer-events: none;
}

.ambient-glow--a {
    width: min(48vw, 560px);
    height: min(48vw, 560px);
    top: -190px;
    right: -170px;
    background: radial-gradient(circle, rgba(0, 210, 255, 0.3), rgba(0, 210, 255, 0) 72%);
    animation: ambient-float-a 11s ease-in-out infinite alternate;
}

.ambient-glow--b {
    width: min(42vw, 500px);
    height: min(42vw, 500px);
    left: -170px;
    bottom: -210px;
    background: radial-gradient(circle, rgba(79, 123, 255, 0.28), rgba(79, 123, 255, 0) 74%);
    animation: ambient-float-b 13s ease-in-out infinite alternate;
}

.ambient-sweep {
    position: absolute;
    inset: -180% -30% auto;
    height: 240%;
    background: linear-gradient(
        120deg,
        transparent 42%,
        rgba(255, 255, 255, 0.2) 50%,
        transparent 58%
    );
    transform: rotate(8deg);
    mix-blend-mode: screen;
    opacity: 0.45;
    animation: ambient-sweep-pass 9s linear infinite;
}

.role-layout--light {
    background:
        radial-gradient(130% 86% at 100% -8%, rgba(14, 165, 233, 0.14), transparent 54%),
        radial-gradient(125% 82% at 0% 112%, rgba(56, 189, 248, 0.12), transparent 56%),
        linear-gradient(180deg, #f8fbfe 0%, #eff5fb 54%, #e8f0f8 100%);
    color: rgb(15 23 42);
    position: relative;
}

.role-layout--light::before,
.role-layout--light::after {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 0;
}

.role-layout--light::before {
    background:
        radial-gradient(44rem 28rem at 8% -6%, rgba(59, 130, 246, 0.13), transparent 72%),
        radial-gradient(38rem 24rem at 92% 0%, rgba(34, 211, 238, 0.12), transparent 74%),
        radial-gradient(34rem 22rem at 50% 100%, rgba(20, 184, 166, 0.08), transparent 76%);
    opacity: 0.88;
    animation: role-light-ambient-drift 20s ease-in-out infinite alternate;
}

.role-layout--light::after {
    background-image:
        linear-gradient(rgba(14, 116, 144, 0.045) 1px, transparent 1px),
        linear-gradient(90deg, rgba(59, 130, 246, 0.04) 1px, transparent 1px);
    background-size: 88px 88px;
    -webkit-mask-image: radial-gradient(88% 88% at 50% 45%, black, transparent 98%);
    mask-image: radial-gradient(88% 88% at 50% 45%, black, transparent 98%);
    opacity: 0.22;
    animation: role-light-grid-drift 24s linear infinite;
}

.role-layout--light .sidebar-shell {
    border-left-color: rgba(148, 163, 184, 0.34);
    background:
        linear-gradient(180deg, rgba(247, 251, 255, 0.98), rgba(238, 245, 251, 0.98)),
        radial-gradient(130% 60% at 110% -10%, rgba(14, 165, 233, 0.11), transparent 72%),
        radial-gradient(120% 64% at 0% 100%, rgba(20, 184, 166, 0.07), transparent 74%);
    box-shadow:
        inset -1px 0 0 rgba(255, 255, 255, 0.62),
        0 20px 56px rgba(15, 23, 42, 0.06);
}

.role-layout--light .sidebar-brand-card,
.role-layout--light .sidebar-user-card {
    border-color: rgba(186, 199, 214, 0.72);
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.99), rgba(244, 249, 253, 0.98)),
        radial-gradient(130% 60% at 110% -10%, rgba(56, 189, 248, 0.1), transparent 72%),
        radial-gradient(120% 64% at 0% 100%, rgba(20, 184, 166, 0.06), transparent 74%);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.65),
        0 14px 36px rgba(148, 163, 184, 0.12);
}

.role-layout--light .sidebar-brand-logo,
.role-layout--light .sidebar-user-avatar {
    border-color: rgba(56, 189, 248, 0.24);
    background: linear-gradient(180deg, rgba(232, 247, 255, 0.96), rgba(208, 236, 250, 0.9));
}

.role-layout--light .sidebar-scroll {
    scrollbar-color: rgba(37, 99, 235, 0.45) rgba(226, 232, 240, 0.9);
}

.role-layout--light .sidebar-scroll::-webkit-scrollbar-track {
    background: rgba(226, 232, 240, 0.9);
}

.role-layout--light .sidebar-scroll::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, rgba(59, 130, 246, 0.55), rgba(37, 99, 235, 0.35));
}

.role-layout--light .sidebar-nav-link--idle {
    border-color: rgba(148, 163, 184, 0.22);
    background:
        linear-gradient(180deg, rgba(250, 253, 255, 0.98), rgba(241, 247, 252, 0.96)),
        radial-gradient(120% 80% at 100% 0%, rgba(14, 165, 233, 0.06), transparent 72%);
    color: rgb(51 65 85);
}

.role-layout--light .sidebar-nav-link--idle:hover {
    border-color: rgba(56, 189, 248, 0.34);
    background:
        linear-gradient(180deg, rgba(235, 246, 253, 0.98), rgba(226, 240, 249, 0.95)),
        radial-gradient(120% 80% at 100% 0%, rgba(20, 184, 166, 0.06), transparent 72%);
    color: rgb(15 23 42);
}

.role-layout--light .sidebar-nav-link--active {
    border-color: rgba(20, 184, 166, 0.34);
    background:
        linear-gradient(95deg, rgba(45, 212, 191, 0.18), rgba(56, 189, 248, 0.1)),
        linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(242, 249, 253, 0.96));
    color: rgb(15 23 42);
    box-shadow: 0 8px 16px rgba(20, 184, 166, 0.1);
}

.role-layout--light .sidebar-subnav-link--idle {
    color: rgb(51 65 85);
}

.role-layout--light .sidebar-subnav-link--idle:hover {
    color: rgb(30 41 59);
    border-color: rgba(148, 163, 184, 0.45);
    background: rgba(241, 245, 249, 0.92);
}

.role-layout--light .sidebar-subnav-link--active {
    color: rgb(13 148 136);
    border-color: rgba(45, 212, 191, 0.28);
    background: rgba(204, 251, 241, 0.68);
}

.role-layout--light .sidebar-nested-heading {
    border-color: rgba(186, 199, 214, 0.68);
    background: linear-gradient(180deg, rgba(248, 252, 255, 0.98), rgba(239, 246, 251, 0.95));
    color: rgb(15 23 42);
}

.role-layout--light .sidebar-nested-children {
    border-right-color: rgba(148, 163, 184, 0.5);
}

.role-layout--light .sidebar-nested-heading .text-slate-400,
.role-layout--light .sidebar-nested-heading .text-slate-500 {
    color: rgb(100 116 139);
}

.role-layout--light .sidebar-nested-children .sidebar-subnav-link--idle {
    color: rgb(51 65 85);
}

.role-layout--light .sidebar-nested-children .sidebar-subnav-link--active {
    color: rgb(13 148 136);
    border-color: rgba(45, 212, 191, 0.34);
    background: rgba(204, 251, 241, 0.62);
}

.role-layout--light .sidebar-toggle-btn {
    border-color: rgba(186, 199, 214, 0.82);
    background: rgba(255, 255, 255, 0.9);
    color: rgb(71 85 105);
}

.role-layout--light .sidebar-toggle-btn:hover {
    border-color: rgba(45, 212, 191, 0.34);
    background: rgba(236, 253, 245, 0.86);
    color: rgb(15 118 110);
}

.role-layout--light .sidebar-toggle-btn--soft,
.role-layout--light .sidebar-toggle-btn--soft:hover {
    border-color: transparent !important;
    background: transparent !important;
    box-shadow: none !important;
}

.role-layout--light .sidebar-accordion-container {
    border-color: rgba(186, 199, 214, 0.76);
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(241, 247, 252, 0.96)),
        radial-gradient(130% 60% at 110% -10%, rgba(14, 165, 233, 0.08), transparent 72%);
    box-shadow: 0 10px 24px rgba(148, 163, 184, 0.1);
}

.role-layout--light .sidebar-accordion-header {
    border-color: rgba(186, 199, 214, 0.56);
    background: linear-gradient(180deg, rgba(249, 252, 255, 0.98), rgba(241, 246, 251, 0.95));
    color: rgb(15 23 42);
    box-shadow: 0 10px 24px rgba(148, 163, 184, 0.08);
}

.role-layout--light .sidebar-accordion-header:hover {
    border-color: rgba(56, 189, 248, 0.34);
}

.role-layout--light .ambient-layer,
.role-layout--light .ambient-noise,
.role-layout--light .ambient-glow,
.role-layout--light .ambient-sweep {
    display: none !important;
    opacity: 0 !important;
    visibility: hidden !important;
}

.role-layout--light .role-header-shell {
    border-color: rgba(186, 199, 214, 0.82);
    background:
        linear-gradient(180deg, rgba(251, 254, 255, 0.96), rgba(239, 247, 252, 0.94)),
        radial-gradient(140% 70% at 100% -20%, rgba(56, 189, 248, 0.08), transparent 72%),
        radial-gradient(120% 80% at 0% 120%, rgba(20, 184, 166, 0.05), transparent 74%);
    box-shadow: 0 12px 32px rgba(148, 163, 184, 0.12);
}

.role-layout--light .role-header-shell::after {
    content: "";
    position: absolute;
    inset: auto 1.5rem 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(56, 189, 248, 0.28), rgba(20, 184, 166, 0.22), transparent);
    pointer-events: none;
}

.role-layout--light .role-header-shell .text-white {
    color: rgb(15 23 42) !important;
}

.role-layout--light .role-header-shell .text-gray-400,
.role-layout--light .role-header-shell .text-slate-400 {
    color: rgb(100 116 139) !important;
}

.role-layout--light .role-header-shell :is(button, a, div)[class*='bg-gray-800'],
.role-layout--light .role-header-shell :is(button, a, div)[class*='bg-gray-900'] {
    border-color: rgba(186, 199, 214, 0.82);
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(241, 247, 252, 0.95)) !important;
    color: rgb(30 41 59) !important;
    box-shadow: 0 8px 18px rgba(148, 163, 184, 0.08);
}

.role-layout--light .role-header-shell :is(button, a)[class*='bg-gray-800']:hover,
.role-layout--light .role-header-shell :is(button, a)[class*='bg-gray-900']:hover {
    border-color: rgba(56, 189, 248, 0.36);
    background:
        linear-gradient(180deg, rgba(242, 250, 255, 0.98), rgba(233, 245, 250, 0.96)) !important;
    color: rgb(15 23 42) !important;
}

.role-layout--light .role-main-shell {
    background: linear-gradient(180deg, rgba(248, 252, 255, 0.62), rgba(239, 246, 252, 0.78));
}

.role-layout--light .role-main-scroll {
    background: transparent;
}

.role-layout--light .role-footer-shell {
    border-top-color: rgba(186, 199, 214, 0.72);
    background: linear-gradient(180deg, rgba(248, 252, 255, 0.92), rgba(238, 245, 251, 0.96));
}

@media (max-width: 767px) {
    .role-dashboard-shell,
    .role-body-grid,
    .role-workspace {
        justify-content: flex-start;
        align-content: start;
    }

    .role-header-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        gap: 0.5rem;
        min-height: 4rem;
        padding-block: 0.55rem;
    }

    .role-header-primary {
        min-width: 0;
        overflow: hidden;
    }

    .role-header-title {
        min-width: 0;
        max-width: min(42vw, 13rem);
    }

    .role-header-title h1 {
        font-size: 0.95rem;
        line-height: 1.35;
    }

    .role-header-actions {
        flex: 0 0 auto;
        min-width: 0;
        justify-content: flex-end;
        gap: 0.4rem;
    }

    .role-header-actions .theme-mode-switch {
        flex: 0 0 auto;
        transform: scale(0.92);
        transform-origin: center;
    }

    .role-header-project-logo {
        width: 2rem !important;
        height: 2rem !important;
        max-width: 2rem;
        max-height: 2rem;
    }

    .role-account-avatar {
        width: 2.35rem;
        height: 2.35rem;
        flex: 0 0 2.35rem;
    }

    .role-mobile-drawer {
        position: fixed;
    }

    .ambient-layer {
        display: none !important;
    }

    .role-mobile-backdrop {
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
        top: calc(4.1rem + env(safe-area-inset-top));
    }

    .role-mobile-drawer {
        inset-inline: 0.75rem;
        top: calc(4.35rem + env(safe-area-inset-top));
        bottom: auto;
        width: auto;
        max-width: none;
        max-height: min(76dvh, 42rem);
        padding: 0.9rem;
        background:
            linear-gradient(180deg, rgba(2, 6, 23, 0.985), rgba(2, 6, 23, 0.97)),
            radial-gradient(140% 70% at 110% -10%, rgba(34, 211, 238, 0.09), transparent 74%);
        border-left-color: rgba(100, 116, 139, 0.42);
        border: 1px solid rgba(100, 116, 139, 0.38);
        border-radius: 1.75rem;
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
        box-shadow:
            inset -1px 0 0 rgba(148, 163, 184, 0.16),
            0 24px 56px rgba(2, 6, 23, 0.42);
    }

    .role-mobile-drawer::before {
        inset: 0.85rem 1rem auto;
        background: linear-gradient(90deg, transparent, rgba(125, 211, 252, 0.4), transparent);
    }

    .role-mobile-drawer--open {
        transform: translate3d(0, 0, 0);
        opacity: 1;
    }

    .role-mobile-drawer--closed {
        transform: translate3d(0, -1.25rem, 0);
        opacity: 0;
        pointer-events: none;
    }

    .sidebar-brand-card,
    .sidebar-user-card {
        border-color: rgba(100, 116, 139, 0.36);
        background:
            linear-gradient(180deg, rgba(15, 23, 42, 0.98), rgba(2, 6, 23, 0.94)),
            radial-gradient(120% 80% at 100% 0%, rgba(34, 211, 238, 0.12), transparent 74%);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.05),
            0 12px 28px rgba(2, 6, 23, 0.22);
    }

    .sidebar-scroll {
        padding-inline: 0.15rem;
        padding-bottom: 0.5rem;
        -webkit-overflow-scrolling: touch;
    }

    .sidebar-primary-link,
    .sidebar-accordion-header,
    .sidebar-nav-link,
    .sidebar-subnav-link,
    .sidebar-toggle-btn {
        min-height: 3.2rem;
        touch-action: manipulation;
    }

    .role-header-shell {
        border-bottom-color: rgba(100, 116, 139, 0.28);
        background: rgba(2, 6, 23, 0.94);
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
    }

    .role-header-shell .role-content-shell {
        min-height: 4rem;
    }

    .role-main-shell {
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-y: auto;
    }

    .role-main-scroll {
        min-height: auto;
        padding-bottom: calc(1rem + env(safe-area-inset-bottom));
    }

    .role-footer-shell {
        border-top-color: rgba(100, 116, 139, 0.22);
        background: rgba(2, 6, 23, 0.94);
    }
}

@keyframes ambient-float-a {
    0% {
        transform: translate3d(0, 0, 0) scale(1);
        opacity: 0.7;
    }
    100% {
        transform: translate3d(-5%, 4%, 0) scale(1.08);
        opacity: 1;
    }
}

@keyframes ambient-float-b {
    0% {
        transform: translate3d(0, 0, 0) scale(1);
        opacity: 0.65;
    }
    100% {
        transform: translate3d(6%, -5%, 0) scale(1.06);
        opacity: 0.95;
    }
}

@keyframes ambient-grid-drift {
    0% {
        transform: translate3d(0, 0, 0);
    }
    100% {
        transform: translate3d(46px, 46px, 0);
    }
}

@keyframes ambient-sweep-pass {
    0% {
        transform: translate(-16%, 0) rotate(8deg);
    }
    100% {
        transform: translate(16%, 0) rotate(8deg);
    }
}

@keyframes role-light-ambient-drift {
    0% {
        transform: translate3d(0, 0, 0) scale(1);
    }
    100% {
        transform: translate3d(0, -1.5%, 0) scale(1.03);
    }
}

@keyframes role-light-grid-drift {
    0% {
        transform: translate3d(0, 0, 0);
    }
    100% {
        transform: translate3d(36px, 28px, 0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .sidebar-nav-link,
    .sidebar-subnav-link,
    .sidebar-toggle-btn,
    .sidebar-accordion-header {
        transition: none !important;
        transform: none !important;
    }

    .ambient-noise,
    .ambient-glow,
    .ambient-sweep {
        animation: none !important;
    }

    .role-layout--light::before,
    .role-layout--light::after {
        animation: none !important;
    }
}
</style>

