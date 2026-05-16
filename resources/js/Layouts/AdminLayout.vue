<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import RealtimeNotifications from '@/Components/Notifications/RealtimeNotifications.vue';
import AppDashboardFooter from '@/Components/Layout/AppDashboardFooter.vue';
import AppPageFeedback from '@/Components/AppPageFeedback.vue';
import ThemeModeSwitch from '@/Components/Layout/ThemeModeSwitch.vue';
import { useThemeMode } from '@/composables/useThemeMode';
import { useProjectBranding } from '@/composables/useProjectBranding';
import {
    Users,
    School,
    DollarSign,
    LogOut,
    LayoutDashboard,
    User,
    ChevronDown,
    Palette,
    ShieldCheck,
    Building2,
    UserPlus,
    Menu,
    X,
} from 'lucide-vue-next';

const page = usePage();
const user = computed(() => page.props?.auth?.user || {});
const isDropdownOpen = ref(false);
const pulsingRouteNames = ref([]);
const { isLightMode } = useThemeMode();
const { siteName, projectLogoUrl, showHeaderLogo, headerLogoStyle } = useProjectBranding();
const isMobileSidebarOpen = ref(false);

const setDashboardScrollLock = (shouldLock) => {
    if (typeof document === 'undefined') return;

    document.documentElement.style.overflow = shouldLock ? 'hidden' : '';
    document.body.style.overflow = shouldLock ? 'hidden' : '';
};

const isRouteMatching = (patterns) => {
    const routePatterns = Array.isArray(patterns) ? patterns : [patterns];
    return routePatterns.some((pattern) => route().current(pattern));
};

const isUsersSectionActive = computed(() =>
    isRouteMatching(['roles.*', 'departments.*', 'users.*']),
);

const isSettingsSectionActive = computed(() =>
    isRouteMatching(['admin.schools.*', 'admin.school_defaults.*', 'roles.*', 'departments.*', 'users.*', 'admin.plans.*']),
);

const isUsersMenuOpen = ref(isUsersSectionActive.value);
const isSettingsMenuOpen = ref(isSettingsSectionActive.value);

const profilePhotoUrl = computed(() => {
    const path = String(user.value?.profile_photo_path || '').trim();
    if (path === '') return null;

    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) {
        return path;
    }

    return `/media-files/${path}`;
});

