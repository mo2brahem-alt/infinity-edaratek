<script setup>
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import PricingTable from '@/Components/Shortcodes/PricingTable.vue';

const loading = ref(true);
const loadingError = ref('');
const supervisorPlans = ref([]);
const managerPlans = ref([]);

const normalizePrice = (price) => {
    const numericPrice = Number(price);

    if (Number.isNaN(numericPrice)) {
        return String(price ?? '');
    }

    return Number.isInteger(numericPrice) ? numericPrice.toString() : numericPrice.toFixed(2);
};

const normalizeBillingLabel = (billingCycle) => {
    if (billingCycle === 'YEARLY') {
        return '/ سنوياً';
    }

    return '/ شهرياً';
};

const planDisplayPrice = (plan) => plan.monthly_price ?? plan.price ?? 0;

const formatLimitKey = (key) => String(key ?? '')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase());

const formatLimitValue = (value) => {
    if (Array.isArray(value)) {
        return value.join(', ');
    }

    if (typeof value === 'boolean') {
        return value ? 'متاح' : 'غير متاح';
    }

    if (value === null || value === undefined || value === '') {
        return null;
    }

    return String(value);
};

const mapPlanFeatures = (plan) => {
    const features = [];

    if (plan.description) {
        features.push({
            text: plan.description,
            included: true,
        });
    }

    if (plan.limits && typeof plan.limits === 'object' && !Array.isArray(plan.limits)) {
        Object.entries(plan.limits).forEach(([key, value]) => {
            const normalizedValue = formatLimitValue(value);

            if (!normalizedValue) return;

            features.push({
                text: `${formatLimitKey(key)}: ${normalizedValue}`,
                included: true,
            });
        });
    }

    if (features.length === 0) {
        features.push({
            text: 'اشتراك فعّال مع مزايا أساسية.',
            included: true,
        });
    }

    return features;
};

const buildRegistrationUrl = (plan) => {
    if (plan.role_type === 'SUPERVISOR') {
        return route('register.supervisor', { plan_id: plan.id });
    }

    return route('register.manager.plan', { plan_id: plan.id });
};

const mapPlanForPricingCard = (plan) => ({
    id: plan.id,
    plan_id: plan.id,
    role_type: plan.role_type,
    name: plan.name,
    price: normalizePrice(planDisplayPrice(plan)),
    monthly_price: plan.monthly_price ?? plan.price ?? 0,
    yearly_price: plan.yearly_price ?? 0,
    billing_cycle: plan.billing_cycle,
    included_users_count: plan.included_users_count ?? 0,
    extra_user_monthly_price: plan.extra_user_monthly_price ?? 0,
    billingLabel: normalizeBillingLabel('MONTHLY'),
    features: mapPlanFeatures(plan),
    isFeatured: false,
    ctaText: 'اشتراك',
});

const buildPricingData = (title, plans, priceColor) => ({
    title,
    subtitle: 'اختر الخطة المناسبة ثم أكمل التسجيل.',
    titleColor: '#ffffff',
    subtitleColor: '#9ca3af',
    cardBgColor: 'bg-gray-900',
    priceColor,
    design: {
        marginTop: 0,
        marginBottom: 0,
        paddingTop: 40,
        paddingBottom: 32,
        textAlign: 'text-right',
        titleSize: 30,
        subtitleSize: 15,
    },
    plans: plans.map(mapPlanForPricingCard),
});

const supervisorPricingData = computed(() => buildPricingData('خطط المشرفين', supervisorPlans.value, '#60a5fa'));
const managerPricingData = computed(() => buildPricingData('خطط مديري المدارس', managerPlans.value, '#34d399'));
const hasPlans = computed(() => supervisorPlans.value.length > 0 || managerPlans.value.length > 0);

const loadPlans = async () => {
    loading.value = true;
    loadingError.value = '';

    try {
        const [supervisorResponse, managerResponse] = await Promise.all([
            axios.get(route('plans.index'), { params: { role_type: 'SUPERVISOR' } }),
            axios.get(route('plans.index'), { params: { role_type: 'SCHOOL_MANAGER' } }),
        ]);

        supervisorPlans.value = Array.isArray(supervisorResponse.data) ? supervisorResponse.data : [];
        managerPlans.value = Array.isArray(managerResponse.data) ? managerResponse.data : [];
    } catch (error) {
        loadingError.value = 'تعذر تحميل خطط الاشتراك حالياً. حاول مرة أخرى لاحقاً.';
    } finally {
        loading.value = false;
    }
};

onMounted(loadPlans);
</script>

<template>
    <section id="subscriptions" class="border-t border-white/5 bg-gray-900/60 py-16">
        <div class="mx-auto mb-8 max-w-7xl px-6 text-center">
            <h2 class="text-3xl font-bold text-white">الاشتراكات</h2>
            <p class="mt-2 text-sm text-gray-400">اختر نوع الاشتراك المناسب وابدأ التسجيل مباشرة.</p>
        </div>

        <div v-if="loading" class="mx-auto max-w-7xl px-6 text-center text-sm text-gray-400">
            جاري تحميل خطط الاشتراك...
        </div>

        <div v-else-if="loadingError" class="mx-auto max-w-7xl px-6 text-center text-sm text-red-300">
            {{ loadingError }}
        </div>

        <div v-else-if="hasPlans" class="space-y-6">
            <PricingTable v-if="supervisorPricingData.plans.length" :data="supervisorPricingData" />
            <PricingTable v-if="managerPricingData.plans.length" :data="managerPricingData" />
        </div>

        <div v-else class="mx-auto max-w-7xl px-6 text-center text-sm text-gray-400">
            لا توجد خطط اشتراك متاحة حالياً.
        </div>
    </section>
</template>
