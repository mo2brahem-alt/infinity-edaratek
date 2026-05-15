<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppInlineAlert from '@/Components/AppInlineAlert.vue';

const page = usePage();

const alerts = computed(() => {
    const flash = page.props?.flash || {};
    const entries = [];

    if (flash.success) {
        entries.push({
            key: 'success',
            variant: 'success',
            title: 'تم تنفيذ العملية بنجاح',
            message: flash.success,
        });
    }

    if (flash.message) {
        entries.push({
            key: 'message',
            variant: 'success',
            title: 'تم تحديث البيانات',
            message: flash.message,
        });
    }

    if (flash.warning) {
        entries.push({
            key: 'warning',
            variant: 'warning',
            title: 'تنبيه',
            message: flash.warning,
        });
    }

    if (flash.info) {
        entries.push({
            key: 'info',
            variant: 'info',
            title: 'معلومة',
            message: flash.info,
        });
    }

    if (flash.error) {
        entries.push({
            key: 'error',
            variant: 'danger',
            title: 'تعذر إكمال الطلب',
            message: flash.error,
        });
    }

    return entries.filter((item) => String(item.message || '').trim() !== '');
});
</script>

<template>
    <div v-if="alerts.length > 0" class="ui-feedback-stack" dir="rtl">
        <AppInlineAlert
            v-for="item in alerts"
            :key="item.key"
            :variant="item.variant"
            :title="item.title"
            :message="item.message"
        />
    </div>
</template>
