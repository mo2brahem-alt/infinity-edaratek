<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CreditCard, Layers3, ShieldCheck } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppFilterBar from '@/Components/AppFilterBar.vue';
import AppInlineAlert from '@/Components/AppInlineAlert.vue';
import AppSearchField from '@/Components/AppSearchField.vue';
import AppStatePanel from '@/Components/AppStatePanel.vue';
import { useActionDialog } from '@/composables/useActionDialog';

const props = defineProps({
    plans: { type: Array, default: () => [] },
    subscriptions: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({ search: '', status: '', role_type: '' }) },
    stats: { type: Object, default: () => ({ total: 0, pending: 0, active: 0, frozen: 0, canceled: 0, expired: 0, deleted: 0 }) },
});

const actionDialog = useActionDialog();
const filterForm = reactive({
    search: props.filters?.search || '',
    status: props.filters?.status || '',
    role_type: props.filters?.role_type || '',
});
const subscriptionsRows = computed(() => props.subscriptions?.data || []);
const paginationLinks = computed(() => props.subscriptions?.links || []);
const actionForm = useForm({ reason: '' });
const planActionForm = useForm({ reason: '' });
const hasActiveFilters = computed(() =>
    String(filterForm.search || '').trim() !== ''
    || String(filterForm.status || '').trim() !== ''
    || String(filterForm.role_type || '').trim() !== '',
);
const activeFilterCount = computed(() => [
    String(filterForm.search || '').trim() !== '',
    String(filterForm.status || '').trim() !== '',
    String(filterForm.role_type || '').trim() !== '',
].filter(Boolean).length);

const statsCards = computed(() => [
    { key: 'total', label: 'إجمالي الاشتراكات', value: props.stats.total, icon: Layers3 },
    { key: 'active', label: 'نشطة', value: props.stats.active, icon: ShieldCheck },
    { key: 'pending', label: 'معلقة', value: props.stats.pending, icon: CreditCard },
    { key: 'frozen', label: 'مجمّدة', value: props.stats.frozen, icon: CreditCard },
    { key: 'canceled', label: 'ملغية', value: props.stats.canceled, icon: Layers3 },
    { key: 'expired', label: 'منتهية', value: props.stats.expired, icon: Layers3 },
    { key: 'deleted', label: 'محذوفة', value: props.stats.deleted, icon: Layers3 },
]);

const statusLabel = (status) => ({
    PENDING: 'معلق',
    ACTIVE: 'نشط',
    FROZEN: 'مجمّد',
    CANCELED: 'ملغي',
    EXPIRED: 'منتهي',
    DELETED: 'محذوف',
}[status] || status || '-');

const statusClass = (status) => ({
    PENDING: 'bg-amber-500/10 text-amber-300 border border-amber-500/30',
    ACTIVE: 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/30',
    FROZEN: 'bg-blue-500/10 text-blue-300 border border-blue-500/30',
    CANCELED: 'bg-rose-500/10 text-rose-300 border border-rose-500/30',
    EXPIRED: 'bg-gray-500/10 text-gray-300 border border-gray-500/30',
    DELETED: 'bg-red-500/10 text-red-300 border border-red-500/30',
}[status] || 'bg-gray-500/10 text-gray-300 border border-gray-500/30');

const roleLabel = (roleType) => {
    if (roleType === 'SUPERVISOR') return 'مشرف';
    if (roleType === 'SCHOOL_MANAGER' || roleType === 'MANAGER') return 'مدير مدرسة';
    return roleType || '-';
};

