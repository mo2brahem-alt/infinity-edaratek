<script setup>
import AcademicPlanningSection from '@/Components/School/AcademicPlanning/AcademicPlanningSection.vue';

const props = defineProps({
    sectionId: {
        type: String,
        default: 'subjects-section',
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
        :step="5"
        title="Subjects and Teacher Assignment"
        guidance="Add subjects and optionally assign teachers from the same school."
        :is-ready="props.isReady"
        :prerequisite-message="props.prerequisiteMessage"
    >
        <template #actions>
            <slot name="actions" />
        </template>
        <slot />
    </AcademicPlanningSection>
</template>
