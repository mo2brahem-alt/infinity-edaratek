<script setup>
import AcademicPlanningSection from '@/Components/School/AcademicPlanning/AcademicPlanningSection.vue';

const props = defineProps({
    sectionId: {
        type: String,
        default: 'timetable-viewer-section',
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
    <AcademicPlanningSection
        :section-id="props.sectionId"
        :step="7"
        title="Timetable Filters and Viewer"
        guidance="Apply filters first, then review timetable rows and actions."
        :is-ready="props.isReady"
        :prerequisite-message="props.prerequisiteMessage"
    >
        <template #actions>
            <slot name="actions" />
        </template>
        <slot />
    </AcademicPlanningSection>
</template>