const userInitial = computed(() => {
    const name = String(user.value?.name || '').trim();
    return name !== '' ? name.charAt(0).toUpperCase() : 'A';
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

const adminNavItemClass = (patterns, pulseRouteName = null) => {
    const isActive = isRouteMatching(patterns);
    let classes = isActive
        ? 'admin-sidebar-link admin-sidebar-link--active'
        : 'admin-sidebar-link admin-sidebar-link--idle';

    if (!isActive && pulseRouteName && isPulsingRoute(pulseRouteName)) {
        classes += ' ring-2 ring-amber-400/70 animate-pulse';
    }

    return classes;
};

const adminSubLinkClass = (pattern, tone = 'slate') =>
    route().current(pattern)
        ? `admin-sidebar-submenu-link admin-sidebar-submenu-link--active admin-sidebar-submenu-link--${tone}`
        : 'admin-sidebar-submenu-link admin-sidebar-submenu-link--idle';

const adminSubgroupToggleClass = computed(() =>
    isUsersSectionActive.value
        ? 'admin-sidebar-subgroup-toggle admin-sidebar-subgroup-toggle--active'
        : 'admin-sidebar-subgroup-toggle admin-sidebar-subgroup-toggle--idle',
);

watch([isSettingsSectionActive, isUsersSectionActive], ([settingsActive, usersActive]) => {
    if (settingsActive) {
        isSettingsMenuOpen.value = true;
    }

    if (usersActive) {
        isUsersMenuOpen.value = true;
    }
}, { immediate: true });

watch(() => page.url, closeMobileSidebar);
watch(isMobileSidebarOpen, setDashboardScrollLock);

onBeforeUnmount(() => {
    setDashboardScrollLock(false);
});
</script>

<template>
    <div
        dir="rtl"
        class="admin-dashboard-shell relative isolate flex min-h-[100svh] flex-col overflow-x-hidden overflow-y-visible bg-gray-900 font-cairo text-gray-100 md:h-[100dvh] md:overflow-hidden"
        :class="isLightMode ? 'admin-layout--light' : 'admin-layout--dark'"
    >
        <div v-if="!isLightMode" aria-hidden="true" class="admin-ambient-layer pointer-events-none fixed inset-0 z-0 overflow-hidden">
            <div class="admin-ambient-noise" />
            <div class="admin-ambient-glow admin-ambient-glow--a" />
            <div class="admin-ambient-glow admin-ambient-glow--b" />
            <div class="admin-ambient-sweep" />
        </div>

        <div v-if="isMobileSidebarOpen" class="admin-mobile-backdrop fixed inset-0 z-[60] md:hidden" @click="closeMobileSidebar" />

        <div class="admin-body-grid relative flex min-w-0 flex-col md:min-h-0 md:flex-1 md:grid md:grid-cols-[18rem_minmax(0,1fr)] xl:grid-cols-[19rem_minmax(0,1fr)] md:overflow-hidden">
        <aside
            class="admin-sidebar-shell admin-mobile-drawer fixed inset-y-0 right-0 z-[70] flex w-[min(92vw,22rem)] max-w-[calc(100vw-1rem)] flex-col overflow-hidden p-3 transition-transform duration-300 sm:p-4 md:relative md:inset-auto md:z-auto md:h-full md:min-h-0 md:w-auto md:max-w-full md:translate-x-0"
            :class="isMobileSidebarOpen ? 'admin-mobile-drawer--open md:translate-x-0' : 'admin-mobile-drawer--closed md:translate-x-0'"
            :role="isMobileSidebarOpen ? 'dialog' : undefined"
            :aria-modal="isMobileSidebarOpen ? 'true' : undefined"
            @click="handleSidebarLinkNavigation"
            aria-label="القائمة الجانبية"
        >
            <div class="mb-3 flex items-center justify-between px-1 md:hidden">
                <div class="text-right">
                    <p class="text-sm font-black text-white">لوحة التحكم</p>
                    <p class="text-xs text-slate-400">المسؤول الرئيسي</p>
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

            <div class="admin-sidebar-brand">
                <div class="flex items-center gap-3">
                    <div class="admin-sidebar-logo">
                        <img :src="projectLogoUrl" :alt="siteName" class="admin-logo-image" />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-base font-bold text-white">{{ siteName }}</p>
                        <p class="admin-sidebar-meta text-[11px] text-cyan-100/75">لوحة المسؤول الرئيسي</p>
                    </div>
                </div>
            </div>

            <div class="admin-sidebar-user-card">
                <div class="flex items-center gap-3">
                    <div class="admin-sidebar-avatar">
                        <img
                            v-if="userMenuLogoUrl"
                            :src="userMenuLogoUrl"
                            :alt="siteName"
                            class="admin-logo-image"
                        />
                        <span v-else class="text-sm font-bold text-white">{{ userInitial }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-bold tracking-wide text-cyan-200/90">المسؤول الرئيسي</p>
                        <p class="truncate text-sm font-semibold text-slate-100">{{ user.name }}</p>
                        <p class="truncate text-[11px] text-slate-400">{{ user.email }}</p>
                    </div>
                </div>
            </div>

            <nav class="admin-sidebar-scroll flex-1 space-y-3 overflow-y-auto px-4 py-5">
                <Link
                    :href="route('admin.dashboard')"
                    :class="adminNavItemClass('admin.dashboard', 'admin.dashboard')"
                >
                    <LayoutDashboard class="h-5 w-5" />
                    <span class="admin-sidebar-label">نظرة عامة</span>
                </Link>

                <div class="admin-sidebar-group">
                    <button
                        type="button"
                        class="admin-sidebar-group-toggle focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/70 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900"
                        :class="isSettingsSectionActive ? 'admin-sidebar-group-toggle--active' : 'admin-sidebar-group-toggle--idle'"
                        :aria-expanded="isSettingsMenuOpen"
                        aria-label="فتح أو إغلاق قسم الضبط"
                        @click="isSettingsMenuOpen = !isSettingsMenuOpen"
                    >
                        <span class="flex min-w-0 items-center gap-3">
                            <ShieldCheck class="h-5 w-5" />
                            <span class="admin-sidebar-label">الضبط</span>
                        </span>
                        <ChevronDown class="h-4 w-4 shrink-0 transition-transform duration-200" :class="{ 'rotate-180': isSettingsMenuOpen }" />
                    </button>

                    <div v-show="isSettingsMenuOpen" class="admin-sidebar-submenu">
                        <Link :href="route('admin.school_defaults.index')" :class="adminSubLinkClass('admin.school_defaults.*', 'emerald')">
                            <School class="h-4 w-4" />
                            <span class="admin-sidebar-submenu-label">القوالب الافتراضية</span>
                        </Link>

                        <div class="admin-sidebar-subgroup">
                            <button
                                type="button"
                                :class="[adminSubgroupToggleClass, 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/70 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900']"
                                :aria-expanded="isUsersMenuOpen"
                                aria-label="فتح أو إغلاق قسم إدارة الحسابات"
                                @click="isUsersMenuOpen = !isUsersMenuOpen"
                            >
                                <span class="flex min-w-0 items-center gap-3">
                                    <Users class="h-4 w-4" />
                                    <span class="admin-sidebar-submenu-label">إدارة الحسابات</span>
                                </span>
                                <ChevronDown class="h-4 w-4 shrink-0 transition-transform duration-200" :class="{ 'rotate-180': isUsersMenuOpen }" />
                            </button>

                            <div v-show="isUsersMenuOpen" class="admin-sidebar-subgroup-children">
                                <Link :href="route('users.index')" :class="adminSubLinkClass('users.*', 'emerald')">
                                    <UserPlus class="h-4 w-4" />
                                    <span class="admin-sidebar-submenu-label">المستخدمون</span>
                                </Link>

                                <Link :href="route('roles.index')" :class="adminSubLinkClass('roles.*', 'blue')">
                                    <ShieldCheck class="h-4 w-4" />
                                    <span class="admin-sidebar-submenu-label">أدوار المستخدمين</span>
                                </Link>

                                <Link :href="route('departments.index')" :class="adminSubLinkClass('departments.*', 'violet')">
                                    <Building2 class="h-4 w-4" />
                                    <span class="admin-sidebar-submenu-label">الهيكل الإداري</span>
                                </Link>
                            </div>
                        </div>

                        <Link :href="route('admin.plans.index')" :class="adminSubLinkClass('admin.plans.*', 'emerald')">
                            <DollarSign class="h-4 w-4" />
                            <span class="admin-sidebar-submenu-label">إدارة الاشتراكات</span>
                        </Link>
                    </div>
                </div>

                <Link
                    :href="route('admin.settings.index')"
                    :class="adminNavItemClass('admin.settings.*', 'admin.settings.index')"
                >
                    <Palette class="h-5 w-5" />
                    <span class="admin-sidebar-label">مظهر الموقع</span>
                </Link>
            </nav>

            <div class="border-t border-white/10 p-4">
                <Link :href="route('logout')" method="post" as="button" class="admin-sidebar-logout">
                    <LogOut class="h-5 w-5" />
                    <span class="admin-sidebar-label">تسجيل الخروج</span>
                </Link>
            </div>
        </aside>

        <div class="admin-workspace flex min-w-0 flex-col md:min-h-0 md:flex-1 md:overflow-hidden">
            <header class="admin-header-shell sticky top-0 z-40 shrink-0">
                <div class="admin-content-shell admin-header-grid min-h-16 min-w-0 px-3 py-2 sm:min-h-20 sm:px-4 md:px-6 xl:px-8">
                    <div class="admin-header-primary flex min-w-0 items-center gap-2.5 sm:gap-3">
                        <button
                            type="button"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-white/10 bg-white/5 text-slate-100 transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/70 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900 md:hidden"
                            aria-label="فتح القائمة الجانبية"
                            :aria-expanded="isMobileSidebarOpen"
                            @click="toggleMobileSidebar"
                        >
                            <Menu class="h-5 w-5" />
                        </button>
                        <div class="admin-mobile-theme-switch md:hidden">
                            <ThemeModeSwitch size="sm" />
                        </div>
                    </div>

                    <div class="admin-header-logo-slot">
                        <img
                            v-if="showHeaderLogo"
                            :src="projectLogoUrl"
                            :alt="siteName"
                            :style="headerLogoStyle"
                            class="admin-project-header-logo admin-header-project-logo shrink-0 rounded-md object-contain"
                        />
                    </div>

                    <div class="admin-header-actions flex items-center gap-2 sm:gap-3">
                        <div class="admin-desktop-theme-switch hidden md:block">
                            <ThemeModeSwitch size="sm" />
                        </div>
                        <RealtimeNotifications @pulse-routes-changed="updatePulsingRoutes" />

                        <Link
                            :href="route('profile.edit')"
                            class="admin-header-action hidden sm:flex"
                            aria-label="إعدادات الحساب"
                            title="إعدادات الحساب"
                        >
                            <User class="h-5 w-5" />
                        </Link>

                        <div class="relative">
                            <button
                                type="button"
                                class="admin-account-trigger group flex items-center gap-3 rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400/70 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900"
                                aria-label="فتح قائمة الحساب"
                                aria-haspopup="menu"
                                :aria-expanded="isDropdownOpen"
                                @click="isDropdownOpen = !isDropdownOpen"
                            >
                                <div class="hidden text-left sm:block">
                                    <p class="text-sm font-bold text-white transition group-hover:text-cyan-300">{{ user.name }}</p>
                                    <p class="text-xs text-gray-500">{{ user.email }}</p>
                                </div>
                                <div class="admin-sidebar-avatar border-2 border-white/10 group-hover:border-cyan-300/50">
                                    <img v-if="userMenuLogoUrl" :src="userMenuLogoUrl" class="admin-logo-image" :alt="siteName" />
                                    <div v-else class="text-sm font-bold text-white">{{ userInitial }}</div>
                                </div>
                                <ChevronDown class="hidden h-4 w-4 text-gray-500 sm:block" />
                            </button>

                            <div v-if="isDropdownOpen" role="menu" class="admin-account-menu absolute left-0 z-50 mt-2 w-56 rounded-2xl border border-white/10 bg-gray-900/95 py-2 shadow-2xl backdrop-blur-md">
                                <div class="mb-1 border-b border-white/10 px-4 py-3">
                                    <p class="text-xs text-gray-400">مرحبًا بك،</p>
                                    <p class="truncate text-sm font-bold text-white">{{ user.name }}</p>
                                </div>
                                <Link :href="route('profile.edit')" @click="isDropdownOpen = false" class="admin-dropdown-link">
                                    <User class="h-4 w-4" />
                                    <span>إعدادات الحساب</span>
                                </Link>
                                <Link :href="route('logout')" method="post" as="button" class="admin-dropdown-link admin-dropdown-link--danger">
                                    <LogOut class="h-4 w-4" />
                                    <span>تسجيل الخروج</span>
                                </Link>
                            </div>
                            <div v-if="isDropdownOpen" class="fixed inset-0 z-40 cursor-default" @click="isDropdownOpen = false" />
                        </div>
                    </div>
                </div>
            </header>

            <main class="admin-main-shell min-w-0 overflow-visible md:flex-1 md:min-h-0 md:overflow-x-hidden md:overflow-y-auto">
                <div class="admin-main-scroll admin-content-shell px-3 py-4 pb-6 sm:px-4 sm:py-6 sm:pb-10 md:px-6 xl:px-8">
                    <AppPageFeedback />
                    <slot />
                </div>
            </main>
        </div>
        </div>

        <div class="admin-footer-band admin-footer-shell relative shrink-0 border-t border-white/10">
            <AppDashboardFooter role-label="الإدارة" />
        </div>
    </div>
</template>

<style scoped>
.admin-dashboard-shell {
    width: 100%;
    max-width: 100%;
    min-height: 0;
}

.admin-body-grid {
    min-width: 0;
}

.admin-mobile-backdrop {
    background: rgba(2, 6, 23, 0.78);
}

.admin-sidebar-shell {
    position: relative;
    border-left: 1px solid rgba(71, 85, 105, 0.35);
    background:
        linear-gradient(180deg, rgba(15, 23, 42, 0.9), rgba(2, 6, 23, 0.94)),
        radial-gradient(130% 60% at 110% -10%, rgba(56, 189, 248, 0.14), transparent 72%);
    backdrop-filter: blur(12px);
    box-shadow:
        inset -1px 0 0 rgba(148, 163, 184, 0.12),
        0 20px 60px rgba(2, 6, 23, 0.38);
}

.admin-content-shell {
    width: 100%;
    max-width: none;
    min-width: 0;
    margin-inline: 0;
}

.admin-main-shell {
    min-height: auto;
    min-width: 0;
    scrollbar-gutter: auto;
    overscroll-behavior: auto;
}

.admin-workspace {
    width: 100%;
    max-width: 100%;
    min-height: 0;
    min-width: 0;
}

.admin-main-scroll {
    min-height: auto;
}

.admin-main-shell::-webkit-scrollbar {
    width: 10px;
}

.admin-main-shell::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.55);
}

.admin-main-shell::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, rgba(59, 130, 246, 0.72), rgba(14, 165, 233, 0.5));
    border-radius: 9999px;
    border: 2px solid rgba(15, 23, 42, 0.5);
}

