<script setup>
import { computed } from 'vue';
import { AlertCircle, CircleCheckBig, Inbox, LoaderCircle, SearchX, TriangleAlert } from 'lucide-vue-next';

const props = defineProps({
    variant: {
        type: String,
        default: 'empty',
    },
    title: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const iconComponent = computed(() => {
    if (props.variant === 'loading') {
        return LoaderCircle;
    }

    if (props.variant === 'error') {
        return AlertCircle;
    }

    if (props.variant === 'warning') {
        return TriangleAlert;
    }

    if (props.variant === 'success') {
        return CircleCheckBig;
    }

    if (props.variant === 'no-results') {
        return SearchX;
    }

    return Inbox;
});

const iconClass = computed(() => {
    if (props.variant === 'loading') {
        return 'animate-spin text-cyan-400';
    }

    if (props.variant === 'error') {
        return 'text-rose-400';
    }

    if (props.variant === 'warning') {
        return 'text-amber-400';
    }

    if (props.variant === 'success') {
        return 'text-emerald-400';
    }

    if (props.variant === 'no-results') {
        return 'text-cyan-400';
    }

    return 'text-slate-400';
});
</script>

<template>
    <div
        class="ui-empty-state"
        :class="compact ? 'min-h-[180px]' : ''"
        dir="rtl"
    >
        <component :is="iconComponent" class="h-9 w-9" :class="iconClass" />
        <h3 class="ui-empty-title">{{ title }}</h3>
        <p v-if="description" class="ui-empty-copy">{{ description }}</p>
        <div v-if="$slots.default" class="mt-2">
            <slot />
        </div>
    </div>
</template>