const billingLabel = (cycle) => cycle === 'MONTHLY' ? 'شهري' : (cycle === 'YEARLY' ? 'سنوي' : (cycle || '-'));
const formatPrice = (value) => {
    const numeric = Number(value ?? 0);

    if (Number.isNaN(numeric)) return value ?? '-';

    return Number.isInteger(numeric) ? numeric.toString() : numeric.toFixed(2);
};
const planPricingSummary = (plan) => {
    const monthly = plan?.monthly_price ?? plan?.price ?? 0;
    const yearly = plan?.yearly_price ?? 0;
    const included = plan?.included_users_count ?? 0;
    const extra = plan?.extra_user_monthly_price ?? 0;

    return `شهري ${formatPrice(monthly)} / سنوي ${formatPrice(yearly)} / ${included} مستخدم / الإضافي ${formatPrice(extra)}`;
};
const subscriptionPricingSummary = (subscription) => {
    const price = subscription?.base_price ?? subscription?.plan?.price ?? 0;
    const cycle = subscription?.billing_cycle ?? subscription?.plan?.billing_cycle;
    const included = subscription?.included_users_count ?? subscription?.plan?.included_users_count ?? 0;

    return `${formatPrice(price)} ريال - ${billingLabel(cycle)} - ${included} مستخدمين`;
};
const effectiveStatus = (subscription) => (subscription?.deleted_at ? 'DELETED' : subscription?.status);
const currentSubscriptionsCount = (plan) => Number(plan?.blocking_subscriptions_count ?? plan?.total_subscriptions_count ?? 0);
const canFreezePlan = (plan) => !!plan?.is_active && currentSubscriptionsCount(plan) === 0;
const canDeletePlan = (plan) => currentSubscriptionsCount(plan) === 0;
const canActivate = (status, deletedAt) => !deletedAt && status !== 'ACTIVE';
const canFreeze = (status, deletedAt) => !deletedAt && (status === 'ACTIVE' || status === 'PENDING');
const canCancel = (status, deletedAt) => !deletedAt && status !== 'CANCELED';
const canDelete = (status, deletedAt) => !deletedAt && ['FROZEN', 'CANCELED', 'EXPIRED'].includes(status);

const formatDate = (value) => {
    if (!value) return '-';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '-' : date.toLocaleString('ar-SA');
};

const toDate = (value) => {
    if (!value) return null;
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
};

const durationDays = (subscription) => {
    const start = toDate(subscription.starts_at);
    const end = toDate(subscription.ends_at);
    if (!start || !end) return '-';
    const diff = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
    return diff > 0 ? `${diff} يوم` : '-';
};

const remainingDays = (subscription) => {
    const end = toDate(subscription.ends_at);
    if (!end) return '-';
    const diff = Math.ceil((end - new Date()) / (1000 * 60 * 60 * 24));
    return diff < 0 ? 'منتهية' : `${diff} يوم`;
};

const latestLifecycleNote = (subscription) => {
    const meta = subscription?.meta || {};
    return meta.deleted_reason || meta.canceled_reason || meta.frozen_reason || meta.activated_reason || '-';
};
const userInitial = (name) => String(name || '?').trim().charAt(0) || '?';

const applyFilters = () => router.get(route('admin.plans.index'), { ...filterForm }, { preserveState: true, preserveScroll: true, replace: true });
const resetFilters = () => { filterForm.search = ''; filterForm.status = ''; filterForm.role_type = ''; applyFilters(); };

const promptReason = (title, message, required = false) => actionDialog.prompt({
    title,
    message,
    confirmText: 'متابعة',
    cancelText: 'إلغاء',
    variant: required ? 'warning' : 'info',
    inputLabel: 'السبب',
    inputPlaceholder: 'اكتب السبب هنا',
    inputMultiline: true,
    inputRequired: required,
});

const submitPlanAction = (method, endpoint, reason = '') => {
    planActionForm.clearErrors();
    planActionForm.reason = reason;
    planActionForm[method](endpoint, { preserveScroll: true, onFinish: () => { planActionForm.reason = ''; } });
};

const submitAction = (method, endpoint, reason = '') => {
    actionForm.clearErrors();
    actionForm.reason = reason;
    actionForm[method](endpoint, { preserveScroll: true, onFinish: () => { actionForm.reason = ''; } });
};

const freezePlan = async (id) => {
    const reason = await promptReason('تجميد الخطة', 'يمكنك إضافة سبب اختياري لتجميد الخطة.');
    if (reason === null) return;
    submitPlanAction('post', route('admin.plans.freeze', id), reason);
};

const activatePlan = async (id) => {
    const reason = await promptReason('إعادة تفعيل الخطة', 'يمكنك إضافة سبب اختياري لإعادة التفعيل.');
    if (reason === null) return;
    submitPlanAction('post', route('admin.plans.activate', id), reason);
};

const deletePlan = async (id) => {
    const confirmed = await actionDialog.confirm({ title: 'حذف الخطة', message: 'سيتم حذف الخطة من النظام وإخفاؤها من مكوّن الباقات. هل تريد المتابعة؟', confirmText: 'نعم، احذف الخطة', cancelText: 'إلغاء', variant: 'danger' });
    if (!confirmed) return;
    const reason = await promptReason('سبب حذف الخطة', 'يمكنك إضافة سبب اختياري يظهر في السجل التشغيلي.');
    if (reason === null) return;
    submitPlanAction('delete', route('admin.plans.destroy', id), reason);
};