.admin-sidebar-shell::before {
    content: "";
    position: absolute;
    inset: 1rem 0.85rem auto 0.85rem;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(125, 211, 252, 0.28), transparent);
    pointer-events: none;
}

.admin-sidebar-brand,
.admin-sidebar-user-card {
    position: relative;
    overflow: hidden;
    margin: 1rem;
    border: 1px solid rgba(100, 116, 139, 0.28);
    border-radius: 1.25rem;
    background:
        linear-gradient(180deg, rgba(15, 23, 42, 0.92), rgba(2, 6, 23, 0.84)),
        radial-gradient(120% 80% at 100% 0%, rgba(56, 189, 248, 0.14), transparent 72%);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.04),
        0 16px 40px rgba(2, 6, 23, 0.26);
}

.admin-sidebar-brand::before,
.admin-sidebar-user-card::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(125, 211, 252, 0.12), transparent 55%);
    pointer-events: none;
}

.admin-sidebar-brand {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    margin-bottom: 0;
}

.admin-sidebar-logo,
.admin-sidebar-avatar {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 1rem;
    border: 1px solid rgba(125, 211, 252, 0.26);
    background: linear-gradient(180deg, rgba(14, 116, 144, 0.42), rgba(8, 47, 73, 0.58));
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.admin-sidebar-logo {
    width: 3rem;
    height: 3rem;
    flex-shrink: 0;
}

.admin-logo-image {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 0.25rem;
}

.admin-project-header-logo {
    display: block;
    max-width: min(320px, 42vw);
    object-fit: contain;
}

.admin-sidebar-user-card {
    padding: 0.95rem 1rem;
}

.admin-sidebar-avatar {
    width: 3rem;
    height: 3rem;
    overflow: hidden;
    flex-shrink: 0;
}

.admin-sidebar-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(56, 189, 248, 0.4) rgba(15, 23, 42, 0.55);
}

