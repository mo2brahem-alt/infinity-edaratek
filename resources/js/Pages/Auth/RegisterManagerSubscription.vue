<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    selectedPlan: {
        type: Object,
        default: null,
    },
    initialBillingCycle: {
        type: String,
        default: 'MONTHLY',
    },
});

const hasSelectedPlan = computed(() => !!props.selectedPlan?.id);
const normalizeNumber = (value) => {
    const numeric = Number(value ?? 0);

    return Number.isFinite(numeric) ? numeric : 0;
};
const formatPrice = (value) => {
    const numeric = normalizeNumber(value);

    return Number.isInteger(numeric) ? numeric.toString() : numeric.toFixed(2);
};
const monthlyPrice = computed(() => props.selectedPlan?.monthly_price ?? props.selectedPlan?.price ?? 0);
const yearlyPrice = computed(() => props.selectedPlan?.yearly_price ?? props.selectedPlan?.price ?? 0);
const includedUsersCount = computed(() => Math.max(0, Number.parseInt(props.selectedPlan?.included_users_count ?? 0, 10) || 0));
const extraUserMonthlyPrice = computed(() => normalizeNumber(props.selectedPlan?.extra_user_monthly_price ?? 0));

const form = useForm({
    plan_id: props.selectedPlan?.id || '',
    billing_cycle: props.initialBillingCycle === 'YEARLY' ? 'YEARLY' : 'MONTHLY',
    extra_users_count: 0,
    name: '',
    email: '',
    mobile: '',
    password: '',
    password_confirmation: '',
});
const selectedBillingPrice = computed(() => form.billing_cycle === 'YEARLY' ? yearlyPrice.value : monthlyPrice.value);
const billingMonths = computed(() => form.billing_cycle === 'YEARLY' ? 12 : 1);
const billingDurationLabel = computed(() => form.billing_cycle === 'YEARLY' ? 'سنة كاملة' : 'شهر واحد');
const extraUsersCount = computed(() => Math.max(0, Number.parseInt(form.extra_users_count ?? 0, 10) || 0));
const requestedUsersCount = computed(() => includedUsersCount.value + extraUsersCount.value);
const extraUsersAmount = computed(() => extraUsersCount.value * extraUserMonthlyPrice.value * billingMonths.value);
const totalPrice = computed(() => normalizeNumber(selectedBillingPrice.value) + extraUsersAmount.value);