const activateSubscription = async (id) => {
    const reason = await promptReason('تفعيل الاشتراك', 'يمكنك إضافة سبب اختياري للتفعيل.');
    if (reason === null) return;
    submitAction('post', route('admin.subscriptions.activate', id), reason);
};

const freezeSubscription = async (id) => {
    const reason = await promptReason('تجميد الاشتراك', 'يمكنك إضافة سبب اختياري للتجميد.');
    if (reason === null) return;
    submitAction('post', route('admin.subscriptions.freeze', id), reason);
};

const cancelSubscription = async (id) => {
    const reason = await promptReason('إلغاء الاشتراك', 'يمكنك إضافة سبب اختياري للإلغاء.');
    if (reason === null) return;
    submitAction('post', route('admin.subscriptions.cancel', id), reason);
};

const deleteSubscription = async (id) => {
    const confirmed = await actionDialog.confirm({ title: 'حذف الاشتراك', message: 'سيتم حذف الاشتراك نهائيًا من النظام. هل تريد المتابعة؟', confirmText: 'نعم، احذف الاشتراك', cancelText: 'إلغاء', variant: 'danger' });
    if (!confirmed) return;
    const reason = await promptReason('سبب الحذف', 'حقل السبب مطلوب لإتمام الحذف النهائي.', true);
    if (reason === null) return;
    submitAction('delete', route('admin.subscriptions.destroy', id), reason);
};
</script>