.admin-sidebar-scroll::-webkit-scrollbar {
    width: 7px;
}

.admin-sidebar-scroll::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.55);
    border-radius: 9999px;
}

.admin-sidebar-scroll::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, rgba(56, 189, 248, 0.65), rgba(14, 165, 233, 0.45));
    border-radius: 9999px;
}

.admin-sidebar-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    min-height: 3.1rem;
    border: 1px solid transparent;
    border-radius: 1rem;
    padding: 0.85rem 1rem;
    font-weight: 600;
    transition: all 180ms ease;
}

.admin-sidebar-link > .lucide,
.admin-sidebar-group-toggle .lucide,
.admin-sidebar-submenu-link > .lucide,
.admin-sidebar-subgroup-toggle .lucide,
.admin-sidebar-logout > .lucide {
    flex-shrink: 0;
}

.admin-sidebar-label,
.admin-sidebar-submenu-label,
.admin-sidebar-meta {
    display: block;
    min-width: 0;
    white-space: normal;
    overflow-wrap: anywhere;
    line-height: 1.45;
    text-align: right;
}

.admin-sidebar-label {
    flex: 1;
    font-size: 0.95rem;
}

.admin-sidebar-link--idle {
    background: linear-gradient(180deg, rgba(15, 23, 42, 0.55), rgba(15, 23, 42, 0.4));
    color: rgb(203 213 225);
}

.admin-sidebar-link--idle:hover {
    border-color: rgba(148, 163, 184, 0.35);
    background: linear-gradient(180deg, rgba(30, 41, 59, 0.78), rgba(30, 41, 59, 0.62));
    color: rgb(248 250 252);
    transform: translateY(-1px);
}

.admin-sidebar-link--active {
    border-color: rgba(125, 211, 252, 0.45);
    background:
        linear-gradient(95deg, rgba(14, 116, 144, 0.45), rgba(14, 165, 233, 0.15)),
        linear-gradient(180deg, rgba(15, 23, 42, 0.8), rgba(15, 23, 42, 0.72));
    color: rgb(240 249 255);
    box-shadow: 0 6px 14px rgba(2, 132, 199, 0.2);
}

