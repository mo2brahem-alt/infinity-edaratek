<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Lock, Mail, Phone, School, User } from 'lucide-vue-next';

defineProps({
    schools: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: '',
    email: '',
    mobile: '',
    school_id: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register.manager.store'));
};
</script>

<template>
    <GuestLayout>
        <Head title="تسجيل مدير مدرسة" />

        <div class="ui-auth-heading">
            <h2 class="ui-auth-title">تسجيل مدير مدرسة</h2>
            <p class="ui-auth-copy">
                اختر مدرسة موقوفة، ثم أنشئ الحساب ليتم إرسال طلب المصافحة للمراجعة والموافقة.
            </p>
        </div>

        <form class="ui-auth-form" @submit.prevent="submit">
            <div class="ui-auth-stack">
                <label for="manager-name" class="ui-field-label">الاسم</label>
                <div class="ui-auth-field">
                    <User class="ui-auth-field-icon" />
                    <input id="manager-name" v-model="form.name" name="name" data-field-label="الاسم" type="text" maxlength="255" autocomplete="name" class="ui-input pr-11" required autofocus />
                </div>
                <p v-if="form.errors.name" class="ui-field-error">{{ form.errors.name }}</p>
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div class="ui-auth-stack">
                    <label for="manager-email" class="ui-field-label">البريد الإلكتروني</label>
                    <div class="ui-auth-field">
                        <Mail class="ui-auth-field-icon" />
                        <input id="manager-email" v-model="form.email" name="email" data-field-label="البريد الإلكتروني" type="email" maxlength="255" autocomplete="email" class="ui-input pr-11" dir="ltr" required />
                    </div>
                    <p v-if="form.errors.email" class="ui-field-error">{{ form.errors.email }}</p>
                </div>

                <div class="ui-auth-stack">
                    <label for="manager-mobile" class="ui-field-label">الجوال</label>
                    <div class="ui-auth-field">
                        <Phone class="ui-auth-field-icon" />
                        <input id="manager-mobile" v-model="form.mobile" name="mobile" data-field-label="رقم الجوال" type="text" inputmode="tel" maxlength="20" autocomplete="tel" class="ui-input pr-11" placeholder="05xxxxxxxx أو +9665xxxxxxxx" dir="ltr" required />
                    </div>
                    <p v-if="form.errors.mobile" class="ui-field-error">{{ form.errors.mobile }}</p>
                </div>
            </div>

            <div class="ui-auth-stack">
                <label for="manager-school" class="ui-field-label">المدرسة</label>
                <div class="ui-auth-field">
                    <School class="ui-auth-field-icon" />
                    <select id="manager-school" v-model="form.school_id" name="school_id" data-field-label="المدرسة" class="ui-select pr-11" required>
                        <option value="" disabled>اختر مدرسة</option>
                        <option v-for="school in schools" :key="school.id" :value="school.id">
                            {{ school.name }} - {{ school.school_id }}
                        </option>
                    </select>
                </div>
                <p v-if="form.errors.school_id" class="ui-field-error">{{ form.errors.school_id }}</p>
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div class="ui-auth-stack">
                    <label for="manager-password" class="ui-field-label">كلمة المرور</label>
                    <div class="ui-auth-field">
                        <Lock class="ui-auth-field-icon" />
                        <input id="manager-password" v-model="form.password" name="password" data-field-label="كلمة المرور" type="password" autocomplete="new-password" class="ui-input pr-11" dir="ltr" required />
                    </div>
                    <p v-if="form.errors.password" class="ui-field-error">{{ form.errors.password }}</p>
                </div>

                <div class="ui-auth-stack">
                    <label for="manager-password-confirmation" class="ui-field-label">تأكيد كلمة المرور</label>
                    <div class="ui-auth-field">
                        <Lock class="ui-auth-field-icon" />
                        <input id="manager-password-confirmation" v-model="form.password_confirmation" name="password_confirmation" data-field-label="تأكيد كلمة المرور" type="password" autocomplete="new-password" class="ui-input pr-11" dir="ltr" required />
                    </div>
                </div>
            </div>

            <button class="ui-primary-button w-full" :disabled="form.processing">
                <ArrowLeft class="h-4 w-4" />
                <span>{{ form.processing ? 'جارٍ إنشاء الحساب...' : 'إنشاء حساب المدير' }}</span>
            </button>

            <div class="ui-auth-links">
                <p>
                    لديك حساب؟
                    <Link :href="route('login')" class="ui-text-link">تسجيل الدخول</Link>
                </p>
                <p>
                    تريد التسجيل العام؟
                    <Link :href="route('register')" class="ui-text-link">العودة إلى التسجيل العام</Link>
                </p>
            </div>
        </form>
    </GuestLayout>
</template>
