<script setup>
import axios from 'axios';
import { Check, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    data: {
        type: Object,
        default: () => ({ plans: [] }),
    },
});

const design = computed(() => props.data?.design || {});
const fallbackPlansByRole = ref({
    SUPERVISOR: [],
    SCHOOL_MANAGER: [],
});
const activeBillingCycle = ref('MONTHLY');
const resolveFileUrl = (path) => {
    if (!path) return null;
    return path.startsWith('http') ? path : `/media-files/${path}`;
};
const backgroundImageUrl = computed(() => resolveFileUrl(design.value.backgroundImage));

const sectionStyle = computed(() => {
    const style = {
        marginTop: `${design.value.marginTop ?? 0}px`,
        marginBottom: `${design.value.marginBottom ?? 0}px`,
        paddingTop: `${design.value.paddingTop ?? 96}px`,
        paddingBottom: `${design.value.paddingBottom ?? 96}px`,
        backgroundColor: design.value.backgroundType === 'color' ? (design.value.backgroundColor || undefined) : undefined,
        backgroundImage: design.value.backgroundType === 'gradient' ? (design.value.backgroundGradient || undefined) : undefined,
    };

    if (design.value.backgroundType === 'image' && backgroundImageUrl.value) {
        const opacity = Number(design.value.backgroundOpacity ?? 100) / 100;
        const overlay = Math.max(0, 1 - opacity);
        style.backgroundImage = `linear-gradient(rgba(15,23,42,${overlay}), rgba(15,23,42,${overlay})), url(${backgroundImageUrl.value})`;
        style.backgroundSize = 'cover';
        style.backgroundPosition = 'center';
    }

    return style;
});

const normalizeRoleType = (roleType) => {
    const normalized = String(roleType || '').trim().toUpperCase();

    if (normalized === 'MANAGER') {
        return 'SCHOOL_MANAGER';
    }

    return normalized;
};

const resolvePlanRoleType = (plan) => normalizeRoleType(plan?.role_type || plan?.roleType);

const normalizeNumber = (value) => {
    const numeric = Number(value ?? 0);

    return Number.isFinite(numeric) ? numeric : 0;
};

const formatPrice = (value) => {
    const numeric = normalizeNumber(value);

    return Number.isInteger(numeric) ? numeric.toString() : numeric.toFixed(2);
};

const resolveCyclePrice = (plan, cycle = activeBillingCycle.value) => {
    if (cycle === 'YEARLY') {
        const yearlyPrice = plan?.yearly_price ?? plan?.yearlyPrice;

        return normalizeNumber(yearlyPrice) > 0 ? yearlyPrice : (plan?.price ?? 0);
    }

    return plan?.monthly_price ?? plan?.monthlyPrice ?? plan?.price ?? 0;
};

const resolveBillingLabel = () => activeBillingCycle.value === 'YEARLY' ? '/ سنوياً' : '/ شهرياً';

const shouldShowBillingToggle = computed(() => (props.data?.plans || []).some((plan) =>
    normalizeNumber(plan?.monthly_price ?? plan?.monthlyPrice ?? plan?.price) > 0
    && normalizeNumber(plan?.yearly_price ?? plan?.yearlyPrice) > 0
));

const includedUsersCount = (plan) => Math.max(0, Number.parseInt(plan?.included_users_count ?? plan?.includedUsersCount ?? 0, 10) || 0);
const extraUserMonthlyPrice = (plan) => formatPrice(plan?.extra_user_monthly_price ?? plan?.extraUserMonthlyPrice ?? 0);

const isPlanDisabled = (plan) => {
    const value = plan?.is_disabled ?? plan?.isDisabled ?? false;
    return value === true || value === 1 || value === '1';
};

const hydrateFallbackPlanIds = (plans = []) => {
    const byRole = {
        SUPERVISOR: [],
        SCHOOL_MANAGER: [],
    };

    for (const plan of plans || []) {
        const roleType = normalizeRoleType(plan?.role_type || plan?.roleType);
        if (roleType in byRole) {
            const planId = String(plan?.id ?? plan?.plan_id ?? plan?.planId ?? '').trim();
            if (planId && planId !== '0') {
                byRole[roleType].push({
                    id: planId,
                    name: String(plan?.name || '').trim().toLowerCase(),
                });
            }
        }
    }

    fallbackPlansByRole.value = byRole;
};

