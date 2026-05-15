<script setup>
import { computed } from 'vue';
import { useProjectBranding } from '@/composables/useProjectBranding';

defineProps({
    appName: {
        type: String,
        default: 'إدارتك',
    },
    roleLabel: {
        type: String,
        default: '',
    },
});

const { siteName, projectLogoUrl } = useProjectBranding();
const currentYear = computed(() => new Date().getFullYear());
</script>

<template>
    <footer class="app-dashboard-footer border-t">
        <div class="app-dashboard-footer__inner flex w-full min-w-0 flex-col items-center gap-2 px-4 py-2.5 text-center text-[10px] leading-5 md:flex-row md:items-center md:justify-between md:px-6 md:py-3 md:text-right md:text-xs xl:px-8">
            <div class="flex items-center gap-2">
                <img :src="projectLogoUrl" :alt="siteName" class="h-6 w-6 rounded object-contain" />
                <span class="font-bold">{{ siteName || appName }}</span>
            </div>

            <p>جميع الحقوق محفوظة © {{ currentYear }}</p>
            <p>الدعم الفني: support@edaratek.com</p>
        </div>
    </footer>
</template>

<style scoped>
.app-dashboard-footer {
    border-color: var(--ui-border-soft);
    background: linear-gradient(180deg, var(--ui-surface-1), var(--ui-surface-2));
    color: var(--ui-text-muted);
}

.app-dashboard-footer__inner {
    color: var(--ui-text-muted);
}

.app-dashboard-footer strong,
.app-dashboard-footer span {
    color: var(--ui-text-primary);
}
</style>
