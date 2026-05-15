<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ArrowLeft, Lock, Mail, Phone, ShieldCheck, User } from 'lucide-vue-next';

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
const formatPrice = (value) => {
    const numeric = Number(value ?? 0);

    if (Number.isNaN(numeric)) return value ?? '0';

    return Number.isInteger(numeric) ? numeric.toString() : numeric.toFixed(2);
};
const monthlyPrice = computed(() => props.selectedPlan?.monthly_price ?? props.selectedPlan?.price ?? 0);
const yearlyPrice = computed(() => props.selectedPlan?.yearly_price ?? props.selectedPlan?.price ?? 0);

const form = useForm({
    plan_id: props.selectedPlan?.id || '',
    billing_cycle: props.initialBillingCycle === 'YEARLY' ? 'YEARLY' : 'MONTHLY',
    name: '',
    email: '',
    mobile: '',
    password: '',
    password_confirmation: '',
});
const selectedBillingPrice = computed(() => form.billing_cycle === 'YEARLY' ? yearlyPrice.value : monthlyPrice.value);

const submit = () => {
    form.post(route('register.supervisor.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="تسجيل مشرف" />

        <div class="ui-auth-heading">
            <h2 class="ui-auth-title">تسجيل مشرف جديد</h2>
            <p class="ui-auth-copy">الحساب مرتبط تلقائيًا بالخطة التي اخترتها من صفحة الباقات.</p>
        </div>

        <form class="ui-auth-form" @submit.prevent="submit">
            <input v-model="form.plan_id" type="hidden" />

            <div class="ui-card-soft p-4 text-right">
                <div class="flex items-start gap-3">
                    <div class="ui-icon-button h-11 w-11 shrink-0 rounded-2xl">
                        <ShieldCheck class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs text-slate-400">الخطة المختارة</p>
                        <p v-if="hasSelectedPlan" class="mt-1 text-sm font-black text-white">
                            {{ selectedPlan.name }} - {{ formatPrice(selectedBillingPrice) }} SAR
                        </p>
                        <div v-if="hasSelectedPlan" class="mt-4 space-y-2">
                            <label for="supervisor-billing-cycle" class="ui-field-label">نوع الفوترة</label>
                            <select
                                id="supervisor-billing-cycle"
                                v-model="form.billing_cycle"
                                name="billing_cycle"
                                class="ui-input"
                            >
                                <option value="MONTHLY">شهري - {{ formatPrice(monthlyPrice) }} ريال</option>
                                <option value="YEARLY">سنوي - {{ formatPrice(yearlyPrice) }} ريال</option>
                            </select>
                        </div>
                        <p v-else class="mt-1 text-sm text-red-400">لا توجد خطة متاحة حاليًا لهذا النوع.</p>
                        <p v-if="form.errors.plan_id" class="ui-field-error">{{ form.errors.plan_id }}</p>
                        <p v-if="form.errors.billing_cycle" class="ui-field-error">{{ form.errors.billing_cycle }}</p>
                    </div>
                </div>
            </div>

            <div class="ui-auth-stack">
                <label for="supervisor-name" class="ui-field-label">الاسم</label>
                <div class="ui-auth-field">
                    <User class="ui-auth-field-icon" />
                    <input v-model="form.name" id="supervisor-name" name="name" data-field-label="الاسم" type="text" maxlength="255" autocomplete="name" class="ui-input pr-11" />
                </div>
                <p v-if="form.errors.name" class="ui-field-error">{{ form.errors.name }}</p>
            </div>

            <div class="ui-auth-stack">
                <label for="supervisor-email" class="ui-field-label">البريد الإلكتروني</label>
                <div class="ui-auth-field">
                    <Mail class="ui-auth-field-icon" />
                    <input v-model="form.email" id="supervisor-email" name="email" data-field-label="البريد الإلكتروني" type="email" maxlength="255" autocomplete="email" class="ui-input pr-11" dir="ltr" />
                </div>
                <p v-if="form.errors.email" class="ui-field-error">{{ form.errors.email }}</p>
            </div>

            <div class="ui-auth-stack">
                <label for="supervisor-mobile" class="ui-field-label">الجوال</label>
                <div class="ui-auth-field">
                    <Phone class="ui-auth-field-icon" />
                    <input v-model="form.mobile" id="supervisor-mobile" name="mobile" data-field-label="رقم الجوال" type="text" inputmode="tel" maxlength="20" autocomplete="tel" class="ui-input pr-11" placeholder="05xxxxxxxx أو +9665xxxxxxxx" dir="ltr" />
                </div>
                <p v-if="form.errors.mobile" class="ui-field-error">{{ form.errors.mobile }}</p>
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div class="ui-auth-stack">
                    <label for="supervisor-password" class="ui-field-label">كلمة المرور</label>
                    <div class="ui-auth-field">
                        <Lock class="ui-auth-field-icon" />
                        <input v-model="form.password" id="supervisor-password" name="password" data-field-label="كلمة المرور" type="password" autocomplete="new-password" class="ui-input pr-11" dir="ltr" />
                    </div>
                    <p v-if="form.errors.password" class="ui-field-error">{{ form.errors.password }}</p>
                </div>

                <div class="ui-auth-stack">
                    <label for="supervisor-password-confirmation" class="ui-field-label">تأكيد كلمة المرور</label>
                    <div class="ui-auth-field">
                        <Lock class="ui-auth-field-icon" />
                        <input v-model="form.password_confirmation" id="supervisor-password-confirmation" name="password_confirmation" data-field-label="تأكيد كلمة المرور" type="password" autocomplete="new-password" class="ui-input pr-11" dir="ltr" />
                    </div>
                </div>
            </div>

            <button class="ui-primary-button w-full" :disabled="form.processing || !hasSelectedPlan">
                <ArrowLeft class="h-4 w-4" />
                <span>{{ form.processing ? 'جارٍ إنشاء الحساب...' : 'إنشاء حساب المشرف' }}</span>
            </button>

            <div class="ui-auth-links">
                <p>
                    تريد مقارنة الخطط؟
                    <Link :href="route('pricing.index')" class="ui-text-link">صفحة الأسعار</Link>
                </p>
                <p>
                    لديك حساب؟
                    <Link :href="route('login')" class="ui-text-link">تسجيل الدخول</Link>
                </p>
            </div>
        </form>
    </GuestLayout>
</template>