const resolvePlanId = (plan) => {
    const roleType = resolvePlanRoleType(plan);
    const rolePlans = fallbackPlansByRole.value[roleType] || [];

    const explicitId = String(plan?.plan_id ?? plan?.planId ?? plan?.id ?? '').trim();
    if (explicitId && explicitId !== '0') {
        return rolePlans.some((item) => item.id === explicitId) ? explicitId : null;
    }

    if (!rolePlans.length) {
        return null;
    }

    const requestedPlanName = String(plan?.name || '').trim().toLowerCase();
    if (requestedPlanName) {
        const matchedByName = rolePlans.find((item) => item.name === requestedPlanName);
        if (matchedByName?.id) {
            return matchedByName.id;
        }
    }

    return rolePlans[0]?.id || null;
};

const resolvePlanUrl = (plan) => {
    if (plan?.url) return plan.url;
    if (isPlanDisabled(plan)) return '#';

    const roleType = resolvePlanRoleType(plan);
    const planId = resolvePlanId(plan);

    if (!planId) {
        return '#';
    }

    if (roleType === 'SUPERVISOR') {
        return route('register.supervisor', { plan_id: planId, billing_cycle: activeBillingCycle.value });
    }

    if (roleType === 'SCHOOL_MANAGER') {
        return route('register.manager.plan', { plan_id: planId, billing_cycle: activeBillingCycle.value });
    }

    return '#';
};

const resolveCtaText = (plan) => {
    if (isPlanDisabled(plan) || !resolvePlanId(plan)) {
        return 'غير متاحة حالياً';
    }

    return plan.ctaText || 'اشترك الآن';
};

const resolveAlignment = (alignment) => {
    const normalized = String(alignment || '').toLowerCase();

    if (normalized === 'left') return 'left';
    if (normalized === 'center') return 'center';

    return 'right';
};

const resolveTextAlignClass = (alignment) => {
    const normalized = resolveAlignment(alignment);

    if (normalized === 'left') return 'text-left';
    if (normalized === 'center') return 'text-center';

    return 'text-right';
};

const resolveFeatureItemClass = (alignment) => {
    const normalized = resolveAlignment(alignment);

    if (normalized === 'center') return 'justify-center';

    return '';
};

const resolvePlansFlow = () => {
    const flow = String(props.data?.plans_flow || props.data?.plansFlow || '').toLowerCase();

    if (flow === 'left') return 'left';
    if (flow === 'center') return 'center';

    return 'right';
};

const plansContainerClasses = computed(() => {
    const flow = resolvePlansFlow();

    if (flow === 'left') return ['md:flex-row', 'md:justify-start'];
    if (flow === 'center') return ['md:flex-row', 'md:justify-center'];

    return ['md:flex-row-reverse', 'md:justify-start'];
});

onMounted(async () => {
    hydrateFallbackPlanIds(props.data?.plans);

    try {
        const response = await axios.get(route('plans.index'));
        hydrateFallbackPlanIds(response.data);
    } catch (_error) {
        // Keep local fallback only when API is unavailable.
    }
});
</script>