<template>
    <Head title="إدارة الاشتراكات" />
    <AdminLayout>
        <div class="ui-page-shell">
            <section class="ui-page-hero">
                <div class="ui-page-header">
                    <div class="ui-page-heading text-right">
                        <span class="ui-page-kicker"><CreditCard class="h-4 w-4" /> تشغيـل الباقات والاشتراكات</span>
                        <h1 class="ui-page-title">إدارة الاشتراكات</h1>
                        <p class="ui-page-copy">واجهة تشغيل موحدة للخطط والاشتراكات مع حالات أوضح على الجوال وسجل أسباب أدق.</p>
                    </div>
                </div>
                <div class="ui-stat-grid admin-plans-stat-grid mt-6">
                    <article v-for="item in statsCards" :key="item.key" class="ui-stat-card">
                        <div class="ui-stat-meta">
                            <div class="text-right"><p class="ui-stat-label">{{ item.label }}</p><h2 class="ui-stat-value">{{ item.value }}</h2></div>
                            <div class="ui-stat-icon"><component :is="item.icon" class="h-5 w-5" /></div>
                        </div>
                    </article>
                </div>
            </section>

            <AppFilterBar
                title="فلاتر الاشتراكات"
                description="ابحث بالاسم أو البريد أو الخطة، ثم صفِّ النتائج حسب الحالة أو نوع الدور."
            >
                <template #meta>
                    <span class="ui-chip">الاشتراكات: {{ subscriptionsRows.length }}</span>
                    <span v-if="activeFilterCount > 0" class="ui-chip">فلاتر مفعلة: {{ activeFilterCount }}</span>
                </template>

                <div class="ui-filter-row">
                    <AppSearchField
                        v-model="filterForm.search"
                        class="flex-1"
                        placeholder="بحث بالاسم أو البريد أو الجوال أو الخطة"
                        aria-label="بحث في الاشتراكات"
                        @clear="applyFilters"
                        @keyup.enter="applyFilters"
                    />
                    <select v-model="filterForm.status" class="ui-select md:max-w-[14rem]">
                        <option value="">كل الحالات</option><option value="PENDING">معلق</option><option value="ACTIVE">نشط</option><option value="FROZEN">مجمّد</option><option value="CANCELED">ملغي</option><option value="EXPIRED">منتهي</option><option value="DELETED">محذوف</option>
                    </select>
                    <select v-model="filterForm.role_type" class="ui-select md:max-w-[14rem]">
                        <option value="">كل الأدوار</option><option value="SUPERVISOR">مشرف</option><option value="SCHOOL_MANAGER">مدير مدرسة</option>
                    </select>
                    <button type="button" class="ui-primary-button" @click="applyFilters">تطبيق</button>
                </div>
                <template #footer>
                    <button type="button" class="ui-ghost-button" :disabled="!hasActiveFilters" @click="resetFilters">مسح الفلاتر</button>
                </template>
            </AppFilterBar>

            <section class="ui-section">
                <div class="ui-section-header"><div class="ui-section-heading text-right"><h2 class="ui-section-title">الخطط الحالية</h2><p class="ui-section-subtitle">إنشاء الخطط وتعديلها يتم من مظهر الموقع، بينما هذه الصفحة مخصصة للتشغيل والمتابعة.</p></div></div>
                <AppInlineAlert v-if="planActionForm.errors.plan" variant="danger" class="mb-4" :message="planActionForm.errors.plan" />
                <AppInlineAlert v-if="planActionForm.errors.reason" variant="danger" class="mb-4" :message="planActionForm.errors.reason" />
                <AppStatePanel v-if="plans.length === 0" title="لا توجد خطط حالياً" description="أضف باقة جديدة من مظهر الموقع لتظهر هنا تلقائيًا." />
                <template v-else>
                    <div class="ui-mobile-card-list admin-plans-mobile-grid lg:hidden">
                        <article v-for="plan in plans" :key="`plan-mobile-${plan.id}`" class="ui-mobile-row-card admin-plan-card">
                            <div class="admin-plan-card-head">
                                <div class="admin-subscription-avatar">
                                    <Layers3 class="h-4 w-4" />
                                </div>
                                <div class="min-w-0 space-y-1 text-center">
                                    <h3 class="line-clamp-2 text-sm font-black text-white">{{ plan.name }}</h3>
                                    <p class="text-[0.68rem] text-slate-400">{{ roleLabel(plan.role_type) }}</p>
                                </div>
                                <span class="admin-subscription-status inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="plan.is_active ? 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/30' : 'bg-gray-500/10 text-gray-300 border border-gray-500/30'">
                                    {{ plan.is_active ? 'مفعلة' : 'غير مفعلة' }}
                                </span>
                            </div>

                            <div class="admin-subscription-plan-pill">
                                <CreditCard class="h-4 w-4 shrink-0" />
                                <div class="min-w-0">
                                    <p class="ui-mobile-row-label">التسعير</p>
                                    <p class="line-clamp-3 text-xs font-bold text-white">{{ planPricingSummary(plan) }}</p>
                                    <p class="text-[0.68rem] text-slate-400">{{ billingLabel(plan.billing_cycle) }}</p>
                                </div>
                            </div>

                            <div class="admin-subscription-detail-grid">
                                <div>
                                    <p class="ui-mobile-row-label">الإجمالي</p>
                                    <p class="font-semibold text-slate-100">{{ plan.total_subscriptions_count }}</p>
                                </div>
                                <div>
                                    <p class="ui-mobile-row-label">النشطة</p>
                                    <p class="font-semibold text-slate-100">{{ plan.active_subscriptions_count }}</p>
                                </div>
                                <div>
                                    <p class="ui-mobile-row-label">المؤرشفة</p>
                                    <p class="font-semibold text-slate-100">{{ plan.archived_subscriptions_count ?? 0 }}</p>
                                </div>
                                <div>
                                    <p class="ui-mobile-row-label">المرتبطة</p>
                                    <p class="font-semibold text-slate-100">{{ currentSubscriptionsCount(plan) }}</p>
                                </div>
                            </div>

                            <div class="admin-subscription-actions admin-plan-actions">
                                <button v-if="plan.is_active" type="button" class="ui-secondary-button disabled:opacity-50" :disabled="!canFreezePlan(plan)" @click="freezePlan(plan.id)">تجميد</button>
                                <button v-else type="button" class="ui-primary-button" @click="activatePlan(plan.id)">تفعيل</button>
                                <button type="button" class="ui-action-button ui-action-button--danger disabled:opacity-50" :disabled="!canDeletePlan(plan)" @click="deletePlan(plan.id)">حذف</button>
                            </div>
                        </article>
                    </div>
                    <div class="ui-table-shell hidden lg:block">
                        <div class="ui-table-container">
                            <table class="ui-data-table">
                                <thead class="ui-table-header"><tr><th>الخطة</th><th>الدور</th><th>السعر</th><th>الدورية</th><th>الحالة</th><th>إجمالي الاشتراكات</th><th>النشطة</th><th>المؤرشفة</th><th>الإجراءات</th></tr></thead>
                                <tbody>
                                    <tr v-for="plan in plans" :key="plan.id">
                                        <td class="font-semibold text-white">{{ plan.name }}</td><td>{{ roleLabel(plan.role_type) }}</td><td>{{ planPricingSummary(plan) }}</td><td>{{ billingLabel(plan.billing_cycle) }}</td>
                                        <td><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="plan.is_active ? 'bg-emerald-500/10 text-emerald-300 border border-emerald-500/30' : 'bg-gray-500/10 text-gray-300 border border-gray-500/30'">{{ plan.is_active ? 'مفعلة' : 'غير مفعلة' }}</span></td>
                                        <td>{{ plan.total_subscriptions_count }}</td><td>{{ plan.active_subscriptions_count }}</td><td>{{ plan.archived_subscriptions_count ?? 0 }}</td>
                                        <td><div class="flex flex-wrap justify-end gap-2"><button v-if="plan.is_active" type="button" class="ui-secondary-button disabled:opacity-50" :disabled="!canFreezePlan(plan)" @click="freezePlan(plan.id)">تجميد</button><button v-else type="button" class="ui-primary-button" @click="activatePlan(plan.id)">تفعيل</button><button type="button" class="ui-action-button ui-action-button--danger disabled:opacity-50" :disabled="!canDeletePlan(plan)" @click="deletePlan(plan.id)">حذف</button></div></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </section>

            <section class="ui-section">
                <div class="ui-section-header"><div class="ui-section-heading text-right"><h2 class="ui-section-title">الاشتراكات الحالية</h2><p class="ui-section-subtitle">تم تحويل العرض على الجوال إلى بطاقات مختصرة مع بقاء الجدول الكامل على الشاشات الواسعة.</p></div></div>
                <AppInlineAlert v-if="actionForm.errors.subscription" variant="danger" class="mb-4" :message="actionForm.errors.subscription" />
                <AppInlineAlert v-if="actionForm.errors.reason" variant="danger" class="mb-4" :message="actionForm.errors.reason" />
                <AppStatePanel
                    v-if="subscriptionsRows.length === 0"
                    :variant="hasActiveFilters ? 'no-results' : 'empty'"
                    :title="hasActiveFilters ? 'لا توجد اشتراكات مطابقة' : 'لا توجد اشتراكات حالياً'"
                    :description="hasActiveFilters
                        ? 'جرّب توسيع البحث أو مسح الفلاتر لعرض النتائج المتاحة.'
                        : 'ستظهر هنا الاشتراكات بعد إنشائها أو تفعيلها على المنصة.'"
                />
                <template v-else>
                    <div class="ui-mobile-card-list admin-subscriptions-mobile-grid lg:hidden">
                        <article v-for="sub in subscriptionsRows" :key="`sub-mobile-${sub.id}`" class="ui-mobile-row-card admin-subscription-card">
                            <div class="admin-subscription-card-head">
                                <div class="admin-subscription-avatar">{{ userInitial(sub.user?.name) }}</div>
                                <div class="min-w-0 space-y-1 text-center">
                                    <h3 class="line-clamp-2 text-sm font-black text-white">{{ sub.user?.name || '-' }}</h3>
                                    <p class="truncate text-[0.68rem] text-slate-400" dir="ltr">{{ sub.user?.email || '-' }}</p>
                                    <p class="text-[0.68rem] text-slate-500" dir="ltr">{{ sub.user?.mobile || '-' }}</p>
                                </div>
                                <span class="admin-subscription-status inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(effectiveStatus(sub))">
                                    {{ statusLabel(effectiveStatus(sub)) }}
                                </span>
                            </div>

                            <div class="admin-subscription-plan-pill">
                                <CreditCard class="h-4 w-4 shrink-0" />
                                <div class="min-w-0">
                                    <p class="ui-mobile-row-label">الخطة</p>
                                    <p class="line-clamp-2 text-xs font-bold text-white">{{ sub.plan?.name || 'خطة محذوفة' }}</p>
                                    <p class="text-[0.68rem] text-slate-400">{{ roleLabel(sub.plan?.role_type) }}</p>
                                </div>
                            </div>

                            <div class="admin-subscription-detail-grid">
                                <div>
                                    <p class="ui-mobile-row-label">المدة</p>
                                    <p class="font-semibold text-slate-100">{{ durationDays(sub) }}</p>
                                </div>
                                <div>
                                    <p class="ui-mobile-row-label">المتبقي</p>
                                    <p class="font-semibold text-slate-100">{{ remainingDays(sub) }}</p>
                                </div>
                                <div>
                                    <p class="ui-mobile-row-label">البداية</p>
                                    <p>{{ formatDate(sub.starts_at) }}</p>
                                </div>
                                <div>
                                    <p class="ui-mobile-row-label">النهاية</p>
                                    <p>{{ formatDate(sub.ends_at) }}</p>
                                </div>
                            </div>

                            <div v-if="latestLifecycleNote(sub) !== '-'" class="admin-subscription-note">
                                <p class="ui-mobile-row-label">آخر ملاحظة</p>
                                <p class="line-clamp-2">{{ latestLifecycleNote(sub) }}</p>
                            </div>

                            <div class="admin-subscription-actions">
                                <button type="button" class="ui-primary-button disabled:opacity-50" :disabled="!canActivate(sub.status, sub.deleted_at)" @click="activateSubscription(sub.id)">تفعيل</button>
                                <button type="button" class="ui-secondary-button disabled:opacity-50" :disabled="!canFreeze(sub.status, sub.deleted_at)" @click="freezeSubscription(sub.id)">تجميد</button>
                                <button type="button" class="ui-ghost-button disabled:opacity-50" :disabled="!canCancel(sub.status, sub.deleted_at)" @click="cancelSubscription(sub.id)">إلغاء</button>
                                <button type="button" class="ui-action-button ui-action-button--danger disabled:opacity-50" :disabled="!canDelete(sub.status, sub.deleted_at)" @click="deleteSubscription(sub.id)">حذف</button>
                            </div>
                        </article>
                    </div>
                    <div class="ui-table-shell hidden lg:block">
                        <div class="ui-table-container">
                            <table class="ui-data-table">
                                <thead class="ui-table-header"><tr><th>المستخدم</th><th>الخطة</th><th>الحالة</th><th>تاريخ الاشتراك</th><th>البداية</th><th>النهاية</th><th>المدة</th><th>المتبقي</th><th>آخر ملاحظة</th><th>الإجراءات</th></tr></thead>
                                <tbody>
                                    <tr v-for="sub in subscriptionsRows" :key="sub.id">
                                        <td><p class="font-semibold text-white">{{ sub.user?.name || '-' }}</p><p class="text-xs text-slate-400">{{ sub.user?.email || '-' }}</p><p class="text-xs text-slate-500">{{ sub.user?.mobile || '-' }}</p></td>
                                        <td><p class="font-semibold text-white">{{ sub.plan?.name || 'خطة محذوفة' }}</p><p class="text-xs text-slate-400">{{ roleLabel(sub.plan?.role_type) }}</p><p class="text-xs text-slate-500">{{ subscriptionPricingSummary(sub) }}</p></td>
                                        <td><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(effectiveStatus(sub))">{{ statusLabel(effectiveStatus(sub)) }}</span></td>
                                        <td>{{ formatDate(sub.created_at) }}</td><td>{{ formatDate(sub.starts_at) }}</td><td>{{ formatDate(sub.ends_at) }}</td><td>{{ durationDays(sub) }}</td><td>{{ remainingDays(sub) }}</td><td class="max-w-[18rem] leading-7">{{ latestLifecycleNote(sub) }}</td>
                                        <td><div class="flex flex-wrap justify-end gap-2"><button type="button" class="ui-primary-button disabled:opacity-50" :disabled="!canActivate(sub.status, sub.deleted_at)" @click="activateSubscription(sub.id)">تفعيل</button><button type="button" class="ui-secondary-button disabled:opacity-50" :disabled="!canFreeze(sub.status, sub.deleted_at)" @click="freezeSubscription(sub.id)">تجميد</button><button type="button" class="ui-ghost-button disabled:opacity-50" :disabled="!canCancel(sub.status, sub.deleted_at)" @click="cancelSubscription(sub.id)">إلغاء</button><button type="button" class="ui-action-button ui-action-button--danger disabled:opacity-50" :disabled="!canDelete(sub.status, sub.deleted_at)" @click="deleteSubscription(sub.id)">حذف</button></div></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div v-if="paginationLinks.length > 3" class="mt-4 flex flex-wrap items-center justify-center gap-2">
                        <template v-for="(link, index) in paginationLinks" :key="index">
                            <span v-if="!link.url" class="rounded-xl border border-slate-700 bg-slate-900 px-3 py-1 text-xs text-slate-500" v-html="link.label" />
                            <Link v-else :href="link.url" class="rounded-xl border px-3 py-1 text-xs transition" :class="link.active ? 'border-sky-500 bg-sky-600 text-white' : 'border-slate-700 bg-slate-900 text-slate-300 hover:bg-slate-800'" v-html="link.label" />
                        </template>
                    </div>
                </template>
            </section>
        </div>
    </AdminLayout>
