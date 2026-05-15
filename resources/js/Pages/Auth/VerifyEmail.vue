<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MailCheck } from 'lucide-vue-next';

defineProps({
    status: {
        type: String,
        default: '',
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};
</script>

<template>
    <GuestLayout>
        <Head title="تأكيد البريد الإلكتروني" />

        <div class="ui-auth-heading">
            <h2 class="ui-auth-title">تأكيد البريد الإلكتروني</h2>
            <p class="ui-auth-copy">
                قبل البدء، افتح رسالة التفعيل التي أرسلناها إلى بريدك الإلكتروني ثم عد إلى النظام بعد التأكيد.
            </p>
        </div>

        <div class="ui-card-soft mb-6 flex items-start gap-3 p-4 text-right">
            <div class="ui-icon-button h-11 w-11 shrink-0 rounded-2xl">
                <MailCheck class="h-5 w-5" />
            </div>
            <p class="text-sm leading-7 text-slate-300">
                إذا لم يصلك البريد، يمكنك طلب إعادة الإرسال من الزر التالي. سيتم إرسال رابط جديد إلى نفس البريد المسجل.
            </p>
        </div>

        <div v-if="status === 'verification-link-sent'" class="ui-inline-alert ui-inline-alert--success mb-6 text-right">
            تم إرسال رابط تأكيد جديد إلى بريدك الإلكتروني.
        </div>

        <form @submit.prevent="submit">
            <div class="flex flex-col gap-3 sm:flex-row">
                <button class="ui-primary-button flex-1" :disabled="form.processing">
                    {{ form.processing ? 'جارٍ الإرسال...' : 'إعادة إرسال رابط التأكيد' }}
                </button>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="ui-ghost-button flex-1"
                >
                    تسجيل الخروج
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