.admin-sidebar-group {
    border: 1px solid rgba(100, 116, 139, 0.3);
    border-radius: 1.1rem;
    background:
        linear-gradient(180deg, rgba(15, 23, 42, 0.72), rgba(2, 6, 23, 0.64)),
        radial-gradient(120% 80% at 100% 0%, rgba(56, 189, 248, 0.08), transparent 72%);
    box-shadow: 0 12px 30px rgba(2, 6, 23, 0.18);
}

.admin-sidebar-group-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    min-height: 3.1rem;
    border-radius: 1rem;
    padding: 0.85rem 1rem;
    font-weight: 600;
    transition: all 180ms ease;
}

.admin-sidebar-group-toggle--idle {
    color: rgb(203 213 225);
}

.admin-sidebar-group-toggle--idle:hover {
    color: rgb(248 250 252);
}

.admin-sidebar-group-toggle--active {
    color: rgb(240 249 255);
    background: linear-gradient(95deg, rgba(14, 116, 144, 0.26), rgba(14, 165, 233, 0.08));
}

.admin-sidebar-submenu {
    display: grid;
    gap: 0.35rem;
    margin: 0 0.85rem 0.85rem;
    padding: 0.55rem 0 0;
    border-top: 1px solid rgba(100, 116, 139, 0.28);
}

.admin-sidebar-submenu-link {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    border: 1px solid transparent;
    border-radius: 0.9rem;
    padding: 0.7rem 0.85rem;
    font-size: 0.9rem;
    font-weight: 600;
    transition: all 180ms ease;
}

.admin-sidebar-subgroup {
    display: grid;
    gap: 0.45rem;
}

.admin-sidebar-subgroup-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    border: 1px solid rgba(100, 116, 139, 0.35);
    border-radius: 0.95rem;
    padding: 0.72rem 0.85rem;
    background: rgba(2, 6, 23, 0.58);
    font-size: 0.9rem;
    font-weight: 600;
    transition: all 180ms ease;
}

.admin-sidebar-subgroup-toggle--idle {
    color: rgb(203 213 225);
}

.admin-sidebar-subgroup-toggle--idle:hover {
    border-color: rgba(125, 211, 252, 0.3);
    background: rgba(15, 23, 42, 0.72);
    color: rgb(248 250 252);
}

.admin-sidebar-subgroup-toggle--active {
    border-color: rgba(125, 211, 252, 0.35);
    background: linear-gradient(95deg, rgba(14, 116, 144, 0.2), rgba(14, 165, 233, 0.08));
    color: rgb(240 249 255);
}

.admin-sidebar-subgroup-children {
    display: grid;
    gap: 0.35rem;
    margin-right: 0.35rem;
    padding-right: 0.55rem;
    border-right: 1px dashed rgba(100, 116, 139, 0.38);
}

.admin-sidebar-submenu-link--idle {
    color: rgb(148 163 184);
}

.admin-sidebar-submenu-link--idle:hover {
    color: rgb(226 232 240);
    border-color: rgba(100, 116, 139, 0.35);
    background: rgba(15, 23, 42, 0.65);
}

.admin-sidebar-submenu-link--active {
    color: rgb(240 249 255);
}

.admin-sidebar-submenu-link--emerald.admin-sidebar-submenu-link--active {
    border-color: rgba(52, 211, 153, 0.35);
    background: rgba(16, 185, 129, 0.14);
}

.admin-sidebar-submenu-link--blue.admin-sidebar-submenu-link--active {
    border-color: rgba(96, 165, 250, 0.35);
    background: rgba(59, 130, 246, 0.14);
}

.admin-sidebar-submenu-link--violet.admin-sidebar-submenu-link--active {
    border-color: rgba(167, 139, 250, 0.35);
    background: rgba(139, 92, 246, 0.14);
}

.admin-sidebar-logout {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    border: 1px solid rgba(248, 113, 113, 0.25);
    border-radius: 1rem;
    padding: 0.85rem 1rem;
    background: linear-gradient(90deg, rgba(127, 29, 29, 0.45), rgba(239, 68, 68, 0.12));
    color: rgb(254 226 226);
    transition: all 180ms ease;
}

.admin-sidebar-logout:hover {
    background: linear-gradient(90deg, rgba(153, 27, 27, 0.52), rgba(239, 68, 68, 0.2));
    transform: translateY(-1px);
}

.admin-header-shell {
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(15, 23, 42, 0.72);
    backdrop-filter: blur(14px);
}

.admin-header-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
    align-items: center;
    gap: 0.75rem;
}

.admin-header-primary {
    min-width: 0;
    justify-self: start;
    overflow: hidden;
}

.admin-header-logo-slot {
    display: flex;
    min-width: 0;
    align-items: center;
    justify-content: center;
    justify-self: center;
    overflow: hidden;
}

.admin-header-actions {
    min-width: 0;
    justify-self: end;
}

.admin-account-menu {
    backdrop-filter: blur(14px);
}

.admin-header-action,
.admin-dropdown-link {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    border: 1px solid rgba(100, 116, 139, 0.3);
    border-radius: 0.95rem;
    background: rgba(15, 23, 42, 0.62);
    color: rgb(226 232 240);
    transition: all 180ms ease;
}

.admin-header-action {
    justify-content: center;
    width: 2.6rem;
    height: 2.6rem;
}

.admin-header-action:hover,
.admin-dropdown-link:hover {
    border-color: rgba(125, 211, 252, 0.35);
    background: rgba(30, 41, 59, 0.86);
    color: rgb(248 250 252);
}

