<script setup>
import { computed } from 'vue';
import { AlertCircle, CheckCircle2, Info, TriangleAlert } from 'lucide-vue-next';

const props = defineProps({
    variant: {
        type: String,
        default: 'info',
    },
    title: {
        type: String,
        default: '',
    },
    message: {
        type: String,
        default: '',
    },
});

const normalizedVariant = computed(() => {
    if (props.variant === 'success') return 'success';
    if (props.variant === 'warning') return 'warning';
    if (props.variant === 'danger' || props.variant === 'error') return 'danger';
    return 'info';
});

const iconComponent = computed(() => {
    if (normalizedVariant.value === 'success') return CheckCircle2;
    if (normalizedVariant.value === 'warning') return TriangleAlert;
    if (normalizedVariant.value === 'danger') return AlertCircle;
    return Info;
});

const roleAttribute = computed(() => (normalizedVariant.value === 'danger' ? 'alert' : 'status'));
</script>

<template>
    <div
        class="ui-inline-alert"
        :class="`ui-inline-alert--${normalizedVariant}`"
        :role="roleAttribute"
        dir="rtl"
    >
        <div class="ui-inline-alert-content">
            <component :is="iconComponent" class="ui-inline-alert-icon" />
            <div class="min-w-0 flex-1">
                <p v-if="title" class="ui-inline-alert-title">{{ title }}</p>
                <p v-if="message || $slots.default" class="ui-inline-alert-copy">
                    <slot>{{ message }}</slot>
                </p>
            </div>
        </div>
    </div>
</template>