</template>

<style scoped>
@media (max-width: 767px) {
    .admin-plans-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .admin-plans-stat-grid .ui-stat-card {
        min-width: 0;
        padding: 0.85rem;
        border-radius: 1rem;
        text-align: center;
    }

    .admin-plans-stat-grid .ui-stat-meta {
        margin-bottom: 0;
        flex-direction: column-reverse;
        align-items: center;
        justify-content: center;
        gap: 0.55rem;
    }

    .admin-plans-stat-grid .ui-stat-meta > div:first-child {
        text-align: center;
    }

    .admin-plans-stat-grid .ui-stat-icon {
        width: 2.35rem;
        height: 2.35rem;
        margin-inline: auto;
        border-radius: 0.85rem;
    }

    .admin-plans-stat-grid .ui-stat-label {
        font-size: 0.75rem;
        line-height: 1.35;
    }

    .admin-plans-stat-grid .ui-stat-value {
        font-size: 1.45rem;
        line-height: 1.15;
    }

    .admin-plans-mobile-grid,
    .admin-subscriptions-mobile-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .admin-plan-card,
    .admin-subscription-card {
        gap: 0.75rem;
        padding: 0.85rem;
        border-radius: 1rem;
        text-align: center;
    }

    .admin-plan-card-head,
    .admin-subscription-card-head {
        display: flex;
        min-width: 0;
        flex-direction: column;
        align-items: center;
        gap: 0.55rem;
    }

    .admin-subscription-avatar {
        display: inline-flex;
        width: 2.35rem;
        height: 2.35rem;
        align-items: center;
        justify-content: center;
        border-radius: 0.85rem;
        border: 1px solid color-mix(in srgb, var(--ui-accent) 32%, var(--ui-border-soft));
        background: linear-gradient(180deg, color-mix(in srgb, var(--ui-accent) 16%, var(--ui-surface-2)), var(--ui-surface-1));
        color: var(--ui-accent-strong);
        font-size: 0.95rem;
        font-weight: 900;
    }

    .admin-subscription-status {
        max-width: 100%;
        justify-content: center;
        white-space: normal;
        text-align: center;
        line-height: 1.35;
    }

    .admin-subscription-plan-pill {
        display: flex;
        min-width: 0;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        border-radius: 0.85rem;
        border: 1px solid var(--ui-border-soft);
        background-color: color-mix(in srgb, var(--ui-surface-2) 86%, transparent);
        padding: 0.65rem;
        color: var(--ui-accent-strong);
        text-align: center;
    }

    .admin-subscription-detail-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.55rem;
        font-size: 0.72rem;
        color: var(--ui-text-secondary);
        text-align: center;
    }

    .admin-subscription-detail-grid .ui-mobile-row-label,
    .admin-subscription-note .ui-mobile-row-label {
        font-size: 0.62rem;
        line-height: 1.35;
    }

    .admin-subscription-note {
        border-radius: 0.85rem;
        border: 1px solid var(--ui-border-soft);
        background-color: color-mix(in srgb, var(--ui-surface-2) 72%, transparent);
        padding: 0.6rem;
        font-size: 0.72rem;
        line-height: 1.6;
        color: var(--ui-text-secondary);
        text-align: center;
    }

    .admin-subscription-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.45rem;
        width: 100%;
    }

    .admin-subscription-actions > button {
        min-height: 2.15rem;
        width: 100%;
        justify-content: center;
        border-radius: 0.75rem;
        padding: 0.45rem 0.35rem;
        font-size: 0.68rem;
        line-height: 1.35;
        text-align: center;
    }

    .admin-plan-actions {
        align-items: stretch;
    }
}

@media (max-width: 359px) {
    .admin-plans-stat-grid,
    .admin-plans-mobile-grid,
    .admin-subscriptions-mobile-grid {
        grid-template-columns: 1fr;
    }
}
</style>