.admin-dropdown-link {
    width: calc(100% - 1rem);
    margin: 0.2rem 0.5rem;
    padding: 0.7rem 0.85rem;
    font-size: 0.9rem;
}

.admin-dropdown-link--danger {
    color: rgb(248 113 113);
}

.admin-dropdown-link--danger:hover {
    border-color: rgba(248, 113, 113, 0.28);
    background: rgba(127, 29, 29, 0.3);
    color: rgb(254 226 226);
}

.admin-ambient-layer {
    background:
        radial-gradient(90% 120% at 92% -16%, rgba(0, 210, 255, 0.22), transparent 58%),
        radial-gradient(95% 125% at -8% 112%, rgba(79, 123, 255, 0.22), transparent 62%),
        linear-gradient(160deg, rgba(4, 11, 23, 0.96), rgba(2, 8, 17, 0.92));
}

.admin-ambient-layer::before {
    content: "";
    position: absolute;
    inset: 0;
    background:
        radial-gradient(120% 95% at 100% -35%, rgba(255, 255, 255, 0.08), transparent 65%),
        linear-gradient(130deg, rgba(184, 241, 255, 0.12), rgba(79, 123, 255, 0.03), rgba(0, 210, 255, 0.11));
    opacity: 0.3;
}

.admin-ambient-noise {
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
    animation: admin-ambient-grid-drift 20s linear infinite;
}

.admin-ambient-glow {
    position: absolute;
    border-radius: 50%;
    filter: blur(18px);
    pointer-events: none;
}

.admin-ambient-glow--a {
    width: min(48vw, 560px);
    height: min(48vw, 560px);
    top: -190px;
    right: -170px;
    background: radial-gradient(circle, rgba(0, 210, 255, 0.3), rgba(0, 210, 255, 0) 72%);
    animation: admin-ambient-float-a 11s ease-in-out infinite alternate;
}

.admin-ambient-glow--b {
    width: min(42vw, 500px);
    height: min(42vw, 500px);
    left: -170px;
    bottom: -210px;
    background: radial-gradient(circle, rgba(79, 123, 255, 0.28), rgba(79, 123, 255, 0) 74%);
    animation: admin-ambient-float-b 13s ease-in-out infinite alternate;
}

.admin-ambient-sweep {
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
    animation: admin-ambient-sweep-pass 9s linear infinite;
}

.admin-layout--light .admin-ambient-layer,
.admin-layout--light .admin-ambient-noise,
.admin-layout--light .admin-ambient-glow,
.admin-layout--light .admin-ambient-sweep {
    display: none !important;
    opacity: 0 !important;
    visibility: hidden !important;
}

.admin-layout--light {
    background:
        radial-gradient(120% 80% at 100% -10%, rgba(59, 130, 246, 0.12), transparent 56%),
        radial-gradient(120% 80% at 0% 110%, rgba(14, 165, 233, 0.11), transparent 58%),
        #eef3f9;
    color: rgb(15 23 42);
    position: relative;
}

.admin-layout--light::before,
.admin-layout--light::after {
    content: "";
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 0;
}

.admin-layout--light::before {
    background:
        radial-gradient(44rem 28rem at 10% -8%, rgba(37, 99, 235, 0.16), transparent 72%),
        radial-gradient(40rem 24rem at 94% 2%, rgba(14, 165, 233, 0.14), transparent 74%),
        radial-gradient(32rem 20rem at 52% 102%, rgba(16, 185, 129, 0.09), transparent 78%);
    opacity: 0.95;
    animation: admin-light-ambient-drift 20s ease-in-out infinite alternate;
}

.admin-layout--light::after {
    background-image:
        linear-gradient(rgba(30, 64, 175, 0.065) 1px, transparent 1px),
        linear-gradient(90deg, rgba(14, 116, 144, 0.055) 1px, transparent 1px);
    background-size: 76px 76px;
    -webkit-mask-image: radial-gradient(88% 88% at 50% 45%, black, transparent 98%);
    mask-image: radial-gradient(88% 88% at 50% 45%, black, transparent 98%);
    opacity: 0.28;
    animation: admin-light-grid-drift 24s linear infinite;
}

.admin-layout--light .admin-sidebar-shell {
    border-left-color: rgba(148, 163, 184, 0.45);
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.96)),
        radial-gradient(130% 60% at 110% -10%, rgba(37, 99, 235, 0.1), transparent 72%);
    box-shadow:
        inset -1px 0 0 rgba(148, 163, 184, 0.3),
        0 20px 56px rgba(15, 23, 42, 0.08);
}

.admin-layout--light .admin-sidebar-brand,
.admin-layout--light .admin-sidebar-user-card {
    border-color: rgba(148, 163, 184, 0.38);
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(248, 250, 252, 0.95)),
        radial-gradient(130% 60% at 110% -10%, rgba(59, 130, 246, 0.1), transparent 72%);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.65),
        0 14px 36px rgba(15, 23, 42, 0.06);
}

.admin-layout--light .admin-sidebar-logo,
.admin-layout--light .admin-sidebar-avatar {
    border-color: rgba(59, 130, 246, 0.28);
    background: linear-gradient(180deg, rgba(219, 234, 254, 0.95), rgba(191, 219, 254, 0.82));
}

.admin-layout--light .admin-sidebar-scroll {
    scrollbar-color: rgba(37, 99, 235, 0.45) rgba(226, 232, 240, 0.9);
}

