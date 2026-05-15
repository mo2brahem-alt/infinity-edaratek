<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Mail } from 'lucide-vue-next';

defineProps({
    status: {
        type: String,
        default: '',
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="استعادة كلمة المرور" />

        <div v-if="status" class="ui-inline-alert ui-inline-alert--success mb-6 text-right">
            {{ status }}
        </div>

        <div class="ui-auth-heading">
            <h2 class="ui-auth-title">استعادة كلمة المرور</h2>
            <p class="ui-auth-copy">
                أدخل بريدك الإلكتروني وسنرسل لك رابطًا آمنًا لإعادة تعيين كلمة المرور والعودة إلى حسابك.
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

            <button class="ui-primary-button w-full" :disabled="form.processing">
                <ArrowLeft class="h-4 w-4" />
                <span>{{ form.processing ? 'جارٍ الإرسال...' : 'إرسال رابط الاستعادة' }}</span>
            </button>

            <div class="ui-auth-links">
                <p>
                    تذكرت كلمة المرور؟
                    <Link :href="route('login')" class="ui-text-link">العودة لتسجيل الدخول</Link>
                </p>
            </div>
        </form>
    </GuestLayout>
</template>
