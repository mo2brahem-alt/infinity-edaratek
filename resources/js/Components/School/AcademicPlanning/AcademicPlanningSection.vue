<script setup>
const props = defineProps({
    sectionId: {
        type: String,
        default: '',
    },
    step: {
        type: [String, Number],
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    guidance: {
        type: String,
        default: '',
    },
    isReady: {
        type: Boolean,
        default: true,
    },
    prerequisiteMessage: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <section :id="sectionId || undefined" class="rounded-xl border border-gray-800 bg-gray-900 p-4">
        <div class="mb-3 flex items-start justify-between gap-2">
            <div>
                <h2 class="text-lg font-bold">{{ step }}) {{ title }}</h2>
                <p v-if="guidance" class="mt-1 text-xs text-gray-400">
                    {{ guidance }}
                </p>
            </div>
            <div class="shrink-0">
                <slot name="actions" />
            </div>
        </div>

        <div v-if="!isReady" class="rounded border border-amber-500/40 bg-amber-500/10 p-3 text-sm text-amber-200">
            {{ prerequisiteMessage || 'Complete the previous setup first to continue.' }}
        </div>

        <div v-else>
            <slot />
        </div>
    </section>
</template>