.admin-layout--light .admin-sidebar-scroll::-webkit-scrollbar-track {
    background: rgba(226, 232, 240, 0.9);
}

.admin-layout--light .admin-sidebar-scroll::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, rgba(59, 130, 246, 0.55), rgba(37, 99, 235, 0.35));
}

.admin-layout--light .admin-sidebar-link--idle {
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.95), rgba(241, 245, 249, 0.92));
    color: rgb(51 65 85);
}

.admin-layout--light .admin-sidebar-link--idle:hover {
    border-color: rgba(148, 163, 184, 0.45);
    background: linear-gradient(180deg, rgba(226, 232, 240, 0.92), rgba(226, 232, 240, 0.82));
    color: rgb(15 23 42);
}

.admin-layout--light .admin-sidebar-link--active {
    border-color: rgba(59, 130, 246, 0.45);
    background:
        linear-gradient(95deg, rgba(59, 130, 246, 0.22), rgba(14, 165, 233, 0.08)),
        linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.92));
    color: rgb(15 23 42);
    box-shadow: 0 8px 16px rgba(59, 130, 246, 0.12);
}

.admin-layout--light .admin-sidebar-group {
    border-color: rgba(148, 163, 184, 0.38);
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.94)),
        radial-gradient(130% 60% at 110% -10%, rgba(59, 130, 246, 0.08), transparent 72%);
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
}

.admin-layout--light .admin-sidebar-group-toggle--idle,
.admin-layout--light .admin-sidebar-submenu-link--idle {
    color: rgb(51 65 85);
}

.admin-layout--light .admin-sidebar-subgroup-toggle--idle {
    color: rgb(51 65 85);
    border-color: rgba(148, 163, 184, 0.38);
    background: rgba(248, 250, 252, 0.94);
}

.admin-layout--light .admin-sidebar-subgroup-toggle--idle:hover {
    border-color: rgba(59, 130, 246, 0.32);
    background: rgba(241, 245, 249, 0.96);
    color: rgb(15 23 42);
}

.admin-layout--light .admin-sidebar-submenu-link--idle:hover,
.admin-layout--light .admin-sidebar-group-toggle--idle:hover {
    background: rgba(241, 245, 249, 0.92);
    color: rgb(15 23 42);
}

.admin-layout--light .admin-sidebar-group-toggle--active {
    color: rgb(15 23 42);
    background: linear-gradient(95deg, rgba(59, 130, 246, 0.14), rgba(14, 165, 233, 0.06));
}

.admin-layout--light .admin-sidebar-subgroup-toggle--active {
    color: rgb(15 23 42);
    border-color: rgba(59, 130, 246, 0.32);
    background: linear-gradient(95deg, rgba(59, 130, 246, 0.14), rgba(14, 165, 233, 0.06));
}

.admin-layout--light .admin-sidebar-submenu {
    border-top-color: rgba(148, 163, 184, 0.35);
}

.admin-layout--light .admin-sidebar-subgroup-children {
    border-right-color: rgba(148, 163, 184, 0.5);
}

.admin-layout--light .admin-sidebar-submenu-link--emerald.admin-sidebar-submenu-link--active {
    border-color: rgba(16, 185, 129, 0.28);
    background: rgba(209, 250, 229, 0.7);
    color: rgb(6 95 70);
}

.admin-layout--light .admin-sidebar-submenu-link--blue.admin-sidebar-submenu-link--active {
    border-color: rgba(59, 130, 246, 0.28);
    background: rgba(219, 234, 254, 0.72);
    color: rgb(30 64 175);
}

.admin-layout--light .admin-sidebar-submenu-link--violet.admin-sidebar-submenu-link--active {
    border-color: rgba(139, 92, 246, 0.28);
    background: rgba(237, 233, 254, 0.72);
    color: rgb(91 33 182);
}

.admin-layout--light .admin-sidebar-logout {
    border-color: rgba(248, 113, 113, 0.22);
    background: linear-gradient(90deg, rgba(254, 226, 226, 0.86), rgba(255, 255, 255, 0.9));
    color: rgb(185 28 28);
}

.admin-layout--light .admin-header-shell {
    border-bottom-color: rgba(148, 163, 184, 0.48);
    background: rgba(255, 255, 255, 0.95);
}

.admin-layout--light .admin-header-shell .text-white {
    color: rgb(15 23 42) !important;
}

.admin-layout--light .admin-header-shell .text-gray-500,
.admin-layout--light .admin-header-shell .text-gray-400 {
    color: rgb(71 85 105) !important;
}

.admin-layout--light .admin-header-action,
.admin-layout--light .admin-dropdown-link {
    border-color: rgba(148, 163, 184, 0.4);
    background: rgba(241, 245, 249, 0.96);
    color: rgb(51 65 85);
}

