<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Lock, Mail, User } from 'lucide-vue-next';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="تسجيل جديد" />

        <div class="ui-auth-heading">
            <h2 class="ui-auth-title">إنشاء حساب جديد</h2>
            <p class="ui-auth-copy">أنشئ حسابًا عامًا للدخول إلى المنصة والوصول إلى المسارات المناسبة لدورك.</p>
        </div>

        <form class="ui-auth-form" @submit.prevent="submit">
            <div class="ui-auth-stack">
                <label for="name" class="ui-field-label">الاسم الكامل</label>
                <div class="ui-auth-field">
                    <User class="ui-auth-field-icon" />
                    <input
                        id="name"
                        v-model="form.name"
                        name="name"
                        data-field-label="الاسم الكامل"
                        type="text"
                        maxlength="255"
                        autocomplete="name"
                        class="ui-input pr-11"
                        placeholder="الاسم الكامل"
                        required
                        autofocus
                    />
                </div>
                <p v-if="form.errors.name" class="ui-field-error">{{ form.errors.name }}</p>
            </div>

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
            </div>

            <button class="ui-primary-button w-full" :disabled="form.processing">
                <ArrowLeft class="h-4 w-4" />
                <span>{{ form.processing ? 'جارٍ إنشاء الحساب...' : 'إنشاء الحساب' }}</span>
            </button>

            <div class="ui-auth-links">
                <p>
                    لديك حساب؟
                    <Link :href="route('login')" class="ui-text-link">تسجيل الدخول</Link>
                </p>
                <p>
                    مدير مدرسة:
                    <Link :href="route('register.manager.plan')" class="ui-text-link">التسجيل بخطة</Link>
                </p>
                <p>
                    مشرف تربوي:
                    <Link :href="route('register.supervisor')" class="ui-text-link">التسجيل بخطة</Link>
                </p>
            </div>
        </form>
    </GuestLayout>
</template>
