<script setup>
import { Head, Link } from '@inertiajs/vue3';
import FrontLayout from '@/Layouts/FrontLayout.vue';

defineProps({
    supervisorPlans: {
        type: Array,
        default: () => [],
    },
    managerPlans: {
        type: Array,
        default: () => [],
    },
});

const billingCycleLabel = (cycle) => {
    if (cycle === 'YEARLY') return 'سنوي';
    return 'شهري';
};

const formatPrice = (value) => {
    const numeric = Number(value ?? 0);

    if (Number.isNaN(numeric)) return value ?? '0';

    return Number.isInteger(numeric) ? numeric.toString() : numeric.toFixed(2);
};

const planPriceLine = (plan) => {
    const monthly = plan.monthly_price ?? plan.price ?? 0;
    const yearly = plan.yearly_price ?? 0;

    if (Number(yearly) > 0) {
        return `شهري ${formatPrice(monthly)} ريال / سنوي ${formatPrice(yearly)} ريال`;
    }

    return `${billingCycleLabel(plan.billing_cycle)} - ${formatPrice(plan.price)} ريال`;
};
</script>

<template>
    <Head title="خطط الاشتراك" />

    <FrontLayout>
        <div class="ui-site-container py-6 sm:py-8">
            <div class="ui-page-shell">
                <section class="ui-page-hero text-center">
                    <div class="ui-page-heading mx-auto max-w-3xl text-center">
                        <h1 class="ui-page-title">خطط الاشتراك</h1>
                        <p class="ui-page-copy">
                            اختر الخطة المناسبة ثم أنشئ الحساب وابدأ التهيئة بخطوات واضحة ومتسقة على جميع الأجهزة.
                        </p>
                    </div>
                </section>

                <section class="ui-section">
                    <div class="ui-section-header">
                        <div class="ui-section-heading">
                            <h2 class="ui-section-title">خطط المشرفين</h2>
                            <p class="ui-section-subtitle">خطط مخصصة للإشراف التربوي وربط المدارس وإدارة المتابعة.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <article v-for="plan in supervisorPlans" :key="plan.id" class="ui-card-soft flex h-full flex-col p-5 text-right">
                            <p class="text-lg font-black text-white">{{ plan.name }}</p>
                            <p class="mt-1 text-sm text-slate-400">
                                {{ planPriceLine(plan) }}
                            </p>
                            <p v-if="plan.included_users_count || plan.extra_user_monthly_price" class="mt-2 text-xs text-slate-500">
                                {{ plan.included_users_count ?? 0 }} مستخدمين مضمنين، والمستخدم الإضافي {{ formatPrice(plan.extra_user_monthly_price ?? 0) }} ريال شهريًا
                            </p>
                            <p v-if="plan.description" class="mt-3 flex-1 text-sm leading-7 text-slate-300">{{ plan.description }}</p>
                            <Link class="ui-primary-button mt-5 w-full" :href="route('register.supervisor', { plan_id: plan.id })">
                                التسجيل كمشرف
                            </Link>
                        </article>
                    </div>
                </section>

                <section class="ui-section">
                    <div class="ui-section-header">
                        <div class="ui-section-heading">
                            <h2 class="ui-section-title">خطط مديري المدارس</h2>
                            <p class="ui-section-subtitle">خطط لإدارة المدرسة وبدء التهيئة وربط الهيكل التشغيلي بشكل احترافي.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <article v-for="plan in managerPlans" :key="plan.id" class="ui-card-soft flex h-full flex-col p-5 text-right">
                            <p class="text-lg font-black text-white">{{ plan.name }}</p>
                            <p class="mt-1 text-sm text-slate-400">
                                {{ planPriceLine(plan) }}
                            </p>
                            <p v-if="plan.included_users_count || plan.extra_user_monthly_price" class="mt-2 text-xs text-slate-500">
                                {{ plan.included_users_count ?? 0 }} مستخدمين مضمنين، والمستخدم الإضافي {{ formatPrice(plan.extra_user_monthly_price ?? 0) }} ريال شهريًا
                            </p>
                            <p v-if="plan.description" class="mt-3 flex-1 text-sm leading-7 text-slate-300">{{ plan.description }}</p>
                            <Link class="ui-secondary-button mt-5 w-full" :href="route('register.manager.plan', { plan_id: plan.id })">
                                التسجيل كمدير مدرسة
                            </Link>
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </FrontLayout>
</template>
