<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Lock, Mail } from 'lucide-vue-next';

const props = defineProps({
    email: {
        type: String,
        default: '',
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="تعيين كلمة مرور جديدة" />

        <div class="ui-auth-heading">
            <h2 class="ui-auth-title">تعيين كلمة مرور جديدة</h2>
            <p class="ui-auth-copy">
                أدخل البريد الإلكتروني ثم كلمة المرور الجديدة وتأكيدها لتأمين الحساب ومتابعة العمل.
            </p>
        </div>

        <form class="ui-auth-form" @submit.prevent="submit">
            <div class="ui-auth-stack">
                <label for="email" class="ui-field-label">البريد الإلكتروني</label>
                <div class="ui-auth-field">
                    <Mail class="ui-auth-field-icon" />
                    <input
                        id="email"
                        v-model="form.email"
                        name="email"
                        data-field-label="البريد الإلكتروني"
                        type="email"
                        maxlength="255"
                        autocomplete="email"
                        class="ui-input pr-11"
                        placeholder="example@domain.com"
                        required
                        autofocus
                    />
                </div>
                <p v-if="form.errors.email" class="ui-field-error">{{ form.errors.email }}</p>
            </div>

            <div class="ui-auth-stack">
                <label for="password" class="ui-field-label">كلمة المرور الجديدة</label>
                <div class="ui-auth-field">
                    <Lock class="ui-auth-field-icon" />
                    <input
                        id="password"
                        v-model="form.password"
                        name="password"
                        data-field-label="كلمة المرور الجديدة"
                        type="password"
                        autocomplete="new-password"
                        class="ui-input pr-11"
                        placeholder="••••••••"
                        required
                    />
                </div>
                <p v-if="form.errors.password" class="ui-field-error">{{ form.errors.password }}</p>
            </div>

            <div class="ui-auth-stack">
                <label for="password_confirmation" class="ui-field-label">تأكيد كلمة المرور</label>
                <div class="ui-auth-field">
                    <Lock class="ui-auth-field-icon" />
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        name="password_confirmation"
                        data-field-label="تأكيد كلمة المرور"
                        type="password"
                        autocomplete="new-password"
                        class="ui-input pr-11"
                        placeholder="••••••••"
                        required
                    />
                </div>
                <p v-if="form.errors.password_confirmation" class="ui-field-error">{{ form.errors.password_confirmation }}</p>
            </div>

            <button class="ui-primary-button w-full" :disabled="form.processing">
                <ArrowLeft class="h-4 w-4" />
                <span>{{ form.processing ? 'جارٍ الحفظ...' : 'حفظ كلمة المرور' }}</span>
            </button>

            <div class="ui-auth-links">
                <Link :href="route('login')" class="ui-text-link">العودة لتسجيل الدخول</Link>
            </div>
        </form>
    </GuestLayout>
</template>
