<script setup>
import { computed, getCurrentInstance, ref } from 'vue';

defineProps({
    title: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
});

const isMobileFiltersOpen = ref(false);
const instance = getCurrentInstance();
const contentId = `filter-panel-${instance?.uid ?? 'main'}`;
const mobileToggleLabel = computed(() => (isMobileFiltersOpen.value ? 'إخفاء الفلاتر' : 'تصفية'));
</script>

<template>
    <section class="ui-filter-bar" :class="{ 'ui-filter-bar--mobile-open': isMobileFiltersOpen }" dir="rtl">
        <div v-if="title || description || $slots.meta || $slots.actions" class="ui-filter-header">
            <div class="ui-filter-heading">
                <h2 v-if="title" class="ui-filter-title">{{ title }}</h2>
                <p v-if="description" class="ui-filter-copy">{{ description }}</p>
            </div>

            <div v-if="$slots.meta || $slots.actions" class="ui-filter-actions">
                <slot name="meta" />
                <slot name="actions" />
            </div>
        </div>

        <button
            type="button"
            class="ui-filter-mobile-toggle"
            :aria-expanded="isMobileFiltersOpen"
            :aria-controls="contentId"
            @click="isMobileFiltersOpen = !isMobileFiltersOpen"
        >
            <span>{{ mobileToggleLabel }}</span>
            <span class="ui-filter-mobile-toggle__icon" aria-hidden="true">{{ isMobileFiltersOpen ? '×' : '⌄' }}</span>
        </button>

        <div
            :id="contentId"
            class="ui-filter-content"
            :class="title || description || $slots.meta || $slots.actions ? 'mt-4' : ''"
        >
            <slot />
        </div>

        <div v-if="$slots.footer" class="ui-filter-footer">
            <slot name="footer" />
        </div>
    </section>
</template>
