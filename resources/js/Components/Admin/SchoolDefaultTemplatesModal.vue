<script setup>
import { computed, ref, watch } from 'vue';
import { X } from 'lucide-vue-next';

const props = defineProps({
    open: { type: Boolean, default: false },
    src: { type: String, default: '' },
    title: { type: String, default: 'إدارة القوالب الافتراضية' },
    description: {
        type: String,
        default: 'أنشئ القوالب العامة أو حدّثها بحسب الدولة ونوع التعليم من نافذة مستقلة تحافظ على تركيز صفحة الإعدادات.',
    },
    iframeTitle: {
        type: String,
        default: 'محرر القوالب الافتراضية المدرسية',
    },
});

defineEmits(['close']);

const iframeReady = ref(false);

watch(
    () => [props.open, props.src],
    () => {
        iframeReady.value = false;
    },
);

const frameSrc = computed(() => {
    if (!props.src) {
        return '';
    }

    try {
        const fallbackOrigin = typeof window !== 'undefined'
            ? window.location.origin
            : 'http://localhost';
        const url = new URL(props.src, fallbackOrigin);

        return `${url.pathname}${url.search}${url.hash}`;
    } catch (_error) {
        return props.src;
    }
});
</script>

<template>
    <teleport to="body">
        <div
            v-if="open"
            class="ui-theme-modal-backdrop fixed inset-0 z-[90] flex items-center justify-center p-3 sm:p-4"
            dir="rtl"
        >
            <div
                class="ui-theme-modal-panel flex max-h-[calc(100dvh-1.5rem)] w-full max-w-7xl flex-col overflow-hidden rounded-3xl border border-white/10 bg-slate-950 shadow-2xl sm:max-h-[94vh]"
                role="dialog"
                aria-modal="true"
                :aria-label="title"
            >
                <div class="ui-theme-modal-header flex items-center justify-between border-b border-white/10 px-4 py-4 sm:px-6">
                    <div class="text-right">
                        <h3 class="text-base font-black text-white sm:text-xl">{{ title }}</h3>
                        <p class="mt-1 text-xs text-slate-400 sm:text-sm">{{ description }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-xl p-2 text-slate-400 transition hover:bg-white/5 hover:text-white"
                        aria-label="إغلاق نافذة القوالب الافتراضية"
                        @click="$emit('close')"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="ui-theme-modal-body relative min-h-0 flex-1 bg-slate-950">
                    <div
                        v-if="!frameSrc"
                        class="absolute inset-0 z-[1] flex items-center justify-center bg-slate-950/95 px-6 text-center text-sm font-medium text-slate-300"
                    >
                        تعذر تجهيز محرر القالب حاليًا. أغلق النافذة ثم أعد المحاولة.
                    </div>
                    <div
                        v-else-if="!iframeReady"
                        class="absolute inset-0 z-[1] flex flex-col items-center justify-center gap-3 bg-slate-950/95 px-6 text-center"
                    >
                        <div class="h-10 w-10 animate-spin rounded-full border-2 border-cyan-500/30 border-t-cyan-400" />
                        <p class="text-sm font-bold text-white">جارٍ تجهيز محرر القالب</p>
                        <p class="max-w-md text-xs leading-6 text-slate-400">يتم الآن تحميل القالب داخل نافذة مستقلة بنفس صلاحيات الصفحة الحالية ودون مغادرة السياق الحالي.</p>
                    </div>
                    <iframe
                        v-if="frameSrc"
                        :key="frameSrc"
                        :src="frameSrc"
                        class="h-full min-h-[72dvh] w-full border-0 bg-slate-950"
                        :title="iframeTitle"
                        @load="iframeReady = true"
                    />
                </div>
            </div>
        </div>
    </teleport>
</template>
