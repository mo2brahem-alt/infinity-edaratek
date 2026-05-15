<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Lock, ShieldCheck } from 'lucide-vue-next';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="تأكيد كلمة المرور" />

        <div class="ui-auth-heading">
            <h2 class="ui-auth-title">تأكيد كلمة المرور</h2>
            <p class="ui-auth-copy">هذه منطقة محمية داخل النظام. أدخل كلمة المرور الحالية للمتابعة بأمان.</p>
        </div>

        <div class="ui-card-soft mb-6 flex items-start gap-3 p-4 text-right">
            <div class="ui-icon-button h-11 w-11 shrink-0 rounded-2xl">
                <ShieldCheck class="h-5 w-5" />
            </div>
            <p class="text-sm leading-7 text-slate-300">
                لن يتم تنفيذ أي إجراء حتى يتم التحقق من هويتك عبر كلمة المرور الحالية للحساب.
            </p>
        </div>

        <form class="ui-auth-form" @submit.prevent="submit">
            <div class="ui-auth-stack">
                <label for="password" class="ui-field-label">كلمة المرور الحالية</label>
                <div class="ui-auth-field">
                    <Lock class="ui-auth-field-icon" />
                    <input
                        id="password"
                        v-model="form.password"
                        name="password"
                        data-field-label="كلمة المرور الحالية"
                        type="password"
                        autocomplete="current-password"
                        class="ui-input pr-11"
                        placeholder="••••••••"
                        required
                        autofocus
                    />
                </div>
                <p v-if="form.errors.password" class="ui-field-error">{{ form.errors.password }}</p>
            </div>

            <button class="ui-primary-button w-full" :disabled="form.processing">
                {{ form.processing ? 'جارٍ التحقق...' : 'تأكيد' }}
            </button>
        </form>
    </GuestLayout>
</template>