const submit = () => {
    form.extra_users_count = extraUsersCount.value;
    form.post(route('register.manager.plan.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="تسجيل مدير مدرسة" />

        <div class="ui-auth-heading">
            <h2 class="ui-auth-title">تسجيل مدير مدرسة</h2>
            <p class="ui-auth-copy">
                الحساب سيرتبط تلقائيًا بالخطة المختارة، وبعد إنشاء الحساب ستتابع إعداد المحافظة ونوع التعليم والمدرسة من شاشة التهيئة.
            </p>
        </div>

        <form class="ui-auth-form" @submit.prevent="submit">
            <input v-model="form.plan_id" type="hidden" />

            <div class="ui-card-soft rounded-2xl p-4 text-right">
                <p class="ui-helper-text !mt-0">الخطة المختارة</p>
                <p v-if="hasSelectedPlan" class="mt-2 text-sm font-black text-white">
                    {{ selectedPlan.name }} - الإجمالي {{ formatPrice(totalPrice) }} ريال
                </p>
                <div v-if="hasSelectedPlan" class="mt-4 space-y-2">
                    <label for="manager-plan-billing-cycle" class="ui-field-label">نوع الفوترة</label>
                    <select
                        id="manager-plan-billing-cycle"
                        v-model="form.billing_cycle"
                        name="billing_cycle"
                        class="ui-input"
                    >
                        <option value="MONTHLY">شهري - {{ formatPrice(monthlyPrice) }} ريال</option>
                        <option value="YEARLY">سنوي - {{ formatPrice(yearlyPrice) }} ريال</option>
                    </select>

                    <div class="space-y-2 pt-2">
                        <label for="manager-plan-extra-users-count" class="ui-field-label">عدد المستخدمين الإضافيين عند التسجيل</label>
                        <input
                            id="manager-plan-extra-users-count"
                            v-model.number="form.extra_users_count"
                            name="extra_users_count"
                            type="number"
                            min="0"
                            step="1"
                            inputmode="numeric"
                            class="ui-input"
                        />
                        <p class="text-xs leading-6 text-slate-400">
                            الباقة تشمل {{ includedUsersCount }} مستخدمين افتراضيًا. إجمالي المستخدمين بعد الإضافة: {{ requestedUsersCount }}.
                        </p>
                    </div>

                    <div class="mt-4 grid gap-2 border-t border-white/10 pt-4 text-xs leading-6 text-slate-300">
                        <div class="flex items-center justify-between gap-3">
                            <span>سعر الباقة</span>
                            <span class="font-bold text-white">{{ formatPrice(selectedBillingPrice) }} ريال</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span>سعر المستخدم الإضافي شهريًا</span>
                            <span class="font-bold text-white">{{ formatPrice(extraUserMonthlyPrice) }} ريال</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span>المستخدمون الإضافيون</span>
                            <span class="font-bold text-white">{{ extraUsersCount }} × {{ billingDurationLabel }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span>تكلفة المستخدمين الإضافيين</span>
                            <span class="font-bold text-white">{{ formatPrice(extraUsersAmount) }} ريال</span>
                        </div>
                        <div class="flex items-center justify-between gap-3 border-t border-white/10 pt-2 text-sm">
                            <span class="font-black text-white">الإجمالي المطلوب</span>
                            <span class="font-black text-sky-200">{{ formatPrice(totalPrice) }} ريال</span>
                        </div>
                    </div>
                </div>
                <p v-else class="mt-2 text-sm font-semibold text-red-400">
                    لا توجد خطة متاحة حاليًا لهذا النوع.
                </p>
                <p v-if="form.errors.plan_id" class="ui-field-error">{{ form.errors.plan_id }}</p>
                <p v-if="form.errors.billing_cycle" class="ui-field-error">{{ form.errors.billing_cycle }}</p>
                <p v-if="form.errors.extra_users_count" class="ui-field-error">{{ form.errors.extra_users_count }}</p>
            </div>

            <div class="ui-auth-stack">
                <label for="manager-plan-name" class="ui-field-label">الاسم</label>
                <input
                    id="manager-plan-name"
                    v-model="form.name"
                    name="name"
                    data-field-label="الاسم"
                    type="text"
                    maxlength="255"
                    autocomplete="name"
                    class="ui-input"
                />
                <p v-if="form.errors.name" class="ui-field-error">{{ form.errors.name }}</p>
            </div>

            <div class="ui-auth-stack">
                <label for="manager-plan-email" class="ui-field-label">البريد الإلكتروني</label>
                <input
                    id="manager-plan-email"
                    v-model="form.email"
                    name="email"
                    data-field-label="البريد الإلكتروني"
                    type="email"
                    maxlength="255"
                    autocomplete="email"
                    class="ui-input"
                    dir="ltr"
                />
                <p v-if="form.errors.email" class="ui-field-error">{{ form.errors.email }}</p>
            </div>

            <div class="ui-auth-stack">
                <label for="manager-plan-mobile" class="ui-field-label">الجوال</label>
                <input
                    id="manager-plan-mobile"
                    v-model="form.mobile"
                    name="mobile"
                    data-field-label="رقم الجوال"
                    type="text"
                    inputmode="tel"
                    maxlength="20"
                    autocomplete="tel"
                    class="ui-input"
                    placeholder="05xxxxxxxx أو +9665xxxxxxxx"
                    dir="ltr"
                />
                <p v-if="form.errors.mobile" class="ui-field-error">{{ form.errors.mobile }}</p>
            </div>

            <div class="ui-auth-stack">
                <label for="manager-plan-password" class="ui-field-label">كلمة المرور</label>
                <input
                    id="manager-plan-password"
                    v-model="form.password"
                    name="password"
                    data-field-label="كلمة المرور"
                    type="password"
                    autocomplete="new-password"
                    class="ui-input"
                    dir="ltr"
                />
                <p v-if="form.errors.password" class="ui-field-error">{{ form.errors.password }}</p>
            </div>

            <div class="ui-auth-stack">
                <label for="manager-plan-password-confirmation" class="ui-field-label">تأكيد كلمة المرور</label>
                <input
                    id="manager-plan-password-confirmation"
                    v-model="form.password_confirmation"
                    name="password_confirmation"
                    data-field-label="تأكيد كلمة المرور"
                    type="password"
                    autocomplete="new-password"
                    class="ui-input"
                    dir="ltr"
                />
            </div>

            <button
                type="submit"
                class="ui-primary-button w-full"
                :class="{ 'opacity-60': form.processing || !hasSelectedPlan }"
                :disabled="form.processing || !hasSelectedPlan"
            >
                {{ form.processing ? 'جارٍ إنشاء الحساب...' : 'إنشاء حساب مدير المدرسة' }}
            </button>

            <div class="ui-auth-links">
                <p class="text-slate-400">
                    تريد مقارنة الخطط؟
                    <Link :href="route('pricing.index')" class="ui-text-link font-semibold">صفحة الأسعار</Link>
                </p>
                <p class="text-slate-400">
                    لديك حساب؟
                    <Link :href="route('login')" class="ui-text-link font-semibold">تسجيل الدخول</Link>
                </p>
            </div>
        </form>
    </GuestLayout>
</template>