@media (max-width: 767px) {
    .admin-dashboard-shell,
    .admin-body-grid,
    .admin-workspace {
        justify-content: flex-start;
        align-content: start;
    }

    .admin-header-grid {
        grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
        align-items: center;
        gap: 0.5rem;
        min-height: 4rem;
        padding-block: 0.55rem;
    }

    .admin-header-primary {
        min-width: 0;
        overflow: hidden;
    }

    .admin-header-logo-slot {
        max-width: 30vw;
    }

    .admin-header-actions {
        flex: 0 0 auto;
        min-width: 0;
        justify-content: flex-end;
        gap: 0.4rem;
    }

    .admin-header-actions .theme-mode-switch {
        flex: 0 0 auto;
        transform: scale(0.92);
        transform-origin: center;
    }

    .admin-mobile-theme-switch .theme-mode-switch {
        flex: 0 0 auto;
        transform: scale(0.92);
        transform-origin: center;
    }

    .admin-header-actions .theme-mode-switch > svg,
    .admin-mobile-theme-switch .theme-mode-switch > svg {
        display: none;
    }

    .admin-header-project-logo {
        width: 2rem !important;
        height: 2rem !important;
        max-width: 2rem;
        max-height: 2rem;
    }

    .admin-account-trigger {
        gap: 0.35rem;
    }

    .admin-account-trigger .admin-sidebar-avatar {
        width: 2.35rem;
        height: 2.35rem;
        flex: 0 0 2.35rem;
    }

    .admin-account-trigger {
        flex: 0 0 auto;
    }

    .admin-account-menu {
        left: 0;
        right: auto;
        width: min(14rem, calc(100vw - 1rem));
    }

    .admin-mobile-drawer {
        position: fixed;
    }

    .admin-ambient-layer {
        display: none !important;
    }

    .admin-mobile-backdrop {
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
        top: calc(4.1rem + env(safe-area-inset-top));
    }

    .admin-mobile-drawer {
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

    .admin-mobile-drawer::before {
        inset: 0.85rem 1rem auto;
        background: linear-gradient(90deg, transparent, rgba(125, 211, 252, 0.4), transparent);
    }

    .admin-mobile-drawer--open {
        transform: translate3d(0, 0, 0);
        opacity: 1;
    }

    .admin-mobile-drawer--closed {
        transform: translate3d(0, -1.25rem, 0);
        opacity: 0;
        pointer-events: none;
    }

    .admin-sidebar-brand,
    .admin-sidebar-user-card {
        border-color: rgba(100, 116, 139, 0.36);
        background:
            linear-gradient(180deg, rgba(15, 23, 42, 0.98), rgba(2, 6, 23, 0.94)),
            radial-gradient(120% 80% at 100% 0%, rgba(34, 211, 238, 0.12), transparent 74%);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.05),
            0 12px 28px rgba(2, 6, 23, 0.22);
    }

    .admin-sidebar-scroll {
        padding-inline: 0.15rem;
        padding-bottom: 0.5rem;
        -webkit-overflow-scrolling: touch;
    }

    .admin-sidebar-link,
    .admin-sidebar-group-toggle,
    .admin-sidebar-subgroup-toggle,
    .admin-sidebar-submenu-link,
    .admin-sidebar-logout {
        min-height: 3.2rem;
        touch-action: manipulation;
    }

    .admin-header-shell {
        border-bottom-color: rgba(100, 116, 139, 0.28);
        background: rgba(2, 6, 23, 0.94);
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
    }

    .admin-account-menu {
        background: rgba(2, 6, 23, 0.98);
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
    }

    .admin-main-shell {
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-y: auto;
    }

    .admin-main-scroll {
        min-height: auto;
        padding-bottom: calc(1rem + env(safe-area-inset-bottom));
    }

    .admin-footer-shell {
        border-top-color: rgba(100, 116, 139, 0.22);
        background: rgba(2, 6, 23, 0.94);
    }
}

@media (max-width: 380px) {
    .admin-header-grid {
        gap: 0.35rem;
        padding-inline: 0.65rem;
    }

    .admin-header-actions {
        gap: 0.3rem;
    }

    .admin-header-actions .theme-mode-switch {
        transform: scale(0.86);
    }

    .admin-header-project-logo {
        width: 1.8rem !important;
        height: 1.8rem !important;
    }
}

@keyframes admin-ambient-float-a {
    0% {
        transform: translate3d(0, 0, 0) scale(1);
        opacity: 0.7;
    }
    100% {
        transform: translate3d(-5%, 4%, 0) scale(1.08);
        opacity: 1;
    }
}

@keyframes admin-ambient-float-b {
    0% {
        transform: translate3d(0, 0, 0) scale(1);
        opacity: 0.65;
    }
    100% {
        transform: translate3d(6%, -5%, 0) scale(1.06);
        opacity: 0.95;
    }
}

@keyframes admin-ambient-grid-drift {
    0% {
        transform: translate3d(0, 0, 0);
    }
    100% {
        transform: translate3d(46px, 46px, 0);
    }
}

@keyframes admin-ambient-sweep-pass {
    0% {
        transform: translate(-16%, 0) rotate(8deg);
    }
    100% {
        transform: translate(16%, 0) rotate(8deg);
    }
}

@keyframes admin-light-ambient-drift {
    0% {
        transform: translate3d(0, 0, 0) scale(1);
    }
    100% {
        transform: translate3d(0, -1.5%, 0) scale(1.03);
    }
}

@keyframes admin-light-grid-drift {
    0% {
        transform: translate3d(0, 0, 0);
    }
    100% {
        transform: translate3d(36px, 28px, 0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .admin-sidebar-link,
    .admin-sidebar-group-toggle,
    .admin-sidebar-subgroup-toggle,
    .admin-sidebar-submenu-link,
    .admin-sidebar-logout,
    .admin-header-action,
    .admin-dropdown-link {
        transition: none !important;
        transform: none !important;
    }

    .admin-ambient-noise,
    .admin-ambient-glow,
    .admin-ambient-sweep {
        animation: none !important;
    }

    .admin-layout--light::before,
    .admin-layout--light::after {
        animation: none !important;
    }
}
</style>
