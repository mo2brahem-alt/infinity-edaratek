<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Activity,
    Building2,
    CalendarClock,
    GraduationCap,
    School,
    ShieldCheck,
    UserCheck,
    Users,
} from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppStatePanel from '@/Components/AppStatePanel.vue';

const props = defineProps({
    metrics: {
        type: Object,
        default: () => ({}),
    },
    recentSchools: {
        type: Array,
        default: () => [],
    },
});

const numberFormatter = new Intl.NumberFormat('ar-EG');

const formatNumber = (value) => numberFormatter.format(Number(value || 0));

const primaryStats = computed(() => [
    {
        key: 'total_schools',
        label: 'إجمالي المدارس',
        value: formatNumber(props.metrics.total_schools),
        helper: `${formatNumber(props.metrics.active_schools)} مدرسة مفعلة`,
        icon: School,
    },
    {
        key: 'students',
        label: 'إجمالي الطلاب',
        value: formatNumber(props.metrics.total_students),
        helper: 'إجمالي السجلات الطلابية على مستوى المنصة',
        icon: Users,
    },
    {
        key: 'active_subscriptions',
        label: 'الاشتراكات النشطة',
        value: formatNumber(props.metrics.active_subscriptions),
        helper: `${formatNumber(props.metrics.pending_subscriptions)} طلب بانتظار التفعيل`,
        icon: ShieldCheck,
    },
    {
        key: 'directorates',
        label: 'المحافظات والأنواع',
        value: formatNumber(props.metrics.directorates),
        helper: `${formatNumber(props.metrics.managers)} مدير مدرسة مرتبط`,
        icon: Building2,
    },
]);

const secondarySummaries = computed(() => [
    {
        key: 'linked_schools',
        title: 'المدارس المرتبطة بمديرين',
        value: formatNumber(props.metrics.linked_schools),
        description: 'تشير إلى المدارس التي اكتملت فيها خطوة الربط الأساسية.',
        icon: UserCheck,
    },
    {
        key: 'suspended_schools',
        title: 'مدارس تحتاج متابعة',
        value: formatNumber(props.metrics.suspended_schools),
        description: 'مدارس حالتها ما تزال معلقة أو تحتاج استكمال إجراءات.',
        icon: Activity,
    },
    {
        key: 'pending_subscriptions',
        title: 'طلبات اشتراك قيد المعالجة',
        value: formatNumber(props.metrics.pending_subscriptions),
        description: 'يساعدك هذا المؤشر على متابعة دورة التفعيل قبل التأخير.',
        icon: CalendarClock,
    },
]);

const hasRecentSchools = computed(() => props.recentSchools.length > 0);

const schoolLogoAlt = (school) => `شعار ${school?.name || 'المدرسة'}`;

const schoolStatusMeta = (school) => {
    if (school.status === 'ACTIVE') {
        return {
            label: 'مفعلة',
            className: 'border-emerald-400/20 bg-emerald-400/10 text-emerald-300',
        };
    }

    if (school.supervision_status === 'WAITING_SUPERVISOR_CONFIRM') {
        return {
            label: 'بانتظار تأكيد المشرف',
            className: 'border-amber-400/25 bg-amber-400/10 text-amber-300',
        };
    }

    if (school.supervision_status === 'WAITING_MANAGER_APPROVAL') {
        return {
            label: 'بانتظار موافقة المدير',
            className: 'border-blue-400/25 bg-blue-500/10 text-blue-300',
        };
    }

    return {
        label: 'معلقة',
        className: 'border-slate-500/25 bg-slate-500/10 text-slate-300',
    };
};
</script>

