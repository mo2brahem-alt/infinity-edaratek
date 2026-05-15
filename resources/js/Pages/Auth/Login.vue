<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Mail, Lock, LogIn } from 'lucide-vue-next';

defineProps({
    status: { type: String },
    canResetPassword: { type: Boolean },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="تسجيل الدخول" />

        <div v-if="status" class="ui-inline-alert ui-inline-alert--success mb-6 text-center">
            {{ status }}
        </div>

        <div class="ui-auth-heading">
            <h2 class="ui-auth-title">مرحبًا بعودتك</h2>
            <p class="ui-auth-copy">سجّل دخولك لمتابعة أعمال الإشراف والإدارة والمهام اليومية من مكان واحد.</p>
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
                        autocomplete="username"
                        class="ui-input pr-11"
                        placeholder="البريد الإلكتروني"
                        required
                        autofocus
                    />
                </div>
                <p v-if="form.errors.email" class="ui-field-error">{{ form.errors.email }}</p>
            </div>

            <div class="ui-auth-stack">
                <label for="password" class="ui-field-label">كلمة المرور</label>
                <div class="ui-auth-field">
                    <Lock class="ui-auth-field-icon" />
                    <input
                        id="password"
                        v-model="form.password"
                        name="password"
                        data-field-label="كلمة المرور"
                        type="password"
                        autocomplete="current-password"
                        class="ui-input pr-11"
                        placeholder="كلمة المرور"
                        required
                    />
                </div>
                <p v-if="form.errors.password" class="ui-field-error">{{ form.errors.password }}</p>
            </div>

            <div class="flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                <label class="flex cursor-pointer items-center gap-2 text-slate-300">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="ui-auth-checkbox h-4 w-4 rounded border-slate-600 bg-slate-900"
                    />
                    <span>تذكرني</span>
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="ui-text-link text-right font-semibold"
                >
                    نسيت كلمة المرور؟
                </Link>
            </div>

            <button
                type="submit"
                class="ui-primary-button w-full"
                :class="{ 'opacity-60': form.processing }"
                :disabled="form.processing"
            >
                <span>{{ form.processing ? 'جارٍ تسجيل الدخول...' : 'دخول' }}</span>
                <LogIn class="h-4 w-4" />
            </button>

            <div class="ui-auth-links">
                <p class="text-slate-400">
                    ليس لديك حساب؟
                    <Link :href="route('register')" class="ui-text-link font-semibold">إنشاء حساب عام</Link>
                </p>
                <p class="text-slate-400">
                    مدير مدرسة؟
                    <Link :href="route('register.manager.plan')" class="ui-text-link font-semibold">تسجيل مدير بخطة</Link>
                </p>
                <p class="text-slate-400">
                    مشرف تربوي؟
                    <Link :href="route('register.supervisor')" class="ui-text-link font-semibold">تسجيل مشرف بخطة</Link>
                </p>
                <p class="text-slate-400">
                    <Link :href="route('pricing.index')" class="ui-text-link font-semibold">عرض جميع الباقات</Link>
                </p>
            </div>
        </form>
    </GuestLayout>
</template>
