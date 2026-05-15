<script setup>
import { ref } from 'vue';
import { Mail, MessageSquareText, Send, UserRound } from 'lucide-vue-next';
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    message: '',
});

const successMessage = ref('');

const submit = () => {
    successMessage.value = 'تم استلام رسالتك بنجاح. سنراجعها ونتواصل معك قريبًا.';
    form.reset();
};
</script>

<template>
    <section class="ui-public-widget mx-auto my-8 max-w-3xl p-6 md:p-8">
        <div class="ui-public-header text-right">
            <span class="ui-public-badge w-fit">
                <Mail class="h-4 w-4" />
                <span>نموذج التواصل</span>
            </span>
            <div>
                <h3 class="ui-public-title text-2xl md:text-3xl">تواصل معنا</h3>
                <p class="ui-public-copy mt-3">
                    أرسل استفسارك أو طلبك، وسنعود إليك برد واضح وسريع عبر البريد الإلكتروني.
                </p>
            </div>
        </div>

        <form class="space-y-5" @submit.prevent="submit">
            <div class="grid gap-5 md:grid-cols-2">
                <label class="ui-auth-field text-right">
                    <span class="ui-field-label">الاسم</span>
                    <div class="relative">
                        <UserRound class="ui-auth-field-icon absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2" />
                        <input v-model="form.name" type="text" class="ui-input pr-11" placeholder="اكتب الاسم الكامل" />
                    </div>
                </label>

                <label class="ui-auth-field text-right">
                    <span class="ui-field-label">البريد الإلكتروني</span>
                    <div class="relative">
                        <Mail class="ui-auth-field-icon absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2" />
                        <input v-model="form.email" type="email" dir="ltr" class="ui-input pr-11" placeholder="name@example.com" />
                    </div>
                </label>
            </div>

            <label class="ui-auth-field text-right">
                <span class="ui-field-label">الرسالة</span>
                <div class="relative">
                    <MessageSquareText class="ui-auth-field-icon absolute right-4 top-5 h-4 w-4" />
                    <textarea v-model="form.message" rows="5" class="ui-textarea pr-11" placeholder="اكتب رسالتك هنا" />
                </div>
            </label>

            <p v-if="successMessage" class="ui-inline-alert ui-inline-alert--success">
                {{ successMessage }}
            </p>

            <div class="flex justify-end">
                <button type="submit" class="ui-primary-button">
                    <Send class="h-4 w-4" />
                    <span>إرسال الرسالة</span>
                </button>
            </div>
        </form>
    </section>
</template>