<template>
    <Head title="لوحة المسؤول الرئيسي" />

    <AdminLayout>
        <div class="ui-page-shell">
            <section class="ui-page-hero">
                <div class="ui-page-header">
                    <div class="ui-page-heading text-right">
                        <span class="ui-page-kicker">
                            <ShieldCheck class="h-4 w-4" />
                            <span>مركز المتابعة التنفيذي</span>
                        </span>
                        <h1 class="ui-page-title">لوحة المسؤول الرئيسي</h1>
                        <p class="ui-page-copy">
                            متابعة المدارس، الاشتراكات، وسير التفعيل من شاشة واحدة ببيانات حية بدل الأرقام التجريبية.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2 text-right">
                        <span class="ui-chip">المدارس المفعلة: {{ formatNumber(metrics.active_schools) }}</span>
                        <span class="ui-chip">طلبات قيد المعالجة: {{ formatNumber(metrics.pending_subscriptions) }}</span>
                    </div>
                </div>

                <div class="ui-stat-grid admin-dashboard-stat-grid mt-6">
                    <article v-for="item in primaryStats" :key="item.key" class="ui-stat-card">
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

            <section class="ui-section">
                <div class="ui-section-header">
                    <div class="ui-section-heading text-right">
                        <h2 class="ui-section-title">ملخص تشغيلي سريع</h2>
                        <p class="ui-section-subtitle">مؤشرات تساعدك على معرفة أين تتركز المتابعة اليومية دون ازدحام بصري.</p>
                    </div>
                </div>

                <div class="admin-dashboard-summary-grid grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <article v-for="summary in secondarySummaries" :key="summary.key" class="ui-card-soft flex items-start gap-4 p-5 text-right">
                        <div class="ui-stat-icon shrink-0">
                            <component :is="summary.icon" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0 flex-1 space-y-1">
                            <p class="ui-stat-label">{{ summary.title }}</p>
                            <p class="text-2xl font-black text-white">{{ summary.value }}</p>
                            <p class="text-sm leading-7 text-slate-400">{{ summary.description }}</p>
                        </div>
                    </article>
                </div>
            </section>

            <section class="ui-table-shell">
                <div class="ui-table-header">
                    <div class="ui-section-header !mb-0">
                        <div class="ui-section-heading text-right">
                            <h2 class="ui-section-title">آخر المدارس المسجلة</h2>
                            <p class="ui-section-subtitle">آخر الإدخالات الفعلية مع حالة المدرسة واسم المدير المرتبط بها.</p>
                        </div>

                        <div class="flex items-center justify-end gap-2">
                            <span class="ui-chip">
                                <GraduationCap class="h-3.5 w-3.5" />
                                <span>آخر {{ formatNumber(recentSchools.length) }} عناصر</span>
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="!hasRecentSchools" class="p-4 md:p-6">
                    <AppStatePanel
                        variant="empty"
                        title="لا توجد مدارس مسجلة بعد"
                        description="بمجرد إضافة أول مدرسة أو اعتمادها ستظهر هنا أحدث المدارس مع حالة كل مدرسة ومديرها."
                        compact
                    />
                </div>

                <template v-else>
                    <div class="hidden lg:block ui-table-container">
                        <table class="ui-data-table">
                            <thead>
                                <tr>
                                    <th>اسم المدرسة</th>
                                    <th>المحافظة</th>
                                    <th>المدير</th>
                                    <th>التسجيل</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="school in recentSchools" :key="school.id">
                                    <td>
                                        <div class="space-y-1 text-right">
                                            <p class="font-bold text-white">{{ school.name }}</p>
                                            <p class="text-xs text-slate-400">{{ school.school_id || 'سيُنشأ الرقم تلقائيًا' }}</p>
                                        </div>
                                    </td>
                                    <td>{{ school.directorate_name || 'غير محددة' }}</td>
                                    <td>{{ school.manager_name || 'غير مرتبط بعد' }}</td>
                                    <td>{{ school.created_at || '—' }}</td>
                                    <td>
                                        <span class="ui-chip" :class="schoolStatusMeta(school).className">
                                            {{ schoolStatusMeta(school).label }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="ui-mobile-card-list admin-dashboard-recent-grid">
                        <article v-for="school in recentSchools" :key="school.id" class="ui-mobile-row-card admin-dashboard-recent-card text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="admin-dashboard-school-heading">
                                    <div class="admin-dashboard-school-logo">
                                        <img
                                            v-if="school.logo_url"
                                            :src="school.logo_url"
                                            :alt="schoolLogoAlt(school)"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                        />
                                        <School v-else class="h-5 w-5" aria-hidden="true" />
                                    </div>
                                    <div class="min-w-0 space-y-1 text-center">
                                        <p class="line-clamp-2 text-sm font-black text-white">{{ school.name }}</p>
                                        <p class="text-[0.68rem] text-slate-400">{{ school.school_id || 'سيُنشأ الرقم تلقائيًا' }}</p>
                                    </div>
                                </div>
                                <span class="ui-chip justify-center text-center" :class="schoolStatusMeta(school).className">
                                    {{ schoolStatusMeta(school).label }}
                                </span>
                            </div>

                            <div class="admin-dashboard-recent-details">
                                <div>
                                    <p class="ui-mobile-row-label">المحافظة</p>
                                    <p class="mt-1 line-clamp-2 text-xs text-slate-300">{{ school.directorate_name || 'غير محددة' }}</p>
                                </div>
                                <div>
                                    <p class="ui-mobile-row-label">المدير</p>
                                    <p class="mt-1 line-clamp-2 text-xs text-slate-300">{{ school.manager_name || 'غير مرتبط بعد' }}</p>
                                </div>
                                <div class="col-span-2">
                                    <p class="ui-mobile-row-label">تاريخ التسجيل</p>
                                    <p class="mt-1 text-xs text-slate-300">{{ school.created_at || '—' }}</p>
                                </div>
                            </div>
                        </article>
                    </div>
                </template>
            </section>
        </div>
    </AdminLayout>
</template>

<style scoped>
.admin-dashboard-stat-grid,
.admin-dashboard-summary-grid {
    direction: ltr;
}

.admin-dashboard-stat-grid .ui-stat-card,
.admin-dashboard-summary-grid .ui-card-soft {
    text-align: left;
}

.admin-dashboard-stat-grid .ui-stat-meta {
    direction: ltr;
}

.admin-dashboard-stat-grid .ui-stat-meta > div:first-child,
.admin-dashboard-summary-grid .ui-card-soft > div:last-child {
    text-align: left;
}

.admin-dashboard-summary-grid .ui-card-soft {
    flex-direction: row;
}

.admin-dashboard-stat-grid .ui-stat-card > p,
.admin-dashboard-summary-grid .ui-card-soft p {
    text-align: left;
}

.admin-dashboard-school-heading {
    display: flex;
    min-width: 0;
    align-items: center;
    justify-content: center;
    gap: 0.55rem;
    direction: rtl;
}

.admin-dashboard-school-logo {
    display: inline-flex;
    width: 2.45rem;
    height: 2.45rem;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 0.95rem;
    border: 1px solid color-mix(in srgb, var(--ui-accent) 32%, var(--ui-border-soft));
    background: linear-gradient(180deg, color-mix(in srgb, var(--ui-accent) 16%, var(--ui-surface-2)), var(--ui-surface-1));
    color: var(--ui-accent-strong);
}

.admin-dashboard-recent-details {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.7rem;
    text-align: center;
}

@media (max-width: 767px) {
    .admin-dashboard-stat-grid,
    .admin-dashboard-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .admin-dashboard-stat-grid .ui-stat-card,
    .admin-dashboard-summary-grid .ui-card-soft {
        min-width: 0;
        padding: 0.85rem;
        border-radius: 1rem;
        text-align: center;
    }

    .admin-dashboard-stat-grid .ui-stat-meta {
        margin-bottom: 0.65rem;
        flex-direction: column-reverse;
        align-items: center;
        justify-content: center;
        gap: 0.55rem;
    }

    .admin-dashboard-stat-grid .ui-stat-meta > div:first-child,
    .admin-dashboard-summary-grid .ui-card-soft > div:last-child {
        text-align: center;
    }

    .admin-dashboard-stat-grid .ui-stat-card > p,
    .admin-dashboard-summary-grid .ui-card-soft p {
        text-align: center;
    }

    .admin-dashboard-recent-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .admin-dashboard-recent-card {
        display: flex;
        min-width: 0;
        flex-direction: column;
        justify-content: center;
        gap: 0.85rem;
        padding: 0.85rem;
        border-radius: 1rem;
    }

    .admin-dashboard-recent-card .ui-chip {
        max-width: 100%;
        min-height: 1.8rem;
        white-space: normal;
        line-height: 1.35;
    }

    .admin-dashboard-school-heading {
        width: 100%;
        gap: 0.5rem;
        text-align: center;
    }

    .admin-dashboard-school-logo {
        width: 2.35rem;
        height: 2.35rem;
        border-radius: 0.85rem;
    }

    .admin-dashboard-recent-details {
        gap: 0.65rem;
    }

    .admin-dashboard-recent-details .ui-mobile-row-label {
        font-size: 0.62rem;
        line-height: 1.35;
    }

    .admin-dashboard-stat-grid .ui-stat-icon {
        width: 2.35rem;
        height: 2.35rem;
        border-radius: 0.85rem;
        margin-inline: auto;
    }

    .admin-dashboard-summary-grid .ui-card-soft {
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.65rem;
    }

    .admin-dashboard-summary-grid .ui-stat-icon {
        width: 2.35rem;
        height: 2.35rem;
        border-radius: 0.85rem;
        margin-inline: auto;
    }

    .admin-dashboard-stat-grid .ui-stat-value {
        font-size: 1.45rem;
        line-height: 1.15;
    }

    .admin-dashboard-summary-grid .text-2xl {
        font-size: 1.35rem;
        line-height: 1.15;
    }

    .admin-dashboard-stat-grid .ui-stat-label,
    .admin-dashboard-summary-grid .ui-stat-label {
        font-size: 0.75rem;
        line-height: 1.35;
    }

    .admin-dashboard-stat-grid .ui-stat-card > p,
    .admin-dashboard-summary-grid .ui-card-soft p:last-child {
        font-size: 0.72rem;
        line-height: 1.55;
    }
}
</style>
