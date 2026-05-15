<script setup>
import { Link } from '@inertiajs/vue3';
import AppPageFeedback from '@/Components/AppPageFeedback.vue';
import { useProjectBranding } from '@/composables/useProjectBranding';

const { siteName, projectLogoUrl } = useProjectBranding();
</script>

<template>
    <div dir="rtl" class="ui-auth-shell selection:bg-blue-500 selection:text-white">
        <div class="ui-auth-content">
            <div class="ui-auth-brand">
                <Link href="/" class="inline-flex flex-col items-center gap-3 text-center">
                    <img
                        :src="projectLogoUrl"
                        :alt="siteName"
                        class="h-20 w-20 object-contain drop-shadow-2xl transition duration-300 hover:scale-105"
                    />
                    <span class="text-2xl font-black tracking-wide text-white">{{ siteName }}</span>
                </Link>
            </div>

            <div class="ui-auth-card">
                <AppPageFeedback />
                <slot />
            </div>
        </div>
    </div>
</template>
