<script setup>
import AcademicPlanningSection from '@/Components/School/AcademicPlanning/AcademicPlanningSection.vue';

const props = defineProps({
    sectionId: {
        type: String,
        default: 'academic-year-section',
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
        :step="1"
        title="Academic Year Management"
        guidance="Start by creating at least one academic year before configuring the rest of the setup."
        :is-ready="props.isReady"
        :prerequisite-message="props.prerequisiteMessage"
    >
        <template #actions>
            <slot name="actions" />
        </template>
        <slot />
    </AcademicPlanningSection>
</template>
