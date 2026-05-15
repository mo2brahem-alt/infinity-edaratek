<script setup>
import { computed } from 'vue';
import { MoonStar, SunMedium } from 'lucide-vue-next';
import { useThemeMode } from '@/composables/useThemeMode';

const props = defineProps({
    size: {
        type: String,
        default: 'md',
    },
    showLabel: {
        type: Boolean,
        default: false,
    },
});

const { isLightMode, toggleThemeMode } = useThemeMode();

const buttonSizeClass = computed(() => {
    if (props.size === 'sm') return 'h-9 px-2.5 text-xs';
    if (props.size === 'lg') return 'h-11 px-3.5 text-sm';
    return 'h-10 px-3 text-sm';
});

const label = computed(() => (isLightMode.value ? 'الوضع الفاتح' : 'الوضع الداكن'));
</script>

<template>
    <button
        type="button"
        :aria-pressed="isLightMode"
        :title="`تبديل الثيم: ${label}`"
        class="theme-mode-switch inline-flex items-center gap-2 rounded-full border border-gray-700 bg-gray-800/85 text-gray-100 transition-all duration-200 hover:border-blue-500/60 hover:bg-gray-700/85 focus:outline-none focus:ring-2 focus:ring-blue-500/40"
        :class="buttonSizeClass"
        @click="toggleThemeMode"
    >
        <span class="relative inline-flex h-5 w-10 items-center rounded-full bg-gray-700 p-0.5 transition-colors" :class="isLightMode ? 'bg-amber-500/70' : 'bg-slate-700'">
            <span
                class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-white text-slate-700 shadow transition-transform duration-200"
                :class="isLightMode ? 'translate-x-5' : 'translate-x-0'"
            >
                <SunMedium v-if="isLightMode" class="h-3 w-3 text-amber-500" />
                <MoonStar v-else class="h-3 w-3 text-indigo-500" />
            </span>
        </span>

        <span v-if="showLabel" class="font-semibold">{{ label }}</span>
        <SunMedium v-else-if="isLightMode" class="h-4 w-4 text-amber-300" />
        <MoonStar v-else class="h-4 w-4 text-indigo-300" />
    </button>
</template>
