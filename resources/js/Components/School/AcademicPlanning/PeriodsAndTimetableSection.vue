<script setup>
import AcademicPlanningSection from '@/Components/School/AcademicPlanning/AcademicPlanningSection.vue';

const props = defineProps({
    sectionId: {
        type: String,
        default: 'periods-timetable-section',
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
        :step="6"
        title="Periods and Timetable Builder"
        guidance="Configure timetable versions and session periods before publishing schedules."
        :is-ready="props.isReady"
        :prerequisite-message="props.prerequisiteMessage"
    >
        <template #actions>
            <slot name="actions" />
        </template>
        <slot />
    </AcademicPlanningSection>
</template>