<template>
    <section class="pricing-table-section relative" :style="sectionStyle">
        <div class="mx-auto max-w-7xl px-6">
            <div class="ui-public-header mb-12" :class="design.textAlign || 'text-center'">
                <span class="ui-public-badge mx-auto" :class="design.textAlign === 'text-right' ? 'mr-0 ml-auto' : design.textAlign === 'text-left' ? 'ml-0 mr-auto' : ''">
                    <span>الباقات والاشتراكات</span>
                </span>
                <div>
                    <h2 :style="{ color: data.titleColor || undefined, fontSize: `${design.titleSize ?? 44}px` }" class="ui-public-title">
                        {{ data.title }}
                    </h2>
                    <p :style="{ color: data.subtitleColor || undefined, fontSize: `${design.subtitleSize ?? 20}px` }" class="ui-public-copy mx-auto mt-4 max-w-2xl">
                        {{ data.subtitle }}
                    </p>
                </div>
            </div>

            <div v-if="shouldShowBillingToggle" class="mb-8 flex justify-center">
                <div class="pricing-billing-toggle inline-flex rounded-full border border-white/10 bg-white/5 p-1 shadow-lg shadow-slate-950/20" role="group" aria-label="اختيار دورية الفوترة">
                    <button
                        type="button"
                        class="pricing-billing-button rounded-full px-5 py-2 text-sm font-bold transition"
                        :class="activeBillingCycle === 'MONTHLY' ? 'bg-sky-600 text-white shadow shadow-sky-950/30' : 'text-slate-300 hover:bg-white/10 hover:text-white'"
                        @click="activeBillingCycle = 'MONTHLY'"
                    >
                        شهري
                    </button>
                    <button
                        type="button"
                        class="pricing-billing-button rounded-full px-5 py-2 text-sm font-bold transition"
                        :class="activeBillingCycle === 'YEARLY' ? 'bg-sky-600 text-white shadow shadow-sky-950/30' : 'text-slate-300 hover:bg-white/10 hover:text-white'"
                        @click="activeBillingCycle = 'YEARLY'"
                    >
                        سنوي
                    </button>
                </div>
            </div>

            <div class="pricing-plans-viewport">
                <div class="pricing-plans-track flex w-full max-w-full flex-nowrap items-start gap-4 overflow-x-auto pb-2 snap-x snap-mandatory md:w-auto md:max-w-none md:flex-wrap md:gap-6 md:overflow-visible md:pb-0 md:snap-none" :class="plansContainerClasses">
                    <div
                        v-for="(plan, i) in data.plans"
                        :key="i"
                        class="pricing-plan-card ui-public-card group relative flex w-[82%] min-w-[82%] max-w-[22rem] shrink-0 snap-center flex-col p-7 transition-all duration-500 hover:-translate-y-1.5 md:w-[calc(33.333%-1rem)] md:min-w-0 md:max-w-none md:p-8"
                        :class="[plan.isFeatured ? 'pricing-plan-card--featured ring-1 ring-sky-500/45 shadow-[0_22px_50px_rgba(14,165,233,0.18)]' : 'hover:border-slate-500/40', data.cardBgColor]"
                    >
                        <div v-if="plan.isFeatured" class="pricing-plan-featured-badge absolute left-1/2 z-10 -translate-x-1/2 transform">
                            <span class="ui-public-badge whitespace-nowrap bg-sky-600 text-white border-transparent shadow-lg">الأكثر طلبًا</span>
                        </div>

                        <h3
                            class="pricing-plan-title mb-4 text-2xl font-bold text-white"
                            :class="resolveTextAlignClass(plan.title_alignment || plan.titleAlignment)"
                        >
                            {{ plan.name }}
                        </h3>

                        <div class="mb-8" :class="resolveTextAlignClass(plan.price_alignment || plan.priceAlignment)">
                            <div class="inline-flex items-baseline gap-1">
                                <span class="pricing-plan-price text-5xl font-black tracking-tight" :style="{ color: data.priceColor || '#ffffff' }">{{ formatPrice(resolveCyclePrice(plan)) }}</span>
                                <span class="pricing-plan-muted font-medium text-slate-400">{{ resolveBillingLabel() }}</span>
                            </div>
                            <div class="pricing-plan-meta mt-4 grid gap-2 text-sm leading-6 text-slate-300">
                                <p>المستخدمون الافتراضيون: <span class="pricing-plan-strong font-bold text-white">{{ includedUsersCount(plan) }}</span></p>
                                <p>المستخدم الإضافي شهريًا: <span class="pricing-plan-strong font-bold text-white">{{ extraUserMonthlyPrice(plan) }} ريال</span></p>
                            </div>
                        </div>

                        <ul class="mb-10 flex-1 space-y-4" :class="resolveTextAlignClass(plan.features_alignment || plan.featuresAlignment)">
                            <li
                                v-for="(feature, f) in plan.features"
                                :key="f"
                                class="flex items-start gap-3 text-base leading-8"
                                :class="resolveFeatureItemClass(plan.features_alignment || plan.featuresAlignment)"
                            >
                                <div class="mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full" :class="feature.included ? 'bg-green-500/20 text-green-400' : 'bg-red-500/10 text-red-400/70'">
                                    <Check v-if="feature.included" class="h-3 w-3" />
                                    <X v-else class="h-3 w-3" />
                                </div>
                                <span :class="feature.included ? 'pricing-plan-feature text-slate-200' : 'pricing-plan-feature--excluded text-slate-500 line-through'">{{ feature.text }}</span>
                            </li>
                        </ul>

                        <a
                            :href="resolvePlanUrl(plan)"
                            class="pricing-plan-cta w-full rounded-2xl py-4 text-center font-bold transition duration-300"
                            :class="[
                                plan.isFeatured ? 'bg-sky-600 text-white hover:bg-sky-700' : 'border border-white/10 bg-white/5 text-white hover:bg-white/10',
                                resolvePlanUrl(plan) === '#' ? 'opacity-60 cursor-not-allowed pointer-events-none' : '',
                            ]"
                        >
                            {{ resolveCtaText(plan) }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.pricing-table-section {
    color-scheme: dark;
    --pricing-card-bg: linear-gradient(180deg, rgba(15, 23, 42, 0.96), rgba(2, 6, 23, 0.94));
    --pricing-card-border: rgba(148, 163, 184, 0.22);
    --pricing-card-shadow: 0 24px 70px -42px rgba(2, 6, 23, 0.92);
    --pricing-text: #f8fafc;
    --pricing-muted: #cbd5e1;
    --pricing-subtle: #94a3b8;
    --pricing-disabled: #64748b;
}

:global(html.theme-light) .pricing-table-section {
    --pricing-card-bg: linear-gradient(180deg, rgba(15, 23, 42, 0.96), rgba(2, 6, 23, 0.94));
    --pricing-card-border: rgba(148, 163, 184, 0.22);
    --pricing-card-shadow: 0 24px 70px -42px rgba(2, 6, 23, 0.92);
    --pricing-text: #f8fafc;
    --pricing-muted: #cbd5e1;
    --pricing-subtle: #94a3b8;
    --pricing-disabled: #64748b;
}

.pricing-billing-toggle {
    background: rgba(15, 23, 42, 0.86) !important;
    border-color: rgba(148, 163, 184, 0.2) !important;
}

.pricing-billing-button:not(.bg-sky-600) {
    color: var(--pricing-muted) !important;
}

.pricing-billing-button:not(.bg-sky-600):hover {
    color: var(--pricing-text) !important;
}

.pricing-plan-card {
    background: var(--pricing-card-bg) !important;
    border-color: var(--pricing-card-border) !important;
    color: var(--pricing-text) !important;
    box-shadow: var(--pricing-card-shadow) !important;
}

.pricing-plan-card--featured {
    border-color: rgba(14, 165, 233, 0.45) !important;
}

.pricing-plan-title,
.pricing-plan-strong,
.pricing-plan-cta {
    color: var(--pricing-text) !important;
}

.pricing-plan-muted {
    color: var(--pricing-subtle) !important;
}

.pricing-plan-meta,
.pricing-plan-feature {
    color: var(--pricing-muted) !important;
}

.pricing-plan-feature--excluded {
    color: var(--pricing-disabled) !important;
}

.pricing-plan-cta:not(.bg-sky-600) {
    background: rgba(255, 255, 255, 0.05) !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
}

.pricing-plan-cta:not(.bg-sky-600):hover {
    background: rgba(255, 255, 255, 0.1) !important;
}

@media (max-width: 767px) {
    .pricing-plans-viewport {
        width: 100%;
        max-width: 100%;
        overflow: hidden;
    }

    .pricing-plans-track {
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        touch-action: auto;
        overscroll-behavior-x: contain;
        overscroll-behavior-y: auto;
        scroll-padding-inline: 0.5rem;
    }

    .pricing-plan-card {
        touch-action: auto;
    }

    .pricing-plan-card--featured {
        padding-top: 3.4rem;
    }

    .pricing-plan-featured-badge {
        top: 0.8rem;
    }

    .pricing-plans-track::-webkit-scrollbar {
        display: none;
    }
}

@media (min-width: 768px) {
    .pricing-plan-featured-badge {
        top: -1rem;
    }
}
</style>
